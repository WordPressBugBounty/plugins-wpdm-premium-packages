<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$order = $orderObj->getOrder( $order_id );

$billing_info = maybe_unserialize($order->billing_info);


if($order) {
$order->items = unserialize( $order->items );
$oitems       = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_order_items where oid='{$order->order_id}'" );

$currency      = maybe_unserialize( $order->currency );
$currency_sign = is_array( $currency ) && isset( $currency['sign'] ) ? $currency['sign'] : '$';

if ( $order->uid > 0 ) {
	$user = new WP_User( $order->uid );
	//$role = is_object($user) ? [0] : '';
} else {
	$user = null;
}
$settings     = maybe_unserialize( get_option( '_wpdmpp_settings' ) );
$total_coupon = wpdmpp_get_all_coupon( unserialize( $order->cart_data ) );

$sbilling = array
(
	'first_name'  => '',
	'last_name'   => '',
	'company'     => '',
	'address_1'   => '',
	'address_2'   => '',
	'city'        => '',
	'postcode'    => '',
	'country'     => '',
	'state'       => '',
	'email'       => '',
	'order_email' => '',
	'phone'       => ''
);
$billing  = unserialize( $order->billing_info );
$billing  = shortcode_atts( $sbilling, $billing );


$renews = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_order_renews where order_id='{$order->order_id}'" );

?>
<?php ob_start(); ?>

<table width="100%" cellspacing="0" class="table">
    <thead>
    <tr>
        <th align="left"><?php _e( "Item Name", "wpdm-premium-packages" ); ?></th>
        <th align="left"><?php _e( "Unit Price", "wpdm-premium-packages" ); ?></th>
        <th align="left"><?php _e( "Quantity", "wpdm-premium-packages" ); ?></th>
        <th align="left"><?php _e( "Role Discount", "wpdm-premium-packages" ); ?></th>
        <th align="left"><?php _e( "Coupon Code", "wpdm-premium-packages" ); ?></th>
        <th align="left"><?php _e( "Coupon Discount", "wpdm-premium-packages" ); ?></th>
        <th align="right" class="text-right" style="width: 100px"><?php _e( "Total", "wpdm-premium-packages" ); ?></th>
    </tr>
    </thead>
	<?php
	//$cart_data = unserialize($order->cart_data);
	$cart_data = \WPDMPP\Libs\Order::GetOrderItems( $order->order_id );

	$currency      = maybe_unserialize( $order->currency );
	$currency_sign = is_array( $currency ) && isset( $currency['sign'] ) ? $currency['sign'] : '$';

	$payment_method              = str_replace( "WPDM_", "", $order->payment_method );

    $coupon_discount = 0;
    $role_discount           = 0;
    $shipping                = 0;
    $order_total             = 0;

	if ( is_array( $cart_data ) && ! empty( $cart_data ) ):
		foreach ( $cart_data as $pid => $item ):

			$currency_sign_before = wpdmpp_currency_sign_position() == 'before' ? $currency_sign : '';
			$currency_sign_after = wpdmpp_currency_sign_position() == 'after' ? $currency_sign : '';

			$license = isset( $item['license'] ) ? maybe_unserialize( $item['license'] ) : null;

			if ( $license ) {
				$license = isset( $license['info'], $license['info']['name'] ) ? '<span class="ttip color-purple" title="' . esc_html( $license['info']['description'] ) . '">' . sprintf( __( "%s License", "wpdm-premium-packages" ), $license['info']['name'] ) . '</span>' : '';
			}


			if ( ! isset( $item['coupon_amount'] ) || $item['coupon_amount'] == "" ) {
				$item['coupon_amount'] = 0.00;
			}

			if ( ! isset( $item['discount_amount'] ) || $item['discount_amount'] == "" ) {
				$item['discount_amount'] = 0.00;
			}

			if ( ! isset( $item['prices'] ) || $item['prices'] == "" ) {
				$item['prices'] = 0.00;
			}

			$title              = get_the_title( $item['pid'] );
			$title              = $title ? $title : '&mdash; The item is not available anymore &mdash;';
			$coupon_discount    += $item['coupon_discount'];
			$role_discount      += $item['role_discount'];
			$item_cost          = WPDMPP()->order->itemCost( $item );
			$order_total        += $item_cost; //(($item['price'] + $item['prices']) * (int)$item['quantity']) - $item['coupon_discount'] - $item['role_discount'];
			$item['extra_gigs'] = maybe_unserialize( $item['extra_gigs'] );


			$item['price'] = (double) $item['price'];
			//echo "<pre>";print_r($item['quantity']);

			?>
            <tr>
                <td>
                    <div style="display: flex;">
                        <div  style="margin-right: 8px"><a href="#" class="text-muted ttip pprmo_item" data-pid="<?=$item['pid'] ?>" title="<?php _e('Remove item', WPDMPP_TEXT_DOMAIN); ?>"><i class="fas fa-trash"></i></a> </div>
                        <div>
                            <strong><?php WPDMPP()->cart->itemLink( $item ); ?></strong>
                            <div>
		                        <?php if ( (int) get_post_meta( $item['pid'], '__wpdm_enable_license_key', true ) === 1 ) { ?>
                                    <div style="margin-right: 5px;float: left">[ <a class="color-success"
                                                                                    id="<?php echo "lic_{$item['pid']}_{$order->order_id}_btn"; ?>"
                                                                                    onclick="return getkey('<?php echo $item['pid']; ?>','<?php echo $order->order_id; ?>', '#'+this.id);"
                                                                                    data-placement="top" data-toggle="popover"
                                                                                    href="#"><i
                                                    class="fa fa-key color-success"></i></a> ]
                                    </div>
		                        <?php } ?>
		                        <?php WPDMPP()->cart->itemInfo( $item ); ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td><?php echo wpdmpp_price_format( $item['price'], $currency_sign, true ); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td><?php echo wpdmpp_price_format( $item['role_discount'], $currency_sign, true ); ?></td>
                <td><?php echo isset( $item['coupon'] ) ? $item['coupon'] : ''; ?></td>
                <td><?php echo wpdmpp_price_format( $item['coupon_discount'], $currency_sign, true ); ?></td>
                <td class="text-right"><?php echo wpdmpp_price_format( $item_cost, $currency_sign, true ); ?></td>
            </tr>
		<?php

		endforeach;
	endif;
	?>
    <tr>
        <td colspan="6" class="text-right"><?php _e( 'Cart Total', 'wpdm-premium-packages' ); ?></td>
        <td class="text-right"><?php echo wpdmpp_price_format( $order_total, $currency_sign, true ); ?></td>
    </tr>
    <tr>
        <td colspan="6" class="text-right"><?php _e( 'Cart Coupon Discount', 'wpdm-premium-packages' );  ?> <div class="badge badge-success"><?= $order->coupon_code ?></div></td>
        <td class="text-right">-<?php echo wpdmpp_price_format( $order->coupon_discount, $currency_sign, true ); ?></td>
    </tr>
    <tr>
        <td colspan="6" class="text-right"><?php _e( 'Tax', 'wpdm-premium-packages' ); ?></td>
        <td class="text-right">+<?php echo wpdmpp_price_format( $order->tax, $currency_sign, true ); ?></td>
    </tr>
    <tr id="refundrow" <?php if ( (int) $order->refund == 0 ) {
		echo "style='display:none;'";
	} ?>>
        <td colspan="6" class="text-right"><?php _e( 'Refund', 'wpdm-premium-packages' ); ?></td>
        <td class="text-right" id="refundamount">-<?php echo wpdmpp_price_format( $order->refund, $currency_sign, true ); ?></td>
    </tr>
    <tr>
        <td colspan="6" class="text-right"><?php _e( 'Total', 'wpdm-premium-packages' ); ?></td>
        <td class="text-right"><strong id="totalamount"
                                       class="order_total"><?php echo wpdmpp_price_format( $order->total, $currency_sign, true ); ?></strong>
        </td>
    </tr>
</table>
<?php $content = ob_get_clean(); ?>



        <div class="view-order">

            <span id="lng" class="color-red" style="margin-left: 20px;display: none"><i
                        class="fas fa-sun fa-spin"></i> <?php _e( 'Please Wait...', 'wpdm-premium-packages' ); ?></span>

            <div class="well" id="orderbar" style="background-image: none">

                <div class="row">
                    <div class="col-lg-5">
                        <b><span id="oslabel"><?php _e( "Order Status:", "wpdm-premium-packages" ); ?></span>
                            <select id="osv" name="order_status"
                                    title="<?php _e( "Select Order Status", "wpdm-premium-packages" ); ?>"
                                    class="form-control wpdm-custom-select ttip" style="width: 150px;display: inline">
                                <option value="Pending"><?php _e( "Order Status:", "wpdm-premium-packages" ); ?></option>
                                <option <?php if ( $order->order_status == 'Pending' ) {
							        echo 'selected="selected"';
						        } ?> value="Pending">Pending
                                </option>
                                <option <?php if ( $order->order_status == 'Processing' ) {
							        echo 'selected="selected"';
						        } ?> value="Processing">Processing
                                </option>
                                <option <?php if ( $order->order_status == 'Completed' ) {
							        echo 'selected="selected"';
						        } ?> value="Completed">Completed
                                </option>
                                <option <?php if ( $order->order_status == 'Expired' ) {
							        echo 'selected="selected"';
						        } ?> value="Expired">Expired
                                </option>
                                <option <?php if ( $order->order_status == 'Cancelled' ) {
							        echo 'selected="selected"';
						        } ?> value="Cancelled">Cancelled
                                </option>
                                <option value="Renew" class="text-success text-renew">Renew Order</option>
                            </select>
                        </b> <input type="button" id="update_os" class="btn btn-default" value="Update">
                    </div>
                    <div class="col-lg-5">
                        <b><span id="pslabel"><?php _e( "Payment Status:", "wpdm-premium-packages" ); ?></span>
                            <select id="psv" title="<?php _e( "Select Payment Status", "wpdm-premium-packages" ); ?>"
                                    class="wpdm-custom-select form-control ttip" name="payment_status"
                                    style="width: 150px;display: inline">
                                <option value="Pending"><?php _e( "Payment Status:", "wpdm-premium-packages" ); ?></option>
                                <option <?php selected($order->payment_status, 'Pending') ?> value="Pending">Pending</option>
                                <option  <?php selected($order->payment_status, 'Processing') ?> value="Processing">Processing</option>
                                <option <?php if ( $order->payment_status == 'Completed' ) {
							        echo 'selected="selected"';
						        } ?> value="Completed">Completed
                                </option>
                                <option <?php if ( $order->payment_status == 'Bonus' ) {
							        echo 'selected="selected"';
						        } ?> value="Bonus">Bonus
                                </option>
                                <option <?php if ( $order->payment_status == 'Gifted' ) {
							        echo 'selected="selected"';
						        } ?> value="Gifted">Gifted
                                </option>
                                <option <?php if ( $order->payment_status == 'Cancelled' ) {
							        echo 'selected="selected"';
						        } ?> value="Cancelled">Cancelled
                                </option>
                                <option <?php if ( $order->payment_status == 'Disputed' ) {
							        echo 'selected="selected"';
						        } ?> value="Disputed">Disputed
                                </option>
                                <option <?php if ( $order->payment_status == 'Refunded' ) {
							        echo 'selected="selected"';
						        } ?> value="Refunded">Refunded
                                </option>
                            </select>
                        </b>
                        <input id="update_ps" type="button" class="btn btn-default" value="Update">
                    </div>
                    <div class="col-lg-2">
                        <div class="wpdm-order-actions pull-right">
                            <button class="wpdm-order-actions__trigger" type="button" id="orderActionsBtn" aria-haspopup="true" aria-expanded="false">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="1"></circle>
                                    <circle cx="12" cy="5" r="1"></circle>
                                    <circle cx="12" cy="19" r="1"></circle>
                                </svg>
                                <span><?php _e('Actions', 'wpdm-premium-packages'); ?></span>
                                <svg class="wpdm-order-actions__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <div class="wpdm-order-actions__menu" id="orderActionsMenu">
                                <a href="#" class="wpdm-order-actions__item" id="dlh">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 3v18h18"></path>
                                        <path d="m19 9-5 5-4-4-3 3"></path>
                                    </svg>
                                    <?php _e('Download History', 'wpdm-premium-packages'); ?>
                                </a>
                                <a href="#" class="wpdm-order-actions__item" onclick="window.open('?id=<?php echo wpdm_query_var('id'); ?>&wpdminvoice=1','Invoice','height=720, width=750, toolbar=0'); return false;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="16" y1="13" x2="8" y2="13"></line>
                                        <line x1="16" y1="17" x2="8" y2="17"></line>
                                        <polyline points="10 9 9 9 8 9"></polyline>
                                    </svg>
                                    <?php _e('View Invoice', 'wpdm-premium-packages'); ?>
                                </a>
                                <a href="#" class="wpdm-order-actions__item" id="oceml">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                        <polyline points="22,6 12,13 2,6"></polyline>
                                    </svg>
                                    <?php _e('Resend Confirmation Email', 'wpdm-premium-packages'); ?>
                                </a>
                                <?php do_action("wpdmpp_order_action_menu_item", $order); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="msg" style="border-radius: 3px;display: none;"
                 class="alert alert-success"><?php _e( "Message", "wpdm-premium-packages" ); ?></div>
	        <?php
	        do_action("wpdmpp_order_details_before_order_info", $order);
	        ?>
            <div class="row">
                <div class="col-lg-3 col-md-6 col-sm-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="pull-right" style="margin-top: -3px">
                                <button type="button" class="btn btn-info btn-xs" data-toggle="modal"
                                        data-target="#changetrannid">
									<?= esc_attr__( 'Edit', WPDMPP_TEXT_DOMAIN ) ?>
                                </button>
                            </div>
							<?php _e( "Order ID", "wpdm-premium-packages" ); ?>
                        </div>
                        <div class="panel-body">
                            <span class="lead" style="display: block;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;margin: 0;"><strong><?php echo apply_filters( "wpdmpp_admin_order_details_order_id", $order->order_id, $payment_method ); ?></strong> <?php if ( $order->trans_id ) {
									echo "<span title='" . sprintf( __( "%s transaction ID", "wpdm-premium-packages" ), $payment_method ) . "' style='font-size: 9pt' class='text-muted ttip' id='tnid'>( " . apply_filters( "wpdmpp_admin_order_details_trans_id", $order->trans_id, $payment_method ) . " )</span>";
								} ?></span>
                            <div class="modal fade" tabindex="-1" role="dialog" id="changetrannid">
                                <div class="modal-dialog" role="document" style="width: 350px">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title"><?php echo __( "Change Transection ID", "wpdm-premium-packages" ); ?></h4>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="text"
                                                   placeholder="<?php echo __( "New Transection ID", "wpdm-premium-packages" ); ?>"
                                                   value="<?= $order->trans_id ?>" class="form-control input-lg"
                                                   id="changetid"/>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" id="change_transection_id"
                                                    class="btn btn-primary"><?php echo __( "Change", "wpdm-premium-packages" ); ?></button>
                                        </div>
                                    </div><!-- /.modal-content -->
                                </div><!-- /.modal-dialog -->
                            </div><!-- /.modal -->
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12">
                    <div class="panel panel-default">
                        <div class="panel-heading"><?php _e( "Order Date", "wpdm-premium-packages" ); ?></div>
                        <div class="panel-body">
                            <span class="lead"><?php echo wp_date( "M d, Y h:i a", $order->date ); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="pull-right" style="font-size: 15pt;margin-top: -2px;">
                                <?php if(get_wpdmpp_option('disable_manual_renew', 0, 'int')) { ?>
                                <a href="#" class="<?= (int)\WPDMPP\Libs\Order::getMeta($order_id, 'manual_renew') ? 'color-green' : 'text-muted' ?> manual-renewal ttip" data-order="<?php echo $order->order_id; ?>" title="<?= __('Manual order renewal status', WPDMPP_TEXT_DOMAIN)?>">
                                    <i class="fa-solid fa-circle-dot"></i>
                                </a>
                                <?php } ?>
                                <a href="#" class="auto-renew-order ttip" data-order="<?php echo $order->order_id; ?>" title="<?= __('Activate/Deactivate auto-renewal', WPDMPP_TEXT_DOMAIN)?>">
                                    <span class="rns renew-<?php echo $order->auto_renew == 0 ? 'cancelled' : 'active'; ?>">
                                        <!--<i class="fa fa-circle-thin fa-stack-2x"></i>-->
                                        <i class="fa <?php echo $order->auto_renew == 1 ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
                                    </span>
                                </a>
                            </div>
							<?php $order->auto_renew == 1 ? _e( "Auto-Renew Date", "wpdm-premium-packages" ) : _e( "Expiry Date", "wpdm-premium-packages" ); ?>
                        </div>
                        <div class="panel-body">
                            <div class="pull-right">
                                <button type="button" class="btn btn-xs btn-secondary" data-toggle="modal" data-target="#changeexpire"><?= __('Edit', WPDMPP_TEXT_DOMAIN); ?></button>
                            </div>
                            <span class="lead" id="xdate"><?php echo wp_date( "M d, Y h:i a", $order->expire_date ); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12">
                    <div class="panel panel-default" style="overflow: visible !important;">
                        <div class="panel-heading" style="display: flex;align-items: center;justify-content: space-between;">
                            <div><?php _e( "Order Total", "wpdm-premium-packages" ); ?></div>
                            <div class="wpdm-pm-dropdown">
                                <button type="button" class="wpdm-pm-dropdown__trigger ttip" id="paymentMethodBtn" title="<?php _e( "Change Payment Method", "wpdm-premium-packages" ); ?>">
                                    <i class="fa fa-credit-card-alt"></i>
                                </button>
                                <div class="wpdm-pm-dropdown__menu" id="paymentMethodMenu">
                                    <div class="wpdm-pm-dropdown__header">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                            <line x1="1" y1="10" x2="23" y2="10"></line>
                                        </svg>
                                        <?php _e( "Change Payment Method", "wpdm-premium-packages" ); ?>
                                    </div>
                                    <div class="wpdm-pm-dropdown__body">
                                        <?php
                                        $payment_methods = WPDMPP()->active_payment_gateways();
                                        foreach ( $payment_methods as $payment_method ) {
                                            $payment_method_class = $payment_method;
                                            $payment_method_name  = str_replace( "WPDM_", "", $payment_method );
                                            ?>
                                            <a href="#" class="wpdm-pm-dropdown__item changepm" data-pm="<?php echo esc_attr($payment_method_class); ?>">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <path d="M12 6v6l4 2"></path>
                                                </svg>
                                                <?php echo esc_html($payment_method_name); ?>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <span class="lead color-green"><strong
                                        class="order_total"><?php echo wpdmpp_price_format( $order->total, $currency_sign, true ); ?></strong></span>
                            <span class="text-muted">via <span
                                        id="pmname"><?php echo str_ireplace( "wpdm_", "", $order->payment_method ); ?></span></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php
			do_action("wpdmpp_order_details_before_order_items", $order);
            ?>
            <div class="row">

                <div class="col-lg-3 col-md-6 col-sm-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
							<?php _e( "Order Summary", "wpdm-premium-packages" ); ?>
                        </div>
                        <table class="table">
                            <tr>
                                <td><?php _e( "Total Coupon Discount", "wpdm-premium-packages" );  ?>:</td>
                                <td><?php echo wpdmpp_price_format( $total_coupon + $order->cart_discount, true, true ); ?></td>
                            </tr>
                            <tr>
                                <td><?php _e( "Role Discount:", "wpdm-premium-packages" ); ?></td>
                                <td><?php echo wpdmpp_price_format( $role_discount, true, true ); ?></td>
                            </tr>


                        </table>
                    </div>
                </div>
                <div class="col-lg-5 col-md-6 col-sm-12">
                    <div class="panel panel-default">
                        <div class="panel-heading"><?php _e( "Customer Info", "wpdm-premium-packages" ); ?></div>
						<?php if ( $order->uid > 0 ) { ?>
                            <table class="table" id="cintable">
                                <tbody>
                                <tr>
                                    <td><?php _e( "Customer Name:", "wpdm-premium-packages" ); ?></td>
                                    <td>
                                        <a href='edit.php?post_type=wpdmpro&page=customers&view=profile&id=<?php echo $user->ID; ?>'><?php echo $user->display_name; ?></a>
                                        <a class="text-filter" title="<?php _e('All orders placed by this customer','wpdm-premium-packages'); ?>" href="edit.php?post_type=wpdmpro&page=orders&customer=<?php echo $user->ID; ?>&focus=<?php echo $order->order_id ?>"><i class="fas fa-search"></i></a><br/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php _e( "Customer Email:", "wpdm-premium-packages" ); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-xs btn-warning pull-right"
                                                data-toggle="modal"
                                                data-target="#changecustomer"><?php _e( 'Change', 'wpdm-premium-packages' ) ?></button>
                                        <a href='mailto:<?php echo $user->user_email; ?>'><?php echo $user->user_email; ?></a>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                            <div class="modal fade" tabindex="-1" role="dialog" id="changecustomer">
                                <div class="modal-dialog" role="document" style="width: 350px">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title"><?php echo __( "Change Customer", "wpdm-premium-packages" ); ?></h4>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="text"
                                                   placeholder="<?php echo __( "Username or Email", "wpdm-premium-packages" ); ?>"
                                                   class="form-control input-lg" id="changec"/>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" id="save_customer_change" class="btn btn-primary"><?php echo __( "Change", "wpdm-premium-packages" ); ?></button>
                                        </div>
                                    </div><!-- /.modal-content -->
                                </div><!-- /.modal-dialog -->
                            </div><!-- /.modal -->

						<?php } else { ?><b></b>
                            <table class="table">

                                <tbody>

                                <tr>
                                    <td><?php _e( "Customer Name:", "wpdm-premium-packages" ); ?></td>
                                    <td><?php echo $billing['first_name'] . ' ' . $billing['last_name']; ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e( "Customer Email:", "wpdm-premium-packages" ); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo $billing['order_email']; ?>"><?php echo $billing['order_email']; ?></a>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                            <table class="table">
                                <thead>
                                <tr>
                                    <th align="left"><?php echo __( "This order is not associated with any registered user", "wpdm-premium-packages" ); ?></th>
                                </tr>
                                </thead>
                                <tr>
                                    <td align="left" id="ausre">
                                        <div class="input-group"><input placeholder="Username or Email" type="text"
                                                                        class="form-control" id="ausr"><span
                                                    class="input-group-btn"><input type="button" id="ausra"
                                                                                   class="btn btn-primary"
                                                                                   value="<?php echo __( "Assign User", "wpdm-premium-packages" ); ?>"></span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
						<?php } ?>
                    </div>
                </div>

                <div class="col-lg-4 col-sm-12">
                    <div class="panel panel-default">
                        <div class="panel-heading"><?php _e( "IP Information", "wpdm-premium-packages" ); ?></div>
                        <table class="table">
                            <tr>
                                <td><?php _e( "IP Address:", "wpdm-premium-packages" ); ?></td>
                                <td><?php echo $order->IP; ?></td>
                            </tr>
                            <tr>
                                <td><?php _e( "Location:", "wpdm-premium-packages" ); ?></td>
                                <td>
                                    <div id="iploc">
                                        <script>
                                            jQuery(function ($) {
                                                $.getJSON("https://ipapi.co/<?php echo $order->IP; ?>/json/", function (data) {
                                                    var table_body = "";
                                                    console.log(data);
                                                    if (data.error !== true && data.reserved !== true) {
                                                        table_body += data.city + ", ";
                                                        table_body += data.region + ", ";
                                                        table_body += data.country;
                                                        $("#iploc").html(table_body);
                                                    } else {
                                                        $("#iploc").html('Private');
                                                    }
                                                });
                                            });
                                        </script>
                                    </div>
                                </td>
                            </tr>

                        </table>
                    </div>
                </div>
                <div style="clear: both"></div>
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="pull-right"><button type="button" class="btn btn-info btn-xs" data-toggle="modal" data-target="#addproduct"><i class="fa fa-plus mr-3"></i> <?php _e('Add Item', WPDMPP_TEXT_DOMAIN); ?></button></div>
							<?php _e( "Ordered Items", "wpdm-premium-packages" ); ?>
                        </div>
						<?php echo $content; ?>

                        <div class="panel-footer text-right bg-white">
                            <button class="btn btn-sm btn-secondary" data-toggle="modal"
                                    data-target="#refundmodal"><?php _e( "Refund", "wpdm-premium-packages" ); ?></button>
                        </div>


                    </div>


                </div>
            </div>


			<?php
			do_action("wpdmpp_order_details_after_order_items", $order);
			include( dirname( __FILE__ ) . '/renew-invoices.php' );
			echo "<div class='well' style='font-weight: 700;font-size: 12pt'>" . __( "Order Notes", "wpdm-premium-packages" ) . "</div>";
			include( dirname( __FILE__ ) . '/order-notes.php' );
			do_action("wpdmpp_order_details_after_order_notes", $order);
			?>
        </div>


    <!-- refund -->
    <div class="modal fade" id="refundmodal" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <form method="post" id="refundform">
                    <input type="hidden" name="wpdmpparnonnce" value="<?php echo wp_create_nonce(WPDM_PRI_NONCE) ?>"/>
                    <input type="hidden" name="action" value="wpdmpp_async_request"/>
                    <input type="hidden" name="execute" value="addRefund"/>
                    <input type="hidden" name="order_id" value="<?php echo wpdm_query_var( 'id' ); ?>"/>
                    <div class="modal-header" style="display: flex; justify-content: space-between">
                        <strong><?php _e( "Refund", "wpdm-premium-packages" ); ?></strong>
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"><i class="fa fa-times"></i></button>
                    </div>
                    <div class="modal-header text-center" style="background: #fafafa">
                        <h4 style="padding: 0;margin: 0;"><?php _e( "Order Total", "wpdm-premium-packages" ); ?>: <span
                                    class="order_total"><?php echo wpdmpp_price_format( $order->total ); ?></span></h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <strong style="margin-bottom: 10px;display: block"><?php _e( "Refund Amount", "wpdm-premium-packages" ); ?>
                                :</strong>
                            <input type="text" class="form-control input-lg" name="refund"/>
                        </div>
                        <div class="form-group">
                            <strong style="margin-bottom: 10px;display: block"><?php _e( "Reason For Refund", "wpdm-premium-packages" ); ?>
                                :</strong>
                            <textarea type="text" class="form-control" name="reason"></textarea>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button type="submit"
                                class="btn btn-block btn-primary btn-lg"><?php _e( "Apply Refund", "wpdm-premium-packages" ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- add product -->
    <div class="modal fade" id="addproduct" tabindex="-1" role="dialog" aria-labelledby="addproductLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="addproductLabel"><?php _e('Select Product','wpdm-premium-packages'); ?></h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="text" placeholder="<?php _e('Search Product...','wpdm-premium-packages'); ?>" class="form-control input-lg" id="srcp">
                    <br/>
                    <div class="list-group" id="productlist"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- expire -->
    <div class="modal fade" id="changeexpire" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <form method="post" id="changeexpireform">
                    <input type="hidden" name="wpdmppuednonnce" value="<?php echo wp_create_nonce(WPDM_PRI_NONCE);  ?>">
                    <input type="hidden" name="action" value="wpdmpp_updateOrderExpiryDate">
                    <input type="hidden" name="order_id" value="<?php echo wpdm_query_var( 'id' ); ?>"/>
                    <div class="modal-header" style="display: flex; justify-content: space-between">
                        <h4><?php _e( "Change Expire Date", "wpdm-premium-packages" ); ?></h4>
                        <button class="close" type="button" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <strong style="margin-bottom: 10px;display: block"><?php _e( "Select Date", "wpdm-premium-packages" ); ?>:</strong>
                            <input type="text" class="form-control input-lg datetime" value="<?php echo wp_date( "M d, Y h:i a", $order->expire_date ); ?>" id="expiredate_field" name="expiredate"/>
                        </div>
                        <div class="form-group">
                            <label style="margin-bottom: 10px;display: block"><input id="dorenew" type="checkbox" name="renew" value="1" /> <?php _e( "Renew Order", "wpdm-premium-packages" ); ?></label>
                            <input type="text" class="form-control input-lg datetime" value="<?php echo wp_date( "M d, Y h:i a", $order->expire_date ); ?>" name="renewdate"/>
                        </div>

                    </div>
                    <div class="panel-footer">
                        <button type="submit"
                                class="btn btn-block btn-primary btn-lg"><?php _e( "Apply Changes", "wpdm-premium-packages" ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>





<script>

    jQuery(function ($) {
        var _reload = 0, $body = $('body');

		<?php
		$style = array(
			'Pending'    => 'btn-warning',
			'Expired'    => 'btn-danger',
			'Processing' => 'btn-info',
			'Completed'  => 'btn-success',
			'Bonus'      => 'btn-success',
			'Gifted'     => 'btn-success',
			'Cancelled'  => 'btn-danger',
			'Disputed'   => 'btn-danger',
			'Refunded'   => 'btn-danger'
		);
		$oid = sanitize_text_field( $_GET['id'] );
		?>
        //$('select#osv').selectpicker({style: '<?php echo isset( $style[ $order->order_status ] ) ? $style[ $order->order_status ] : 'btn-default'; ?>'});
        //$('select#psv').selectpicker({style: '<?php echo $style[ $order->payment_status ]; ?>'});

        $('#refundform').on('submit', function (e) {
            e.preventDefault();
            WPDM.blockUI('#refundform');
            $(this).ajaxSubmit({
                url: ajaxurl,
                success: function (response) {
                    $('#refundrow').show();
                    $('#refundamount').html(response.amount)
                    $('.order_total').html(response.total)
                    WPDM.notify("<i class='fa fa-check-double'></i> " + response.msg, 'success', 'top-center', 7000);
                    WPDM.unblockUI('#refundform');
                    $('#refundform').trigger('reset');
                    $('#refundmodal').modal('hide');
                }
            });
        });


        $('#changeexpireform').on('submit', function (e) {
            e.preventDefault();
            WPDM.blockUI('#changeexpireform');
            $(this).ajaxSubmit({
                url: ajaxurl,
                success: function (response) {
                    $('#xdate').html(response.date)
                    WPDM.notify("<i class='fa fa-check-double'></i> " + response.msg, 'success', 'top-center', 7000);
                    WPDM.unblockUI('#changeexpireform');
                    $('#changeexpire').modal('hide');
                }
            });
        });

        $('#dorenew').on('change', function () {
            if($(this).is(':checked'))
                $('#expiredate_field').attr('disabled', 'disabled');
            else
                $('#expiredate_field').removeAttr('disabled');
        });

        $('#update_os').click(function () {
            WPDM.blockUI('#orderbar');
            $.post(ajaxurl, {
                action: 'wpdmpp_async_request',
                execute: 'updateOS',
                wpdmppasyncrequest: '<?php echo wp_create_nonce(WPDM_PRI_NONCE); ?>',
                order_id: '<?php echo $oid; ?>',
                status: $('#osv').val()
            }, function (res) {
                WPDM.notify("<i class='fa fa-check-double'></i> " + res, 'success', 'top-center', 7000);
                WPDM.unblockUI('#orderbar');

            });
        });


        $('#oceml').click(function (e) {
            e.preventDefault();
            //if(!confirm('<?= __('Resending order confirmation email...', WPDMPP_TEXT_DOMAIN) ?>')) return false;
            WPDM.confirm('<?= __('Order Confirmation Email', WPDM_TEXT_DOMAIN); ?>', '<?= __('Resending order confirmation email...', WPDM_TEXT_DOMAIN); ?>', [
                {
                    label: 'Yes, Confirm!',
                    class: 'btn btn-success',
                    callback: function () {
                        let $mod = $(this);
                        $mod.find('.modal-body').html("<i class='fas fa-sun fa-spin'></i> Processing...");

                        $.post(ajaxurl, {
                            action: 'wpdmpp_async_request',
                            execute: 'orderConfirmationEmail',
                            ocemnonce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>',
                            order_id: '<?php echo $oid; ?>',
                        }, function (res) {
                            $('#pmname').html(res.pmname);
                            if(res.success === false)
                                WPDM.notify("<i class='fa fa-times-circle'></i> " + res.message, 'danger', 'top-center', 4000);
                            else
                                WPDM.notify("<i class='fa fa-check-double'></i> " + res.msg, 'success', 'top-center', 4000);
                            $mod.modal('hide');
                        });
                    }
                },
                {
                    label: 'No, Later',
                    class: 'btn btn-info',
                    callback: function () {
                        $(this).modal('hide');
                    }
                }
            ]);


        });


         $('.changepm').click(function (e) {
            e.preventDefault();
            WPDM.blockUI('#orderbar');
            // Add loading state to trigger button
            var $trigger = $('#paymentMethodBtn');
            $trigger.addClass('loading').prop('disabled', true);
            $trigger.find('svg').css('animation', 'wpdm-spin 1s linear infinite');
            $.post(ajaxurl, {
                action: 'wpdmpp_async_request',
                wpdmppasyncrequest: '<?php echo wp_create_nonce(WPDM_PRI_NONCE); ?>',
                execute: 'updatePM',
                order_id: '<?php echo $oid; ?>',
                pm: $(this).data('pm')
            }, function (res) {
                $('#pmname').html(res.pmname);
                WPDM.notify("<i class='fa fa-check-double'></i> " + res.msg, 'success', 'top-center', 4000);
                WPDM.unblockUI('#orderbar');
                // Remove loading state
                $trigger.removeClass('loading').prop('disabled', false);
                $trigger.find('svg').css('animation', '');
            });
        });


        $('#update_ps').click(function () {
            WPDM.blockUI('#orderbar');
            $.post(ajaxurl, {
                action: 'wpdmpp_async_request',
                wpdmppasyncrequest: '<?php echo wp_create_nonce(WPDM_PRI_NONCE); ?>',
                execute: 'updatePS',
                order_id: '<?php echo $oid; ?>',
                status: $('#psv').val()
            }, function (res) {
                WPDM.notify("<i class='fa fa-check-double'></i> " + res, 'success', 'top-center', 4000);
                WPDM.unblockUI('#orderbar');
            });
        });

        $('#save_customer_change').on('click', function () {
            WPDM.blockUI('#changecustomer .modal-content');
            $.post(ajaxurl, {
                action: 'assign_user_2order',
                order: '<?php echo $oid; ?>',
                assignuser: $('#changec').val(),
                __nonce: '<?php echo wp_create_nonce( NONCE_KEY );?>'
            }, function (res) {
                $('#cintable').html("<tbody><tr><td>" + res + "</td></tr></tbpdy>");
                WPDM.unblockUI('#changecustomer .modal-content');
                $('#changecustomer').modal('hide');
            });
        });

        $('#change_transection_id').on('click', function () {
            WPDM.blockUI('#changetrannid .modal-content');
            $.post(ajaxurl, {
                action: 'wpdmpp_change_transection_id',
                order_id: '<?php echo $oid; ?>',
                trans_id: $('#changetid').val(),
                __nonce: '<?php echo wp_create_nonce( NONCE_KEY );?>'
            }, function (res) {
                $('#tnid').html("( " + $('#changetid').val() + " )");
                WPDM.unblockUI('#changetrannid .modal-content');
                alert(res);
                $('#changetrannid').modal('hide');
            }).fail(function (res) {
                alert('<?= esc_attr__( 'Action Failed!', WPDMPP_TEXT_DOMAIN ) ?>');
                WPDM.unblockUI('#changetrannid .modal-content');
            });
        });

        var ruf = $('#ausre').html();
        $body.on('click', '#ausre .alert', function () {
            $('#ausre').html(ruf);
        });
        $body.on('click', '#ausra', function () {
            var ausr = $('#ausr').val();
            $('#ausre').html("<div class='alert alert-primary' style='padding:7px 15px;border-radius:2px;margin:0'><i class='fa fa-spin fa-refresh'></i> <?php _e( 'Please Wait...', 'wpdm-premium-packages' ); ?></div>");
            $.post(ajaxurl, {
                action: 'assign_user_2order',
                order: '<?php echo $oid; ?>',
                assignuser: ausr,
                __nonce: '<?php echo wp_create_nonce( NONCE_KEY );?>'
            }, function (res) {
                $('#ausre').html(res);
            });
        });


        $('#dlh').on('click', function () {
            __bootModal("Download History", "<div id='dlhh'><i class='far fa-sun fa-spin'></i> Loading...</div>", 400);
            $('#dlhh').load(ajaxurl, {
                action: 'wpdmpp_download_hostory',
                oid: '<?php echo wpdm_query_var( 'id', 'txt' ); ?>',
                __dlhnonce: '<?php echo wp_create_nonce( NONCE_KEY ); ?>'
            });
        });



        function search_product()
        {
            $.get('<?= wpdm_rest_url('search') ?>', { search: $('#srcp').val(), premium: 1 }, function (res) {
                //res = JSON.parse(res);
                $('#productlist').html("");

                $(res.packages).each(function( i, package ) {
                    var licenses = package.licenses;
                    if(!licenses) {
                        $("#productlist").append("<div class='list-group-item'><a style='opacity: 1;margin-right: -5px;transform: scale(1.4)' href='#' data-pid='" + package.ID + "' data-license='' data-index='" + i + "' class='pull-right insert-pid'><i class='fa fa-plus-circle color-green'></i></a>" + package.post_title + "</div>");
                    }
                    else {
                        $.each(licenses, function(licid, license) {
                            $("#productlist").append("<div class='list-group-item'><a style='opacity: 1;margin-right: -5px;transform: scale(1.4)' href='#' data-pid='" + package.ID + "' data-license='"+licid+"' data-index='" + i + "' class='pull-right insert-pid'><i class='fa fa-plus-circle color-green'></i></a>" + package.post_title + " &mdash; <span class='text-info'>" + license.name + "</span></div>");
                        });
                    }
                });
            });
        }

        $body.on('keyup', '#srcp', function () {
            search_product();
        });

        $body.on('click', '.insert-pid', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            $(this).find('.fa').removeClass('fa-plus-circle').addClass('fa-sun fa-spin');

            //wpdmpp_admin_cart.push($(this).data('pid')."|".$(this).data('license'));

            //window.localStorage.setItem("wpdmpp_admin_cart", JSON.stringify(wpdmpp_admin_cart));

            var $this = $(this);
            $.get(ajaxurl, {order: '<?= $order->order_id ?>',product: $(this).data('pid'), license: $(this).data('license'), action: 'wpdmpp_edit_order', task: 'add_product', __eononce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>'}, function (res) {
                $this.find('.fa').removeClass('fa-sun fa-spin').addClass('fa-check-circle');
                _reload = 1;
            });


        });

        $('#addproduct').on('hidden.bs.modal', function (e) {
            if(_reload === 1)
                window.location.reload();
        });

        $body.on('click', '.pprmo_item', function () {
            if(!confirm('<?= __('Are you sure?', WPDMPP_TEXT_DOMAIN); ?>')) return false;
            $(this).find('.fa').removeClass('fa-trash').addClass('fa-sun fa-spin');
            $.get(ajaxurl, {order: '<?= $order->order_id ?>',product: $(this).data('pid'), action: 'wpdmpp_edit_order', task: 'remove_product', __eononce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>'}, function (res) {
                window.location.reload();
            });
        });



        $('.datetime').datetimepicker({
            dateFormat: "M dd, yy",
            timeFormat: "hh:mm tt"
        });
        $('.ttip').tooltip();

        // Order Actions Dropdown Toggle
        $('#orderActionsBtn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $parent = $(this).closest('.wpdm-order-actions');
            $parent.toggleClass('open');
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.wpdm-order-actions').length) {
                $('.wpdm-order-actions').removeClass('open');
            }
        });

        // Close dropdown when clicking a menu item
        $('.wpdm-order-actions__item').on('click', function() {
            $(this).closest('.wpdm-order-actions').removeClass('open');
        });

        // Close dropdown on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.wpdm-order-actions').removeClass('open');
                $('.wpdm-pm-dropdown').removeClass('open');
            }
        });

        // Payment Method Dropdown Toggle
        $('#paymentMethodBtn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $parent = $(this).closest('.wpdm-pm-dropdown');
            // Close other dropdowns
            $('.wpdm-order-actions').removeClass('open');
            $parent.toggleClass('open');
        });

        // Close payment method dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.wpdm-pm-dropdown').length) {
                $('.wpdm-pm-dropdown').removeClass('open');
            }
        });

        // Close payment method dropdown after selecting
        $('.wpdm-pm-dropdown__item').on('click', function() {
            $(this).closest('.wpdm-pm-dropdown').removeClass('open');
        });

    });
</script>
<style>
    .chzn-search input {
        display: none;
    }
    .w3eden .badge.badge-success {
        background: var(--color-success);font-weight: 400;font-size: 11px;border-radius: 3px;text-transform: uppercase;letter-spacing: 1px;
    }

    .chzn-results {
        padding-top: 5px !important;
    }

    .btn-group.bootstrap-select .btn {
        border-radius: 3px !important;
    }

    a:focus {
        outline: none !important;
    }

    .panel-heading {
        font-weight: bold;
    }

    .text-renew * {
        font-weight: 800;
        color: #1e9460;
    }

    .w3eden .dropdown-menu > li {
        margin-bottom: 0;
    }

    .w3eden .dropdown-menu > li > a {
        padding: 5px 20px;
    }

    a.list-item {
        display: block;
        padding: 0 20px;
        line-height: 40px;
        color: #666666;
        text-decoration: none;
        font-size: 11px;
    }

    a.list-item:hover {
        text-decoration: none;
    }

    a.list-item:not(:last-child) {
        border-bottom: 1px solid #dddddd;
    }

    /* Order Actions Dropdown - Modern Design */
    .wpdm-order-actions {
        position: relative;
        display: inline-block;
    }

    .wpdm-order-actions__trigger {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .wpdm-order-actions__trigger:hover {
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border-color: #cbd5e1;
        color: #1e293b;
    }

    .wpdm-order-actions__trigger:focus {
        outline: none;
        border-color: var(--color-info, #0ea5e9);
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    }

    .wpdm-order-actions__trigger svg {
        flex-shrink: 0;
        color: #64748b;
    }

    .wpdm-order-actions__trigger span {
        white-space: nowrap;
    }

    .wpdm-order-actions__chevron {
        transition: transform 0.2s ease;
    }

    .wpdm-order-actions.open .wpdm-order-actions__chevron {
        transform: rotate(180deg);
    }

    .wpdm-order-actions__menu {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        min-width: 220px;
        padding: 6px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all 0.2s ease;
        z-index: 1000;
    }

    .wpdm-order-actions.open .wpdm-order-actions__menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .wpdm-order-actions__item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        font-size: 13px;
        color: #475569;
        text-decoration: none !important;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .wpdm-order-actions__item:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .wpdm-order-actions__item:active {
        background: #e2e8f0;
    }

    .wpdm-order-actions__item svg {
        flex-shrink: 0;
        color: #64748b;
        transition: color 0.15s ease;
    }

    .wpdm-order-actions__item:hover svg {
        color: var(--color-info, #0ea5e9);
    }

    /* Separator between items (optional) */
    .wpdm-order-actions__separator {
        height: 1px;
        margin: 6px 0;
        background: #e2e8f0;
    }

    /* Payment Method Dropdown - Modern Design */
    .wpdm-pm-dropdown {
        position: relative;
        display: inline-block;
    }

    .wpdm-pm-dropdown__trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 6px;
        color: var(--color-info, #0ea5e9);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .wpdm-pm-dropdown__trigger:hover svg{
        stroke: var(--admin-color);
    }

    .wpdm-pm-dropdown__trigger svg {
        flex-shrink: 0;
    }

    .wpdm-pm-dropdown__menu {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        width: 240px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all 0.2s ease;
        z-index: 1000;
        overflow: hidden;
    }

    .wpdm-pm-dropdown.open .wpdm-pm-dropdown__menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .wpdm-pm-dropdown__header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
    }

    .wpdm-pm-dropdown__header svg {
        color: #64748b;
    }

    .wpdm-pm-dropdown__body {
        max-height: 200px;
        overflow-y: auto;
        padding: 6px;
    }

    .wpdm-pm-dropdown__item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        font-size: 13px;
        color: #475569;
        text-decoration: none !important;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .wpdm-pm-dropdown__item:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .wpdm-pm-dropdown__item:active {
        background: #e2e8f0;
    }

    .wpdm-pm-dropdown__item svg {
        flex-shrink: 0;
        color: #94a3b8;
        transition: color 0.15s ease;
    }

    .wpdm-pm-dropdown__item:hover svg {
        color: var(--color-success, #10b981);
    }

    .wpdm-pm-dropdown__item.selected {
        background: rgba(16, 185, 129, 0.1);
        color: var(--color-success, #10b981);
    }

    .wpdm-pm-dropdown__item.selected svg {
        color: var(--color-success, #10b981);
    }

    /* Custom scrollbar for payment methods */
    .wpdm-pm-dropdown__body::-webkit-scrollbar {
        width: 6px;
    }

    .wpdm-pm-dropdown__body::-webkit-scrollbar-track {
        background: transparent;
    }

    .wpdm-pm-dropdown__body::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 3px;
    }

    .wpdm-pm-dropdown__body::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }

    /* Spin animation for loading states */
    @keyframes wpdm-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .wpdm-pm-dropdown__trigger.loading {
        opacity: 0.6;
        cursor: wait;
    }
</style>
<?php
} else {
    ?>
    <div class="text-center">
        <div class="alert alert-danger lead" style="border-radius: 3px;display: inline-block;">
            <i class="fa fa-exclamation-triangle mr-2"></i>
            <?php
            echo esc_attr__('Error: No matching order found!', WPDMPP_TEXT_DOMAIN);
            ?>
        </div>
    </div>
    <?php
}
