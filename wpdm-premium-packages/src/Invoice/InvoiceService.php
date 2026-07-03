<?php
/**
 * Invoice Service
 *
 * Main orchestration class for invoice operations.
 * Provides a singleton interface for generating and managing invoices.
 *
 * @package WPDMPP\Invoice
 * @since 7.0.0
 */

namespace WPDMPP\Invoice;

use WPDMPP\Invoice\Repository\DatabaseRepository;

defined('ABSPATH') || exit;

class InvoiceService
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Invoice repository
     *
     * @var InvoiceRepositoryInterface
     */
    private InvoiceRepositoryInterface $repository;

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->repository = new DatabaseRepository();
    }

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set repository (for testing or custom implementations)
     *
     * @param InvoiceRepositoryInterface $repository Repository instance
     * @return self
     */
    public function setRepository(InvoiceRepositoryInterface $repository): self
    {
        $this->repository = $repository;
        return $this;
    }

    /**
     * Get repository
     *
     * @return InvoiceRepositoryInterface
     */
    public function getRepository(): InvoiceRepositoryInterface
    {
        return $this->repository;
    }

    // =========================================================================
    // INVOICE RETRIEVAL
    // =========================================================================

    /**
     * Get invoice by order ID
     *
     * @param string $orderId Order ID
     * @return Invoice|null
     */
    public function getInvoice(string $orderId): ?Invoice
    {
        return $this->repository->findByOrderId($orderId);
    }

    /**
     * Get renewal invoice
     *
     * @param string $orderId     Order ID
     * @param int    $renewalDate Renewal timestamp
     * @return Invoice|null
     */
    public function getRenewalInvoice(string $orderId, int $renewalDate): ?Invoice
    {
        return $this->repository->findRenewalInvoice($orderId, $renewalDate);
    }

    /**
     * Get all invoices for customer
     *
     * @param int   $customerId Customer/User ID
     * @param array $args       Query arguments
     * @return Invoice[]
     */
    public function getCustomerInvoices(int $customerId, array $args = []): array
    {
        return $this->repository->findByCustomerId($customerId, $args);
    }

    /**
     * Get paginated invoices for customer
     *
     * @param int   $customerId Customer/User ID
     * @param int   $page       Page number
     * @param int   $perPage    Items per page
     * @param array $args       Additional arguments
     * @return array
     */
    public function getCustomerInvoicesPaginated(int $customerId, int $page = 1, int $perPage = 20, array $args = []): array
    {
        $args['limit'] = $perPage;
        $args['offset'] = ($page - 1) * $perPage;

        $invoices = $this->repository->findByCustomerId($customerId, $args);
        $total = $this->repository->countByCustomerId($customerId);
        $pages = ceil($total / $perPage);

        return [
            'invoices' => $invoices,
            'total' => $total,
            'pages' => (int) $pages,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Count invoices for customer
     *
     * @param int $customerId Customer/User ID
     * @return int
     */
    public function countCustomerInvoices(int $customerId): int
    {
        return $this->repository->countByCustomerId($customerId);
    }

    // =========================================================================
    // RENEWAL INVOICES
    // =========================================================================

    /**
     * Get renewal invoices for an order
     *
     * @param string $orderId Order ID
     * @return array
     */
    public function getOrderRenewals(string $orderId): array
    {
        return $this->repository->findRenewalsByOrderId($orderId);
    }

    /**
     * Get all renewals for a customer
     *
     * @param int $customerId Customer/User ID
     * @return array
     */
    public function getCustomerRenewals(int $customerId): array
    {
        return $this->repository->findRenewalsByCustomerId($customerId);
    }

    /**
     * Count renewal invoices for order
     *
     * @param string $orderId Order ID
     * @return int
     */
    public function countOrderRenewals(string $orderId): int
    {
        return $this->repository->countRenewalsByOrderId($orderId);
    }

    // =========================================================================
    // INVOICE DATA
    // =========================================================================

    /**
     * Get invoice data for rendering
     *
     * @param string $orderId     Order ID
     * @param int    $renewalDate Renewal date (0 for main invoice)
     * @return array|null
     */
    public function getInvoiceData(string $orderId, int $renewalDate = 0): ?array
    {
        $invoice = $renewalDate > 0
            ? $this->getRenewalInvoice($orderId, $renewalDate)
            : $this->getInvoice($orderId);

        if (!$invoice) {
            return null;
        }

        return $invoice->toArray();
    }

    /**
     * Get order data
     *
     * @param string $orderId Order ID
     * @return object|null
     */
    public function getOrderData(string $orderId): ?object
    {
        return $this->repository->getOrderData($orderId);
    }

    /**
     * Get order items
     *
     * @param string $orderId Order ID
     * @return array
     */
    public function getOrderItems(string $orderId): array
    {
        return $this->repository->getOrderItems($orderId);
    }

    /**
     * Get billing info
     *
     * @param int    $customerId Customer/User ID
     * @param string $orderId    Order ID (optional)
     * @return array
     */
    public function getBillingInfo(int $customerId, string $orderId = ''): array
    {
        return $this->repository->getBillingInfo($customerId, $orderId);
    }

    // =========================================================================
    // SETTINGS
    // =========================================================================

    /**
     * Get invoice settings
     *
     * @return array
     */
    public function getSettings(): array
    {
        return $this->repository->getSettings();
    }

    /**
     * Save invoice settings
     *
     * @param array $settings Settings array
     * @return bool
     */
    public function saveSettings(array $settings): bool
    {
        $result = $this->repository->saveSettings($settings);

        if ($result) {
            do_action('wpdmpp_invoice_settings_saved', $settings);
        }

        return $result;
    }

    /**
     * Get specific setting
     *
     * @param string $key     Setting key
     * @param mixed  $default Default value
     * @return mixed
     */
    public function getSetting(string $key, $default = '')
    {
        $settings = $this->getSettings();
        return $settings[$key] ?? $default;
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get invoice statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    // =========================================================================
    // ACCESS CONTROL
    // =========================================================================

    /**
     * Check if user can access invoice
     *
     * @param string $orderId Order ID
     * @param int    $userId  User ID (0 for current user)
     * @return bool
     */
    public function userCanAccessInvoice(string $orderId, int $userId = 0): bool
    {
        if ($userId <= 0) {
            $userId = get_current_user_id();
        }

        // Admins can access all invoices
        if (current_user_can(WPDMPP_ADMIN_CAP)) {
            return true;
        }

        // Get order data
        $order = $this->repository->getOrderData($orderId);
        if (!$order) {
            return false;
        }

        // Check if user owns the order
        return (int) $order->uid === $userId;
    }

    /**
     * Check if guest can access invoice via session
     *
     * @param string $orderId Order ID
     * @return bool
     */
    public function guestCanAccessInvoice(string $orderId): bool
    {
        if (is_user_logged_in()) {
            return false;
        }

        // Check session for guest order access
        if (class_exists('\\WPDM\\__\\Session')) {
            $guestOrder = \WPDM\__\Session::get('guest_order');
            return $guestOrder === $orderId;
        }

        return false;
    }

    // =========================================================================
    // URL GENERATION
    // =========================================================================

    /**
     * Get invoice URL
     *
     * @param string $orderId     Order ID
     * @param int    $renewalDate Renewal date (0 for main invoice)
     * @return string
     */
    public function getInvoiceUrl(string $orderId, int $renewalDate = 0): string
    {
        $url = add_query_arg([
            'id' => $orderId,
            'wpdminvoice' => '1',
        ], home_url());

        if ($renewalDate > 0) {
            $url = add_query_arg('renew', $renewalDate, $url);
        }

        return $url;
    }

    /**
     * Get invoice PDF URL
     *
     * @param string $orderId     Order ID
     * @param int    $renewalDate Renewal date (0 for main invoice)
     * @return string
     */
    public function getInvoicePdfUrl(string $orderId, int $renewalDate = 0): string
    {
        $url = $this->getInvoiceUrl($orderId, $renewalDate);
        return add_query_arg('wpdminvoice', 'pdf', $url);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Check if billing info is complete
     *
     * @param array $billingInfo Billing info array
     * @return bool
     */
    public function isBillingInfoComplete(array $billingInfo): bool
    {
        $required = ['first_name', 'last_name', 'address_1', 'city'];

        foreach ($required as $field) {
            if (empty($billingInfo[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing billing fields
     *
     * @param array $billingInfo Billing info array
     * @return array
     */
    public function getMissingBillingFields(array $billingInfo): array
    {
        $required = [
            'first_name' => __('First Name', 'wpdm-premium-packages'),
            'last_name' => __('Last Name', 'wpdm-premium-packages'),
            'address_1' => __('Address', 'wpdm-premium-packages'),
            'city' => __('City', 'wpdm-premium-packages'),
        ];

        $missing = [];
        foreach ($required as $field => $label) {
            if (empty($billingInfo[$field])) {
                $missing[$field] = $label;
            }
        }

        return $missing;
    }

    /**
     * Format price with currency
     *
     * @param float $amount Amount to format
     * @return string
     */
    public function formatPrice(float $amount): string
    {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($amount);
        }

        return '$' . number_format($amount, 2);
    }
}
