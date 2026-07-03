<?php
/**
 * Top Sales Dashboard Widget
 *
 * Displays the top selling products over a period.
 *
 * @package WPDMPP\Admin\Dashboard\Widgets
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Dashboard\Widgets;

use WPDMPP\Admin\Dashboard\AbstractWidget;

defined('ABSPATH') || exit;

class TopSalesWidget extends AbstractWidget
{
    /**
     * Widget ID
     *
     * @var string
     */
    protected string $id = 'top_sales';

    /**
     * Widget title
     *
     * @var string
     */
    protected string $title;

    /**
     * Number of products to display
     *
     * @var int
     */
    protected int $limit = 10;

    /**
     * Period in days
     *
     * @var int
     */
    protected int $periodDays = 90;

    /**
     * Cache duration (1 day)
     *
     * @var int
     */
    protected int $cacheDuration = 86400;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->title = __('WPDM Top Sales', 'wpdm-premium-packages');
    }

    /**
     * Get cache key
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return 'wpdmpp_top_sales_' . wp_date('Y-m-d');
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

        $startDate = strtotime("-{$this->periodDays} days");

        // Get top selling products
        // Use payment_status (not order_status) for sales calculations
        $topSales = $wpdb->get_results($wpdb->prepare("
            SELECT
                oi.pid,
                SUM(oi.price * oi.quantity) as sales,
                SUM(oi.quantity) as quantities
            FROM {$wpdb->prefix}ahm_order_items oi
            INNER JOIN {$wpdb->prefix}ahm_orders o ON o.order_id = oi.oid
            WHERE (o.payment_status = 'Completed' OR o.payment_status = 'Expired')
            AND o.date >= %d
            GROUP BY oi.pid
            ORDER BY sales DESC
            LIMIT %d
        ", $startDate, $this->limit));

        // Calculate max sales for progress bar
        $maxSales = 0;
        $productIds = [];

        foreach ($topSales as $item) {
            $sales = (float) ($item->sales ?? 0);
            if ($sales > $maxSales) {
                $maxSales = $sales;
            }
            if (!empty($item->pid)) {
                $productIds[] = (int) $item->pid;
            }
        }

        // Batch fetch product data
        $productsData = $this->batchFetchPosts($productIds);

        // Process top sales
        $processedSales = [];
        $rank = 0;

        foreach ($topSales as $item) {
            $rank++;
            $productInfo = isset($productsData[$item->pid]) ? $productsData[$item->pid] : null;
            $sales = (float) ($item->sales ?? 0);
            $progress = $maxSales > 0 ? ($sales / $maxSales) * 100 : 0;

            $processedSales[] = [
                'rank' => $rank,
                'product_id' => (int) $item->pid,
                'sales' => $sales,
                'quantity' => (int) ($item->quantities ?? 0),
                'progress' => round($progress, 2),
                'product' => $productInfo ? [
                    'title' => $productInfo['title'],
                    'thumbnail' => $productInfo['thumbnail'],
                ] : null,
                'is_deleted' => !$productInfo,
                'product_url' => admin_url('post.php?post=' . $item->pid . '&action=edit'),
            ];
        }

        $data = [
            'products' => $processedSales,
            'max_sales' => $maxSales,
            'period_days' => $this->periodDays,
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
            // Prepare data in format expected by template
            $topsales = [];
            foreach ($data['products'] as $product) {
                $topsales[] = (object) [
                    'pid' => $product['product_id'],
                    'sales' => $product['sales'],
                    'quantities' => $product['quantity'],
                ];
            }
            $max_sales = $data['max_sales'];
            $posts_data = [];
            foreach ($data['products'] as $product) {
                if ($product['product']) {
                    $posts_data[$product['product_id']] = [
                        'title' => $product['product']['title'],
                        'thumbnail' => $product['product']['thumbnail'],
                    ];
                }
            }
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
        $products = $data['products'];
        $currency = $data['currency'];

        if (empty($products)) {
            echo '<div style="padding: 20px; text-align: center; color: #64748b;">';
            esc_html_e('No sales data yet', 'wpdm-premium-packages');
            echo '</div>';
            return;
        }
        ?>
        <div style="padding: 8px 12px;">
            <div style="font-size: 11px; color: #64748b; margin-bottom: 12px;">
                <?php printf(esc_html__('Last %d days', 'wpdm-premium-packages'), $data['period_days']); ?>
            </div>
            <?php foreach ($products as $product): ?>
                <div style="display: flex; align-items: center; padding: 8px 0; gap: 10px;">
                    <div style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; <?php
                        if ($product['rank'] === 1) {
                            echo 'background: linear-gradient(135deg, #fef3c7, #fde68a); color: #eab308; box-shadow: 0 2px 4px rgba(234, 179, 8, 0.3);';
                        } elseif ($product['rank'] === 2) {
                            echo 'background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #94a3b8; box-shadow: 0 2px 4px rgba(148, 163, 184, 0.3);';
                        } elseif ($product['rank'] === 3) {
                            echo 'background: linear-gradient(135deg, #ffedd5, #fed7aa); color: #d97706; box-shadow: 0 2px 4px rgba(217, 119, 6, 0.3);';
                        } else {
                            echo 'background: #f8fafc; color: #64748b;';
                        }
                    ?>">
                        <?php echo esc_html($product['rank']); ?>
                    </div>
                    <div style="width: 36px; height: 36px; border-radius: 6px; background: #eef2ff; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if ($product['product'] && $product['product']['thumbnail']): ?>
                            <img src="<?php echo esc_url($product['product']['thumbnail']); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#6366f1" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 13px; font-weight: 600; color: <?php echo $product['is_deleted'] ? '#ef4444' : '#1e293b'; ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px;">
                            <?php echo $product['is_deleted'] ? esc_html__('Item Deleted', 'wpdm-premium-packages') : esc_html($product['product']['title']); ?>
                        </div>
                        <div style="height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(90deg, #6366f1, #818cf8); border-radius: 2px; width: <?php echo esc_attr($product['progress']); ?>%;"></div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 14px; font-weight: 700; color: #10b981;"><?php echo esc_html($currency . number_format($product['sales'], 2)); ?></div>
                        <div style="font-size: 11px; color: #64748b;"><?php printf(_n('%d sold', '%d sold', $product['quantity'], 'wpdm-premium-packages'), $product['quantity']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Set the period in days
     *
     * @param int $days
     * @return self
     */
    public function setPeriod(int $days): self
    {
        $this->periodDays = $days;
        return $this;
    }

    /**
     * Set the number of products to show
     *
     * @param int $limit
     * @return self
     */
    public function setLimit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Get top sellers for a specific product category
     *
     * @param int $categoryId
     * @return array
     */
    public function getTopByCategory(int $categoryId): array
    {
        global $wpdb;

        $startDate = strtotime("-{$this->periodDays} days");

        // Get product IDs in category
        $productIds = get_posts([
            'post_type' => 'wpdmpro',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'wpdmcategory',
                    'terms' => $categoryId,
                ],
            ],
        ]);

        if (empty($productIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '%d'));

        $topSales = $wpdb->get_results($wpdb->prepare("
            SELECT
                oi.pid,
                SUM(oi.price * oi.quantity) as sales,
                SUM(oi.quantity) as quantities
            FROM {$wpdb->prefix}ahm_order_items oi
            INNER JOIN {$wpdb->prefix}ahm_orders o ON o.order_id = oi.oid
            WHERE (o.payment_status = 'Completed' OR o.payment_status = 'Expired')
            AND o.date >= %d
            AND oi.pid IN ($placeholders)
            GROUP BY oi.pid
            ORDER BY sales DESC
            LIMIT %d
        ", array_merge([$startDate], $productIds, [$this->limit])));

        return $topSales;
    }
}
