<?php
/**
 * User: shahnuralam
 * Date: 5/6/17
 * Time: 11:42 PM
 */
if(!defined('ABSPATH')) die('!');

$orders = new \WPDMPP\Libs\Order();
$latest_orders = $orders->GetAllOrders("where order_status='Completed' and payment_status='Completed'", 0, 5);

$currency = wpdmpp_currency_sign();

/**
 * Get human-readable time difference
 */
if (!function_exists('wpdmpp_time_ago')) {
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
}
?>
<style>
    .wpdmpp-latest-orders {
        --orders-primary: #6366f1;
        --orders-primary-light: #eef2ff;
        --orders-success: #10b981;
        --orders-success-light: #ecfdf5;
        --orders-warning: #f59e0b;
        --orders-warning-light: #fffbeb;
        --orders-text: #1e293b;
        --orders-text-muted: #64748b;
        --orders-border: #e2e8f0;
        --orders-bg: #f8fafc;
        --orders-radius: 10px;
        --orders-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.08);
        margin: -12px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
    }

    .wpdmpp-latest-orders * {
        box-sizing: border-box;
    }

    .wpdmpp-orders-content {
        padding: 12px;
        background: var(--orders-bg);
    }

    .wpdmpp-orders-list {
        background: #fff;
        border-radius: var(--orders-radius);
        box-shadow: var(--orders-shadow);
        border: 1px solid var(--orders-border);
        overflow: hidden;
    }

    .wpdmpp-order-item {
        display: flex;
        align-items: center;
        padding: 12px 14px;
        border-bottom: 1px solid var(--orders-border);
        text-decoration: none;
        transition: background 150ms ease;
        gap: 12px;
    }

    .wpdmpp-order-item:last-child {
        border-bottom: none;
    }

    .wpdmpp-order-item:hover {
        background: var(--orders-bg);
        text-decoration: none;
    }

    .wpdmpp-order-item:focus {
        outline: none;
        background: var(--orders-primary-light);
    }

    .wpdmpp-order-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--orders-primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
        border: 2px solid #fff;
        box-shadow: 0 1px 3px rgb(0 0 0 / 0.1);
    }

    .wpdmpp-order-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .wpdmpp-order-avatar svg {
        width: 18px;
        height: 18px;
        color: var(--orders-primary);
    }

    .wpdmpp-order-info {
        flex: 1;
        min-width: 0;
    }

    .wpdmpp-order-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 3px;
    }

    .wpdmpp-order-id {
        font-size: 13px;
        font-weight: 600;
        color: var(--orders-text);
    }

    .wpdmpp-order-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 10px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .wpdmpp-order-status--completed {
        background: var(--orders-success-light);
        color: var(--orders-success);
    }

    .wpdmpp-order-status svg {
        width: 10px;
        height: 10px;
    }

    .wpdmpp-order-customer {
        font-size: 12px;
        color: var(--orders-text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 2px;
    }

    .wpdmpp-order-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        color: var(--orders-text-muted);
    }

    .wpdmpp-order-meta-item {
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .wpdmpp-order-meta-item svg {
        width: 11px;
        height: 11px;
        opacity: 0.6;
    }

    .wpdmpp-order-meta-divider {
        width: 3px;
        height: 3px;
        border-radius: 50%;
        background: var(--orders-border);
    }

    .wpdmpp-order-total {
        flex-shrink: 0;
        text-align: right;
    }

    .wpdmpp-order-amount {
        font-size: 15px;
        font-weight: 700;
        color: var(--orders-success);
        line-height: 1.2;
    }

    .wpdmpp-order-items-count {
        font-size: 11px;
        color: var(--orders-text-muted);
        margin-top: 2px;
    }

    .wpdmpp-empty-state {
        padding: 32px 16px;
        text-align: center;
        color: var(--orders-text-muted);
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

<div class="wpdmpp-latest-orders">
    <div class="wpdmpp-orders-content">
        <div class="wpdmpp-orders-list">
            <?php if (empty($latest_orders)): ?>
                <div class="wpdmpp-empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                    </svg>
                    <p><?php _e('No orders yet', 'wpdm-premium-packages'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($latest_orders as $order):
                    $billing_info = isset($order->billing_info) ? maybe_unserialize($order->billing_info) : [];
                    if (!is_array($billing_info)) $billing_info = [];

                    // Get user info
                    $user = isset($order->uid) ? get_user_by('id', $order->uid) : false;
                    $email = '';
                    $name = '';
                    $avatar_url = '';

                    if (is_object($user) && !is_wp_error($user)) {
                        $email = $user->user_email;
                        $name = $user->display_name;
                        $avatar_url = get_avatar_url($user->ID, ['size' => 80]);
                    } else {
                        $email = isset($billing_info['order_email']) ? $billing_info['order_email'] : '';
                        $first = isset($billing_info['first_name']) ? $billing_info['first_name'] : '';
                        $last = isset($billing_info['last_name']) ? $billing_info['last_name'] : '';
                        $name = trim($first . ' ' . $last);
                        if ($email) {
                            $avatar_url = get_avatar_url($email, ['size' => 80]);
                        }
                    }

                    if (empty($name)) {
                        $name = $email ? explode('@', $email)[0] : __('Guest', 'wpdm-premium-packages');
                    }

                    $order_date = isset($order->date) ? $order->date : time();
                    $time_ago = wpdmpp_time_ago($order_date);
                    $items_count = isset($order->items) && is_array($order->items) ? count($order->items) : 0;
                ?>
                <?php $order_id = isset($order->order_id) ? $order->order_id : ''; ?>
                <a href="edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=<?php echo esc_attr($order_id); ?>" class="wpdmpp-order-item">

                    <!-- Avatar -->
                    <div class="wpdmpp-order-avatar">
                        <?php if ($avatar_url): ?>
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="">
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="wpdmpp-order-info">
                        <div class="wpdmpp-order-header">
                            <span class="wpdmpp-order-id">#<?php echo esc_html($order_id); ?></span>
                            <span class="wpdmpp-order-status wpdmpp-order-status--completed">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg>
                                <?php _e('Paid', 'wpdm-premium-packages'); ?>
                            </span>
                        </div>
                        <div class="wpdmpp-order-customer"><?php echo esc_html($name); ?></div>
                        <div class="wpdmpp-order-meta">
                            <span class="wpdmpp-order-meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <?php echo esc_html($time_ago); ?>
                            </span>
                            <?php if ($email): ?>
                            <span class="wpdmpp-order-meta-divider"></span>
                            <span class="wpdmpp-order-meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                                <?php echo esc_html($email); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Total -->
                    <?php $order_total = isset($order->total) ? (float)$order->total : 0; ?>
                    <div class="wpdmpp-order-total">
                        <div class="wpdmpp-order-amount"><?php echo $currency . number_format($order_total, 2, '.', ','); ?></div>
                        <?php if ($items_count > 0): ?>
                            <div class="wpdmpp-order-items-count">
                                <?php printf(_n('%d item', '%d items', $items_count, 'wpdm-premium-packages'), $items_count); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
