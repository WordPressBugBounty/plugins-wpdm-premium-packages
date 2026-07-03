<?php
/**
 * Cart REST Endpoint
 *
 * Handles cart-related REST API endpoints:
 * - GET    /cart                - Get cart data
 * - POST   /cart                - Add item to cart
 * - PUT    /cart/{id}           - Update item quantity
 * - DELETE /cart/{id}           - Remove item from cart
 * - DELETE /cart                - Clear cart
 * - POST   /cart/coupon         - Apply coupon
 * - DELETE /cart/coupon         - Remove coupon
 * - GET    /cart/mini           - Get mini cart data
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\Cart\CartService;

defined('ABSPATH') || exit;

class CartEndpoint {

    /**
     * Cart service instance
     * @var CartService
     */
    private CartService $cartService;

    /**
     * Constructor
     */
    public function __construct() {
        $this->cartService = CartService::instance();
    }

    /**
     * Register cart routes
     */
    public function register(): void {
        $namespace = RestApi::API_NAMESPACE;

        // Get cart
        register_rest_route($namespace, '/cart', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getCart'],
            'permission_callback' => '__return_true',
        ]);

        // Add item to cart
        register_rest_route($namespace, '/cart', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'addItem'],
            'permission_callback' => '__return_true',
            'args' => $this->getAddItemArgs(),
        ]);

        // Clear cart
        register_rest_route($namespace, '/cart', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'clearCart'],
            'permission_callback' => '__return_true',
        ]);

        // Update item quantity
        register_rest_route($namespace, '/cart/(?P<product_id>\d+)', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'updateItem'],
            'permission_callback' => '__return_true',
            'args' => [
                'product_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'quantity' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return $value >= 1;
                    },
                ],
            ],
        ]);

        // Remove item from cart
        register_rest_route($namespace, '/cart/(?P<product_id>\d+)', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'removeItem'],
            'permission_callback' => '__return_true',
            'args' => [
                'product_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Apply coupon
        register_rest_route($namespace, '/cart/coupon', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'applyCoupon'],
            'permission_callback' => '__return_true',
            'args' => [
                'code' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        // Remove coupon
        register_rest_route($namespace, '/cart/coupon', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'removeCoupon'],
            'permission_callback' => '__return_true',
        ]);

        // Get mini cart (for header/widget display)
        register_rest_route($namespace, '/cart/mini', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getMiniCart'],
            'permission_callback' => '__return_true',
        ]);

        // Add dynamic item (for subscriptions, etc.)
        register_rest_route($namespace, '/cart/dynamic', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'addDynamicItem'],
            'permission_callback' => '__return_true',
            'args' => [
                'item_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'price' => [
                    'required' => true,
                    'type' => 'number',
                    'sanitize_callback' => 'floatval',
                ],
                'recurring' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // Calculate tax
        register_rest_route($namespace, '/cart/tax', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'calculateTax'],
            'permission_callback' => '__return_true',
            'args' => [
                'country' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'state' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Get add item arguments
     *
     * @return array
     */
    private function getAddItemArgs(): array {
        return [
            'product_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) {
                    return $value > 0 && get_post_type($value) === 'wpdmpro';
                },
            ],
            'quantity' => [
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'license' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'extra_gigs' => [
                'type' => 'array',
                'default' => [],
                'sanitize_callback' => function ($value) {
                    return is_array($value) ? array_map('sanitize_text_field', $value) : [];
                },
            ],
            'files' => [
                'type' => 'array',
                'default' => [],
                'sanitize_callback' => function ($value) {
                    return is_array($value) ? array_map('sanitize_text_field', $value) : [];
                },
            ],
            'iwantopay' => [
                'type' => 'number',
                'default' => 0,
                'sanitize_callback' => 'floatval',
            ],
        ];
    }

    /**
     * Get cart data
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getCart(\WP_REST_Request $request): \WP_REST_Response {
        // Auto-apply coupon if eligible
        $this->cartService->tryAutoApplyCoupon();

        $cart = $this->cartService->getCart();

        return RestApi::success([
            'cart' => $this->formatCartData($cart),
            'is_empty' => $cart->isEmpty(),
            'is_locked' => $cart->isLocked(),
            'is_recurring' => $this->cartService->isRecurring(),
            'cart_url' => function_exists('wpdmpp_cart_page') ? wpdmpp_cart_page() : '',
            'currency' => $this->getCurrencyData(),
        ]);
    }

    /**
     * Add item to cart
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function addItem(\WP_REST_Request $request): \WP_REST_Response {
        $productId = $request->get_param('product_id');

        // Check if product exists and is published
        $post = get_post($productId);
        if (!$post || $post->post_status !== 'publish') {
            return RestApi::error(
                __('Product not found.', 'wpdm-premium-packages'),
                404
            );
        }

        // Check if cart is locked
        if ($this->cartService->isCartLocked()) {
            return RestApi::error(
                __('Cart is locked. Please complete or clear your current order.', 'wpdm-premium-packages'),
                400
            );
        }

        $data = [
            'quantity' => $request->get_param('quantity'),
            'license' => $request->get_param('license'),
            'extra_gigs' => $request->get_param('extra_gigs'),
            'files' => $request->get_param('files'),
            'iwantopay' => $request->get_param('iwantopay'),
        ];

        $cart = $this->cartService->addItem($productId, $data);
        $item = $cart->getItem($productId);

        return RestApi::success([
            'cart' => $this->formatCartData($cart),
            'added_item' => $item ? $this->formatCartItem($item) : null,
            'message' => sprintf(
                __('"%s" has been added to your cart.', 'wpdm-premium-packages'),
                get_the_title($productId)
            ),
        ], __('Item added to cart.', 'wpdm-premium-packages'));
    }

    /**
     * Add dynamic item to cart
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function addDynamicItem(\WP_REST_Request $request): \WP_REST_Response {
        $itemId = $request->get_param('item_id');
        $name = $request->get_param('name');
        $price = $request->get_param('price');

        $extras = [
            'recurring' => $request->get_param('recurring'),
        ];

        // Get any additional parameters
        $params = $request->get_params();
        unset($params['item_id'], $params['name'], $params['price'], $params['recurring']);
        $extras = array_merge($extras, $params);

        $cart = $this->cartService->addDynamicItem($itemId, $name, $price, $extras);

        return RestApi::success([
            'cart' => $this->formatCartData($cart),
            'is_recurring' => $this->cartService->isRecurring(),
        ], __('Item added to cart.', 'wpdm-premium-packages'));
    }

    /**
     * Update item quantity
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateItem(\WP_REST_Request $request): \WP_REST_Response {
        $productId = (int) $request->get_param('product_id');
        $quantity = (int) $request->get_param('quantity');

        $cart = $this->cartService->getCart();

        if (!$cart->hasItem($productId)) {
            return RestApi::error(
                __('Item not found in cart.', 'wpdm-premium-packages'),
                404
            );
        }

        $cart = $this->cartService->updateItemQuantity($productId, $quantity);
        $item = $cart->getItem($productId);

        return RestApi::success([
            'cart' => $this->formatCartData($cart),
            'updated_item' => $item ? $this->formatCartItem($item) : null,
        ], __('Cart updated.', 'wpdm-premium-packages'));
    }

    /**
     * Remove item from cart
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function removeItem(\WP_REST_Request $request): \WP_REST_Response {
        $productId = (int) $request->get_param('product_id');

        $cart = $this->cartService->getCart();

        if (!$cart->hasItem($productId)) {
            return RestApi::error(
                __('Item not found in cart.', 'wpdm-premium-packages'),
                404
            );
        }

        $productName = $cart->getItem($productId)->getProductName();
        $cart = $this->cartService->removeItem($productId);

        return RestApi::success([
            'cart' => $this->formatCartData($cart),
            'is_empty' => $cart->isEmpty(),
        ], sprintf(__('"%s" has been removed from your cart.', 'wpdm-premium-packages'), $productName));
    }

    /**
     * Clear cart
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function clearCart(\WP_REST_Request $request): \WP_REST_Response {
        $this->cartService->clearCart();
        $cart = $this->cartService->getCart();

        return RestApi::success([
            'cart' => $this->formatCartData($cart),
            'is_empty' => true,
        ], __('Cart has been cleared.', 'wpdm-premium-packages'));
    }

    /**
     * Apply coupon
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function applyCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $code = $request->get_param('code');

        if (empty($code)) {
            return RestApi::error(
                __('Please enter a coupon code.', 'wpdm-premium-packages'),
                400
            );
        }

        $email = (string) $request->get_param('email');
        $result = $this->cartService->applyCoupon($code, $email ?: null);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        $cart = $this->cartService->getCart();

        return RestApi::success([
            'cart' => $this->formatCartData($cart),
            'coupon' => $cart->getCoupon(),
            'discount' => $result['discount'],
            'discount_formatted' => function_exists('wpdmpp_price_format')
                ? wpdmpp_price_format($result['discount'])
                : number_format($result['discount'], 2),
        ], $result['message']);
    }

    /**
     * Remove coupon
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function removeCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $this->cartService->removeCoupon();
        $cart = $this->cartService->getCart();

        return RestApi::success([
            'cart' => $this->formatCartData($cart),
        ], __('Coupon has been removed.', 'wpdm-premium-packages'));
    }

    /**
     * Get mini cart data (for header/widget)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getMiniCart(\WP_REST_Request $request): \WP_REST_Response {
        $cart = $this->cartService->getCart();

        $items = [];
        foreach ($cart->getItems() as $item) {
            $items[] = [
                'id' => $item->getProductId(),
                'name' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice(),
                'line_total' => $item->getLineTotal(),
                'thumbnail' => $item->getThumbnailUrl(),
                'permalink' => $item->getPermalink(),
                'is_dynamic' => $item->isDynamic(),
            ];
        }

        return RestApi::success([
            'items' => $items,
            'item_count' => $cart->count(),
            'total_quantity' => $cart->getTotalQuantity(),
            'subtotal' => $cart->getSubtotal(),
            'total' => $cart->getTotal(),
            'subtotal_formatted' => $this->formatPrice($cart->getSubtotal()),
            'total_formatted' => $this->formatPrice($cart->getTotal()),
            'is_empty' => $cart->isEmpty(),
            'cart_url' => function_exists('wpdmpp_cart_page') ? wpdmpp_cart_page() : '',
            'checkout_url' => function_exists('wpdmpp_cart_page') ? wpdmpp_cart_page() : '',
        ]);
    }

    /**
     * Calculate tax
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function calculateTax(\WP_REST_Request $request): \WP_REST_Response {
        $country = $request->get_param('country');
        $state = $request->get_param('state');

        $tax = $this->cartService->calculateTax($country, $state);
        $cart = $this->cartService->getCart();

        return RestApi::success([
            'tax' => $tax,
            'tax_formatted' => $this->formatPrice($tax),
            'total' => $cart->getTotal(),
            'total_with_tax' => $cart->getTotalWithTax(),
            'total_formatted' => $this->formatPrice($cart->getTotal()),
            'total_with_tax_formatted' => $this->formatPrice($cart->getTotalWithTax()),
        ]);
    }

    /**
     * Format cart data for response
     *
     * @param \WPDMPP\Cart\Cart $cart
     * @return array
     */
    private function formatCartData(\WPDMPP\Cart\Cart $cart): array {
        $items = [];
        foreach ($cart->getItems() as $item) {
            $items[] = $this->formatCartItem($item);
        }

        return [
            'items' => $items,
            'item_count' => $cart->count(),
            'total_quantity' => $cart->getTotalQuantity(),
            'subtotal' => $cart->getSubtotal(),
            'role_discount' => $cart->getTotalRoleDiscount(),
            'coupon' => $cart->getCoupon(),
            'coupon_discount' => $cart->getCouponDiscount(),
            'tax' => $cart->getTax(),
            'total' => $cart->getTotal(),
            'total_with_tax' => $cart->getTotalWithTax(),
            'subtotal_formatted' => $this->formatPrice($cart->getSubtotal()),
            'role_discount_formatted' => $this->formatPrice($cart->getTotalRoleDiscount()),
            'coupon_discount_formatted' => $this->formatPrice($cart->getCouponDiscount()),
            'tax_formatted' => $this->formatPrice($cart->getTax()),
            'total_formatted' => $this->formatPrice($cart->getTotal()),
            'total_with_tax_formatted' => $this->formatPrice($cart->getTotalWithTax()),
        ];
    }

    /**
     * Format cart item for response
     *
     * @param \WPDMPP\Cart\CartItem $item
     * @return array
     */
    private function formatCartItem(\WPDMPP\Cart\CartItem $item): array {
        return [
            'product_id' => $item->getProductId(),
            'product_name' => $item->getProductName(),
            'product_type' => $item->getProductType(),
            'quantity' => $item->getQuantity(),
            'price' => $item->getPrice(),
            'gigs_cost' => $item->getGigsCost(),
            'unit_total' => $item->getUnitTotal(),
            'subtotal' => $item->getSubtotal(),
            'role_discount' => $item->getRoleDiscount(),
            'coupon' => $item->getCoupon(),
            'coupon_discount' => $item->getCouponDiscount(),
            'line_total' => $item->getLineTotal(),
            'license' => $item->getLicense(),
            'license_name' => $item->getLicenseName(),
            'extra_gigs' => $item->getExtraGigs(),
            'files' => $item->getFiles(),
            'is_dynamic' => $item->isDynamic(),
            'thumbnail' => $item->getThumbnailUrl(),
            'permalink' => $item->getPermalink(),
            'price_formatted' => $this->formatPrice($item->getPrice()),
            'unit_total_formatted' => $this->formatPrice($item->getUnitTotal()),
            'subtotal_formatted' => $this->formatPrice($item->getSubtotal()),
            'line_total_formatted' => $this->formatPrice($item->getLineTotal()),
        ];
    }

    /**
     * Get currency data
     *
     * @return array
     */
    private function getCurrencyData(): array {
        return [
            'code' => function_exists('wpdmpp_currency_code') ? wpdmpp_currency_code() : 'USD',
            'symbol' => function_exists('wpdmpp_currency_sign') ? wpdmpp_currency_sign() : '$',
            'position' => function_exists('wpdmpp_currency_sign_position') ? wpdmpp_currency_sign_position() : 'left',
        ];
    }

    /**
     * Format price
     *
     * @param float $price
     * @return string
     */
    private function formatPrice(float $price): string {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($price);
        }
        return '$' . number_format($price, 2);
    }
}
