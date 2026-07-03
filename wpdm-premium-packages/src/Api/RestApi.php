<?php
/**
 * REST API Registration
 *
 * Registers all REST API routes for the plugin.
 *
 * @package WPDMPP\Api
 * @since 7.0.0
 */

namespace WPDMPP\Api;

use WPDMPP\Api\Endpoints\CheckoutEndpoint;
use WPDMPP\Api\Endpoints\CartEndpoint;
use WPDMPP\Api\Endpoints\OrderEndpoint;
use WPDMPP\Api\Endpoints\CouponEndpoint;
use WPDMPP\Api\Endpoints\LicenseEndpoint;
use WPDMPP\Api\Endpoints\ProductEndpoint;
use WPDMPP\Api\Endpoints\CustomerEndpoint;
use WPDMPP\Api\Endpoints\InvoiceEndpoint;
use WPDMPP\Api\Endpoints\OrderNoteTemplateEndpoint;
use WPDMPP\Api\Endpoints\PayPalWebhookEndpoint;

defined('ABSPATH') || exit;

class RestApi {

    /**
     * API namespace
     */
    public const API_NAMESPACE = 'wpdmpp/v1';

    /**
     * Registered endpoints
     *
     * @var array
     */
    private array $endpoints = [];

    /**
     * Register the REST API
     */
    public function register(): void {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register all routes
     */
    public function registerRoutes(): void {
        // Register checkout endpoints
        $checkout = new CheckoutEndpoint();
        $checkout->register();

        // Register cart endpoints
        $cart = new CartEndpoint();
        $cart->register();

        // Register order endpoints
        $order = new OrderEndpoint();
        $order->register();

        // Register coupon endpoints
        $coupon = new CouponEndpoint();
        $coupon->register();

        // Register license endpoints
        $license = new LicenseEndpoint();
        $license->register();

        // Register product endpoints
        $product = new ProductEndpoint();
        $product->register();

        // Register customer endpoints
        $customer = new CustomerEndpoint();
        $customer->register();

        // Register invoice endpoints
        $invoice = new InvoiceEndpoint();
        $invoice->register();

        // Register order note template endpoints
        $orderNoteTemplate = new OrderNoteTemplateEndpoint();
        $orderNoteTemplate->register();

        // Register PayPal webhook endpoint
        $paypalWebhook = new PayPalWebhookEndpoint();
        $paypalWebhook->register();

        // Allow extensions to register endpoints
        do_action('wpdmpp_register_rest_routes', $this);
    }

    /**
     * Get the API namespace
     *
     * @return string
     */
    public static function getNamespace(): string {
        return self::API_NAMESPACE;
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public static function checkAuthenticated(): bool {
        return is_user_logged_in();
    }

    /**
     * Check if user has admin capability
     *
     * @return bool
     */
    public static function checkAdminCap(): bool {
        return current_user_can(WPDMPP_ADMIN_CAP);
    }

    /**
     * Standard JSON error response
     *
     * @param string $message Error message
     * @param int    $status  HTTP status code
     * @param array  $data    Additional data
     * @return \WP_REST_Response
     */
    public static function error(string $message, int $status = 400, array $data = []): \WP_REST_Response {
        return new \WP_REST_Response([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Standard JSON success response
     *
     * @param array  $data    Response data
     * @param string $message Success message
     * @return \WP_REST_Response
     */
    public static function success(array $data = [], string $message = ''): \WP_REST_Response {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return new \WP_REST_Response($response, 200);
    }
}
