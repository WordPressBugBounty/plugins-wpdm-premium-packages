<?php
/**
 * Pricing sales overview metabox for premium package. Displayed on edit package screen.
 *
 * This template can be overridden by copying it to yourtheme/download-manager/metaboxes/product-sales-overview.php.
 *
 * @version     2.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$pid = wpdm_query_var('post', 'int');

// Check if it's a free item first (single query)
$effective_price = wpdmpp_effective_price($pid);

if ((float)$effective_price <= 0) {
    ?>
    <style>
        .wpdmpp-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            color: #64748b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .wpdmpp-empty-state-icon {
            width: 48px;
            height: 48px;
            margin-bottom: 12px;
            color: #94a3b8;
        }
        .wpdmpp-empty-state-text {
            font-size: 14px;
            color: #64748b;
        }
    </style>
    <div class="wpdmpp-empty-state">
        <svg class="wpdmpp-empty-state-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
        </svg>
        <div class="wpdmpp-empty-state-text"><?php _e('Free Item!', 'wpdm-premium-packages'); ?></div>
    </div>
    <?php
    return;
}

// Calculate date ranges once
$today = wp_date("Y-m-d");
$yesterday = wp_date("Y-m-d", strtotime("-1 day"));
$tomorrow = wp_date("Y-m-d", strtotime("+1 day"));
$this_week_start = wp_date("Y-m-d", strtotime("monday this week"));
$last_week_start = wp_date("Y-m-d", strtotime("monday last week"));
$last_week_end = wp_date("Y-m-d", strtotime("sunday last week"));
$this_month_start = wp_date("Y-m-01");
$last_month_start = wp_date("Y-m-01", strtotime("first day of last month"));
$last_month_end = wp_date("Y-m-t", strtotime("last day of last month"));
$this_year_start = wp_date("Y-01-01");
$last_year = wp_date("Y") - 1;
$last_year_start = "$last_year-01-01";
$last_year_end = "$last_year-12-31";
$seven_days_ago = wp_date("Y-m-d", strtotime("-6 days"));

// Convert dates to timestamps for query
$ts_tomorrow = strtotime($tomorrow);
$ts_today = strtotime($today);
$ts_yesterday = strtotime($yesterday);
$ts_this_week_start = strtotime($this_week_start);
$ts_last_week_start = strtotime($last_week_start);
$ts_last_week_end = strtotime($last_week_end . " 23:59:59");
$ts_this_month_start = strtotime($this_month_start);
$ts_last_month_start = strtotime($last_month_start);
$ts_last_month_end = strtotime($last_month_end . " 23:59:59");
$ts_this_year_start = strtotime($this_year_start);
$ts_last_year_start = strtotime($last_year_start);
$ts_last_year_end = strtotime($last_year_end . " 23:59:59");
$ts_seven_days_ago = strtotime($seven_days_ago);
$ts_all_time_start = strtotime("1990-01-01");

// Single optimized query to get all period summaries
$orders_table = $wpdb->prefix . 'ahm_orders';
$items_table = $wpdb->prefix . 'ahm_order_items';

$sales_query = $wpdb->prepare("
    SELECT
        SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as today_sales,
        SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as yesterday_sales,
        SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as this_week_sales,
        SUM(CASE WHEN o.date >= %d AND o.date <= %d THEN oi.price * oi.quantity ELSE 0 END) as last_week_sales,
        SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as this_month_sales,
        SUM(CASE WHEN o.date >= %d AND o.date <= %d THEN oi.price * oi.quantity ELSE 0 END) as last_month_sales,
        SUM(CASE WHEN o.date >= %d AND o.date < %d THEN oi.price * oi.quantity ELSE 0 END) as this_year_sales,
        SUM(CASE WHEN o.date >= %d AND o.date <= %d THEN oi.price * oi.quantity ELSE 0 END) as last_year_sales,
        SUM(oi.price * oi.quantity) as total_sales
    FROM {$orders_table} o
    INNER JOIN {$items_table} oi ON oi.oid = o.order_id
    WHERE oi.pid = %d
    AND (o.payment_status = 'Completed' OR o.payment_status = 'Expired')
    AND o.date >= %d
",
    $ts_today, $ts_tomorrow,
    $ts_yesterday, $ts_today,
    $ts_this_week_start, $ts_tomorrow,
    $ts_last_week_start, $ts_last_week_end,
    $ts_this_month_start, $ts_tomorrow,
    $ts_last_month_start, $ts_last_month_end,
    $ts_this_year_start, $ts_tomorrow,
    $ts_last_year_start, $ts_last_year_end,
    $pid,
    $ts_all_time_start
);

$sales = $wpdb->get_row($sales_query);

// Check if no sales
if (!$sales || (float)$sales->total_sales == 0) {
    ?>
    <style>
        .wpdmpp-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            color: #64748b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .wpdmpp-empty-state-icon {
            width: 48px;
            height: 48px;
            margin-bottom: 12px;
            color: #94a3b8;
        }
        .wpdmpp-empty-state-text {
            font-size: 14px;
            color: #64748b;
        }
    </style>
    <div class="wpdmpp-empty-state">
        <svg class="wpdmpp-empty-state-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
        </svg>
        <div class="wpdmpp-empty-state-text"><?php _e('No Sales Yet!', 'wpdm-premium-packages'); ?></div>
    </div>
    <?php
    return;
}

// Get daily sales for chart (last 7 days) - single query
$daily_sales_query = $wpdb->prepare("
    SELECT
        oi.date,
        SUM(oi.price * oi.quantity) as daily_sale,
        SUM(oi.quantity) as quantities
    FROM {$orders_table} o
    INNER JOIN {$items_table} oi ON oi.oid = o.order_id
    WHERE oi.pid = %d
    AND (o.payment_status = 'Completed' OR o.payment_status = 'Expired')
    AND o.date >= %d
    AND o.date < %d
    GROUP BY oi.date
    ORDER BY oi.date ASC
", $pid, $ts_seven_days_ago, $ts_tomorrow);

$daily_results = $wpdb->get_results($daily_sales_query);

// Build daily sales array with all 7 days
$daily_sales = [];
$daily_quantities = [];
$max_sale = 0;

for ($i = 6; $i >= 0; $i--) {
    $date = wp_date("Y-m-d", strtotime("-$i days"));
    $daily_sales[$date] = 0;
    $daily_quantities[$date] = 0;
}

foreach ($daily_results as $row) {
    if (isset($daily_sales[$row->date])) {
        $daily_sales[$row->date] = (float)$row->daily_sale;
        $daily_quantities[$row->date] = (int)$row->quantities;
        if ((float)$row->daily_sale > $max_sale) {
            $max_sale = (float)$row->daily_sale;
        }
    }
}

// Get total renews - with prepared statement and proper table prefix
$renews_table = $wpdb->prefix . 'ahm_order_renews';
$total_renews = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(ori.price) as total_renews
     FROM {$renews_table} orn
     INNER JOIN {$items_table} ori ON orn.order_id = ori.oid
     WHERE ori.pid = %d",
    $pid
));
$total_renews = $total_renews ? (float)$total_renews : 0;

$currency = wpdmpp_currency_sign();
$total_sales = (float)$sales->total_sales;
$total_earning = $total_sales + $total_renews;
?>

<style>
    .wpdmpp-sales-overview {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        padding: 0;
    }

    /* Today/Yesterday Cards */
    .wpdmpp-highlight-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 16px;
    }
    .wpdmpp-highlight-card {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        text-align: center;
    }
    .wpdmpp-highlight-card--today {
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        border-color: #c7d2fe;
    }
    .wpdmpp-highlight-value {
        font-size: 22px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 4px;
    }
    .wpdmpp-highlight-card--today .wpdmpp-highlight-value {
        color: #4f46e5;
    }
    .wpdmpp-highlight-label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Chart */
    .wpdmpp-chart {
        margin-bottom: 16px;
        padding: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
    .wpdmpp-chart-title {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
    }
    .wpdmpp-chart-bars {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        height: 80px;
        gap: 8px;
    }
    .wpdmpp-chart-bar-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        height: 100%;
    }
    .wpdmpp-chart-bar-container {
        flex: 1;
        width: 100%;
        display: flex;
        align-items: flex-end;
        justify-content: center;
    }
    .wpdmpp-chart-bar {
        width: 100%;
        max-width: 32px;
        background: linear-gradient(180deg, #6366f1 0%, #4f46e5 100%);
        border-radius: 4px 4px 0 0;
        min-height: 4px;
        transition: all 0.3s ease;
        position: relative;
    }
    .wpdmpp-chart-bar:hover {
        background: linear-gradient(180deg, #818cf8 0%, #6366f1 100%);
        transform: scaleY(1.05);
        transform-origin: bottom;
    }
    .wpdmpp-chart-bar-tooltip {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s;
        margin-bottom: 4px;
    }
    .wpdmpp-chart-bar:hover .wpdmpp-chart-bar-tooltip {
        opacity: 1;
    }
    .wpdmpp-chart-label {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 6px;
    }

    /* Stats List */
    .wpdmpp-stats-list {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
    }
    .wpdmpp-stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 14px;
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
        transition: background 0.15s ease;
    }
    .wpdmpp-stat-item:last-child {
        border-bottom: none;
    }
    .wpdmpp-stat-item:hover {
        background: #f8fafc;
    }
    .wpdmpp-stat-item--total {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border-bottom: none;
    }
    .wpdmpp-stat-item--total:hover {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    }
    .wpdmpp-stat-label {
        font-size: 13px;
        color: #475569;
    }
    .wpdmpp-stat-value {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 12px;
    }
    .wpdmpp-stat-item--total .wpdmpp-stat-value {
        background: #10b981;
        color: #fff;
    }

    /* Export Button */
    .wpdmpp-export-section {
        margin-bottom: 16px;
    }
    .wpdmpp-export-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
        width: 100%;
        justify-content: center;
    }
    .wpdmpp-export-btn:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
    .wpdmpp-export-btn:active {
        transform: translateY(0);
    }
    .wpdmpp-export-btn svg {
        width: 18px;
        height: 18px;
    }
    .wpdmpp-export-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
</style>

<div class="wpdmpp-sales-overview">

    <!-- Today/Yesterday Highlight Cards -->
    <div class="wpdmpp-highlight-cards">
        <div class="wpdmpp-highlight-card wpdmpp-highlight-card--today">
            <div class="wpdmpp-highlight-value"><?php echo esc_html($currency . number_format((float)$sales->today_sales, 2)); ?></div>
            <div class="wpdmpp-highlight-label"><?php _e('Today', 'wpdm-premium-packages'); ?></div>
        </div>
        <div class="wpdmpp-highlight-card">
            <div class="wpdmpp-highlight-value"><?php echo esc_html($currency . number_format((float)$sales->yesterday_sales, 2)); ?></div>
            <div class="wpdmpp-highlight-label"><?php _e('Yesterday', 'wpdm-premium-packages'); ?></div>
        </div>
    </div>

    <!-- 7-Day Chart -->
    <div class="wpdmpp-chart">
        <div class="wpdmpp-chart-title"><?php _e('Last 7 Days', 'wpdm-premium-packages'); ?></div>
        <div class="wpdmpp-chart-bars">
            <?php
            $max_sale = max($max_sale, 1); // Prevent division by zero
            foreach ($daily_sales as $date => $amount):
                $height = ($amount / $max_sale) * 100;
                $height = max($height, 5); // Minimum 5% height for visibility
                $day_label = wp_date("D", strtotime($date));
                $qty = $daily_quantities[$date];
            ?>
            <div class="wpdmpp-chart-bar-wrapper">
                <div class="wpdmpp-chart-bar-container">
                    <div class="wpdmpp-chart-bar" style="height: <?php echo esc_attr($height); ?>%;">
                        <div class="wpdmpp-chart-bar-tooltip">
                            <?php echo esc_html($currency . number_format($amount, 2) . ' (' . $qty . ')'); ?>
                        </div>
                    </div>
                </div>
                <div class="wpdmpp-chart-label"><?php echo esc_html($day_label); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Export Button -->
    <div class="wpdmpp-export-section">
        <button type="button" class="wpdmpp-export-btn" id="wpdmpp-export-customers" data-pid="<?php echo esc_attr($pid); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <?php _e('Export Customer Data', 'wpdm-premium-packages'); ?>
        </button>
    </div>

    <!-- Stats List -->
    <div class="wpdmpp-stats-list">
        <div class="wpdmpp-stat-item">
            <span class="wpdmpp-stat-label"><?php _e('This Week', 'wpdm-premium-packages'); ?></span>
            <span class="wpdmpp-stat-value"><?php echo esc_html($currency . number_format((float)$sales->this_week_sales, 2)); ?></span>
        </div>
        <div class="wpdmpp-stat-item">
            <span class="wpdmpp-stat-label"><?php _e('Last Week', 'wpdm-premium-packages'); ?></span>
            <span class="wpdmpp-stat-value"><?php echo esc_html($currency . number_format((float)$sales->last_week_sales, 2)); ?></span>
        </div>
        <div class="wpdmpp-stat-item">
            <span class="wpdmpp-stat-label"><?php _e('This Month', 'wpdm-premium-packages'); ?></span>
            <span class="wpdmpp-stat-value"><?php echo esc_html($currency . number_format((float)$sales->this_month_sales, 2)); ?></span>
        </div>
        <div class="wpdmpp-stat-item">
            <span class="wpdmpp-stat-label"><?php _e('Last Month', 'wpdm-premium-packages'); ?></span>
            <span class="wpdmpp-stat-value"><?php echo esc_html($currency . number_format((float)$sales->last_month_sales, 2)); ?></span>
        </div>
        <div class="wpdmpp-stat-item">
            <span class="wpdmpp-stat-label"><?php _e('This Year', 'wpdm-premium-packages'); ?></span>
            <span class="wpdmpp-stat-value"><?php echo esc_html($currency . number_format((float)$sales->this_year_sales, 2)); ?></span>
        </div>
        <div class="wpdmpp-stat-item">
            <span class="wpdmpp-stat-label"><?php _e('Last Year', 'wpdm-premium-packages'); ?></span>
            <span class="wpdmpp-stat-value"><?php echo esc_html($currency . number_format((float)$sales->last_year_sales, 2)); ?></span>
        </div>
        <div class="wpdmpp-stat-item">
            <span class="wpdmpp-stat-label"><?php _e('Total Sales', 'wpdm-premium-packages'); ?></span>
            <span class="wpdmpp-stat-value"><?php echo esc_html($currency . number_format($total_sales, 2)); ?></span>
        </div>
        <div class="wpdmpp-stat-item">
            <span class="wpdmpp-stat-label"><?php _e('Total Renews', 'wpdm-premium-packages'); ?></span>
            <span class="wpdmpp-stat-value"><?php echo esc_html($currency . number_format($total_renews, 2)); ?></span>
        </div>
        <div class="wpdmpp-stat-item wpdmpp-stat-item--total">
            <span class="wpdmpp-stat-label"><strong><?php _e('Total Earning', 'wpdm-premium-packages'); ?></strong></span>
            <span class="wpdmpp-stat-value"><?php echo esc_html($currency . number_format($total_earning, 2)); ?></span>
        </div>
    </div>

</div>

<script>
jQuery(function($) {
    $('#wpdmpp-export-customers').on('click', function() {
        var btn = $(this);
        var pid = btn.data('pid');
        var originalText = btn.html();

        btn.prop('disabled', true).html('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px;animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path stroke-linecap="round" d="M12 2a10 10 0 0 1 10 10"/></svg> <?php _e('Exporting...', 'wpdm-premium-packages'); ?>');

        // Create a hidden form to trigger file download
        var form = $('<form>', {
            method: 'POST',
            action: ajaxurl
        }).append(
            $('<input>', { type: 'hidden', name: 'action', value: 'wpdmpp_export_product_customers' }),
            $('<input>', { type: 'hidden', name: 'pid', value: pid }),
            $('<input>', { type: 'hidden', name: '_wpnonce', value: '<?php echo wp_create_nonce('wpdmpp_export_customers'); ?>' })
        );

        $('body').append(form);
        form.submit();
        form.remove();

        // Re-enable button after a delay
        setTimeout(function() {
            btn.prop('disabled', false).html(originalText);
        }, 2000);
    });
});
</script>
