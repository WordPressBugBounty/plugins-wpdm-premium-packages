<?php
/**
 * Product REST API Endpoint
 *
 * Handles product pricing-related REST API operations.
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\Product\Product;
use WPDMPP\Product\ProductService;

defined('ABSPATH') || exit;

class ProductEndpoint {

    /**
     * Product service instance
     *
     * @var ProductService
     */
    private ProductService $productService;

    /**
     * Constructor
     */
    public function __construct() {
        $this->productService = ProductService::getInstance();
    }

    /**
     * Register routes
     *
     * @return void
     */
    public function register(): void {
        $namespace = RestApi::API_NAMESPACE;

        // =====================================================================
        // PUBLIC ENDPOINTS
        // =====================================================================

        // GET /products - List premium products
        register_rest_route($namespace, '/products', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getProducts'],
            'permission_callback' => '__return_true',
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
                'category' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'min_price' => [
                    'type' => 'number',
                    'default' => 0,
                    'minimum' => 0,
                ],
                'max_price' => [
                    'type' => 'number',
                    'default' => 0,
                    'minimum' => 0,
                ],
                'on_sale' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'license_type' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'orderby' => [
                    'type' => 'string',
                    'default' => 'date',
                    'enum' => ['date', 'title', 'price', 'sales'],
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC',
                    'enum' => ['ASC', 'DESC'],
                ],
            ],
        ]);

        // GET /products/{id} - Get single product
        register_rest_route($namespace, '/products/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getProduct'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // GET /products/{id}/price - Get product price
        register_rest_route($namespace, '/products/(?P<id>\d+)/price', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getProductPrice'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'license' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /products/{id}/licenses - Get product license tiers
        register_rest_route($namespace, '/products/(?P<id>\d+)/licenses', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getProductLicenses'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // GET /products/{id}/variations - Get product extra gigs
        register_rest_route($namespace, '/products/(?P<id>\d+)/variations', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getProductVariations'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // POST /products/{id}/calculate - Calculate price with options
        register_rest_route($namespace, '/products/(?P<id>\d+)/calculate', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'calculatePrice'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'license' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'gigs' => [
                    'type' => 'array',
                    'default' => [],
                    'items' => ['type' => 'string'],
                ],
                'quantity' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                ],
            ],
        ]);

        // GET /products/on-sale - Get products on sale
        register_rest_route($namespace, '/products/on-sale', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getProductsOnSale'],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ],
            ],
        ]);

        // GET /products/best-sellers - Get best selling products
        register_rest_route($namespace, '/products/best-sellers', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getBestSellers'],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ],
            ],
        ]);

        // GET /products/recent - Get recently added products
        register_rest_route($namespace, '/products/recent', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getRecentProducts'],
            'permission_callback' => '__return_true',
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ],
            ],
        ]);

        // GET /products/{id}/purchased - Check if user purchased product
        register_rest_route($namespace, '/products/(?P<id>\d+)/purchased', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'checkPurchased'],
            'permission_callback' => [RestApi::class, 'checkAuthenticated'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // GET /license-types - Get global license types
        register_rest_route($namespace, '/license-types', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getLicenseTypes'],
            'permission_callback' => '__return_true',
        ]);

        // =====================================================================
        // ADMIN ENDPOINTS
        // =====================================================================

        // GET /admin/products - List all products (with pricing data)
        register_rest_route($namespace, '/admin/products', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetProducts'],
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
                'premium_only' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'on_sale' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'orderby' => [
                    'type' => 'string',
                    'default' => 'date',
                    'enum' => ['date', 'title', 'price', 'modified'],
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC',
                    'enum' => ['ASC', 'DESC'],
                ],
            ],
        ]);

        // GET /admin/products/{id} - Get single product (admin)
        register_rest_route($namespace, '/admin/products/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetProduct'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // PUT /admin/products/{id} - Update product pricing
        register_rest_route($namespace, '/admin/products/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'adminUpdateProduct'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => array_merge(
                ['id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ]],
                $this->getProductWriteArgs()
            ),
        ]);

        // DELETE /admin/products/{id}/pricing - Delete product pricing (reset to free)
        register_rest_route($namespace, '/admin/products/(?P<id>\d+)/pricing', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'adminDeletePricing'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // GET /admin/products/statistics - Get product statistics
        register_rest_route($namespace, '/admin/products/statistics', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetStatistics'],
            'permission_callback' => [$this, 'checkAdminCapability'],
        ]);
    }

    // =========================================================================
    // PUBLIC ENDPOINTS
    // =========================================================================

    /**
     * Get premium products
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getProducts(\WP_REST_Request $request): \WP_REST_Response {
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
        $category = $request->get_param('category');
        $search = $request->get_param('search');
        $minPrice = $request->get_param('min_price');
        $maxPrice = $request->get_param('max_price');
        $onSale = $request->get_param('on_sale');
        $licenseType = $request->get_param('license_type');
        $orderby = $request->get_param('orderby');
        $order = $request->get_param('order');

        $args = [
            'posts_per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'orderby' => $this->mapOrderBy($orderby),
            'order' => $order,
        ];

        $products = [];

        // Filter by conditions
        if (!empty($search)) {
            $products = $this->productService->searchProducts($search, $args);
        } elseif ($onSale) {
            $products = $this->productService->getProductsOnSale($args);
        } elseif (!empty($licenseType)) {
            $products = $this->productService->getProductsByLicenseType($licenseType, $args);
        } elseif ($minPrice > 0 || $maxPrice > 0) {
            $max = $maxPrice > 0 ? $maxPrice : PHP_INT_MAX;
            $products = $this->productService->getProductsByPriceRange($minPrice, $max, $args);
        } elseif (!empty($category)) {
            $products = $this->productService->getProductsByCategory($category, $args);
        } else {
            $products = $this->productService->getPremiumProducts($args);
        }

        $data = [];
        foreach ($products as $product) {
            $data[] = $this->formatProductResponse($product);
        }

        return RestApi::success([
            'products' => $data,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Get single product
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getProduct(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $product = $this->productService->getProduct($id);

        if (!$product) {
            return RestApi::error(__('Product not found.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success([
            'product' => $this->formatProductResponse($product, true),
        ]);
    }

    /**
     * Get product price
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getProductPrice(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $license = $request->get_param('license');

        $product = $this->productService->getProduct($id);

        if (!$product) {
            return RestApi::error(__('Product not found.', 'wpdm-premium-packages'), 404);
        }

        $licenseId = !empty($license) ? $license : null;
        $basePrice = $product->getEffectivePrice($licenseId);
        $discountedPrice = $product->getDiscountedPrice($licenseId);
        $roleDiscount = $product->getRoleDiscount();

        return RestApi::success([
            'product_id' => $id,
            'base_price' => $product->getBasePrice(),
            'sales_price' => $product->getSalesPrice(),
            'sale_active' => $product->isSaleActive(),
            'effective_price' => $basePrice,
            'discounted_price' => $discountedPrice,
            'role_discount' => $roleDiscount,
            'formatted_price' => $this->formatPrice($discountedPrice),
            'license' => $licenseId,
        ]);
    }

    /**
     * Get product license tiers
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getProductLicenses(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $product = $this->productService->getProduct($id);

        if (!$product) {
            return RestApi::error(__('Product not found.', 'wpdm-premium-packages'), 404);
        }

        if (!$product->isLicenseEnabled()) {
            return RestApi::success([
                'product_id' => $id,
                'license_enabled' => false,
                'licenses' => [],
            ]);
        }

        $licenses = $product->getActiveLicenses();
        $formatted = [];

        foreach ($licenses as $licenseId => $license) {
            $formatted[] = [
                'id' => $licenseId,
                'name' => $license['name'] ?? $licenseId,
                'price' => $license['price'],
                'formatted_price' => $this->formatPrice($license['price']),
                'domain_limit' => $license['domain_limit'] ?? 0,
                'validity' => $license['validity'] ?? 0,
            ];
        }

        return RestApi::success([
            'product_id' => $id,
            'license_enabled' => true,
            'licenses' => $formatted,
        ]);
    }

    /**
     * Get product variations (extra gigs)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getProductVariations(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $product = $this->productService->getProduct($id);

        if (!$product) {
            return RestApi::error(__('Product not found.', 'wpdm-premium-packages'), 404);
        }

        if (!$product->hasVariations()) {
            return RestApi::success([
                'product_id' => $id,
                'variations_enabled' => false,
                'variations' => [],
            ]);
        }

        $variations = $product->getVariations();
        $formatted = [];

        foreach ($variations as $groupIndex => $group) {
            $options = [];
            if (isset($group['options']) && is_array($group['options'])) {
                foreach ($group['options'] as $optionIndex => $option) {
                    $options[] = [
                        'id' => "{$groupIndex}_{$optionIndex}",
                        'name' => $option['name'] ?? '',
                        'description' => $option['description'] ?? '',
                        'price' => (float) ($option['price'] ?? 0),
                        'formatted_price' => $this->formatPrice((float) ($option['price'] ?? 0)),
                    ];
                }
            }

            $formatted[] = [
                'group_index' => $groupIndex,
                'group_name' => $group['name'] ?? '',
                'options' => $options,
            ];
        }

        return RestApi::success([
            'product_id' => $id,
            'variations_enabled' => true,
            'variations' => $formatted,
        ]);
    }

    /**
     * Calculate price with options
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function calculatePrice(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $license = $request->get_param('license');
        $gigs = $request->get_param('gigs');
        $quantity = $request->get_param('quantity');

        $product = $this->productService->getProduct($id);

        if (!$product) {
            return RestApi::error(__('Product not found.', 'wpdm-premium-packages'), 404);
        }

        $licenseId = !empty($license) ? $license : null;
        $basePrice = $product->getDiscountedPrice($licenseId);
        $gigsCost = $product->calculateGigsCost($gigs);
        $unitTotal = $basePrice + $gigsCost;
        $lineTotal = $unitTotal * $quantity;

        return RestApi::success([
            'product_id' => $id,
            'license' => $licenseId,
            'gigs' => $gigs,
            'quantity' => $quantity,
            'base_price' => $basePrice,
            'gigs_cost' => $gigsCost,
            'unit_total' => $unitTotal,
            'line_total' => $lineTotal,
            'formatted_unit_total' => $this->formatPrice($unitTotal),
            'formatted_line_total' => $this->formatPrice($lineTotal),
        ]);
    }

    /**
     * Get products on sale
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getProductsOnSale(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $request->get_param('limit');

        $products = $this->productService->getProductsOnSale(['posts_per_page' => $limit]);

        $data = [];
        foreach ($products as $product) {
            $data[] = $this->formatProductResponse($product);
        }

        return RestApi::success([
            'products' => $data,
            'count' => count($data),
        ]);
    }

    /**
     * Get best sellers
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getBestSellers(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $request->get_param('limit');

        $products = $this->productService->getBestSellers($limit);

        $data = [];
        foreach ($products as $product) {
            $data[] = $this->formatProductResponse($product);
        }

        return RestApi::success([
            'products' => $data,
            'count' => count($data),
        ]);
    }

    /**
     * Get recent products
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getRecentProducts(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $request->get_param('limit');

        $products = $this->productService->getRecentProducts($limit);

        $data = [];
        foreach ($products as $product) {
            $data[] = $this->formatProductResponse($product);
        }

        return RestApi::success([
            'products' => $data,
            'count' => count($data),
        ]);
    }

    /**
     * Check if user has purchased product
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function checkPurchased(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $orderId = $this->productService->hasPurchased($id);

        return RestApi::success([
            'product_id' => $id,
            'purchased' => $orderId !== false,
            'order_id' => $orderId ?: null,
        ]);
    }

    /**
     * Get global license types
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getLicenseTypes(\WP_REST_Request $request): \WP_REST_Response {
        $licenseTypes = $this->productService->getGlobalLicenseTypes();

        $formatted = [];
        foreach ($licenseTypes as $id => $type) {
            $formatted[] = [
                'id' => $id,
                'name' => $type['name'] ?? $id,
                'domain' => $type['domain'] ?? 0,
                'validity' => $type['validity'] ?? 0,
            ];
        }

        return RestApi::success([
            'license_types' => $formatted,
        ]);
    }

    // =========================================================================
    // ADMIN ENDPOINTS
    // =========================================================================

    /**
     * Get products (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetProducts(\WP_REST_Request $request): \WP_REST_Response {
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
        $search = $request->get_param('search');
        $premiumOnly = $request->get_param('premium_only');
        $onSale = $request->get_param('on_sale');
        $orderby = $request->get_param('orderby');
        $order = $request->get_param('order');

        $args = [
            'posts_per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'orderby' => $this->mapOrderBy($orderby),
            'order' => $order,
        ];

        if (!empty($search)) {
            $products = $this->productService->searchProducts($search, $args);
        } elseif ($onSale) {
            $products = $this->productService->getProductsOnSale($args);
        } elseif ($premiumOnly) {
            $products = $this->productService->getPremiumProducts($args);
        } else {
            // Get all wpdmpro posts with pricing
            $args['post_type'] = 'wpdmpro';
            $args['post_status'] = 'any';
            $query = new \WP_Query($args);
            $posts = $query->get_posts();

            $products = [];
            foreach ($posts as $post) {
                $products[] = new Product($post->ID);
            }

            wp_reset_postdata();
        }

        $data = [];
        foreach ($products as $product) {
            $data[] = $this->formatProductResponse($product, true);
        }

        $totalCount = $premiumOnly
            ? $this->productService->countPremiumProducts()
            : wp_count_posts('wpdmpro')->publish;

        return RestApi::success([
            'products' => $data,
            'total' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($totalCount / $perPage),
        ]);
    }

    /**
     * Get single product (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetProduct(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $product = $this->productService->getProduct($id);

        if (!$product) {
            return RestApi::error(__('Product not found.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success([
            'product' => $this->formatProductResponse($product, true),
        ]);
    }

    /**
     * Update product pricing (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminUpdateProduct(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $data = $this->extractProductData($request);

        $result = $this->productService->saveProduct($id, $data);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([
            'product' => $this->formatProductResponse($result['product'], true),
        ], $result['message']);
    }

    /**
     * Delete product pricing (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminDeletePricing(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $result = $this->productService->deleteProduct($id);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([], $result['message']);
    }

    /**
     * Get product statistics (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetStatistics(\WP_REST_Request $request): \WP_REST_Response {
        $stats = $this->productService->getStatistics();

        return RestApi::success([
            'statistics' => $stats,
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
     * Get product write arguments for route registration
     *
     * @return array
     */
    private function getProductWriteArgs(): array {
        return [
            'product_code' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'base_price' => [
                'type' => 'number',
                'minimum' => 0,
            ],
            'sales_price' => [
                'type' => 'number',
                'minimum' => 0,
            ],
            'sales_price_expire' => [
                'type' => 'integer',
                'minimum' => 0,
            ],
            'pay_as_you_want' => [
                'type' => 'boolean',
            ],
            'variations_enabled' => [
                'type' => 'boolean',
            ],
            'variations' => [
                'type' => 'array',
            ],
            'license_enabled' => [
                'type' => 'boolean',
            ],
            'licenses' => [
                'type' => 'object',
            ],
            'license_key_required' => [
                'type' => 'boolean',
            ],
            'role_discounts' => [
                'type' => 'object',
            ],
            'assign_role' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'free_downloads' => [
                'type' => 'array',
            ],
        ];
    }

    /**
     * Extract product data from request
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    private function extractProductData(\WP_REST_Request $request): array {
        $data = [];
        $fields = [
            'product_code', 'base_price', 'sales_price', 'sales_price_expire',
            'pay_as_you_want', 'variations_enabled', 'variations',
            'license_enabled', 'licenses', 'license_key_required',
            'role_discounts', 'assign_role', 'free_downloads',
        ];

        foreach ($fields as $field) {
            if ($request->has_param($field)) {
                $data[$field] = $request->get_param($field);
            }
        }

        return $data;
    }

    /**
     * Format product for API response
     *
     * @param Product $product
     * @param bool    $detailed Include all details
     * @return array
     */
    private function formatProductResponse(Product $product, bool $detailed = false): array {
        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'product_code' => $product->getProductCode(),
            'is_premium' => $product->isPremium(),
            'price' => $product->getEffectivePrice(),
            'discounted_price' => $product->getDiscountedPrice(),
            'formatted_price' => $this->formatPrice($product->getDiscountedPrice()),
            'sale_active' => $product->isSaleActive(),
            'has_licenses' => $product->isLicenseEnabled() && !empty($product->getLicenses()),
            'has_variations' => $product->hasVariations(),
        ];

        // Add price range for products with licenses
        if ($product->isLicenseEnabled()) {
            $priceRange = $product->getPriceRange();
            $data['price_range'] = $priceRange;
            if ($priceRange[0] !== $priceRange[1]) {
                $data['formatted_price_range'] = $this->formatPrice($priceRange[0]) . ' - ' . $this->formatPrice($priceRange[1]);
            }
        }

        // Add sale info
        if ($product->isSaleActive()) {
            $data['sale_info'] = [
                'original_price' => $product->getBasePrice(),
                'sale_price' => $product->getSalesPrice(),
                'formatted_original' => $this->formatPrice($product->getBasePrice()),
                'formatted_sale' => $this->formatPrice($product->getSalesPrice()),
                'expires' => $product->getSalesPriceExpire(),
                'discount_percent' => $product->getBasePrice() > 0
                    ? round((($product->getBasePrice() - $product->getSalesPrice()) / $product->getBasePrice()) * 100)
                    : 0,
            ];
        }

        // Add detailed info
        if ($detailed) {
            $data = array_merge($data, [
                'base_price' => $product->getBasePrice(),
                'sales_price' => $product->getSalesPrice(),
                'sales_price_expire' => $product->getSalesPriceExpire(),
                'pay_as_you_want' => $product->isPayAsYouWant(),
                'license_enabled' => $product->isLicenseEnabled(),
                'license_key_required' => $product->isLicenseKeyRequired(),
                'role_discounts' => $product->getRoleDiscounts(),
                'current_role_discount' => $product->getRoleDiscount(),
                'assign_role' => $product->getAssignRole(),
                'free_downloads' => $product->getFreeDownloads(),
                'licenses' => $product->getActiveLicenses(),
                'variations' => $product->getVariations(),
            ]);
        }

        // Add thumbnail
        $thumbnailId = get_post_thumbnail_id($product->getId());
        if ($thumbnailId) {
            $data['thumbnail'] = wp_get_attachment_image_url($thumbnailId, 'medium');
        }

        // Add permalink
        $data['url'] = get_permalink($product->getId());

        return $data;
    }

    /**
     * Format price with currency
     *
     * @param float $price
     * @return string
     */
    private function formatPrice(float $price): string {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($price, true, true);
        }

        $sign = function_exists('wpdmpp_currency_sign') ? wpdmpp_currency_sign() : '$';
        return $sign . number_format($price, 2);
    }

    /**
     * Map orderby parameter to WP_Query format
     *
     * @param string $orderby
     * @return string
     */
    private function mapOrderBy(string $orderby): string {
        $map = [
            'date' => 'date',
            'title' => 'title',
            'price' => 'meta_value_num',
            'sales' => 'meta_value_num',
            'modified' => 'modified',
        ];

        return $map[$orderby] ?? 'date';
    }
}
