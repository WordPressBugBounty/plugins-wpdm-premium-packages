<?php
/**
 * Latest Orders Dashboard Widget
 *
 * Displays the most recent completed orders.
 *
 * @package WPDMPP\Admin\Dashboard\Widgets
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Dashboard\Widgets;

use WPDMPP\Admin\Dashboard\AbstractWidget;

defined('ABSPATH') || exit;

class LatestOrdersWidget extends AbstractWidget
{
    /**
     * Widget ID
     *
     * @var string
     */
    protected string $id = 'latest_orders';

    /**
     * Widget title
     *
     * @var string
     */
    protected string $title;

    /**
     * Number of orders to display
     *
     * @var int
     */
    protected int $limit = 5;

    /**
     * Cache duration (30 minutes)
     *
     * @var int
     */
    protected int $cacheDuration = 1800;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->title = __('WPDM Latest Orders', 'wpdm-premium-packages');
    }

    /**
     * Get cache key
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return 'wpdmpp_latest_orders_' . wp_date('Y-m-d-H');
    }

    /**
     * Get widget data
     *
     * @return array
     */
    public function getData(): array
    {
        // Check cache first
        $cached = $this->getCachedData();
        if ($cached !== null) {
            return $cached;
        }

        global $wpdb;

        // Get latest completed orders
        $orders = $wpdb->get_results($wpdb->prepare("
            SELECT order_id, uid, total, date
            FROM {$wpdb->prefix}ahm_orders
            WHERE payment_status = 'Completed'
            ORDER BY date DESC
            LIMIT %d
        ", $this->limit));

        // Collect user IDs for batch fetching
        $userIds = [];
        foreach ($orders as $order) {
            if (!empty($order->uid)) {
                $userIds[] = (int) $order->uid;
            }
        }

        // Pre-fetch users to avoid N+1 queries
        if (!empty($userIds)) {
            cache_users(array_unique($userIds));
        }

        // Process orders with user data
        $processedOrders = [];
        foreach ($orders as $order) {
            $user = $order->uid ? get_userdata($order->uid) : null;

            $processedOrders[] = [
                'order_id' => $order->order_id,
                'user_id' => $order->uid,
                'total' => (float) $order->total,
                'date' => (int) $order->date,
                'time_ago' => $this->timeAgo((int) $order->date),
                'user' => $user ? [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'avatar' => get_avatar_url($user->ID, ['size' => 40]),
                ] : [
                    'id' => 0,
                    'name' => __('Guest', 'wpdm-premium-packages'),
                    'email' => '',
                    'avatar' => get_avatar_url(0, ['size' => 40]),
                ],
                'order_url' => admin_url('edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $order->order_id),
            ];
        }

        $data = [
            'orders' => $processedOrders,
            'currency' => wpdmpp_currency_sign(),
        ];

        // Cache the data
        $this->setCachedData($data);

        return $data;
    }

    /**
     * Render the widget content
     *
     * @return void
     */
    public function render(): void
    {
        $data = $this->getData();
        $templatePath = $this->getTemplatePath();

        if (file_exists($templatePath)) {
            // Pass data to template
            $orders = $data['orders'];
            $currency = $data['currency'];
            include $templatePath;
        } else {
            $this->renderFallback($data);
        }
    }

    /**
     * Render fallback content if template is missing
     *
     * @param array $data
     * @return void
     */
    private function renderFallback(array $data): void
    {
        $orders = $data['orders'];
        $currency = $data['currency'];

        if (empty($orders)) {
            echo '<div style="padding: 20px; text-align: center; color: #64748b;">';
            esc_html_e('No orders yet', 'wpdm-premium-packages');
            echo '</div>';
            return;
        }
        ?>
        <div style="padding: 0;">
            <?php foreach ($orders as $order): ?>
                <a href="<?php echo esc_url($order['order_url']); ?>" style="display: flex; align-items: center; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-decoration: none; gap: 10px;">
                    <img src="<?php echo esc_url($order['user']['avatar']); ?>" alt="" style="width: 36px; height: 36px; border-radius: 50%;">
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: #1e293b; font-size: 13px;"><?php echo esc_html($order['user']['name']); ?></div>
                        <div style="font-size: 11px; color: #64748b;">#<?php echo esc_html($order['order_id']); ?> &bull; <?php echo esc_html($order['time_ago']); ?></div>
                    </div>
                    <div style="font-weight: 700; color: #10b981; font-size: 14px;"><?php echo esc_html($currency . number_format($order['total'], 2)); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Get orders count for different statuses
     *
     * @return array
     */
    public function getOrderCounts(): array
    {
        global $wpdb;

        $counts = $wpdb->get_results("
            SELECT payment_status, COUNT(*) as count
            FROM {$wpdb->prefix}ahm_orders
            GROUP BY payment_status
        ", OBJECT_K);

        return [
            'completed' => isset($counts['Completed']) ? (int) $counts['Completed']->count : 0,
            'pending' => isset($counts['Pending']) ? (int) $counts['Pending']->count : 0,
            'expired' => isset($counts['Expired']) ? (int) $counts['Expired']->count : 0,
            'cancelled' => isset($counts['Cancelled']) ? (int) $counts['Cancelled']->count : 0,
        ];
    }
}
