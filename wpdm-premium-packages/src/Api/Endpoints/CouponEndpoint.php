<?php
/**
 * Coupon REST API Endpoint
 *
 * Handles coupon-related REST API operations.
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\Coupon\Coupon;
use WPDMPP\Coupon\CouponService;

defined('ABSPATH') || exit;

class CouponEndpoint {

    /**
     * Coupon service instance
     *
     * @var CouponService
     */
    private CouponService $couponService;

    /**
     * Constructor
     */
    public function __construct() {
        $this->couponService = CouponService::getInstance();
    }

    /**
     * Register routes
     *
     * @return void
     */
    public function register(): void {
        $namespace = RestApi::API_NAMESPACE;

        // =====================================================================
        // PUBLIC ENDPOINTS (for cart/checkout)
        // =====================================================================

        // POST /coupon/validate - Validate coupon
        register_rest_route($namespace, '/coupon/validate', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'validateCoupon'],
            'permission_callback' => '__return_true',
            'args' => [
                'code' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'cart_total' => [
                    'required' => true,
                    'type' => 'number',
                    'minimum' => 0,
                ],
                'product_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'email' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                    'default' => '',
                ],
            ],
        ]);

        // POST /coupon/apply - Apply coupon to cart
        register_rest_route($namespace, '/coupon/apply', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'applyCoupon'],
            'permission_callback' => '__return_true',
            'args' => [
                'code' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // DELETE /coupon/applied - Remove applied coupon
        register_rest_route($namespace, '/coupon/applied', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'removeCoupon'],
            'permission_callback' => '__return_true',
        ]);

        // GET /coupon/applied - Get currently applied coupon
        register_rest_route($namespace, '/coupon/applied', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getAppliedCoupon'],
            'permission_callback' => '__return_true',
        ]);

        // =====================================================================
        // ADMIN ENDPOINTS
        // =====================================================================

        // GET /admin/coupons - List all coupons
        register_rest_route($namespace, '/admin/coupons', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetCoupons'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
                'search' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type' => [
                    'type' => 'string',
                    'default' => '',
                    'enum' => ['', 'percent', 'fixed'],
                ],
                'product_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'active_only' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'auto_apply' => [
                    'type' => 'boolean',
                    'default' => null,
                ],
                'orderby' => [
                    'type' => 'string',
                    'default' => 'ID',
                    'enum' => ['ID', 'code', 'discount', 'expire_date', 'used', 'usage_limit'],
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC',
                    'enum' => ['ASC', 'DESC'],
                ],
            ],
        ]);

        // POST /admin/coupons - Create coupon
        register_rest_route($namespace, '/admin/coupons', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminCreateCoupon'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => $this->getCouponWriteArgs(true),
        ]);

        // GET /admin/coupons/{id} - Get single coupon
        register_rest_route($namespace, '/admin/coupons/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetCoupon'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // PUT /admin/coupons/{id} - Update coupon
        register_rest_route($namespace, '/admin/coupons/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'adminUpdateCoupon'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => array_merge(
                ['id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ]],
                $this->getCouponWriteArgs(false)
            ),
        ]);

        // DELETE /admin/coupons/{id} - Delete coupon
        register_rest_route($namespace, '/admin/coupons/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'adminDeleteCoupon'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // POST /admin/coupons/bulk-delete - Bulk delete coupons
        register_rest_route($namespace, '/admin/coupons/bulk-delete', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminBulkDeleteCoupons'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'ids' => [
                    'required' => true,
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
        ]);

        // GET /admin/coupons/{id}/stats - Get coupon usage statistics
        register_rest_route($namespace, '/admin/coupons/(?P<id>\d+)/stats', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetCouponStats'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // POST /admin/coupons/generate-code - Generate unique code
        register_rest_route($namespace, '/admin/coupons/generate-code', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminGenerateCode'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'length' => [
                    'type' => 'integer',
                    'default' => 8,
                    'minimum' => 4,
                    'maximum' => 20,
                ],
                'prefix' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /admin/coupons/expired - Get expired coupons
        register_rest_route($namespace, '/admin/coupons/expired', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetExpiredCoupons'],
            'permission_callback' => [$this, 'checkAdminCapability'],
        ]);

        // GET /admin/coupons/exhausted - Get exhausted coupons
        register_rest_route($namespace, '/admin/coupons/exhausted', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetExhaustedCoupons'],
            'permission_callback' => [$this, 'checkAdminCapability'],
        ]);

        // GET /admin/coupons/count - Get coupon count
        register_rest_route($namespace, '/admin/coupons/count', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetCouponCount'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'active_only' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'type' => [
                    'type' => 'string',
                    'default' => '',
                    'enum' => ['', 'percent', 'fixed'],
                ],
            ],
        ]);
    }

    // =========================================================================
    // PUBLIC ENDPOINTS
    // =========================================================================

    /**
     * Validate a coupon code
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function validateCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $code = $request->get_param('code');
        $cartTotal = (float) $request->get_param('cart_total');
        $productId = (int) $request->get_param('product_id');
        $email = $request->get_param('email');

        // Get cart items if using the cart service
        $cartItems = [];
        if (class_exists('\WPDMPP\Cart\CartService')) {
            $cartService = \WPDMPP\Cart\CartService::instance();
            $cartItems = $cartService->getItems();
        }

        $result = $this->couponService->validateCoupon(
            $code,
            $cartTotal,
            $cartItems,
            $email ?: null,
            $productId ?: null
        );

        if (!$result['valid']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([
            'valid' => true,
            'coupon' => $result['coupon']->toArray(),
            'discount' => $result['discount'],
            'discount_formatted' => $result['discount_formatted'],
        ], __('Coupon is valid.', 'wpdm-premium-packages'));
    }

    /**
     * Apply coupon to cart
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function applyCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $code = $request->get_param('code');

        // Get cart data
        $cartTotal = 0;
        $cartItems = [];

        if (class_exists('\WPDMPP\Cart\CartService')) {
            $cartService = \WPDMPP\Cart\CartService::instance();
            $cartTotal = $cartService->getSubtotal();
            $cartItems = $cartService->getItems();
        }

        // Get user email if logged in
        $email = null;
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $email = $user->user_email;
        }

        $result = $this->couponService->applyCoupon($code, $cartTotal, $cartItems, $email);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([
            'coupon' => $result['coupon'],
            'discount' => $result['discount'],
        ], $result['message']);
    }

    /**
     * Remove applied coupon
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function removeCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $result = $this->couponService->removeCoupon();

        return RestApi::success([], $result['message']);
    }

    /**
     * Get currently applied coupon
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getAppliedCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $coupon = $this->couponService->getAppliedCoupon();

        if (!$coupon) {
            return RestApi::success([
                'coupon' => null,
                'applied' => false,
            ]);
        }

        return RestApi::success([
            'coupon' => $coupon->toArray(),
            'applied' => true,
        ]);
    }

    // =========================================================================
    // ADMIN ENDPOINTS
    // =========================================================================

    /**
     * Get all coupons (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetCoupons(\WP_REST_Request $request): \WP_REST_Response {
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');

        $args = [
            'search' => $request->get_param('search'),
            'type' => $request->get_param('type'),
            'product_id' => $request->get_param('product_id'),
            'active_only' => $request->get_param('active_only'),
            'orderby' => $request->get_param('orderby'),
            'order' => $request->get_param('order'),
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        // Handle null/undefined auto_apply
        $autoApply = $request->get_param('auto_apply');
        if ($autoApply !== null) {
            $args['auto_apply'] = $autoApply;
        }

        $result = $this->couponService->getCoupons($args);

        $data = [];
        foreach ($result['coupons'] as $coupon) {
            $data[] = $this->formatCouponResponse($coupon);
        }

        return RestApi::success([
            'coupons' => $data,
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($result['total'] / $perPage),
        ]);
    }

    /**
     * Create coupon (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminCreateCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $data = $this->extractCouponData($request);

        $result = $this->couponService->createCoupon($data);

        if (!$result['success']) {
            return RestApi::error(
                $result['errors']['general'] ?? $result['errors']['code'] ?? __('Failed to create coupon.', 'wpdm-premium-packages'),
                400,
                $result['errors']
            );
        }

        return RestApi::success([
            'coupon' => $this->formatCouponResponse($result['coupon']),
        ], __('Coupon created successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Get single coupon (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $coupon = $this->couponService->findById($id);

        if (!$coupon) {
            return RestApi::error(__('Coupon not found.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success([
            'coupon' => $this->formatCouponResponse($coupon),
        ]);
    }

    /**
     * Update coupon (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminUpdateCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $data = $this->extractCouponData($request);

        $result = $this->couponService->updateCoupon($id, $data);

        if (!$result['success']) {
            return RestApi::error(
                $result['errors']['general'] ?? $result['errors']['code'] ?? __('Failed to update coupon.', 'wpdm-premium-packages'),
                400,
                $result['errors']
            );
        }

        return RestApi::success([
            'coupon' => $this->formatCouponResponse($result['coupon']),
        ], __('Coupon updated successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Delete coupon (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminDeleteCoupon(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $result = $this->couponService->deleteCoupon($id);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([], $result['message']);
    }

    /**
     * Bulk delete coupons (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminBulkDeleteCoupons(\WP_REST_Request $request): \WP_REST_Response {
        $ids = $request->get_param('ids');

        $result = $this->couponService->bulkDeleteCoupons($ids);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([
            'deleted' => $result['deleted'],
        ], $result['message']);
    }

    /**
     * Get coupon usage statistics (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetCouponStats(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $coupon = $this->couponService->findById($id);

        if (!$coupon) {
            return RestApi::error(__('Coupon not found.', 'wpdm-premium-packages'), 404);
        }

        $stats = $this->couponService->getCouponStats($coupon->getCode());

        return RestApi::success([
            'statistics' => $stats,
        ]);
    }

    /**
     * Generate unique code (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGenerateCode(\WP_REST_Request $request): \WP_REST_Response {
        $length = $request->get_param('length');
        $prefix = $request->get_param('prefix');

        $code = $this->couponService->generateCode($length, $prefix);

        return RestApi::success([
            'code' => $code,
        ]);
    }

    /**
     * Get expired coupons (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetExpiredCoupons(\WP_REST_Request $request): \WP_REST_Response {
        $coupons = $this->couponService->getExpiredCoupons();

        $data = [];
        foreach ($coupons as $coupon) {
            $data[] = $this->formatCouponResponse($coupon);
        }

        return RestApi::success([
            'coupons' => $data,
            'count' => count($data),
        ]);
    }

    /**
     * Get exhausted coupons (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetExhaustedCoupons(\WP_REST_Request $request): \WP_REST_Response {
        $coupons = $this->couponService->getExhaustedCoupons();

        $data = [];
        foreach ($coupons as $coupon) {
            $data[] = $this->formatCouponResponse($coupon);
        }

        return RestApi::success([
            'coupons' => $data,
            'count' => count($data),
        ]);
    }

    /**
     * Get coupon count (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetCouponCount(\WP_REST_Request $request): \WP_REST_Response {
        $args = [];

        if ($request->get_param('active_only')) {
            $args['active_only'] = true;
        }

        if ($request->get_param('type')) {
            $args['type'] = $request->get_param('type');
        }

        $count = $this->couponService->getCount($args);

        return RestApi::success([
            'count' => $count,
        ]);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if user has admin capability
     *
     * @return bool
     */
    public function checkAdminCapability(): bool {
        return current_user_can(WPDMPP_ADMIN_CAP);
    }

    /**
     * Get coupon write arguments for route registration
     *
     * @param bool $required Whether fields are required
     * @return array
     */
    private function getCouponWriteArgs(bool $required): array {
        return [
            'code' => [
                'required' => $required,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'type' => [
                'required' => $required,
                'type' => 'string',
                'enum' => ['percent', 'fixed'],
            ],
            'discount' => [
                'required' => $required,
                'type' => 'number',
                'minimum' => 0,
            ],
            'min_order_amount' => [
                'type' => 'number',
                'default' => 0,
                'minimum' => 0,
            ],
            'max_order_amount' => [
                'type' => 'number',
                'default' => 0,
                'minimum' => 0,
            ],
            'product_id' => [
                'type' => 'integer',
                'default' => 0,
            ],
            'allowed_emails' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'expire_date' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'usage_limit' => [
                'type' => 'integer',
                'default' => 0,
                'minimum' => 0,
            ],
            'auto_apply' => [
                'type' => 'boolean',
                'default' => false,
            ],
        ];
    }

    /**
     * Extract coupon data from request
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    private function extractCouponData(\WP_REST_Request $request): array {
        $data = [];
        $fields = [
            'code', 'description', 'type', 'discount',
            'min_order_amount', 'max_order_amount', 'product_id',
            'allowed_emails', 'expire_date', 'usage_limit', 'auto_apply',
        ];

        foreach ($fields as $field) {
            if ($request->has_param($field)) {
                $data[$field] = $request->get_param($field);
            }
        }

        return $data;
    }

    /**
     * Format coupon for API response
     *
     * @param Coupon $coupon
     * @return array
     */
    private function formatCouponResponse(Coupon $coupon): array {
        $data = $coupon->toArray();

        // Add formatted discount
        if ($coupon->getType() === Coupon::TYPE_PERCENT) {
            $data['discount_formatted'] = $coupon->getDiscount() . '%';
        } else {
            $data['discount_formatted'] = wpdmpp_currency_sign() . number_format($coupon->getDiscount(), 2);
        }

        // Add status info
        $data['is_expired'] = $coupon->isExpired();
        $data['is_exhausted'] = $coupon->isExhausted();
        $data['is_active'] = !$coupon->isExpired() && !$coupon->isExhausted();
        $data['remaining_uses'] = $coupon->hasUsageLimit() ? $coupon->getRemainingUses() : null;

        // Add formatted dates
        if ($coupon->getExpireDate() > 0) {
            $data['expire_date_formatted'] = date_i18n(get_option('date_format'), $coupon->getExpireDate());
        } else {
            $data['expire_date_formatted'] = __('Never', 'wpdm-premium-packages');
        }

        return $data;
    }
}
