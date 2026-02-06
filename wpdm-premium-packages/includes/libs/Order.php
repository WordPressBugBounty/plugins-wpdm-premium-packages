<?php

namespace WPDMPP\Libs;

// Exit if accessed directly
use WPDM\__\__;
use WPDM\__\Email;
use WPDM\__\__MailUI;
use WPDM\__\Messages;
use WPDM\__\Session;
use WPDMPP\Product;
use WPDMPP\WPDMPremiumPackage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Order {
	var $oid;
	var $order_id;
	var $trans_id;
	var $title;
	var $date;
	var $items;
	var $total;
	var $expire_date;
	var $auto_renew;
	var $ID;
	var $orderData;
	var $uid = 0;
	var $coupon_discount;
	var $cart_discount;
	var $cart_data;
	var $order_status;
	var $payment_status;
	var $ipn;
	var $unit_prices;
	var $subtotal;
	var $tax;
	var $coupon_code;
	var $refund;
	var $meta_data;

	function __construct( $oid = '' ) {
		if ( $oid ) {
			$this->oid      = $oid;
			$this->ID       = $oid;
			$this->order_id = $oid;
			$order          = $this->getOrder( $oid );
			if ( $order ) {
				$order = (array) $order;
				foreach ( $order as $key => $val ) {
					$this->$key = maybe_unserialize( $val );
					if ( $key != 'order_id' ) {
						$this->orderData[ $key ] = maybe_unserialize( $val );
					}
				}
			} else {
				$this->oid = $this->ID = null;
			}
		}
	}

	function set( $key, $val ) {
		$this->orderData[ $key ] = $val;
		$this->$key              = $val;

		return $this;
	}

	function save() {
		return Order::update( $this->orderData, $this->oid );
	}

	function open() {
		global $wpdb;
		$order_id    = strtoupper( get_wpdmpp_option( 'order_id_prefix', 'WPDMPP' ) . uniqid() );
		$order_title = get_wpdmpp_option( 'order_title' );
		$cart_items  = WPDMPP()->cart->getItems();

		if ( count( $cart_items ) === 0 ) {
			return false;
		}
		$items           = array_values( $cart_items );
		$first_item      = array_shift( $items );
		$product_name    = wpdm_valueof( $first_item, 'product_name' );
		$order_title     = str_replace( [ '{{PRODUCT_NAME}}', '{{ORDER_ID}}' ], [
			$product_name,
			$order_id
		], $order_title );
		$billing_country = wpdm_query_var( "billing/country" );
		$billing_state   = wpdm_query_var( "billing/state" );
		$subtotal        = WPDMPP()->cart->cartTotal();
		$discount        = WPDMPP()->cart->couponDiscount();
		$tax             = WPDMPP()->cart->calculateTax( $subtotal - $discount, $billing_country, $billing_state, false );
		$total           = $subtotal - $discount + $tax;
		$currency        = serialize( [ 'sign' => wpdmpp_currency_sign(), 'code' => wpdmpp_currency_code() ] );

		$order           = [
			'order_id'        => $order_id,
			'title'           => $order_title,
			'date'            => time(),
			'expire_date'     => 0,
			'auto_renew'      => get_wpdmpp_option( 'auto_renew', 0 ),
			'items'           => serialize( array_keys( $cart_items ) ),
			'cart_data'       => serialize( $cart_items ),
			'total'           => wpdmpp_price_format($total, false, false),
			'order_status'    => 'Processing',
			'payment_status'  => 'Processing',
			'payment_method'  => wpdm_query_var( 'method' ),
			'uid'             => get_current_user_id(),
			'subtotal'        => $subtotal,
			'billing_info'    => serialize( wpdm_query_var( 'billing' ) ),
			'coupon_discount' => $discount,
			'cart_discount'   => $discount,
			'tax'             => $tax,
			'currency'        => $currency,
			'download'        => 0,
			'IP'              => __::get_client_ip(),
			'coupon_code'     => WPDMPP()->cart->getCoupon( 'code' ) ?? '',
			'refund'          => 0
		];

		$inserted = $wpdb->insert( "{$wpdb->prefix}ahm_orders", $order );
		if ( ! $inserted ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo "<style>.modal-body code { display: block; }</style>";
				$wpdb->show_errors();
				$wpdb->print_error();
				die();
			} else {
				die( __( "Order creation is failed due to some error! Please contact site admin.", "wpdm-premium-packages" ) );
			}
		}
		self::updateOrderItems( $cart_items, $order_id );
		$this->oid = $this->order_id = $this->ID = $order_id;
		Session::set( 'orderid', $order_id );
		do_action( "wpdmpp_new_order_created", $order_id );

		return $order_id;
	}

	function reCalculate( $order_id ) {
		global $wpdb;
		$cart_items   = WPDMPP()->cart->getItems();
		$items        = array_values( $cart_items );
		$first_item   = array_shift( $items );
		$product_name = wpdm_valueof( $first_item, 'product_name' );
		$order_title  = get_wpdmpp_option( 'order_title' );
		$order_title  = str_replace( [ '{{PRODUCT_NAME}}', '{{ORDER_ID}}' ], [
			$product_name,
			$order_id
		], $order_title );
		$subtotal     = WPDMPP()->cart->cartTotal();
		$discount     = WPDMPP()->cart->couponDiscount();
		$tax          = WPDMPP()->cart->getTax();
		$total        = $subtotal - $discount + $tax;
		$order        = [
			'title'           => $order_title,
			'items'           => serialize( array_keys( $cart_items ) ),
			'cart_data'       => serialize( $cart_items ),
			'total'           => wpdmpp_price_format($total, false, false),
			'uid'             => get_current_user_id(),
			'subtotal'        => $subtotal,
			'billing_info'    => serialize( wpdm_query_var( 'billing' ) ),
			'coupon_discount' => $discount,
			'cart_discount'   => $discount,
			'tax'             => $tax,
			'coupon_code'     => WPDMPP()->cart->getCoupon( 'code' ),
			'refund'          => 0
		];
		//wpdmdd($order);
		$wpdb->update( "{$wpdb->prefix}ahm_orders", $order, [ 'order_id' => $order_id ] );
		do_action( "wpdmpp_order_recalculated", $order_id, $total );
		Session::set( 'orderid', $order_id );
	}

	function newOrder( $id, $title, $items, $total, $userid, $order_status = 'Processing', $payment_status = 'Processing', $cart_data = '', $order_notes = "", $payment_method = "" ) {
		global $wpdb;

		$currency    = serialize( [ 'sign' => wpdmpp_currency_sign(), 'code' => wpdmpp_currency_code() ] );

		$days        = (int) get_wpdmpp_option( 'order_validity_period' );
		$expire_date = strtotime( "+{$days} days" );
		$ip          = __::get_client_ip();
		$ret         = $wpdb->insert( "{$wpdb->prefix}ahm_orders", array(
			'order_id'       => $id,
			'title'          => $title,
			'date'           => time(),
			'items'          => $items,
			'total'          => wpdmpp_price_format($total, false, false),
			'order_status'   => $order_status,
			'payment_status' => $payment_status,
			'cart_data'      => $cart_data,
			'uid'            => (int) $userid,
			'order_notes'    => $order_notes,
			'payment_method' => $payment_method,
			'download'       => 0,
			'IP'             => $ip,
			'currency'       => $currency,
			'auto_renew'     => 0,
			'expire_date'    => $expire_date
		) );

		$this->oid = $id;
		Session::set( 'orderid', $id );
		do_action( "wpdmpp_new_order_created", $id );

		return $id;
	}

	static function update( $data, $id ) {
		global $wpdb;
		$data = apply_filters( "wpdmpp_update_order", $data, $id );
		foreach ( $data as &$column ) {
			if ( is_array( $column ) ) {
				$column = maybe_serialize( $column );
			}
		}

		$old_data = $wpdb->get_row( "select * from {$wpdb->prefix}ahm_orders where order_id='$id'" );
		$res      = $wpdb->update( "{$wpdb->prefix}ahm_orders", $data, array( 'order_id' => $id ) );
		do_action( "wpdmpp_order_updated", $id, $data, $old_data );

		return $res;
	}

	function cancel() {
		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}ahm_orders", ['order_status' => 'Cancelled', 'auto_renew' => 0], array( 'order_id' => $this->order_id ) );
		do_action( "wpdmpp_order_cencelled", $this);
	}

	function updateTax() {

	}

	public static function customerInfo( $order_id ) {
		$_order = new Order();
		$order  = $_order->getOrder( $order_id );
		if ( $order->uid < 1 ) {
			$billing_info      = unserialize( $order->billing_info );
			$customer['name']  = isset( $billing_info['first_name'] ) ? $billing_info['first_name'] : '';
			$customer['email'] = isset( $billing_info['order_email'] ) ? $billing_info['order_email'] : '';
		} else {
			$user              = get_user_by( 'id', $order->uid );
			$customer['name']  = $user->display_name;
			$customer['email'] = $user->user_email;
		}

		return $customer;
	}

	public function addItem( $itemID, $license ) {
		$cart_data = $this->cart_data;
		$product   = new Product( $itemID );
		$price     = $product->getLicensePrice( $license );
		$title     = get_the_title( $itemID );

		$cart_data[ $itemID ] = array(
			'pid'             => $itemID,
			'product_type'    => '',
			'product_name'    => $title,
			'quantity'        => true,
			'extra_gigs'      => [],
			'license'         => $product->getLicenseInfo( $license ),
			'price'           => $price,
			'role_discount'   => 0.0,
			'coupon'          => '',
			'coupon_discount' => 0,
			'files'           => [],
			'ID'              => $itemID,
			'post_title'      => $title,
			'prices'          => 0,
			'discount_amount' => 0.0,
		);

		$this->set( 'cart_data', $cart_data );
		$items   = $this->items;
		$items[] = $itemID;
		$items   = array_unique( $items );
		$this->set( 'items', $items );
		self::updateOrderItems( $cart_data, $this->oid, true );
		$this->save();
		self::recalculateTotal( $this->oid );
	}

	public function removeItem( $itemID ) {
		$cart_data = $this->cart_data;

		unset( $cart_data[ $itemID ] );

		$this->set( 'cart_data', $cart_data );
		$items = $this->items;
		unset( $items[ $itemID ] );
		$items = array_unique( $items );
		$this->set( 'items', $items );
		self::updateOrderItems( $cart_data, $this->oid, true );
		$this->save();
		self::recalculateTotal( $this->oid );
	}

	/**
	 * @param $id Order ID
	 * @param $data array['note' => '', ]
	 * @param string $type
	 *
	 * @return bool
	 */
	static function add_note( $id, $data, $type = 'messages' ) {
		global $wpdb, $current_user;

		$current_user = wp_get_current_user();

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$order_info = $wpdb->get_row( "select * from {$wpdb->prefix}ahm_orders where order_id='{$id}'" );

		if ( $current_user->ID != $order_info->uid && ! current_user_can( WPDMPP_MENU_ACCESS_CAP ) ) {
			return false;
		}

		$order_note = $order_info->order_notes;

		if ( ! isset( $data['by'] ) ) {
			$data['by'] = $current_user->ID == $order_info->uid ? 'Customer' : 'Seller';
		}

		$fromname = get_bloginfo( 'name' );
		$frommail = "no-reply@" . $_SERVER['HTTP_HOST'];

		$customer = get_user_by( 'id', $order_info->uid );

		//$customer
		// For Email
		$viewlink_customer = "<a class='button' style='display:block;margin:0;padding:10px 0 !important;' href='" . wpdmpp_orders_page( 'id=' . $id ) . "'>View Order</a>";
		$viewlink_admin    = "<a class='button' style='display:block;margin:0;padding:10px 0 !important;' href='" . admin_url( "/edit.php?post_type=wpdmpro&page=orders&task=vieworder&id={$id}" ) . "'>View Order</a>";
		$send_email        = isset( $data['email'] ) && $data['email'] == 0 ? 0 : 1;

		$data['note'] = wp_kses( $data['note'], array(
			'strong' => array(),
			'b'      => array(),
			'br'     => array(),
			'p'      => array(),
			'hr'     => array(),
			'a'      => array( 'href' => array(), 'title' => array() )
		) );
		$data['note'] = wpdm_escs( $data['note'] );

		if ( isset( $data['admin'] ) && $send_email ) {

			$message = '<strong>Note:</strong><div class="uibox" style="padding: 20px; background: #ffffff; border: 1px solid #d7dcea;border-radius: 4px;margin: 10px 0">' . wpautop( $data['note'] ) . $viewlink_admin . '</div>';

			$params = array(
				'subject'  => "New Note: Order# {$id}",
				'to_email' => get_option( "admin_email" ),
				'message'  => $message
			);
			Email::send( "default", $params );
		}
		if ( isset( $data['customer'] ) && $send_email ) {

			$message = '<strong>Note:</strong><div  class="uibox" style="padding: 20px; background: #ffffff; border: 1px solid #d7dcea;border-radius: 4px;margin: 10px 0">' . wpautop( $data['note'] ) . $viewlink_customer . '</div>';


			$params = array(
				'subject'  => "New Note: Order# {$id}",
				'to_email' => $customer->user_email,
				'message'  => $message
			);
			Email::send( "default", $params );
		}

		$order_note = maybe_unserialize( $order_note );

		if ( ! is_array( $order_note ) ) {
			$order_note = array();
		}

		$order_note[ $type ][ time() ] = $data;
		Order::Update( array( 'order_notes' => serialize( $order_note ) ), $id );

		return true;
	}

	/**
	 * @param $id
	 * @param bool $email_notify
	 * @param null $payment_method
	 *
	 * @return bool
	 */
	static function complete_order( $id, $email_notify = true, $payment_method = null ) {

		global $wpdb;

		//echo $id;
		if ( strpos( $id, "renew" ) ) {
			$id = explode( "_", $id );
			$id = $id[0];
		}
		$id        = sanitize_text_field( $id );
		$order_det = $wpdb->get_row( "select * from {$wpdb->prefix}ahm_orders where order_id='$id'" );
		if ( ! $order_det ) {
			return false;
		}

		wpdmpp_clear_user_cart( $order_det->uid );
		wpdmpp_empty_cart();

		$billing_info = unserialize( $order_det->billing_info );
		$buyer_email  = isset( $billing_info['order_email'] ) ? $billing_info['order_email'] : '';
		$name         = isset( $billing_info['first_name'] ) ? $billing_info['first_name'] : '';
		$settings     = get_option( '_wpdmpp_settings' );
		$logo         = isset( $settings['logo_url'] ) && $settings['logo_url'] != "" ? "<img src='{$settings['logo_url']}' alt='" . get_bloginfo( 'name' ) . "'/>" : get_bloginfo( 'name' );
		$expire_date  = get_wpdmpp_option( 'order_validity_period', 0, 'int' ) > 0 ? strtotime( "+" . get_wpdmpp_option( 'order_validity_period', 0, 'int' ) . " days" ) : 0;
		$auto_renew   = get_wpdmpp_option( 'auto_renew', 0, 'int' );

		$order_data = [
			'order_status'   => 'Completed',
			'payment_status' => 'Completed',
			'expire_date'    => $expire_date,
			'auto_renew'     => $auto_renew
		];

		if ( $order_det->order_status === 'Processing' ) {
			$order_data['date'] = time();
		}

		self::Update( $order_data, $id );

		if ( ! is_user_logged_in() ) {
			Session::set( 'guest_order', $id, 18000 );
			Session::set( 'order_email', $buyer_email, 18000 );
		} else {
			User::addCustomer( $order_det->uid );
			User::processActiveRoles( $order_det->uid );
		}

		$order_det->currency = maybe_unserialize( $order_det->currency );

		if ( $order_det->order_status == 'Expired' ) {
			$t = time();
			$wpdb->insert( "{$wpdb->prefix}ahm_order_renews", array(
				'order_id'        => $order_det->order_id,
				'total'        => $order_det->total,
				'subscription_id' => $order_det->trans_id,
				'date'            => $t
			) );
			//wpdmdd($wpdb->last_query);
			//\WPDMPP\Libs\Order::add_note($id, array('note'=>'Order Renewed Successfully <a onclick="window.open(\'?id='.$id.'&wpdminvoice=1&renew='.$t.'\',\'Invoice\',\'height=720, width = 750, toolbar=0\'); return false;" href="#" class="btn-invoice">Get Invoice</a>.','by'=>'Customer'));
			do_action( 'wpdmpp_order_renewed', $id );
		} else {
			//\WPDMPP\Libs\Order::add_note($id, array('note'=>'Order Status: Completed / Payment Status: Completed / Paid with: '.$order_det->payment_method,'by'=>'Customer'));
			do_action( 'wpdmpp_order_completed', $id );
		}

		//check and increase coupon usage
		$coupon                    = array();
		$coupon['coupon_code']     = $order_det->coupon_code;
		$coupon['coupon_discount'] = (float) $order_det->coupon_discount;
		if ( $coupon['coupon_discount'] > 0 ) {
			\WPDMPP\Libs\CouponCodes::increase_coupon_usage_count( $coupon );
		}

		//return if email notification set to false
		if ( $email_notify == false ) {
			return true;
		}

		// send email notifications
		$userid = $order_det->uid;

		if ( $userid && $buyer_email == '' ) {
			$user_info   = get_user_by( 'id', $userid );
			$name        = $user_info->display_name;
			$buyer_email = $user_info->user_email;
		}

		$params = array(
			'date'            => wp_date( get_option( 'date_format' ), time() ),
			'homeurl'         => home_url( '/' ),
			'sitename'        => get_bloginfo( 'name' ),
			'order_link'      => "<a href='" . wpdmpp_orders_page( 'id=' . $id ) . "'>" . wpdmpp_orders_page( 'id=' . $id ) . "</a>",
			'register_link'   => "<a href='" . wpdmpp_orders_page( 'orderid=' . $id ) . "'>" . wpdmpp_orders_page( 'orderid=' . $id ) . "</a>",
			'name'            => $name,
			'orderid'         => $id,
			'to_email'        => $buyer_email,
			'order_url'       => wpdmpp_orders_page( 'id=' . $id ),
			'guest_order_url' => wpdmpp_guest_order_page( 'id=' . $id ),
			'order_url_admin' => admin_url( 'edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $id ),
			'img_logo'        => $logo,
			'payment_method'  => str_replace( array( "Wpdm_", "WPDM_" ), "", $order_det->payment_method ),
			'order_total'     => $order_det->currency['sign'] . number_format( $order_det->total, 2 )
		);


		$items         = self::getOrderItems( $id );
		$th            = $allitems = "<table  class='email' style='width: 100%;border: 0;' cellpadding='0' cellspacing='0'><tr><th>Product Name</th><th>License</th><th style='width:80px;text-align:right'>Price</th></tr>";
		$seller_emails = [];
		foreach ( $items as $item ) {
			$product_type = wpdm_valueof( $item, 'product_type' );
			if ( $product_type !== 'dynamic' ) {
				$product                                = get_post( $item['pid'] );
				$udata                                  = get_userdata( $product->post_author );
				$seller_emails[ $product->post_author ] = $udata->user_email;
				$seller                                 = $product->post_author;
			} else {
				$seller_emails[0] = get_option( 'admin_email' );
				$seller           = 0;
			}
			//$license = maybe_unserialize($item['license']);
			//$license = is_array($license) && isset($license['info'], $license['info']['name']) ? $license['info']['name'] : " &mdash; ";
			$price                          = $order_det->currency['sign'] . wpdmpp_price_format( $item['price'], false, true );
			$_item                          = "<tr><td>" . WPDMPP()->cart->itemLink( $item, false ) . "</td><td>" . WPDMPP()->cart->itemInfo( $item, false ) . "</td><td style='width:80px;text-align:right'>{$price}</td></tr>";
			$product_by_seller[ $seller ][] = $_item;
			$allitems                       .= $_item;

		}
		$allitems .= "<tr><th colspan='2' style='text-align: right'>" . __( 'Cart Total', WPDM_TEXT_DOMAIN ) . "</th><th style='text-align: right'>" . wpdmpp_price_format( WPDMPP()->order->itemsCost( $items ) ) . "</th></tr>";
		if ( (double) $order_det->coupon_discount > 0 ) {
			$allitems .= "<tr><th colspan='2' style='text-align: right'>" . __( 'Coupon Discount', WPDM_TEXT_DOMAIN ) . "</th><th style='text-align: right'>-" . wpdmpp_price_format( $order_det->coupon_discount, true, true ) . "</th></tr>";
		}
		if ( (double) $order_det->tax > 0 ) {
			$allitems .= "<tr><th colspan='2' style='text-align: right'>" . __( 'Tax', WPDM_TEXT_DOMAIN ) . "</th><th style='text-align: right'>" . wpdmpp_price_format( $order_det->tax, true, true ) . "</th></tr>";
		}
		$allitems .= "<tr><th colspan='2' style='text-align: right'>" . __( 'Order Total', WPDM_TEXT_DOMAIN ) . "</th><th style='text-align: right'>" . wpdmpp_price_format( $order_det->total, true, true ) . "</th></tr>";

		$allitems        .= "</table>";
		$params['items'] = $allitems;

		// to buyer
		if ( ! $userid ) {
			Email::send( "purchase-confirmation-guest", $params );
		} else {
			Email::send( "purchase-confirmation", $params );
		}


		// to admin
		$params['to_email'] = get_option( 'admin_email' );
		Email::send( "sale-notification", $params );

		//to sellers
		$adb_page_id = get_option( '__wpdm_author_dashboard', 0 );
		$page        = get_permalink( $adb_page_id );
		$url         = add_query_arg( [ 'adb_page' => 'sales' ], $page );
		if ( is_array( $seller_emails ) ) {
			foreach ( $seller_emails as $seid => $seller_email ) {
				if ( get_option( 'admin_email' ) != $seller_email ) {
					$user                       = get_user_by( 'email', $seller_email );
					$prods                      = $th . implode( "", $product_by_seller[ $seid ] ) . "</table>";
					$params['items']            = $prods;
					$params['order_url_seller'] = $url;
					$params['name']             = $user->display_name;
					$params['to_email']         = $seller_email;
					$params['to_seller']        = 1;
					Email::send( "sale-notification-seller", $params );
				}
			}
		}

	}

	/**
	 * @param $id
	 * @param $sub_id
	 * @param $email_notify
	 * @param $payment_method
	 * @param $timestamp
	 *
	 * @return void
	 */
	static function renewOrder( $id, $sub_id = '', $email_notify = true, $timestamp = null, $invoice = null ) {

		global $wpdb;
		$sub_id = sanitize_text_field( $sub_id );
		$sql    = "select * from {$wpdb->prefix}ahm_orders where order_id='$id'";
		if ( $sub_id != '' ) {
			$sql .= " or trans_id='$sub_id'";
		}
		$order_det    = $wpdb->get_row( $sql );
		$billing_info = unserialize( $order_det->billing_info );
		$buyer_email  = isset( $billing_info['order_email'] ) ? $billing_info['order_email'] : '';
		$name         = "";
		$settings     = get_option( '_wpdmpp_settings' );
		$logo         = isset( $settings['logo_url'] ) && $settings['logo_url'] != "" ? "<img src='{$settings['logo_url']}' alt='" . get_bloginfo( 'name' ) . "'/>" : get_bloginfo( 'name' );
		$expire_date  = get_wpdmpp_option( 'order_validity_period', 365 ) > 0 ? strtotime( "+" . get_wpdmpp_option( 'order_validity_period', 0 ) . " days" ) : 0;
		if ( $timestamp ) {
			$expire_date = $timestamp + get_wpdmpp_option( 'order_validity_period', 365 ) * 86400;
		}
		self::Update( array(
			'order_status'   => 'Completed',
			'payment_status' => 'Completed',
			'auto_renew'     => 1,
			'expire_date'    => $expire_date
		), $order_det->order_id );
		$timestamp = $timestamp ? $timestamp : time();
		$renewEntry = [
			'order_id'        => $order_det->order_id,
			'total'        => $order_det->total,
			'subscription_id' => $sub_id,
			'date'            => $timestamp
		];
		if($invoice) $renewEntry['ipn'] = $invoice;
		$wpdb->insert( "{$wpdb->prefix}ahm_order_renews", $renewEntry );

		do_action( 'wpdmpp_order_renewed', $id );


		//return if email notification set to false
		if ( $email_notify == false ) {
			return;
		}

		// send email notifications
		$userid = $order_det->uid;

		if ( $userid && $buyer_email == '' ) {
			$user_info   = get_userdata( $userid );
			$name        = $user_info->user_login;
			$buyer_email = $user_info->user_email;
		}

		$params = array(
			'date'            => wp_date( get_option( 'date_format' ), time() ),
			'homeurl'         => home_url( '/' ),
			'sitename'        => get_bloginfo( 'name' ),
			'order_link'      => "<a href='" . wpdmpp_orders_page( 'id=' . $id ) . "'>" . wpdmpp_orders_page( 'id=' . $id ) . "</a>",
			'register_link'   => "<a href='" . wpdmpp_orders_page( 'orderid=' . $id ) . "'>" . wpdmpp_orders_page( 'orderid=' . $id ) . "</a>",
			'name'            => $name,
			'orderid'         => $id,
			'to_email'        => $buyer_email,
			'order_url'       => wpdmpp_orders_page( 'id=' . $id ),
			'order_url_admin' => admin_url( 'edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $id ),
			'img_logo'        => $logo
		);


		// to buyer
		Email::send( "renew-confirmation", $params );


		$items    = Order::getOrderItems( $id );
		$allitems = "";
		foreach ( $items as $item ) {
			$product                                      = get_post( $item['pid'] );
			$udata                                        = get_userdata( $product->post_author );
			$seller_emails[ $product->post_author ]       = $udata->user_email;
			$item                                         = "<a href='" . get_permalink( $product->ID ) . "'>{$product->post_title}</a>";
			$product_by_seller[ $product->post_author ][] = $item;
			$allitems                                     .= $item . "<br/>";
		}

		// to admin
		/*
		$params['items'] = $allitems;
		$params['to_email'] = get_option('admin_email');
		\WPDM\__\Email::send("sale-notification", $params);

		//to sellers
		if(is_array($seller_emails)) {
			foreach ($seller_emails as $seid => $seller_email) {
				if(get_option('admin_email') != $seller_email) {
					$prods = implode("<br/>", $product_by_seller[$seid]);
					$params['items'] = $prods;
					$params['to_email'] = $seller_email;
					\WPDM\__\Email::send("sale-notification", $params);
				}
			}
		}
		*/


	}

	/**
	 * @param $id
	 */
	static function expireOrder( $id, $email_notify = true ) {

		$order = new Order( $id );

		if ( $order->order_status == 'Expired' ) {
			return;
		}

		$order->set( 'order_status', 'Expired' );
		$order->set( 'payment_status', 'Expired' );
		if ( $order->expire_date == 0 ) {
			$expire_date = $order->date + ( get_wpdmpp_option( 'order_validity_period', 365 ) * 86400 );
			$order->set( 'expire_date', $expire_date );
		}
		//$_items = maybe_unserialize($order->cart_data);
		$_items = Order::GetOrderItems( $id );
		$order->save();
		$items = "<ul>";
		//wpdmdd( $_items );
		foreach ( $_items as $item ) {
			$item    = "<li>" . WPDMPP()->cart->itemLink( $item, false ) . WPDMPP()->cart->itemInfo( $item, false ). "</li>";
			$items  .= $item;
			$product = new Product( wpdm_valueof( $item, 'pid' ) );
			$product->removeRole( $order->uid );
		}
		$items .= "</ul>";


		if ( $email_notify ) {
			$user     = get_user_by( 'id', $order->uid );
			$settings = get_option( '_wpdmpp_settings' );
			$settings = maybe_unserialize( $settings );
			$logo     = isset( $settings['logo_url'] ) && $settings['logo_url'] != "" ? "<img src='{$settings['logo_url']}' alt='" . get_bloginfo( 'name' ) . "'/>" : get_bloginfo( 'name' );
			$params   = array(
				'date'        => wp_date( get_option( 'date_format' ), time() ),
				'expire_date' => wp_date( get_option( 'date_format' ), $order->expire_date ),
				'homeurl'     => home_url( '/' ),
				'sitename'    => get_bloginfo( 'name' ),
				'name'        => $user->display_name,
				'orderid'     => $id,
				'order_items' => $items,
				'to_email'    => $user->user_email,
				'order_url'   => wpdmpp_orders_page( 'id=' . $id ),
				'img_logo'    => $logo
			);
			Email::send( "order-expire", $params );
		}
	}

	/**
	 * @param $id
	 */
	static function cancelOrder( $id ) {
		self::update( array( 'order_status' => 'Cancelled', 'payment_status' => 'Cancelled' ), $id );
	}

	/**
	 * @param $cart_data
	 * @param $id
	 */
	static function updateOrderItems( $cart_data, $id, $admin = false ) {
		global $wpdb;
		$cart_data = maybe_unserialize( $cart_data );
		$o         = new Order( $id );
		if ( $o->order_status !== 'Processing' && ! $admin ) {
			return false;
		}
		$time = $o->date;
		$wpdb->query( "delete from {$wpdb->prefix}ahm_order_items where oid='$id'" );
		if ( ! empty( $cart_data ) ) {
			foreach ( $cart_data as $pid => $cdt ) {

				$product_type = wpdm_valueof( $cdt, 'product_type' );

				$coupon        = wpdm_valueof( $cdt, 'coupon' );
				$coupon_amount = (double) wpdm_valueof( $cdt, 'coupon_amount', 0 );
				$role_disc     = (double) wpdm_valueof( $cdt, 'discount_amount', 0 );
				$site_comm     = 0;
				$product_name  = wpdm_valueof( $cdt, 'product_name' );
				//$sid = Seller ID
				$sid = $product_type === 'dynamic' ? 0 : get_post( $pid )->post_author;
				//$cid = Customer ID
				$cid = $o->uid;
				$wpdb->insert( "{$wpdb->prefix}ahm_order_items", array(
					'oid'             => $id,
					'pid'             => $pid,
					'product_type'    => $product_type,
					'product_name'    => $product_name,
					'license'         => serialize( $cdt['license'] ),
					'quantity'        => $cdt['quantity'],
					'price'           => $cdt['price'],
					'extra_gigs'      => serialize( $cdt['extra_gigs'] ),
					'coupon'          => $coupon,
					'coupon_discount' => floatval( $coupon_amount ),
					'role_discount'   => $role_disc,
					'site_commission' => $site_comm,
					'date'            => wp_date( "Y-m-d H:m:s", $time ),
					'year'            => wp_date( 'Y' ),
					'month'           => wp_date( 'm' ),
					'day'             => wp_date( 'd' ),
					'sid'             => $sid,
					'cid'             => $cid
				) );

			}
		}
	}

	static function getOrderItems( $id ) {
		global $wpdb;
		$items = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_order_items where oid='{$id}'", ARRAY_A );

		return is_array( $items ) ? $items : array();
	}

	/**
	 * @param $item
	 * @param false $format
	 *
	 * @return double|string
	 */
	function itemCost( $item, $format = false ) {
		$product   = new Product( wpdm_valueof( $item, 'pid' ), wpdm_valueof( $item, 'product_type' ) );
		$gigs_cost = $product->gigsCost( wpdm_valueof( $item, 'extra_gigs' ) );
		$cost      = (double) wpdm_valueof( $item, 'price' ) + $gigs_cost;
		$cost      = $cost * wpdm_valueof( $item, 'quantity', [ 'default' => 1, 'validate' => 'int' ] );
		//$role_discount_percent = $item['role_discount'];
		//$cost -= ($cost*$role_discount_percent/100);
		$cost -= (double) wpdm_valueof( $item, 'role_discount' );
		$cost -= (double) wpdm_valueof( $item, 'coupon_discount' );

		return wpdmpp_price_format( $cost, $format, $format );
	}

	function itemsCost( $items ) {
		$total = 0;
		foreach ( $items as $item ) {
			$total += $this->itemCost( $item );
		}

		return $total;
	}

	function calcOrderTotal( $oid ) {
		global $wpdb;
		global $current_user;

		$current_user = wp_get_current_user();

		$role      = is_user_logged_in() && isset( $current_user->roles[0] ) ? $current_user->roles[0] : 'guest';
		$total     = 0;
		$orderdata = $this->GetOrder( $oid );
		if ( ! $orderdata ) {
			return 0;
		}
		$order_items = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_order_items where oid='{$oid}'", ARRAY_A );;
		$discount1 = 0;

		if ( is_array( $order_items ) ) {

			foreach ( $order_items as $item ) {
				$prices = 0;

				$pid = $item['pid'];
				//$item['variation'] = isset($item['variation']) ? maybe_unserialize($item['variation']) : null;
				$item['variations'] = isset( $item['variations'] ) ? maybe_unserialize( $item['variations'] ) : null;
				//wpdmdd($item);
				//$variation = get_post_meta($pid,'__wpdm_variation', true);
				/*if(isset($item['variation']) && is_array($item['variation']) && is_array($variation)){
					foreach($variation as $key=>$value){
						foreach($value as $optionkey=>$optionvalue){
							if($optionkey!="vname"){
								foreach($item['variation'] as $var){
									if($var==$optionkey){
										$prices+=(double)$optionvalue['option_price'];
									}
								}
							}
						}
					}
				}*/
				if ( is_array( $item['variations'] ) ) {
					foreach ( $item['variations'] as $vari ) {
						$prices += (double) $vari['price'];
					}
				}
				if ( isset( $item['coupon'] ) && trim( $item['coupon'] ) != '' ) {
					$valid_coupon  = wpdmpp_check_coupon( $pid, $item['coupon'] );
					$item['price'] = (double) $item['price'];
					if ( $valid_coupon != 0 ) {
						$item_total = ( ( $item['price'] + $prices ) * $item['quantity'] ) - $valid_coupon; //(($item['price']+$prices)*$item['quantity']*($valid_coupon/100));
						$total      += $item_total;
					} else {
						$item_total = ( ( $item['price'] + $prices ) * $item['quantity'] );
						$total      += $item_total;
					}
				} else {
					$item_total = ( ( $item['price'] + $prices ) * $item['quantity'] );
					$total      += $item_total;

				}

				//calculate role discount

				$role_discount = wpdmpp_role_discount( $pid );

				$discount1 += ( ( $item_total * $role_discount ) / 100 );
				//if($role_discount > 0)
				//    Session::set('role_discount_'.$oid, true);

			}
		}

		$total = apply_filters( 'wpdmpp_cart_subtotal', $total );

		$subtotal = $total;


		$tax_summery = $this->wpdmpp_calculate_tax();

		$tax = 0;
		if ( count( $tax_summery ) > 0 ) {
			foreach ( $tax_summery as $taxrow ) {
				$tax += $taxrow['rates'];
			}
		}
		$total += $tax;


		$total = $total - $discount1;

		return $total;
	}

	function wpdmpp_calculate_tax( $oid = null ) {
		$taxr        = array();
		$settings    = maybe_unserialize( get_option( '_wpdmpp_settings' ) );
		$tax_summery = array();
		if ( Session::get( 'orderid' ) ) {
			$order_info = $this->GetOrder( Session::get( 'orderid' ) );
		}
		if ( $oid ) {
			$order_info = $this->GetOrder( $oid );
		}
		$bdata      = unserialize( $order_info->billing_info );
		$cart_items = null;
		if ( Session::get( 'orderid' ) ) {
			$cart_items = $this->getOrderItems( Session::get( 'orderid' ) );
		}

		if ( isset( $settings['tax']['enable'] ) && $settings['tax']['enable'] == 1 ) {
			if ( is_array( $cart_items ) ) {
				foreach ( $cart_items as $item ) {
					$taxes      = 0;
					$tax_status = "";
					$tax_class  = "";
					$tax_status = get_post_meta( $item['pid'], '__wpdm_taxable', true );

					$price = wpdmpp_product_price( $item['pid'] );

					if ( $tax_status == "taxable" ) {

						if ( $settings['tax']['tax_rate'] ) {
							$temp_class = "";
							$temp_label = "";
							$taxes      = 0;
							foreach ( $settings['tax']['tax_rate'] as $key => $rate ) {

								if ( $rate['tax_class'] == $tax_class ) {
									$taxes = 0;
									if ( in_array( $bdata['shippingin']['country'], $rate['country'] ) ) {

										$taxes = ( ( $rate['rate'] * $price ) / 100 );
										if ( $rate['shipping'] == 1 ) {
											$taxes += ( ( $rate['rate'] * $order_info->shipping_cost ) / 100 );
										}
										//product wise tax
										$taxr['label'][ $item['pid'] ][] = $rate['label'];
										$taxr['rate'][ $item['pid'] ]    += $taxes;
										//class wise tax
										$tax_summery[ $key ]['label'] = $rate['label'];
										$tax_summery[ $key ]['rates'] += $taxes;
									}
								}
							}
						}
					}
				}
			}
		}

		return $tax_summery;
	}

	function Load() {

	}

	function getOrder( $id ) {
		global $wpdb;
		$id = sanitize_text_field( $id );
		if ( $id == '' ) {
			return false;
		}
		if ( strpos( $id, "renew" ) ) {
			$id = explode( "_", $id );
			$id = $id[0];
		}
		$this->oid  = $id;
		$this->ID   = $id;
		$id         = sanitize_text_field( $id );
		$id         = esc_sql( $id );
		$order_data = $wpdb->get_row( "select * from {$wpdb->prefix}ahm_orders where order_id='$id' or trans_id='$id'" );
		$order_data = apply_filters( "wpdmpp_get_order", $order_data );

		return $order_data;
	}

	function getOrderByTID($tid) {
		global $wpdb;
		if(!$tid) return false;
		$order = $wpdb->get_row( "select * from {$wpdb->prefix}ahm_orders where trans_id='$tid'" );
		$order = (array) $order;
		foreach ( $order as $key => $val ) {
			$this->$key = maybe_unserialize( $val );
			if ( $key != 'order_id' ) {
				$this->orderData[ $key ] = maybe_unserialize( $val );
			}
		}
		return $this;
	}

	function getOrders( $user_id, $completed_only = false ) {
		global $wpdb;
		$user_id = (int) $user_id;
		$os_cond = ( $completed_only === true ) ? " and ( order_status = 'Completed' or order_status = 'Expired' ) " : '';

		return $wpdb->get_results( "select * from {$wpdb->prefix}ahm_orders where uid='$user_id' {$os_cond} order by `order_status` desc, `date` desc" );
	}

	function getAllOrders( $qry = "", $s = 0, $l = 20 ) {
		global $wpdb;
		return $wpdb->get_results( "select * from {$wpdb->prefix}ahm_orders $qry limit $s,$l" );
	}

	function getAllRenews( $qry = "", $s = 0, $l = 20 ) {
		global $wpdb;
		//return $wpdb->get_results( "select o.*, orn.date as renew_date from {$wpdb->prefix}ahm_orders ors, LEFT JOIN {$wpdb->prefix}ahm_order_renews orn  order by ID desc limit $s,$l" );
		return $wpdb->get_results( "select o.*, n.date as renew_date from {$wpdb->prefix}ahm_order_renews n LEFT JOIN {$wpdb->prefix}ahm_orders o ON o.order_id = n.order_id order by ID desc limit $s,$l" );
	}

	function totalRenews() {
		global $wpdb;
		return $wpdb->get_var('select count(*) from {$wpdb->prefix}ahm_order_renews');
	}

	public static function getPurchasedItems( $uid = null ) {
		global $wpdb;
		$current_user = wp_get_current_user();
		if ( ! $uid && is_user_logged_in() ) {
			$uid = $current_user->ID;
		}
		if ( ! $uid ) {
			return [];
		}
		$purchased_items = $wpdb->get_results( "select oi.*, o.order_status, o.date as order_date from {$wpdb->prefix}ahm_order_items oi,{$wpdb->prefix}ahm_orders o where o.order_id = oi.oid and o.uid = {$uid} and o.order_status IN ('Expired', 'Completed') order by `order_date` desc" );
		foreach ( $purchased_items as &$item ) {
			$files = get_post_meta( $item->pid, '__wpdm_files', true );
			$item->product_name = $item->product_name ?: get_the_title( $item->pid );
			if ( is_array( $files ) ) {
				foreach ( $files as $id => $index ) {
					$index = basename( $index );
					if ( $item->order_status === 'Completed' ) {
						$item->download_url[ $index ] = WPDMPremiumPackage::customerDownloadURL( $item->pid, $item->oid ) . "&ind={$id}";
					}
				}
			}
		}

		return $purchased_items;
	}

	public function getPurchasedFiles( $uid = null ) {
		global $wpdb;
		$current_user = wp_get_current_user();
		if ( ! $uid && is_user_logged_in() ) {
			$uid = $current_user->ID;
		}
		if ( ! $uid ) {
			return [];
		}
		$now             = time();
		$purchased_items = $wpdb->get_results( "select oi.*, o.order_status, o.date as order_date from {$wpdb->prefix}ahm_order_items oi,{$wpdb->prefix}ahm_orders o where o.order_id = oi.oid and o.uid = {$uid} and o.order_status = 'Completed' and o.expire_date > $now order by `order_date` desc" );
		$purchased_files = [];
		foreach ( $purchased_items as $item ) {
			$files = get_post_meta( $item->pid, '__wpdm_files', true );
			if ( is_array( $files ) ) {
				foreach ( $files as $id => $file_path ) {
					$file                     = basename( $file_path );
					$purchased_files[ $file ] = WPDM()->fileSystem->locateFile( $file_path );
				}
			}
		}

		return $purchased_files;
	}

	/**
	 * add to cart using form submit
	 */
	static function recalculateItemPrice( $cart_data ) {

		global $wpdb, $post, $wp_query, $current_user;

		$current_user = wp_get_current_user();
		$sales_price  = 0;

		foreach ( $cart_data as $pid => $item ) {

			$q      = 1;
			$sfiles = wpdm_valueof( $item, 'files' );
			if ( ! is_array( $sfiles ) ) {
				$sfiles = explode( ',', $sfiles );
			}
			$license        = wpdm_valueof( $item, 'license' );
			$license        = is_array( $license ) ? wpdm_valueof( $license, 'id' ) : $license;
			$license_req    = get_post_meta( $pid, "__wpdm_enable_license", true );
			$license_prices = get_post_meta( $pid, "__wpdm_license", true );
			$license_prices = maybe_unserialize( $license_prices );

			$pre_licenses = wpdmpp_get_licenses();
			$files        = array();
			$fileinfo     = get_post_meta( $pid, '__wpdm_fileinfo', true );
			$fileinfo     = maybe_unserialize( $fileinfo );
			$files_price  = 0;

			if ( count( $sfiles ) > 0 && $sfiles[0] != '' && is_array( $fileinfo ) ) {
				foreach ( $sfiles as $findx ) {
					$files[ $findx ] = $fileinfo[ $findx ]['price'];
					if ( $license_req == 1 && $license != '' && $fileinfo[ $findx ]['license_price'][ $license ] > 0 ) {
						$files[ $findx ] = $fileinfo[ $findx ]['license_price'][ $license ];
					}
				}
			}
			if ( $q < 1 ) {
				$q = 1;
			}

			$base_price = wpdmpp_product_price( $pid );
			if ( $license_req == 1 && isset( $license_prices[ $license ]['price'] ) && $license_prices[ $license ]['price'] > 0 ) {
				$base_price = $license_prices[ $license ]['price'];
			}


			if ( (int) get_post_meta( $pid, '__wpdm_pay_as_you_want', true ) == 0 ) {

				// If product id already exist ( Product already added to cart )
				if ( array_key_exists( $pid, $cart_data ) ) {

					if ( ! isset( $cart_data['variation'] ) || $cart_data['variation'] == '' ) {
						$cart_data['variation'] = array();
					}

					if ( isset( $cart_data[ $pid ]['files'] ) ) {
						$cart_data[ $pid ]['files'] = maybe_unserialize( $cart_data[ $pid ]['files'] );
						$cart_data[ $pid ]['files'] += $files;
					} else {
						$cart_data[ $pid ]['files'] = $files;
					}
					$files_price = array_sum( $cart_data[ $pid ]['files'] );
					//$cart_data[$pid]['quantity'] += $q;
					if ( ! isset( $cart_data[ $pid ]['price'] ) || $cart_data[ $pid ]['price'] == 0 ) {
						$cart_data[ $pid ]['price'] = $files_price;
					} else {
						$cart_data[ $pid ]['price'] = $cart_data[ $pid ]['price'] > $files_price && $files_price > 0 ? $files_price : $cart_data[ $pid ]['price'];
					}

				} else {
					// product id does not exist in cart. Add to cart as new item
					$variation         = isset( $item['variation'] ) ? wpdm_sanitize_array( $item['variation'] ) : array();
					$files_price       = array_sum( $files );
					$base_price        = $files_price > 0 && $files_price < $base_price ? $files_price : $base_price;
					$cart_data[ $pid ] = array(
						'quantity'  => $q,
						'variation' => $variation,
						'price'     => $base_price,
						'files'     => $files
					);

				}

			}

			$lic_info                     = isset( $pre_licenses[ $license ] ) ? $pre_licenses[ $license ] : '';
			$license_det                  = array( 'id' => $license, 'info' => $lic_info );
			$cart_data[ $pid ]['license'] = $license_det;

		}

		return $cart_data;

	}

	public static function recalculateTotal( $oid ) {
		global $wpdb;
		$total     = 0;
		$orderdata = $wpdb->get_row( "select * from {$wpdb->prefix}ahm_orders where order_id='$oid' or trans_id='$oid'" );
		if ( current_user_can( 'manage_options' ) || $orderdata->uid === get_current_user_id() ) {
			$cart_items = unserialize( $orderdata->cart_data );
			$discount1  = 0;

			if ( is_array( $cart_items ) ) {

				$cart_items = self::recalculateItemPrice( $cart_items );

				foreach ( $cart_items as $pid => $item ) {
					$prices = 0;

					//$license = isset($item['license']) ? maybe_unserialize($item['license']) : null;
					//$license = is_array($license) && isset($license['id']) ? $license['id'] : '';
					//$item['price'] = wpdmpp_product_price($pid, $license);
					//wpdmdd($item);

					$variation = get_post_meta( $pid, '__wpdm_variation', true );
					if ( isset( $item['variation'] ) && is_array( $item['variation'] ) && is_array( $variation ) ) {
						foreach ( $variation as $key => $value ) {
							foreach ( $value as $optionkey => $optionvalue ) {
								if ( $optionkey != "vname" ) {
									foreach ( $item['variation'] as $var ) {
										if ( $var == $optionkey ) {
											$prices += (double) $optionvalue['option_price'];
										}
									}
								}
							}
						}
					}
					if ( isset( $item['coupon'] ) && trim( $item['coupon'] ) != '' ) {
						$valid_coupon = wpdmpp_check_coupon( $pid, $item['coupon'] );

						if ( $valid_coupon != 0 ) {
							$item_total = ( ( $item['price'] + $prices ) * $item['quantity'] ) - $valid_coupon; //(($item['price']+$prices)*$item['quantity']*($valid_coupon/100));
							$total      += $item_total;
						} else {
							$item_total = ( ( $item['price'] + $prices ) * $item['quantity'] );
							$total      += $item_total;
						}
					} else {
						$item_total = ( ( $item['price'] + $prices ) * $item['quantity'] );
						$total      += $item_total;

					}

					//calculate role discount

					$role_discount = wpdmpp_role_discount( $pid );

					$discount1 += ( ( $item_total * $role_discount ) / 100 );
					//if($role_discount > 0)
					//    Session::set('role_discount_'.$oid, true);
					//wpdmprecho($item_total);
				}

			}
			self::update( array( 'total' => $total ), $oid );
		} else {
			$total = $orderdata->total;
		}
		do_action( "wpdmpp_order_recalculated", $oid, $total );

		return $total;
	}

	function totalOrders( $qry = '' ) {
		global $wpdb;

		return $wpdb->get_var( "select count(*) from {$wpdb->prefix}ahm_orders $qry" );
	}

	static function userOrderDetails( $order_id = null ) {
		global $wpdb, $sap, $wpdmpp_settings;

		$current_user = wp_get_current_user();

		$order_notes = '';
		if ( ! wpdm_query_var( 'udb_page' ) || ! $order_id ) {
			$order_id = wpdm_query_var( 'id' );
		}
		if ( $order_id ) {
			$orderObj     = new \WPDMPP\Libs\Order( $order_id );
			$orderurl     = get_permalink( get_the_ID() );
			$loginurl     = home_url( "/wp-login.php?redirect_to=" . urlencode( $orderurl ) );
			$csign        = wpdmpp_currency_sign();
			$csign_before = wpdmpp_currency_sign_position() == 'before' ? $csign : '';
			$csign_after  = wpdmpp_currency_sign_position() == 'after' ? $csign : '';
			$link         = wpdm_query_var( 'udb_page' ) ? get_permalink() . "?udb_page=purchases/" : get_permalink();
			$o            = $orderObj;
			$order        = $orderObj->getOrder( $order_id );
			$extbtns      = "";
			$extbtns      = apply_filters( "wpdmpp_order_details_frontend", $extbtns, $order );

			//Check order status
			if ( $order->expire_date == 0 && get_wpdmpp_option( 'order_validity_period', 365 ) > 0 ) {
				$expire_date = $order->date + ( get_wpdmpp_option( 'order_validity_period', 365 ) * 86400 );
				$orderObj->set( 'expire_date', $expire_date );
				if ( time() > $expire_date ) {
					$orderObj->set( 'order_status', 'Expired' );
					$orderObj->set( 'payment_status', 'Expired' );
					$order->order_status   = 'Expired';
					$order->payment_status = 'Expired';
				}
				$orderObj->save();
			}

			$date        = wp_date( "Y-m-d h:i a", $order->date );
			$items       = maybe_unserialize( $order->items );
			$expire_date = $order->expire_date;


			$renews = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_order_renews where order_id='" . esc_sql( $orderObj->oid ) . "'" );

			if ( $order->uid == 0 ) {
				$order->uid = $current_user->ID;
				$o->update( array( 'uid' => $current_user->ID ), $order->order_id );
			}

			if ( $order->uid == $current_user->ID ) {

				$order->currency = maybe_unserialize( $order->currency );
				$csign           = isset( $order->currency['sign'] ) ? $order->currency['sign'] : '$';
				$csign_before    = wpdmpp_currency_sign_position() == 'before' ? $csign : '';
				$csign_after     = wpdmpp_currency_sign_position() == 'after' ? $csign : '';
				$cart_data       = maybe_unserialize( $order->cart_data );
				$items           = \WPDMPP\Libs\Order::GetOrderItems( $order->order_id );

				if ( is_array( $items ) && count( $items ) == 0 ) {
					foreach ( $cart_data as $pid => $noi ) {
						$newi = get_posts( array(
							'post_type'  => 'wpdmpro',
							'meta_key'   => '__wpdm_legacy_id',
							'meta_value' => $pid
						) );
						if ( is_array( $newi ) && count( $newi ) > 0 ) {
							$new_cart_data[ $newi[0]->ID ] = array(
								"quantity"  => $noi,
								"variation" => "",
								"price"     => get_post_meta( $newi[0]->ID, "__wpdm_base_price", true )
							);
							$new_order_items[]             = $newi[0]->ID;
						}
					}

					\WPDMPP\Libs\Order::Update( array(
						'cart_data' => serialize( $new_cart_data ),
						'items'     => serialize( $new_order_items )
					), $order->order_id );
					\WPDMPP\Libs\Order::UpdateOrderItems( $new_cart_data, $order->order_id );
					$items = \WPDMPP\Libs\Order::GetOrderItems( $order->order_id );
				}

				$order->title = $order->title ? $order->title : sprintf( __( 'Order # %s', 'wpdm-premium-packages' ), $order->order_id );

				$colspan         = 6;
				$coupon_discount = $role_discount = 0;
				foreach ( $items as $item ) {
					$coupon_discount += $item['coupon_discount'];
					$role_discount   += $item['role_discount'];
				}
				if ( $coupon_discount == 0 ) {
					$colspan --;
				}
				if ( $role_discount == 0 ) {
					$colspan --;
				}
				if ( $order->order_status !== 'Completed' ) {
					$colspan --;
				}

				include wpdm_tpl_path( 'partials/user-order-details.php', WPDMPP_TPL_DIR );
			} else {
				Messages::error( __( 'Order does not belong to you!', 'wpdm-premium-packages' ) );
			}
		} else {
			Messages::error( __( 'Invalid Order ID!', 'wpdm-premium-packages' ) );
		}
	}

	static function itemsTable( $order_id ) {
		$items     = self::GetOrderItems( $order_id );
		$itemTable = "<table  class='email' style='width: 100%;border: 0;margin-top: 15px' cellpadding='0' cellspacing='0'><tr><th>Product Name</th><th>License</th><th style='width:80px;text-align:right'>Price</th></tr>";
		foreach ( $items as $item ) {
			$product   = get_post( $item['pid'] );
			$license   = maybe_unserialize( $item['license'] );
			$license   = is_array( $license ) && isset( $license['info'], $license['info']['name'] ) ? $license['info']['name'] : " &mdash; ";
			$price     = wpdmpp_price_format( $item['price'] );
			$_item     = "<tr><td><a href='" . get_permalink( $product->ID ) . "'>{$product->post_title}</a></td><td>{$license}</td><td align='right' style='width:80px;text-align:right'>{$price}</td></tr>";
			$itemTable .= $_item;
		}
		$itemTable .= "</table>";

		return $itemTable;
	}

	function delete( $id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->wpdmpp_orders, [ 'order_id' => $id ] );
		$wpdb->delete( $wpdb->wpdmpp_order_items, [ 'oid' => $id ] );
	}

	static function getMeta( $orderID, $metaKey ) {
		global $wpdb;
		$orderID   = esc_sql( $orderID );
		$meta_data = $wpdb->get_var( "select meta_data from {$wpdb->prefix}ahm_orders where order_id='$orderID'" );
		$meta_data = json_decode( $meta_data, true );
		$meta_data = $meta_data ? $meta_data : [];

		return wpdm_valueof( $meta_data, $metaKey );
	}

	static function updateMeta( $orderID, $metaKey, $metaValue ) {
		global $wpdb;
		$orderID               = esc_sql( $orderID );
		$meta_data             = $wpdb->get_var( "select meta_data from {$wpdb->prefix}ahm_orders where order_id='$orderID'" );
		$meta_data             = json_decode( $meta_data, true );
		$meta_data[ $metaKey ] = $metaValue;
		$meta_data             = $meta_data ? $meta_data : [];
		$wpdb->update( "{$wpdb->prefix}ahm_orders", [ 'meta_data' => json_encode( $meta_data ) ], [ 'order_id' => $orderID ] );
	}

	static function sendConfirmationEmail( $id ) {
		global $wpdb;

		$id        = sanitize_text_field( $id );
		$order_det = $wpdb->get_row( "select * from {$wpdb->prefix}ahm_orders where order_id='$id'" );
		if ( ! $order_det ) {
			return false;
		}

		if ( ! $order_det->uid ) {
			$billing_info = unserialize( $order_det->billing_info );
			$buyer_email  = isset( $billing_info['order_email'] ) ? $billing_info['order_email'] : '';
			$name         = isset( $billing_info['first_name'] ) ? $billing_info['first_name'] : '';
		} else {
			$user        = get_user_by( 'id', $order_det->uid );
			$buyer_email = $user->user_email;
			$name        = $user->display_name;
		}

		$order_det->currency = maybe_unserialize( $order_det->currency );

		$settings = get_option( '_wpdmpp_settings' );
		$logo     = isset( $settings['logo_url'] ) && $settings['logo_url'] != "" ? "<img src='{$settings['logo_url']}' alt='" . get_bloginfo( 'name' ) . "'/>" : get_bloginfo( 'name' );


		$params = array(
			'date'            => wp_date( get_option( 'date_format' ), time() ),
			'homeurl'         => home_url( '/' ),
			'sitename'        => get_bloginfo( 'name' ),
			'order_link'      => "<a href='" . wpdmpp_orders_page( 'id=' . $id ) . "'>" . wpdmpp_orders_page( 'id=' . $id ) . "</a>",
			'register_link'   => "<a href='" . wpdmpp_orders_page( 'orderid=' . $id ) . "'>" . wpdmpp_orders_page( 'orderid=' . $id ) . "</a>",
			'name'            => $name,
			'orderid'         => $id,
			'to_email'        => $buyer_email,
			'order_url'       => wpdmpp_orders_page( 'id=' . $id ),
			'guest_order_url' => wpdmpp_guest_order_page( 'id=' . $id ),
			'order_url_admin' => admin_url( 'edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $id ),
			'img_logo'        => $logo,
			'payment_method'  => str_replace( array( "Wpdm_", "WPDM_" ), "", $order_det->payment_method ),
			'order_total'     => $order_det->currency['sign'] . number_format( $order_det->total, 2 )
		);


		$items = self::getOrderItems( $id );
		$th    = $allitems = "<table  class='email' style='width: 100%;border: 0;' cellpadding='0' cellspacing='0'><tr><th>Product Name</th><th>License</th><th style='width:80px;text-align:right'>Price</th></tr>";
		foreach ( $items as $item ) {
			$price    = $order_det->currency['sign'] . wpdmpp_price_format( $item['price'], false, true );
			$_item    = "<tr><td>" . WPDMPP()->cart->itemLink( $item, false ) . "</td><td>" . WPDMPP()->cart->itemInfo( $item, false ) . "</td><td style='width:80px;text-align:right'>{$price}</td></tr>";
			$allitems .= $_item;

		}
		$allitems .= "<tr><th colspan='2' style='text-align: right'>" . __( 'Cart Total', WPDM_TEXT_DOMAIN ) . "</th><th style='text-align: right'>" . wpdmpp_price_format( WPDMPP()->order->itemsCost( $items ) ) . "</th></tr>";
		if ( (double) $order_det->coupon_discount > 0 ) {
			$allitems .= "<tr><th colspan='2' style='text-align: right'>" . __( 'Coupon Discount', WPDM_TEXT_DOMAIN ) . "</th><th style='text-align: right'>-" . wpdmpp_price_format( $order_det->coupon_discount, true, true ) . "</th></tr>";
		}
		if ( (double) $order_det->tax > 0 ) {
			$allitems .= "<tr><th colspan='2' style='text-align: right'>" . __( 'Tax', WPDM_TEXT_DOMAIN ) . "</th><th style='text-align: right'>" . wpdmpp_price_format( $order_det->tax, true, true ) . "</th></tr>";
		}
		$allitems        .= "<tr><th colspan='2' style='text-align: right'>" . __( 'Order Total', WPDM_TEXT_DOMAIN ) . "</th><th style='text-align: right'>" . wpdmpp_price_format( $order_det->total, true, true ) . "</th></tr>";
		$allitems        .= "</table>";
		$params['items'] = $allitems;

		// to buyer
		if ( ! $order_det->uid ) {
			Email::send( "purchase-confirmation-guest", $params );
		} else {
			Email::send( "purchase-confirmation", $params );
		}

	}


}
