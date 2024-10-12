<?php
/**
 * Template for displaying active Payment Methods in cart checkout page.
 *
 * This template can be overridden by copying it to yourtheme/download-manager/checkout-cart-2col/checkout-payment-methods.php.
 *
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $payment_methods;
?>

<div class="card" id="csp">
    <div class="card-header"><?php echo __( "Select Payment Method:", "wpdm-premium-packages" ); ?></div>
    <div class="card-body">
        <div class="pmblocks">
			<?php
			$settings        = maybe_unserialize( get_option( '_wpdmpp_settings' ) );
			$payment_methods = apply_filters( 'payment_method', $payment_methods );
			$payment_methods = isset( $settings['pmorders'] ) && count( $settings['pmorders'] ) == count( $payment_methods ) ? $settings['pmorders'] : $payment_methods;
			$index           = 0;
			foreach ( $payment_methods as $payment_method ) {

				$payment_gateway_class = 'WPDMPP\Libs\PaymentMethods\\' . $payment_method;

				if ( class_exists( $payment_gateway_class ) ) {
					$enables = get_wpdmpp_option( "{$payment_method}/enabled", 0, 'int' );
					if ( $enables === 1 || ( current_user_can( 'manage_options' ) && $enables === 2 ) ) {
						$index ++;
						$obj               = new $payment_gateway_class();
						$obj->GatewayName  = isset( $obj->GatewayName ) ? $obj->GatewayName : $payment_method;
						$logo              = isset( $obj->logo ) ? $obj->logo : $obj->GatewayName;
						$name              = get_wpdmpp_option( $payment_method . '/title', $obj->GatewayName );
						$name              = $name == '' ? $payment_method : $name;
						$name              = strstr( $name, "://" ) ? "<img src='$name' alt='{$obj->GatewayName}' />" : $name;
						$name              = str_replace( "[logo]", $logo, $name );
						$payment_method_lc = strtolower( $payment_method );
						$pg_item_class     = "payment-gateway-item payment-gateway-{$payment_method_lc} index-$index";
						$pg_item_class     = apply_filters( "wpdmpp_payment_gateway_item_class", $pg_item_class );
						$row_id            = "__PM_{$payment_method}";
						// If you are editing this file, keep the radio input field name same as no, "payment_method"
						$logo = "<img src='" . WPDMPP()->payment->getLogo($payment_method) . "' alt='{$payment_method}' />";
						echo '<div class="column"><label class="page-method-block ' . $pg_item_class . '" id="' . $row_id . '"><input class="wpdm-radio mr-3" type="radio" name="payment_method" ' . checked( $payment_methods[0], $payment_method, false ) . ' value="' . $payment_method . '" > <span class="grateway-name">' . $name . '</span><div class="pmlogo">' . $logo . '</div></label></div>';
					}
				}
			}
			?>
        </div>
    </div>
</div>
<style>
    .pmblocks {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .pmblocks > div.column {
        display: flex;
    }

    .page-method-block {
        border: 1px solid #dddddd;
        border-radius: 4px;
        padding: 6px 12px;
        width: 100%;
        margin: 0 !important;
        display: flex !important;
    }

    .grateway-name {
        width: 100%;
        line-height: normal;
        align-content: center;
        font-size: 14px;
        padding-right: 10px;
    }
    .pmlogo {
        align-content: center;
    }

    .page-method-block img {
        width: 32px;
    }

    .page-method-block .wpdm-radio {
        display: none !important;
    }
    .page-method-block.active{
        border: 1px solid var(--color-primary);
        box-shadow: 0 0 5px inset rgba(var(--color-primary-rgb), 0.3);
    }
</style>
<script>
jQuery(function ($) {
    $('.page-method-block').on('click', function () {
        $('.page-method-block').removeClass('active');
        $(this).addClass('active');
    });
});
</script>
