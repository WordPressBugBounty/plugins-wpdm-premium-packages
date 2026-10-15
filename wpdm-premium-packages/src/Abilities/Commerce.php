<?php

namespace WPDMPP\Abilities;

use WPDM\Abilities\AbilityGroup;
use WPDMPP\Customer\CustomerService;
use WPDMPP\License\License;
use WPDMPP\License\LicenseService;
use WPDMPP\Order\Order;
use WPDMPP\Order\OrderService;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only commerce abilities: orders, customers, licenses and sales totals.
 *
 * Registered through Download Manager's `wpdm_ability_groups` filter, so it only loads
 * when Download Manager 7.6+ runs on WordPress 6.9+.
 *
 * Output keeps what an admin needs to answer a question and drops what an agent doesn't:
 * buyer IP addresses, street addresses, phone numbers, internal order notes and download
 * URLs never appear, and license keys are masked to their last 4 characters.
 *
 * @since 7.3.0
 */
class Commerce extends AbilityGroup {

	const CATEGORY = 'wpdm-commerce';

	const ORDER_STATUSES = [ 'Pending', 'Processing', 'Completed', 'Cancelled', 'Expired' ];

	const PAYMENT_STATUSES = [ 'Pending', 'Processing', 'Completed', 'Bonus', 'Gifted', 'Cancelled', 'Refunded', 'Disputed', 'Expired' ];

	/**
	 * Payment statuses counted as revenue, matching wpdmpp_total_sales().
	 */
	const PAID = [ 'Completed', 'Expired' ];

	const MAX_RANGE_DAYS = 366;

	/**
	 * Add the group to Download Manager's ability groups.
	 *
	 * @param array $groups Groups.
	 *
	 * @return array
	 */
	public static function addGroup( $groups ) {
		$groups['wpdm-commerce'] = [
			'label'       => __( 'Commerce', 'wpdm-premium-packages' ),
			'description' => __( 'Read orders, customers, licenses and sales totals. License keys are masked.', 'wpdm-premium-packages' ),
			'class'       => self::class,
		];

		return $groups;
	}

	public function register() {
		$read     = [
			'readonly'   => true,
			'idempotent' => true,
		];
		$is_admin = function () {
			return current_user_can( WPDMPP_ADMIN_CAP );
		};
		$date     = [
			'type'    => 'string',
			'pattern' => '^\d{4}-\d{2}-\d{2}$',
		];

		$this->registerAbility(
			'wpdmpp/list-orders',
			[
				'label'               => __( 'List orders', 'wpdm-premium-packages' ),
				'description'         => __( 'Lists orders, newest first, filtered by status, payment status, customer user ID, date range, or a search on order ID, transaction ID or title. To find a customer\'s orders by email, look up their user ID with wpdmpp/get-customer first.', 'wpdm-premium-packages' ),
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'additionalProperties' => false,
					'properties'           => [
						'status'         => [
							'type' => 'string',
							'enum' => self::ORDER_STATUSES,
						],
						'payment_status' => [
							'type' => 'string',
							'enum' => self::PAYMENT_STATUSES,
						],
						'user_id'        => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'search'         => [
							'type'      => 'string',
							'maxLength' => 200,
						],
						'date_from'      => $date + [ 'description' => 'First day, YYYY-MM-DD.' ],
						'date_to'        => $date + [ 'description' => 'Last day, YYYY-MM-DD.' ],
						'orderby'        => [
							'type'    => 'string',
							'enum'    => [ 'date', 'total', 'order_id', 'order_status', 'payment_status' ],
							'default' => 'date',
						],
						'order'          => [
							'type'    => 'string',
							'enum'    => [ 'asc', 'desc' ],
							'default' => 'desc',
						],
					] + $this->pagingProperties(),
				],
				'output_schema'       => $this->pageSchema( $this->orderSchema() ),
				'permission_callback' => $is_admin,
				'execute_callback'    => [ $this, 'listOrders' ],
				'annotations'         => $read,
			]
		);

		$this->registerAbility(
			'wpdmpp/get-order',
			[
				'label'               => __( 'Get order', 'wpdm-premium-packages' ),
				'description'         => __( 'Reads one order: its items, amounts, statuses, payment method and the buyer\'s name, email, company and country.', 'wpdm-premium-packages' ),
				'input_schema'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => [ 'order_id' ],
					'properties'           => [
						'order_id' => [
							'type'        => 'string',
							'minLength'   => 1,
							'maxLength'   => 200,
							'description' => 'Order ID or payment transaction ID.',
						],
					],
				],
				'output_schema'       => $this->orderDetailSchema(),
				'permission_callback' => $is_admin,
				'execute_callback'    => [ $this, 'getOrder' ],
				'annotations'         => $read,
			]
		);

		$this->registerAbility(
			'wpdmpp/get-customer',
			[
				'label'               => __( 'Get customer', 'wpdm-premium-packages' ),
				'description'         => __( 'Reads a registered customer by user ID or email: total spent, order counts and the products they bought. Guest buyers without an account are not covered.', 'wpdm-premium-packages' ),
				'input_schema'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => [
						'user_id' => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'email'   => [
							'type'   => 'string',
							'format' => 'email',
						],
					],
				],
				'output_schema'       => $this->customerSchema(),
				'permission_callback' => $is_admin,
				'execute_callback'    => [ $this, 'getCustomer' ],
				'annotations'         => $read,
			]
		);

		$this->registerAbility(
			'wpdmpp/list-licenses',
			[
				'label'               => __( 'List licenses', 'wpdm-premium-packages' ),
				'description'         => __( 'Lists license keys, filtered by product, order, status or expiry. Search matches part of a key or an activated domain. Keys are masked to their last 4 characters; full keys stay in wp-admin.', 'wpdm-premium-packages' ),
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'additionalProperties' => false,
					'properties'           => [
						'search'       => [
							'type'      => 'string',
							'maxLength' => 200,
						],
						'status'       => [
							'type' => 'string',
							'enum' => [ 'active', 'inactive' ],
						],
						'product_id'   => [
							'type'    => 'integer',
							'minimum' => 1,
						],
						'order_id'     => [
							'type'      => 'string',
							'maxLength' => 200,
						],
						'expired_only' => [
							'type'    => 'boolean',
							'default' => false,
						],
					] + $this->pagingProperties(),
				],
				'output_schema'       => $this->pageSchema( $this->licenseSchema() ),
				'permission_callback' => $is_admin,
				'execute_callback'    => [ $this, 'listLicenses' ],
				'annotations'         => $read,
			]
		);

		$this->registerAbility(
			'wpdmpp/get-sales-summary',
			[
				'label'               => __( 'Get sales summary', 'wpdm-premium-packages' ),
				'description'         => __( 'Totals paid orders (Completed or Expired) over a date range in the site\'s timezone: revenue in the store currency, order count, average order, revenue per day and the top 10 products. Defaults to the last 30 days; up to 366 days. With product_id, only that product\'s line items count.', 'wpdm-premium-packages' ),
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'additionalProperties' => false,
					'properties'           => [
						'date_from'  => $date + [ 'description' => 'First day, YYYY-MM-DD.' ],
						'date_to'    => $date + [ 'description' => 'Last day, YYYY-MM-DD. Defaults to today.' ],
						'product_id' => [
							'type'    => 'integer',
							'minimum' => 1,
						],
					],
				],
				'output_schema'       => $this->salesSchema(),
				'permission_callback' => $is_admin,
				'execute_callback'    => [ $this, 'getSalesSummary' ],
				'annotations'         => $read,
			]
		);
	}

	public function listOrders( array $input ) {
		$page     = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 20;

		$args = [
			'orderby' => isset( $input['orderby'] ) ? $input['orderby'] : 'date',
			'order'   => isset( $input['order'] ) && 'asc' === $input['order'] ? 'ASC' : 'DESC',
			'limit'   => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
		];
		foreach ( [ 'status', 'payment_status', 'user_id', 'search', 'date_from', 'date_to' ] as $key ) {
			if ( ! empty( $input[ $key ] ) ) {
				$args[ $key ] = $input[ $key ];
			}
		}

		$result = OrderService::instance()->getOrders( $args );

		return $this->page( array_map( [ $this, 'orderSummary' ], $result['orders'] ), $result['total'], $page, $per_page );
	}

	public function getOrder( array $input ) {
		$order = OrderService::instance()->getOrder( (string) $input['order_id'] );
		if ( ! $order ) {
			return $this->error( 'wpdmpp_order_not_found', __( 'No order has that ID.', 'wpdm-premium-packages' ), 404 );
		}

		$data    = $order->toArray();
		$billing = is_array( $data['billing_info'] ) ? $data['billing_info'] : [];
		$items   = array_map(
			function ( $item ) {
				return [
					'product_id'   => (int) $item['product_id'],
					'product_name' => html_entity_decode( (string) $item['product_name'], ENT_QUOTES, 'UTF-8' ),
					'license'      => (string) $item['license_name'],
					'quantity'     => (int) $item['quantity'],
					'price'        => (float) $item['price'],
					'line_total'   => (float) $item['line_total'],
				];
			},
			$data['items']
		);

		return $this->orderSummary( $order ) + [
			'transaction_id' => (string) $data['transaction_id'],
			'items'          => $items,
			'subtotal'       => (float) $data['subtotal'],
			'discount'       => (float) $data['total_discount'],
			'tax'            => (float) $data['tax'],
			'refund'         => (float) $data['refund'],
			'coupon_code'    => (string) $data['coupon_code'],
			'auto_renew'     => (bool) $data['auto_renew'],
			'billing'        => [
				'name'    => (string) $data['billing_name'],
				'email'   => (string) $data['billing_email'],
				'company' => isset( $billing['company'] ) ? (string) $billing['company'] : '',
				'country' => isset( $billing['country'] ) ? (string) $billing['country'] : '',
			],
		];
	}

	public function getCustomer( array $input ) {
		$has_id    = ! empty( $input['user_id'] );
		$has_email = ! empty( $input['email'] );
		if ( $has_id === $has_email ) {
			return $this->error( 'wpdmpp_invalid_customer_lookup', __( 'Give either user_id or email, not both.', 'wpdm-premium-packages' ), 400 );
		}

		$service  = CustomerService::getInstance();
		$customer = $has_id ? $service->getCustomer( (int) $input['user_id'] ) : $service->getCustomerByEmail( (string) $input['email'] );
		$profile  = $customer && $customer->exists() ? $service->getCustomerProfile( (int) $customer->getId() ) : null;
		if ( ! $profile ) {
			return $this->error( 'wpdmpp_customer_not_found', __( 'No registered customer matches.', 'wpdm-premium-packages' ), 404 );
		}

		$info      = $profile['customer'];
		$purchases = array_map(
			function ( $item ) {
				return [
					'product_id'   => (int) $item['product_id'],
					'product_name' => html_entity_decode( (string) $item['product_name'], ENT_QUOTES, 'UTF-8' ),
					'order_id'     => (string) $item['order_id'],
					'order_status' => (string) $item['order_status'],
					'order_date'   => $this->isoDate( (int) $item['order_date'] ),
				];
			},
			$profile['purchases']
		);

		return [
			'user_id'     => (int) $info['id'],
			'name'        => (string) ( $info['full_name'] ?: $info['display_name'] ),
			'email'       => (string) $info['email'],
			'registered'  => $info['registered_date'] ? $this->isoDate( strtotime( $info['registered_date'] ) ) : '',
			'total_spent' => (float) $info['total_spent'],
			'orders'      => [
				'total'     => (int) $profile['orders']['total'],
				'completed' => (int) $profile['orders']['completed'],
			],
			'purchases'   => $purchases,
			'orders_url'  => admin_url( 'edit.php?post_type=wpdmpro&page=orders&customer=' . (int) $info['id'] ),
		];
	}

	public function listLicenses( array $input ) {
		$page     = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 20;

		$args = [
			'limit'        => $per_page,
			'offset'       => ( $page - 1 ) * $per_page,
			'expired_only' => ! empty( $input['expired_only'] ),
		];
		if ( isset( $input['status'] ) ) {
			$args['status'] = 'active' === $input['status'] ? License::STATUS_ACTIVE : License::STATUS_INACTIVE;
		}
		foreach ( [ 'search', 'product_id', 'order_id' ] as $key ) {
			if ( ! empty( $input[ $key ] ) ) {
				$args[ $key ] = $input[ $key ];
			}
		}

		$result = LicenseService::getInstance()->getLicenses( $args );
		$items  = array_map(
			function ( $license ) {
				$data = $license->toArray();

				return [
					'id'              => (int) $data['id'],
					'license_key'     => self::maskKey( (string) $data['license_no'] ),
					'status'          => $data['is_active'] ? 'active' : 'inactive',
					'is_expired'      => (bool) $data['is_expired'],
					'order_id'        => (string) $data['order_id'],
					'product_id'      => (int) $data['product_id'],
					'product_name'    => html_entity_decode( (string) get_the_title( (int) $data['product_id'] ), ENT_QUOTES, 'UTF-8' ),
					'domains'         => array_values( array_map( 'strval', (array) $data['domains'] ) ),
					'domain_limit'    => (int) $data['domain_limit'],
					'activation_date' => $data['activation_date'] ? $this->isoDate( (int) $data['activation_date'] ) : '',
					'expire_date'     => $data['expire_date'] ? $this->isoDate( (int) $data['expire_date'] ) : '',
				];
			},
			$result['licenses']
		);

		return $this->page( $items, $result['total'], $page, $per_page );
	}

	public function getSalesSummary( array $input ) {
		global $wpdb;

		$tz    = wp_timezone();
		$today = new \DateTimeImmutable( 'today', $tz );
		$to    = ! empty( $input['date_to'] ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $input['date_to'], $tz ) : $today;
		$from  = ! empty( $input['date_from'] ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $input['date_from'], $tz ) : ( $to ? $to->modify( '-29 days' ) : false );
		foreach ( [ 'date_from' => $from, 'date_to' => $to ] as $key => $day ) {
			if ( ! $day || ( ! empty( $input[ $key ] ) && $day->format( 'Y-m-d' ) !== $input[ $key ] ) ) {
				return $this->error( 'wpdmpp_invalid_date', __( 'Dates must be real days in YYYY-MM-DD form.', 'wpdm-premium-packages' ), 400 );
			}
		}
		if ( $from > $to || $from->diff( $to )->days >= self::MAX_RANGE_DAYS ) {
			/* translators: %d: days */
			return $this->error( 'wpdmpp_invalid_range', sprintf( __( 'date_from must be on or before date_to, and the range at most %d days.', 'wpdm-premium-packages' ), self::MAX_RANGE_DAYS ), 400 );
		}

		$orders  = "{$wpdb->prefix}ahm_orders";
		$items   = "{$wpdb->prefix}ahm_order_items";
		$paid    = "'" . implode( "','", self::PAID ) . "'";
		$range   = [ $from->getTimestamp(), $to->modify( '+1 day' )->getTimestamp() ];
		$offset  = $tz->getOffset( $from );
		$product = ! empty( $input['product_id'] ) ? (int) $input['product_id'] : 0;

		if ( $product ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT FLOOR((o.date + %d) / 86400) AS site_day, COUNT(DISTINCT o.order_id) AS orders, SUM(oi.base_price * oi.quantity) AS revenue
					 FROM {$orders} o INNER JOIN {$items} oi ON oi.oid = o.order_id
					 WHERE o.payment_status IN ({$paid}) AND o.date >= %d AND o.date < %d AND oi.pid = %d
					 GROUP BY site_day ORDER BY site_day",
					array_merge( [ $offset ], $range, [ $product ] )
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT FLOOR((o.date + %d) / 86400) AS site_day, COUNT(*) AS orders, SUM(o.base_total) AS revenue
					 FROM {$orders} o
					 WHERE o.payment_status IN ({$paid}) AND o.date >= %d AND o.date < %d
					 GROUP BY site_day ORDER BY site_day",
					array_merge( [ $offset ], $range )
				)
			);
		}

		$by_day        = [];
		$revenue       = 0.0;
		$order_count   = 0;
		foreach ( $rows as $row ) {
			$by_day[]     = [
				'date'    => gmdate( 'Y-m-d', (int) $row->site_day * DAY_IN_SECONDS ),
				'revenue' => round( (float) $row->revenue, 2 ),
				'orders'  => (int) $row->orders,
			];
			$revenue     += (float) $row->revenue;
			$order_count += (int) $row->orders;
		}

		$top = [];
		if ( ! $product ) {
			$products = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT oi.pid, SUM(oi.base_price * oi.quantity) AS revenue, SUM(oi.quantity) AS quantity
					 FROM {$orders} o INNER JOIN {$items} oi ON oi.oid = o.order_id
					 WHERE o.payment_status IN ({$paid}) AND o.date >= %d AND o.date < %d
					 GROUP BY oi.pid ORDER BY revenue DESC LIMIT 10",
					$range
				)
			);
			foreach ( $products as $row ) {
				$top[] = [
					'product_id'   => (int) $row->pid,
					'product_name' => html_entity_decode( (string) get_the_title( (int) $row->pid ), ENT_QUOTES, 'UTF-8' ),
					'revenue'      => round( (float) $row->revenue, 2 ),
					'quantity'     => (int) $row->quantity,
				];
			}
		}

		return [
			'date_from'     => $from->format( 'Y-m-d' ),
			'date_to'       => $to->format( 'Y-m-d' ),
			'currency'      => function_exists( 'wpdmpp_currency_code' ) ? (string) wpdmpp_currency_code() : '',
			'revenue'       => round( $revenue, 2 ),
			'orders'        => $order_count,
			'average_order' => $order_count ? round( $revenue / $order_count, 2 ) : 0.0,
			'by_day'        => $by_day,
			'top_products'  => $top,
		];
	}

	/**
	 * The fields shared by order list items and the full order.
	 *
	 * @param Order $order Order.
	 *
	 * @return array
	 */
	public function orderSummary( Order $order ) {
		$data = $order->toArray();

		return [
			'order_id'       => (string) $data['order_id'],
			'title'          => html_entity_decode( (string) $data['title'], ENT_QUOTES, 'UTF-8' ),
			'order_status'   => (string) $data['order_status'],
			'payment_status' => (string) $data['payment_status'],
			'payment_method' => (string) $data['payment_method_label'],
			'total'          => (float) $data['total'],
			'currency'       => $order->getCurrencyCode(),
			'date'           => $this->isoDate( (int) $data['date'] ),
			'expire_date'    => $data['expire_date'] ? $this->isoDate( (int) $data['expire_date'] ) : '',
			'item_count'     => (int) $data['item_count'],
			'customer'       => [
				'user_id' => (int) $data['user_id'],
				'name'    => (string) $data['billing_name'],
				'email'   => (string) $data['billing_email'],
			],
			'admin_url'      => $order->getAdminUrl(),
		];
	}

	/**
	 * A license key reduced to its last 4 characters.
	 *
	 * @param string $key License key.
	 *
	 * @return string
	 */
	public static function maskKey( $key ) {
		$length = strlen( $key );

		return $length > 4 ? str_repeat( '*', min( 12, $length - 4 ) ) . substr( $key, -4 ) : str_repeat( '*', $length );
	}

	private function orderSchema() {
		return [
			'type'       => 'object',
			'properties' => [
				'order_id'       => [ 'type' => 'string' ],
				'title'          => [ 'type' => 'string' ],
				'order_status'   => [ 'type' => 'string' ],
				'payment_status' => [ 'type' => 'string' ],
				'payment_method' => [ 'type' => 'string' ],
				'total'          => [ 'type' => 'number' ],
				'currency'       => [ 'type' => 'string' ],
				'date'           => [ 'type' => 'string' ],
				'expire_date'    => [ 'type' => 'string' ],
				'item_count'     => [ 'type' => 'integer' ],
				'customer'       => [
					'type'       => 'object',
					'properties' => [
						'user_id' => [ 'type' => 'integer' ],
						'name'    => [ 'type' => 'string' ],
						'email'   => [ 'type' => 'string' ],
					],
				],
				'admin_url'      => [ 'type' => 'string' ],
			],
			'required'   => [ 'order_id', 'order_status', 'payment_status', 'total' ],
		];
	}

	private function orderDetailSchema() {
		$schema                 = $this->orderSchema();
		$schema['properties'] += [
			'transaction_id' => [ 'type' => 'string' ],
			'items'          => [
				'type'  => 'array',
				'items' => [
					'type'       => 'object',
					'properties' => [
						'product_id'   => [ 'type' => 'integer' ],
						'product_name' => [ 'type' => 'string' ],
						'license'      => [ 'type' => 'string' ],
						'quantity'     => [ 'type' => 'integer' ],
						'price'        => [ 'type' => 'number' ],
						'line_total'   => [ 'type' => 'number' ],
					],
				],
			],
			'subtotal'       => [ 'type' => 'number' ],
			'discount'       => [ 'type' => 'number' ],
			'tax'            => [ 'type' => 'number' ],
			'refund'         => [ 'type' => 'number' ],
			'coupon_code'    => [ 'type' => 'string' ],
			'auto_renew'     => [ 'type' => 'boolean' ],
			'billing'        => [
				'type'       => 'object',
				'properties' => [
					'name'    => [ 'type' => 'string' ],
					'email'   => [ 'type' => 'string' ],
					'company' => [ 'type' => 'string' ],
					'country' => [ 'type' => 'string' ],
				],
			],
		];

		return $schema;
	}

	private function customerSchema() {
		return [
			'type'       => 'object',
			'properties' => [
				'user_id'     => [ 'type' => 'integer' ],
				'name'        => [ 'type' => 'string' ],
				'email'       => [ 'type' => 'string' ],
				'registered'  => [ 'type' => 'string' ],
				'total_spent' => [ 'type' => 'number' ],
				'orders'      => [
					'type'       => 'object',
					'properties' => [
						'total'     => [ 'type' => 'integer' ],
						'completed' => [ 'type' => 'integer' ],
					],
				],
				'purchases'   => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'product_id'   => [ 'type' => 'integer' ],
							'product_name' => [ 'type' => 'string' ],
							'order_id'     => [ 'type' => 'string' ],
							'order_status' => [ 'type' => 'string' ],
							'order_date'   => [ 'type' => 'string' ],
						],
					],
				],
				'orders_url'  => [ 'type' => 'string' ],
			],
			'required'   => [ 'user_id', 'email', 'orders', 'purchases' ],
		];
	}

	private function licenseSchema() {
		return [
			'type'       => 'object',
			'properties' => [
				'id'              => [ 'type' => 'integer' ],
				'license_key'     => [
					'type'        => 'string',
					'description' => 'Masked: only the last 4 characters are shown.',
				],
				'status'          => [ 'type' => 'string' ],
				'is_expired'      => [ 'type' => 'boolean' ],
				'order_id'        => [ 'type' => 'string' ],
				'product_id'      => [ 'type' => 'integer' ],
				'product_name'    => [ 'type' => 'string' ],
				'domains'         => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'domain_limit'    => [ 'type' => 'integer' ],
				'activation_date' => [ 'type' => 'string' ],
				'expire_date'     => [ 'type' => 'string' ],
			],
			'required'   => [ 'id', 'license_key', 'status' ],
		];
	}

	private function salesSchema() {
		return [
			'type'       => 'object',
			'properties' => [
				'date_from'     => [ 'type' => 'string' ],
				'date_to'       => [ 'type' => 'string' ],
				'currency'      => [ 'type' => 'string' ],
				'revenue'       => [ 'type' => 'number' ],
				'orders'        => [ 'type' => 'integer' ],
				'average_order' => [ 'type' => 'number' ],
				'by_day'        => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'date'    => [ 'type' => 'string' ],
							'revenue' => [ 'type' => 'number' ],
							'orders'  => [ 'type' => 'integer' ],
						],
					],
				],
				'top_products'  => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'product_id'   => [ 'type' => 'integer' ],
							'product_name' => [ 'type' => 'string' ],
							'revenue'      => [ 'type' => 'number' ],
							'quantity'     => [ 'type' => 'integer' ],
						],
					],
				],
			],
			'required'   => [ 'date_from', 'date_to', 'revenue', 'orders', 'by_day' ],
		];
	}
}
