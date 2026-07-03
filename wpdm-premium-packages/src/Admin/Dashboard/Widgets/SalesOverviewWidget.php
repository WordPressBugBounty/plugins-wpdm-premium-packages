<?php
/**
 * Sales Overview Dashboard Widget
 *
 * Displays sales statistics for different time periods.
 *
 * @package WPDMPP\Admin\Dashboard\Widgets
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Dashboard\Widgets;

use WPDMPP\Admin\Dashboard\AbstractWidget;
use WPDM\__\Session;

defined('ABSPATH') || exit;

class SalesOverviewWidget extends AbstractWidget
{
    /**
     * Widget ID
     *
     * @var string
     */
    protected string $id = 'sales_overview';

    /**
     * Widget title
     *
     * @var string
     */
    protected string $title;

    /**
     * Cache duration (1 hour)
     *
     * @var int
     */
    protected int $cacheDuration = 3600;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->title = __('WPDM Sales Overview', 'wpdm-premium-packages');
    }

    /**
     * Get cache key based on current hour
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return 'wpdmpp_sales_overview_' . wp_date('Y-m-d-H');
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

        // Calculate timestamps for different periods
        $now = current_time('timestamp');
        $today_start = strtotime('today', $now);
        $today_end = strtotime('tomorrow', $now);
        $yesterday_start = strtotime('yesterday', $now);
        $yesterday_end = $today_start;
        $this_week_start = strtotime('monday this week', $now);
        $this_week_end = strtotime('monday next week', $now);
        $last_week_start = strtotime('monday last week', $now);
        $last_week_end = $this_week_start;
        $this_month_start = strtotime('first day of this month', $now);
        $this_month_end = strtotime('first day of next month', $now);
        $last_month_start = strtotime('first day of last month', $now);
        $last_month_end = $this_month_start;
        $this_year_start = strtotime('first day of January this year', $now);
        $this_year_end = strtotime('first day of January next year', $now);

        // Get all sales data in a single optimized query
        $sales = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as today,
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as yesterday,
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as this_week,
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as last_week,
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as this_month,
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as last_month,
                SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as this_year,
                SUM(oi.price * oi.quantity) as total
            FROM {$wpdb->prefix}ahm_orders o
            INNER JOIN {$wpdb->prefix}ahm_order_items oi ON oi.oid = o.order_id
            WHERE (o.payment_status = 'Completed' OR o.payment_status = 'Expired')
        ",
            $today_start, $today_end,
            $yesterday_start, $yesterday_end,
            $this_week_start, $this_week_end,
            $last_week_start, $last_week_end,
            $this_month_start, $this_month_end,
            $last_month_start, $last_month_end,
            $this_year_start, $this_year_end
        ));

        // Get daily sales for chart (last 7 days)
        $dailySales = $this->getDailySales(7);

        $data = [
            'today' => (float) ($sales->today ?? 0),
            'yesterday' => (float) ($sales->yesterday ?? 0),
            'this_week' => (float) ($sales->this_week ?? 0),
            'last_week' => (float) ($sales->last_week ?? 0),
            'this_month' => (float) ($sales->this_month ?? 0),
            'last_month' => (float) ($sales->last_month ?? 0),
            'this_year' => (float) ($sales->this_year ?? 0),
            'total' => (float) ($sales->total ?? 0),
            'daily_sales' => $dailySales,
            'currency' => wpdmpp_currency_sign(),
        ];

        // Cache the data
        $this->setCachedData($data);

        return $data;
    }

    /**
     * Get daily sales for chart
     *
     * @param int $days Number of days
     * @return array
     */
    private function getDailySales(int $days = 7): array
    {
        global $wpdb;

        $sales = [];
        $now = current_time('timestamp');

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = wp_date('Y-m-d', strtotime("-{$i} days", $now));
            $sales[$date] = 0;
        }

        $start = strtotime("-" . ($days - 1) . " days", strtotime('today', $now));

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                FROM_UNIXTIME(o.date, '%%Y-%%m-%%d') as sale_date,
                SUM(oi.price * oi.quantity) as total
            FROM {$wpdb->prefix}ahm_orders o
            INNER JOIN {$wpdb->prefix}ahm_order_items oi ON oi.oid = o.order_id
            WHERE (o.payment_status = 'Completed' OR o.payment_status = 'Expired')
            AND o.date >= %d
            GROUP BY sale_date
            ORDER BY sale_date ASC
        ", $start));

        foreach ($results as $row) {
            if (isset($sales[$row->sale_date])) {
                $sales[$row->sale_date] = (float) $row->total;
            }
        }

        return $sales;
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
            $sales_data = $data;
            $currency = $data['currency'];
            $daily_sales = $data['daily_sales'];
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
        $currency = $data['currency'];
        $periods = [
            'today' => __('Today', 'wpdm-premium-packages'),
            'yesterday' => __('Yesterday', 'wpdm-premium-packages'),
            'this_week' => __('This Week', 'wpdm-premium-packages'),
            'last_week' => __('Last Week', 'wpdm-premium-packages'),
            'this_month' => __('This Month', 'wpdm-premium-packages'),
            'last_month' => __('Last Month', 'wpdm-premium-packages'),
            'this_year' => __('This Year', 'wpdm-premium-packages'),
            'total' => __('Total', 'wpdm-premium-packages'),
        ];
        ?>
        <div style="padding: 15px;">
            <?php foreach ($periods as $key => $label): ?>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                    <span><?php echo esc_html($label); ?></span>
                    <strong><?php echo esc_html($currency . number_format($data[$key], 2)); ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Get summary statistics for API
     *
     * @return array
     */
    public function getSummary(): array
    {
        $data = $this->getData();

        return [
            'today' => $this->formatCurrency($data['today']),
            'this_week' => $this->formatCurrency($data['this_week']),
            'this_month' => $this->formatCurrency($data['this_month']),
            'total' => $this->formatCurrency($data['total']),
            'trend' => $this->calculateTrend($data['today'], $data['yesterday']),
        ];
    }

    /**
     * Calculate trend percentage
     *
     * @param float $current
     * @param float $previous
     * @return array
     */
    private function calculateTrend(float $current, float $previous): array
    {
        if ($previous == 0) {
            return [
                'direction' => $current > 0 ? 'up' : 'neutral',
                'percentage' => $current > 0 ? 100 : 0,
            ];
        }

        $change = (($current - $previous) / $previous) * 100;

        return [
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
            'percentage' => abs(round($change, 1)),
        ];
    }
}
