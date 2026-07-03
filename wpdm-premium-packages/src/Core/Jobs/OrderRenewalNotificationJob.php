<?php
/**
 * Order Renewal Notification Job
 *
 * Sends reminder emails to customers about expiring orders/subscriptions.
 *
 * @package WPDMPP\Core\Jobs
 * @since 7.0.0
 */

namespace WPDMPP\Core\Jobs;

use WPDM\__\Jobs\Job;
use WPDM\__\Email;
use WPDMPP\Order\OrderService;

defined('ABSPATH') || exit;

class OrderRenewalNotificationJob extends Job
{
    /**
     * Execute the renewal notification job
     *
     * @param object|array $data Job payload
     * @return bool
     */
    public function handle($data): bool
    {
        global $wpdb, $wpdmpp_settings;

        $this->log('Starting order renewal notification job');

        // Check if order expiry alert is enabled
        if (!isset($wpdmpp_settings['order_expiry_alert']) || (int) $wpdmpp_settings['order_expiry_alert'] !== 1) {
            $this->log('Order expiry alert is disabled, skipping');
            return true;
        }

        // Get orders expiring in 8 days
        $daysAhead = $this->get('days_ahead', 8);
        $date = date('Y-m-d', strtotime("+{$daysAhead} days"));
        $startTime = strtotime($date . ' 00:00');
        $endTime = strtotime($date . ' 23:59');

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_orders WHERE expire_date >= %d AND expire_date <= %d",
            $startTime,
            $endTime
        ));

        if (empty($orders)) {
            $this->log('No orders expiring on ' . $date);
            return true;
        }

        // Get notification tracking
        $trackingKey = '__wpdmpp_order_renewal_notifs_' . date('Y_m');
        $sentNotifications = maybe_unserialize(get_option($trackingKey, []));
        if (!is_array($sentNotifications)) {
            $sentNotifications = [];
        }

        $mailed = 0;
        $totalAutoRenew = 0;
        $totalManualRenew = 0;
        $adminMessages = [];
        $siteName = get_bloginfo('name');

        foreach ($orders as $order) {
            $notificationKey = $order->order_id . '_' . $order->expire_date;

            // Skip if already notified
            if (isset($sentNotifications[$notificationKey])) {
                // Update auto_renew flag if needed
                OrderService::instance()->updateOrder(['auto_renew' => 1], $order->order_id);
                continue;
            }

            // Skip 2Checkout orders (handled separately)
            if ($order->payment_method === 'WPDM_2Checkout') {
                continue;
            }

            // Track totals
            if ((int) $order->auto_renew === 1) {
                $totalAutoRenew += (float) $order->total;
            } else {
                $totalManualRenew += (float) $order->total;
            }

            // Prepare order data
            $billingInfo = maybe_unserialize($order->billing_info);
            $currency = maybe_unserialize($order->currency);
            $currencySign = isset($currency['sign']) ? $currency['sign'] : '$';
            $user = get_user_by('id', $order->uid);

            if (!$user) {
                $this->log("User not found for order {$order->order_id}, skipping");
                continue;
            }

            $expireDate = date(get_option('date_format'), $order->expire_date - 82800);
            $orderUrl = wpdmpp_orders_page('id=' . $order->order_id);

            // Build order items table
            $items = OrderService::instance()->getOrderItemsAsArrays($order->order_id);
            $itemsHtml = $this->buildItemsTable($items, $currencySign);

            // Email parameters
            $params = [
                'subject' => sprintf('[%s] Automatic Order Renewal', $siteName),
                'to_email' => $user->user_email,
                'expire_date' => $expireDate,
                'orderid' => $order->order_id,
                'order_url' => $orderUrl,
                'items' => $itemsHtml,
                'order_items' => $itemsHtml,
            ];

            // Send appropriate email template
            if ((int) $order->auto_renew === 1) {
                Email::send('subscription-reminder', $params);
            } else {
                Email::send('order-expire', $params);
            }

            $mailed++;
            $sentNotifications[$notificationKey] = 1;

            // Track for admin notification
            $adminViewUrl = admin_url("edit.php?post_type=wpdmpro&page=orders&task=vieworder&id={$order->order_id}");
            $adminMessages[] = [
                'order_id' => $order->order_id,
                'total' => $currencySign . $order->total,
                'url' => $adminViewUrl,
            ];
        }

        // Save notification tracking
        update_option($trackingKey, $sentNotifications, false);

        // Send admin summary if any emails were sent
        if ($mailed > 0) {
            $this->sendAdminNotification($adminMessages, $totalAutoRenew, $totalManualRenew, $date, $siteName);
        }

        $this->log("Sent {$mailed} renewal notifications for orders expiring on {$date}");
        return true;
    }

    /**
     * Build HTML table of order items
     *
     * @param array  $items        Order items
     * @param string $currencySign Currency symbol
     * @return string HTML table
     */
    private function buildItemsTable(array $items, string $currencySign): string
    {
        $html = "<table class='email' style='width: 100%;border: 0;margin-top: 15px' cellpadding='0' cellspacing='0'>";
        $html .= '<tr><th>Product Name</th><th>License</th><th style="width:80px;text-align:right">Price</th></tr>';

        foreach ($items as $item) {
            $product = get_post($item['pid']);
            if (!$product) {
                continue;
            }

            $license = maybe_unserialize($item['license']);
            $licenseName = is_array($license) && isset($license['info'], $license['info']['name'])
                ? $license['info']['name']
                : '&mdash;';
            $price = $currencySign . number_format($item['price'], 2);

            $html .= sprintf(
                "<tr><td><a href='%s'>%s</a></td><td>%s</td><td align='right' style='width:80px;text-align:right'>%s</td></tr>",
                esc_url(get_permalink($product->ID)),
                esc_html($product->post_title),
                esc_html($licenseName),
                esc_html($price)
            );
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Send admin notification summary
     *
     * @param array  $messages       Array of order info
     * @param float  $totalAutoRenew Total auto-renewal amount
     * @param float  $totalManual    Total manual renewal amount
     * @param string $date           Renewal date
     * @param string $siteName       Site name
     */
    private function sendAdminNotification(array $messages, float $totalAutoRenew, float $totalManual, string $date, string $siteName): void
    {
        $currencySign = wpdmpp_currency_sign();

        $msgHtml = __('Order Expiration and Subscription reminder email sent for the following orders:', 'wpdm-premium-packages') . '<br/>';
        $msgHtml .= "<table style='width:100%' class='email' cellspacing='0'>";

        foreach ($messages as $msg) {
            $msgHtml .= sprintf(
                "<tr style='border-bottom: #ebf0f5'><td><a href='%s'>%s</a></td><td align='right' style='text-align:right'>%s</td></tr>",
                esc_url($msg['url']),
                esc_html($msg['order_id']),
                esc_html($msg['total'])
            );
        }

        $msgHtml .= sprintf(
            "<tr style='background: #ebf0f5'><th>%s</th><th align='right' style='text-align:right'>%s</th></tr>",
            __('Total Auto-renewal Amount', 'wpdm-premium-packages'),
            $currencySign . number_format($totalAutoRenew, 2)
        );
        $msgHtml .= sprintf(
            "<tr><th>%s</th><th align='right' style='text-align:right'>%s</th></tr>",
            __('Total Manual-renewal Amount', 'wpdm-premium-packages'),
            $currencySign . number_format($totalManual, 2)
        );
        $msgHtml .= sprintf(
            "<tr style='background: #ebf0f5'><th>%s</th><th align='right' style='text-align:right'>%s</th></tr></table>",
            __('Renewal Date', 'wpdm-premium-packages'),
            $date
        );

        $params = [
            'subject' => sprintf(__('[%s] Order Expiration and Subscription reminder sent.', 'wpdm-premium-packages'), $siteName),
            'to_email' => get_option('admin_email'),
            'message' => $msgHtml,
        ];

        Email::send('default', $params);
    }

    /**
     * Schedule this job
     *
     * @return int|false Job ID or false on failure
     */
    public static function schedule(): int|false
    {
        if (!class_exists('\WPDM\__\CronJob')) {
            return false;
        }

        return \WPDM\__\CronJob::create(
            self::class,
            [],
            0,                              // Execute now
            0,                              // Infinite repeat
            21600,                          // Every 6 hours
            'premium-packages',             // Queue name
            5,                              // Priority
            3,                              // Max attempts
            'wpdmpp_order_renewal_notification'  // Unique code
        );
    }

    /**
     * Get job name
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'Order Renewal Notification';
    }

    /**
     * Get job description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Sends reminder emails to customers about expiring orders and subscriptions.';
    }
}
