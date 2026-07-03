<?php
/**
 * Invoice Database Repository
 *
 * Implements invoice data retrieval using database queries.
 * Invoices are generated from order data, not stored separately.
 *
 * @package WPDMPP\Invoice\Repository
 * @since 7.0.0
 */

namespace WPDMPP\Invoice\Repository;

use WPDMPP\Invoice\Invoice;
use WPDMPP\Invoice\InvoiceRepositoryInterface;

defined('ABSPATH') || exit;

class DatabaseRepository implements InvoiceRepositoryInterface
{
    /**
     * Settings option name
     */
    private const SETTINGS_OPTION = '_wpdmpp_settings';

    /**
     * Get invoice by order ID
     *
     * @param string $orderId Order ID
     * @return Invoice|null
     */
    public function findByOrderId(string $orderId): ?Invoice
    {
        $order = $this->getOrderData($orderId);
        if (!$order) {
            return null;
        }

        $items = $this->getOrderItems($orderId);
        $settings = $this->getSettings();

        return Invoice::fromOrder($order, $items, $settings);
    }

    /**
     * Get renewal invoice
     *
     * @param string $orderId     Order ID
     * @param int    $renewalDate Renewal timestamp
     * @return Invoice|null
     */
    public function findRenewalInvoice(string $orderId, int $renewalDate): ?Invoice
    {
        $order = $this->getOrderData($orderId);
        if (!$order) {
            return null;
        }

        // Verify renewal exists
        $renewal = $this->getRenewalByDate($orderId, $renewalDate);
        if (!$renewal) {
            return null;
        }

        $items = $this->getOrderItems($orderId);
        $settings = $this->getSettings();

        return Invoice::fromOrder($order, $items, $settings, $renewalDate);
    }

    /**
     * Get all invoices for a customer
     *
     * @param int   $customerId Customer/User ID
     * @param array $args       Query arguments
     * @return Invoice[]
     */
    public function findByCustomerId(int $customerId, array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'status' => '', // Filter by payment status
            'limit' => -1,
            'offset' => 0,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM {$wpdb->prefix}ahm_orders WHERE uid = %d";
        $params = [$customerId];

        if (!empty($args['status'])) {
            $sql .= " AND payment_status = %s";
            $params[] = $args['status'];
        }

        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";

        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }

        $orders = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        $settings = $this->getSettings();
        $invoices = [];

        foreach ($orders as $order) {
            $items = $this->getOrderItems($order->order_id);
            $invoices[] = Invoice::fromOrder($order, $items, $settings);
        }

        return $invoices;
    }

    /**
     * Get renewal invoices for an order
     *
     * @param string $orderId Order ID
     * @return array Array of renewal records
     */
    public function findRenewalsByOrderId(string $orderId): array
    {
        global $wpdb;

        $renewals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_order_renews WHERE order_id = %s ORDER BY date DESC",
            $orderId
        ));

        return $renewals ?: [];
    }

    /**
     * Get all renewals for a customer
     *
     * @param int $customerId Customer/User ID
     * @return array
     */
    public function findRenewalsByCustomerId(int $customerId): array
    {
        global $wpdb;

        $renewals = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, o.order_id, o.total as order_total
             FROM {$wpdb->prefix}ahm_order_renews r
             INNER JOIN {$wpdb->prefix}ahm_orders o ON r.order_id = o.order_id
             WHERE o.uid = %d
             ORDER BY r.date DESC",
            $customerId
        ));

        return $renewals ?: [];
    }

    /**
     * Get renewal by date
     *
     * @param string $orderId     Order ID
     * @param int    $renewalDate Renewal timestamp
     * @return object|null
     */
    private function getRenewalByDate(string $orderId, int $renewalDate): ?object
    {
        global $wpdb;

        $renewal = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_order_renews WHERE order_id = %s AND date = %d",
            $orderId,
            $renewalDate
        ));

        return $renewal ?: null;
    }

    /**
     * Get invoice settings
     *
     * @return array
     */
    public function getSettings(): array
    {
        $settings = get_option(self::SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        // Default values
        $defaults = [
            'invoice_logo' => '',
            'invoice_company_address' => '',
            'invoice_thanks' => __('Thank you for your business!', 'wpdm-premium-packages'),
            'invoice_terms_acceptance' => '',
            'signature' => '',
        ];

        return wp_parse_args($settings, $defaults);
    }

    /**
     * Save invoice settings
     *
     * @param array $settings Settings array
     * @return bool
     */
    public function saveSettings(array $settings): bool
    {
        $currentSettings = get_option(self::SETTINGS_OPTION, []);
        if (!is_array($currentSettings)) {
            $currentSettings = [];
        }

        // Only update invoice-related settings
        $invoiceKeys = [
            'invoice_logo',
            'invoice_company_address',
            'invoice_thanks',
            'invoice_terms_acceptance',
            'signature',
        ];

        foreach ($invoiceKeys as $key) {
            if (isset($settings[$key])) {
                $currentSettings[$key] = sanitize_text_field($settings[$key]);
            }
        }

        return update_option(self::SETTINGS_OPTION, $currentSettings);
    }

    /**
     * Get order data for invoice generation
     *
     * @param string $orderId Order ID
     * @return object|null
     */
    public function getOrderData(string $orderId): ?object
    {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s",
            $orderId
        ));

        return $order ?: null;
    }

    /**
     * Get order items for invoice generation
     *
     * @param string $orderId Order ID
     * @return array
     */
    public function getOrderItems(string $orderId): array
    {
        global $wpdb;

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_order_items WHERE oid = %s",
            $orderId
        ), ARRAY_A);

        if (!$items) {
            return [];
        }

        // Enhance items with product info
        foreach ($items as &$item) {
            $post = get_post($item['pid']);
            if ($post) {
                $item['product_name'] = $item['product_name'] ?: $post->post_title;
            } else {
                $item['product_name'] = $item['product_name'] ?: __('[Item Deleted]', 'wpdm-premium-packages');
            }

            // Parse serialized data
            if (!empty($item['license']) && is_string($item['license'])) {
                $item['license'] = maybe_unserialize($item['license']);
            }
            if (!empty($item['variation']) && is_string($item['variation'])) {
                $item['variation'] = maybe_unserialize($item['variation']);
            }
        }

        return $items;
    }

    /**
     * Get billing info for customer
     *
     * @param int    $customerId Customer/User ID
     * @param string $orderId    Order ID (optional, for order-specific billing)
     * @return array
     */
    public function getBillingInfo(int $customerId, string $orderId = ''): array
    {
        $defaults = [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address_1' => '',
            'address_2' => '',
            'city' => '',
            'postcode' => '',
            'country' => '',
            'state' => '',
            'order_email' => '',
            'email' => '',
            'phone' => '',
            'taxid' => '',
        ];

        // Try order-specific billing first
        if (!empty($orderId)) {
            $order = $this->getOrderData($orderId);
            if ($order && !empty($order->billing_info)) {
                $orderBilling = maybe_unserialize($order->billing_info);
                if (is_array($orderBilling)) {
                    return wp_parse_args($orderBilling, $defaults);
                }
            }
        }

        // Fall back to user billing
        if ($customerId > 0) {
            $userBilling = get_user_meta($customerId, 'user_billing_shipping', true);
            $userBilling = maybe_unserialize($userBilling);
            if (is_array($userBilling) && isset($userBilling['billing'])) {
                return wp_parse_args($userBilling['billing'], $defaults);
            }
        }

        return $defaults;
    }

    /**
     * Count invoices for customer
     *
     * @param int $customerId Customer/User ID
     * @return int
     */
    public function countByCustomerId(int $customerId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ahm_orders WHERE uid = %d",
            $customerId
        ));
    }

    /**
     * Count renewal invoices for order
     *
     * @param string $orderId Order ID
     * @return int
     */
    public function countRenewalsByOrderId(string $orderId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ahm_order_renews WHERE order_id = %s",
            $orderId
        ));
    }

    /**
     * Get invoice statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        global $wpdb;

        // Total invoices (completed orders)
        $totalInvoices = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ahm_orders WHERE payment_status = 'Completed'"
        );

        // Total renewal invoices
        $totalRenewals = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ahm_order_renews"
        );

        // Invoices this month
        $thisMonthStart = strtotime(date('Y-m-01'));
        $invoicesThisMonth = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ahm_orders WHERE payment_status = 'Completed' AND date >= %d",
            $thisMonthStart
        ));

        // Total revenue
        $totalRevenue = (float) $wpdb->get_var(
            "SELECT SUM(total) FROM {$wpdb->prefix}ahm_orders WHERE payment_status = 'Completed'"
        );

        // Renewal revenue
        $renewalRevenue = (float) $wpdb->get_var(
            "SELECT SUM(total) FROM {$wpdb->prefix}ahm_order_renews"
        );

        return [
            'total_invoices' => $totalInvoices,
            'total_renewals' => $totalRenewals,
            'invoices_this_month' => $invoicesThisMonth,
            'total_revenue' => $totalRevenue,
            'renewal_revenue' => $renewalRevenue,
            'combined_revenue' => $totalRevenue + $renewalRevenue,
        ];
    }
}
