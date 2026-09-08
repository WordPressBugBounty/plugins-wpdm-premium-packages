<?php
/**
 * PayPal Webhook REST Endpoint
 *
 * Handles incoming PayPal webhook events via the WordPress REST API.
 * Replaces the legacy /?pmwebhook=Paypal query parameter routing.
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\Order\OrderService;

defined('ABSPATH') || exit;

class PayPalWebhookEndpoint {

    /**
     * Register the webhook REST route
     */
    public function register(): void {
        register_rest_route(RestApi::API_NAMESPACE, '/paypal/webhook', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleWebhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle incoming PayPal webhook request
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleWebhook(\WP_REST_Request $request): \WP_REST_Response {
        $rawBody = $request->get_body();
        $payload = json_decode($rawBody, true);

        if (!$payload || !isset($payload['event_type'])) {
            $this->log('Invalid webhook payload', [], 'error');
            return new \WP_REST_Response(['error' => 'Invalid webhook payload'], 400);
        }

        // The route is necessarily public - PayPal calls it with no credentials of
        // ours - so the signature is the only thing separating a real event from a
        // forged one. Nothing below this point may run on an unverified payload.
        if (!$this->isVerified($rawBody, $request)) {
            return new \WP_REST_Response(['error' => 'Webhook signature verification failed'], 401);
        }

        $eventType = $payload['event_type'];
        $resource = $payload['resource'] ?? [];

        $this->log('Webhook received', [
            'event_type'  => $eventType,
            'resource_id' => $resource['id'] ?? 'unknown',
        ]);

        switch ($eventType) {
            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
                return $this->handleSubscriptionCancelled($payload);

            case 'PAYMENT.SALE.COMPLETED':
                return $this->handlePaymentCompleted($payload);

            case 'CHECKOUT.ORDER.APPROVED':
                return $this->handleOrderApproved($payload);

            default:
                $this->log('Unhandled webhook event', ['event_type' => $eventType]);
                return new \WP_REST_Response(['received' => true], 200);
        }
    }

    /**
     * Confirm PayPal signed this event.
     *
     * @param string            $rawBody
     * @param \WP_REST_Request $request
     *
     * @return bool
     */
    private function isVerified(string $rawBody, \WP_REST_Request $request): bool {
        $gateway = \WPDMPP\Payment\PaymentService::instance()->getGateway('paypal');

        if (!$gateway || !method_exists($gateway, 'verifyWebhookSignature')) {
            $this->log('Webhook rejected: the PayPal gateway is unavailable to verify with', [], 'error');
            return false;
        }

        $headers = [];
        foreach ($request->get_headers() as $name => $values) {
            $headers[strtolower(str_replace('_', '-', $name))] = is_array($values) ? reset($values) : $values;
        }

        return $gateway->verifyWebhookSignature($rawBody, $headers);
    }

    /**
     * Handle subscription cancellation/suspension/expiry webhook
     *
     * @param array $payload Webhook payload
     * @return \WP_REST_Response
     */
    private function handleSubscriptionCancelled(array $payload): \WP_REST_Response {
        $resource = $payload['resource'] ?? [];
        $subscriptionId = $resource['id'] ?? ($resource['billing_agreement_id'] ?? null);

        if (!$subscriptionId) {
            $this->log('Missing subscription/transaction ID', [], 'error');
            return new \WP_REST_Response(['error' => 'Missing subscription ID'], 400);
        }

        $orderService = OrderService::instance();
        $order = $orderService->getOrderByTransactionId($subscriptionId);

        if (!$order) {
            $this->log('Order not found for subscription', ['subscription_id' => $subscriptionId]);
            return new \WP_REST_Response(['received' => true, 'status' => 'order_not_found'], 200);
        }

        $orderId = $order->getOrderId();
        $eventType = $payload['event_type'];

        $orderService->updateOrder(['auto_renew' => 0], $orderId);
        $orderService->updateMeta($orderId, 'paypal_subscription_cancelled', time());

        $this->log('Subscription cancelled/suspended', [
            'order_id'        => $orderId,
            'subscription_id' => $subscriptionId,
            'event_type'      => $eventType,
        ]);

        return new \WP_REST_Response(['received' => true, 'order_id' => $orderId], 200);
    }

    /**
     * Handle payment completed webhook (subscription renewal)
     *
     * @param array $payload Webhook payload
     * @return \WP_REST_Response
     */
    private function handlePaymentCompleted(array $payload): \WP_REST_Response {
        $resource = $payload['resource'] ?? [];
        $subscriptionId = $resource['billing_agreement_id'] ?? null;

        if (!$subscriptionId) {
            $this->log('Missing billing_agreement_id in PAYMENT.SALE.COMPLETED', [], 'error');
            return new \WP_REST_Response(['error' => 'Missing billing agreement ID'], 400);
        }

        $orderService = OrderService::instance();
        $order = $orderService->getOrderByTransactionId($subscriptionId);

        if (!$order) {
            $this->log('Order not found for billing agreement', ['billing_agreement_id' => $subscriptionId]);
            return new \WP_REST_Response(['received' => true, 'status' => 'order_not_found'], 200);
        }

        $orderId = $order->getOrderId();
        $saleId = $resource['id'] ?? '';

        $renewed = $orderService->renewOrder($orderId, $subscriptionId, true, time(), $saleId);

        if ($renewed) {
            $this->log('Payment completed (renewal)', [
                'order_id'        => $orderId,
                'subscription_id' => $subscriptionId,
                'sale_id'         => $saleId,
            ]);
        } else {
            $this->log('Renewal failed', [
                'order_id'        => $orderId,
                'subscription_id' => $subscriptionId,
            ], 'error');
        }

        return new \WP_REST_Response(['received' => true, 'order_id' => $orderId, 'renewed' => $renewed], 200);
    }

    /**
     * Handle order approved webhook
     *
     * @param array $payload Webhook payload
     * @return \WP_REST_Response
     */
    private function handleOrderApproved(array $payload): \WP_REST_Response {
        // This is primarily handled client-side by Smart Buttons
        $this->log('Order approval webhook received', [
            'resource_id' => $payload['resource']['id'] ?? 'unknown',
        ]);
        return new \WP_REST_Response(['received' => true], 200);
    }

    /**
     * Log a webhook message
     *
     * @param string $message Log message
     * @param array  $context Additional context
     * @param string $level   Log level
     */
    private function log(string $message, array $context = [], string $level = 'info'): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log(sprintf(
            '[WPDMPP PayPal Webhook] [%s] %s | Context: %s',
            strtoupper($level),
            $message,
            wp_json_encode($context)
        ));
    }
}
