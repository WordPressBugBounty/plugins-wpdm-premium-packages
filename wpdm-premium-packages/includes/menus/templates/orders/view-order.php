<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$order = $orderObj->getOrder( $order_id );

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
                        <div class="dropdown pull-right">
                            <button class="btn btn-info" id="dLabel" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dLabel">
                                <li><a href="#" id="dlh"><?php _e( 'Download History', 'wpdm-premium-packages' ) ?></a></li>
                                <li><a href="#" id="dlh" onclick="window.open('?id=<?php echo wpdm_query_var( 'id' ); ?>&wpdminvoice=1','Invoice','height=720, width = 750, toolbar=0'); return false;"><?php _e( 'View Invoice', 'wpdm-premium-packages' ) ?></a></li>
                                <li><a href="#" id="oceml"><?php _e( 'Resend Confirmation Email', 'wpdm-premium-packages' ) ?></a></li>
                                <?php do_action("wpdmpp_order_action_menu_item", $order); ?>
                            </ul>
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
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span></button>
                                            <h4 class="modal-title"><?php echo __( "Change Transection ID", "wpdm-premium-packages" ); ?></h4>
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
                    <div class="panel panel-default">
                        <div class="panel-heading"><?php _e( "Order Total", "wpdm-premium-packages" ); ?></div>
                        <div class="panel-body">

                            <div class="dropdown pull-right" style="margin-top: -1px">
                                <a href="#" id="dLabel" class="ttip" type="button" style="color: var(--color-info)" data-toggle="dropdown"
                                   aria-haspopup="true" aria-expanded="false"
                                   title="<?php _e( "Change Payment Method", "wpdm-premium-packages" ); ?>">
                                    <i id="editpm" class="fa-solid fa-square-pen" style="font-size: 15pt;"></i>
                                </a>

                                <div class="dropdown-menu panel panel-default" aria-labelledby="dLabel"
                                     style="padding: 0;width: 230px;">
                                    <div class="panel-heading"><?php _e( "Change Payment Method:", "wpdm-premium-packages" ); ?></div>
                                    <div class="panel-body-np" style="height: 200px;overflow: auto;">
										<?php
										$payment_methods = WPDMPP()->active_payment_gateways();
										foreach ( $payment_methods as $payment_method ) {
											$payment_method_class = $payment_method;
											$payment_method       = str_replace( "WPDM_", "", $payment_method );
											?>
                                            <a href="#" class="list-item changepm"
                                               data-pm="<?php echo $payment_method_class; ?>"><?php echo $payment_method; ?></a>
										<?php } ?>
                                    </div>
                                </div>
                            </div>

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
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span></button>
                                            <h4 class="modal-title"><?php echo __( "Change Customer", "wpdm-premium-packages" ); ?></h4>
                                        </div>
                                        <div class="modal-body">
                                            <input type="text"
                                                   placeholder="<?php echo __( "Username or Email", "wpdm-premium-packages" ); ?>"
                                                   class="form-control input-lg" id="changec"/>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" id="save_customer_change"
                                                    class="btn btn-primary"><?php echo __( "Change", "wpdm-premium-packages" ); ?></button>
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
                    <div class="modal-header">
                        <strong><?php _e( "Refund", "wpdm-premium-packages" ); ?></strong>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="addproductLabel"><?php _e('Select Product','wpdm-premium-packages'); ?></h4>
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
                    <div class="modal-header">
                        <strong><?php _e( "Change Expire Date", "wpdm-premium-packages" ); ?></strong>
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
            $('#editpm').removeClass('fa-square-pen').addClass('fa-sun fa-spin');
            $.post(ajaxurl, {
                action: 'wpdmpp_async_request',
                execute: 'updatePM',
                order_id: '<?php echo $oid; ?>',
                pm: $(this).data('pm')
            }, function (res) {
                $('#pmname').html(res.pmname);
                WPDM.notify("<i class='fa fa-check-double'></i> " + res.msg, 'success', 'top-center', 4000);
                WPDM.unblockUI('#orderbar');
                $('#editpm').removeClass('fa-sun fa-spin').addClass('fa-square-pen');

            });
        });


        $('#update_ps').click(function () {
            WPDM.blockUI('#orderbar');
            $.post(ajaxurl, {
                action: 'wpdmpp_async_request',
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
