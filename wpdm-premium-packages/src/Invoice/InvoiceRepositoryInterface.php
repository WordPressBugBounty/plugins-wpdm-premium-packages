<?php
/**
 * Invoice Repository Interface
 *
 * Contract for invoice data retrieval.
 * Note: Invoices are generated from orders, not stored separately.
 *
 * @package WPDMPP\Invoice
 * @since 7.0.0
 */

namespace WPDMPP\Invoice;

defined('ABSPATH') || exit;

interface InvoiceRepositoryInterface
{
    /**
     * Get invoice by order ID
     *
     * @param string $orderId Order ID
     * @return Invoice|null
     */
    public function findByOrderId(string $orderId): ?Invoice;

    /**
     * Get renewal invoice
     *
     * @param string $orderId     Order ID
     * @param int    $renewalDate Renewal timestamp
     * @return Invoice|null
     */
    public function findRenewalInvoice(string $orderId, int $renewalDate): ?Invoice;

    /**
     * Get all invoices for a customer
     *
     * @param int   $customerId Customer/User ID
     * @param array $args       Query arguments
     * @return Invoice[]
     */
    public function findByCustomerId(int $customerId, array $args = []): array;

    /**
     * Get renewal invoices for an order
     *
     * @param string $orderId Order ID
     * @return array Array of renewal records
     */
    public function findRenewalsByOrderId(string $orderId): array;

    /**
     * Get all renewals for a customer
     *
     * @param int $customerId Customer/User ID
     * @return array
     */
    public function findRenewalsByCustomerId(int $customerId): array;

    /**
     * Get invoice settings
     *
     * @return array
     */
    public function getSettings(): array;

    /**
     * Save invoice settings
     *
     * @param array $settings Settings array
     * @return bool
     */
    public function saveSettings(array $settings): bool;

    /**
     * Get order data for invoice generation
     *
     * @param string $orderId Order ID
     * @return object|null
     */
    public function getOrderData(string $orderId): ?object;

    /**
     * Get order items for invoice generation
     *
     * @param string $orderId Order ID
     * @return array
     */
    public function getOrderItems(string $orderId): array;

    /**
     * Get billing info for customer
     *
     * @param int    $customerId Customer/User ID
     * @param string $orderId    Order ID (optional, for order-specific billing)
     * @return array
     */
    public function getBillingInfo(int $customerId, string $orderId = ''): array;

    /**
     * Count invoices for customer
     *
     * @param int $customerId Customer/User ID
     * @return int
     */
    public function countByCustomerId(int $customerId): int;

    /**
     * Count renewal invoices for order
     *
     * @param string $orderId Order ID
     * @return int
     */
    public function countRenewalsByOrderId(string $orderId): int;

    /**
     * Get invoice statistics
     *
     * @return array
     */
    public function getStatistics(): array;
}
