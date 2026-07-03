<?php
/**
 * Metabox Service
 *
 * Manages all Premium Package metaboxes for the package edit screen.
 *
 * @package WPDMPP\Admin\Metabox
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Metabox;

use WPDM\__\Session;
use WPDM\__\Template;

defined('ABSPATH') || exit;

class MetaboxService
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
    }

    /**
     * Register all metabox hooks
     *
     * @return void
     */
    public function register(): void
    {
        // Package form pricing section
        add_action('wpdm-package-form-left', [$this, 'renderPricingMetabox']);

        // Package settings tabs (Pricing & Discounts tab)
        add_filter('wpdm_package_settings_tabs', [$this, 'addPricingTab']);

        // Sales Overview metabox in sidebar
        add_filter('wpdm_meta_box', [$this, 'addSalesOverviewMetabox']);

        // AJAX handler for sales overview content
        add_action('wp_ajax_product_sales_overview', [$this, 'ajaxLoadSalesOverview']);
    }

    /**
     * Add Pricing & Discounts tab to package settings
     *
     * @param array $tabs Existing tabs
     * @return array
     */
    public function addPricingTab(array $tabs): array
    {
        if (!is_admin()) {
            return $tabs;
        }

        $tabs['pricing'] = [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-badge-dollar-sign-icon lucide-badge-dollar-sign"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>',
            'name' => __('Pricing & Discounts', 'wpdm-premium-packages'),
            'callback' => [$this, 'renderPricingMetabox']
        ];

        return $tabs;
    }

    /**
     * Render pricing metabox content
     *
     * @return void
     */
    public function renderPricingMetabox(): void
    {
        global $post;
        $templatePath = Template::locate('metaboxes/wpdm-pp-settings.php', WPDMPP_TPL_DIR);
        if ($templatePath && file_exists($templatePath)) {
            include $templatePath;
        }
    }

    /**
     * Add Sales Overview metabox to sidebar
     *
     * @param array $metaboxes Existing metaboxes
     * @return array
     */
    public function addSalesOverviewMetabox(array $metaboxes): array
    {
        $pid = wpdm_query_var('post');
        $price = wpdmpp_effective_price($pid);

        if ($price > 0) {
            $salesMetabox = [
                'sales-overview' => [
                    'title' => __('Sales Overview', 'wpdm-premium-packages'),
                    'callback' => [$this, 'renderSalesOverviewPlaceholder'],
                    'position' => 'side',
                    'priority' => 'core'
                ]
            ];
            $metaboxes = $salesMetabox + $metaboxes;
        }

        return $metaboxes;
    }

    /**
     * Render sales overview loading placeholder
     *
     * Uses AJAX lazy loading for better performance.
     *
     * @return void
     */
    public function renderSalesOverviewPlaceholder(): void
    {
        $postId = wpdm_query_var('post');
        ?>
        <style>
            .wpdmpp-widget-loading {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 40px 20px;
                color: #64748b;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .wpdmpp-widget-loading-spinner {
                width: 32px;
                height: 32px;
                border: 3px solid #e2e8f0;
                border-top-color: #6366f1;
                border-radius: 50%;
                animation: wpdmpp-spin 0.8s linear infinite;
                margin-bottom: 12px;
            }
            .wpdmpp-widget-loading-text {
                font-size: 13px;
                color: #94a3b8;
            }
            @keyframes wpdmpp-spin {
                to { transform: rotate(360deg); }
            }
        </style>
        <div id="wpdmpp-sales-overview">
            <div class="wpdmpp-widget-loading">
                <div class="wpdmpp-widget-loading-spinner"></div>
                <div class="wpdmpp-widget-loading-text"><?php esc_html_e('Loading...', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <script>
            jQuery(function ($) {
                $('#wpdmpp-sales-overview').load(ajaxurl, {
                    action: 'product_sales_overview',
                    post: <?php echo (int) $postId; ?>
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX handler for loading sales overview content
     *
     * @return void
     */
    public function ajaxLoadSalesOverview(): void
    {
        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_die(__('Permission denied', 'wpdm-premium-packages'));
        }

        $postId = wpdm_query_var('post');
        $cacheKey = 'sales_overview_html_' . $postId;

        // Check cache first
        $data = Session::get($cacheKey);
        if ($data) {
            echo $data;
            wp_die();
        }

        // Generate content
        ob_start();

        // New path in src/Admin/Metabox/views/
        $templatePath = __DIR__ . '/views/product-sales-overview.php';
        if (!file_exists($templatePath)) {
            // Fallback to old path for backward compatibility
            $templatePath = WPDMPP_BASE_DIR . 'includes/menus/templates/product-sales-overview.php';
        }

        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            $this->renderSalesOverviewFallback($postId);
        }
        $data = ob_get_clean();

        // Cache for 30 minutes
        Session::set($cacheKey, $data, 1800);

        echo $data;
        wp_die();
    }

    /**
     * Render fallback content if template is missing
     *
     * @param int $postId Product ID
     * @return void
     */
    private function renderSalesOverviewFallback(int $postId): void
    {
        $totalSales = wpdmpp_total_sales('', $postId, '', '');
        $totalPurchases = wpdmpp_total_purchase($postId);
        $currency = wpdmpp_currency_sign();
        ?>
        <div style="padding: 15px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                <div>
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">
                        <?php esc_html_e('Total Sales', 'wpdm-premium-packages'); ?>
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: #10b981;">
                        <?php echo esc_html($currency . number_format((float) $totalSales, 2)); ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">
                        <?php esc_html_e('Orders', 'wpdm-premium-packages'); ?>
                    </div>
                    <div style="font-size: 20px; font-weight: 700; color: #6366f1;">
                        <?php echo esc_html($totalPurchases); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Clear cache for a specific product
     *
     * @param int $postId Product ID
     * @return void
     */
    public static function clearCache(int $postId): void
    {
        Session::clear('sales_overview_html_' . $postId);
    }

    /**
     * Clear cache for all products
     *
     * @return void
     */
    public static function clearAllCaches(): void
    {
        // Session-based cache clears automatically after expiry
        // This method is a placeholder for future cache implementations
    }

    /**
     * Get sales data for a product (for API use)
     *
     * @param int $postId Product ID
     * @return array
     */
    public function getSalesData(int $postId): array
    {
        global $wpdb;

        // Get current timestamps
        $now = time();
        $todayStart = strtotime('today');
        $tomorrowStart = strtotime('tomorrow');
        $yesterdayStart = strtotime('yesterday');
        $thisWeekStart = strtotime('monday this week');
        $lastWeekStart = strtotime('monday last week');
        $lastWeekEnd = strtotime('sunday last week 23:59:59');
        $thisMonthStart = strtotime(date('Y-m-01'));
        $lastMonthStart = strtotime('first day of last month');
        $lastMonthEnd = strtotime('last day of last month 23:59:59');
        $thisYearStart = strtotime(date('Y-01-01'));

        // Single optimized query using CASE statements
        $salesQuery = $wpdb->prepare("
            SELECT
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as today_sales,
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as yesterday_sales,
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as this_week_sales,
                SUM(CASE WHEN o.date >= %d AND o.date <= %d THEN oi.price * oi.quantity ELSE 0 END) as last_week_sales,
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as this_month_sales,
                SUM(CASE WHEN o.date >= %d AND o.date <= %d THEN oi.price * oi.quantity ELSE 0 END) as last_month_sales,
                SUM(CASE WHEN o.date >= %d THEN oi.price * oi.quantity ELSE 0 END) as this_year_sales,
                SUM(oi.price * oi.quantity) as total_sales,
                COUNT(DISTINCT o.order_id) as total_orders,
                SUM(oi.quantity) as total_quantity
            FROM {$wpdb->prefix}ahm_orders o
            INNER JOIN {$wpdb->prefix}ahm_order_items oi ON oi.oid = o.order_id
            WHERE oi.pid = %d
            AND o.payment_status IN ('Completed', 'Expired')
        ",
            $todayStart, $tomorrowStart,
            $yesterdayStart, $todayStart,
            $thisWeekStart, $now,
            $lastWeekStart, $lastWeekEnd,
            $thisMonthStart, $now,
            $lastMonthStart, $lastMonthEnd,
            $thisYearStart,
            $postId
        );

        $sales = $wpdb->get_row($salesQuery);

        return [
            'product_id' => $postId,
            'today' => (float) ($sales->today_sales ?? 0),
            'yesterday' => (float) ($sales->yesterday_sales ?? 0),
            'this_week' => (float) ($sales->this_week_sales ?? 0),
            'last_week' => (float) ($sales->last_week_sales ?? 0),
            'this_month' => (float) ($sales->this_month_sales ?? 0),
            'last_month' => (float) ($sales->last_month_sales ?? 0),
            'this_year' => (float) ($sales->this_year_sales ?? 0),
            'total' => (float) ($sales->total_sales ?? 0),
            'total_orders' => (int) ($sales->total_orders ?? 0),
            'total_quantity' => (int) ($sales->total_quantity ?? 0),
            'currency' => wpdmpp_currency_sign(),
        ];
    }
}
