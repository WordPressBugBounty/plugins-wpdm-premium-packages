<?php
/**
 * Invoice REST API Endpoint
 *
 * Handles REST API requests for invoice retrieval and management.
 *
 * @package WPDMPP\Api\Endpoints
 * @since 7.0.0
 */

namespace WPDMPP\Api\Endpoints;

use WPDMPP\Api\RestApi;
use WPDMPP\Invoice\Invoice;
use WPDMPP\Invoice\InvoiceService;

defined('ABSPATH') || exit;

class InvoiceEndpoint
{
    /**
     * Invoice service instance
     *
     * @var InvoiceService
     */
    private InvoiceService $service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->service = InvoiceService::getInstance();
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

        // Get invoice for order (requires access)
        register_rest_route($namespace, '/invoice/(?P<order_id>[a-zA-Z0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getInvoice'],
            'permission_callback' => [$this, 'checkInvoiceAccess'],
            'args' => [
                'order_id' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'renew' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
            ],
        ]);

        // Get current user's invoices
        register_rest_route($namespace, '/invoices', [
            'methods' => 'GET',
            'callback' => [$this, 'getMyInvoices'],
            'permission_callback' => [RestApi::class, 'checkAuthenticated'],
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                ],
                'status' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);

        // Get renewal invoices for an order
        register_rest_route($namespace, '/invoice/(?P<order_id>[a-zA-Z0-9]+)/renewals', [
            'methods' => 'GET',
            'callback' => [$this, 'getOrderRenewals'],
            'permission_callback' => [$this, 'checkInvoiceAccess'],
            'args' => [
                'order_id' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ]);

        // Get invoice URL
        register_rest_route($namespace, '/invoice/(?P<order_id>[a-zA-Z0-9]+)/url', [
            'methods' => 'GET',
            'callback' => [$this, 'getInvoiceUrl'],
            'permission_callback' => [$this, 'checkInvoiceAccess'],
            'args' => [
                'order_id' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'renew' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'pdf' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // =====================================================================
        // ADMIN ENDPOINTS
        // =====================================================================

        // Get invoice by order ID (admin)
        register_rest_route($namespace, '/admin/invoices/(?P<order_id>[a-zA-Z0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getInvoiceAdmin'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'order_id' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'renew' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
            ],
        ]);

        // Get invoices for a customer (admin)
        register_rest_route($namespace, '/admin/invoices/customer/(?P<customer_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomerInvoices'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'customer_id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                ],
            ],
        ]);

        // Get invoice settings
        register_rest_route($namespace, '/admin/invoices/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettings'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
        ]);

        // Update invoice settings
        register_rest_route($namespace, '/admin/invoices/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'updateSettings'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'invoice_logo' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'invoice_company_address' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'invoice_thanks' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'invoice_terms_acceptance' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'signature' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);

        // Get invoice statistics
        register_rest_route($namespace, '/admin/invoices/statistics', [
            'methods' => 'GET',
            'callback' => [$this, 'getStatistics'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
        ]);

        // Get all renewals for a customer
        register_rest_route($namespace, '/admin/invoices/renewals/customer/(?P<customer_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getCustomerRenewals'],
            'permission_callback' => [RestApi::class, 'checkAdminCap'],
            'args' => [
                'customer_id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);
    }

    // =========================================================================
    // PERMISSION CALLBACKS
    // =========================================================================

    /**
     * Check if user can access invoice
     *
     * @param \WP_REST_Request $request Request object
     * @return bool
     */
    public function checkInvoiceAccess(\WP_REST_Request $request): bool
    {
        $orderId = $request->get_param('order_id');

        // Admin can access all
        if (current_user_can(WPDMPP_ADMIN_CAP)) {
            return true;
        }

        // Check user access
        if (is_user_logged_in()) {
            return $this->service->userCanAccessInvoice($orderId);
        }

        // Check guest access via session
        return $this->service->guestCanAccessInvoice($orderId);
    }

    // =========================================================================
    // PUBLIC / USER HANDLERS
    // =========================================================================

    /**
     * Get invoice for order
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getInvoice(\WP_REST_Request $request): \WP_REST_Response
    {
        $orderId = $request->get_param('order_id');
        $renewalDate = (int) $request->get_param('renew');

        $invoice = $renewalDate > 0
            ? $this->service->getRenewalInvoice($orderId, $renewalDate)
            : $this->service->getInvoice($orderId);

        if (!$invoice) {
            return RestApi::error(__('Invoice not found.', 'wpdm-premium-packages'), 404);
        }

        return RestApi::success($invoice->toApiResponse());
    }

    /**
     * Get current user's invoices
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getMyInvoices(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $page = (int) $request->get_param('page');
        $perPage = (int) $request->get_param('per_page');
        $status = $request->get_param('status');

        $args = [];
        if (!empty($status)) {
            $args['status'] = $status;
        }

        $result = $this->service->getCustomerInvoicesPaginated($userId, $page, $perPage, $args);

        $invoices = array_map(function (Invoice $invoice) {
            return $invoice->toApiResponse();
        }, $result['invoices']);

        return RestApi::success([
            'invoices' => $invoices,
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
        ]);
    }

    /**
     * Get renewal invoices for an order
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getOrderRenewals(\WP_REST_Request $request): \WP_REST_Response
    {
        $orderId = $request->get_param('order_id');
        $renewals = $this->service->getOrderRenewals($orderId);

        // Add URLs for each renewal
        $renewalsWithUrls = array_map(function ($renewal) use ($orderId) {
            $renewal->invoice_url = $this->service->getInvoiceUrl($orderId, $renewal->date);
            $renewal->pdf_url = $this->service->getInvoicePdfUrl($orderId, $renewal->date);
            $renewal->date_formatted = wp_date(get_option('date_format'), $renewal->date);
            return $renewal;
        }, $renewals);

        return RestApi::success([
            'renewals' => $renewalsWithUrls,
            'total' => count($renewals),
        ]);
    }

    /**
     * Get invoice URL
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getInvoiceUrl(\WP_REST_Request $request): \WP_REST_Response
    {
        $orderId = $request->get_param('order_id');
        $renewalDate = (int) $request->get_param('renew');
        $pdf = (bool) $request->get_param('pdf');

        $url = $pdf
            ? $this->service->getInvoicePdfUrl($orderId, $renewalDate)
            : $this->service->getInvoiceUrl($orderId, $renewalDate);

        return RestApi::success([
            'url' => $url,
            'is_pdf' => $pdf,
        ]);
    }

    // =========================================================================
    // ADMIN HANDLERS
    // =========================================================================

    /**
     * Get invoice (admin)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getInvoiceAdmin(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->getInvoice($request);
    }

    /**
     * Get customer invoices (admin)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getCustomerInvoices(\WP_REST_Request $request): \WP_REST_Response
    {
        $customerId = (int) $request->get_param('customer_id');
        $page = (int) $request->get_param('page');
        $perPage = (int) $request->get_param('per_page');

        $result = $this->service->getCustomerInvoicesPaginated($customerId, $page, $perPage);

        $invoices = array_map(function (Invoice $invoice) {
            return $invoice->toApiResponse();
        }, $result['invoices']);

        return RestApi::success([
            'invoices' => $invoices,
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
        ]);
    }

    /**
     * Get invoice settings
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = $this->service->getSettings();
        return RestApi::success($settings);
    }

    /**
     * Update invoice settings
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = [
            'invoice_logo' => $request->get_param('invoice_logo'),
            'invoice_company_address' => $request->get_param('invoice_company_address'),
            'invoice_thanks' => $request->get_param('invoice_thanks'),
            'invoice_terms_acceptance' => $request->get_param('invoice_terms_acceptance'),
            'signature' => $request->get_param('signature'),
        ];

        $result = $this->service->saveSettings($settings);

        if ($result) {
            return RestApi::success(
                $this->service->getSettings(),
                __('Invoice settings updated successfully.', 'wpdm-premium-packages')
            );
        }

        return RestApi::error(__('Failed to update invoice settings.', 'wpdm-premium-packages'));
    }

    /**
     * Get invoice statistics
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
     * Get all renewals for a customer
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function getCustomerRenewals(\WP_REST_Request $request): \WP_REST_Response
    {
        $customerId = (int) $request->get_param('customer_id');
        $renewals = $this->service->getCustomerRenewals($customerId);

        // Format renewals with invoice URLs
        $formattedRenewals = array_map(function ($renewal) {
            $renewal->invoice_url = $this->service->getInvoiceUrl($renewal->order_id, $renewal->date);
            $renewal->pdf_url = $this->service->getInvoicePdfUrl($renewal->order_id, $renewal->date);
            $renewal->date_formatted = wp_date(get_option('date_format'), $renewal->date);
            return $renewal;
        }, $renewals);

        return RestApi::success([
            'renewals' => $formattedRenewals,
            'total' => count($renewals),
        ]);
    }
}
