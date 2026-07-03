<?php
/**
 * Customer REST API Endpoint
 *
 * Handles REST API requests for customer management.
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\Customer\Customer;
use WPDMPP\Customer\CustomerService;

defined('ABSPATH') || exit;

class CustomerEndpoint
{
    /**
     * Customer service instance
     *
     * @var CustomerService
     */
    private CustomerService $service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->service = CustomerService::getInstance();
    }

    /**
     * Register routes
     */
    public function register(): void
    {
        $namespace = RestApi::API_NAMESPACE;

        // =====================================================================
        // PUBLIC / USER ENDPOINTS
        // =====================================================================

        // Get current user's customer profile
        register_rest_route($namespace, '/customer/profile', [
            'methods' => 'GET',
            'callback' => [$this, 'getProfile'],
            'permission_callback' => [RestApi::class, 'checkAuthenticated'],
        ]);

        // Get current user's orders
        register_rest_route($namespace, '/customer/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'getOrders'],
            'permission_callback' => [RestApi::class, 'checkAuthenticated'],
            'args' => [
                'completed_only' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // Get current user's purchases
        register_rest_route($namespace, '/customer/purchases', [
            'methods' => 'GET',
            'callback' => [$this, 'getPurchases'],
            'permission_callback' => [RestApi::class, 'checkAuthenticated'],
        ]);

        // Get current user's total spent
        register_rest_route($namespace, '/customer/total-spent', [
            'methods' => 'GET',
            'callback' => [$this, 'getTotalSpent'],
            'permission_callback' => [RestApi::class, 'checkAuthenticated'],
        ]);

        // =====================================================================
        // ADMIN ENDPOINTS
        // =====================================================================

        // List all customers (paginated)
        register_rest_route($namespace, '/admin/customers', [
            'methods' => 'GET',
            'callback' => [$this, 'listCustomers'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
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
                ],
                'orderby' => [
                    'type' => 'string',
                    'default' => 'registered',
                    'enum' => ['registered', 'display_name', 'user_email', 'ID'],
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC',
                    'enum' => ['ASC', 'DESC'],
                ],
            ],
        ]);

        // Get single customer
        register_rest_route($namespace, '/admin/customers/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomer'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        // Get customer's orders
        register_rest_route($namespace, '/admin/customers/(?P<id>\d+)/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomerOrders'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        // Get customer's purchases
        register_rest_route($namespace, '/admin/customers/(?P<id>\d+)/purchases', [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomerPurchases'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        // Recalculate customer's total spent
        register_rest_route($namespace, '/admin/customers/(?P<id>\d+)/recalculate', [
            'methods' => 'POST',
            'callback' => [$this, 'recalculateSpent'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        // Process customer roles
        register_rest_route($namespace, '/admin/customers/(?P<id>\d+)/process-roles', [
            'methods' => 'POST',
            'callback' => [$this, 'processRoles'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        // Add customer role to user
        register_rest_route($namespace, '/admin/customers/(?P<id>\d+)/add-role', [
            'methods' => 'POST',
            'callback' => [$this, 'addCustomerRole'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        // Remove customer role from user
        register_rest_route($namespace, '/admin/customers/(?P<id>\d+)/remove-role', [
            'methods' => 'POST',
            'callback' => [$this, 'removeCustomerRole'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        // Get top customers
        register_rest_route($namespace, '/admin/customers/top', [
            'methods' => 'GET',
            'callback' => [$this, 'getTopCustomers'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);

        // Get recent customers
        register_rest_route($namespace, '/admin/customers/recent', [
            'methods' => 'GET',
            'callback' => [$this, 'getRecentCustomers'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ]);

        // Get customer statistics
        register_rest_route($namespace, '/admin/customers/statistics', [
            'methods' => 'GET',
            'callback' => [$this, 'getStatistics'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
        ]);

        // Sync customer roles (bulk operation)
        register_rest_route($namespace, '/admin/customers/sync-roles', [
            'methods' => 'POST',
            'callback' => [$this, 'syncRoles'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
        ]);

        // Recalculate all totals (bulk operation)
        register_rest_route($namespace, '/admin/customers/recalculate-all', [
            'methods' => 'POST',
            'callback' => [$this, 'recalculateAllTotals'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
        ]);

        // Search customers
        register_rest_route($namespace, '/admin/customers/search', [
            'methods' => 'GET',
            'callback' => [$this, 'searchCustomers'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'q' => [
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 2,
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                ],
            ],
        ]);

        // Get customer info from order
        register_rest_route($namespace, '/admin/customers/by-order/(?P<order_id>[a-zA-Z0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomerByOrder'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'order_id' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ]);
    }

    // =========================================================================
    // PUBLIC / USER HANDLERS
    // =========================================================================

    /**
     * Get current user's customer profile
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getProfile(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $profile = $this->service->getCustomerProfile($userId);

        if (!$profile) {
            return RestApi::error(__('Customer profile not found.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success($profile);
    }

    /**
     * Get current user's orders
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getOrders(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $completedOnly = $request->get_param('completed_only');

        $orders = $this->service->getCustomerOrders($userId, $completedOnly);

        return RestApi::success([
            'orders' => $orders,
            'total' => count($orders),
        ]);
    }

    /**
     * Get current user's purchases
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getPurchases(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $purchases = $this->service->getPurchasedItems($userId);

        return RestApi::success([
            'purchases' => $purchases,
            'total' => count($purchases),
        ]);
    }

    /**
     * Get current user's total spent
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getTotalSpent(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $total = $this->service->getTotalSpent($userId);

        $formatted = $total;
        if (function_exists('wpdmpp_price_format')) {
            $formatted = wpdmpp_price_format($total);
        }

        return RestApi::success([
            'total_spent' => $total,
            'total_spent_formatted' => $formatted,
        ]);
    }

    // =========================================================================
    // ADMIN HANDLERS
    // =========================================================================

    /**
     * List all customers
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function listCustomers(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
        $search = $request->get_param('search');
        $orderby = $request->get_param('orderby');
        $order = $request->get_param('order');

        $args = [
            'orderby' => $orderby,
            'order' => $order,
        ];

        if (!empty($search)) {
            $args['search'] = '*' . esc_attr($search) . '*';
            $args['search_columns'] = ['ID', 'user_login', 'user_email', 'user_nicename', 'display_name'];
        }

        $result = $this->service->getCustomersPaginated($page, $perPage, $args);

        $customers = array_map(function (Customer $customer) {
            return $customer->toApiResponse();
        }, $result['customers']);

        return RestApi::success([
            'customers' => $customers,
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
        ]);
    }

    /**
     * Get single customer
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getCustomer(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $profile = $this->service->getCustomerProfile($id);

        if (!$profile) {
            return RestApi::error(__('Customer not found.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success($profile);
    }

    /**
     * Get customer orders
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getCustomerOrders(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $orders = $this->service->getCustomerOrders($id);

        return RestApi::success([
            'orders' => $orders,
            'total' => count($orders),
        ]);
    }

    /**
     * Get customer purchases
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getCustomerPurchases(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $purchases = $this->service->getPurchasedItems($id);

        return RestApi::success([
            'purchases' => $purchases,
            'total' => count($purchases),
        ]);
    }

    /**
     * Recalculate customer's total spent
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function recalculateSpent(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $total = $this->service->refreshTotalSpent($id);

        $formatted = $total;
        if (function_exists('wpdmpp_price_format')) {
            $formatted = wpdmpp_price_format($total);
        }

        return RestApi::success([
            'total_spent' => $total,
            'total_spent_formatted' => $formatted,
        ], __('Total spent recalculated successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Process customer roles
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function processRoles(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = $this->service->processActiveRoles($id);

        if ($result) {
            return RestApi::success([], __('Customer roles processed successfully.', 'wpdm-premium-packages'));
        }

        return RestApi::error(__('Failed to process customer roles.', 'wpdm-premium-packages'));
    }

    /**
     * Add customer role to user
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function addCustomerRole(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = $this->service->addCustomer($id);

        if ($result) {
            return RestApi::success([], __('Customer role added successfully.', 'wpdm-premium-packages'));
        }

        return RestApi::error(__('User already has customer role or does not exist.', 'wpdm-premium-packages'));
    }

    /**
     * Remove customer role from user
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function removeCustomerRole(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $result = $this->service->removeCustomer($id);

        if ($result) {
            return RestApi::success([], __('Customer role removed successfully.', 'wpdm-premium-packages'));
        }

        return RestApi::error(__('User does not have customer role or does not exist.', 'wpdm-premium-packages'));
    }

    /**
     * Get top customers
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getTopCustomers(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = $request->get_param('limit');
        $customers = $this->service->getTopCustomers($limit);

        $data = array_map(function (Customer $customer) {
            return $customer->toApiResponse();
        }, $customers);

        return RestApi::success([
            'customers' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Get recent customers
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getRecentCustomers(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = $request->get_param('limit');
        $customers = $this->service->getRecentCustomers($limit);

        $data = array_map(function (Customer $customer) {
            return $customer->toApiResponse();
        }, $customers);

        return RestApi::success([
            'customers' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Get customer statistics
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getStatistics(\WP_REST_Request $request): \WP_REST_Response
    {
        $stats = $this->service->getStatistics();
        return RestApi::success($stats);
    }

    /**
     * Sync customer roles (bulk)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function syncRoles(\WP_REST_Request $request): \WP_REST_Response
    {
        $count = $this->service->syncCustomerRoles();

        return RestApi::success([
            'updated' => $count,
        ], sprintf(__('%d customer roles synced.', 'wpdm-premium-packages'), $count));
    }

    /**
     * Recalculate all totals (bulk)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function recalculateAllTotals(\WP_REST_Request $request): \WP_REST_Response
    {
        $count = $this->service->recalculateAllTotalSpent();

        return RestApi::success([
            'updated' => $count,
        ], sprintf(__('%d customer totals recalculated.', 'wpdm-premium-packages'), $count));
    }

    /**
     * Search customers
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function searchCustomers(\WP_REST_Request $request): \WP_REST_Response
    {
        $query = $request->get_param('q');
        $limit = $request->get_param('limit');

        $customers = $this->service->searchCustomers($query, ['number' => $limit]);

        $data = array_map(function (Customer $customer) {
            return $customer->toMinimalArray();
        }, $customers);

        return RestApi::success([
            'customers' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Get customer info by order
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getCustomerByOrder(\WP_REST_Request $request): \WP_REST_Response
    {
        $orderId = $request->get_param('order_id');
        $info = $this->service->getCustomerInfoFromOrder($orderId);

        if (empty($info['name']) && empty($info['email'])) {
            return RestApi::error(__('Customer not found for this order.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success($info);
    }
}
