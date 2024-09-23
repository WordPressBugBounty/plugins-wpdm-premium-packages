<?php
if(!defined('ABSPATH')) die('Dream more!');


global $wpdb;


if ( isset( $_REQUEST['oid'] ) && $_REQUEST['oid'] ) {
	$qry[] = "order_id='" . sanitize_text_field( $_REQUEST['oid'] ) . "'";
}
if ( isset( $_REQUEST['customer'] ) && $_REQUEST['customer'] != '' ) {
	$customer = esc_sql( $_REQUEST['customer'] );
	if ( is_email( $customer ) ) {
		$customer = email_exists( $customer );
	}
	$qry[] = "uid='{$customer}'";
}
if ( wpdm_query_var( 'ost' ) != 'Expiring' ) {
	if ( isset( $_REQUEST['ost'] ) && $_REQUEST['ost'] ) {
		$qry[] = "order_status='" . sanitize_text_field( $_REQUEST['ost'] ) . "'";
	}
	if ( isset( $_REQUEST['pst'] ) && $_REQUEST['pst'] ) {
		$qry[] = "payment_status='" . sanitize_text_field( $_REQUEST['pst'] ) . "'";
	}

	if ( isset( $_REQUEST['sdate'], $_REQUEST['edate'] ) && ( $_REQUEST['sdate'] != '' || $_REQUEST['edate'] != '' ) ) {
		$_REQUEST['edate'] = $_REQUEST['edate'] ? $_REQUEST['edate'] : $_REQUEST['sdate'];
		$_REQUEST['sdate'] = $_REQUEST['sdate'] ? $_REQUEST['sdate'] : $_REQUEST['edate'];
		$sdate             = strtotime( $_REQUEST['sdate'] );
		$edate             = strtotime( $_REQUEST['edate'] );
		$qry[]             = "(`date` >=$sdate and `date` <=$edate)";
	}
} else {
	$qry[] = "order_status='Completed'";
	$sdate = wpdm_query_var( 'sdate' ) != '' ? strtotime( wpdm_query_var( 'sdate' ) ) : time();
	$edate = wpdm_query_var( 'edate' ) != '' ? strtotime( wpdm_query_var( 'edate' ) ) : strtotime( "+7 days" );
	$qry[] = "(`expire_date` >=$sdate and `expire_date` <=$edate)";

}

if ( isset( $qry ) ) {
	$qry = "where " . implode( " and ", $qry );
} else {
	$qry = "";
}

if ( wpdm_query_var( 'orderby' ) != '' ) {
	$orderby = sanitize_text_field( wpdm_query_var( 'orderby' ) );
	$_order  = wpdm_query_var( 'order' ) == 'asc' ? 'asc' : 'desc';
	$qry     = $qry . " order by $orderby $_order";
} else {
	$qry = "$qry order by `date` desc";
}

$t      = $orderObj->totalRenews( );
$orders = $orderObj->getAllRenews( $qry, $s, $l );

$osi = array('Pending'=>'ellipsis-h','Processing'=>'paw','Completed'=>'check','Cancelled'=>'times','Refunded'=>'retweet','Expired' => ' fas fa-exclamation-triangle','Gifted' => 'gift','Disputed'=>'gavel');
if(!wpdm_query_var('customer') && !wpdm_query_var('oid')) {
	$completed  = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_orders where payment_status='Completed' or payment_status='Expired'" );
	$expired    = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_orders where payment_status='Expired'" );
	$refunded   = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_orders where payment_status='Refunded'" );
	$abandoned  = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_orders where payment_status='Processing'" );
	$allrenews  = $wpdb->get_row( "select sum(total) as sales, count(total) as orders, order_id from {$wpdb->prefix}ahm_order_renews" );

	$sdatet     = strtotime( date( "Y-m-d" ) . " 00:00:00" );
	$edatet     = strtotime( date( "Y-m-d" ) . " 23:59:59" );
	$newtoday   = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_orders where payment_status='Completed' and `date` >= '$sdatet' and `date` <= '$edatet'" );
	$renewtoday = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_order_renews where `date` >= '$sdatet' and `date` <= '$edatet'" );

}
$where = [];
foreach ( $orders as $order ) {
    $where[] = "'{$order->order_id}'";
}
$where = implode(",", $where);
$renews     = $wpdb->get_results( "select count(*) as renew_cycle, order_id from {$wpdb->prefix}ahm_order_renews where order_id in ($where) GROUP  BY order_id" );
$renew_cycle = array();
foreach ($renews as $renew){
	$renew_cycle[$renew->order_id] = $renew->renew_cycle;
}
?>
<div class="order summery row">
<?php if(!wpdm_query_var('customer') && !wpdm_query_var('oid')) { ?>
                <div class="col-sm-2">
                    <div class="panel panel-default text-center">
                        <div class="panel-heading"><?php echo __( "Completed Orders", WPDMPP_TEXT_DOMAIN ); ?></div>
<div class="panel-body"><h3 class="color-green"><?php echo wpdmpp_price_format($completed->sales, true, true); ?> / <?php echo $completed->orders; ?></h3></div>
</div>
</div>
    <div class="col-sm-2">
        <div class="panel panel-default text-center">
            <div class="panel-heading"><?php echo __( "Renewed Orders", WPDMPP_TEXT_DOMAIN ) ?></div>
            <div class="panel-body"><h3 class="color-blue"><?php echo wpdmpp_price_format($allrenews->sales, true, true); ?> / <?php echo $allrenews->orders; ?></h3></div>
        </div>
    </div>
    <div class="col-sm-2">
        <div class="panel panel-default text-center">
            <div class="panel-heading"><?php echo __( "New Orders Today", WPDMPP_TEXT_DOMAIN ) ?></div>
            <div class="panel-body"><h3 class="color-blue"><?php echo wpdmpp_price_format($newtoday->sales, true, true); ?> / <?php echo $newtoday->orders; ?></h3></div>
        </div>
    </div>
    <div class="col-sm-2">
        <div class="panel panel-default text-center">
            <div class="panel-heading"><?php echo __( "Renewed Orders Today", WPDMPP_TEXT_DOMAIN ) ?></div>
            <div class="panel-body"><h3 class="color-blue"><?php echo wpdmpp_price_format(@$renewtoday->sales, true, true); ?> / <?php echo (int)@$renewtoday->orders; ?></h3></div>
        </div>
    </div>
<div class="col-sm-2">
	<div class="panel panel-default text-center">
		<div class="panel-heading"><?php echo __( "Refunded Orders", WPDMPP_TEXT_DOMAIN ); ?></div>
		<div class="panel-body"><h3 class="color-red"><?php echo wpdmpp_price_format($refunded->sales, true, true); ?> / <?php echo $refunded->orders; ?></h3></div>
	</div>
</div>
<div class="col-sm-2">
	<div class="panel panel-default text-center">
		<div class="panel-heading"><?php echo __( "Expired Orders", WPDMPP_TEXT_DOMAIN ); ?></div>
		<div class="panel-body">

			<h3 class="color-purple">
				<?php echo wpdmpp_price_format($expired->sales, true, true); ?> / <?php echo $expired->orders; ?>
				&nbsp;
			</h3>
		</div>
	</div>


</div>
    <?php } ?>
<div class="clear"></div>
<div class="col-md-12">
	<form method="get" action="" id="order-search">

		<input type="hidden" name="post_type" value="wpdmpro">
		<input type="hidden" name="page" value="orders">
		<div class="panel panel-default">
			<div class="panel-body">
				<div class="row">
					<div class="col-md-2"><select class="select-action form-control wpdm-custom-select" name="ost">
							<option value=""><?php _e('Order status:','wpdm-premium-packages'); ?></option>
							<option value="Pending" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Pending'?'selected=selected':''; ?>>Pending</option>
							<option value="Processing" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Processing'?'selected=selected':''; ?>>Processing</option>
							<option value="Completed" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Completed'?'selected=selected':''; ?>>Completed</option>
							<option value="Cancelled" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Cancelled'?'selected=selected':''; ?>>Cancelled</option>
							<option value="Expiring" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Expiring'?'selected=selected':''; ?>>Expiring ( On Selected Period )</option>
						</select></div>
					<div class="col-md-2"><select class="select-action form-control wpdm-custom-select" name="pst">
							<option value=""><?php _e('Payment status:','wpdm-premium-packages'); ?></option>
							<option value="Pending" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Pending'?'selected=selected':''; ?>>Pending</option>
							<option value="Processing" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Processing'?'selected=selected':''; ?>>Processing</option>
							<option value="Completed" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Completed'?'selected=selected':''; ?>>Completed</option>
							<option value="Bonus" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Bonus'?'selected=selected':''; ?>>Bonus</option>
							<option value="Gifted" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Gifted'?'selected=selected':''; ?>>Gifted</option>
							<option value="Cancelled" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Cancelled'?'selected=selected':''; ?>>Cancelled</option>
							<option value="Disputed" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Disputed'?'selected=selected':''; ?>>Disputed</option>
							<option value="Refunded" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Refunded'?'selected=selected':''; ?>>Refunded</option>
						</select></div>
					<div class="col-md-1"><input  class="form-control datep" type="text" placeholder="<?php _e("From Date","wpdm-premium-packages");?>" name="sdate" value="<?php if(isset($_REQUEST['sdate'])) echo esc_attr($_REQUEST['sdate']); ?>"></div>
					<div class="col-md-1"><input  class="form-control datep" type="text" placeholder="<?php _e("To Date","wpdm-premium-packages");?>" name="edate" value="<?php if(isset($_REQUEST['edate'])) echo esc_attr($_REQUEST['edate']); ?>"></div>
					<div class="col-md-2"><input  class="form-control" type="text" placeholder="<?php _e("Order ID","wpdm-premium-packages");?> " name="oid" value="<?php if(isset($_REQUEST['oid'])) echo esc_attr($_REQUEST['oid']); ?>"></div>
					<div class="col-md-2"><input  class="form-control" type="text" placeholder="<?php _e("Customer ID / Email / Username","wpdm-premium-packages");?> " name="customer" value="<?php if(isset($_REQUEST['customer'])) echo esc_attr($_REQUEST['customer']); ?>"></div>
					<div class="col-md-2"><button style="margin: 0" type="submit" class="btn btn-secondary btn-block" id="doaction" name="doaction"><i class="fas fa-search"></i> <?php _e('Search','wpdm-premium-packages'); ?></button></div>
				</div>

			</div>
			<?php if(!wpdm_query_var('customer') && !wpdm_query_var('oid')) { ?>
			<div class="panel-footer"><?php
				// Calculate Total Sales

				?>
				<span class="pull-right color-green"> <b><?php _e("Total Sales:","wpdm-premium-packages");?> <?php echo wpdmpp_price_format($completed->sales, true, true); ?></b></span>
				<b><?php echo $completed->orders; ?> <?php _e("order(s) found","wpdm-premium-packages");?></b>
			</div>
            <?php } ?>
		</div>
	</form>
	<div class="clear"></div>
	<form method="get" action="<?php echo admin_url('/edit.php'); ?>" id="orders-form">
		<input type="hidden" name="post_type" value="wpdmpro">
		<input type="hidden" name="page" value="orders">

		<?php if(wpdm_query_var('ost') == 'Expiring'){ ?>
			<div class="panel panel-default">
				<div class="panel-body">
					<ul>
						<li><input type="checkbox" checked="checked" disabled="disabled" style="margin: 0"> Update order status to expired</li>
						<li><input type="checkbox" checked="checked" disabled="disabled" style="margin: 0"> Send email notification to customers</li>
					</ul>
				</div>
				<div class="panel-footer">
					<a href="#" class="btn btn-primary" id="expire-orders">Execute</a>
				</div>
			</div>
		<?php } ?>

		<div class="panel panel-default">
			<table cellspacing="0" class="table table-striped table-wpdmpp">
				<thead>
				<tr>
					<th style="width: 40px" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
					<th style="width: 40px" class="manage-column" id="media" scope="col">
						<div class="w3eden">
                    <span class="fa-stack ttip" title="<?php _e("Order Status","wpdm-premium-packages");?>">
                        <i class="fa fa-circle-thin fa-stack-2x"></i>
                        <i class="fas fa-cart-arrow-down fa-stack-1x"></i>
                    </span>
						</div>
					</th>
					<th style="" class="manage-column" id="media" scope="col"><?php _e("Order","wpdm-premium-packages");?></th>
					<th style="width: 40px" class="manage-column" id="media" scope="col">
						<div class="w3eden">
                    <span class="fa-stack ttip" title="<?php _e("Payment Status","wpdm-premium-packages");?>">
                        <i class="fa fa-circle-thin fa-stack-2x"></i>
                        <i class="far fa-money-bill-alt fa-stack-1x"></i>
                    </span>
						</div>
					</th>
					<th style="width: 150px" class="manage-column" id="author" scope="col"><?php _e("Total","wpdm-premium-packages");?></th>
					<th style="" class="manage-column" id="author" scope="col"><?php _e("Customer","wpdm-premium-packages");?></th>
					<th style="width: 200px" class="manage-column column-parent" id="parent" scope="col"><?php _e("Order Date","wpdm-premium-packages");?></th>
					<th style="" class="manage-column" id="parent" scope="col"><?php _e("Renew Cycle","wpdm-premium-packages");?></th>
					<th style="" class="manage-column" id="parent" scope="col"><?php _e("Renewed On","wpdm-premium-packages");?></th>
					<th style="width: 40px" class="manage-column" id="parent" scope="col">
						<div class="w3eden">
                    <span class="fa-stack ttip" title="<?php _e('Item Download Status','wpdm-premium-packages'); ?>">
                        <i class="fa fa-circle-thin fa-stack-2x"></i>
                        <i class="fa fa-download fa-stack-1x"></i>
                    </span>
						</div>
					</th>
					<th style="width: 40px" class="manage-column" id="parent" scope="col">
						<div class="w3eden">
                    <span class="fa-stack ttip" title="<?php _e('Auto Renew Status','wpdm-premium-packages'); ?>">
                        <i class="fa fa-circle-thin fa-stack-2x"></i>
                        <i class="fa fa-sync fa-stack-1x"></i>
                    </span>
						</div>
					</th>
					<?php do_action("wpdmpp_orders_custom_column_th"); ?>
				</tr>
				</thead>

				<tfoot>
				<tr>
					<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
					<th style="" class="manage-column" id="media" scope="col" title="<?php _e("Order Status","wpdm-premium-packages");?>">
                <span class="fa-stack">
                    <i class="fa fa-circle-thin fa-stack-2x"></i>
                    <i class="fas fa-cart-arrow-down fa-stack-1x"></i>
                </span>
					</th>
					<th style="" class="manage-column" id="media" scope="col"><?php _e("Order","wpdm-premium-packages");?></th>
					<th style="width: 40px" class="manage-column" id="media" scope="col" title="<?php _e("Payment Status","wpdm-premium-packages");?>">
                <span class="fa-stack">
                    <i class="fa fa-circle-thin fa-stack-2x"></i>
                    <i class="far fa-money-bill-alt fa-stack-1x"></i>
                </span>
					</th>
					<th style="" class="manage-column" id="author" scope="col"><?php _e("Total","wpdm-premium-packages");?></th>
					<th style="" class="manage-column " id="author" scope="col"><?php _e("Customer","wpdm-premium-packages");?></th>
					<th style="" class="manage-column" id="parent" scope="col"><?php _e("Order Date","wpdm-premium-packages");?></th>
					<th style="" class="manage-column" id="parent" scope="col"><?php _e("Renew Cycle","wpdm-premium-packages");?></th>
					<th style="" class="manage-column" id="parent" scope="col"><?php _e("Renewed On","wpdm-premium-packages");?></th>
					<th style="width: 40px" class="manage-column" id="parent" scope="col">
						<div class="w3eden">
                    <span class="fa-stack ttip" title="<?php _e('Item Download Status','wpdm-premium-packages'); ?>">
                        <i class="fa fa-circle-thin fa-stack-2x"></i>
                        <i class="fa fa-download fa-stack-1x"></i>
                    </span>
						</div>
					</th>
					<th style="width: 40px" class="manage-column" id="parent" scope="col">
						<div class="w3eden">
                    <span class="fa-stack ttip" title="<?php _e('Auto Renew Status','wpdm-premium-packages'); ?>">
                        <i class="fa fa-circle-thin fa-stack-2x"></i>
                        <i class="fa fa-sync fa-stack-1x"></i>
                    </span>
						</div>
					</th>
					<?php do_action("wpdmpp_orders_custom_column_th"); ?>
				</tr>
				</tfoot>

				<tbody class="list:post" id="the-list">
				<?php
				$z = 'alternate';
				foreach($orders as $order) {
					//$o = new \WPDMPP\Libs\Order();
					//echo $o->calcOrderTotal($order->order_id);
					//echo "<pre>".print_r($order, 1);die();
					$user_info = get_userdata($order->uid);
					$z = $z == 'alternate' ? '' : 'alternate';
					$currency = maybe_unserialize($order->currency);
					$currency = is_array($currency) && isset($currency['sign'])?$currency['sign']:'$';
					$citems = maybe_unserialize($order->cart_data);
					$sbilling =  array
					(
						'first_name' => '',
						'last_name' => '',
						'company' => '',
						'address_1' => '',
						'address_2' => '',
						'city' => '',
						'postcode' => '',
						'country' => '',
						'state' => '',
						'email' => '',
						'order_email' => '',
						'phone' => ''
					);
					$billing = unserialize($order->billing_info);
					$billing = shortcode_atts($sbilling, $billing);
					$items = 0;
					if(is_array($citems)){
						foreach($citems as $ci){
							$items += (int)wpdm_valueof($ci,'quantity');
						}}
					$oitems = maybe_unserialize($order->cart_data);
					$product_name = array();
					if(is_array($oitems)) {
						foreach ($oitems as $oitem) {
							$product_name[] = wpdm_valueof($oitem, 'product_title', wpdm_valueof($oitem, 'post_title'));
						}
					}
					$product_names = implode(", ", $product_name);
					$_product_name = (isset($product_name[0])) ? $product_name[0] : '';
					$order_title = $order->title ? $order->title : $_product_name;
					if($order->expire_date == 0)
						$order->expire_date = $order->date + (get_wpdmpp_option('order_validity_period', 365) * 86400);

					?>
					<tr class="<?php echo wpdm_query_var('focus') === $order->order_id ? 'row-focus' : '' ?>">
						<th class="check-column" scope="row"><input type="checkbox" class="cboid" value="<?php echo $order->order_id; ?>" name="id[]"></th>
						<td class="">
							<div class="w3eden">
                    <span title="<?php echo $order->order_status; ?>" class="fa-stack oa-<?php echo $order->order_status; ?>">
                        <i class="fa fa-circle-thin fa-stack-2x"></i>
                        <i class="fa fa-<?php echo $osi[$order->order_status]; ?> fa-stack-1x"></i>
                    </span>
							</div>
						</td>
						<td class="">
							<strong>
								<a title="<?php echo __( "View Order Details", WPDMPP_TEXT_DOMAIN ) ?>" href="edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=<?php echo $order->order_id; ?>"><?php echo $order->order_id; ?></a>
							</strong><br/>
							<small class="text-muted">
								<i  class="ttip far fa-list-alt" style="color: var(--color-info);" title="<?php echo $product_names; ?>"></i> <?php echo $items; ?> <?php $items > 1 ? _e("items","wpdm-premium-packages"):_e("item","wpdm-premium-packages");?>
								<?php if($order->trans_id !== '') { ?><span class="ttip" title="<?php echo __( "Transaction ID", WPDMPP_TEXT_DOMAIN ) ?>"><i class="fas fa-bullseye" style="color: var(--color-primary);margin-left: 5px"></i> <?php echo apply_filters("wpdmpp_admin_order_details_trans_id", $order->trans_id, $order->payment_method); ?></span><?php } ?>
							</small>
						</td>
						<td class="">
							<div class="w3eden">
                    <span title="<?php echo $order->payment_status; ?>" class="fa-stack oa-<?php echo $order->payment_status; ?>">
                        <i class="fa fa-circle-thin fa-stack-2x"></i>
                        <i class="fa fa-<?php echo $osi[$order->payment_status]; ?> fa-stack-1x"></i>
                    </span>
							</div>
						</td>
						<td class=""><strong><?php echo wpdmpp_price_format($order->total,true, true); ?></strong><br/>
							<small class="note"><?php _e('via','wpdm-premium-packages'); echo " ".str_replace("WPDM_", "", $order->payment_method); ?></small>
						</td>
						<td class="">
							<?php if(is_object($user_info)){ ?>
								<b><a href="edit.php?post_type=wpdmpro&page=customers&view=profile&id=<?php echo $user_info->ID; ?>"><?php echo $user_info->display_name; ?></a></b>
								<a class="text-filter" title="<?php _e('All orders placed by this customer','wpdm-premium-packages'); ?>" href="edit.php?post_type=wpdmpro&page=orders&customer=<?php echo $user_info->ID; ?>&focus=<?php echo $order->order_id ?>"><i class="fas fa-search"></i></a><br/>
								<a href="mailto:<?php echo $user_info->user_email; ?>"><?php echo $user_info->user_email; ?></a>
							<?php } else { ?>
								<b><?php echo $billing['first_name'].' '.$billing['last_name']; ?></b>
								<a class="text-filter" href="edit.php?post_type=wpdmpro&page=orders&customer=<?php echo $billing['order_email']; ?>"><i class="fas fa-search"></i></a><br/>
								<a href="mailto:<?php echo $billing['order_email']; ?>"><?php echo $billing['order_email']; ?></a>
							<?php }?>
						</td>
						<td class=""><?php echo wp_date(get_option('date_format'). " "  .get_option('time_format'),$order->date); ?></td>
						<td class=""><?php echo isset($renew_cycle[$order->order_id])?sprintf(__("%d time(s)", 'wpdm-premium-packages'), $renew_cycle[$order->order_id]):__('First Purchase', 'wpdm-premium-packages'); ?></td>
						<td style="color: var(--color-success)"><?php echo wp_date(get_option('date_format'). " "  .get_option('time_format'),$order->renew_date); ?></td>
						<td style="" class="" id="parent" scope="col">
							<div class="w3eden">
                    <span class="fa-stack download-<?php echo $order->download==0?'off':'on'; ?> ttip" title="<?php echo $order->download==0?__('New','wpdm-premium-packages'):__('Downloaded','wpdm-premium-packages'); ?>">
                        <i class="fa fa-circle-thin fa-stack-2x"></i>
                        <i class="fa fa-toggle-<?php echo $order->download==0?'off':'on'; ?> fa-stack-1x"></i>
                    </span>
							</div>
						</td>
						<td style="" class="" id="parent" scope="col">
							<div class="w3eden">
								<a href="#" class="auto-renew-order" data-order="<?php echo $order->order_id; ?>">
                                        <span class="fa-stack renew-<?php echo $order->auto_renew==0?'cancelled':'active'; ?>">
                                            <i class="fa fa-circle-thin fa-stack-2x"></i>
                                            <i class="fa <?php echo $order->auto_renew==1?'fa-check':'fa-times'; ?> fa-stack-1x"></i>
                                        </span>
								</a>
							</div>
						</td>
						<?php do_action("wpdmpp_orders_custom_column_td", $order); ?>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php

		$page_links = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'total' => ceil($t/$l),
			'current' => $p
		));
		?>

		<div id="ajax-response"></div>

		<div class="tablenav">
			<?php
			if ( $page_links ) {
				?>
				<div class="tablenav-pages">
					<?php
					$paged = wpdm_query_var('paged');
					$paged = (int)$paged > 0?$paged:1;
					$page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
						number_format_i18n( ( $paged - 1 ) * $l + 1 ),
						number_format_i18n( min( $paged * $l, $t ) ),
						number_format_i18n( $t ),
						$page_links
					);

					echo $page_links_text; ?>
				</div>
			<?php } ?>

			<div class="alignleft actions" style="height: 35px;">
				<input type="hidden" id="delete_confirm" name="delete_confirm" value="0" />
			</div>


			<br class="clear">
		</div>

	</form>
</div>
<br class="clear">
</div>
