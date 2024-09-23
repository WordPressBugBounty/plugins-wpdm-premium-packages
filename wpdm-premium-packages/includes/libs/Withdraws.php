<?php

namespace WPDMPP\Libs;

use WPDM\__\__;
use WPDM\__\Template;

class Withdraws {

	function __construct() {
		include_once WPDMPP_BASE_DIR . 'includes/libs/payout-methods/PayPal.php';
		include_once WPDMPP_BASE_DIR . 'includes/libs/payout-methods/Payoneer.php';
		add_action( "wp_ajax_wpdmpp_user_payment_options", [ $this, 'saveUserPaymentAccount' ] );
	}

	function getPayoutMethods() {
		$methods = [
			'paypal'   => [
				'id'    => 'paypal',
				'name'  => 'PayPal',
				'icon'  => WPDMPP_BASE_URL . 'assets/images/paypal.png',
				'class' => '\WPDMPP\Libs\PayoutMethods\PayPal',
				'min'   => 10
			],
			'payoneer' => [
				'id'    => 'payoneer',
				'name'  => 'Payoneer',
				'icon'  => 'https://www.payoneer.com/wp-content/uploads/payoneer-circle.png',
				'class' => '\WPDMPP\Libs\PayoutMethods\Payoneer',
				'min'   => 50
			]
		];
		$methods = apply_filters( "wpdmpp_payout_methods", $methods );

		$payout_min_amount = get_option("wpdmpp_payout_min_amount", ['paypal' => 10, 'payoneer' => 50]);
		$active_pom = get_option("wpdmpp_active_pom", []);
		if(!is_array($active_pom)) $active_pom = [];
		foreach ($methods as &$method) {
			$method['min'] = (int)wpdm_valueof($payout_min_amount, $method['id']);
			$method['active'] = in_array($method['id'], $active_pom);
		}

		return $methods;
	}

	function saveUserPaymentAccount() {
		__::isAuthentic( '__supanonce', WPDM_PUB_NONCE, 'read', true );
		update_user_meta( get_current_user_id(), '__wpdmpp_payment_account', wpdm_query_var( 'account' ) );
		wp_send_json( [
			'success' => true,
			'type'    => 'success',
			'message' => __( 'Payment information have been updated!', WPDMPP_TEXT_DOMAIN )
		] );
	}

	function getRequests( $params = [] ) {
		global $wpdb;
		$cond = [];
		foreach ( $params as $field => $value ) {
			$cond[] = esc_sql( $field ) . "='" . esc_sql( $value ) . "'";
		}

		$cond     = ( count( $cond ) > 0 ) ? "where " . implode( " and ", $cond ) : "";
		$requests = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_withdraws " . $cond );
		foreach ( $requests as &$request ) {
			$request->user = get_user_by( 'id', $request->uid );
		}

		return $requests;
	}

	function getPaymentAccount( $payout ) {
		$user_accounts = get_user_meta( $payout->uid, '__wpdmpp_payment_account', true );
		$accounts      = $this->payoutAccounts();
		$pm = ( $payout->payment_method ? $payout->payment_method : 'paypal' );
		$account       = wpdm_valueof( $accounts, $pm );
		$account['account'] = wpdm_valueof( $user_accounts, $pm );
		$cname = wpdm_valueof( $account, 'class' );
		if ( $cname && class_exists( $cname ) ) {
			$account['method'] = new $cname();
		}

		return $account;
	}

	/**
	 * @return true|null
	 */
	function payoutAccounts( $uid = null ) {
		if ( ! $uid ) {
			$methods = $this->getPayoutMethods();
		} else {
			$methods = get_user_meta( get_current_user_id(), '__wpdmpp_payment_account', true );
		}

		return $methods;
	}

	function processPayout( $request ) {

	}

	/**
	 * @return false|string
	 */
	function requests() {
		ob_start();
		$requests = $this->getRequests( [ 'uid' => get_current_user_id() ] );
		$methods  = $this->getPayoutMethods();
		$accounts = get_user_meta( get_current_user_id(), '__wpdmpp_payment_account', true );
		include Template::locate( "dashboard/withdraws.php", WPDMPP_TPL_DIR );

		return ob_get_clean();
	}


}