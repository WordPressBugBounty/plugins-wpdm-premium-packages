<?php
/**
 * PayPal Payment Gateway
 *
 * Modern PayPal gateway implementation with Smart Buttons support.
 *
 * @package WPDMPP\Payment\Gateways
 * @since 7.0.0
 */

namespace WPDMPP\Payment\Gateways;

use WPDMPP\Payment\AbstractGateway;
use WPDMPP\Order\OrderService;
use WPDMPP\Cart\CartService;
use WPDM\__\Session;

defined('ABSPATH') || exit;

class PayPalGateway extends AbstractGateway {

    /**
     * Gateway ID
     *
     * @var string
     */
    protected string $id = 'paypal';

    /**
     * Settings key
     *
     * @var string
     */
    protected string $settingsKey = 'PayPal';

    /**
     * Gateway title
     *
     * @var string
     */
    protected string $title = 'PayPal';

    /**
     * Gateway description
     *
     * @var string
     */
    protected string $description = 'Pay securely via PayPal with credit/debit cards or PayPal account.';

    /**
     * Supported features
     *
     * @var array
     */
    protected array $supports = ['recurring', 'webhooks', 'refunds'];

    /**
     * PayPal API URLs
     */
    private const API_LIVE = 'api-m.paypal.com';
    private const API_SANDBOX = 'api-m.sandbox.paypal.com';

    /**
     * Human-readable reason for the most recent PayPal API failure, surfaced to
     * the caller so checkout errors are actionable instead of generic.
     */
    private ?string $lastApiError = null;

    /**
     * Reason for the most recent API failure on this instance, or null.
     */
    public function getLastApiError(): ?string {
        return $this->lastApiError;
    }

    /**
     * Build a readable message from a PayPal error response body.
     *
     * @param mixed $body Decoded JSON body (array) or null.
     * @param int   $code HTTP status code.
     */
    private function formatApiError($body, int $code): string {
        if (is_array($body)) {
            $msg = $body['message'] ?? ($body['error_description'] ?? '');
            if (!empty($body['details'][0]) && is_array($body['details'][0])) {
                $d = $body['details'][0];
                $detail = trim(($d['issue'] ?? '') . ' ' . ($d['description'] ?? ''));
                if (!empty($d['field'])) {
                    $detail = trim($detail . ' [field: ' . $d['field'] . ']');
                }
                if ($detail !== '') {
                    $msg = trim($msg . ' (' . $detail . ')');
                }
            }
            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }
        return sprintf(__('PayPal returned HTTP %d.', 'wpdm-premium-packages'), $code);
    }

    /**
     * Whether a URL is safe to send to PayPal as home_url/image_url.
     *
     * PayPal rejects the whole product-create request with
     * INVALID_PARAMETER_SYNTAX when these point at non-public hosts
     * (localhost, bare IPs, dev-only TLDs like .local / .test). They are
     * optional metadata, so only genuine public HTTPS hosts qualify.
     */
    private function isPublicHttpsUrl(string $url): bool {
        if (stripos($url, 'https://') !== 0) {
            return false;
        }

        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || strpos($host, '.') === false) {
            return false;
        }

        // Bare IP address (IPv4/IPv6) — PayPal expects a domain name.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Reserved / dev-only TLDs that are not publicly resolvable.
        $reservedTlds = ['local', 'localhost', 'test', 'example', 'invalid', 'internal', 'localdomain'];
        $tld = substr($host, strrpos($host, '.') + 1);

        return !in_array($tld, $reservedTlds, true);
    }

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->icon = defined('WPDMPP_BASE_URL')
            ? WPDMPP_BASE_URL . 'assets/images/paypal.svg'
            : '';

        // Migrate legacy 'Paypal' settings key to 'PayPal'
        $all = get_option('_wpdmpp_settings', []);
        $changed = false;
        if (isset($all['Paypal']) && !isset($all['PayPal'])) {
            $all['PayPal'] = $all['Paypal'];
            unset($all['Paypal']);
            $changed = true;
        }
        if (!empty($all['pmorders']) && is_array($all['pmorders'])) {
            $key = array_search('Paypal', $all['pmorders'], true);
            if ($key !== false) {
                $all['pmorders'][$key] = 'PayPal';
                $changed = true;
            }
        }
        if ($changed) {
            update_option('_wpdmpp_settings', $all);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool {
        return (bool) get_wpdmpp_option('PayPal/enabled', false);
    }

    /**
     * Get PayPal mode (sandbox/production)
     *
     * @return string
     */
    public function getMode(): string {
        return get_wpdmpp_option('PayPal/Paypal_mode', 'production');
    }

    /**
     * Check if in sandbox mode
     *
     * @return bool
     */
    public function isSandbox(): bool {
        return $this->getMode() === 'sandbox';
    }

    /**
     * Get client ID based on mode
     *
     * @return string
     */
    public function getClientId(): string {
        if ($this->isSandbox()) {
            return get_wpdmpp_option('PayPal/client_id_sandbox', '');
        }
        return get_wpdmpp_option('PayPal/client_id', '');
    }

    /**
     * Get client secret based on mode
     *
     * @return string
     */
    private function getClientSecret(): string {
        if ($this->isSandbox()) {
            return get_wpdmpp_option('PayPal/client_secret_sandbox', '');
        }
        return get_wpdmpp_option('PayPal/client_secret', '');
    }

    /**
     * Get API domain based on mode
     *
     * @return string
     */
    private function getApiDomain(): string {
        return $this->isSandbox() ? self::API_SANDBOX : self::API_LIVE;
    }

    /**
     * Get access token for API calls
     *
     * @return string|false
     */
    public function getAccessToken() {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();

        if (empty($clientId) || empty($clientSecret)) {
            $this->log('Missing PayPal credentials', [], 'error');
            return false;
        }

        $auth = base64_encode($clientId . ':' . $clientSecret);
        $apiDomain = $this->getApiDomain();

        $response = wp_remote_post("https://{$apiDomain}/v1/oauth2/token", [
            'timeout' => 90,
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . $auth,
            ],
            'body' => ['grant_type' => 'client_credentials'],
        ]);

        if (is_wp_error($response)) {
            $this->log('Failed to get access token', ['error' => $response->get_error_message()], 'error');
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        if (!is_object($data) || !isset($data->access_token)) {
            $this->log('Invalid access token response', ['response' => $data], 'error');
            return false;
        }

        return $data->access_token;
    }

    /**
     * {@inheritdoc}
     */
    public function renderSettings(): string {
        return $this->formStart()
            . $this->selectField(
                'Paypal_mode',
                __('Environment', 'wpdm-premium-packages'),
                [
                    'production' => __('Live', 'wpdm-premium-packages'),
                    'sandbox' => __('Sandbox (Test)', 'wpdm-premium-packages'),
                ],
                __('Use Sandbox for testing, Live for real transactions.', 'wpdm-premium-packages')
            )
            . $this->separator()
            . $this->notice(
                sprintf(
                    '<strong>%s</strong> %s <a href="https://developer.paypal.com/dashboard/applications/live" target="_blank">%s</a> %s <a href="https://developer.paypal.com/dashboard/applications/sandbox" target="_blank">%s</a>',
                    __('Setup:', 'wpdm-premium-packages'),
                    __('Get your API credentials from', 'wpdm-premium-packages'),
                    __('PayPal Developer Dashboard (Live)', 'wpdm-premium-packages'),
                    __('or', 'wpdm-premium-packages'),
                    __('Sandbox', 'wpdm-premium-packages')
                )
            )
            . $this->heading(__('Live API Credentials', 'wpdm-premium-packages'))
            . $this->textField(
                'client_id',
                __('Client ID', 'wpdm-premium-packages'),
                '',
                __('From your Live PayPal app.', 'wpdm-premium-packages')
            )
            . $this->passwordField(
                'client_secret',
                __('Client Secret', 'wpdm-premium-packages'),
                __('Keep this secret. Never share publicly.', 'wpdm-premium-packages')
            )
            . $this->heading(__('Sandbox API Credentials', 'wpdm-premium-packages'))
            . $this->textField(
                'client_id_sandbox',
                __('Client ID (Sandbox)', 'wpdm-premium-packages'),
                '',
                __('From your Sandbox PayPal app.', 'wpdm-premium-packages')
            )
            . $this->passwordField(
                'client_secret_sandbox',
                __('Client Secret (Sandbox)', 'wpdm-premium-packages')
            )
            . $this->separator()
            . $this->heading(__('Webhook Configuration', 'wpdm-premium-packages'))
            . $this->readonlyField(
                __('Webhook URL', 'wpdm-premium-packages'),
                site_url('/wp-json/wpdmpp/v1/paypal/webhook'),
                __('Add this URL to your PayPal app webhook settings. Click to copy.', 'wpdm-premium-packages')
            )
            . $this->panel($this->getWebhookPanelContent())
            . $this->formEnd();
    }

    /**
     * Get the webhook panel content with button and status
     *
     * @return string HTML
     */
    private function getWebhookPanelContent(): string {
        $webhook = json_decode(get_option('wpdmpp_paypal_webhook', '{}'));
        $webhookStatus = $webhook && isset($webhook->id)
            ? sprintf('%s WebHook is active (ID: %s)', \WPDMPP\UI\Icons::get('check-double', 14), esc_html($webhook->id))
            : \WPDMPP\UI\Icons::get('arrow-left', 14) . ' Save PayPal API info, then click the button to create webhook';

        $nonce = wp_create_nonce('wpdmpp_paypal_create_webhook');
        $ajaxUrl = esc_url(admin_url('admin-ajax.php'));

        return sprintf(
            '<div class="media">
                <div class="pull-left">
                    <button id="wpdmpp-paypal-create-webhook"
                            type="button"
                            class="btn btn-info">
                        %s
                    </button>
                </div>
                <div id="wpdmpp-paypal-webhook-status" class="media-body text-right text-success" style="line-height: 34px;">
                    %s
                </div>
            </div>
            <script>
            jQuery(function($) {
                $("#wpdmpp-paypal-create-webhook").on("click", function() {
                    var $btn = $(this), html = $btn.html();
                    $btn.html((typeof wpdmppIcons !== "undefined" ? wpdmppIcons.spinner : "") + " Creating...").attr("disabled", true);
                    $.post("%s", { action: "wpdmpp_paypal_create_webhook", nonce: "%s" }, function(res) {
                        $btn.html(html).removeAttr("disabled");
                        if (res.success) {
                            $("#wpdmpp-paypal-webhook-status").html(\'%s \' + res.data.message);
                        } else {
                            $("#wpdmpp-paypal-webhook-status").removeClass("text-success").addClass("text-danger").html(res.data.message || "Failed");
                        }
                    }).fail(function() {
                        $btn.html(html).removeAttr("disabled");
                        $("#wpdmpp-paypal-webhook-status").removeClass("text-success").addClass("text-danger").html("Request failed");
                    });
                });
            });
            </script>',
            esc_html__('Create WebHook', 'wpdm-premium-packages'),
            wp_kses_post($webhookStatus),
            $ajaxUrl,
            esc_js($nonce),
            \WPDMPP\UI\Icons::get('check-double', 14)
        );
    }

    /**
     * Register AJAX handler for creating PayPal webhook
     */
    public function registerWebhookAjax(): void {
        add_action('wp_ajax_wpdmpp_paypal_create_webhook', [$this, 'ajaxCreateWebhook']);
    }

    /**
     * AJAX handler: Create PayPal webhook via API
     */
    public function ajaxCreateWebhook(): void {
        check_ajax_referer('wpdmpp_paypal_create_webhook', 'nonce');

        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_send_json_error(['message' => __('Permission denied.', 'wpdm-premium-packages')]);
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            wp_send_json_error(['message' => __('Failed to authenticate with PayPal. Check your API credentials.', 'wpdm-premium-packages')]);
        }

        $apiDomain = $this->getApiDomain();
        $webhookUrl = site_url('/wp-json/wpdmpp/v1/paypal/webhook');

        // Delete existing webhook if present
        $existingWebhook = json_decode(get_option('wpdmpp_paypal_webhook', '{}'));
        if ($existingWebhook && isset($existingWebhook->id)) {
            wp_remote_request("https://{$apiDomain}/v1/notifications/webhooks/{$existingWebhook->id}", [
                'method'  => 'DELETE',
                'timeout' => 90,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
        }

        // Create new webhook
        $response = wp_remote_post("https://{$apiDomain}/v1/notifications/webhooks", [
            'timeout' => 90,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'body' => wp_json_encode([
                'url'         => $webhookUrl,
                'event_types' => [
                    ['name' => 'PAYMENT.SALE.COMPLETED'],
                    ['name' => 'BILLING.SUBSCRIPTION.CANCELLED'],
                    ['name' => 'BILLING.SUBSCRIPTION.SUSPENDED'],
                    ['name' => 'BILLING.SUBSCRIPTION.UPDATED'],
                    ['name' => 'BILLING.SUBSCRIPTION.EXPIRED'],
                    ['name' => 'CHECKOUT.ORDER.APPROVED'],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            $this->log('Failed to create webhook', ['error' => $response->get_error_message()], 'error');
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode($body);

        if ($code !== 201 && $code !== 200) {
            $errorMsg = isset($data->message) ? $data->message : __('Failed to create webhook.', 'wpdm-premium-packages');
            $this->log('Webhook creation failed', ['code' => $code, 'body' => $body], 'error');
            wp_send_json_error(['message' => $errorMsg]);
        }

        update_option('wpdmpp_paypal_webhook', $body, true);

        $this->log('PayPal webhook created', ['webhook_id' => $data->id ?? 'unknown']);

        wp_send_json_success([
            'message'    => __('WebHook created successfully!', 'wpdm-premium-packages'),
            'webhook_id' => $data->id ?? '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getCheckoutFields(): string {
        // PayPal Smart Buttons are rendered via JavaScript
        return '<div id="wpdmpp-paypal-button-container" class="wpdmpp-paypal-buttons"></div>';
    }

    /**
     * {@inheritdoc}
     */
    public function processPayment(array $orderData): array {
        // For PayPal, we use Smart Buttons which handle payment on the client side
        // This method is called after PayPal confirms the payment
        $orderId = $orderData['order_id'] ?? null;
        $paypalOrderId = $orderData['paypal_order_id'] ?? null;

        if (!$orderId || !$paypalOrderId) {
            return $this->errorResponse(__('Missing order information.', 'wpdm-premium-packages'));
        }

        try {
            $capture = $this->captureOrder($paypalOrderId);

            if ($capture['status'] !== 'COMPLETED') {
                return $this->errorResponse(__('Payment was not completed.', 'wpdm-premium-packages'));
            }

            // Complete the order
            $orderService = OrderService::instance();
            $billingInfo = $orderData['billing'] ?? [];
            $orderService->updateOrder([
                'payment_method' => 'PayPal',
                'trans_id' => $capture['id'],
                'billing_info' => maybe_serialize($billingInfo),
            ], $orderId);

            $orderService->completeOrder($orderId, true, 'PayPal');

            // Set session for guest orders
            Session::set('guest_order_init', uniqid(), 18000);
            Session::set('guest_order', $orderId, 18000);
            if (!empty($billingInfo['order_email'])) {
                Session::set('order_email', $billingInfo['order_email'], 18000);
            }

            // Clear cart
            if (function_exists('wpdmpp_empty_cart')) {
                wpdmpp_empty_cart();
            }

            do_action('wpdmpp_payment_completed', $orderId);

            return $this->successResponse(
                __('Payment completed successfully.', 'wpdm-premium-packages'),
                [
                    'order_id' => $orderId,
                    'redirect_url' => wpdm_user_dashboard_url(["udb_page" => "purchases/order/{$orderId}/"]),
                ]
            );

        } catch (\Exception $e) {
            $this->log('Payment processing failed', ['error' => $e->getMessage()], 'error');
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create a PayPal order for Smart Buttons
     *
     * @param int $orderId Local order ID
     * @param float $amount Order total
     * @param string $description Order description
     * @return array PayPal order data
     * @throws \Exception
     */
    public function createOrder(int $orderId, float $amount, string $description = ''): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new \Exception(__('Failed to authenticate with PayPal.', 'wpdm-premium-packages'));
        }

        $apiDomain = $this->getApiDomain();
        $url = "https://{$apiDomain}/v2/checkout/orders";

        $currency = function_exists('wpdmpp_currency_code') ? wpdmpp_currency_code() : 'USD';
        $description = $description ?: sprintf(__('Order #%d', 'wpdm-premium-packages'), $orderId);

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string) $orderId,
                    'description' => substr($description, 0, 127),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => get_bloginfo('name'),
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = wp_remote_post($url, [
            'timeout' => 90,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'PayPal-Request-Id' => 'WPDMPP-' . $orderId . '-' . time(),
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 201 && $code !== 200) {
            $errorMsg = $body['message'] ?? __('Failed to create PayPal order.', 'wpdm-premium-packages');
            throw new \Exception($errorMsg);
        }

        $this->log('PayPal order created', [
            'order_id' => $orderId,
            'paypal_order_id' => $body['id'],
        ]);

        return $body;
    }

    /**
     * Capture a PayPal order after approval
     *
     * @param string $paypalOrderId PayPal order ID
     * @return array Capture response
     * @throws \Exception
     */
    public function captureOrder(string $paypalOrderId): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new \Exception(__('Failed to authenticate with PayPal.', 'wpdm-premium-packages'));
        }

        $apiDomain = $this->getApiDomain();
        $url = "https://{$apiDomain}/v2/checkout/orders/{$paypalOrderId}/capture";

        $response = wp_remote_post($url, [
            'timeout' => 90,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'body' => '{}',
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 201 && $code !== 200) {
            $errorMsg = $body['message'] ?? __('Failed to capture PayPal payment.', 'wpdm-premium-packages');
            throw new \Exception($errorMsg);
        }

        $this->log('PayPal order captured', [
            'paypal_order_id' => $paypalOrderId,
            'status' => $body['status'],
        ]);

        return $body;
    }

    /**
     * Get order details from PayPal
     *
     * @param string $paypalOrderId PayPal order ID
     * @return array|null Order details
     */
    public function getOrderDetails(string $paypalOrderId): ?array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $apiDomain = $this->getApiDomain();
        $url = "https://{$apiDomain}/v2/checkout/orders/{$paypalOrderId}";

        $response = wp_remote_get($url, [
            'timeout' => 90,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Handle webhook callback
     *
     * @return array
     */
    public function handleWebhook(): array {
        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || !isset($payload['event_type'])) {
            return $this->errorResponse('Invalid webhook payload');
        }

        $eventType = $payload['event_type'];
        $resource = $payload['resource'] ?? [];

        $this->log('Webhook received', [
            'event_type' => $eventType,
            'resource_id' => $resource['id'] ?? 'unknown',
        ]);

        switch ($eventType) {
            case 'BILLING.SUBSCRIPTION.CANCELLED':
                return $this->handleSubscriptionCancelled($payload);

            case 'PAYMENT.SALE.COMPLETED':
                return $this->handlePaymentCompleted($payload);

            case 'CHECKOUT.ORDER.APPROVED':
                return $this->handleOrderApproved($payload);

            default:
                $this->log('Unhandled webhook event', ['event_type' => $eventType]);
                return $this->successResponse('Event acknowledged');
        }
    }

    /**
     * Handle subscription cancellation webhook
     *
     * @param array $payload Webhook payload
     * @return array
     */
    private function handleSubscriptionCancelled(array $payload): array {
        $transactionId = $payload['resource']['billing_agreement_id'] ?? null;

        if (!$transactionId) {
            return $this->errorResponse('Missing transaction ID');
        }

        $orderService = OrderService::instance();
        $order = $orderService->getOrderByTransactionId($transactionId);
        if ($order) {
            $this->log('Subscription cancelled', ['order_id' => $order->getOrderId()]);
            $orderService->cancelOrder($order->getOrderId(), 'PayPal subscription cancelled');
            return $this->successResponse('Subscription cancelled');
        }

        return $this->errorResponse('Order not found');
    }

    /**
     * Handle payment completed webhook
     *
     * @param array $payload Webhook payload
     * @return array
     */
    private function handlePaymentCompleted(array $payload): array {
        $transactionId = $payload['resource']['billing_agreement_id'] ?? null;

        if (!$transactionId) {
            return $this->errorResponse('Missing transaction ID');
        }

        $orderService = OrderService::instance();
        $order = $orderService->getOrderByTransactionId($transactionId);
        if ($order) {
            $this->log('Payment completed (renewal)', ['order_id' => $order->getOrderId()]);
            $orderService->renewOrder($order->getOrderId(), $transactionId);
            return $this->successResponse('Payment processed');
        }

        return $this->errorResponse('Order not found');
    }

    /**
     * Handle order approved webhook
     *
     * @param array $payload Webhook payload
     * @return array
     */
    private function handleOrderApproved(array $payload): array {
        // This is handled by client-side Smart Buttons
        return $this->successResponse('Order approval acknowledged');
    }

    /**
     * Calculate billing interval from order validity period setting
     *
     * Converts order_validity_period (days) to PayPal-compatible interval.
     *
     * @return array{unit: string, count: int}
     */
    /**
     * Build a PayPal billing interval from a plan's own billing cycle
     * (e.g. membership period = 1, unit = "month"). Falls back to the global
     * order-validity-based interval if the unit is not recognised.
     *
     * @param int    $period Number of units per billing cycle.
     * @param string $unit   One of day|week|month|year.
     * @return array{unit: string, count: int}
     */
    public function intervalFromPeriod(int $period, string $unit): array {
        $map = [
            'day'   => 'DAY',
            'week'  => 'WEEK',
            'month' => 'MONTH',
            'year'  => 'YEAR',
        ];
        $unit = strtolower($unit);

        if (!isset($map[$unit])) {
            return $this->calculateBillingInterval();
        }

        return ['unit' => $map[$unit], 'count' => max(1, $period)];
    }

    public function calculateBillingInterval(): array {
        $days = (int) get_wpdmpp_option('order_validity_period', 365, 'int');
        $days = $days ?: 365;

        if ($days % 365 === 0) {
            return ['unit' => 'YEAR', 'count' => $days / 365];
        }
        if ($days % 30 === 0) {
            return ['unit' => 'MONTH', 'count' => $days / 30];
        }
        if ($days % 7 === 0) {
            return ['unit' => 'WEEK', 'count' => $days / 7];
        }
        return ['unit' => 'DAY', 'count' => $days];
    }

    /**
     * Create a subscription product in PayPal
     *
     * @param string $name Product name
     * @param string $requestId Unique request ID (e.g. order ID)
     * @return string|null Product ID or null on failure
     */
    public function createSubscriptionProduct(string $name, string $requestId): ?string {
        $this->lastApiError = null;

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->lastApiError = __('Could not authenticate with PayPal — check the API client ID/secret and that the environment (Live/Sandbox) matches the keys.', 'wpdm-premium-packages');
            return null;
        }

        $apiDomain = $this->getApiDomain();
        $url = "https://{$apiDomain}/v1/catalogs/products";

        // PayPal's Catalog Products API requires a non-empty name/description
        // (1–127 and 1–256 chars) and rejects the request with
        // INVALID_PARAMETER_SYNTAX otherwise. The order title can be empty
        // (when the "Order Title" setting is blank) or carry HTML/entities/
        // multibyte characters, so normalise it and fall back to the site name.
        $name = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $name)));
        if ($name === '') {
            $blogName = trim(wp_strip_all_tags((string) get_bloginfo('name')));
            $name = $blogName !== '' ? $blogName . ' Subscription' : 'Subscription';
        }

        $payload = [
            'name' => mb_substr($name, 0, 127),
            'description' => mb_substr($name, 0, 256),
            'type' => 'SERVICE',
            'category' => 'SOFTWARE',
        ];

        // PayPal validates home_url/image_url and rejects the whole request with
        // INVALID_PARAMETER_SYNTAX when they point at non-public hosts (localhost,
        // IPs, dev TLDs like .local/.test) or don't match its format. Both are
        // optional metadata, so only include them when they qualify.
        $homeUrl = home_url('/');
        if ($this->isPublicHttpsUrl($homeUrl)) {
            $payload['home_url'] = $homeUrl;
        }
        // image_url additionally must end in an image extension with a restricted
        // character set — no query strings, ports, etc. A site-icon URL like
        // ".../icon.png?w=512" passes the host check but fails PayPal's regex.
        $imageUrl = get_site_icon_url();
        if ($imageUrl
            && $this->isPublicHttpsUrl($imageUrl)
            && preg_match('~^https:[/.|\w\s-]*\.(?:jpg|gif|png|jpeg|JPG|GIF|PNG|JPEG)$~', $imageUrl)) {
            $payload['image_url'] = $imageUrl;
        }

        $response = wp_remote_post($url, [
            'timeout' => 90,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'PayPal-Request-Id' => 'WPDMPP-PROD-' . $requestId,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $this->log('Failed to create subscription product', ['error' => $response->get_error_message()], 'error');
            $this->lastApiError = $response->get_error_message();
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if (($code !== 201 && $code !== 200) || empty($body['id'])) {
            $this->log('Invalid subscription product response', ['code' => $code, 'body' => $body], 'error');
            $this->lastApiError = $this->formatApiError($body, $code);
            return null;
        }

        $this->log('Subscription product created', ['product_id' => $body['id']]);
        return $body['id'];
    }

    /**
     * Create a subscription plan in PayPal
     *
     * @param string $productId PayPal product ID
     * @param float $price Subscription price
     * @param int $intervalCount Billing interval count
     * @param string $intervalUnit Billing interval unit (DAY, WEEK, MONTH, YEAR)
     * @return string|null Plan ID or null on failure
     */
    public function createSubscriptionPlan(string $productId, float $price, int $intervalCount, string $intervalUnit, int $trialDays = 0): ?string {
        $this->lastApiError = null;

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->lastApiError = __('Could not authenticate with PayPal — check the API client ID/secret and that the environment (Live/Sandbox) matches the keys.', 'wpdm-premium-packages');
            return null;
        }

        $apiDomain = $this->getApiDomain();
        $url = "https://{$apiDomain}/v1/billing/plans";

        $currency = function_exists('wpdmpp_currency_code') ? wpdmpp_currency_code() : 'USD';
        $name = sprintf(__('Subscription - %s', 'wpdm-premium-packages'), get_bloginfo('name'));

        $billingCycles = [];

        // Add trial billing cycle if applicable (sequence 1 = first)
        if ($trialDays > 0) {
            $billingCycles[] = [
                'frequency' => [
                    'interval_unit' => 'DAY',
                    'interval_count' => $trialDays,
                ],
                'tenure_type' => 'TRIAL',
                'sequence' => 1,
                'total_cycles' => 1,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => '0.00',
                        'currency_code' => $currency,
                    ],
                ],
            ];
        }

        // Regular billing cycle
        $billingCycles[] = [
            'frequency' => [
                'interval_unit' => $intervalUnit,
                'interval_count' => $intervalCount,
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => $trialDays > 0 ? 2 : 1,
            'total_cycles' => 0,
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => number_format($price, 2, '.', ''),
                    'currency_code' => $currency,
                ],
            ],
        ];

        $payload = [
            'product_id' => $productId,
            'name' => substr($name, 0, 127),
            'description' => substr($name, 0, 256),
            'billing_cycles' => $billingCycles,
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 5,
            ],
        ];

        $response = wp_remote_post($url, [
            'timeout' => 90,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'PayPal-Request-Id' => 'WPDMPP-PLAN-' . $productId . '-' . time(),
                'Accept' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $this->log('Failed to create subscription plan', ['error' => $response->get_error_message()], 'error');
            $this->lastApiError = $response->get_error_message();
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if (($code !== 201 && $code !== 200) || empty($body['id'])) {
            $this->log('Invalid subscription plan response', ['code' => $code, 'body' => $body], 'error');
            $this->lastApiError = $this->formatApiError($body, $code);
            return null;
        }

        $this->log('Subscription plan created', ['plan_id' => $body['id'], 'price' => $price]);
        return $body['id'];
    }

    /**
     * Verify a subscription by ID
     *
     * @param string $subscriptionId PayPal subscription ID
     * @return array|null Subscription details or null on failure
     */
    public function verifySubscription(string $subscriptionId): ?array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $apiDomain = $this->getApiDomain();
        $url = "https://{$apiDomain}/v1/billing/subscriptions/{$subscriptionId}";

        $response = wp_remote_get($url, [
            'timeout' => 90,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        if (is_wp_error($response)) {
            $this->log('Failed to verify subscription', ['error' => $response->get_error_message()], 'error');
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200 || empty($body['id'])) {
            $this->log('Invalid subscription verification response', ['code' => $code], 'error');
            return null;
        }

        $this->log('Subscription verified', ['subscription_id' => $subscriptionId, 'status' => $body['status'] ?? 'unknown']);
        return $body;
    }

    /**
     * Test PayPal connection
     *
     * @return bool
     */
    public function testConnection(): bool {
        return (bool) $this->getAccessToken();
    }

    /**
     * Get Smart Buttons configuration for JavaScript
     *
     * @param int $orderId Local order ID
     * @param float $amount Order amount
     * @return array Configuration data
     */
    public function getSmartButtonsConfig(int $orderId, float $amount): array {
        return [
            'client_id' => $this->getClientId(),
            'currency' => function_exists('wpdmpp_currency_code') ? wpdmpp_currency_code() : 'USD',
            'mode' => $this->isSandbox() ? 'sandbox' : 'production',
            'order_id' => $orderId,
            'amount' => number_format($amount, 2, '.', ''),
            'create_order_url' => rest_url('wpdmpp/v1/checkout/paypal/create'),
            'capture_url' => rest_url('wpdmpp/v1/checkout/paypal/capture'),
        ];
    }

    /**
     * Render PayPal Smart Buttons for the legacy AJAX checkout context
     *
     * Returns full HTML+JS block that loads the PayPal SDK and initializes
     * paypal.Buttons() using REST API endpoints for create/capture.
     *
     * @param string $orderId Local order ID (empty string to auto-create)
     * @param float $amount Order amount (0 to calculate from cart)
     * @return string HTML+JS for PayPal Smart Buttons, empty if not configured
     */
    public function renderCheckoutButton(string $orderId = '', float $amount = 0): string {
        $clientId = $this->getClientId();
        if (empty($clientId)) {
            return '';
        }

        $currency = function_exists('wpdmpp_currency_code') ? wpdmpp_currency_code() : 'USD';
        $createUrl = rest_url('wpdmpp/v1/checkout/paypal/create');
        $captureUrl = rest_url('wpdmpp/v1/checkout/paypal/capture');
        $nonce = wp_create_nonce('wp_rest');

        // Check for recurring/subscription
        $recurring = false;
        $planId = '';
        if (CartService::instance()->isRecurring()) {
            $recurring = true;
            if ($orderId) {
                $order = OrderService::instance()->getOrder($orderId);
                if (!$order) {
                    return '';
                }
                $interval = $this->calculateBillingInterval();

                // Check cart items for trial/membership metadata
                $trialDays = 0;
                $fullPrice = 0;
                $cart = CartService::instance()->getCart();
                foreach ($cart as $item) {
                    $info = $item->getInfo();
                    if (!empty($info['trial_days'])) {
                        $trialDays = (int) $info['trial_days'];
                        $fullPrice = (float) ($info['full_price'] ?? 0);
                        break;
                    }
                }

                $productId = $this->createSubscriptionProduct($order->getTitle(), $orderId);
                if ($productId) {
                    // For trial subscriptions, use full price as recurring amount (not $0 order total)
                    $orderTotal = $trialDays > 0 && $fullPrice > 0 ? $fullPrice : ($amount > 0 ? $amount : $order->getTotal());
                    $planId = $this->createSubscriptionPlan($productId, $orderTotal, $interval['count'], $interval['unit'], $trialDays);
                }
            }
        }

        $activateUrl = rest_url('wpdmpp/v1/checkout/paypal/activate-subscription');

        // Build SDK URL
        $sdkParams = 'client-id=' . esc_attr($clientId) . '&currency=' . esc_attr($currency);
        if (!$recurring) {
            $sdkParams .= '&components=buttons,hosted-fields,funding-eligibility&vault=true&intent=capture';
        } else {
            $sdkParams .= '&components=buttons&vault=true&intent=subscription';
        }
        $sdkUrl = 'https://www.paypal.com/sdk/js?' . $sdkParams;

        ob_start();
        ?>
        <div id="wpdm-paypal-button-container"></div>
        <style>
            #wpdm-paypal-button-container { position: relative; }
            #wpdm-paypal-button-container.__blocked:before {
                position: absolute; content: ""; width: 100%; height: 100%;
                left: 0; top: 0; background: rgba(255,255,255,0.5); z-index: 9999999 !important;
            }
        </style>
        <script>
        jQuery(function($) {
            function pmf_is_email(email) {
                return /^([a-zA-Z0-9_.+-])+@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(email);
            }
            function validate_payment_form() {
                if ($('#email_m').length > 0 && ($('#f-name').val() === '' || $('#l-name').val() === '' || !pmf_is_email($('#email_m').val()))) return false;
                return true;
            }
            function _ppbtnact() {
                if (validate_payment_form()) $('#wpdm-paypal-button-container').removeClass('__blocked');
                else $('#wpdm-paypal-button-container').addClass('__blocked');
            }
            _ppbtnact();
            $('body').on('keydown change', '#payment_form input', _ppbtnact);

            window.loadScripts = window.loadScripts || function(scripts) {
                return scripts.reduce(function(p, url) {
                    return p.then(function() {
                        return new Promise(function(resolve) {
                            var s = document.createElement('script');
                            s.async = true; s.src = url; s.onload = resolve;
                            document.getElementsByTagName('head')[0].appendChild(s);
                        });
                    });
                }, Promise.resolve());
            };

            <?php if (!$recurring): ?>
            loadScripts([<?php echo wp_json_encode($sdkUrl); ?>]).then(function() {
                paypal.Buttons({
                    style: { layout: 'horizontal', size: 'medium', shape: 'rect', color: 'blue', label: 'checkout', tagline: false },
                    funding: { allowed: [paypal.FUNDING.CARD], disallowed: [paypal.FUNDING.CREDIT] },
                    createOrder: function(data, actions) {
                        var formData = jQuery('#payment_form').serialize();
                        return fetch(<?php echo wp_json_encode($createUrl); ?> + '?' + formData, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': <?php echo wp_json_encode($nonce); ?> },
                            body: JSON.stringify({ order_id: <?php echo wp_json_encode($orderId); ?> })
                        }).then(function(r) { return r.json(); }).then(function(result) {
                            if (!result.success) throw new Error(result.message || 'Error');
                            var d = result.data || result;
                            return d.paypal_order_id;
                        });
                    },
                    onApprove: function(data, actions) {
                        var formData = jQuery('#payment_form').serialize();
                        $('#paymentform').append('<div class="alert alert-success">' + (typeof wpdmppIcons !== "undefined" ? wpdmppIcons.spinner : "") + ' <?php echo esc_js(__('Completing Order...', 'wpdm-premium-packages')); ?></div>');
                        return fetch(<?php echo wp_json_encode($captureUrl); ?>, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': <?php echo wp_json_encode($nonce); ?> },
                            body: JSON.stringify({ paypal_order_id: data.orderID, order_id: <?php echo wp_json_encode($orderId); ?> })
                        }).then(function(r) { return r.json(); }).then(function(result) {
                            if (result.success) {
                                var d = result.data || result;
                                location.href = d.redirect || d.redirect_url || '<?php echo esc_js(home_url('/')); ?>';
                            }
                        });
                    },
                    onError: function(err) { console.log(err); }
                }).render('#wpdm-paypal-button-container');
            });
            <?php else: ?>
            loadScripts([<?php echo wp_json_encode($sdkUrl); ?>]).then(function() {
                paypal.Buttons({
                    style: { layout: 'horizontal', size: 'responsive', shape: 'rect', color: 'blue', tagline: false },
                    funding: { allowed: [paypal.FUNDING.CARD], disallowed: [paypal.FUNDING.CREDIT] },
                    createSubscription: function(data, actions) {
                        return actions.subscription.create({ 'plan_id': <?php echo wp_json_encode($planId); ?> });
                    },
                    onApprove: function(data, actions) {
                        $('#selected-payment-gateway-action').addClass('blockui');
                        $('#paymentform').append('<div class="alert alert-success">' + (typeof wpdmppIcons !== "undefined" ? wpdmppIcons.spinner : "") + ' <?php echo esc_js(__('Completing Order...', 'wpdm-premium-packages')); ?></div>');
                        return fetch(<?php echo wp_json_encode($activateUrl); ?>, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': <?php echo wp_json_encode($nonce); ?> },
                            body: JSON.stringify({ subscription_id: data.subscriptionID, order_id: <?php echo wp_json_encode($orderId); ?> })
                        }).then(function(r) { return r.json(); }).then(function(result) {
                            if (result.success) {
                                var d = result.data || result;
                                location.href = d.redirect_url || '<?php echo esc_js(home_url('/')); ?>';
                            } else {
                                alert(result.message || 'Something is wrong!');
                                $('#selected-payment-gateway-action').removeClass('blockui');
                            }
                        });
                    }
                }).render('#wpdm-paypal-button-container');
            });
            <?php endif; ?>
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
