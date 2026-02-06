<?php

namespace WPDMPP\Libs;

use WPDM\__\Email;
use WPDM\__\Session;

class AbandonedOrderRecovery {

	function __construct()
	{
		add_action( 'init', [ $this, 'processACR' ] );
	}

	function processACR() {
		if ( substr_count( $_SERVER['REQUEST_URI'], "/acr/" ) ) {
			$this->reLoadCart();
		}

		if ( wpdm_query_var( 'acre' ) ) {
			$this->abandonedOrderQueue();
			$this->abandonedOrderRecovery();
			die( 'Done!' );
		}
	}

	function abandonedOrderRecovery() {

		if ( wpdm_query_var( 'acre_key' ) !== WPDM()->cronJob->cronKey() ) {
			return;
		}

		global $wpdb;

		$acre_count      = get_wpdmpp_option( 'acre_count', 0, 'int' );
		$acre_interval   = get_wpdmpp_option( 'acre_interval' );
		$acre_interval   = explode( ",", $acre_interval );
		$acre_interval   = array_map( "trim", $acre_interval );
		$email_processed = 0;

		//Calculate/Sort email sending intervals
		for ( $i = 1; $i < $acre_count; $i ++ ) {
			$acre_interval[ $i ] = isset( $acre_interval[ $i ] ) ? $acre_interval[ $i ] : $acre_interval[0] * ( $i + 1 );
		}

		foreach ( $acre_interval as $_stage => $interval ) {
			$date  = wp_date( "Ymd", strtotime( "-{$interval} days" ) );
			$today = wp_date( 'Ymd' );

			$stage = $_stage + 1;

			$abandoned_orders = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_acr_emails where `stage` = $stage and `email_date`=$today and sent=0" );
			foreach ( $abandoned_orders as $abandoned_order ) {
				$order        = new Order( $abandoned_order->order_id );
				$activity_log = (array) json_decode( $abandoned_order->activity_log );
				if ( $order->order_status === 'Completed' ) {

					$order_url = admin_url( "/edit.php?post_type=wpdmpro&page=orders&task=vieworder&id={$abandoned_order->order_id}" );
					$params    = [
						'name'       => $abandoned_order->name,
						'order_date' => wp_date( get_option( 'date_format' ), strtotime( $abandoned_order->order_date ) ),
						'orderid'    => $abandoned_order->order_id,
						'items'      => Order::itemsTable( $abandoned_order->order_id ),
						'order_url'  => $order_url
					];
					Email::send( "recovered-order-confirmation", $params );
					$activity_log[ time() ] = [ "msg"  => __("Congratulation! Customer completed the payment", WPDMPP_TEXT_DOMAIN),
					                            'time' => wp_date( get_option( 'date_format' ), strtotime( $abandoned_order->order_date ) )
					];
					$wpdb->update( "{$wpdb->prefix}ahm_acr_emails", [
						'activity_log' => json_encode( $activity_log ),
						'sent'         => 1,
					], [ 'ID' => $abandoned_order->ID ] );

				} else {

					$checkout_url = home_url( "/acr/{$abandoned_order->order_id}/" );
					$params       = [
						'name'         => $abandoned_order->name,
						'to_email'     => $abandoned_order->email,
						'order_date'   => wp_date( get_option( 'date_format' ), strtotime( $abandoned_order->order_date ) ),
						'orderid'      => $abandoned_order->order_id,
						'items'        => Order::itemsTable( $abandoned_order->order_id ),
						'checkout_url' => $checkout_url
					];
					Email::send( "order-recovery-email-{$stage}", $params );
					$activity_log[ time() ] = [ "msg" => sprintf(__("Step #%s email sent successfully", WPDMPP_TEXT_DOMAIN), $stage), 'time' => time() ];
					$wpdb->update( "{$wpdb->prefix}ahm_acr_emails", [
						'sent'         => 1,
						'activity_log' => json_encode( $activity_log )
					], [ 'ID' => $abandoned_order->ID ] );
					//Process 10 emails per second
					$email_processed ++;
					if ( $email_processed % 10 === 0 ) {
						sleep( 1 );
					}
				}
			}
		}


	}

	// Call this function once a day
	function abandonedOrderQueue() {

		if ( wpdm_query_var( 'acrq_key' ) !== WPDM()->cronJob->cronKey() ) {
			return;
		}

		global $wpdb;

		$acre_count    = get_wpdmpp_option( 'acre_count', 0, 'int' );
		$acre_interval = get_wpdmpp_option( 'acre_interval' );
		$acre_interval = explode( ",", $acre_interval );
		$acre_interval = array_map( "trim", $acre_interval );

		if ( $acre_count <= 0 ) {
			return;
		}

		//Collect all abandoned orders within the last 24 hours
		$date             = time() - 90000;
		$abandoned_orders = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_orders where date >= {$date} and order_status = 'Processing' order by `date` desc" );

		foreach ( $abandoned_orders as $abandoned_order ) {
			$billing = maybe_unserialize( $abandoned_order->billing_info );
			$email   = wpdm_valueof( $billing, 'order_email' );
			$name    = wpdm_valueof( $billing, 'first_name' );
			if ( ! is_email( $email ) ) {
				$user = (int) $abandoned_order->uid > 0 ? get_user_by( 'id', $abandoned_order->uid ) : false;
				if ( $user ) {
					$email = $user->user_email;
					$name  = $user->display_name;
				}
			}
			$order_date = wp_date( "Ymd", $abandoned_order->date );
			if ( $email ) {
				foreach ( $acre_interval as $_stage => $days ) {
					$email_date = wp_date( "Ymd", strtotime( "+{$days} days" ) );
					$stage      = $_stage + 1;
					$wpdb->query( "INSERT IGNORE INTO {$wpdb->prefix}ahm_acr_emails SET order_id = '{$abandoned_order->order_id}', user_id = '{$abandoned_order->uid}', `name` = '{$name}', email = '{$email}', order_date = '{$order_date}', stage = '$stage', email_date='$email_date', activity_log = '[]', sent= '0'" );
				}
			}
		}

	}

	function reLoadCart() {
		preg_match( "/WPDM[A-Z0-9]+/", $_SERVER['REQUEST_URI'], $matches );
		$order_id = $matches[0];
		$order    = new Order( $order_id );
		Session::set( 'orderid', $order_id );
		wpdmpp_update_cart_data( $order->cart_data );
		header( "location: " . wpdmpp_cart_url() );
		die();
	}


}

new AbandonedOrderRecovery();
