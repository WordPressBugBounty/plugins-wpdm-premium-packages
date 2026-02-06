<?php
/**
 * User: shahnuralam
 * Date: 5/6/17
 * Time: 11:42 PM
 */
if(!defined('ABSPATH')) die('!');

global $wpdb;

// Optimized query with prepared statement
$latest_items = $wpdb->get_results("
    SELECT oi.*, o.date, o.uid
    FROM {$wpdb->prefix}ahm_order_items oi
    INNER JOIN {$wpdb->prefix}ahm_orders o ON o.order_id = oi.oid
    WHERE o.order_status = 'Completed'
    ORDER BY o.date DESC
    LIMIT 5
");

$currency = wpdmpp_currency_sign();
$date_format = get_option('date_format');
$time_format = get_option('time_format');

/**
 * Get human-readable time difference
 */
function wpdmpp_time_ago($timestamp) {
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return __('Just now', 'wpdm-premium-packages');
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return sprintf(_n('%d min ago', '%d mins ago', $mins, 'wpdm-premium-packages'), $mins);
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'wpdm-premium-packages'), $hours);
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $days, 'wpdm-premium-packages'), $days);
    } else {
        return wp_date(get_option('date_format'), $timestamp);
    }
}
?>
<style>
    .wpdmpp-recent-sales {
        --recent-primary: #6366f1;
        --recent-primary-light: #eef2ff;
        --recent-success: #10b981;
        --recent-success-light: #ecfdf5;
        --recent-danger: #ef4444;
        --recent-danger-light: #fef2f2;
        --recent-text: #1e293b;
        --recent-text-muted: #64748b;
        --recent-border: #e2e8f0;
        --recent-bg: #f8fafc;
        --recent-radius: 10px;
        --recent-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.08);
        margin: -12px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
    }

    .wpdmpp-recent-sales * {
        box-sizing: border-box;
    }

    .wpdmpp-recent-content {
        padding: 12px;
        background: var(--recent-bg);
    }

    .wpdmpp-recent-list {
        background: #fff;
        border-radius: var(--recent-radius);
        box-shadow: var(--recent-shadow);
        border: 1px solid var(--recent-border);
        overflow: hidden;
    }

    .wpdmpp-sale-item {
        display: flex;
        align-items: center;
        padding: 12px 14px;
        border-bottom: 1px solid var(--recent-border);
        text-decoration: none;
        transition: background 150ms ease;
        gap: 12px;
    }

    .wpdmpp-sale-item:last-child {
        border-bottom: none;
    }

    .wpdmpp-sale-item:hover {
        background: var(--recent-bg);
        text-decoration: none;
    }

    .wpdmpp-sale-item:focus {
        outline: none;
        background: var(--recent-primary-light);
    }

    .wpdmpp-sale-thumb {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        background: var(--recent-primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .wpdmpp-sale-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .wpdmpp-sale-thumb svg {
        width: 20px;
        height: 20px;
        color: var(--recent-primary);
    }

    .wpdmpp-sale-thumb--deleted {
        background: var(--recent-danger-light);
    }

    .wpdmpp-sale-thumb--deleted svg {
        color: var(--recent-danger);
    }

    .wpdmpp-sale-info {
        flex: 1;
        min-width: 0;
    }

    .wpdmpp-sale-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--recent-text);
        margin-bottom: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .wpdmpp-sale-title--deleted {
        color: var(--recent-danger);
    }

    .wpdmpp-sale-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        color: var(--recent-text-muted);
    }

    .wpdmpp-sale-meta-item {
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .wpdmpp-sale-meta-item svg {
        width: 12px;
        height: 12px;
        opacity: 0.7;
    }

    .wpdmpp-sale-meta-divider {
        width: 3px;
        height: 3px;
        border-radius: 50%;
        background: var(--recent-border);
    }

    .wpdmpp-sale-price {
        flex-shrink: 0;
        text-align: right;
    }

    .wpdmpp-sale-amount {
        font-size: 14px;
        font-weight: 700;
        color: var(--recent-success);
        margin-bottom: 2px;
    }

    .wpdmpp-sale-qty {
        font-size: 11px;
        color: var(--recent-text-muted);
    }

    .wpdmpp-empty-state {
        padding: 32px 16px;
        text-align: center;
        color: var(--recent-text-muted);
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

<div class="wpdmpp-recent-sales">
    <div class="wpdmpp-recent-content">
        <div class="wpdmpp-recent-list">
            <?php if (empty($latest_items)): ?>
                <div class="wpdmpp-empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                    <p><?php _e('No recent sales yet', 'wpdm-premium-packages'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($latest_items as $item):
                    $post = get_post($item->pid);
                    $title = $post ? $post->post_title : null;
                    $is_deleted = !$title;
                    $thumbnail = $post ? get_the_post_thumbnail_url($item->pid, 'thumbnail') : null;
                    $time_ago = wpdmpp_time_ago($item->date);
                    $qty = isset($item->quantity) ? (int)$item->quantity : 1;
                ?>
                <a href="edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=<?php echo esc_attr($item->oid); ?>" class="wpdmpp-sale-item">

                    <!-- Thumbnail -->
                    <div class="wpdmpp-sale-thumb <?php echo $is_deleted ? 'wpdmpp-sale-thumb--deleted' : ''; ?>">
                        <?php if ($thumbnail): ?>
                            <img src="<?php echo esc_url($thumbnail); ?>" alt="">
                        <?php elseif ($is_deleted): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="wpdmpp-sale-info">
                        <div class="wpdmpp-sale-title <?php echo $is_deleted ? 'wpdmpp-sale-title--deleted' : ''; ?>">
                            <?php echo $is_deleted ? __('Item Deleted', 'wpdm-premium-packages') : esc_html($title); ?>
                        </div>
                        <div class="wpdmpp-sale-meta">
                            <span class="wpdmpp-sale-meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <?php echo esc_html($time_ago); ?>
                            </span>
                            <span class="wpdmpp-sale-meta-divider"></span>
                            <span class="wpdmpp-sale-meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                #<?php echo esc_html($item->oid); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Price -->
                    <div class="wpdmpp-sale-price">
                        <div class="wpdmpp-sale-amount"><?php echo $currency . number_format($item->price, 2, '.', ','); ?></div>
                        <?php if ($qty > 1): ?>
                            <div class="wpdmpp-sale-qty"><?php printf(__('Qty: %d', 'wpdm-premium-packages'), $qty); ?></div>
                        <?php endif; ?>
                    </div>

                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
