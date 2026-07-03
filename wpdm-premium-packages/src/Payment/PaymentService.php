<?php
/**
 * Payment Service
 *
 * Orchestrates payment gateway registration, discovery, and processing.
 *
 * @package WPDMPP\Payment
 * @since 7.0.0
 */

namespace WPDMPP\Payment;

defined('ABSPATH') || exit;

class PaymentService {

    /**
     * Registered gateways
     *
     * @var array<string, PaymentGatewayInterface>
     */
    private array $gateways = [];

    /**
     * Gateway class map for lazy loading
     *
     * @var array<string, string>
     */
    private array $gatewayClasses = [];

    /**
     * Whether external gateway filters have been applied
     *
     * @var bool
     */
    private bool $filtersApplied = false;

    /**
     * Singleton instance
     *
     * @var PaymentService|null
     */
    private static ?PaymentService $instance = null;

    /**
     * Get singleton instance
     *
     * @return PaymentService
     */
    public static function instance(): PaymentService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        $this->registerDefaultGateways();
    }

    /**
     * Register default payment gateways
     */
    private function registerDefaultGateways(): void {
        $this->gatewayClasses = [
            'paypal' => Gateways\PayPalGateway::class,
            'cash' => Gateways\CashGateway::class,
            'cheque' => Gateways\ChequeGateway::class,
            'testpay' => Gateways\TestPayGateway::class,
        ];
    }

    /**
     * Apply external gateway filters (deferred until first access)
     *
     * This is deferred because add-on plugins (e.g. wpdmpp-stripe) may not
     * be loaded yet when PaymentService is first instantiated.
     */
    private function applyGatewayFilters(): void {
        if ($this->filtersApplied) {
            return;
        }
        $this->filtersApplied = true;
        $this->gatewayClasses = apply_filters('wpdmpp_payment_gateways', $this->gatewayClasses);
    }

    /**
     * Register a payment gateway
     *
     * @param string $id Gateway ID
     * @param string $className Fully qualified class name
     */
    public function registerGateway(string $id, string $className): void {
        $this->gatewayClasses[$id] = $className;
    }

    /**
     * Get a gateway instance by ID
     *
     * @param string $id Gateway ID
     * @return PaymentGatewayInterface|null
     */
    public function getGateway(string $id): ?PaymentGatewayInterface {
        $this->applyGatewayFilters();
        $id = strtolower($id); // Normalize to lowercase

        // Return cached instance
        if (isset($this->gateways[$id])) {
            return $this->gateways[$id];
        }

        // Lazy load from class map
        if (isset($this->gatewayClasses[$id])) {
            $className = $this->gatewayClasses[$id];
            if (class_exists($className)) {
                $this->gateways[$id] = new $className();
                return $this->gateways[$id];
            }
        }

        return null;
    }

    /**
     * Get all registered gateways
     *
     * @param bool $enabledOnly Only return enabled gateways
     * @return array<string, PaymentGatewayInterface>
     */
    public function getGateways(bool $enabledOnly = false): array {
        $this->applyGatewayFilters();
        // Instantiate all gateways
        foreach ($this->gatewayClasses as $id => $className) {
            if (!isset($this->gateways[$id]) && class_exists($className)) {
                $this->gateways[$id] = new $className();
            }
        }

        if (!$enabledOnly) {
            return $this->gateways;
        }

        // Filter to enabled only
        return array_filter($this->gateways, function (PaymentGatewayInterface $gateway) {
            return $gateway->isEnabled();
        });
    }

    /**
     * Get available gateway options for checkout
     *
     * @return array Array of gateway data for checkout display
     */
    public function getCheckoutOptions(): array {
        $options = [];
        $enabledGateways = $this->getGateways(true);

        foreach ($enabledGateways as $gateway) {
            $options[] = [
                'id' => $gateway->getId(),
                'title' => $gateway->getTitle(),
                'description' => $gateway->getDescription(),
                'icon' => $gateway->getIcon(),
                'fields' => $gateway->getCheckoutFields(),
                'supports' => [
                    'recurring' => $gateway->supports('recurring'),
                    'refunds' => $gateway->supports('refunds'),
                ],
            ];
        }

        return $options;
    }

    /**
     * Process a payment
     *
     * @param string $gatewayId Gateway ID
     * @param array $orderData Order data
     * @return array Result with success status and data
     */
    public function processPayment(string $gatewayId, array $orderData): array {
        $gateway = $this->getGateway($gatewayId);

        if (!$gateway) {
            return [
                'success' => false,
                'message' => __('Invalid payment gateway.', 'wpdm-premium-packages'),
            ];
        }

        if (!$gateway->isEnabled()) {
            return [
                'success' => false,
                'message' => __('Payment gateway is not available.', 'wpdm-premium-packages'),
            ];
        }

        // Validate payment data
        $validation = $gateway->validatePaymentData($orderData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => __('Invalid payment data.', 'wpdm-premium-packages'),
                'errors' => $validation['errors'],
            ];
        }

        // Process the payment
        try {
            return $gateway->processPayment($orderData);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook for a gateway
     *
     * @param string $gatewayId Gateway ID
     * @return array Result
     */
    public function handleWebhook(string $gatewayId): array {
        $gateway = $this->getGateway($gatewayId);

        if (!$gateway) {
            return [
                'success' => false,
                'message' => 'Invalid gateway',
            ];
        }

        if (!$gateway->supports('webhooks')) {
            return [
                'success' => false,
                'message' => 'Gateway does not support webhooks',
            ];
        }

        // Check if gateway has webhook handler
        if (method_exists($gateway, 'handleWebhook')) {
            return $gateway->handleWebhook();
        }

        return [
            'success' => false,
            'message' => 'Webhook handler not implemented',
        ];
    }

    /**
     * Render settings for all gateways
     *
     * @return string HTML for all gateway settings
     */
    public function renderAllSettings(): string {
        $html = '';
        $gateways = $this->getGateways();

        foreach ($gateways as $gateway) {
            $html .= sprintf(
                '<div class="wpdmpp-gateway-settings" data-gateway="%s">',
                esc_attr($gateway->getId())
            );
            $html .= sprintf(
                '<h3>%s</h3>',
                esc_html($gateway->getTitle())
            );
            $html .= $gateway->renderSettings();
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Get gateway by order
     *
     * @param int|object $order Order ID or object
     * @return PaymentGatewayInterface|null
     */
    public function getGatewayByOrder($order): ?PaymentGatewayInterface {
        if (is_numeric($order)) {
            global $wpdb;
            $gatewayId = $wpdb->get_var($wpdb->prepare(
                "SELECT payment_method FROM {$wpdb->prefix}ahm_orders WHERE ID = %d",
                $order
            ));
        } elseif (is_object($order) && isset($order->payment_method)) {
            $gatewayId = $order->payment_method;
        } else {
            return null;
        }

        return $gatewayId ? $this->getGateway($gatewayId) : null;
    }

    /**
     * Register webhook listener and AJAX handlers
     */
    public function register(): void {
        add_action('init', [$this, 'webHookListener']);
        add_action('wp_ajax_wpdmpp_payment_intent', [$this, 'payNow']);

        // Register gateway-specific AJAX handlers
        $paypal = $this->getGateway('paypal');
        if ($paypal && method_exists($paypal, 'registerWebhookAjax')) {
            $paypal->registerWebhookAjax();
        }
    }

    /**
     * Handle incoming webhook requests
     */
    public function webHookListener(): void {
        $paymentMethod = wpdm_query_var('wpdmpp_payment', 'txt');
        if (!$paymentMethod) {
            return;
        }

        $gateway = $this->getGateway(strtolower($paymentMethod));
        if ($gateway && method_exists($gateway, 'handleWebhook')) {
            $gateway->handleWebhook();
        }
    }

    /**
     * Get payment method names (capitalized settings keys)
     *
     * @param bool $enabledOnly Only return enabled gateways
     * @return array
     */
    public function getPaymentMethodNames(bool $enabledOnly = false): array {
        $gateways = $this->getGateways($enabledOnly);
        $names = [];
        foreach ($gateways as $gateway) {
            $names[] = $gateway->getSettingsKey();
        }
        return apply_filters('wpdmpp_payment_methods', $names);
    }

    /**
     * Get gateway logo URL
     *
     * @param string $method Payment method name (capitalized or lowercase)
     * @return string Logo URL
     */
    public function getLogo(string $method): string {
        $gateway = $this->getGateway(strtolower($method));
        if ($gateway) {
            return $gateway->getIcon();
        }
        return '';
    }

    /**
     * Get gateway display title
     *
     * @param string $method Payment method name
     * @return string Gateway title
     */
    public function getGatewayTitle(string $method): string {
        $gateway = $this->getGateway(strtolower($method));
        if ($gateway) {
            $customTitle = get_wpdmpp_option($gateway->getSettingsKey() . '/title', '');
            return $customTitle ?: $gateway->getTitle();
        }
        return $method;
    }

    /**
     * Check if billing form is required
     *
     * @param string $method Payment method name
     * @return bool
     */
    public function needsBillingForm(string $method = ''): bool {
        return get_wpdmpp_option('billing_address') == 1 || wpdmpp_tax_active();
    }

    /**
     * Get checkout button HTML (Smart Buttons for PayPal, checkout fields for others)
     *
     * @param string $method Payment method name
     * @param string $orderId Optional order ID
     * @return string HTML or empty string for default button
     */
    public function getCheckoutButton(string $method, string $orderId = ''): string {
        $gateway = $this->getGateway(strtolower($method));
        if (!$gateway) {
            return '';
        }

        // PayPal uses Smart Buttons via renderCheckoutButton()
        if (strtolower($method) === 'paypal' && method_exists($gateway, 'renderCheckoutButton')) {
            $amount = 0;
            if ($orderId) {
                $order = \WPDMPP\Order\OrderService::instance()->getOrder($orderId);
                $amount = $order ? (float) $order->getTotal() : 0;
            }
            $result = $gateway->renderCheckoutButton($orderId, $amount);
            if ($result !== '') {
                return $result;
            }
        }

        // Other gateways: return checkout fields if any
        return $gateway->getCheckoutFields();
    }

    /**
     * Get custom checkout form HTML for a gateway
     *
     * @param string $method Payment method name
     * @return string HTML or empty string
     */
    public function getGatewayCheckoutForm(string $method): string {
        $gateway = $this->getGateway(strtolower($method));
        return $gateway ? $gateway->getCheckoutFields() : '';
    }

    /**
     * Get payment method instance (backward compat for templates)
     *
     * Returns an object with GatewayName property for template compatibility.
     *
     * @param string $method Payment method name
     * @return object Object with GatewayName property
     */
    public function getMethod(string $method) {
        $gateway = $this->getGateway(strtolower($method));
        if ($gateway) {
            $obj = new \stdClass();
            $obj->GatewayName = $this->getGatewayTitle($method);
            return $obj;
        }

        // Fallback: return object with method name
        $obj = new \stdClass();
        $obj->GatewayName = str_replace("WPDM_", "", $method) . ' [disabled]';
        return $obj;
    }

    /**
     * Cancel subscription for an order
     *
     * @param string $orderId Order ID
     * @return bool
     */
    public function cancelSubscription(string $orderId): bool {
        $gateway = $this->getGatewayByOrder($orderId);
        if ($gateway && method_exists($gateway, 'cancelSubscription')) {
            return (bool) $gateway->cancelSubscription($orderId);
        }
        return false;
    }

    /**
     * Handle pay-now AJAX request for existing orders
     *
     * @param array $post_data
     */
    public function payNow($post_data = []): void {
        global $current_user;
        $current_user = wp_get_current_user();
        if (!$post_data || count($post_data) == 0) $post_data = wpdm_sanitize_array($_POST);
        $corder = \WPDMPP\Order\OrderService::instance()->getOrder(sanitize_text_field($post_data['order_id']));
        if (!$corder) {
            wp_die(esc_html__('Order not found', 'wpdm-premium-packages'));
        }

        wpdmpp_empty_cart();

        \WPDM\__\Session::set('renew_orderid', $corder->getOrderId());

        $total = $corder->getTotal();
        $method = sanitize_text_field($post_data['payment_method'] ?? $corder->getPaymentMethod());

        ob_start();
        $billing_required = $this->needsBillingForm($method);
        $billing = [];
        if (is_user_logged_in()) {
            $billing = maybe_unserialize(get_user_meta(get_current_user_id(), 'user_billing_shipping', true));
            $billing = is_array($billing) && isset($billing['billing']) ? $billing['billing'] : $billing;
            if (!is_array($billing)) $billing = [];
            $billing['email'] = isset($billing['email']) ? $billing['email'] : $current_user->user_email;
        }

        $checkout_page = '-2col';
        if ($billing_required) {
            include \WPDM\__\Template::locate("checkout-cart{$checkout_page}/checkout-billing-info.php", WPDMPP_BASE_DIR . 'includes/libs/templates' . WPDM()->bsversion . "/", WPDMPP_TPL_FALLBACK);
        } else {
            include \WPDM\__\Template::locate("checkout-cart{$checkout_page}/checkout-name-email.php", WPDMPP_BASE_DIR . 'includes/libs/templates' . WPDM()->bsversion . "/", WPDMPP_TPL_FALLBACK);
        }
        $billing_form = ob_get_clean();

        $cb = $this->getCheckoutButton($method, $corder->getOrderId());
        if ($cb !== '') {
            echo $cb;
            wp_die();
        }

        $result = $this->processPayment(strtolower($method), [
            'order_id'    => $corder->getOrderId(),
            'order_title' => "Order# " . $corder->getOrderId(),
            'amount'      => number_format($total, 2, ".", ""),
        ]);

        if (!empty($result['redirect'])) {
            wpdmpp_js_redirect($result['redirect']);
        }

        wp_die();
    }
}
