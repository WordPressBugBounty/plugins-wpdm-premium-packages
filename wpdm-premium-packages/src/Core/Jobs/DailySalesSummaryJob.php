<?php
/**
 * Daily Sales Summary Job
 *
 * Sends a daily sales summary email to the site administrator.
 *
 * @package WPDMPP\Core\Jobs
 * @since 7.0.0
 */

namespace WPDMPP\Core\Jobs;

use WPDM\__\Jobs\Job;
use WPDM\__\Email;
use WPDM\__\__MailUI;

defined('ABSPATH') || exit;

class DailySalesSummaryJob extends Job
{
    /**
     * Execute the daily sales summary job
     *
     * @param object|array $data Job payload
     * @return bool
     */
    public function handle($data): bool
    {
        global $wpdb;

        $this->log('Starting daily sales summary job');

        // Check if already sent today
        $lastSent = get_option('__wpdmpp_ssm_sent');
        if ($lastSent && date('Ymd', $lastSent) === date('Ymd')) {
            $this->log('Daily sales summary already sent today');
            return true;
        }

        // Get yesterday's date range
        $yesterdayStart = strtotime('yesterday 00:00:00');
        $yesterdayEnd = strtotime('yesterday 23:59:59');

        // Get new completed orders
        $newOrders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_orders
             WHERE `date` >= %d AND `date` <= %d
             AND order_status = 'Completed'
             AND payment_status = 'Completed'",
            $yesterdayStart,
            $yesterdayEnd
        ));

        // Get renewed orders
        $renewedOrders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_order_renews
             WHERE `date` >= %d AND `date` <= %d",
            $yesterdayStart,
            $yesterdayEnd
        ));

        // Build summary data
        $totalSales = 0;
        $orderCount = count($newOrders);
        $renewCount = count($renewedOrders);
        $tableData = [];

        foreach ($newOrders as $order) {
            $tableData[] = [
                __('New', 'wpdm-premium-packages'),
                $order->order_id,
                wpdmpp_price_format($order->total),
            ];
            $totalSales += (float) $order->total;
        }

        foreach ($renewedOrders as $order) {
            $tableData[] = [
                __('Renew', 'wpdm-premium-packages'),
                $order->order_id,
                wpdmpp_price_format($order->total),
            ];
            $totalSales += (float) $order->total;
        }

        // Skip if no sales
        if ($orderCount === 0 && $renewCount === 0) {
            $this->log('No sales yesterday, skipping summary email');
            update_option('__wpdmpp_ssm_sent', time(), false);
            return true;
        }

        // Build email content
        $formattedTotal = wpdmpp_price_format($totalSales);
        $table = $this->buildTable(
            [__('Type', 'wpdm-premium-packages'), __('Order ID', 'wpdm-premium-packages'), __('Amount', 'wpdm-premium-packages')],
            $tableData
        );

        $totalCard = $this->buildStatCard(__('Total Sales', 'wpdm-premium-packages'), $formattedTotal);
        $newCard = $this->buildStatCard(__('New Purchases', 'wpdm-premium-packages'), (string) $orderCount);
        $renewCard = $this->buildStatCard(__('Renewals', 'wpdm-premium-packages'), (string) $renewCount);

        $message = __("Here's a quick snapshot of yesterday's sales performance:", 'wpdm-premium-packages');
        $message .= '<br/><br/>';
        $message .= "<table style='width:100%;'><tr><td>{$totalCard}</td><td>{$newCard}</td><td>{$renewCard}</td></tr></table>";
        $message .= '<br/>' . $table;

        // Send email
        $params = [
            'subject' => sprintf(
                __('[%s] Daily Sales Overview - %s', 'wpdm-premium-packages'),
                get_bloginfo('name'),
                date(get_option('date_format'), strtotime('yesterday'))
            ),
            'to_email' => get_option('admin_email'),
            'message' => $message,
        ];

        Email::send('default', $params);

        // Mark as sent
        update_option('__wpdmpp_ssm_sent', time(), false);

        $this->log("Daily sales summary sent: {$orderCount} new orders, {$renewCount} renewals, {$formattedTotal} total");

        /**
         * Fires after daily sales summary is sent
         *
         * @param float $totalSales  Total sales amount
         * @param int   $orderCount  Number of new orders
         * @param int   $renewCount  Number of renewals
         */
        do_action('wpdmpp_daily_sales_summary_sent', $totalSales, $orderCount, $renewCount);

        return true;
    }

    /**
     * Build HTML table
     *
     * @param array $headers Table headers
     * @param array $data    Table data rows
     * @return string HTML table
     */
    private function buildTable(array $headers, array $data): string
    {
        // Try to use WPDM's MailUI if available
        if (class_exists('\WPDM\__\__MailUI')) {
            return __MailUI::table($headers, $data, [
                'th' => 'background: #f5f5f5;padding: 10px 5px;text-align:left;',
                'td' => 'border-bottom: 1px solid #f5f5f5;padding:8px 5px',
            ]);
        }

        // Fallback to basic table
        $html = '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<tr>';
        foreach ($headers as $header) {
            $html .= '<th style="background:#f5f5f5;padding:10px 5px;text-align:left;">' . esc_html($header) . '</th>';
        }
        $html .= '</tr>';

        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td style="border-bottom:1px solid #f5f5f5;padding:8px 5px;">' . esc_html($cell) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Build stat card HTML
     *
     * @param string $label Card label
     * @param string $value Card value
     * @return string HTML
     */
    private function buildStatCard(string $label, string $value): string
    {
        // Try to use WPDM's MailUI if available
        if (class_exists('\WPDM\__\__MailUI')) {
            return __MailUI::panel($label, ["<h1 style='margin: 0'>{$value}</h1>"]);
        }

        // Fallback to basic panel
        return sprintf(
            '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;text-align:center;">
                <div style="font-size:12px;color:#64748b;margin-bottom:8px;">%s</div>
                <div style="font-size:24px;font-weight:bold;color:#1e293b;">%s</div>
            </div>',
            esc_html($label),
            esc_html($value)
        );
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
            21600,                          // Every 6 hours (but only sends once per day due to check)
            'premium-packages',             // Queue name
            5,                              // Priority
            3,                              // Max attempts
            'wpdmpp_daily_sales_summary'    // Unique code
        );
    }

    /**
     * Get job name
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'Daily Sales Summary';
    }

    /**
     * Get job description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Sends a daily sales summary email to the site administrator with order and renewal statistics.';
    }
}
