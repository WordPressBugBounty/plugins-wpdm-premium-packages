<?php


namespace WPDMPP;


use WPDM\__\Session;

class PayPalAPI {
	private $base = 'https://api-m.paypal.com/v{{APIV}}/';
	private $base_sandbox = 'https://api-m.sandbox.paypal.com/v{{APIV}}/';
	private $clientID;
	private $clientSecret;
	private $accessToken = null;
	public $planID = null;
	private $productID = null;
	private $orderID = null;
	private $orderTitle = null;

	function __construct( $env, $clientID, $clientSecret ) {
		$this->clientID     = $clientID;
		$this->clientSecret = $clientSecret;
		if ( $env === 'sandbox' ) {
			$this->base = $this->base_sandbox;
		}
		$this->getAccessToken();
		//->createProduct("BINGOX1", null, null, 'https://www.wpdownloadmanager.com/', 'https://www.wpdownloadmanager.com/wp-content/themes/wpdm5/images/wordpress-download-manager-logo.png')->createPlan(129);
	}

	function getAccessToken() {
		$params            = array( 'grant_type' => 'client_credentials' );
		$headers           = array(
			"Authorization" => "Basic " . base64_encode( $this->clientID . ':' . $this->clientSecret ),
			"Content-Type"  => "application/x-www-form-urlencoded"
		);
		$data              = $this->_request( 'oauth2/token', $params, $headers );
		$this->accessToken = $data->access_token;

		return $this;
	}

	public function createWebhook() {
		$webhook = get_option('wpdmpp_paypal_webhook');
		$webhook = json_decode( $webhook );
		$apiUrl  = $this->base . "notifications/webhooks";
		$apiUrl = str_replace( '{{APIV}}', 1, $apiUrl );

		$headers = [
			"Content-Type: application/json",
			"Authorization: Bearer " . $this->accessToken
		];
		if($webhook) {
			$this->curlExec( $webhook->links[2]->href, [], $headers, 'DELETE' );
		}
		$data    = json_encode( [
			"url"         => home_url( "/?pmwebhook=Paypal" ),
			"event_types" => [
				[ 'name' => 'PAYMENT.SALE.COMPLETED' ],
				[ 'name' => 'BILLING.SUBSCRIPTION.CANCELLED' ],
				[ 'name' => 'BILLING.SUBSCRIPTION.SUSPENDED' ],
				[ 'name' => 'BILLING.SUBSCRIPTION.UPDATED' ],
				[ 'name' => 'BILLING.SUBSCRIPTION.EXPIRED' ]
			]
		] );
		$response = $this->curlExec( $apiUrl, $data, $headers );

		update_option( 'wpdmpp_paypal_webhook', $response, true );

		return $response;
	}


	function createProduct( $orderID, $name = null, $description = '', $url = null, $image = null ) {
		if ( ! $name ) {
			$name = 'Order: ' . $orderID;
		}
		if ( ! $description ) {
			$description = $name;
		}
		if ( ! $url ) {
			$url = home_url( '/' );
		}
		if ( ! $image ) {
			$image = get_site_icon_url();
		}
		$params  = array(
			'name'        => $name,
			'description' => $description,
			'type'        => 'SERVICE',
			'category'    => 'SOFTWARE',
			'image_url'   => $image,
			'home_url'    => $url
		);
		$headers = array(
			"Authorization"     => "Bearer " . $this->accessToken,
			"PayPal-Request-Id" => $orderID,
			"Content-Type"      => "application/json"
		);
		//wpdmdd($params);
		$data = $this->_request( 'catalogs/products', json_encode( $params ), $headers );

		if ( ! property_exists( $data, 'id' ) ) {
			return false;
		}

		$this->orderID    = $orderID;
		$this->orderTitle = $name;
		$this->productID  = $data->id;

		return $this;
	}

	function createPlan( $price, $interval_count = null, $interval_unit = null ) {
		global $wpdmpp_settings;
		if ( ! $interval_count || ! $interval_unit ) {
			$order_validity_period = get_wpdmpp_option( 'order_validity_period', 365, 'int' );
			$interval_count        = $order_validity_period / 365;
			$interval_unit         = 'YEAR';
			if ( $interval_count < 1 ) {
				$interval_count = $order_validity_period / 30;
				$interval_unit  = 'MONTH';
			}
			if ( ! is_int( $interval_count ) ) {
				$interval_count = $order_validity_period / 7;
				$interval_unit  = 'WEEK';
			}

		}

		$params = array(
			'product_id'          => $this->productID,
			'name'                => $this->orderTitle,
			'description'         => $this->orderTitle,
			'billing_cycles'      => array(
				array(
					'frequency'      => array(
						"interval_unit"  => $interval_unit,
						"interval_count" => (int) $interval_count
					),
					"tenure_type"    => "REGULAR",
					"sequence"       => 1,
					"total_cycles"   => 0,
					"pricing_scheme" => array(
						"fixed_price" => array(
							"value"         => $price,
							"currency_code" => wpdmpp_currency_code()
						)
					)
				)
			),
			"payment_preferences" => array(
				"auto_bill_outstanding"     => true,
				"setup_fee_failure_action"  => "CONTINUE",
				"payment_failure_threshold" => 5
			),
		);
		$reqid  = Session::get( 'ppreqid' );
		if ( ! $reqid ) {
			$reqid = $this->orderID;
			Session::set( 'ppreqid', $reqid );
		}
		$headers = array(
			"Authorization"     => "Bearer " . $this->accessToken,
			"PayPal-Request-Id" => $reqid,
			"Content-Type"      => "application/json",
			"Accept"            => "application/json",
			"Prefer"            => "return=representation"
		);

		$data = $this->_request( 'billing/plans', json_encode( $params ), $headers );

		$this->planID = $data->id;

		//Update pricing in case price is changed in cart
		if ( (double) $data->billing_cycles[0]->pricing_scheme->fixed_price->value !== (double) $price ) {
			$reqid = $this->orderID . "_" . time();
			Session::set( 'ppreqid', $reqid );
			$headers      = array(
				"Authorization"     => "Bearer " . $this->accessToken,
				"PayPal-Request-Id" => $reqid,
				"Content-Type"      => "application/json",
				"Accept"            => "application/json",
				"Prefer"            => "return=representation"
			);
			$data         = $this->_request( 'billing/plans', json_encode( $params ), $headers );
			$this->planID = $data->id;
		}

		return $this;
	}


	function getSubscriptionDetails( $subscriptionID ) {
		$headers = array(
			"Authorization" => "Bearer " . $this->accessToken,
			"Content-Type"  => "application/json",
		);
		$data    = $this->_request( "billing/subscriptions/{$subscriptionID}", array(), $headers, 'GET' );

		return $data;
	}

	function getSubscriptionPayments( $subscriptionID ) {
		$start_time = date( "Y-m-d\TH:i:s\Z", strtotime( '-10 years' ) );
		$end_time   = date( "Y-m-d\TH:i:s\Z", time() );
		$headers    = array(
			"Authorization" => "Bearer " . $this->accessToken,
			"Content-Type"  => "application/json",
		);
		$data       = $this->_request( "billing/subscriptions/{$subscriptionID}/transactions?start_time={$start_time}&end_time={$end_time}", array(), $headers, 'GET' );

		return $data->transactions;
	}

	function curlExec( $url, $params, $headers, $method = 'POST' ) {
		$curl    = curl_init();
		$opts = array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_POSTFIELDS     => $params,
			CURLOPT_HTTPHEADER     => $headers,
		);

		curl_setopt_array( $curl, $opts );

		$response = curl_exec( $curl );
		curl_close( $curl );
		return $response;
	}

	function _request( $action, $params, $headers, $method = 'POST' ) {
		$apiv       = $action === 'oauth2/token' ? 1 : 2;
		$this->base = str_replace( '{{APIV}}', $apiv, $this->base );
		$url        = $this->base . $action;
		$uparts     = parse_url( $this->base );
		//$headers['Host'] = $uparts['host'];
		$response = wp_remote_post( $url, array(
				'method'      => $method,
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => $headers,
				'body'        => $params,
				'cookies'     => array()
			)
		);

		return json_decode( $response['body'] );
	}

}


