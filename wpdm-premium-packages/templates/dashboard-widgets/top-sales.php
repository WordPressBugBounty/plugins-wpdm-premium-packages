<?php
/**
 * User: shahnuralam
 * Date: 5/6/17
 * Time: 11:42 PM
 */
if(!defined('ABSPATH')) die('!');

global $wpdb;

$currency = wpdmpp_currency_sign();
$today = wp_date("Y-m-d");
$cache_key = 'wpdmpp_top_sales_' . $today;

// Check session cache first
$cached_data = \WPDM\__\Session::get($cache_key);

if ($cached_data) {
    $topsales = $cached_data['topsales'];
    $max_sales = $cached_data['max_sales'];
    $posts_data = $cached_data['posts_data'];
} else {
    $fdolm = wp_date("Y-m-d", strtotime("-90 days"));
    $ldolm = $today;

    // Get top sales data
    $topsales = wpdmpp_top_sellings_products("", $fdolm, $ldolm, 0, 10);

    // Calculate max sales for progress bar
    $max_sales = 0;
    $pids = [];

    if (!empty($topsales)) {
        foreach ($topsales as $item) {
            $sales = isset($item->sales) ? (float)$item->sales : 0;
            if ($sales > $max_sales) $max_sales = $sales;
            if (isset($item->pid)) $pids[] = (int)$item->pid;
        }
    }

    // Batch fetch all posts at once (avoids N+1 queries)
    $posts_data = [];
    if (!empty($pids)) {
        $posts = get_posts([
            'post_type' => 'wpdmpro',
            'post__in' => $pids,
            'posts_per_page' => count($pids),
            'post_status' => 'any',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ($posts as $post) {
            $posts_data[$post->ID] = [
                'title' => $post->post_title,
                'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
            ];
        }
    }

    // Cache for the day
    \WPDM\__\Session::set($cache_key, [
        'topsales' => $topsales,
        'max_sales' => $max_sales,
        'posts_data' => $posts_data,
    ]);
}
?>
<style>
    .wpdmpp-top-sales {
        --top-primary: #6366f1;
        --top-primary-light: #eef2ff;
        --top-success: #10b981;
        --top-success-light: #ecfdf5;
        --top-warning: #f59e0b;
        --top-warning-light: #fffbeb;
        --top-gold: #eab308;
        --top-silver: #94a3b8;
        --top-bronze: #d97706;
        --top-text: #1e293b;
        --top-text-muted: #64748b;
        --top-border: #e2e8f0;
        --top-bg: #f8fafc;
        --top-radius: 10px;
        --top-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.08);
        margin: -12px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
    }

    .wpdmpp-top-sales * {
        box-sizing: border-box;
    }

    .wpdmpp-top-content {
        padding: 12px;
        background: var(--top-bg);
    }

    .wpdmpp-top-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        padding: 0 4px;
    }

    .wpdmpp-top-period {
        font-size: 11px;
        color: var(--top-text-muted);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .wpdmpp-top-period svg {
        width: 12px;
        height: 12px;
    }

    .wpdmpp-top-list {
        background: #fff;
        border-radius: var(--top-radius);
        box-shadow: var(--top-shadow);
        border: 1px solid var(--top-border);
        overflow: hidden;
    }

    .wpdmpp-top-item {
        display: flex;
        align-items: center;
        padding: 10px 14px;
        border-bottom: 1px solid var(--top-border);
        gap: 12px;
        transition: background 150ms ease;
    }

    .wpdmpp-top-item:last-child {
        border-bottom: none;
    }

    .wpdmpp-top-item:hover {
        background: var(--top-bg);
    }

    .wpdmpp-top-rank {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
        background: var(--top-bg);
        color: var(--top-text-muted);
    }

    .wpdmpp-top-rank--1 {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: var(--top-gold);
        box-shadow: 0 2px 4px rgb(234 179 8 / 0.3);
    }

    .wpdmpp-top-rank--2 {
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        color: var(--top-silver);
        box-shadow: 0 2px 4px rgb(148 163 184 / 0.3);
    }

    .wpdmpp-top-rank--3 {
        background: linear-gradient(135deg, #ffedd5 0%, #fed7aa 100%);
        color: var(--top-bronze);
        box-shadow: 0 2px 4px rgb(217 119 6 / 0.3);
    }

    .wpdmpp-top-thumb {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--top-primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .wpdmpp-top-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .wpdmpp-top-thumb svg {
        width: 18px;
        height: 18px;
        color: var(--top-primary);
    }

    .wpdmpp-top-info {
        flex: 1;
        min-width: 0;
    }

    .wpdmpp-top-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--top-text);
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .wpdmpp-top-title--deleted {
        color: #ef4444;
        font-style: italic;
    }

    .wpdmpp-top-progress {
        height: 4px;
        background: var(--top-border);
        border-radius: 2px;
        overflow: hidden;
    }

    .wpdmpp-top-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--top-primary) 0%, #818cf8 100%);
        border-radius: 2px;
        transition: width 300ms ease;
    }

    .wpdmpp-top-stats {
        flex-shrink: 0;
        text-align: right;
    }

    .wpdmpp-top-amount {
        font-size: 14px;
        font-weight: 700;
        color: var(--top-success);
        line-height: 1.2;
    }

    .wpdmpp-top-qty {
        font-size: 11px;
        color: var(--top-text-muted);
        margin-top: 2px;
    }

    .wpdmpp-empty-state {
        padding: 32px 16px;
        text-align: center;
        color: var(--top-text-muted);
        background: #fff;
        border-radius: var(--top-radius);
        box-shadow: var(--top-shadow);
        border: 1px solid var(--top-border);
    }

    .wpdmpp-empty-state svg {
        width: 40px;
        height: 40px;
        margin-bottom: 8px;
        opacity: 0.5;
    }

    .wpdmpp-empty-state p {
        margin: 0;
        font-size: 13px;
    }
</style>

<div class="wpdmpp-top-sales">
    <div class="wpdmpp-top-content">

        <!-- Period indicator -->
        <div class="wpdmpp-top-header">
            <span class="wpdmpp-top-period">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <?php _e('Last 90 days', 'wpdm-premium-packages'); ?>
            </span>
        </div>

        <?php if (empty($topsales)): ?>
            <div class="wpdmpp-empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-2.927 0" />
                </svg>
                <p><?php _e('No sales data yet', 'wpdm-premium-packages'); ?></p>
            </div>
        <?php else: ?>
            <div class="wpdmpp-top-list">
                <?php
                $rank = 0;
                foreach ($topsales as $item):
                    $rank++;
                    $pid = isset($item->pid) ? (int)$item->pid : 0;

                    // Use cached post data (no individual DB queries)
                    $post_info = isset($posts_data[$pid]) ? $posts_data[$pid] : null;
                    $title = $post_info ? $post_info['title'] : null;
                    $thumbnail = $post_info ? $post_info['thumbnail'] : null;
                    $is_deleted = !$title;

                    $sales = isset($item->sales) ? (float)$item->sales : 0;
                    $qty = isset($item->quantities) ? (int)$item->quantities : 0;
                    $progress = $max_sales > 0 ? ($sales / $max_sales) * 100 : 0;
                ?>
                <div class="wpdmpp-top-item">

                    <!-- Rank -->
                    <div class="wpdmpp-top-rank wpdmpp-top-rank--<?php echo $rank <= 3 ? $rank : ''; ?>">
                        <?php echo $rank; ?>
                    </div>

                    <!-- Thumbnail -->
                    <div class="wpdmpp-top-thumb">
                        <?php if ($thumbnail): ?>
                            <img src="<?php echo esc_url($thumbnail); ?>" alt="">
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="wpdmpp-top-info">
                        <div class="wpdmpp-top-title <?php echo $is_deleted ? 'wpdmpp-top-title--deleted' : ''; ?>">
                            <?php echo $is_deleted ? __('Item Deleted', 'wpdm-premium-packages') : esc_html($title); ?>
                        </div>
                        <div class="wpdmpp-top-progress">
                            <div class="wpdmpp-top-progress-bar" style="width: <?php echo esc_attr($progress); ?>%"></div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="wpdmpp-top-stats">
                        <div class="wpdmpp-top-amount"><?php echo $currency . number_format($sales, 2, '.', ','); ?></div>
                        <div class="wpdmpp-top-qty"><?php printf(_n('%d sold', '%d sold', $qty, 'wpdm-premium-packages'), $qty); ?></div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>
