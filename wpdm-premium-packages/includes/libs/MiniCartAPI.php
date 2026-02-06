<?php
/**
 * Mini Cart REST API
 *
 * Provides REST endpoints for cart operations:
 * - GET /wpdmpp/v1/cart - Get cart data
 * - POST /wpdmpp/v1/cart - Add item to cart
 * - DELETE /wpdmpp/v1/cart/{id} - Remove item from cart
 * - PUT /wpdmpp/v1/cart/{id} - Update item quantity
 * - DELETE /wpdmpp/v1/cart - Clear entire cart
 *
 * @package WPDMPP
 * @since 6.2.0
 */

namespace WPDMPP\Libs;

if (!defined('ABSPATH')) {
    exit;
}

class MiniCartAPI {

    /**
     * API namespace
     */
    const NAMESPACE = 'wpdmpp/v1';

    /**
     * Initialize the REST API
     */
    public static function init() {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    /**
     * Register REST API routes
     */
    public static function registerRoutes() {
        // Get cart data
        register_rest_route(self::NAMESPACE, '/cart', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'getCart'],
            'permission_callback' => '__return_true',
        ]);

        // Add item to cart
        register_rest_route(self::NAMESPACE, '/cart', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'addItem'],
            'permission_callback' => '__return_true',
            'args' => [
                'product_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'quantity' => [
                    'type' => 'integer',
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'license' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'variation' => [
                    'type' => 'array',
                    'default' => [],
                ],
            ],
        ]);

        // Update cart item quantity
        register_rest_route(self::NAMESPACE, '/cart/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [self::class, 'updateItem'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'quantity' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Remove item from cart
        register_rest_route(self::NAMESPACE, '/cart/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'removeItem'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Clear entire cart
        register_rest_route(self::NAMESPACE, '/cart/clear', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'clearCart'],
            'permission_callback' => '__return_true',
        ]);

        // Apply coupon
        register_rest_route(self::NAMESPACE, '/cart/coupon', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'applyCoupon'],
            'permission_callback' => '__return_true',
            'args' => [
                'code' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Remove coupon
        register_rest_route(self::NAMESPACE, '/cart/coupon', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [self::class, 'removeCoupon'],
            'permission_callback' => '__return_true',
        ]);

        // Get mini cart settings (for admin)
        register_rest_route(self::NAMESPACE, '/mini-cart/settings', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'getSettings'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);

        // Update mini cart settings (for admin)
        register_rest_route(self::NAMESPACE, '/mini-cart/settings', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [self::class, 'updateSettings'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);
    }

    /**
     * Check if user has admin permission
     */
    public static function checkAdminPermission() {
        return current_user_can('manage_options');
    }

    /**
     * Get cart data
     *
     * @return \WP_REST_Response
     */
    public static function getCart() {
        $cart_items = \wpdmpp_get_cart_data();
        $formatted_items = self::formatCartItems($cart_items);
        $coupon = \wpdmpp_get_cart_coupon();

        $response = [
            'success' => true,
            'data' => [
                'items' => $formatted_items,
                'item_count' => self::getTotalItemCount($cart_items),
                'subtotal' => (float) \wpdmpp_get_cart_subtotal(),
                'discount' => (float) \wpdmpp_get_cart_discount(),
                'total' => (float) \wpdmpp_get_cart_total(),
                'subtotal_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_subtotal()),
                'discount_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_discount()),
                'total_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_total()),
                'currency' => [
                    'code' => \wpdmpp_currency_code(),
                    'symbol' => \wpdmpp_currency_sign(),
                    'position' => \wpdmpp_currency_sign_position(),
                ],
                'coupon' => $coupon ? [
                    'code' => $coupon['code'] ?? '',
                    'discount' => (float) ($coupon['discount'] ?? 0),
                ] : null,
                'cart_url' => \wpdmpp_cart_page(),
                'checkout_url' => \wpdmpp_cart_page(['step' => 'checkout']),
                'is_empty' => empty($formatted_items),
            ],
        ];

        return new \WP_REST_Response($response, 200);
    }

    /**
     * Format cart items for API response
     *
     * @param array $cart_items Raw cart data
     * @return array Formatted items
     */
    private static function formatCartItems($cart_items) {
        $formatted = [];

        if (!is_array($cart_items) || empty($cart_items)) {
            return $formatted;
        }

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

            // Get icon if no thumbnail
            if (!$thumbnail_url) {
                $icon = get_post_meta($pid, '__wpdm_icon', true);
                $thumbnail_url = $icon ?: '';
            }

            $formatted[] = [
                'id' => $pid,
                'product_id' => $pid,
                'name' => $post->post_title,
                'url' => get_permalink($pid),
                'thumbnail' => $thumbnail_url,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'line_total' => $unit_price * $quantity,
                'unit_price_formatted' => \wpdmpp_price_format($unit_price),
                'line_total_formatted' => \wpdmpp_price_format($unit_price * $quantity),
                'variation' => isset($item['variation']) ? $item['variation'] : [],
                'license' => isset($item['license']) ? $item['license'] : '',
                'coupon_discount' => isset($item['coupon_amount']) ? (float) $item['coupon_amount'] : 0,
                'role_discount' => isset($item['discount_amount']) ? (float) $item['discount_amount'] : 0,
            ];
        }

        return $formatted;
    }

    /**
     * Get total item count
     *
     * @param array $cart_items
     * @return int
     */
    private static function getTotalItemCount($cart_items) {
        $count = 0;
        if (is_array($cart_items)) {
            foreach ($cart_items as $item) {
                $count += isset($item['quantity']) ? (int) $item['quantity'] : 1;
            }
        }
        return $count;
    }

    /**
     * Add item to cart
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function addItem(\WP_REST_Request $request) {
        $product_id = $request->get_param('product_id');
        $quantity = $request->get_param('quantity') ?: 1;
        $license = $request->get_param('license') ?: '';
        $variation = $request->get_param('variation') ?: [];

        // Validate product
        if (get_post_type($product_id) !== 'wpdmpro') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Invalid product.', 'wpdm-premium-packages'),
            ], 400);
        }

        // Check if cart is locked
        if (WPDMPP()->cart->isLocked()) {
            WPDMPP()->cart->clear();
        }

        // Build request params for cart
        $_REQUEST['quantity'] = $quantity;
        $_REQUEST['license'] = $license;
        $_REQUEST['variation'] = $variation;

        // Add to cart
        $cart_data = WPDMPP()->cart->addItem($product_id, $license, $_REQUEST);

        // Clear any existing coupon when adding new items
        WPDMPP()->cart->clearCoupon();

        // Get product name for message
        $product_name = get_the_title($product_id);

        return new \WP_REST_Response([
            'success' => true,
            'message' => sprintf(__('%s has been added to your cart.', 'wpdm-premium-packages'), $product_name),
            'cart' => self::getCartSummary(),
        ], 200);
    }

    /**
     * Update item quantity
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function updateItem(\WP_REST_Request $request) {
        $product_id = $request->get_param('id');
        $quantity = $request->get_param('quantity');

        if ($quantity < 1) {
            return self::removeItem($request);
        }

        $cart_data = \wpdmpp_get_cart_data();

        if (!isset($cart_data[$product_id])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Item not found in cart.', 'wpdm-premium-packages'),
            ], 404);
        }

        $cart_data[$product_id]['quantity'] = $quantity;
        \wpdmpp_update_cart_data($cart_data);
        \wpdmpp_calculate_discount();

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Cart updated.', 'wpdm-premium-packages'),
            'cart' => self::getCartSummary(),
        ], 200);
    }

    /**
     * Remove item from cart
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function removeItem(\WP_REST_Request $request) {
        $product_id = $request->get_param('id');

        WPDMPP()->cart->removeItem($product_id);

        // Clear cart completely if empty
        if (!count(WPDMPP()->cart->getItems())) {
            WPDMPP()->cart->clear();
        } else {
            WPDMPP()->cart->clearCoupon();
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Item removed from cart.', 'wpdm-premium-packages'),
            'cart' => self::getCartSummary(),
        ], 200);
    }

    /**
     * Clear entire cart
     *
     * @return \WP_REST_Response
     */
    public static function clearCart() {
        WPDMPP()->cart->clear();

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Cart cleared.', 'wpdm-premium-packages'),
            'cart' => self::getCartSummary(),
        ], 200);
    }

    /**
     * Apply coupon code
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function applyCoupon(\WP_REST_Request $request) {
        $code = $request->get_param('code');

        $discount = CouponCodes::validate_coupon($code);

        if ($discount <= 0) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Invalid or expired coupon code.', 'wpdm-premium-packages'),
            ], 400);
        }

        WPDMPP()->cart->applyCoupon($code);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Coupon applied successfully.', 'wpdm-premium-packages'),
            'discount' => $discount,
            'cart' => self::getCartSummary(),
        ], 200);
    }

    /**
     * Remove coupon
     *
     * @return \WP_REST_Response
     */
    public static function removeCoupon() {
        WPDMPP()->cart->clearCoupon();

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Coupon removed.', 'wpdm-premium-packages'),
            'cart' => self::getCartSummary(),
        ], 200);
    }

    /**
     * Get cart summary (lightweight response for updates)
     *
     * @return array
     */
    private static function getCartSummary() {
        $cart_items = \wpdmpp_get_cart_data();
        $coupon = \wpdmpp_get_cart_coupon();

        return [
            'item_count' => self::getTotalItemCount($cart_items),
            'items' => self::formatCartItems($cart_items),
            'subtotal' => (float) \wpdmpp_get_cart_subtotal(),
            'discount' => (float) \wpdmpp_get_cart_discount(),
            'total' => (float) \wpdmpp_get_cart_total(),
            'subtotal_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_subtotal()),
            'discount_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_discount()),
            'total_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_total()),
            'currency_symbol' => \wpdmpp_currency_sign(),
            'coupon' => $coupon ? [
                'code' => $coupon['code'] ?? '',
                'discount' => (float) ($coupon['discount'] ?? 0),
            ] : null,
            'is_empty' => empty($cart_items),
            'cart_url' => \wpdmpp_cart_page(),
            'checkout_url' => \wpdmpp_cart_page(['step' => 'checkout']),
        ];
    }

    /**
     * Get mini cart settings
     *
     * @return \WP_REST_Response
     */
    public static function getSettings() {
        $settings = get_option('wpdmpp_mini_cart_settings', self::getDefaultSettings());

        return new \WP_REST_Response([
            'success' => true,
            'data' => $settings,
        ], 200);
    }

    /**
     * Update mini cart settings
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function updateSettings(\WP_REST_Request $request) {
        $settings = $request->get_json_params();
        $defaults = self::getDefaultSettings();

        // Merge with defaults to ensure all keys exist
        $settings = wp_parse_args($settings, $defaults);

        // Sanitize settings
        $sanitized = [
            'enabled' => (bool) $settings['enabled'],
            'display_style' => in_array($settings['display_style'], ['dropdown', 'slide_panel', 'floating'])
                ? $settings['display_style']
                : 'dropdown',
            'position' => in_array($settings['position'], ['top-right', 'top-left', 'bottom-right', 'bottom-left'])
                ? $settings['position']
                : 'top-right',
            'show_item_count' => (bool) $settings['show_item_count'],
            'show_subtotal' => (bool) $settings['show_subtotal'],
            'show_thumbnails' => (bool) $settings['show_thumbnails'],
            'auto_open_on_add' => (bool) $settings['auto_open_on_add'],
            'auto_close_delay' => (int) $settings['auto_close_delay'],
            'mobile_full_screen' => (bool) $settings['mobile_full_screen'],
            'mobile_breakpoint' => (int) $settings['mobile_breakpoint'],
            'trigger_selector' => sanitize_text_field($settings['trigger_selector']),
            'auto_inject' => (bool) $settings['auto_inject'],
            'auto_inject_position' => in_array($settings['auto_inject_position'], ['header', 'menu', 'custom'])
                ? $settings['auto_inject_position']
                : 'header',
            'custom_css' => wp_strip_all_tags($settings['custom_css']),
            'icon_style' => in_array($settings['icon_style'], ['cart', 'bag', 'basket'])
                ? $settings['icon_style']
                : 'cart',
            'primary_color' => sanitize_hex_color($settings['primary_color']) ?: '#6366f1',
            'text_color' => sanitize_hex_color($settings['text_color']) ?: '#1e293b',
        ];

        update_option('wpdmpp_mini_cart_settings', $sanitized);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Settings saved.', 'wpdm-premium-packages'),
            'data' => $sanitized,
        ], 200);
    }

    /**
     * Get default mini cart settings
     *
     * @return array
     */
    public static function getDefaultSettings() {
        return [
            'enabled' => true,
            'display_style' => 'dropdown',
            'position' => 'top-right',
            'show_item_count' => true,
            'show_subtotal' => true,
            'show_thumbnails' => true,
            'auto_open_on_add' => true,
            'auto_close_delay' => 3000,
            'mobile_full_screen' => true,
            'mobile_breakpoint' => 768,
            'trigger_selector' => '',
            'auto_inject' => false,
            'auto_inject_position' => 'header',
            'custom_css' => '',
            'icon_style' => 'cart',
            'primary_color' => '#6366f1',
            'text_color' => '#1e293b',
        ];
    }
}

// Initialize the API
MiniCartAPI::init();
