<?php
/**
 * Base: wpdmpro
 * Developer: shahjada
 * Team: W3 Eden
 * Date: 20/7/20 07:23
 */

use WPDM\__\UI;

if(!defined("ABSPATH")) die();
?>
<form method="post" class="wpdm_checkout_cart_form" id="wpdm_checkout_cart_form" action="" name="checkout_cart_form" onsubmit="WPDM.blockUI('#wpdm_checkout_cart_form')">
    <input name="wpdmpp_update_cart" value="1" type="hidden">

    <table class="wpdm_cart table mb-3">

        <tbody>
        <!-- Cart Items  -->
        <?php do_action( 'wpdmpp_cart_items_before' ); ?>
        <?php
        foreach ( $cart_data as $ID => $item ) {
            $dynamic = wpdm_valueof($item, 'type') === 'dynamic';
            if ( isset( $item['coupon_amount'] ) && $item['coupon_amount'] > 0 ) {
                $discount_amount    = $item['coupon_amount'];
                $discount_style     = "style='color:#008000; text-decoration:underline;'";
                $discount_title     = 'Discounted $' . $discount_amount . " for coupon code '{$item['coupon']}'";

            } else {
                $discount_amount    = "";
                $discount_style     = "";
                $discount_title     = "";
            }

            if (isset($item['error']) && $item['error'] != '') {
                $coupon_style   = "border:1px solid #ff0000;";
                $title          = $item['error'];
            } else {
                $coupon_style   = "";
                $title          = "";
            }
            $item['pid'] = $ID;
            $item['coupon']         = isset($item['coupon']) ? $item['coupon'] : '';
            $item['coupon_amount']  = isset($item['coupon_amount']) ? $item['coupon_amount'] : 0;
            $item_total             = number_format((($item['price'] + $item['prices']) * $item['quantity']) - $item['coupon_amount'] - $item['discount_amount'], 2, ".", "");
            ?>
            <tr id='cart_item_<?php echo $ID; ?>'>

                <?php do_action( 'wpdmpp_cart_item_col_first' , $item ); ?>
                <td class='cart_item_title'>
                    <div class="media">
                        <div class='mr-3'><?php WPDMPP()->cart->itemThumb($item, true, ['crop' => true]); ?></div>
                        <div class="media-body">
                            <strong><?php echo $item['product_name']; ?></strong>
                            <?php WPDMPP()->cart->itemInfo($item); ?>
                        </div>
                    </div>
                    <div class='clear'></div>
                </td>

                <td class='cart_item_subtotal amt nowrap'>
	                <?php echo $item['quantity']; ?> &times; <span class='ttip' title='<?php echo $discount_title; ?>'><?php echo wpdmpp_price_format($item['price']); ?></span> =
                    <?php echo WPDMPP()->cart->itemCost($item, true); ?>
                </td>

                <?php do_action( 'wpdmpp_cart_item_col_last', $item ); ?>

            </tr>

        <?php } ?>
        <!-- Cart Items end -->

        <?php do_action( 'wpdmpp_cart_items_after' ); ?>

        <?php do_action('wpdmpp_cart_extra_row', $cart_data); ?>

        <!-- Cart Sub Total  -->
        <tr id="cart-total">
            <td class="text-right  " align="right"></td>
            <td class="amt nowrap">
	            <?php echo __("Subtotal", "wpdm-premium-packages"); ?>:
                <strong id="wpdmpp_cart_subtotal"><?php echo WPDMPP()->cart->cartTotal(false, false, true); ?></strong>
            </td>
        </tr>
        <!-- Cart Sub Total end  -->

        <!-- Cart Coupon Discount  -->
        <tr id="cart-total">
            <td  class="text-right ">
                <div class="input-group input-group-sm" style="max-width: 160px;display: flex;">
                    <input type="text" name="coupon_code" class="form-control" value="<?php echo is_array($cart_coupon) && isset($cart_coupon['code'])?$cart_coupon['code']:''; ?>" placeholder="Coupon Code">
                    <span class="input-group-append"><button class="btn btn-secondary" type="submit"><?= esc_attr__( 'Apply', WPDMPP_TEXT_DOMAIN ) ?></button></span>
                </div>
            </td>

            <td class="amt nowrap">
	            <?php  if(wpdm_valueof($cart_coupon, 'note')) { ?><i title="<?= wpdm_valueof($cart_coupon, 'note') ?>" class="fa fa-circle-info color-info ttip"></i><?php } ?>
	            <?php echo __("Coupon Discount", "wpdm-premium-packages"); ?>:
                <span id="wpdmpp_cart_discount">- <?php echo wpdmpp_price_format($cart_coupon_discount); ?></span>
            </td>
        </tr>
        <!-- Cart Coupon Discount end -->

        <?php if (wpdmpp_tax_active()) { ?>
            <!-- Cart Tax  -->
            <tr id="cart-tax">
                <td colspan="<?php echo $colspan ?>" class="text-right  " align="right"><?php echo __("Tax", "wpdm-premium-packages"); ?>:</td>
                <td class="amt" id="wpdmpp_cart_tax">
                    <span class=" d-md-none"><?php echo __("Tax", "wpdm-premium-packages"); ?>: </span>
                    <?php echo wpdmpp_price_format($cart_tax); ?>
                </td>
            </tr>
            <!-- Cart Tax end -->
        <?php } ?>

        <!-- Cart Total Including Tax -->
        <tr id="cart-total-with-tax">
            <td colspan="<?php echo $colspan ?>" class="text-right bg-transparent" align="right"></td>
            <td class="amt bg-transparent">
	            <?php echo __("Total", "wpdm-premium-packages"); ?>:
                <strong id="wpdmpp_cart_grand_total"><?php echo wpdmpp_price_format($cart_total_with_tax); ?></strong>
            </td>
        </tr>
        <!-- Cart Total Including Tax end -->



        </tbody>
    </table>


</form>

<style>
    .w3eden .input-group-sm .form-control{
        border-radius: 4px 0 0 4px !important;
    }
    .w3eden .input-group-sm .btn{
        min-height: 24px;
        height: 28px;
        border-radius: 0 4px 4px 0 !important;
    }
</style>
