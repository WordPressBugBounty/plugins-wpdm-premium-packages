<?php
/**
 * Abandoned Order Recovery Service
 *
 * Handles abandoned cart/order recovery by queuing and sending
 * recovery emails to customers who didn't complete their purchase.
 *
 * @package WPDMPP\Order
 * @since 7.0.0
 */

namespace WPDMPP\Order;

use WPDM\__\Email;
use WPDM\__\Session;
use WPDMPP\Order\OrderService;

defined('ABSPATH') || exit;

class AbandonedOrderService {

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Database prefix
     *
     * @var string
     */
    private string $dbPrefix;

    /**
     * ACR emails table name
     *
     * @var string
     */
    private string $acrTable;

    /**
     * Orders table name
     *
     * @var string
     */
    private string $ordersTable;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        global $wpdb;
        $this->dbPrefix = $wpdb->prefix;
        $this->acrTable = $this->dbPrefix . 'ahm_acr_emails';
        $this->ordersTable = $this->dbPrefix . 'ahm_orders';
    }

    /**
     * Register hooks for abandoned order recovery
     *
     * @return void
     */
    public function register(): void {
        add_action('init', [$this, 'handleRecoveryRequest']);
    }

    /**
     * Handle recovery-related URL requests
     *
     * @return void
     */
    public function handleRecoveryRequest(): void {
        // Check for cart reload URL pattern (/acr/ORDER_ID/)
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/acr/') !== false) {
            $this->reloadCartFromRecoveryLink();
        }

        // Check for cron-triggered recovery processing
        if (function_exists('wpdm_query_var') && wpdm_query_var('acre')) {
            $this->processQueue();
            $this->sendRecoveryEmails();
            wp_die('Done!');
        }
    }

    /**
     * Get recovery settings
     *
     * @return array
     */
    public function getSettings(): array {
        $emailCount = (int) get_wpdmpp_option('acre_count', 0);
        $intervals = get_wpdmpp_option('acre_interval', '1,3,7');

        // Parse intervals
        $intervalArray = array_map('trim', explode(',', $intervals));
        $intervalArray = array_filter($intervalArray, 'is_numeric');

        // Fill missing intervals (multiply by 2 for each subsequent stage)
        if (!empty($intervalArray)) {
            $baseInterval = (int) $intervalArray[0];
            for ($i = 1; $i < $emailCount; $i++) {
                if (!isset($intervalArray[$i])) {
                    $intervalArray[$i] = $baseInterval * ($i + 1);
                }
            }
        }

        return [
            'enabled' => $emailCount > 0,
            'email_count' => $emailCount,
            'intervals' => array_map('intval', $intervalArray),
        ];
    }

    /**
     * Check if recovery is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool {
        return (int) get_wpdmpp_option('acre_count', 0) > 0;
    }

    /**
     * Process abandoned orders queue
     *
     * Collects orders in "Processing" status from last 24 hours
     * and schedules recovery emails for each stage.
     *
     * @return int Number of orders queued
     */
    public function processQueue(): int {
        // Verify cron key
        if (!$this->verifyCronKey('acrq_key')) {
            return 0;
        }

        $settings = $this->getSettings();
        if (!$settings['enabled']) {
            return 0;
        }

        global $wpdb;

        // Get abandoned orders from last ~25 hours (90000 seconds)
        $cutoffTime = time() - 90000;

        $abandonedOrders = $wpdb->get_results($wpdb->prepare(
            "SELECT order_id, uid, billing_info, date
             FROM {$this->ordersTable}
             WHERE date >= %d
             AND order_status = 'Processing'
             ORDER BY date DESC",
            $cutoffTime
        ));

        $queuedCount = 0;

        foreach ($abandonedOrders as $order) {
            $customerInfo = $this->extractCustomerInfo($order);

            if (empty($customerInfo['email'])) {
                continue;
            }

            $orderDate = wp_date('Ymd', $order->date);

            // Queue recovery emails for each stage
            foreach ($settings['intervals'] as $stageIndex => $days) {
                $stage = $stageIndex + 1;
                $emailDate = wp_date('Ymd', strtotime("+{$days} days"));

                $result = $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$this->acrTable}
                     SET order_id = %s,
                         user_id = %d,
                         name = %s,
                         email = %s,
                         order_date = %s,
                         stage = %d,
                         email_date = %s,
                         activity_log = %s,
                         sent = 0",
                    $order->order_id,
                    $order->uid,
                    $customerInfo['name'],
                    $customerInfo['email'],
                    $orderDate,
                    $stage,
                    $emailDate,
                    '[]'
                ));

                if ($result) {
                    $queuedCount++;
                }
            }
        }

        do_action('wpdmpp_abandoned_orders_queued', $queuedCount);

        return $queuedCount;
    }

    /**
     * Send recovery emails for scheduled items
     *
     * @return int Number of emails sent
     */
    public function sendRecoveryEmails(): int {
        // Verify cron key
        if (!$this->verifyCronKey('acre_key')) {
            return 0;
        }

        $settings = $this->getSettings();
        if (!$settings['enabled']) {
            return 0;
        }

        global $wpdb;

        $emailsSent = 0;
        $today = wp_date('Ymd');

        foreach ($settings['intervals'] as $stageIndex => $interval) {
            $stage = $stageIndex + 1;

            // Get pending emails for this stage scheduled for today
            $pendingEmails = $wpdb->get_results($wpdb->prepare(
                "SELECT *
                 FROM {$this->acrTable}
                 WHERE stage = %d
                 AND email_date = %s
                 AND sent = 0",
                $stage,
                $today
            ));

            foreach ($pendingEmails as $pendingEmail) {
                $result = $this->processRecoveryEmail($pendingEmail, $stage);

                if ($result) {
                    $emailsSent++;

                    // Rate limit: 10 emails per second
                    if ($emailsSent % 10 === 0) {
                        sleep(1);
                    }
                }
            }
        }

        do_action('wpdmpp_recovery_emails_sent', $emailsSent);

        return $emailsSent;
    }

    /**
     * Process a single recovery email
     *
     * @param object $emailRecord Email record from database
     * @param int    $stage       Recovery stage number
     * @return bool True if processed successfully
     */
    private function processRecoveryEmail(object $emailRecord, int $stage): bool {
        global $wpdb;

        // Get current order status
        $order = OrderService::instance()->getOrder($emailRecord->order_id);
        $activityLog = json_decode($emailRecord->activity_log ?: '[]', true) ?: [];

        if ($order && $order->getOrderStatus() === 'Completed') {
            // Order was completed - send confirmation to admin
            return $this->handleRecoveredOrder($emailRecord, $order, $activityLog);
        }

        // Order still pending - send recovery email
        return $this->sendRecoveryEmailToCustomer($emailRecord, $order, $stage, $activityLog);
    }

    /**
     * Handle a recovered order (customer completed payment)
     *
     * @param object      $emailRecord Email record
     * @param Order       $order       Order object
     * @param array       $activityLog Activity log
     * @return bool
     */
    private function handleRecoveredOrder(object $emailRecord, Order $order, array $activityLog): bool {
        global $wpdb;

        $orderUrl = admin_url("/edit.php?post_type=wpdmpro&page=orders&task=vieworder&id={$emailRecord->order_id}");

        $params = [
            'name' => $emailRecord->name,
            'order_date' => wp_date(get_option('date_format'), strtotime($emailRecord->order_date)),
            'orderid' => $emailRecord->order_id,
            'items' => OrderService::instance()->buildItemsTableHtml($emailRecord->order_id),
            'order_url' => $orderUrl,
        ];

        if (class_exists('\WPDM\__\Email')) {
            Email::send('recovered-order-confirmation', $params);
        }

        // Update activity log (store an int timestamp, consistent with the other writer)
        $activityLog[time()] = [
            'msg' => __('Congratulation! Customer completed the payment', 'wpdm-premium-packages'),
            'time' => time(),
        ];

        $wpdb->update(
            $this->acrTable,
            [
                'activity_log' => wp_json_encode($activityLog),
                'sent' => 1,
            ],
            ['ID' => $emailRecord->ID]
        );

        do_action('wpdmpp_order_recovered', $emailRecord->order_id);

        return true;
    }

    /**
     * Send recovery email to customer
     *
     * @param object      $emailRecord Email record
     * @param Order       $order       Order object
     * @param int         $stage       Recovery stage
     * @param array       $activityLog Activity log
     * @return bool
     */
    private function sendRecoveryEmailToCustomer(object $emailRecord, Order $order, int $stage, array $activityLog): bool {
        global $wpdb;

        $checkoutUrl = home_url("/acr/{$emailRecord->order_id}/");

        $params = [
            'name' => $emailRecord->name,
            'to_email' => $emailRecord->email,
            'order_date' => wp_date(get_option('date_format'), strtotime($emailRecord->order_date)),
            'orderid' => $emailRecord->order_id,
            'items' => OrderService::instance()->buildItemsTableHtml($emailRecord->order_id),
            'checkout_url' => $checkoutUrl,
        ];

        if (class_exists('\WPDM\__\Email')) {
            Email::send("order-recovery-email-{$stage}", $params);
        }

        // Update activity log
        $activityLog[time()] = [
            'msg' => sprintf(__('Step #%s email sent successfully', 'wpdm-premium-packages'), $stage),
            'time' => time(),
        ];

        $wpdb->update(
            $this->acrTable,
            [
                'sent' => 1,
                'activity_log' => wp_json_encode($activityLog),
            ],
            ['ID' => $emailRecord->ID]
        );

        do_action('wpdmpp_recovery_email_sent', $emailRecord->order_id, $stage);

        return true;
    }

    /**
     * Reload cart from recovery link
     *
     * Called when customer clicks a recovery link like /acr/WPDMPP123456/
     *
     * @return void
     */
    public function reloadCartFromRecoveryLink(): void {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        // Extract order ID from URL
        if (!preg_match('/WPDM[A-Z0-9]+/', $_SERVER['REQUEST_URI'], $matches)) {
            return;
        }

        $orderId = $matches[0];
        $order = OrderService::instance()->getOrder($orderId);

        if (!$order) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        // Store order ID in session
        if (class_exists('\WPDM\__\Session')) {
            Session::set('orderid', $orderId);
        }

        // Reload cart with order items
        $cartData = $order->getCartData();
        if (function_exists('wpdmpp_update_cart_data') && !empty($cartData)) {
            wpdmpp_update_cart_data($cartData);
        }

        // Log recovery attempt
        $this->logRecoveryAttempt($orderId);

        // Redirect to cart
        $cartUrl = function_exists('wpdmpp_cart_url') ? wpdmpp_cart_url() : home_url('/cart/');
        wp_safe_redirect($cartUrl);
        exit;
    }

    /**
     * Log a recovery attempt
     *
     * @param string $orderId Order ID
     * @return void
     */
    private function logRecoveryAttempt(string $orderId): void {
        global $wpdb;

        // Update activity log for all stages of this order
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, activity_log FROM {$this->acrTable} WHERE order_id = %s",
            $orderId
        ));

        foreach ($records as $record) {
            $activityLog = json_decode($record->activity_log ?: '[]', true) ?: [];
            $activityLog[time()] = [
                'msg' => __('Customer clicked recovery link', 'wpdm-premium-packages'),
                'time' => time(),
            ];

            $wpdb->update(
                $this->acrTable,
                ['activity_log' => wp_json_encode($activityLog)],
                ['ID' => $record->ID]
            );
        }

        do_action('wpdmpp_recovery_link_clicked', $orderId);
    }

    /**
     * Extract customer info from order
     *
     * @param object $order Order object from database
     * @return array ['name' => string, 'email' => string]
     */
    private function extractCustomerInfo(object $order): array {
        $billing = maybe_unserialize($order->billing_info);

        $email = '';
        $name = '';

        // Try billing info first
        if (is_array($billing)) {
            $email = $billing['order_email'] ?? $billing['email'] ?? '';
            $name = $billing['first_name'] ?? '';
        }

        // Fall back to user data
        if (!is_email($email) && (int) $order->uid > 0) {
            $user = get_user_by('id', $order->uid);
            if ($user) {
                $email = $user->user_email;
                $name = $user->display_name;
            }
        }

        return [
            'name' => $name,
            'email' => $email,
        ];
    }

    /**
     * Verify cron key
     *
     * @param string $keyParam Query parameter name
     * @return bool
     */
    private function verifyCronKey(string $keyParam): bool {
        if (!function_exists('wpdm_query_var')) {
            return false;
        }

        $providedKey = wpdm_query_var($keyParam);

        if (empty($providedKey)) {
            return false;
        }

        if (!function_exists('WPDM') || !isset(WPDM()->cronJob)) {
            return false;
        }

        return $providedKey === WPDM()->cronJob->cronKey();
    }

    /**
     * Get pending recovery emails count
     *
     * @return int
     */
    public function getPendingCount(): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->acrTable} WHERE sent = 0"
        );
    }

    /**
     * Get recovery statistics
     *
     * @return array
     */
    public function getStatistics(): array {
        global $wpdb;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->acrTable}");
        $sent = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->acrTable} WHERE sent = 1");
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->acrTable} WHERE sent = 0");

        // Count recovered orders (orders that were completed after recovery emails)
        $recovered = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT acr.order_id)
             FROM {$this->acrTable} acr
             INNER JOIN {$this->ordersTable} o ON acr.order_id = o.order_id
             WHERE o.order_status = 'Completed'
             AND acr.sent = 1"
        );

        return [
            'total_queued' => $total,
            'emails_sent' => $sent,
            'pending' => $pending,
            'orders_recovered' => $recovered,
            'recovery_rate' => $total > 0 ? round(($recovered / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get recovery emails for an order
     *
     * @param string $orderId Order ID
     * @return array
     */
    public function getOrderRecoveryEmails(string $orderId): array {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$this->acrTable}
             WHERE order_id = %s
             ORDER BY stage ASC",
            $orderId
        ));

        return array_map(function ($row) {
            $row->activity_log = json_decode($row->activity_log ?: '[]', true);
            return $row;
        }, $results);
    }

    /**
     * Cancel pending recovery emails for an order
     *
     * Called when order is completed to prevent further recovery emails.
     *
     * @param string $orderId Order ID
     * @return int Number of cancelled emails
     */
    public function cancelPendingEmails(string $orderId): int {
        global $wpdb;

        return (int) $wpdb->update(
            $this->acrTable,
            ['sent' => 1],
            [
                'order_id' => $orderId,
                'sent' => 0,
            ]
        );
    }

    /**
     * Delete old recovery records
     *
     * @param int $olderThanDays Delete records older than this many days
     * @return int Number of deleted records
     */
    public function cleanup(int $olderThanDays = 90): int {
        global $wpdb;

        $cutoffDate = wp_date('Ymd', strtotime("-{$olderThanDays} days"));

        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->acrTable} WHERE order_date < %s",
            $cutoffDate
        ));
    }

    /**
     * Generate recovery URL for an order
     *
     * @param string $orderId Order ID
     * @return string
     */
    public function getRecoveryUrl(string $orderId): string {
        return home_url("/acr/{$orderId}/");
    }
}
