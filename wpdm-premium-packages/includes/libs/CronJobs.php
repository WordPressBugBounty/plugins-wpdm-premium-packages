<?php
/**
 * User: shahjada
 * Date: 2019-03-21
 * Time: 13:14
 */

namespace WPDMPP\Libs;


use WPDM\__\__;
use WPDM\__\__MailUI;
use WPDM\__\Email;
use WPDM\__\Session;
use WPDM\__\UI;

class CronJobs {
	function __construct() {

		add_action("init", [$this, 'orderRenewalNotificationCron']);
		add_action("init", [$this, 'runDailySalesSummery']);
		add_filter( 'cron_schedules', [ $this, 'interval' ] );
		add_action("wpdm_cron_job", [ $this, 'dailySalesSummery' ]);

		if ( ! wp_next_scheduled( 'wpdmpp_notify_to_renew' ) ) {
			wp_schedule_event( time() + 1800, 'six_hourly', 'wpdmpp_notify_to_renew' );
		}

		if ( ! wp_next_scheduled( 'wpdmpp_delete_incomplete_order' ) ) {
			wp_schedule_event( time() + 3600, 'six_hourly', 'wpdmpp_delete_incomplete_order' );
		}

		if ( ! wp_next_scheduled( 'wpdmpp_daily_sales_summary' ) ) {
			wp_schedule_event( time() + 3600, 'six_hourly', 'wpdmpp_daily_sales_summary' );
		}

		$this->schedule();


	}

	function interval( $schedules ) {
		$schedules['six_hourly'] = array(
			'interval' => 21600, //6 hours
			'display'  => esc_html__( 'Every 6 hours' ),
		);

		return $schedules;
	}

	function schedule() {
		add_action( 'wpdmpp_notify_to_renew', array( $this, 'notifyToRenew' ) );
		add_action( 'wpdmpp_delete_incomplete_order', array( $this, 'deleteIncompleteOrders' ) );
		add_action( 'wpdmpp_daily_sales_summary', array( $this, 'runDailySalesSummery' ) );
	}

	function orderRenewalNotificationCron()
	{
		if(wpdm_query_var('ornc') === WPDM()->cronJob->cronKey()) {
			$this->notifyToRenew();;
		}
	}

	function notifyToRenew() {
		global $wpdb, $wpdmpp_settings;
		if ( ! isset( $wpdmpp_settings['order_expiry_alert'] ) || (int) $wpdmpp_settings['order_expiry_alert'] !== 1 ) {
			return;
		}
		$date   = date( "Y-m-d", strtotime( "+8 days" ) );
		$stime  = strtotime( $date . " 00:00" );
		$etime  = strtotime( $date . " 23:59" );
		$orders = $wpdb->get_results( "select * from {$wpdb->wpdmpp_orders} where expire_date >= $stime and expire_date <= $etime" );

		$ndate        = date( "Y_m" );
		$renew_notifs = get_option( "__wpdmpp_order_renewal_notifs_{$ndate}", array() );
		$renew_notifs = maybe_unserialize( $renew_notifs );
		$mailed       = 0;
		$total        = 0;
		$totalm       = 0;
		$msg          = __( "Order Expiration and Subscription reminder email sent for the following orders:", "wpdm-premium-packages" ) . "<br/>";
		$msg          .= "<table style='width:100%' class='email' cellspacing='0'>";
		foreach ( $orders as $order ) {
			if ( ! isset( $renew_notifs[ $order->order_id . "_" . $order->expire_date ] ) ) {
				if ( (int) $order->auto_renew === 1 ) {
					$total += (double) $order->total;
				} else {
					$totalm += (double) $order->total;
				}
				if ( $order->payment_method !== 'WPDM_2Checkout' ) {
					$order->billing_info = maybe_unserialize( $order->billing_info );
					$order->currency     = maybe_unserialize( $order->currency );
					$csign               = isset( $order->currency['sign'] ) ? $order->currency['sign'] : '$';
					$user                = get_user_by( 'id', $order->uid );
					$sitename            = get_bloginfo( 'name' );
					$exp_date            = date( get_option( 'date_format' ), $order->expire_date - 82800 );
					$order_url           = wpdmpp_orders_page( 'id=' . $order->order_id );
					$params              = array(
						'subject'     => "[$sitename] Automatic Order Renewal",
						'to_email'    => $user->user_email,
						'expire_date' => $exp_date,
						'orderid'     => $order->order_id,
						'order_url'   => $order_url
					);
					$items               = \WPDMPP\Libs\Order::GetOrderItems( $order->order_id );
					$allitems            = "<table  class='email' style='width: 100%;border: 0;margin-top: 15px' cellpadding='0' cellspacing='0'><tr><th>Product Name</th><th>License</th><th style='width:80px;text-align:right'>Price</th></tr>";
					foreach ( $items as $item ) {
						$product                                      = get_post( $item['pid'] );
						$license                                      = maybe_unserialize( $item['license'] );
						$license                                      = is_array( $license ) && isset( $license['info'], $license['info']['name'] ) ? $license['info']['name'] : " &mdash; ";
						$price                                        = $csign . number_format( $item['price'], 2 );
						$_item                                        = "<tr><td><a href='" . get_permalink( $product->ID ) . "'>{$product->post_title}</a></td><td>{$license}</td><td align='right' style='width:80px;text-align:right'>{$price}</td></tr>";
						$product_by_seller[ $product->post_author ][] = $_item;
						$allitems                                     .= $_item;
					}
					$allitems        .= "</table>";
					$params['items'] = $params['order_items'] = $allitems;

					if ( $order->auto_renew == 1 ) {
						Email::send( 'subscription-reminder', $params );
					} else {
						Email::send( 'order-expire', $params );
					}
					$mailed ++;
					$admin_view_order                                             = admin_url( "edit.php?post_type=wpdmpro&page=orders&task=vieworder&id={$order->order_id}" );
					$renew_notifs[ $order->order_id . "_" . $order->expire_date ] = 1;
				} else {
					//Order::update( array( 'auto_renew' => 1 ), $order->order_id );
				}
				$msg .= "<tr style='border-bottom: #ebf0f5'><td><a href='{$admin_view_order}'>{$order->order_id}</a></td><td align='right' style='text-align:right'>{$csign}{$order->total}</td></tr>";
			} else {
				Order::update( array( 'auto_renew' => 1 ), $order->order_id );
			}
		}

		if ( $mailed > 0 ) {
			// Notify admin
			$msg    .= "<tr style='background: #ebf0f5'><th>" . __( "Total Auto-renewal Amount", "wpdm-premium-packages" ) . ": </th><th align='right' style='text-align:right'>" . wpdmpp_currency_sign() . number_format( $total, 2 ) . "</th></tr>";
			$msg    .= "<tr><th>" . __( "Total Manual-renewal Amount", "wpdm-premium-packages" ) . ": </th><th align='right' style='text-align:right'>" . wpdmpp_currency_sign() . number_format( $totalm, 2 ) . "</th></tr>";
			$msg    .= "<tr style='background: #ebf0f5'><th>" . __( "Renewal Date", "wpdm-premium-packages" ) . ": </th><th align='right' style='text-align:right'>" . $date . "</th></tr></table>";
			$params = array(
				'subject'  => sprintf( __( "[%s] Order Expiration and Subscription reminder sent.", "wpdm-premium-packages" ), $sitename ),
				'to_email' => get_option( 'admin_email' ),
				'message'  => $msg
			);
			Email::send( 'default', $params );
		}

		update_option( "__wpdmpp_order_renewal_notifs_{$ndate}", $renew_notifs, false );

	}

	function deleteIncompleteOrders() {
		global $wpdb;

		$days = (int) get_wpdmpp_option( 'delete_incomplete_order' );

		if ( $days <= 0 ) {
			return;
		}

		$date   = strtotime( "-{$days} days" );
		$orders = $wpdb->get_results( "select * from {$wpdb->wpdmpp_orders} where date <= {$date} and order_status = 'Processing' and payment_status = 'Processing' limit 0, 20" );

		foreach ( $orders as $order ) {
			$wpdb->delete( $wpdb->wpdmpp_orders, [ 'order_id' => $order->order_id, 'order_status' => 'Processing' ] );
			$wpdb->delete( $wpdb->wpdmpp_order_items, [ 'oid' => $order->order_id ] );
		}

	}

	function runDailySalesSummery() {
		if(wpdm_query_var('wpdmppcron') === WPDM()->cronJob->cronKey()) {
			$this->dailySalesSummery();
		}
	}

	function dailySalesSummery() {
			global $wpdb;

			$mail_sent = get_option( '__wpdmpp_ssm_sent' );
			if( date("Ymd", $mail_sent) === date("Ymd", time())  ) return;

			$yesterdayStart = strtotime( "yesterday 00:00:00" );
			$yesterdayEnd   = strtotime( "yesterday 23:59:59" );
			$new_orders     = $wpdb->get_results( "select * from {$wpdb->prefix}`ahm_orders` where date <= {$yesterdayEnd} and date >= {$yesterdayStart} and order_status = 'Completed' and payment_status = 'Completed'" );
			$renewed_orders = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_order_renews where date <= {$yesterdayEnd} and date >= {$yesterdayStart}" );
			$data        = [];
			$totalSales  = 0;
			$orderCount  = count( $new_orders );
			$orderRenews = count( $renewed_orders );
			foreach ( $new_orders as $order ) {
				$data[]     = [ 'New', $order->order_id, wpdmpp_price_format( $order->total ) ];
				$totalSales += $order->total;
			}
			foreach ( $renewed_orders as $order ) {
				$data[]     = [ 'Renew', $order->order_id, wpdmpp_price_format( $order->total ) ];
				$totalSales += $order->total;
			}
			$totalSales = wpdmpp_price_format( $totalSales );
			$table      = __MailUI::table( [
				'Type',
				'Order ID',
				'Amount'
			], $data, [
				'th' => 'background: #f5f5f5;padding: 10px 5px;text-align:left;',
				'td' => 'border-bottom: 1px solid #f5f5f5;padding:8px 5px'
			] );

			$tscard     = __MailUI::panel( "Total Sales", [ "<h1 style='margin: 0'>{$totalSales}</h1>" ] );
			$tocard     = __MailUI::panel( "New Purchases", [ "<h1 style='margin: 0'>{$orderCount}</h1>" ] );
			$trcard     = __MailUI::panel( "Renewals", [ "<h1 style='margin: 0'>{$orderRenews}</h1>" ] );

			// Notify admin
			$msg    = "Here’s a quick snapshot of today’s sales performance:<br/><br/><table style='width:100%;'><tr><td>{$tscard}</td><td>{$tocard}</td><td>{$trcard}</td></tr></table><br/>{$table}";
			$params = array(
				'subject'  => sprintf( __( "[%s] Daily Sales Overview - %s", "wpdm-premium-packages" ), get_bloginfo( 'name' ), date( get_option( 'date_format' ), strtotime( 'yesterday' ) ) ),
				'to_email' => get_option( 'admin_email' ),
				'message'  => $msg
			);
			Email::send( 'default', $params );

			update_option( "__wpdmpp_ssm_sent", time(), false );
	}



}

new CronJobs();
