<?php
/**
 * Template for [wpdm-pp-guest-orders] shortcode
 *
 * This template can be overridden by copying it to yourtheme/download-manager/wpdm-pp-guest-orders.php.
 *
 * @version     2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$wpdmpp_has_guest_order = (bool) \WPDM\__\Session::get('guest_order');
?>
<div class="w3eden">
    <div class="wpdmpp-orders wpdmpp-go<?php echo $wpdmpp_has_guest_order ? ' wpdmpp-go--resolved' : ''; ?>">

        <?php do_action( 'wpdmpp_guest_orders_before' ); ?>

        <?php include wpdm_tpl_path('partials/guest-order-search-form.php', WPDMPP_TPL_DIR); ?>

        <?php include wpdm_tpl_path('partials/guest-order-details.php', WPDMPP_TPL_DIR); ?>

        <?php include wpdm_tpl_path('partials/guest-order-billing-info.php', WPDMPP_TPL_DIR); ?>

        <?php do_action( 'wpdmpp_guest_orders_after' ); ?>

    </div>
</div>
