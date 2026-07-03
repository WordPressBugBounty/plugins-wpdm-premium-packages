<?php
/**
 * License REST API Endpoint
 *
 * Handles license-related REST API operations.
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\License\License;
use WPDMPP\License\LicenseService;

defined('ABSPATH') || exit;

class LicenseEndpoint {

    /**
     * License service instance
     *
     * @var LicenseService
     */
    private LicenseService $licenseService;

    /**
     * Constructor
     */
    public function __construct() {
        $this->licenseService = LicenseService::getInstance();
    }

    /**
     * Register routes
     *
     * @return void
     */
    public function register(): void {
        $namespace = RestApi::API_NAMESPACE;

        // =====================================================================
        // PUBLIC ENDPOINTS (for license validation and domain management)
        // =====================================================================

        // POST /license/validate - Validate license for domain
        register_rest_route($namespace, '/license/validate', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'validateLicense'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'domain' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /license/{key} - Get license info (owner only)
        register_rest_route($namespace, '/license/(?P<key>[A-Z0-9\-]+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getLicense'],
            'permission_callback' => '__return_true',
            'args' => [
                'key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /license/{key}/domains - Get domains for license (owner only)
        register_rest_route($namespace, '/license/(?P<key>[A-Z0-9\-]+)/domains', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getLicenseDomains'],
            'permission_callback' => '__return_true',
            'args' => [
                'key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // POST /license/key - Get or create license key for order+product
        register_rest_route($namespace, '/license/key', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'getLicenseKey'],
            'permission_callback' => [$this, 'checkLoggedIn'],
            'args' => [
                'product_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'order_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // DELETE /license/{key}/domains - Remove domain from license (owner only)
        register_rest_route($namespace, '/license/(?P<key>[A-Z0-9\-]+)/domains', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'removeDomain'],
            'permission_callback' => '__return_true',
            'args' => [
                'key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'domain' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // =====================================================================
        // ADMIN ENDPOINTS
        // =====================================================================

        // GET /admin/licenses - List all licenses
        register_rest_route($namespace, '/admin/licenses', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetLicenses'],
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
                'status' => [
                    'type' => 'integer',
                    'default' => null,
                ],
                'product_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'order_id' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'expired_only' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'active_only' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'orderby' => [
                    'type' => 'string',
                    'default' => 'id',
                    'enum' => ['id', 'licenseno', 'status', 'pid', 'oid', 'activation_date', 'expire_date', 'domain_limit'],
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC',
                    'enum' => ['ASC', 'DESC'],
                ],
            ],
        ]);

        // POST /admin/licenses - Create license
        register_rest_route($namespace, '/admin/licenses', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminCreateLicense'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => $this->getLicenseWriteArgs(true),
        ]);

        // GET /admin/licenses/{id} - Get single license
        register_rest_route($namespace, '/admin/licenses/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetLicense'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // PUT /admin/licenses/{id} - Update license
        register_rest_route($namespace, '/admin/licenses/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'adminUpdateLicense'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => array_merge(
                ['id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ]],
                $this->getLicenseWriteArgs(false)
            ),
        ]);

        // DELETE /admin/licenses/{id} - Delete license
        register_rest_route($namespace, '/admin/licenses/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::DELETABLE,
            'callback' => [$this, 'adminDeleteLicense'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // POST /admin/licenses/{id}/activate - Activate license
        register_rest_route($namespace, '/admin/licenses/(?P<id>\d+)/activate', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminActivateLicense'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // POST /admin/licenses/{id}/deactivate - Deactivate license
        register_rest_route($namespace, '/admin/licenses/(?P<id>\d+)/deactivate', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminDeactivateLicense'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // POST /admin/licenses/{id}/clear-domains - Clear all domains
        register_rest_route($namespace, '/admin/licenses/(?P<id>\d+)/clear-domains', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminClearDomains'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // POST /admin/licenses/bulk-delete - Bulk delete licenses
        register_rest_route($namespace, '/admin/licenses/bulk-delete', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminBulkDeleteLicenses'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'ids' => [
                    'required' => true,
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
        ]);

        // GET /admin/licenses/statistics - Get license statistics
        register_rest_route($namespace, '/admin/licenses/statistics', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetStatistics'],
            'permission_callback' => [$this, 'checkAdminCapability'],
        ]);

        // POST /admin/licenses/generate-key - Generate unique license key
        register_rest_route($namespace, '/admin/licenses/generate-key', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'adminGenerateKey'],
            'permission_callback' => [$this, 'checkAdminCapability'],
        ]);

        // GET /admin/licenses/expired - Get expired licenses
        register_rest_route($namespace, '/admin/licenses/expired', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetExpiredLicenses'],
            'permission_callback' => [$this, 'checkAdminCapability'],
        ]);

        // GET /admin/licenses/expiring-soon - Get licenses expiring soon
        register_rest_route($namespace, '/admin/licenses/expiring-soon', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetExpiringSoon'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'days' => [
                    'type' => 'integer',
                    'default' => 30,
                    'minimum' => 1,
                    'maximum' => 365,
                ],
            ],
        ]);

        // GET /admin/licenses/by-product/{id} - Get licenses by product
        register_rest_route($namespace, '/admin/licenses/by-product/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetLicensesByProduct'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
        ]);

        // GET /admin/licenses/by-order/{id} - Get licenses by order
        register_rest_route($namespace, '/admin/licenses/by-order/(?P<id>[A-Z0-9]+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'adminGetLicensesByOrder'],
            'permission_callback' => [$this, 'checkAdminCapability'],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    // =========================================================================
    // PUBLIC ENDPOINTS
    // =========================================================================

    /**
     * Get or create license key for an order+product combination
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getLicenseKey(\WP_REST_Request $request): \WP_REST_Response {
        $productId = (int) $request->get_param('product_id');
        $orderId = $request->get_param('order_id');

        // Verify the current user owns this order
        global $wpdb;
        $orderUserId = $wpdb->get_var($wpdb->prepare(
            "SELECT uid FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s",
            $orderId
        ));

        if (!$orderUserId || (int) $orderUserId !== get_current_user_id()) {
            return RestApi::error(__('You do not have permission to access this order.', 'wpdm-premium-packages'), 403);
        }

        // Get domain limit from order item's license config
        $domainLimit = 1;
        $orderItem = $wpdb->get_row($wpdb->prepare(
            "SELECT license FROM {$wpdb->prefix}ahm_order_items WHERE oid = %s AND pid = %d",
            $orderId, $productId
        ));

        if ($orderItem && !empty($orderItem->license)) {
            $licenseTypes = get_option('_wpdmpp_license', []);
            $licenseId = $orderItem->license;
            if (is_array($licenseTypes) && isset($licenseTypes[$licenseId]['domain'])) {
                $domainLimit = (int) $licenseTypes[$licenseId]['domain'];
            }
        }

        $result = $this->licenseService->getOrCreateLicense($orderId, $productId, $domainLimit);

        if (!$result['success'] || !$result['license']) {
            return RestApi::error(
                $result['message'] ?? __('Failed to retrieve license key.', 'wpdm-premium-packages'),
                400
            );
        }

        $license = $result['license'];

        return RestApi::success([
            'key' => $license->getLicenseNo(),
            'domains' => $license->getDomains(),
        ]);
    }

    /**
     * Validate license for domain
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function validateLicense(\WP_REST_Request $request): \WP_REST_Response {
        $licenseKey = $request->get_param('license_key');
        $domain = $request->get_param('domain');

        $result = $this->licenseService->validateLicense($licenseKey, $domain, true);

        if ($result['status'] !== License::VALID) {
            $statusCode = $result['status'] === License::EXPIRED ? 410 : 400;
            return RestApi::error($result['message'] ?? __('License validation failed.', 'wpdm-premium-packages'), $statusCode, [
                'status' => $result['status'],
                'error' => $result['error'] ?? null,
            ]);
        }

        $response = [
            'status' => $result['status'],
            'valid' => true,
            'domain_registered' => $result['domain_registered'] ?? false,
        ];

        if (!empty($result['download_url'])) {
            $response['download_url'] = $result['download_url'];
        }

        if (!empty($result['license'])) {
            $response['license'] = [
                'license_no' => $result['license']->getLicenseNo(),
                'domains' => $result['license']->getDomains(),
                'domain_limit' => $result['license']->getDomainLimit(),
                'remaining_slots' => $result['license']->getRemainingDomainSlots(),
                'expire_date' => $result['license']->getExpireDate(),
                'is_expired' => $result['license']->isExpired(),
            ];
        }

        return RestApi::success($response, $result['message'] ?? __('License validated successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Get license info
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getLicense(\WP_REST_Request $request): \WP_REST_Response {
        $licenseKey = $request->get_param('key');

        $license = $this->licenseService->findByLicenseNo($licenseKey);

        if (!$license) {
            return RestApi::error(__('License not found.', 'wpdm-premium-packages'), 404);
        }

        // Verify ownership
        if (!$this->verifyLicenseOwnership($license)) {
            return RestApi::error(__('You do not have permission to access this license.', 'wpdm-premium-packages'), 403);
        }

        return RestApi::success([
            'license' => $this->formatLicenseResponse($license),
        ]);
    }

    /**
     * Get domains for license
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getLicenseDomains(\WP_REST_Request $request): \WP_REST_Response {
        $licenseKey = $request->get_param('key');

        $license = $this->licenseService->findByLicenseNo($licenseKey);

        if (!$license) {
            return RestApi::error(__('License not found.', 'wpdm-premium-packages'), 404);
        }

        // Verify ownership
        if (!$this->verifyLicenseOwnership($license)) {
            return RestApi::error(__('You do not have permission to access this license.', 'wpdm-premium-packages'), 403);
        }

        return RestApi::success([
            'domains' => $license->getDomains(),
            'domain_count' => $license->getDomainCount(),
            'domain_limit' => $license->getDomainLimit(),
            'remaining_slots' => $license->getRemainingDomainSlots(),
        ]);
    }

    /**
     * Remove domain from license
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function removeDomain(\WP_REST_Request $request): \WP_REST_Response {
        $licenseKey = $request->get_param('key');
        $domain = $request->get_param('domain');

        $license = $this->licenseService->findByLicenseNo($licenseKey);

        if (!$license) {
            return RestApi::error(__('License not found.', 'wpdm-premium-packages'), 404);
        }

        // Verify ownership
        if (!$this->verifyLicenseOwnership($license)) {
            return RestApi::error(__('You do not have permission to modify this license.', 'wpdm-premium-packages'), 403);
        }

        $result = $this->licenseService->removeDomain($license->getId(), $domain);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([
            'domains' => $result['domains'],
            'domain_count' => count($result['domains']),
        ], $result['message']);
    }

    // =========================================================================
    // ADMIN ENDPOINTS
    // =========================================================================

    /**
     * Get all licenses (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetLicenses(\WP_REST_Request $request): \WP_REST_Response {
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');

        $args = [
            'search' => $request->get_param('search'),
            'status' => $request->get_param('status'),
            'product_id' => $request->get_param('product_id'),
            'order_id' => $request->get_param('order_id'),
            'expired_only' => $request->get_param('expired_only'),
            'active_only' => $request->get_param('active_only'),
            'orderby' => $request->get_param('orderby'),
            'order' => $request->get_param('order'),
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $result = $this->licenseService->getLicenses($args);

        $data = [];
        foreach ($result['licenses'] as $license) {
            $data[] = $this->formatLicenseResponse($license);
        }

        return RestApi::success([
            'licenses' => $data,
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($result['total'] / $perPage),
        ]);
    }

    /**
     * Create license (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminCreateLicense(\WP_REST_Request $request): \WP_REST_Response {
        $data = $this->extractLicenseData($request);

        $result = $this->licenseService->createLicense($data);

        if (!$result['success']) {
            return RestApi::error(
                $result['errors']['general'] ?? __('Failed to create license.', 'wpdm-premium-packages'),
                400,
                $result['errors']
            );
        }

        return RestApi::success([
            'license' => $this->formatLicenseResponse($result['license']),
        ], __('License created successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Get single license (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetLicense(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $license = $this->licenseService->findById($id);

        if (!$license) {
            return RestApi::error(__('License not found.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success([
            'license' => $this->formatLicenseResponse($license),
        ]);
    }

    /**
     * Update license (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminUpdateLicense(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $data = $this->extractLicenseData($request);

        $result = $this->licenseService->updateLicense($id, $data);

        if (!$result['success']) {
            return RestApi::error(
                $result['errors']['general'] ?? __('Failed to update license.', 'wpdm-premium-packages'),
                400,
                $result['errors']
            );
        }

        return RestApi::success([
            'license' => $this->formatLicenseResponse($result['license']),
        ], __('License updated successfully.', 'wpdm-premium-packages'));
    }

    /**
     * Delete license (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminDeleteLicense(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $result = $this->licenseService->deleteLicense($id);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([], $result['message']);
    }

    /**
     * Activate license (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminActivateLicense(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $result = $this->licenseService->activateLicense($id);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([
            'license' => $this->formatLicenseResponse($result['license']),
        ], $result['message']);
    }

    /**
     * Deactivate license (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminDeactivateLicense(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $result = $this->licenseService->deactivateLicense($id);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([
            'license' => $this->formatLicenseResponse($result['license']),
        ], $result['message']);
    }

    /**
     * Clear all domains for license (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminClearDomains(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');

        $result = $this->licenseService->clearDomains($id);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([
            'license' => $this->formatLicenseResponse($result['license']),
        ], $result['message']);
    }

    /**
     * Bulk delete licenses (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminBulkDeleteLicenses(\WP_REST_Request $request): \WP_REST_Response {
        $ids = $request->get_param('ids');

        $result = $this->licenseService->bulkDeleteLicenses($ids);

        if (!$result['success']) {
            return RestApi::error($result['message'], 400);
        }

        return RestApi::success([
            'deleted' => $result['deleted'],
        ], $result['message']);
    }

    /**
     * Get license statistics (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetStatistics(\WP_REST_Request $request): \WP_REST_Response {
        $stats = $this->licenseService->getStatistics();

        return RestApi::success([
            'statistics' => $stats,
        ]);
    }

    /**
     * Generate unique license key (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGenerateKey(\WP_REST_Request $request): \WP_REST_Response {
        $key = $this->licenseService->generateLicenseKey();

        return RestApi::success([
            'license_key' => $key,
        ]);
    }

    /**
     * Get expired licenses (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetExpiredLicenses(\WP_REST_Request $request): \WP_REST_Response {
        $licenses = $this->licenseService->getExpiredLicenses();

        $data = [];
        foreach ($licenses as $license) {
            $data[] = $this->formatLicenseResponse($license);
        }

        return RestApi::success([
            'licenses' => $data,
            'count' => count($data),
        ]);
    }

    /**
     * Get licenses expiring soon (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetExpiringSoon(\WP_REST_Request $request): \WP_REST_Response {
        $days = $request->get_param('days');

        $licenses = $this->licenseService->getExpiringSoon($days);

        $data = [];
        foreach ($licenses as $license) {
            $data[] = $this->formatLicenseResponse($license);
        }

        return RestApi::success([
            'licenses' => $data,
            'count' => count($data),
            'days' => $days,
        ]);
    }

    /**
     * Get licenses by product (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetLicensesByProduct(\WP_REST_Request $request): \WP_REST_Response {
        $productId = (int) $request->get_param('id');

        $licenses = $this->licenseService->getLicensesForProduct($productId);

        $data = [];
        foreach ($licenses as $license) {
            $data[] = $this->formatLicenseResponse($license);
        }

        return RestApi::success([
            'licenses' => $data,
            'count' => count($data),
            'product_id' => $productId,
        ]);
    }

    /**
     * Get licenses by order (admin)
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function adminGetLicensesByOrder(\WP_REST_Request $request): \WP_REST_Response {
        $orderId = $request->get_param('id');

        $licenses = $this->licenseService->getLicensesForOrder($orderId);

        $data = [];
        foreach ($licenses as $license) {
            $data[] = $this->formatLicenseResponse($license);
        }

        return RestApi::success([
            'licenses' => $data,
            'count' => count($data),
            'order_id' => $orderId,
        ]);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public function checkLoggedIn(): bool {
        return is_user_logged_in();
    }

    /**
     * Check if user has admin capability
     *
     * @return bool
     */
    public function checkAdminCapability(): bool {
        return current_user_can(WPDMPP_ADMIN_CAP);
    }

    /**
     * Verify license ownership
     *
     * @param License $license
     * @return bool
     */
    private function verifyLicenseOwnership(License $license): bool {
        // Admin can access any license
        if (current_user_can(WPDMPP_ADMIN_CAP)) {
            return true;
        }

        // Get order for this license
        $orderId = $license->getOrderId();
        if (empty($orderId)) {
            return false;
        }

        // Check if current user owns the order
        if (!is_user_logged_in()) {
            return false;
        }

        global $wpdb;
        $orderUserId = $wpdb->get_var($wpdb->prepare(
            "SELECT uid FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s",
            $orderId
        ));

        return (int) $orderUserId === get_current_user_id();
    }

    /**
     * Get license write arguments for route registration
     *
     * @param bool $required Whether fields are required
     * @return array
     */
    private function getLicenseWriteArgs(bool $required): array {
        return [
            'license_no' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'order_id' => [
                'required' => $required,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'product_id' => [
                'required' => $required,
                'type' => 'integer',
                'minimum' => 1,
            ],
            'status' => [
                'type' => 'integer',
                'default' => License::STATUS_ACTIVE,
                'enum' => [License::STATUS_ACTIVE, License::STATUS_INACTIVE],
            ],
            'domain_limit' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 0,
            ],
            'domains' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'default' => [],
            ],
            'activation_date' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'expire_date' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'expire_period' => [
                'type' => 'integer',
                'default' => 0,
                'minimum' => 0,
            ],
        ];
    }

    /**
     * Extract license data from request
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    private function extractLicenseData(\WP_REST_Request $request): array {
        $data = [];
        $fields = [
            'license_no', 'order_id', 'product_id', 'status',
            'domain_limit', 'domains', 'activation_date',
            'expire_date', 'expire_period',
        ];

        foreach ($fields as $field) {
            if ($request->has_param($field)) {
                $data[$field] = $request->get_param($field);
            }
        }

        return $data;
    }

    /**
     * Format license for API response
     *
     * @param License $license
     * @return array
     */
    private function formatLicenseResponse(License $license): array {
        $data = $license->toArray();

        // Add formatted dates
        if ($license->getActivationDate() > 0) {
            $data['activation_date_formatted'] = date_i18n(get_option('date_format'), $license->getActivationDate());
        } else {
            $data['activation_date_formatted'] = __('Not activated', 'wpdm-premium-packages');
        }

        if ($license->getExpireDate() > 0) {
            $data['expire_date_formatted'] = date_i18n(get_option('date_format'), $license->getExpireDate());

            // Add days until expiration
            $daysUntilExpire = ceil(($license->getExpireDate() - time()) / 86400);
            if ($daysUntilExpire < 0) {
                $data['expire_status'] = sprintf(__('Expired %d days ago', 'wpdm-premium-packages'), abs($daysUntilExpire));
            } elseif ($daysUntilExpire === 0) {
                $data['expire_status'] = __('Expires today', 'wpdm-premium-packages');
            } elseif ($daysUntilExpire <= 30) {
                $data['expire_status'] = sprintf(__('Expires in %d days', 'wpdm-premium-packages'), $daysUntilExpire);
            } else {
                $data['expire_status'] = null;
            }
        } else {
            $data['expire_date_formatted'] = __('Never', 'wpdm-premium-packages');
            $data['expire_status'] = null;
        }

        // Add status label
        $data['status_label'] = $license->isActive()
            ? __('Active', 'wpdm-premium-packages')
            : __('Inactive', 'wpdm-premium-packages');

        // Add domain info
        $data['domain_limit_label'] = $license->hasUnlimitedDomains()
            ? __('Unlimited', 'wpdm-premium-packages')
            : $license->getDomainLimit();

        // Add product info if available
        if ($license->getProductId() > 0) {
            $product = get_post($license->getProductId());
            if ($product) {
                $data['product_name'] = $product->post_title;
            }
        }

        return $data;
    }
}
