<?php
/**
 * Recent Sales Dashboard Widget
 *
 * Displays the most recently sold products (individual items).
 *
 * @package WPDMPP\Admin\Dashboard\Widgets
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Dashboard\Widgets;

use WPDMPP\Admin\Dashboard\AbstractWidget;

defined('ABSPATH') || exit;

class RecentSalesWidget extends AbstractWidget
{
    /**
     * Widget ID
     *
     * @var string
     */
    protected string $id = 'recent_sales';

    /**
     * Widget title
     *
     * @var string
     */
    protected string $title;

    /**
     * Number of items to display
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
        $this->title = __('WPDM Recent Sales', 'wpdm-premium-packages');
    }

    /**
     * Get cache key
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return 'wpdmpp_recent_sales_' . wp_date('Y-m-d-H');
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

        // Get latest sold items with order info
        // Use payment_status (not order_status) for sales calculations
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT oi.*, o.date, o.uid
            FROM {$wpdb->prefix}ahm_order_items oi
            INNER JOIN {$wpdb->prefix}ahm_orders o ON o.order_id = oi.oid
            WHERE (o.payment_status = 'Completed' OR o.payment_status = 'Expired')
            ORDER BY o.date DESC
            LIMIT %d
        ", $this->limit));

        // Collect product IDs for batch fetching
        $productIds = [];
        foreach ($items as $item) {
            if (!empty($item->pid)) {
                $productIds[] = (int) $item->pid;
            }
        }

        // Batch fetch product data
        $productsData = $this->batchFetchPosts($productIds);

        // Process items
        $processedItems = [];
        foreach ($items as $item) {
            $productInfo = isset($productsData[$item->pid]) ? $productsData[$item->pid] : null;
            $isDeleted = !$productInfo;

            $processedItems[] = [
                'product_id' => (int) $item->pid,
                'order_id' => $item->oid,
                'price' => (float) $item->price,
                'quantity' => isset($item->quantity) ? (int) $item->quantity : 1,
                'date' => (int) $item->date,
                'time_ago' => $this->timeAgo((int) $item->date),
                'product' => $productInfo ? [
                    'title' => $productInfo['title'],
                    'thumbnail' => $productInfo['thumbnail'],
                ] : null,
                'is_deleted' => $isDeleted,
                'order_url' => admin_url('edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $item->oid),
            ];
        }

        $data = [
            'items' => $processedItems,
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
            $latest_items = $data['items'];
            $currency = $data['currency'];
            // The existing template expects posts_data array, let's adapt
            $posts_data = [];
            foreach ($data['items'] as $item) {
                if ($item['product']) {
                    $posts_data[$item['product_id']] = [
                        'title' => $item['product']['title'],
                        'thumbnail' => $item['product']['thumbnail'],
                    ];
                }
            }
            // Convert back to raw format expected by template
            $latest_items = [];
            foreach ($data['items'] as $item) {
                $latest_items[] = (object) [
                    'pid' => $item['product_id'],
                    'oid' => $item['order_id'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'date' => $item['date'],
                ];
            }
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
        $items = $data['items'];
        $currency = $data['currency'];

        if (empty($items)) {
            echo '<div style="padding: 20px; text-align: center; color: #64748b;">';
            esc_html_e('No recent sales yet', 'wpdm-premium-packages');
            echo '</div>';
            return;
        }
        ?>
        <div style="padding: 0;">
            <?php foreach ($items as $item): ?>
                <a href="<?php echo esc_url($item['order_url']); ?>" style="display: flex; align-items: center; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; text-decoration: none; gap: 10px;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; background: <?php echo $item['is_deleted'] ? '#fef2f2' : '#eef2ff'; ?>; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if ($item['product'] && $item['product']['thumbnail']): ?>
                            <img src="<?php echo esc_url($item['product']['thumbnail']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="<?php echo $item['is_deleted'] ? '#ef4444' : '#6366f1'; ?>" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: <?php echo $item['is_deleted'] ? '#ef4444' : '#1e293b'; ?>; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo $item['is_deleted'] ? esc_html__('Item Deleted', 'wpdm-premium-packages') : esc_html($item['product']['title']); ?>
                        </div>
                        <div style="font-size: 11px; color: #64748b;">#<?php echo esc_html($item['order_id']); ?> &bull; <?php echo esc_html($item['time_ago']); ?></div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 700; color: #10b981; font-size: 14px;"><?php echo esc_html($currency . number_format($item['price'], 2)); ?></div>
                        <?php if ($item['quantity'] > 1): ?>
                            <div style="font-size: 11px; color: #64748b;"><?php printf(esc_html__('Qty: %d', 'wpdm-premium-packages'), $item['quantity']); ?></div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Get sales count by product
     *
     * @param int $productId
     * @return int
     */
    public function getProductSalesCount(int $productId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(oi.quantity), 0)
            FROM {$wpdb->prefix}ahm_order_items oi
            INNER JOIN {$wpdb->prefix}ahm_orders o ON o.order_id = oi.oid
            WHERE oi.pid = %d
            AND o.order_status = 'Completed'
        ", $productId));
    }
}
