<?php
/**
 * User: shahnuralam
 * Date: 5/6/17
 * Time: 8:14 PM
 */
if(!defined('ABSPATH')) die('!');

global $wpdb;

// Calculate all date boundaries once
$today = wp_date("Y-m-d");
$tomorrow = wp_date("Y-m-d", strtotime("Tomorrow"));
$yesterday = wp_date("Y-m-d", strtotime("Yesterday"));

// This week (Monday to now)
$date = new DateTime();
$date->modify('this week');
$this_week_start = $date->format('Y-m-d');

// Last week
$date = new DateTime();
$date->modify('this week -7 days');
$last_week_start = $date->format('Y-m-d');
$last_week_end = $this_week_start;

// This month
$this_month_start = wp_date("Y-m-01");

// Last month
$date = new DateTime();
$date->modify('first day of last month');
$last_month_start = $date->format('Y-m-d');
$last_month_end = $this_month_start;

// This year / Last year
$this_year = wp_date("Y");
$last_year = $this_year - 1;
$this_year_start = "{$this_year}-01-01";
$last_year_start = "{$last_year}-01-01";
$last_year_end = $this_year_start;

// Convert dates to timestamps for the query
$ts_tomorrow = strtotime($tomorrow);
$ts_this_week_start = strtotime($this_week_start);
$ts_last_week_start = strtotime($last_week_start);
$ts_last_week_end = strtotime($last_week_end);
$ts_this_month_start = strtotime($this_month_start);
$ts_last_month_start = strtotime($last_month_start);
$ts_last_month_end = strtotime($last_month_end);
$ts_this_year_start = strtotime($this_year_start);
$ts_last_year_start = strtotime($last_year_start);
$ts_last_year_end = strtotime($last_year_end);

// Check for cached sales data
$cache_key = 'wpdmpp_sales_overview_' . $today;
$sales_data = \WPDM\__\Session::get($cache_key);

if (!$sales_data) {
    // Single optimized query with conditional aggregation (replaces 7 separate queries)
    $sales_data = $wpdb->get_row($wpdb->prepare("
        SELECT
            SUM(CASE WHEN date >= %d AND date < %d THEN total ELSE 0 END) as this_week,
            SUM(CASE WHEN date >= %d AND date < %d THEN total ELSE 0 END) as last_week,
            SUM(CASE WHEN date >= %d AND date < %d THEN total ELSE 0 END) as this_month,
            SUM(CASE WHEN date >= %d AND date < %d THEN total ELSE 0 END) as last_month,
            SUM(CASE WHEN date >= %d AND date < %d THEN total ELSE 0 END) as this_year,
            SUM(CASE WHEN date >= %d AND date < %d THEN total ELSE 0 END) as last_year,
            SUM(total) as total_all_time
        FROM {$wpdb->prefix}ahm_orders
        WHERE payment_status IN ('Completed', 'Expired')
        AND date >= %d
    ",
        $ts_this_week_start, $ts_tomorrow,
        $ts_last_week_start, $ts_last_week_end,
        $ts_this_month_start, $ts_tomorrow,
        $ts_last_month_start, $ts_last_month_end,
        $ts_this_year_start, $ts_tomorrow,
        $ts_last_year_start, $ts_last_year_end,
        $ts_last_year_start
    ), ARRAY_A);

    $sales_data = array_map(function($v) { return (float) $v; }, $sales_data);
    \WPDM\__\Session::set($cache_key, $sales_data);
}

// Daily sales for chart (cached separately)
if(!\WPDM\__\Session::get('daily_sales')) {
    $daily_sales = wpdmpp_daily_sales('', '', wp_date("Y-m-d", strtotime("-6 Days")), $tomorrow);
    \WPDM\__\Session::set('daily_sales', $daily_sales);
} else {
    $daily_sales = \WPDM\__\Session::get('daily_sales');
}

$currency = wpdmpp_currency_sign();
$today_sales = $daily_sales['sales'][$today] ?? 0;
$yesterday_sales = $daily_sales['sales'][$yesterday] ?? 0;

// Calculate percentage change
$pct_change = $yesterday_sales > 0 ? (($today_sales - $yesterday_sales) / $yesterday_sales) * 100 : ($today_sales > 0 ? 100 : 0);
$pct_positive = $pct_change >= 0;
?>
<style>
    .wpdmpp-sales-widget {
        --sales-primary: #6366f1;
        --sales-primary-light: #eef2ff;
        --sales-success: #10b981;
        --sales-success-light: #ecfdf5;
        --sales-warning: #f59e0b;
        --sales-warning-light: #fffbeb;
        --sales-danger: #ef4444;
        --sales-danger-light: #fef2f2;
        --sales-info: #0ea5e9;
        --sales-info-light: #f0f9ff;
        --sales-purple: #8b5cf6;
        --sales-purple-light: #f5f3ff;
        --sales-text: #1e293b;
        --sales-text-muted: #64748b;
        --sales-border: #e2e8f0;
        --sales-bg: #f8fafc;
        --sales-radius: 10px;
        --sales-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.08);
        margin: -12px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
    }

    .wpdmpp-sales-widget * {
        box-sizing: border-box;
    }

    .wpdmpp-sales-content {
        padding: 16px;
        background: var(--sales-bg);
    }

    /* Chart Card */
    .wpdmpp-chart-card {
        background: #fff;
        border-radius: var(--sales-radius);
        box-shadow: var(--sales-shadow);
        border: 1px solid var(--sales-border);
        overflow: hidden;
        margin-bottom: 16px;
    }

    .wpdmpp-chart-header {
        padding: 12px 16px;
        border-bottom: 1px solid var(--sales-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .wpdmpp-chart-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--sales-text);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .wpdmpp-chart-title svg {
        width: 16px;
        height: 16px;
        color: var(--sales-primary);
    }

    .wpdmpp-chart-container {
        padding: 8px;
    }

    #chart_div {
        width: 100%;
        height: 180px;
    }

    /* Today/Yesterday Cards */
    .wpdmpp-highlight-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }

    .wpdmpp-highlight-card {
        background: #fff;
        border-radius: var(--sales-radius);
        padding: 16px;
        box-shadow: var(--sales-shadow);
        border: 1px solid var(--sales-border);
        text-align: center;
        transition: transform 150ms ease, box-shadow 150ms ease;
    }

    .wpdmpp-highlight-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgb(0 0 0 / 0.1);
    }

    .wpdmpp-highlight-card--today {
        background: linear-gradient(135deg, var(--sales-primary) 0%, #4f46e5 100%);
        border: none;
    }

    .wpdmpp-highlight-card--today .wpdmpp-highlight-label,
    .wpdmpp-highlight-card--today .wpdmpp-highlight-value {
        color: #fff;
    }

    .wpdmpp-highlight-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--sales-text);
        line-height: 1.2;
        margin-bottom: 4px;
    }

    .wpdmpp-highlight-label {
        font-size: 12px;
        color: var(--sales-text-muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .wpdmpp-highlight-badge {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 12px;
        margin-top: 8px;
    }

    .wpdmpp-highlight-badge--up {
        background: rgba(255,255,255,0.2);
        color: #fff;
    }

    .wpdmpp-highlight-badge--down {
        background: rgba(255,255,255,0.2);
        color: #fff;
    }

    .wpdmpp-highlight-badge svg {
        width: 12px;
        height: 12px;
    }

    /* Sales List */
    .wpdmpp-sales-list {
        background: #fff;
        border-radius: var(--sales-radius);
        box-shadow: var(--sales-shadow);
        border: 1px solid var(--sales-border);
        overflow: hidden;
    }

    .wpdmpp-sales-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid var(--sales-border);
        transition: background 150ms ease;
    }

    .wpdmpp-sales-item:last-child {
        border-bottom: none;
    }

    .wpdmpp-sales-item:hover {
        background: var(--sales-bg);
    }

    .wpdmpp-sales-item--total {
        background: var(--sales-primary-light);
    }

    .wpdmpp-sales-item--total:hover {
        background: var(--sales-primary-light);
    }

    .wpdmpp-sales-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        flex-shrink: 0;
    }

    .wpdmpp-sales-icon svg {
        width: 16px;
        height: 16px;
    }

    .wpdmpp-sales-icon--week { background: var(--sales-info-light); color: var(--sales-info); }
    .wpdmpp-sales-icon--month { background: var(--sales-success-light); color: var(--sales-success); }
    .wpdmpp-sales-icon--year { background: var(--sales-warning-light); color: var(--sales-warning); }
    .wpdmpp-sales-icon--total { background: var(--sales-primary); color: #fff; }

    .wpdmpp-sales-label {
        flex: 1;
        font-size: 13px;
        font-weight: 500;
        color: var(--sales-text);
    }

    .wpdmpp-sales-value {
        font-size: 14px;
        font-weight: 700;
        color: var(--sales-text);
    }

    .wpdmpp-sales-item--total .wpdmpp-sales-label,
    .wpdmpp-sales-item--total .wpdmpp-sales-value {
        color: var(--sales-primary);
    }

    @media (max-width: 782px) {
        .wpdmpp-highlight-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="wpdmpp-sales-widget">
    <div class="wpdmpp-sales-content">

        <!-- Chart -->
        <div class="wpdmpp-chart-card">
            <div class="wpdmpp-chart-header">
                <div class="wpdmpp-chart-title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    <?php _e('Last 7 Days', 'wpdm-premium-packages'); ?>
                </div>
            </div>
            <div class="wpdmpp-chart-container">
                <div id="chart_div"></div>
            </div>
        </div>

        <!-- Today / Yesterday Highlights -->
        <div class="wpdmpp-highlight-grid">
            <div class="wpdmpp-highlight-card wpdmpp-highlight-card--today">
                <div class="wpdmpp-highlight-value"><?php echo $currency . number_format($today_sales, 2); ?></div>
                <div class="wpdmpp-highlight-label"><?php _e('Today', 'wpdm-premium-packages'); ?></div>
                <?php if ($pct_change != 0): ?>
                <div class="wpdmpp-highlight-badge wpdmpp-highlight-badge--<?php echo $pct_positive ? 'up' : 'down'; ?>">
                    <?php if ($pct_positive): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                        </svg>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181" />
                        </svg>
                    <?php endif; ?>
                    <?php echo ($pct_positive ? '+' : '') . number_format($pct_change, 0) . '%'; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="wpdmpp-highlight-card">
                <div class="wpdmpp-highlight-value"><?php echo $currency . number_format($yesterday_sales, 2); ?></div>
                <div class="wpdmpp-highlight-label"><?php _e('Yesterday', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>

        <!-- Sales List -->
        <div class="wpdmpp-sales-list">
            <!-- This Week -->
            <div class="wpdmpp-sales-item">
                <div class="wpdmpp-sales-icon wpdmpp-sales-icon--week">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="wpdmpp-sales-label"><?php _e('This Week', 'wpdm-premium-packages'); ?></div>
                <div class="wpdmpp-sales-value"><?php echo $currency . number_format($sales_data['this_week'], 2); ?></div>
            </div>

            <!-- Last Week -->
            <div class="wpdmpp-sales-item">
                <div class="wpdmpp-sales-icon wpdmpp-sales-icon--week">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="wpdmpp-sales-label"><?php _e('Last Week', 'wpdm-premium-packages'); ?></div>
                <div class="wpdmpp-sales-value"><?php echo $currency . number_format($sales_data['last_week'], 2); ?></div>
            </div>

            <!-- This Month -->
            <div class="wpdmpp-sales-item">
                <div class="wpdmpp-sales-icon wpdmpp-sales-icon--month">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                    </svg>
                </div>
                <div class="wpdmpp-sales-label"><?php _e('This Month', 'wpdm-premium-packages'); ?></div>
                <div class="wpdmpp-sales-value"><?php echo $currency . number_format($sales_data['this_month'], 2); ?></div>
            </div>

            <!-- Last Month -->
            <div class="wpdmpp-sales-item">
                <div class="wpdmpp-sales-icon wpdmpp-sales-icon--month">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                    </svg>
                </div>
                <div class="wpdmpp-sales-label"><?php _e('Last Month', 'wpdm-premium-packages'); ?></div>
                <div class="wpdmpp-sales-value"><?php echo $currency . number_format($sales_data['last_month'], 2); ?></div>
            </div>

            <!-- This Year -->
            <div class="wpdmpp-sales-item">
                <div class="wpdmpp-sales-icon wpdmpp-sales-icon--year">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="wpdmpp-sales-label"><?php _e('This Year', 'wpdm-premium-packages'); ?> (<?php echo $this_year; ?>)</div>
                <div class="wpdmpp-sales-value"><?php echo $currency . number_format($sales_data['this_year'], 2); ?></div>
            </div>

            <!-- Last Year -->
            <div class="wpdmpp-sales-item">
                <div class="wpdmpp-sales-icon wpdmpp-sales-icon--year">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="wpdmpp-sales-label"><?php _e('Last Year', 'wpdm-premium-packages'); ?> (<?php echo $last_year; ?>)</div>
                <div class="wpdmpp-sales-value"><?php echo $currency . number_format($sales_data['last_year'], 2); ?></div>
            </div>

            <!-- Total -->
            <div class="wpdmpp-sales-item wpdmpp-sales-item--total">
                <div class="wpdmpp-sales-icon wpdmpp-sales-icon--total">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="wpdmpp-sales-label"><?php _e('Total Revenue', 'wpdm-premium-packages'); ?></div>
                <div class="wpdmpp-sales-value"><?php echo $currency . number_format($sales_data['total_all_time'], 2); ?></div>
            </div>
        </div>

    </div>
</div>
<script type="text/javascript">
    jQuery(function($) {
        $.getScript('https://www.gstatic.com/charts/loader.js', function () {
            google.charts.load('current', {'packages':['corechart']});
            google.charts.setOnLoadCallback(drawChart);

            function drawChart() {
                var data = google.visualization.arrayToDataTable([
                    ['<?php _e('Date', 'wpdm-premium-packages'); ?>', '<?php echo esc_js($currency); ?>', '#'],
                    <?php
                    $dn = 0;
                    foreach ($daily_sales['sales'] as $date => $sale) {
                        $day = wp_date("D", strtotime($date));
                        $qty = $daily_sales['quantities'][$date] ?? 0;
                        echo "['" . esc_js($day) . "', " . floatval($sale) . ", " . intval($qty) . "]";
                        if ($dn++ < 6) echo ',';
                        else break;
                    }
                    ?>
                ]);

                var options = {
                    legend: { position: 'none' },
                    chartArea: { width: '85%', height: '75%' },
                    hAxis: {
                        textStyle: { color: '#64748b', fontSize: 11 },
                        gridlines: { color: 'transparent' }
                    },
                    vAxis: {
                        minValue: 0,
                        textStyle: { color: '#64748b', fontSize: 11 },
                        gridlines: { color: '#e2e8f0' },
                        baselineColor: '#e2e8f0'
                    },
                    colors: ['#6366f1', '#10b981'],
                    areaOpacity: 0.1,
                    lineWidth: 2,
                    pointSize: 4,
                    backgroundColor: 'transparent',
                    tooltip: { textStyle: { fontSize: 12 } }
                };

                var chart = new google.visualization.AreaChart(document.getElementById('chart_div'));
                chart.draw(data, options);
            }

            // Redraw on resize
            $(window).resize(function() {
                drawChart();
            });
        });
    });
</script>
