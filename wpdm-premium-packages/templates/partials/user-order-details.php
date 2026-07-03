<?php
/**
 * Order details in Frontend User Dashboard >> Purchases >> Order details page
 *
 * This template can be overridden by copying it to yourtheme/download-manager/user-dashboard/order-details.php.
 *
 * @version     3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdmpp_settings, $wpdb;
$current_user   = wp_get_current_user();
$currency_data  = $order->getCurrency();
$currency_sign  = is_array($currency_data) && isset($currency_data['sign']) ? $currency_data['sign'] : wpdmpp_currency_sign();
$download_button_label = esc_attr__( 'Download', 'download-manager' );

// Pre-compute status data
$order_status   = $order->getOrderStatus();
$order_id       = $order->getOrderId();
$is_completed   = ($order_status === 'Completed');
$is_expired     = ($order_status === 'Expired');
$is_processing  = ($order_status === 'Processing');
$status_key     = strtolower($order_status);

// Dark mode
$color_scheme = get_option('__wpdm_color_scheme', 'system');
$dark_class   = $color_scheme === 'dark' ? ' wpdmpp-dark' : '';

// Order validity
$wpdmpp_settings['order_validity_period'] = (int)($wpdmpp_settings['order_validity_period'] ?? 0) > 0 ? (int)$wpdmpp_settings['order_validity_period'] : 365;
$usermeta = maybe_unserialize(get_user_meta($current_user->ID, 'user_billing_shipping', true));
if(is_array($usermeta)) extract($usermeta);

// Subscription data (computed early for header buttons)
$has_auto_renew = isset($wpdmpp_settings['auto_renew']) && $wpdmpp_settings['auto_renew'] == 1;
$auto_renew_active = false;
$renew_count = 0;
if ($has_auto_renew) {
    $auto_renew_active = (int)$order->getAutoRenew() === 1;
    $renew_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(ID) FROM {$wpdb->prefix}ahm_order_renews WHERE order_id = %s",
        $order_id
    ) );
}

// Membership order — the renewal period comes from the membership plan, not
// the global order validity period. Read the snapshot stored at order creation;
// fall back to the plan post meta for orders placed before the snapshot existed.
$membership_meta        = $order->getMeta('membership');
$is_membership          = is_array($membership_meta);
$membership_period      = $is_membership ? (int) ($membership_meta['period'] ?? 0) : 0;
$membership_period_unit = $is_membership ? (string) ($membership_meta['period_unit'] ?? '') : '';

if (!$is_membership) {
    foreach ((array) $items as $_mi) {
        $_pid = $_mi['pid'] ?? 0;
        if ($_pid && get_post_type($_pid) === 'wpdm_plan') {
            $is_membership          = true;
            $membership_period      = (int) get_post_meta($_pid, '_plan_period', true);
            $membership_period_unit = (string) get_post_meta($_pid, '_plan_period_unit', true);
            break;
        }
    }
}

$membership_cycle_label = '';
if ($is_membership && $membership_period > 0 && $membership_period_unit !== '') {
    $membership_cycle_label = sprintf(
        _n('Every %1$d %2$s', 'Every %1$d %2$ss', $membership_period, 'wpdm-premium-packages'),
        $membership_period,
        $membership_period_unit
    );
}
?>

<div class="wpdmpp-order-detail<?php echo $dark_class; ?>" id="wpdmpp-od-root"<?php if ($color_scheme === 'system') echo ' data-cs="system"'; ?>>

<?php if ($color_scheme === 'system') : ?>
<script>if(window.matchMedia('(prefers-color-scheme:dark)').matches)document.getElementById('wpdmpp-od-root').classList.add('wpdmpp-dark');</script>
<?php endif; ?>

<?php // ── Session Messages ── ?>
<?php if(\WPDM\__\Session::get('wpdm_global_msg_success')): ?>
    <div class="wpdmpp-od__alert wpdmpp-od__alert--success">
        <?php echo \WPDMPP\UI\Icons::get('check-circle', 16); ?>
        <?php echo esc_html(\WPDM\__\Session::get('wpdm_global_msg_success')); ?>
    </div>
    <?php \WPDM\__\Session::clear('wpdm_global_msg_success'); ?>
<?php endif; ?>

<?php if(\WPDM\__\Session::get('wpdm_global_msg_error')): ?>
    <div class="wpdmpp-od__alert wpdmpp-od__alert--error">
        <?php echo \WPDMPP\UI\Icons::get('info-circle', 16); ?>
        <?php echo esc_html(\WPDM\__\Session::get('wpdm_global_msg_error')); ?>
    </div>
    <?php \WPDM\__\Session::clear('wpdm_global_msg_error'); ?>
<?php endif; ?>

<?php do_action("wpdmpp_before_order_details", $order); ?>

<?php // ── Header ── ?>
<div class="wpdmpp-od__header">
    <div class="wpdmpp-od__breadcrumb">
        <?php if ( empty($hide_orders_link) ) : // Guest order view hides the "All Orders" link (no order list to return to). ?>
        <a href="<?php echo esc_url($link); ?>"><?php echo \WPDMPP\UI\Icons::get('arrow-left', 14); ?> <?php _e("All Orders", "wpdm-premium-packages"); ?></a>
        <?php echo \WPDMPP\UI\Icons::get('chevron-right', 14); ?>
        <?php endif; ?>
        <span class="wpdmpp-od__breadcrumb-current"><?php echo $title; ?></span>
        <span class="wpdmpp-status wpdmpp-status--<?php echo esc_attr($status_key); ?>"><?php echo esc_html($order_status); ?></span>
    </div>
    <div class="wpdmpp-od__actions">
        <?php if ( $is_completed ) : ?>
            <button type="button" class="wpdmpp-btn wpdmpp-btn--sm" onclick="WPDM.popupWindow('<?php echo esc_js(home_url("/?id={$order_id}&wpdminvoice=1")); ?>', 'Invoice', 800, 810)">
                <?php echo \WPDMPP\UI\Icons::get('file-text', 14); ?> <?php _e('Invoice', 'wpdm-premium-packages'); ?>
            </button>
        <?php endif; ?>
        <?php if ($has_auto_renew && $is_completed) : ?>
            <?php if ($auto_renew_active) : ?>
                <button id="btn-cansub" class="wpdmpp-btn wpdmpp-btn--danger wpdmpp-btn--sm"><?php _e('Cancel Subscription', 'wpdm-premium-packages'); ?></button>
            <?php else : ?>
                <button id="btn-actsub" class="wpdmpp-btn wpdmpp-btn--sm"><?php _e('Activate', 'wpdm-premium-packages'); ?></button>
            <?php endif; ?>
        <?php endif; ?>
        <?php echo $extbtns; ?>
    </div>
</div>

<?php // ── Summary Cards ── ?>
<?php
$status_icon_map = [
    'completed'  => 'check-circle',
    'processing' => 'clock',
    'expired'    => 'times-circle',
    'cancelled'  => 'close',
    'refunded'   => 'redo',
];
$status_icon = $status_icon_map[$status_key] ?? 'circle-dot';
?>
<div class="wpdmpp-od__summary">
    <div class="wpdmpp-od__card">
        <div class="wpdmpp-od__card-icon wpdmpp-od__card-icon--<?php echo esc_attr($status_key); ?>"><?php echo \WPDMPP\UI\Icons::get($status_icon, 18); ?></div>
        <div class="wpdmpp-od__card-body">
            <div class="wpdmpp-od__card-label"><?php _e('Order Status', 'wpdm-premium-packages'); ?></div>
            <div class="wpdmpp-od__card-value"><?php echo esc_html($order_status); ?></div>
        </div>
    </div>
    <div class="wpdmpp-od__card">
        <div class="wpdmpp-od__card-icon"><?php echo \WPDMPP\UI\Icons::get('calendar', 18); ?></div>
        <div class="wpdmpp-od__card-body">
            <div class="wpdmpp-od__card-label"><?php _e('Order Date', 'wpdm-premium-packages'); ?></div>
            <div class="wpdmpp-od__card-value"><?php echo wp_date(get_option('date_format'), $order->getDate()); ?></div>
        </div>
    </div>
    <div class="wpdmpp-od__card">
        <div class="wpdmpp-od__card-icon"><?php echo \WPDMPP\UI\Icons::get('credit-card', 18); ?></div>
        <div class="wpdmpp-od__card-body">
            <div class="wpdmpp-od__card-label"><?php _e('Payment Method', 'wpdm-premium-packages'); ?></div>
            <div class="wpdmpp-od__card-value"><?php echo esc_html(WPDMPP()->payment->getGatewayTitle($order->getPaymentMethod())); ?></div>
        </div>
    </div>
</div>

<?php // ── Subscription Info ── ?>
<?php if($has_auto_renew || $is_membership) : ?>
<div class="wpdmpp-od__subscription">
    <div class="wpdmpp-od__sub-card">
        <div class="wpdmpp-od__sub-label"><?php _e('Auto Renew', 'wpdm-premium-packages'); ?></div>
        <div class="wpdmpp-od__sub-value">
            <span id="wpdmpp-csstatus" class="wpdmpp-renew-badge <?php echo $auto_renew_active ? 'wpdmpp-renew-badge--active' : 'wpdmpp-renew-badge--inactive'; ?>">
                <?php echo $auto_renew_active ? __('Active', 'wpdm-premium-packages') : __('Inactive', 'wpdm-premium-packages'); ?>
            </span>
        </div>
    </div>
    <div class="wpdmpp-od__sub-card">
        <div class="wpdmpp-od__sub-label"><?php echo $auto_renew_active ? __('Next Renew Date', 'wpdm-premium-packages') : __('Expiry Date', 'wpdm-premium-packages'); ?></div>
        <div class="wpdmpp-od__sub-value"><?php echo wp_date(get_option('date_format'), $order->getExpireDate()); ?></div>
    </div>
    <div class="wpdmpp-od__sub-card">
        <?php if ($is_membership && $membership_cycle_label !== '') : ?>
            <div class="wpdmpp-od__sub-label"><?php _e('Billing Period', 'wpdm-premium-packages'); ?></div>
            <div class="wpdmpp-od__sub-value"><?php echo esc_html($membership_cycle_label); ?></div>
        <?php else : ?>
            <div class="wpdmpp-od__sub-label"><?php _e('Renew Cycle', 'wpdm-premium-packages'); ?></div>
            <div class="wpdmpp-od__sub-value"><?php echo $renew_count; ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
/**
 * Fires after the order-details subscription cards.
 * Add-ons (e.g. wpdm-membership) can render their own info panel here.
 *
 * @param \WPDMPP\Order\Order $order The current order.
 */
do_action('wpdmpp_order_details_after_subscription', $order);
?>

<?php // ── Order Items ── ?>
<div class="wpdmpp-od__section">
    <h3 class="wpdmpp-od__section-title"><?php echo \WPDMPP\UI\Icons::get('shopping-bag', 18); ?> <?php _e("Order Items", "wpdm-premium-packages"); ?></h3>
    <div class="wpdmpp-od__items-wrap">

        <div class="wpdmpp-od__items-head<?php echo $is_completed ? ' has-download' : ''; ?>">
            <div><?php _e("Product", "wpdm-premium-packages"); ?></div>
            <div><?php _e("Price", "wpdm-premium-packages"); ?></div>
            <div><?php _e("License", "wpdm-premium-packages"); ?></div>
            <div><?php _e("Total", "wpdm-premium-packages"); ?></div>
            <?php if($is_completed): ?>
                <div class="wpdmpp-od__col-right"><?php _e("Download", "wpdm-premium-packages"); ?></div>
            <?php endif; ?>
        </div>

        <?php
        $total = 0;
        $cart_items = $order->getCartData();

        // Map a file extension to a file-type icon for the file modal.
        $wpdmpp_file_icon = static function ($filename) {
            $map = [
                'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
                'webp' => 'image', 'svg' => 'image', 'bmp' => 'image', 'ico' => 'image',
                'mp4' => 'film', 'mov' => 'film', 'avi' => 'film', 'mkv' => 'film', 'webm' => 'film',
                'mp3' => 'music', 'wav' => 'music', 'ogg' => 'music', 'flac' => 'music', 'm4a' => 'music',
                'zip' => 'archive', 'rar' => 'archive', '7z' => 'archive', 'tar' => 'archive', 'gz' => 'archive',
            ];
            $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
            return $map[$ext] ?? 'file-text';
        };

        foreach ($items as $item) :
            $ditem = null;
            if(wpdm_valueof($item, 'product_type') !== 'dynamic')
                $ditem = get_post($item['pid']);

            $discount_r     = $item['role_discount'];
            $prices         = 0;
            $discount       = $discount_r;

            $itotal         = number_format(((($item['price'] + $prices) * $item['quantity']) - $discount - $item['coupon_discount']), 2, ".", "");
            $total          += $itotal;
            $download_link  = \WPDMPP\WPDMPremiumPackage::customerDownloadURL($item['pid'], $order_id);

            $license = maybe_unserialize($item['license']);

            $license_label = isset($license, $license['name'])
                ? esc_html($license['name'])
                : '';

            // License key button
            $licenseg = "";
            if (get_post_meta($item['pid'], '__wpdm_enable_license_key', true) == 1 && $is_completed) {
                $key_icon = \WPDMPP\UI\Icons::get('key', 14);
                $licenseg = '<a id="lic_'.$item['pid'].'_'.$order_id.'_btn" onclick="return getkey(\''.$item['pid'].'\',\''.$order_id.'\', \'#\'+this.id);" class="wpdmpp-od__license-btn" href="#">'.$key_icon.'</a>';
            }

            // Files
            $files = WPDM()->package->getFiles($item['pid'], true);
            $cart  = $order->getCartData();

            $sfiles = isset($cart[$item['pid']], $cart[$item['pid']]['files']) ? $cart[$item['pid']]['files'] : array();
            $sfiles = is_array($sfiles) ? $sfiles : array();
            $cfiles = array();
            foreach ($sfiles as $fID){
                if($fID !== '' && isset($files[$fID]))
                    $cfiles[$fID] = $files[$fID];
            }

            // Get files for purchased license
            if(count($cfiles) === 0) {
                $all_licenses = wpdmpp_get_licenses();
                $starter = array_keys($all_licenses)[0];
                $_license = wpdm_valueof($cart, "{$item['pid']}/license/id");
                if(!$_license) $_license = $starter;
                $license_pack = $ditem ? get_post_meta($item['pid'], "__wpdm_license_pack", true) : '';
                $license_pack = wpdm_valueof($license_pack, $_license);
                if(is_array($license_pack)) {
                    foreach ($license_pack as $fID) {
                        if(isset($files[$fID]))
                            $cfiles[$fID] = $files[$fID];
                    }
                }
            }

            if(is_array($cfiles) && count($cfiles) > 0)
                $files = $cfiles;

            if(!is_array($files)) $files = [];

            $file_count = count($files);
            $discount = number_format(floatval($discount), 2);
            $item['price'] = number_format($item['price'], 2);

            $show_download_button = $is_completed && (
                ($file_count > 1 && !(int)get_wpdmpp_option('disable_multi_file_download', 0))
                || $file_count === 1
            );

            $has_download_class = $is_completed ? ' has-download' : '';
        ?>

        <div class="wpdmpp-od__item<?php echo $has_download_class; ?>">
            <div class="wpdmpp-od__item-product">
                <div class="wpdmpp-od__item-thumb"><?php WPDMPP()->cart->itemThumb($item, true, ['style' => 'width: 40px']) ?></div>
                <div class="wpdmpp-od__item-info">
                    <div class="wpdmpp-od__item-name"><?php WPDMPP()->cart->itemLink($item) ?></div>
                    <?php if($license_label): ?>
                        <div class="wpdmpp-od__item-meta"><?php echo $license_label; ?></div>
                    <?php elseif($item['quantity'] > 1): ?>
                        <div class="wpdmpp-od__item-meta"><?php printf(__('Qty: %d', 'wpdm-premium-packages'), $item['quantity']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wpdmpp-od__item-price"><?php echo wpdmpp_price_format($item['price'], $currency_sign, true); ?></div>

            <div class="wpdmpp-od__item-license" id="lic_<?php echo $item['pid'].'_'.$order_id; ?>">
                <?php echo $licenseg ?: '&mdash;'; ?>
            </div>

            <div class="wpdmpp-od__item-total"><?php echo WPDMPP()->order->itemCost($item, $currency_sign); ?></div>

            <?php if ($is_completed) : ?>
            <div class="wpdmpp-od__item-download">
                <?php if ($show_download_button): ?>
                    <a href="<?php echo esc_url($download_link); ?>" class="wpdmpp-btn wpdmpp-btn--success d-block text-center w-100 wpdmpp-btn--sm"><?php echo \WPDMPP\UI\Icons::get('download', 12); ?> <?php echo esc_html($download_button_label); ?></a>
                <?php endif; ?>
                <?php if ($file_count > 1): ?>
                    <button type="button" class="wpdmpp-btn d-block w-100 wpdmpp-btn--sm wpdmpp-od__open-files" data-target="#wpdmpp-files-<?php echo esc_attr($item['pid']); ?>">
                        <?php echo \WPDMPP\UI\Icons::get('list', 12); ?> <?php printf(__('Files (%d)', 'wpdm-premium-packages'), $file_count); ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php // File list modal — keeps the items table compact regardless of file count ?>
        <?php if ($is_completed && $file_count > 1) :
            $item_title = get_the_title($item['pid']);
        ?>
        <div class="wpdmpp-od__modal" id="wpdmpp-files-<?php echo esc_attr($item['pid']); ?>" aria-hidden="true">
            <div class="wpdmpp-od__modal-backdrop" data-close></div>
            <div class="wpdmpp-od__modal-dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr(sprintf(__('Files for %s', 'wpdm-premium-packages'), $item_title)); ?>">
                <div class="wpdmpp-od__modal-head">
                    <div class="wpdmpp-od__modal-title">
                        <?php echo \WPDMPP\UI\Icons::get('layers', 16); ?>
                        <span class="wpdmpp-od__modal-name"><?php echo esc_html($item_title); ?></span>
                        <span class="wpdmpp-od__modal-count"><?php printf(_n('%d file', '%d files', $file_count, 'wpdm-premium-packages'), $file_count); ?></span>
                    </div>
                    <button type="button" class="wpdmpp-od__modal-close" data-close aria-label="<?php esc_attr_e('Close', 'wpdm-premium-packages'); ?>"><?php echo \WPDMPP\UI\Icons::get('close', 18); ?></button>
                </div>
                <div class="wpdmpp-od__modal-body">
                    <?php if ($file_count > 5): ?>
                    <div class="wpdmpp-od__modal-search">
                        <?php echo \WPDMPP\UI\Icons::get('search', 16); ?>
                        <input type="search" class="wpdm-od-search-file" data-filelist="#wpdmpp-filelist-<?php echo esc_attr($item['pid']); ?>" placeholder="<?php echo esc_attr(sprintf(__("Search %d files...", "wpdm-premium-packages"), $file_count)); ?>" />
                    </div>
                    <?php endif; ?>
                    <ul class="wpdmpp-od__files-list" id="wpdmpp-filelist-<?php echo esc_attr($item['pid']); ?>">
                        <?php foreach ($files as $ind => $ff):
                            $data = $ditem ? get_post_meta($ditem->ID,'__wpdm_fileinfo', true) : [];
                            $file_title = isset($data[$ind], $data[$ind]['title']) && !empty($data[$ind]['title']) ? $data[$ind]['title'] : basename($ff);
                        ?>
                        <li class="wpdmpp-od__file-item">
                            <span class="wpdmpp-od__file-icon"><?php echo \WPDMPP\UI\Icons::get($wpdmpp_file_icon($ff), 16); ?></span>
                            <span class="wpdmpp-od__file-name" title="<?php echo esc_attr($file_title); ?>"><?php echo esc_html($file_title); ?></span>
                            <a href="<?php echo esc_url($download_link . '&ind=' . $ind); ?>" class="wpdmpp-od__file-dl" aria-label="<?php echo esc_attr(sprintf(__('Download %s', 'wpdm-premium-packages'), $file_title)); ?>"><?php echo \WPDMPP\UI\Icons::get('download', 16); ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="wpdmpp-od__files-empty"><?php _e('No files match your search.', 'wpdm-premium-packages'); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Order item filter hook
        $order_item = apply_filters("wpdmpp_order_item", "", $item);
        if ($order_item != '') : ?>
            <div class="wpdmpp-od__item-extra"><?php echo $order_item; ?></div>
        <?php endif;

        endforeach; ?>

        <?php // ── Financial Totals ── ?>
        <div class="wpdmpp-od__totals">
            <div class="wpdmpp-od__total-row">
                <span><?php _e("Subtotal", "wpdm-premium-packages"); ?></span>
                <span><?php echo wpdmpp_price_format($order->getSubtotal(), $currency_sign); ?></span>
            </div>
            <?php if(floatval($order->getCouponDiscount()) > 0): ?>
            <div class="wpdmpp-od__total-row">
                <span><?php _e("Coupon Discount", "wpdm-premium-packages"); ?></span>
                <span>&minus; <?php echo wpdmpp_price_format($order->getCouponDiscount(), $currency_sign); ?></span>
            </div>
            <?php endif; ?>
            <?php if(floatval($order->getTax()) > 0): ?>
            <div class="wpdmpp-od__total-row">
                <span><?php _e("Tax", "wpdm-premium-packages"); ?></span>
                <span>+ <?php echo wpdmpp_price_format($order->getTax(), $currency_sign); ?></span>
            </div>
            <?php endif; ?>
            <div class="wpdmpp-od__total-row wpdmpp-od__total-row--grand">
                <span><?php _e("Total", "wpdm-premium-packages"); ?></span>
                <span><?php echo wpdmpp_price_format($order->getTotal(), $currency_sign); ?></span>
            </div>
        </div>
    </div>
</div>

<?php // ── Payment CTA ── ?>
<?php if (!$is_completed) :
    $purl = home_url('/?pay_now=' . $order_id);
    $homeurl = home_url('/');

    if ($is_expired) :
        $vdlink = sprintf(__("Get continuous support and update for another %d days", "wpdm-premium-packages"), $wpdmpp_settings['order_validity_period']);
        $pnow = __("Renew Now", "wpdm-premium-packages");
        $order_specific_manual_renewal = (int)\WPDMPP\Order\OrderService::instance()->getMeta($order_id, 'manual_renew');
        $disable_manual_renewal_period = ((int)get_wpdmpp_option('disable_manual_renewal_period', 90))*86400;
        $disable_manual_renewal_time = $order->getExpireDate() + $disable_manual_renewal_period;
        $manual_renewal_allowed = $disable_manual_renewal_time < time() && (int)get_wpdmpp_option('disable_manual_renew');

        if (!$manual_renewal_allowed || $order_specific_manual_renewal === 1) : ?>
            <div class="wpdmpp-od__cta" id="proceed_<?php echo $order_id; ?>">
                <div class="wpdmpp-od__cta-text"><?php echo $vdlink; ?></div>
                <a class="wpdmpp-btn wpdmpp-btn--success" onclick="return proceed2payment_<?php echo $order_id; ?>(this)" href="#"><?php echo $pnow; ?></a>
            </div>
        <?php else: ?>
            <div class="wpdmpp-od__cta" id="proceed_<?php echo $order_id; ?>">
                <div class="wpdmpp-od__cta-text"><?php _e('Manual renewal for your order is not possible. Please create new order.', 'wpdm-premium-packages'); ?></div>
                <a class="wpdmpp-btn wpdmpp-btn--success" href="<?php echo esc_url(wpdmpp_cart_url(['clone_order' => $order_id])); ?>"><?php _e('Create New Order', 'wpdm-premium-packages'); ?></a>
            </div>
        <?php endif;
    else : ?>
        <div class="wpdmpp-od__cta" id="proceed_<?php echo $order_id; ?>">
            <div class="wpdmpp-od__cta-text"><?php _e("If you still want to complete this order ", "wpdm-premium-packages"); ?></div>
            <a class="wpdmpp-btn wpdmpp-btn--success" onclick="return proceed2payment_<?php echo $order_id; ?>(this)" href="#"><?php _e("Pay Now", "wpdm-premium-packages"); ?></a>
        </div>
    <?php endif; ?>

    <script>
    function proceed2payment_<?php echo $order_id; ?>(ob){
        jQuery('#proceed_<?php echo $order_id; ?>').html('<?php echo \WPDMPP\UI\Icons::spinner(16); ?> <?php _e("Processing...", "wpdm-premium-packages"); ?>');
        jQuery.post(wpdm_url.ajax,{action:'wpdmpp_payment_intent', order_id:'<?php echo $order_id; ?>'},function(res){
            jQuery('#proceed_<?php echo $order_id; ?>').html(res);
        });
        return false;
    }
    </script>
<?php endif; ?>

<?php do_action("wpdmpp_after_order_details", $order); ?>

<?php // ── Renewal Invoices (inlined from renew-invoices.php) ── ?>
<?php if(count($renews) > 0): ?>
<div class="wpdmpp-od__section wpdmpp-od__invoices">
    <h3 class="wpdmpp-od__section-title"><?php echo \WPDMPP\UI\Icons::get('file-text', 18); ?> <?php _e('Renewal Invoices', 'wpdm-premium-packages'); ?></h3>
    <div class="wpdmpp-od__invoice-list">
        <?php foreach ($renews as $renew): ?>
        <div class="wpdmpp-od__invoice-row">
            <span><?php echo wp_date(get_option('date_format'), $renew->date); ?></span>
            <button type="button" class="wpdmpp-btn wpdmpp-btn--sm" onclick="WPDM.popupWindow('<?php echo esc_js(home_url("/?id={$order_id}&wpdminvoice=1&renew={$renew->date}")); ?>','Invoice','800','810'); return false;">
                <?php echo \WPDMPP\UI\Icons::get('file-text', 14); ?> <?php _e('View Invoice', 'wpdm-premium-packages'); ?>
            </button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php // ── Order Notes (inlined from order-notes.php) ── ?>
<?php if((int)get_wpdmpp_option('disable_order_notes', 0) === 0): ?>
<div class="wpdmpp-od__section wpdmpp-od__notes">
    <h3 class="wpdmpp-od__section-title"><?php echo \WPDMPP\UI\Icons::get('edit', 18); ?> <?php _e('Order Notes', 'wpdm-premium-packages'); ?></h3>

    <div id="all-notes">
    <?php
    $order_notes = $order->getNotes();
    $order_uid = (int) $order->getUserId();
    if (isset($order_notes['messages'])) :
        foreach ($order_notes['messages'] as $time => $order_note) :
            $copy = array();
            if (isset($order_note['admin'])) $copy[] = 'Admin';
            if (isset($order_note['seller'])) $copy[] = 'Seller';
            if (isset($order_note['customer'])) $copy[] = 'Customer';

            // Show "Customer" for order owner's notes, "Seller" for admin/seller notes
            $note_sender = (isset($order_note['uid']) && (int) $order_note['uid'] === $order_uid)
                || (!isset($order_note['uid']) && isset($order_note['by']) && $order_note['by'] === $current_user->display_name)
                ? __('Customer', 'wpdm-premium-packages')
                : __('Seller', 'wpdm-premium-packages');
    ?>
    <div class="wpdmpp-od__note">
        <div class="wpdmpp-od__note-body">
            <?php echo strip_tags(wpautop(stripslashes_deep($order_note['note'])), "<a><strong><b><img><br><p>"); ?>
        </div>
        <?php if (isset($order_note['file']) && is_array($order_note['file'])) : ?>
        <div class="wpdmpp-od__note-attachments">
            <?php foreach ($order_note['file'] as $id => $file) :
                $aid = \WPDM\__\Crypt::Encrypt($order_id . "|||" . $time . "|||" . $file);
            ?>
                <a href="<?php echo esc_url(home_url("/?oid=" . $order_id . "&_atcdl=" . $aid)); ?>">
                    <?php echo \WPDMPP\UI\Icons::get('paperclip', 13); ?> <?php echo esc_html($file); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="wpdmpp-od__note-footer">
            <div class="wpdmpp-od__note-meta">
                <span class="wpdmpp-od__note-meta-item"><?php echo \WPDMPP\UI\Icons::get('pencil', 12); ?> <?php echo esc_html($note_sender); ?></span>
                <span class="wpdmpp-od__note-meta-item"><?php echo \WPDMPP\UI\Icons::get('clock', 12); ?> <?php echo wp_date(get_option('date_format') . " h:i", $time); ?></span>
                <?php if (!empty($copy)): ?>
                <span class="wpdmpp-od__note-meta-item"><?php _e('Sent to:', 'wpdm-premium-packages'); ?> <?php echo esc_html(implode(', ', $copy)); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach;
    endif; ?>
    </div>

    <form method="post" id="post-order-note">
        <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>"/>

        <div class="wpdmpp-od__note-form">
            <textarea required id="order-note" name="note" placeholder="<?php esc_attr_e('Write a note...', 'wpdm-premium-packages'); ?>"></textarea>

            <div class="wpdmpp-od__note-upload" id="wpdmpp-note-upload-ui">
                <?php
                // "*" (alone or in the list) means all files allowed → no accept filter.
                $wpdm_note_allowed_types = (string) get_option('__wpdm_allowed_file_types', 'png,pdf,jpg,txt');
                $wpdm_note_allow_all = trim($wpdm_note_allowed_types) === '*'
                    || in_array('*', array_map('trim', explode(',', $wpdm_note_allowed_types)), true);
                ?>
                <input type="file" id="wpdmpp-note-file-input" style="display:none"
                       accept="<?php echo $wpdm_note_allow_all ? '' : esc_attr('.' . str_replace(',', ',.', $wpdm_note_allowed_types)); ?>" />
                <label for="wpdmpp-note-file-input" id="wpdmpp-note-browse-btn" class="wpdmpp-btn wpdmpp-btn--sm" style="cursor:pointer;margin:0">
                    <?php echo \WPDMPP\UI\Icons::get('file-upload', 14); ?> <?php _e("Select File", "download-manager"); ?>
                </label>
                <div class="progress" id="wpdmpp-note-progressbar" style="display:none">
                    <div id="wpdmpp-note-progress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%;line-height:20px;background-color:var(--o-primary,#6366f1)"></div>
                    <div style="font-size:8px;position:absolute;line-height:20px;height:20px;width:100%;z-index:999;text-align:center;color:#ffffff;letter-spacing:1px">
                        <?php _e('UPLOADING...', 'download-manager'); ?> <span id="wpdmpp-note-loaded">0</span>%
                    </div>
                </div>
                <div id="wpdmpp-note-filelist" style="display:flex;gap:6px;flex-wrap:wrap"></div>
            </div>

            <div class="wpdmpp-od__note-footer-bar">
                <div class="wpdmpp-od__note-recipients">
                    <span><?php _e('Send to:', 'wpdm-premium-packages'); ?></span>
                    <label><input type="checkbox" name="admin" value="1"> <?php _e('Admin', 'wpdm-premium-packages'); ?></label>
                    <label><input type="checkbox" name="seller" value="1"> <?php _e('Seller', 'wpdm-premium-packages'); ?></label>
                    <label><input type="checkbox" name="customer" value="1"> <?php _e('Customer', 'wpdm-premium-packages'); ?></label>
                </div>
                <button class="wpdmpp-btn wpdmpp-btn--primary wpdmpp-btn--sm" id="add-note-button" type="submit">
                    <?php echo \WPDMPP\UI\Icons::get('plus-circle', 14); ?> <?php _e('Add Note', 'wpdm-premium-packages'); ?>
                </button>
            </div>
        </div>
    </form>

    <?php
    // File upload configuration (native HTML5)
    $upload_config = array(
        'url' => admin_url('admin-ajax.php'),
        'params' => array(
            '_ajax_nonce' => wp_create_nonce(NONCE_KEY),
            'action' => 'wpdm_frontend_file_upload',
            'section' => 'wpdm_order_note',
        ),
        'max_size' => wp_max_upload_size(),
    );
    // REST API config for note submission
    $rest_config = array(
        'url' => esc_url_raw(rest_url('wpdmpp/v1/orders/' . $order_id . '/note')),
        'nonce' => wp_create_nonce('wp_rest'),
    );
    ?>
</div>
<?php endif; ?>

</div><?php // .wpdmpp-order-detail ?>

<script>
jQuery(function($){

    // ── File modal ──
    function openFilesModal($modal){
        if(!$modal.length) return;
        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('wpdmpp-od-modal-open');
        var $search = $modal.find('.wpdm-od-search-file');
        if($search.length){ setTimeout(function(){ $search.trigger('focus'); }, 60); }
    }
    function closeFilesModal($modal){
        if(!$modal.length) return;
        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('wpdmpp-od-modal-open');
    }

    $('body').on('click', '.wpdmpp-od__open-files', function(){
        openFilesModal($($(this).data('target')));
    });
    $('body').on('click', '.wpdmpp-od__modal [data-close]', function(){
        closeFilesModal($(this).closest('.wpdmpp-od__modal'));
    });
    $(document).on('keydown', function(e){
        if(e.key === 'Escape') closeFilesModal($('.wpdmpp-od__modal.is-open'));
    });

    // File search (scoped to the modal's list)
    $('body').on('keyup', '.wpdm-od-search-file', function(){
        var value = $(this).val().toLowerCase();
        var list = $(this).data('filelist');
        var visible = 0;
        $(list + ' li').each(function(){
            var match = $(this).text().toLowerCase().indexOf(value) > -1;
            $(this).toggle(match);
            if(match) visible++;
        });
        $(list).siblings('.wpdmpp-od__files-empty').toggle(visible === 0);
    });

    <?php if(isset($wpdmpp_settings['auto_renew']) && $wpdmpp_settings['auto_renew'] == 1): ?>
    // Subscription cancel
    $('#btn-cansub').on('click', async function(){
        var confirmed = await WPDM.dialog.confirmDelete(
            '<?php echo esc_js(__('Cancel Subscription', 'wpdm-premium-packages')); ?>',
            '<?php echo esc_js(sprintf(__('If you cancel auto-renew, you will lose access to support and updates after %s. Are you sure?', 'wpdm-premium-packages'), wp_date(get_option('date_format'), $order->getExpireDate()))); ?>'
        );
        if(!confirmed) return;

        var $btn = $(this);
        $btn.attr('disabled','disabled').html('<?php echo \WPDMPP\UI\Icons::spinner(14); ?>');
        $.post(wpdm_url.ajax, {action: 'wpdmpp_cancel_subscription', __cansub: '<?php echo wp_create_nonce(NONCE_KEY) ?>', orderid: '<?php echo $order_id ?>'}, function(res){
            $btn.html('<?php echo esc_js(__('Canceled', 'wpdm-premium-packages')); ?>');
            $('#wpdmpp-csstatus').removeClass('wpdmpp-renew-badge--active').addClass('wpdmpp-renew-badge--inactive').html('<?php echo \WPDMPP\UI\Icons::get('toggle-off', 12); ?> <?php echo esc_js(__('Inactive', 'wpdm-premium-packages')); ?>');
        });
    });

    // Subscription activate info
    $('#btn-actsub').on('click', function(){
        WPDM.dialog.success(
            '<?php echo esc_js(__('Reactivate Subscription', 'wpdm-premium-packages')); ?>',
            '<?php echo esc_js(__('Please send an order note to reactivate your subscription.', 'wpdm-premium-packages')); ?>'
        );
    });
    <?php endif; ?>

    <?php if((int)get_wpdmpp_option('disable_order_notes', 0) === 0): ?>
    var uploadConfig = <?php echo json_encode($upload_config); ?>;
    var restConfig = <?php echo json_encode($rest_config); ?>;
    var closeIcon = <?php echo json_encode(\WPDMPP\UI\Icons::get('close', 14)); ?>;

    // Show selected file name after the button
    $('#wpdmpp-note-file-input').on('change', function(){
        var file = this.files[0];
        $('#wpdmpp-note-filelist').html('');
        if(!file) return;
        if(file.size > uploadConfig.max_size){
            WPDM.dialog.error('<?php echo esc_js(__('Upload Error', 'wpdm-premium-packages')); ?>', '<?php echo esc_js(__('File exceeds maximum upload size.', 'wpdm-premium-packages')); ?>');
            this.value = '';
            return;
        }
        var ID = Date.now();
        $('#wpdmpp-note-filelist').html("<span id='file_" + ID + "' class='atcf'><a href='#' rel='#file_" + ID + "' class='del-file' style='color:var(--o-danger,#ef4444)'>" + closeIcon + "</a> " + file.name + "</span>");
    });

    // Remove selected file
    $(document).on('click', '#wpdmpp-note-filelist .del-file', function(e){
        e.preventDefault();
        $('#wpdmpp-note-file-input').val('');
        $(this).closest('.atcf').remove();
    });

    // Upload file then submit note
    function uploadFileThenSubmit($form) {
        var fileInput = document.getElementById('wpdmpp-note-file-input');
        var file = fileInput && fileInput.files[0];

        if(!file) {
            submitNote($form);
            return;
        }

        var formData = new FormData();
        formData.append('attach_file', file);
        for(var key in uploadConfig.params){
            formData.append(key, uploadConfig.params[key]);
        }

        $('#wpdmpp-note-browse-btn').hide();
        $('#wpdmpp-note-progressbar').show();
        $('#wpdmpp-note-progress').css('width', '0%');
        $('#wpdmpp-note-loaded').html('0');

        var xhr = new XMLHttpRequest();
        xhr.upload.addEventListener('progress', function(e){
            if(e.lengthComputable){
                var pct = Math.round(e.loaded / e.total * 100);
                $('#wpdmpp-note-progress').css('width', pct + '%');
                $('#wpdmpp-note-loaded').html(pct);
            }
        });
        xhr.addEventListener('load', function(){
            $('#wpdmpp-note-progressbar').hide();
            $('#wpdmpp-note-browse-btn').show();
            if(xhr.status === 200){
                var parts = xhr.responseText.split('|||');
                var filename = parts[1] || file.name;
                $('#wpdmpp-note-filelist').html("<input type='hidden' name='file[]' value='" + filename + "' />");
                submitNote($form);
            } else {
                resetNoteButton();
                WPDM.dialog.error('<?php echo esc_js(__('Upload Error', 'wpdm-premium-packages')); ?>', '<?php echo esc_js(__('Something went wrong. Please try again.', 'wpdm-premium-packages')); ?>');
            }
        });
        xhr.addEventListener('error', function(){
            $('#wpdmpp-note-progressbar').hide();
            $('#wpdmpp-note-browse-btn').show();
            resetNoteButton();
            WPDM.dialog.error('<?php echo esc_js(__('Upload Error', 'wpdm-premium-packages')); ?>', '<?php echo esc_js(__('Something went wrong. Please try again.', 'wpdm-premium-packages')); ?>');
        });

        xhr.open('POST', uploadConfig.url, true);
        xhr.send(formData);
    }

    function submitNote($form) {
        var body = { note: $('#order-note').val() };
        var files = [];
        $form.find('input[name="file[]"]').each(function(){ files.push($(this).val()); });
        if(files.length) body.file = files;
        if($form.find('input[name="admin"]').is(':checked')) body.admin = true;
        if($form.find('input[name="seller"]').is(':checked')) body.seller = true;
        if($form.find('input[name="customer"]').is(':checked')) body.customer = true;

        fetch(restConfig.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': restConfig.nonce
            },
            body: JSON.stringify(body)
        })
        .then(function(r){ return r.json(); })
        .then(function(res){
            resetNoteButton();
            $('#wpdmpp-note-filelist').html('');
            $('#wpdmpp-note-file-input').val('');
            if(res.success && res.data && res.data.html){
                $('#all-notes').append(res.data.html);
                $('#order-note').val('');
            } else {
                WPDM.dialog.error('<?php echo esc_js(__('Error', 'wpdm-premium-packages')); ?>', res.message || '<?php echo esc_js(__('Something went wrong. Please try again.', 'wpdm-premium-packages')); ?>');
            }
        })
        .catch(function(){
            resetNoteButton();
            WPDM.dialog.error('<?php echo esc_js(__('Error', 'wpdm-premium-packages')); ?>', '<?php echo esc_js(__('Something went wrong. Please try again.', 'wpdm-premium-packages')); ?>');
        });
    }

    function resetNoteButton() {
        $('#add-note-button').html('<?php echo \WPDMPP\UI\Icons::get('plus-circle', 14); ?> <?php echo esc_js(__('Add Note', 'wpdm-premium-packages')); ?>');
    }

    // Form submit handler
    $('#post-order-note').submit(function(){
        $('#add-note-button').html('<?php echo \WPDMPP\UI\Icons::spinner(14); ?> <?php echo esc_js(__('Adding...', 'wpdm-premium-packages')); ?>');
        uploadFileThenSubmit($(this));
        return false;
    });
    <?php endif; ?>

});
</script>
