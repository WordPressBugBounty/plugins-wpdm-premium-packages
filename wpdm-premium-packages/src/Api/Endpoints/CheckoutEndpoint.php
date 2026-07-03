<?php
/**
 * Checkout REST Endpoint
 *
 * Handles checkout-related REST API endpoints:
 * - GET  /checkout              - Get checkout data
 * - POST /checkout              - Process checkout
 * - POST /checkout/validate     - Validate billing info
 * - POST /checkout/paypal/create-order        - Create PayPal order
 * - POST /checkout/paypal/capture             - Capture PayPal payment
 * - POST /checkout/paypal/create-subscription - Create PayPal subscription
 * - POST /checkout/paypal/activate-subscription - Activate PayPal subscription
 * - GET  /checkout/order/{id}                 - Get order status
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\Libs\Logger;
use WPDMPP\Order\OrderService;
use WPDMPP\Payment\PaymentService;
use WPDMPP\Payment\Gateways\PayPalGateway;
use WPDM\__\Session;
use WPDMPP\Cart\CartService;

defined('ABSPATH') || exit;

class CheckoutEndpoint {

    /**
     * Register checkout routes
     */
    public function register(): void {
        $namespace = RestApi::API_NAMESPACE;

        // Get checkout data
        register_rest_route($namespace, '/checkout', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getCheckoutData'],
            'permission_callback' => '__return_true',
        ]);

        // Process checkout
        register_rest_route($namespace, '/checkout', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'processCheckout'],
            'permission_callback' => '__return_true',
            'args' => $this->getCheckoutArgs(),
        ]);

        // Validate billing info
        register_rest_route($namespace, '/checkout/validate', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'validateBilling'],
            'permission_callback' => '__return_true',
            'args' => $this->getBillingArgs(),
        ]);

        // PayPal - Create order
        register_rest_route($namespace, '/checkout/paypal/create-order', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'paypalCreateOrder'],
            'permission_callback' => '__return_true',
            'args' => $this->getBillingArgs(),
        ]);

        // PayPal - Capture payment
        register_rest_route($namespace, '/checkout/paypal/capture', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'paypalCapturePayment'],
            'permission_callback' => '__return_true',
            'args' => [
                'paypal_order_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // PayPal - Create subscription
        register_rest_route($namespace, '/checkout/paypal/create-subscription', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'paypalCreateSubscription'],
            'permission_callback' => '__return_true',
            'args' => $this->getBillingArgs(),
        ]);

        // PayPal - Activate subscription
        register_rest_route($namespace, '/checkout/paypal/activate-subscription', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'paypalActivateSubscription'],
            'permission_callback' => '__return_true',
            'args' => [
                'subscription_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Get order status
        register_rest_route($namespace, '/checkout/order/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getOrderStatus'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Get checkout validation arguments
     *
     * @return array
     */
    private function getCheckoutArgs(): array {
        return array_merge($this->getBillingArgs(), [
            'payment_method' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'privacy_agreed' => [
                'type' => 'boolean',
                'default' => false,
            ],
        ]);
    }

    /**
     * Get billing field arguments
     *
     * @return array
     */
    private function getBillingArgs(): array {
        return [
            'first_name' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'email' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function($value) {
                    return is_email($value) ? true : new \WP_Error('invalid_email', __('Please enter a valid email address.', 'wpdm-premium-packages'));
                },
            ],
            // Billing address — collected only when tax calculation is enabled.
            // Country/state drive the tax rate; the rest are stored for invoices.
            'company' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'country' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'state' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address_1' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address_2' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'postcode' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'phone' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Build the billing info array sent to OrderService from the request.
     *
     * When tax is enabled the full address is captured; country/state are read
     * by OrderService::createFromCart() to compute and persist order tax.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    private function buildBillingInfo(\WP_REST_Request $request): array {
        return [
            'first_name'  => $request->get_param('first_name'),
            'last_name'   => $request->get_param('last_name') ?: '',
            'order_email' => $request->get_param('email'),
            'company'     => $request->get_param('company') ?: '',
            'country'     => $request->get_param('country') ?: '',
            'state'       => $request->get_param('state') ?: '',
            'address_1'   => $request->get_param('address_1') ?: '',
            'address_2'   => $request->get_param('address_2') ?: '',
            'city'        => $request->get_param('city') ?: '',
            'postcode'    => $request->get_param('postcode') ?: '',
            'phone'       => $request->get_param('phone') ?: '',
        ];
    }

    /**
     * Get checkout data
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getCheckoutData(\WP_REST_Request $request): \WP_REST_Response {
        $cart_items = \wpdmpp_get_cart_data();

        if (empty($cart_items)) {
            return RestApi::error(
                __('Your cart is empty.', 'wpdm-premium-packages'),
                400,
                ['redirect' => \wpdmpp_cart_page()]
            );
        }

        $current_user = wp_get_current_user();
        $user_data = null;

        if ($current_user->ID > 0) {
            $user_data = [
                'id' => $current_user->ID,
                'email' => $current_user->user_email,
                'first_name' => $current_user->first_name ?: '',
                'last_name' => $current_user->last_name ?: '',
                'display_name' => $current_user->display_name,
            ];
        }

        $payment_methods = $this->formatPaymentMethods();
        $cart_summary = $this->getCartSummary($cart_items);

        $settings = maybe_unserialize(get_option('_wpdmpp_settings', []));
        $require_privacy = isset($settings['checkout_privacy']) && $settings['checkout_privacy'];

        return RestApi::success([
            'cart' => $cart_summary,
            'user' => $user_data,
            'is_logged_in' => $current_user->ID > 0,
            'payment_methods' => $payment_methods,
            'billing_fields' => $this->getBillingFields($user_data),
            'require_privacy' => $require_privacy,
            'privacy_url' => get_privacy_policy_url(),
            'terms_url' => get_option('wpdmpp_terms_page') ? get_permalink(get_option('wpdmpp_terms_page')) : '',
            'currency' => [
                'code' => \wpdmpp_currency_code(),
                'symbol' => \wpdmpp_currency_sign(),
                'position' => \wpdmpp_currency_sign_position(),
            ],
        ]);
    }

    /**
     * Process checkout
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function processCheckout(\WP_REST_Request $request): \WP_REST_Response {
        // Check cart
        $cart_items = \wpdmpp_get_cart_data();
        if (empty($cart_items)) {
            return RestApi::error(__('Your cart is empty.', 'wpdm-premium-packages'), 400);
        }

        // Validate billing
        $validation = $this->validateBillingData($request);
        if (is_wp_error($validation)) {
            return RestApi::error(
                $validation->get_error_message(),
                400,
                ['errors' => $validation->get_error_data()]
            );
        }

        // Check privacy agreement if required
        $settings = maybe_unserialize(get_option('_wpdmpp_settings', []));
        if (!empty($settings['checkout_privacy']) && !$request->get_param('privacy_agreed')) {
            return RestApi::error(__('Please agree to the privacy policy.', 'wpdm-premium-packages'), 400);
        }

        // Re-validate any applied coupon against the billing email — the
        // apply-time check can't see the email a guest enters later in the
        // same form, so this is the authoritative gate.
        $coupon = \wpdmpp_get_cart_coupon();
        if (!empty($coupon['code'])) {
            $couponValidation = \WPDMPP\Coupon\CouponService::getInstance()->validateCoupon(
                (string) $coupon['code'],
                (float) \wpdmpp_get_cart_subtotal(),
                $cart_items,
                $request->get_param('email') ?: null
            );
            if (!$couponValidation['valid']) {
                \WPDMPP\Cart\CartService::instance()->removeCoupon();
                return RestApi::error(
                    sprintf(
                        __('The coupon "%s" was removed: %s', 'wpdm-premium-packages'),
                        $coupon['code'],
                        $couponValidation['message']
                    ),
                    400,
                    ['coupon_removed' => true]
                );
            }
        }

        // Create order via OrderService
        $billingInfo = $this->buildBillingInfo($request);
        $payment_method = $request->get_param('payment_method');
        $orderService = OrderService::instance();
        $order = $orderService->createFromCart($cart_items, $billingInfo, $payment_method);

        if (!$order) {
            if (class_exists('WPDMPP\Libs\Logger')) {
                Logger::error('Checkout failed: Order creation failed', [
                    'user_id' => get_current_user_id(),
                    'payment_method' => $payment_method,
                ]);
            }
            return RestApi::error(__('Failed to create order. Please try again.', 'wpdm-premium-packages'), 500);
        }

        $order_id = $order->getOrderId();
        $order_total = $order->getTotal();

        $response_data = [
            'order_id' => $order_id,
            'total' => $order_total,
            'total_formatted' => \wpdmpp_price_format($order_total),
        ];

        // Route through PaymentService
        $service = PaymentService::instance();
        $gateway = $service->getGateway(strtolower($payment_method));

        if ($gateway && strtolower($payment_method) === 'paypal') {
            $response_data['payment_type'] = 'paypal_smart_buttons';
        } elseif ($gateway) {
            $result = $service->processPayment(strtolower($payment_method), [
                'order_id' => $order_id,
                'billing'  => $request->get_param('billing') ?: [],
            ]);

            // The gateway can fail to initiate payment (misconfigured success/
            // cancel URL, API/auth error, declined setup, etc.). Never report
            // success in that case — otherwise the REST response says "success"
            // with no redirect_url, checkout.js falls back to the order page,
            // and the buyer sees a "completed" order that was never paid.
            if (empty($result['success'])) {
                // Roll back the just-created order so an unpaid attempt is not
                // left indistinguishable from a real "Processing" order.
                $orderService->cancelOrder(
                    $order_id,
                    'Payment initiation failed: ' . ($result['message'] ?? 'Unknown gateway error')
                );

                if (class_exists('WPDMPP\Libs\Logger')) {
                    Logger::error('Checkout failed: gateway did not initiate payment', [
                        'order_id'       => $order_id,
                        'payment_method' => $payment_method,
                        'message'        => $result['message'] ?? '',
                    ]);
                }

                $error_data = [];
                if (!empty($result['errors'])) {
                    $error_data['errors'] = $result['errors'];
                }

                return RestApi::error(
                    !empty($result['message'])
                        ? $result['message']
                        : __('Payment could not be processed. Please try again.', 'wpdm-premium-packages'),
                    400,
                    $error_data
                );
            }

            if (!empty($result['redirect_url'])) {
                $response_data['payment_type'] = 'redirect';
            }
            // Merge gateway-specific data into response
            unset($result['success']);
            $response_data = array_merge($response_data, $result);
        }

        return RestApi::success($response_data, __('Order created successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Validate billing data
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    private function validateBillingData(\WP_REST_Request $request) {
        $errors = [];

        $first_name = $request->get_param('first_name');
        if (empty($first_name)) {
            $errors['first_name'] = __('First name is required.', 'wpdm-premium-packages');
        }

        $email = $request->get_param('email');
        if (empty($email)) {
            $errors['email'] = __('Email is required.', 'wpdm-premium-packages');
        } elseif (!is_email($email)) {
            $errors['email'] = __('Please enter a valid email address.', 'wpdm-premium-packages');
        }

        // When tax calculation is enabled, the billing location is required so the
        // correct rate can be applied. Strict check matches CartService::calculateTax.
        if (function_exists('get_wpdmpp_option') && (int) get_wpdmpp_option('tax/enable', 0, 'int') === 1) {
            if (empty($request->get_param('country'))) {
                $errors['country'] = __('Country is required.', 'wpdm-premium-packages');
            }
            if (empty($request->get_param('state'))) {
                $errors['state'] = __('State / province is required.', 'wpdm-premium-packages');
            }
            if (empty($request->get_param('address_1'))) {
                $errors['address_1'] = __('Address is required.', 'wpdm-premium-packages');
            }
            if (empty($request->get_param('city'))) {
                $errors['city'] = __('City is required.', 'wpdm-premium-packages');
            }
            if (empty($request->get_param('postcode'))) {
                $errors['postcode'] = __('Zip / postal code is required.', 'wpdm-premium-packages');
            }
        }

        if (!empty($errors)) {
            return new \WP_Error('validation_failed', __('Please fix the errors below.', 'wpdm-premium-packages'), $errors);
        }

        return true;
    }

    /**
     * Validate billing endpoint
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function validateBilling(\WP_REST_Request $request): \WP_REST_Response {
        $validation = $this->validateBillingData($request);

        if (is_wp_error($validation)) {
            return RestApi::error(
                $validation->get_error_message(),
                400,
                ['errors' => $validation->get_error_data()]
            );
        }

        return RestApi::success([], __('Billing info is valid.', 'wpdm-premium-packages'));
    }

    /**
     * PayPal: Create order
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function paypalCreateOrder(\WP_REST_Request $request): \WP_REST_Response {
        // Validate billing
        $validation = $this->validateBillingData($request);
        if (is_wp_error($validation)) {
            return RestApi::error(
                $validation->get_error_message(),
                400,
                ['errors' => $validation->get_error_data()]
            );
        }

        // Check cart
        $cart_items = \wpdmpp_get_cart_data();
        if (empty($cart_items)) {
            return RestApi::error(__('Your cart is empty.', 'wpdm-premium-packages'), 400);
        }

        // Create local order via OrderService
        $billingInfo = $this->buildBillingInfo($request);
        $orderService = OrderService::instance();
        $order = $orderService->createFromCart($cart_items, $billingInfo, 'PayPal');

        if (!$order) {
            return RestApi::error(__('Failed to create order.', 'wpdm-premium-packages'), 500);
        }

        $order_id = $order->getOrderId();
        $order_total = $order->getTotal();

        try {
            $paypal = new PayPalGateway();
            $paypal_order = $paypal->createOrder((int) $order_id, (float) $order_total);

            if (isset($paypal_order['id'])) {
                // Store PayPal order ID in order meta
                $orderService->getRepository()->updateMeta($order_id, 'paypal_order_id', $paypal_order['id']);

                return RestApi::success([
                    'order_id' => $order_id,
                    'paypal_order_id' => $paypal_order['id'],
                ]);
            }

            return RestApi::error(__('Failed to create PayPal order.', 'wpdm-premium-packages'), 500);

        } catch (\Exception $e) {
            return RestApi::error($e->getMessage(), 500);
        }
    }

    /**
     * PayPal: Capture payment
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function paypalCapturePayment(\WP_REST_Request $request): \WP_REST_Response {
        $paypal_order_id = $request->get_param('paypal_order_id');
        $order_id = $request->get_param('order_id');

        // Verify order exists
        $orderService = OrderService::instance();
        $order = $orderService->getOrder($order_id);

        if (!$order) {
            return RestApi::error(__('Order not found.', 'wpdm-premium-packages'), 404);
        }

        // Verify PayPal order ID matches
        $stored_paypal_id = $order->getMeta('paypal_order_id');
        if ($stored_paypal_id !== $paypal_order_id) {
            return RestApi::error(__('Invalid PayPal order.', 'wpdm-premium-packages'), 400);
        }

        try {
            $paypal = new PayPalGateway();
            $capture_result = $paypal->captureOrder($paypal_order_id);

            if (isset($capture_result['status']) && $capture_result['status'] === 'COMPLETED') {
                // Get capture ID
                $capture_id = '';
                if (isset($capture_result['purchase_units'][0]['payments']['captures'][0]['id'])) {
                    $capture_id = $capture_result['purchase_units'][0]['payments']['captures'][0]['id'];
                }

                // Set transaction ID and complete the order
                $orderService->updateOrder(['trans_id' => $capture_id], $order_id);
                $orderService->completeOrder($order_id, true, 'PayPal');

                // Clear the cart
                WPDMPP()->cart->clear();

                return RestApi::success([
                    'order_id' => $order_id,
                    'transaction_id' => $capture_id,
                    'redirect_url' => \wpdmpp_orders_page('id=' . $order_id),
                ], __('Payment completed successfully!', 'wpdm-premium-packages'));
            }

            return RestApi::error(
                __('Payment could not be completed. Please try again.', 'wpdm-premium-packages'),
                400,
                ['status' => $capture_result['status'] ?? 'UNKNOWN']
            );

        } catch (\Exception $e) {
            return RestApi::error($e->getMessage(), 500);
        }
    }

    /**
     * PayPal: Create subscription (plan + product)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function paypalCreateSubscription(\WP_REST_Request $request): \WP_REST_Response {
        // Validate billing
        $validation = $this->validateBillingData($request);
        if (is_wp_error($validation)) {
            return RestApi::error(
                $validation->get_error_message(),
                400,
                ['errors' => $validation->get_error_data()]
            );
        }

        // Check cart
        $cart_items = \wpdmpp_get_cart_data();
        if (empty($cart_items)) {
            return RestApi::error(__('Your cart is empty.', 'wpdm-premium-packages'), 400);
        }

        // Create local order via OrderService
        $billingInfo = $this->buildBillingInfo($request);
        $orderService = OrderService::instance();
        $order = $orderService->createFromCart($cart_items, $billingInfo, 'PayPal');

        if (!$order) {
            return RestApi::error(__('Failed to create order.', 'wpdm-premium-packages'), 500);
        }

        $order_id = $order->getOrderId();
        $order_total = $order->getTotal();

        try {
            $paypal = new PayPalGateway();

            // Inspect the cart for membership metadata (trial, full price, billing period).
            $trialDays = 0;
            $fullPrice = 0;
            $membershipPeriod = 0;
            $membershipUnit = '';
            $cart = CartService::instance()->getCart();
            foreach ($cart as $item) {
                $info = $item->getInfo();
                if (empty($info)) {
                    continue;
                }
                if (!empty($info['trial_days'])) {
                    $trialDays = (int) $info['trial_days'];
                    $fullPrice = (float) ($info['full_price'] ?? 0);
                }
                if (!empty($info['period']) && !empty($info['period_unit'])) {
                    $membershipPeriod = (int) $info['period'];
                    $membershipUnit = (string) $info['period_unit'];
                }
                if ($trialDays > 0 || $membershipPeriod > 0) {
                    break;
                }
            }

            // Bill on the membership plan's own cycle when present; otherwise fall
            // back to the global order validity period.
            $interval = $membershipPeriod > 0
                ? $paypal->intervalFromPeriod($membershipPeriod, $membershipUnit)
                : $paypal->calculateBillingInterval();

            $productId = $paypal->createSubscriptionProduct($order->getTitle(), $order_id);
            if (!$productId) {
                $reason = $paypal->getLastApiError();
                $message = __('Failed to create PayPal subscription product.', 'wpdm-premium-packages');
                return RestApi::error($reason ? $message . ' ' . $reason : $message, 500);
            }

            // For trial subscriptions, use full price as recurring amount (not $0 order total)
            $recurringPrice = $trialDays > 0 && $fullPrice > 0 ? $fullPrice : (float) $order_total;
            $planId = $paypal->createSubscriptionPlan($productId, $recurringPrice, $interval['count'], $interval['unit'], $trialDays);
            if (!$planId) {
                $reason = $paypal->getLastApiError();
                $message = __('Failed to create PayPal subscription plan.', 'wpdm-premium-packages');
                return RestApi::error($reason ? $message . ' ' . $reason : $message, 500);
            }

            // Store plan_id in order meta
            $orderService->getRepository()->updateMeta($order_id, 'paypal_plan_id', $planId);

            return RestApi::success([
                'order_id' => $order_id,
                'plan_id' => $planId,
            ]);

        } catch (\Exception $e) {
            return RestApi::error($e->getMessage(), 500);
        }
    }

    /**
     * PayPal: Activate subscription after approval
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function paypalActivateSubscription(\WP_REST_Request $request): \WP_REST_Response {
        $subscription_id = $request->get_param('subscription_id');
        $order_id = $request->get_param('order_id');

        // Verify order exists
        $orderService = OrderService::instance();
        $order = $orderService->getOrder($order_id);

        if (!$order) {
            return RestApi::error(__('Order not found.', 'wpdm-premium-packages'), 404);
        }

        try {
            $paypal = new PayPalGateway();
            $subscription = $paypal->verifySubscription($subscription_id);

            if (!$subscription || !isset($subscription['status'])) {
                return RestApi::error(__('Failed to verify subscription.', 'wpdm-premium-packages'), 400);
            }

            if ($subscription['status'] !== 'ACTIVE') {
                return RestApi::error(
                    sprintf(__('Subscription is not active. Status: %s', 'wpdm-premium-packages'), $subscription['status']),
                    400
                );
            }

            // Store subscription_id as trans_id on order
            $orderService->updateOrder(['trans_id' => $subscription_id], $order_id);

            // Complete the order
            $orderService->completeOrder($order_id, true, 'PayPal');

            // Set session for guest orders
            Session::set('guest_order_init', uniqid(), 18000);
            Session::set('guest_order', $order_id, 18000);

            // Clear cart
            WPDMPP()->cart->clear();

            do_action('wpdmpp_payment_completed', $order_id);

            return RestApi::success([
                'order_id' => $order_id,
                'subscription_id' => $subscription_id,
                'redirect_url' => \wpdmpp_orders_page('id=' . $order_id),
            ], __('Subscription activated successfully!', 'wpdm-premium-packages'));

        } catch (\Exception $e) {
            return RestApi::error($e->getMessage(), 500);
        }
    }

    /**
     * Get order status
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getOrderStatus(\WP_REST_Request $request): \WP_REST_Response {
        $order_id = $request->get_param('id');

        $order = OrderService::instance()->getOrder($order_id);

        if (!$order) {
            return RestApi::error(__('Order not found.', 'wpdm-premium-packages'), 404);
        }

        // Security check
        $current_user_id = get_current_user_id();
        if ($order->getUserId() != $current_user_id && !current_user_can('manage_options')) {
            $session_order_id = Session::get('wpdmpp_last_order_id');
            if ($session_order_id != $order_id) {
                return RestApi::error(__('Access denied.', 'wpdm-premium-packages'), 403);
            }
        }

        return RestApi::success([
            'order_id' => $order_id,
            'order_status' => $order->getOrderStatus(),
            'payment_status' => $order->getPaymentStatus(),
            'total' => $order->getTotal(),
            'total_formatted' => $order->getFormattedTotal(),
            'is_completed' => $order->isCompleted() && $order->getPaymentStatus() === 'Completed',
            'success_url' => \wpdmpp_orders_page('id=' . $order_id),
        ]);
    }

    /**
     * Format payment methods
     *
     * @return array
     */
    private function formatPaymentMethods(): array {
        $service = PaymentService::instance();
        $gateways = $service->getGateways(true);

        // Build gateway map keyed by settings key
        $gatewayMap = [];
        foreach ($gateways as $gateway) {
            $gatewayMap[$gateway->getSettingsKey()] = $gateway;
        }

        // Apply saved sort order from pmorders setting
        $savedOrder = get_wpdmpp_option('pmorders', []);
        if (!empty($savedOrder) && is_array($savedOrder) && count($savedOrder) >= count($gatewayMap)) {
            $sorted = [];
            foreach ($savedOrder as $key) {
                if (isset($gatewayMap[$key])) {
                    $sorted[$key] = $gatewayMap[$key];
                }
            }
            // Append any gateways not in saved order
            foreach ($gatewayMap as $key => $gw) {
                if (!isset($sorted[$key])) {
                    $sorted[$key] = $gw;
                }
            }
            $gatewayMap = $sorted;
        }

        $formatted = [];
        foreach ($gatewayMap as $gateway) {
            $settingsKey = $gateway->getSettingsKey();
            $method_data = [
                'id' => $settingsKey,
                'name' => get_wpdmpp_option($settingsKey . '/title', '') ?: $gateway->getTitle(),
                'description' => $gateway->getDescription(),
                'logo' => $gateway->getIcon(),
            ];

            if ($gateway->getId() === 'paypal') {
                $method_data['client_id'] = method_exists($gateway, 'getClientId') ? $gateway->getClientId() : '';
                $method_data['mode'] = method_exists($gateway, 'getMode') ? $gateway->getMode() : 'sandbox';
                $method_data['currency'] = \wpdmpp_currency_code();
            }

            $formatted[] = $method_data;
        }

        return $formatted;
    }

    /**
     * Get billing fields configuration
     *
     * @param array|null $user_data
     * @return array
     */
    private function getBillingFields(?array $user_data = null): array {
        return [
            [
                'id' => 'first_name',
                'label' => __('First Name', 'wpdm-premium-packages'),
                'type' => 'text',
                'required' => true,
                'value' => $user_data['first_name'] ?? '',
                'autocomplete' => 'given-name',
            ],
            [
                'id' => 'last_name',
                'label' => __('Last Name', 'wpdm-premium-packages'),
                'type' => 'text',
                'required' => false,
                'value' => $user_data['last_name'] ?? '',
                'autocomplete' => 'family-name',
            ],
            [
                'id' => 'email',
                'label' => __('Email Address', 'wpdm-premium-packages'),
                'type' => 'email',
                'required' => true,
                'value' => $user_data['email'] ?? '',
                'autocomplete' => 'email',
            ],
        ];
    }

    /**
     * Get cart summary
     *
     * @param array $cart_items
     * @return array
     */
    private function getCartSummary(array $cart_items): array {
        $items = [];
        $coupon = \wpdmpp_get_cart_coupon();

        foreach ($cart_items as $pid => $item) {
            if (!$pid || get_post_type($pid) !== 'wpdmpro') {
                continue;
            }

            $post = get_post($pid);
            if (!$post) {
                continue;
            }

            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
            $price = isset($item['price']) ? (float) $item['price'] : 0;
            $prices = isset($item['prices']) ? (float) $item['prices'] : 0;
            $unit_price = $price + $prices;

            // Get thumbnail
            $thumbnail_id = get_post_thumbnail_id($pid);
            $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'thumbnail') : '';

            if (!$thumbnail_url) {
                $icon = get_post_meta($pid, '__wpdm_icon', true);
                $thumbnail_url = $icon ?: '';
            }

            $items[] = [
                'id' => $pid,
                'name' => $post->post_title,
                'thumbnail' => $thumbnail_url,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'line_total' => $unit_price * $quantity,
                'unit_price_formatted' => \wpdmpp_price_format($unit_price),
                'line_total_formatted' => \wpdmpp_price_format($unit_price * $quantity),
                'license' => $item['license'] ?? '',
                'variation' => $item['variation'] ?? [],
            ];
        }

        return [
            'items' => $items,
            'item_count' => count($items),
            'subtotal' => (float) \wpdmpp_get_cart_subtotal(),
            'discount' => (float) \wpdmpp_get_cart_discount(),
            'total' => (float) \wpdmpp_get_cart_total(),
            'subtotal_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_subtotal()),
            'discount_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_discount()),
            'total_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_total()),
            'coupon' => $coupon ? [
                'code' => $coupon['code'] ?? '',
                'discount' => (float) ($coupon['discount'] ?? 0),
            ] : null,
        ];
    }
}
