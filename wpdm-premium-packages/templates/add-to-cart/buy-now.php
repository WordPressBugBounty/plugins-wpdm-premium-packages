<?php
/**
 * User: shahnuralam
 * Create Date: 24/12/18 3:47 PM
 * Last Updated: 15/06/19
 * Version: 1.2
 */
if (!defined('ABSPATH')) die();
// Buy Now skips the checkout form, so every billing value has to come from
// somewhere else. A signed-in buyer supplies them from their saved profile.
// A guest can be asked for a name and email inline, but not for the full
// address that tax calculation needs — so in that one combination the button
// is left out and the Add to Cart route above it handles the purchase.
$wpdmpp_buynow_needs_address = (int) get_wpdmpp_option('tax/enable', 0, 'int') === 1;
$wpdmpp_buynow_supported     = is_user_logged_in() || ! $wpdmpp_buynow_needs_address;

if((int)get_wpdmpp_option('show_buynow') === 1 && $wpdmpp_buynow_supported){
    if(!isset($params)) $params = array();
	$_buynow_html = '';
	$pp = new \WPDMPP\Payment\Gateways\PayPalGateway();
	// Pass the product and licence so the order is built from what this page
	// is selling, not from whatever happens to be in the shopper's cart.
	$buynow['PayPal'] = $pp->isEnabled()
		? $pp->renderCheckoutButton('', $price, (int) $product_id, (string) $license)
		: '';
	$buynow = apply_filters("wpdmpp_buynow_options", $buynow, $product_id, $license);
	foreach ($buynow as $pm => $buynow_html){
		if($buynow_html) {
			$_buynow_html .= "<div class='buynow-btn' id='buynow-{$pm}'>";
			$_buynow_html .= $buynow_html;
			$_buynow_html .= "</div>";
		}
	}
    if($_buynow_html) {
    ?>
    <div class="w3eden">

        <div class="wpdmpp-buy-now buy-now-<?php echo $product_id; ?>">

            <?php if(isset($params, $params['title'])){ ?>
            <div class="card card-default">
                <div class="card-header"><?php echo str_replace("{price}", wpdmpp_price_format($price, true, true), $params['title']); ?></div>
                <div class="card-body">
                    <?php } ?>

                    <div class="wpdmpp-buy-now wpdmpp-buy-now-<?php echo $product_id; ?>" id="wpdmpp-buy-now-<?php echo $product_id; ?>">
                        <?php if(isset($params, $params['showprice']) && (int)$params['showprice'] === 1){ ?>
                            <div class="wpdmpp-buynow-price" id="wpdmpp-buynow-price-<?php echo $product_id; ?>">
                                <h2 class="text-center"><?php echo wpdmpp_price_format($price, true, true); ?></h2>
                            </div>
                        <?php } ?>

                        <?php
                        // A signed-in buyer's name and email come from their account.
                        // A guest has to supply them, or the order cannot be raised —
                        // the gateway button stays blocked until these validate. The
                        // ids are the ones PayPalGateway's validate_payment_form()
                        // watches; the names match the checkout REST arguments.
                        if ( ! is_user_logged_in() ) { ?>
                            <form id="payment_form" class="wpdmpp-buynow-fields" onsubmit="return false;">
                                <div class="form-group">
                                    <input type="text" id="f-name" name="first_name" class="form-control" required="required"
                                           placeholder="<?php esc_attr_e( 'First Name', 'wpdm-premium-packages' ); ?>">
                                </div>
                                <div class="form-group">
                                    <input type="text" id="l-name" name="last_name" class="form-control" required="required"
                                           placeholder="<?php esc_attr_e( 'Last Name', 'wpdm-premium-packages' ); ?>">
                                </div>
                                <div class="form-group">
                                    <input type="email" id="email_m" name="email" class="form-control" required="required"
                                           placeholder="<?php esc_attr_e( 'Email Address', 'wpdm-premium-packages' ); ?>">
                                </div>
                            </form>
                        <?php }

                            echo $_buynow_html;
                        ?>

                    </div>
                    <?php if(isset($params, $params['title'])){ ?>

                </div>
            </div>
        <?php } ?>

        </div>
    </div>

    <style>


        .wpdmpp-buy-now{
            margin: 10px 0;
            max-width: 330px;
        }

        .wpdmpp-buynow-price h2{
            margin: 0 0 20px;
            font-weight: 700;
            font-family: var(--fetfont);
            font-sirze: 18pt;
        }

        .wpdmpp-buynow-fields{
            margin-bottom: 12px;
        }

        .wpdmpp-buynow-fields .form-group{
            margin-bottom: 8px;
        }

        #wpdmpp-paypal-button-container *,
        #wpdmpp-paypal-button-container {
            max-width: 100% !important;
            width: 100%;
        }
        .zoid-outlet{
            min-width: 100% !important;
        }

    </style>
<?php }
}

