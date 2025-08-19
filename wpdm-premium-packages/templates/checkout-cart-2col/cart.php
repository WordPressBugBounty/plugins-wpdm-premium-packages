<?php
/**
 * Cart Template
 *
 * This template can be overridden by copying it to yourtheme/download-manager/checkout-cart-2col/cart.php.
 *
 * @version     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$settings               = get_option('_wpdmpp_settings');
$currency_sign          = wpdmpp_currency_sign();
$currency_sign_before   = wpdmpp_currency_sign_position() == 'before' ? $currency_sign : '';
$currency_sign_after    = wpdmpp_currency_sign_position() == 'after' ? $currency_sign : '';
$guest_checkout         = ( isset($settings['guest_checkout']) && $settings['guest_checkout'] == 1 ) ? 1 : 0;
$login_required         = ! is_user_logged_in() && $guest_checkout == 0 ? true : false;
$wpdm_template          = new \WPDM\__\Template();

if ( is_array( $cart_data ) && count( $cart_data ) > 0 ) { ?>

    <div class="w3eden">

        <div class="row">
            <div class="col-md-6">
                <div id="left-col-cart">

	                <?php do_action( 'wpdmpp_before_cart' ); ?>

                    <!-- Cart Form -->
                    <div id="wpdmpp-cart-form">
		                <?php include wpdm_tpl_path('checkout-cart-2col/cart-items.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK); ?>
                    </div>
                    <!-- Cart Form end-->

	                <?php do_action( 'wpdmpp_after_cart' ); ?>

                    <div class="text-right">
                        <button type="button" class="btn btn-info" id="edit-cart"><i class="fa fa-edit"></i> Edit Cart</button>
                    </div>

                </div>
            </div>
            <div class="col-md-6">
                <div id="right-col-checkout">
                    <!-- Cart Checkout-->
                    <div id="wpdm-checkout">
		                <?php
		                if($login_required){
			                include wpdm_tpl_path('checkout-cart-2col/checkout-login-register.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
		                } else {
			                include wpdm_tpl_path('checkout-cart-2col/checkout.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
		                }
		                ?>
                    </div>
                    <!-- Cart Checkout end-->

	                <?php do_action( 'wpdmpp_after_checkout_form' ); ?>
                </div>
            </div>
        </div>

        <style>
            #cart-panel-bottom{
                position: fixed;
                width:100%;
                left: 0;
                z-index: 9999999;
                border-radius: 0 !important;
                background: #ffffff;
                border-top: 1px solid #dadbdd;
                top: 105%;
                transition: all 0.3s ease-in-out;
                margin-bottom: -48px;
            }
            #cart-panel-bottom.open{
                bottom: 0;
                top: auto;
                max-height: 90%;
                overflow-y: auto;
                margin-bottom: 0;
            }
            #cart-panel-bottom table{
                margin: 0 !important;
            }
            #cart-panel-bottom #close-panel{
                position: fixed;
                z-index: 99999999;
                right: 4px;
                border-radius: 500px;
                height: 36px;
                width: 36px;
                margin-top: -40px;
                padding: 0;
                line-height: 32px;
                font-size: 12px
                text-align: center;
            }
            body.cart-panel-open{
                overflow: hidden !important;
            }
            body #cart-overlay{
                display: none;
                position: fixed;
                left:0;
                top:0;
                width: 100%;
                height: 100%;
                z-index: 999;
                background: rgba(0,0,0,0.8);
                opacity: 0;
                transition: all ease-in-out 300ms;
            }
            body.cart-panel-open #cart-overlay{
                display: block !important;
                opacity: 1;
                cursor: pointer;
            }
        </style>
        <div id="cart-overlay"></div>
        <div id="cart-panel-bottom">
                <button class="btn btn-secondary" id="close-panel"><i class="fa fa-times"></i></button>
			    <?php include wpdm_tpl_path('checkout-cart-2col/cart-form.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK); ?>
            <!-- Saved cart -->
	        <?php include wpdm_tpl_path('checkout-cart-2col/cart-save.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK); ?>
            <!-- Saved cart end -->

        </div>

    </div>
    <link rel="stylesheet" href="<?php echo WPDM_ASSET_URL; ?>drawer/css/slide-out-panel.min.css" />
    <script src="<?php echo WPDM_ASSET_URL ?>drawer/js/slide-out-panel.js"></script>
    <script>
        jQuery(function ($) {
            $('#edit-cart').on('click', function (e) {
                e.preventDefault();
                $('#cart-panel-bottom').addClass('open');
                $('body').addClass('cart-panel-open');
            });
            $('#close-panel, #cart-overlay').on('click', function (e) {
                e.preventDefault();
                $('#cart-panel-bottom').removeClass('open');
                $('body').removeClass('cart-panel-open');
            });

        })
    </script>



    <?php

} else {
    // Cart is empty
    include wpdm_tpl_path('checkout-cart-2col/cart-empty.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
}
