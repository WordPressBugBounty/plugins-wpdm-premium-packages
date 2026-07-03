<?php
/**
 * Orders List Template for User Dashboard >> Purchases >> All Orders
 *
 * This template can be overridden by copying it to yourtheme/download-manager/user-dashboard/purchase-orders.php.
 *
 * @version     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb, $sap, $wpdmpp_settings, $current_user;
$current_user   = wp_get_current_user();
$orderService   = \WPDMPP\Order\OrderService::instance();
$myorders       = $orderService->getRawUserOrders($current_user->ID);
$orderurl       = get_permalink(get_the_ID());

if ( ! isset($_GET['id']) && ! isset($_GET['item']) ) {

    // Scope renews query to current user's orders only
    $order_ids = array_column($myorders, 'order_id');
    $renew_cycle = [];
    if ( ! empty($order_ids)) {
        $id_placeholders = implode(',', array_fill(0, count($order_ids), '%s'));
        $renews = $wpdb->get_results($wpdb->prepare(
            "SELECT COUNT(*) AS renew_cycle, order_id FROM {$wpdb->prefix}ahm_order_renews WHERE order_id IN ({$id_placeholders}) GROUP BY order_id",
            ...$order_ids
        ));
        foreach ($renews as $renew) {
            $renew_cycle[$renew->order_id] = $renew->renew_cycle;
        }
    }

    $order_validity_period = get_wpdmpp_option('order_validity_period', 365, 'int') * 86400;
    $auto_renew_enabled    = (int) get_wpdmpp_option('auto_renew') === 1;
    $date_format           = get_option('date_format');
    $time_format           = get_option('time_format');
    $delete_nonce          = wp_create_nonce(NONCE_KEY);

    // Batch-fetch product names for all orders in one query
    $order_products = [];
    if ( ! empty($order_ids)) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT oid, pid, product_name FROM {$wpdb->prefix}ahm_order_items WHERE oid IN ({$id_placeholders})",
            ...$order_ids
        ));
        foreach ($rows as $row) {
            $name = $row->product_name;
            if (empty($name)) {
                $name = get_the_title($row->pid);
            }
            if ( ! empty($name)) {
                $order_products[$row->oid][] = $name;
            }
        }
    }

    // Collect unique statuses for filter tabs
    $status_counts = [];
    foreach ($myorders as $o) {
        $s = $o->order_status;
        $status_counts[$s] = ($status_counts[$s] ?? 0) + 1;
    }
    ?>

    <?php
    $color_scheme = get_option('__wpdm_color_scheme', 'system');
    $dark_class   = $color_scheme === 'dark' ? ' wpdmpp-dark' : '';
    ?>
    <div class="wpdmpp-orders<?php echo $dark_class; ?>" id="wpdmpp-orders-root"<?php if ($color_scheme === 'system') echo ' data-cs="system"'; ?>>
    <?php if ($color_scheme === 'system') : ?>
        <script>if(window.matchMedia('(prefers-color-scheme:dark)').matches)document.getElementById('wpdmpp-orders-root').classList.add('wpdmpp-dark');</script>
    <?php endif; ?>
        <div class="wpdmpp-orders__header">
            <h3 class="wpdmpp-orders__title">
                <?php echo \WPDMPP\UI\Icons::get('layers', 20); ?>
                <?php echo apply_filters('wpdmpp_all_orders_title', __('All Orders', 'wpdm-premium-packages')); ?>
                <?php if ( ! empty($myorders)) : ?>
                    <span class="wpdmpp-orders__count"><?php echo count($myorders); ?></span>
                <?php endif; ?>
            </h3>
        </div>

        <?php if ( ! empty($myorders) && count($status_counts) > 1) : ?>
            <div class="wpdmpp-orders__filters">
                <button type="button" class="wpdmpp-filter-tab is-active" data-status="all">
                    <?php _e('All', 'wpdm-premium-packages'); ?>
                    <span class="wpdmpp-filter-tab__count"><?php echo count($myorders); ?></span>
                </button>
                <?php foreach ($status_counts as $status => $count) : ?>
                    <button type="button" class="wpdmpp-filter-tab" data-status="<?php echo esc_attr(strtolower($status)); ?>">
                        <?php echo esc_html($status); ?>
                        <span class="wpdmpp-filter-tab__count"><?php echo (int) $count; ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($myorders)) : ?>
            <div class="wpdmpp-orders__empty">
                <div class="wpdmpp-orders__empty-icon">
                    <?php echo \WPDMPP\UI\Icons::get('shopping-bag', 28); ?>
                </div>
                <p class="wpdmpp-orders__empty-text"><?php _e('No orders found.', 'wpdm-premium-packages'); ?></p>
            </div>
        <?php else : ?>
            <div class="wpdmpp-orders__list">
                <?php
                foreach ($myorders as $order) :
                    $expire_date = $order->expire_date;
                    if ((int) $expire_date === 0) {
                        $expire_date = $order->date + $order_validity_period;
                        $orderService->updateOrder(['expire_date' => $expire_date], $order->order_id);
                    }

                    if (time() > $expire_date && $order->order_status === 'Completed') {
                        $orderService->expireOrder($order->order_id);
                        $order->order_status   = 'Expired';
                        $order->payment_status = 'Expired';
                    }

                    if (wpdm_query_var('udb_page')) {
                        $sap  = (! isset($params['flaturl']) || $params['flaturl'] == 0) ? "?udb_page=" : "";
                        $zurl = get_permalink(get_the_ID()) . $sap . "purchases/order/{$order->order_id}/";
                    } else {
                        $zurl = add_query_arg(['id' => $order->order_id], get_permalink(get_the_ID()));
                    }

                    $status_key   = strtolower($order->order_status);
                    $status_icons = [
                        'completed'  => 'check-circle',
                        'processing' => 'clock',
                        'expired'    => 'times-circle',
                        'cancelled'  => 'close',
                        'refunded'   => 'redo',
                    ];
                    $status_icon = $status_icons[$status_key] ?? 'circle-dot';

                    $order_date    = wp_date($date_format . ' ' . $time_format, $order->date);
                    $expire_date_s = wp_date($date_format, $expire_date);
                    $is_processing = $order->order_status === 'Processing';
                    $show_renew    = $auto_renew_enabled || (int) $order->auto_renew === 1;

                    $_renew_cycle = isset($renew_cycle[$order->order_id])
                        ? sprintf(__('%s cycle', 'wpdm-premium-packages'), wpdmpp_ordinal(($renew_cycle[$order->order_id]) + 1))
                        : __('1st cycle', 'wpdm-premium-packages');

                    // Product names for this order
                    $products = $order_products[$order->order_id] ?? [];

                    ?>
                    <div class="wpdmpp-order-card"
                         id="order_<?php echo esc_attr($order->order_id); ?>"
                         data-status="<?php echo esc_attr($status_key); ?>">
                        <div class="wpdmpp-order-card__body">
                            <div class="wpdmpp-order-card__main">
                                <div class="wpdmpp-order-card__top">
                                    <a href="<?php echo esc_url($zurl); ?>" class="wpdmpp-order-card__id">
                                        #<?php echo esc_html($order->order_id); ?>
                                    </a>
                                    <span class="wpdmpp-status wpdmpp-status--<?php echo esc_attr($status_key); ?>">
                                        <?php echo \WPDMPP\UI\Icons::get($status_icon, 12); ?>
                                        <?php echo esc_html($order->order_status); ?>
                                    </span>
                                </div>

                                <?php if ( ! empty($products)) : ?>
                                    <div class="wpdmpp-order-card__products">
                                        <?php
                                        $max_show  = 2;
                                        $shown     = array_slice($products, 0, $max_show);
                                        $remaining = count($products) - $max_show;
                                        foreach ($shown as $i => $pname) :
                                            if ($i > 0) echo '<span class="wpdmpp-order-card__product-sep">&middot;</span>';
                                            ?>
                                            <span class="wpdmpp-order-card__product-name">
                                                <?php if ($i === 0) echo \WPDMPP\UI\Icons::get('shopping-bag', 13); ?>
                                                <?php echo esc_html($pname); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if ($remaining > 0) : ?>
                                            <span class="wpdmpp-order-card__product-more">+<?php echo $remaining; ?> <?php _e('more', 'wpdm-premium-packages'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="wpdmpp-order-card__meta">
                                    <span class="wpdmpp-order-card__meta-item">
                                        <?php echo \WPDMPP\UI\Icons::get('calendar', 14); ?>
                                        <?php echo esc_html($order_date); ?>
                                    </span>
                                    <span class="wpdmpp-order-card__meta-sep"></span>
                                    <span class="wpdmpp-order-card__meta-item">
                                        <?php echo \WPDMPP\UI\Icons::get('clock', 14); ?>
                                        <?php _e('Expires', 'wpdm-premium-packages'); ?> <?php echo esc_html($expire_date_s); ?>
                                    </span>
                                    <?php if ($show_renew) : ?>
                                        <span class="wpdmpp-order-card__meta-sep"></span>
                                        <span class="wpdmpp-renew-badge wpdmpp-renew-badge--<?php echo (int) $order->auto_renew === 1 ? 'active' : 'inactive'; ?>">
                                            <?php echo \WPDMPP\UI\Icons::get('sync', 12); ?>
                                            <?php echo (int) $order->auto_renew === 1
                                                ? esc_html($_renew_cycle)
                                                : esc_html__('No auto-renew', 'wpdm-premium-packages');
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="wpdmpp-order-card__actions">
                                <?php if ($is_processing) : ?>
                                    <button type="button"
                                            class="wpdmpp-btn wpdmpp-btn--icon-danger wpdmpp-delete-order"
                                            data-order-id="<?php echo esc_attr($order->order_id); ?>"
                                            data-nonce="<?php echo esc_attr($delete_nonce); ?>"
                                            title="<?php esc_attr_e('Delete Order', 'wpdm-premium-packages'); ?>">
                                        <?php echo \WPDMPP\UI\Icons::get('trash', 16); ?>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo esc_url($zurl); ?>" class="wpdmpp-btn wpdmpp-btn--primary">
                                    <?php _e('View Details', 'wpdm-premium-packages'); ?>
                                    <?php echo \WPDMPP\UI\Icons::get('arrow-right', 14); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="wpdmpp-orders__empty" id="wpdmpp-filter-empty" style="display:none">
                <div class="wpdmpp-orders__empty-icon">
                    <?php echo \WPDMPP\UI\Icons::get('filter', 28); ?>
                </div>
                <p class="wpdmpp-orders__empty-text"><?php _e('No orders match this filter.', 'wpdm-premium-packages'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <script type="text/javascript">
    jQuery(function($){

        /* ── Status Filter Tabs ── */
        var $tabs    = $('.wpdmpp-filter-tab');
        var $cards   = $('.wpdmpp-order-card');
        var $empty   = $('#wpdmpp-filter-empty');

        $tabs.on('click', function(){
            var status = $(this).data('status');
            $tabs.removeClass('is-active');
            $(this).addClass('is-active');

            if (status === 'all') {
                $cards.removeClass('is-hidden');
                $empty.hide();
            } else {
                var visible = 0;
                $cards.each(function(){
                    if ($(this).data('status') === status) {
                        $(this).removeClass('is-hidden');
                        visible++;
                    } else {
                        $(this).addClass('is-hidden');
                    }
                });
                $empty.toggle(visible === 0);
            }
        });

        /* ── Delete Order ── */
        $('.wpdmpp-delete-order').on('click', async function(e){
            e.preventDefault();
            var orderId = $(this).data('order-id');
            var nonce   = $(this).data('nonce');

            var confirmed = await WPDM.dialog.confirmDelete(
                '<?php echo esc_js(__('Delete Order', 'wpdm-premium-packages')); ?>',
                '<?php echo esc_js(__('Are you sure you want to delete this order? This action cannot be undone.', 'wpdm-premium-packages')); ?>'
            );

            if (!confirmed) return;

            $.ajax({
                type: 'post',
                dataType: 'json',
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                data: {
                    action: 'wpdmpp_delete_frontend_order',
                    order_id: orderId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.type === 'success') {
                        var $card = $('#order_' + orderId);
                        $card.css({ overflow: 'hidden', transition: 'all 300ms ease' })
                             .animate({ opacity: 0, height: 0, marginBottom: 0 }, 300, function(){
                            $(this).remove();
                            $cards = $('.wpdmpp-order-card');
                            var remaining = $cards.length;
                            if (remaining === 0) {
                                $('.wpdmpp-orders__list').replaceWith(
                                    '<div class="wpdmpp-orders__empty">' +
                                    '<div class="wpdmpp-orders__empty-icon"><?php echo \WPDMPP\UI\Icons::get('shopping-bag', 28); ?></div>' +
                                    '<p class="wpdmpp-orders__empty-text"><?php _e('No orders found.', 'wpdm-premium-packages'); ?></p></div>'
                                );
                                $('.wpdmpp-orders__count').remove();
                                $('.wpdmpp-orders__filters').remove();
                            } else {
                                $('.wpdmpp-orders__count').text(remaining);
                                var counts = { all: remaining };
                                $cards.each(function(){
                                    var s = $(this).data('status');
                                    counts[s] = (counts[s] || 0) + 1;
                                });
                                $tabs.each(function(){
                                    var s = $(this).data('status');
                                    var c = counts[s] || 0;
                                    $(this).find('.wpdmpp-filter-tab__count').text(s === 'all' ? remaining : c);
                                    if (s !== 'all' && c === 0) $(this).remove();
                                });
                                $tabs = $('.wpdmpp-filter-tab');
                                var activeStatus = $tabs.filter('.is-active').data('status');
                                if (activeStatus !== 'all' && !counts[activeStatus]) {
                                    $tabs.removeClass('is-active');
                                    $tabs.filter('[data-status="all"]').addClass('is-active').trigger('click');
                                }
                            }
                        });
                    } else {
                        WPDM.dialog.error('<?php echo esc_js(__('Error', 'wpdm-premium-packages')); ?>', '<?php echo esc_js(__('Something went wrong. Please try again.', 'wpdm-premium-packages')); ?>');
                    }
                },
                error: function() {
                    WPDM.dialog.error('<?php echo esc_js(__('Error', 'wpdm-premium-packages')); ?>', '<?php echo esc_js(__('Something went wrong. Please try again.', 'wpdm-premium-packages')); ?>');
                }
            });
        });
    });
    </script>

<?php
} // end if ( ! isset($_GET['id']) && ! isset($_GET['item']) )
