<?php

namespace WPDMPP\Libs;

// Exit if accessed directly
use WPDM\__\__;
use WPDM\__\TempStorage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CouponCodes' ) ):

	class CouponCodes {

		function __construct() {
			add_action( 'init', array( $this, 'add_coupon' ) );
			add_action( 'init', array( $this, 'update_coupon' ) );
			add_action( 'wp_ajax_wpdmpp_delete_coupon', array( $this, 'delete_coupon' ) );
			add_action( 'wp_ajax_wpdmpp_get_couponed_orders', array( $this, 'view_orders' ) );
		}

		static function find( $coupon_code ) {
			global $wpdb;
			$row = $wpdb->get_row( "select * from {$wpdb->prefix}ahm_coupons where code='{$coupon_code}'" );

			return $row;
		}

		static function get( $ID ) {
			global $wpdb;
			$row = $wpdb->get_row( "select * from {$wpdb->prefix}ahm_coupons where ID='{$ID}'" );

			return $row;
		}

		function add_coupon() {
			if ( isset( $_REQUEST['do'] ) && $_REQUEST['do'] == 'addcoupon' && current_user_can( WPDMPP_ADMIN_CAP ) && isset( $_POST['__anc'] ) && wp_verify_nonce( $_POST['__anc'], NONCE_KEY ) ) {
				global $wpdb;
				$coupon = wpdm_sanitize_array( $_REQUEST['coupon'] );

				if ( $coupon['expire_date'] != '' ) {
					$coupon['expire_date'] = strtotime( $coupon['expire_date'] );
				} else {
					$coupon['expire_date'] = 0;
				}

				$wpdb->insert( "{$wpdb->prefix}ahm_coupons", $coupon );
				//$wpdb->show_errors();
				//$wpdb->print_error();
				header( "location: edit.php?post_type=wpdmpro&page=pp-coupon-codes" );
				die();
			}
		}

		function update_coupon() {
			if ( isset( $_REQUEST['do'] ) && $_REQUEST['do'] == 'updatecoupon' && current_user_can( WPDMPP_ADMIN_CAP ) && isset( $_POST['__ucc'] ) && wp_verify_nonce( $_POST['__ucc'], NONCE_KEY ) ) {
				global $wpdb;
				$coupon = wpdm_sanitize_array( $_REQUEST['coupon'] );

				if ( $coupon['expire_date'] != '' ) {
					$coupon['expire_date'] = strtotime( $coupon['expire_date'] );
				} else {
					$coupon['expire_date'] = 0;
				}
				$coupon = __::sanitize_array( $coupon, 'kses' );
				$wpdb->update( "{$wpdb->prefix}ahm_coupons", $coupon, array( 'ID' => absint( $_REQUEST['ID'] ) ) );
				header( "location: edit.php?post_type=wpdmpro&page=pp-coupon-codes" );
				die();
			}
		}

		static function increase_coupon_usage_count( $coupon ) {
			global $wpdb;
			$query = "UPDATE `{$wpdb->prefix}ahm_coupons` SET `used`= `used` + 1 WHERE `code` = '{$coupon['coupon_code']}'";
			$wpdb->query( $query );
		}

		static function validate_coupon( $code, $product = 0, $items = null ) {

			$coupon   = self::find( $code );
			$car_data = $items ?: WPDMPP()->cart->getItems();

			if ( ! $coupon || ! $car_data ) {
				return false;
			}

			$amount = wpdmpp_get_cart_subtotal();
			if ( $coupon->expire_date > 0 && $coupon->expire_date < time() ) {
				return false;
			}

			if ( $coupon->usage_limit > 0 && $coupon->usage_limit <= $coupon->used ) {
				return false;
			}
			if ( $coupon->min_order_amount > 0 && $coupon->min_order_amount > $amount ) {
				return false;
			}
			if ( $coupon->max_order_amount > 0 && $coupon->max_order_amount < $amount ) {
				return false;
			}

			if ( $product === 0 && wpdm_valueof( $car_data, "{$coupon->product}/coupon" ) === $coupon ) {
				return false;
			}

			if ( $coupon->product && ! in_array( $coupon->product, array_keys( $car_data ) ) ) {
				return false;
			}

			$product = $coupon->product;

			$total_amount = $product == 0 ? $amount : $car_data[ $product ]['price'] * $car_data[ $product ]['quantity'];

			$discount = 0;
			if ( $product == 0 && $coupon->type == 'fixed' ) {
				$discount = $coupon->discount;
			} else if ( $product > 0 && $coupon->type == 'fixed' ) {
				$discount = $coupon->discount * $car_data[ $product ]['quantity'];
			} else {
				$discount = number_format( $total_amount * $coupon->discount / 100, 2, ".", "" );
				if ( $discount > $total_amount ) {
					$discount = $total_amount;
				}
			}

			return floatval( $discount );
		}

		function delete_coupon() {
			__::isAuthentic( 'dcpnonce', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP );
			if ( current_user_can( WPDMPP_ADMIN_CAP ) ) {
				global $wpdb;
				$wpdb->delete( "{$wpdb->prefix}ahm_coupons", array( 'ID' => absint( $_REQUEST['ID'] ) ) );
				die( "OK" );
			}
			die( "ERROR" );
		}

		function auto_coupon( $total = 0 ) {
			global $wpdb;
			$total           = (double) $total;
			$discount_amount = 0;
			$code            = '';
			$now             = time();

			$coupons = $wpdb->get_results( "select  * from {$wpdb->wpdmpp_coupons} where auto_apply=1 and min_order_amount <= $total and (expire_date = 0 or expire_date > $now)" );

			foreach ( $coupons as $_coupon ) {
				$_discount_amount = self::validate_coupon( $_coupon->code );
				if ( $_discount_amount > $discount_amount ) {
					$discount_amount = $_discount_amount;
					$code            = $_coupon->code;
				}
			}
			if ( $code && $discount_amount > 0 ) {
				$coupon_info = WPDMPP()->cart->applyCoupon( $code );

				return $coupon_info;
			}

			return false;
		}

		function get_orders( $coupon_code ) {
			global $wpdb;
			$coupon_code = esc_sql( $coupon_code );
			$orders      = $wpdb->get_results( "select * from {$wpdb->wpdmpp_orders} where coupon_code = '{$coupon_code}'" );

			return $orders;
		}

		function view_orders() {
			__::isAuthentic( 'cononce', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP );
			$code = wpdm_query_var( 'coupon_code', 'txt' );
			if ( ! $code ) {
				die( 'Coupon code missing!' );
			}
			$orders = $this->get_orders( $code );
			foreach ( $orders as $order ) {
				?>
				<div
					onclick="window.open('<?= admin_url( 'edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $order->order_id ); ?>')"
					class="panel panel-default c-pointer">
					<div class="panel-body">
						<div class="media">
							<div class="pull-right">
								<h3 class="text-success"><?= wpdmpp_price_format( $order->total ) ?></h3>
							</div>
							<div class="media-body">
								<strong><?= $order->order_id ?></strong><br/>
								<small><?= wp_date( get_option( 'date_format' ), $order->date ) ?></small>
							</div>
						</div>
					</div>
				</div>
				<?php
			}
			die();
		}


	}


endif;
