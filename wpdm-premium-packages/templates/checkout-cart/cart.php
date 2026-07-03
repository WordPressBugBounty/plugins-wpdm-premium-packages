<?php
/**
 * Cart Template
 *
 * This template can be overridden by copying it to yourtheme/download-manager/checkout-cart/cart.php.
 *
 * @version     3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings       = get_option('_wpdmpp_settings');
$guest_checkout = ( isset($settings['guest_checkout']) && $settings['guest_checkout'] == 1 ) ? 1 : 0;
$login_required = ! is_user_logged_in() && $guest_checkout == 0;

if ( is_array( $cart_data ) && count( $cart_data ) > 0 ) { ?>

    <div class="w3eden">

        <?php do_action( 'wpdmpp_before_cart' ); ?>

        <?php do_action( 'wpdmpp_after_cart' ); ?>

        <div id="wpdm-checkout">
            <?php
            // Always render the full checkout (incl. order summary / cart). When login is
            // required, checkout.php swaps its payment section for the login/register form.
            include wpdm_tpl_path('checkout/checkout.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
            ?>
        </div>

        <?php do_action( 'wpdmpp_after_checkout_form' ); ?>

    </div>

    <?php

} else {
    include wpdm_tpl_path('checkout-cart/cart-empty.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
}
