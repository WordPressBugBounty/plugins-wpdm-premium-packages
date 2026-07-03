<?php
/**
 * Customer Service
 *
 * Main orchestration class for customer operations.
 * Provides a singleton interface for managing customers.
 *
 * @package WPDMPP\Customer
 * @since 7.0.0
 */

namespace WPDMPP\Customer;

use WPDMPP\Customer\Repository\DatabaseRepository;
use WPDMPP\Order\OrderService;

defined('ABSPATH') || exit;

class CustomerService
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Customer repository
     *
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $repository;

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
     * @param CustomerRepositoryInterface $repository Repository instance
     * @return self
     */
    public function setRepository(CustomerRepositoryInterface $repository): self
    {
        $this->repository = $repository;
        return $this;
    }

    /**
     * Get repository
     *
     * @return CustomerRepositoryInterface
     */
    public function getRepository(): CustomerRepositoryInterface
    {
        return $this->repository;
    }

    // =========================================================================
    // CUSTOMER RETRIEVAL
    // =========================================================================

    /**
     * Get customer by ID
     *
     * @param int $id Customer/User ID
     * @return Customer|null
     */
    public function getCustomer(int $id): ?Customer
    {
        return $this->repository->findById($id);
    }

    /**
     * Get customer by email
     *
     * @param string $email Email address
     * @return Customer|null
     */
    public function getCustomerByEmail(string $email): ?Customer
    {
        return $this->repository->findByEmail($email);
    }

    /**
     * Get all customers
     *
     * @param array $args Query arguments
     * @return Customer[]
     */
    public function getCustomers(array $args = []): array
    {
        return $this->repository->findAll($args);
    }

    /**
     * Get paginated customers
     *
     * @param int   $page    Page number
     * @param int   $perPage Items per page
     * @param array $args    Additional arguments
     * @return array
     */
    public function getCustomersPaginated(int $page = 1, int $perPage = 20, array $args = []): array
    {
        return $this->repository->findPaginated($page, $perPage, $args);
    }

    /**
     * Search customers
     *
     * @param string $search Search term
     * @param array  $args   Additional arguments
     * @return Customer[]
     */
    public function searchCustomers(string $search, array $args = []): array
    {
        return $this->repository->search($search, $args);
    }

    /**
     * Count total customers
     *
     * @param array $args Query arguments
     * @return int
     */
    public function countCustomers(array $args = []): int
    {
        return $this->repository->count($args);
    }

    // =========================================================================
    // CUSTOMER ROLE MANAGEMENT
    // =========================================================================

    /**
     * Add customer role to user
     *
     * @param int $userId User ID
     * @return bool
     */
    public function addCustomer(int $userId): bool
    {
        $result = $this->repository->addCustomerRole($userId);

        if ($result) {
            do_action('wpdmpp_customer_added', $userId);
        }

        return $result;
    }

    /**
     * Remove customer role from user
     *
     * @param int $userId User ID
     * @return bool
     */
    public function removeCustomer(int $userId): bool
    {
        $result = $this->repository->removeCustomerRole($userId);

        if ($result) {
            do_action('wpdmpp_customer_removed', $userId);
        }

        return $result;
    }

    /**
     * Check if user is a customer
     *
     * @param int $userId User ID
     * @return bool
     */
    public function isCustomer(int $userId): bool
    {
        return $this->repository->isCustomer($userId);
    }

    // =========================================================================
    // SPENDING & STATISTICS
    // =========================================================================

    /**
     * Get total spent by customer
     *
     * @param int $userId Customer ID
     * @return float
     */
    public function getTotalSpent(int $userId): float
    {
        return $this->repository->getTotalSpent($userId);
    }

    /**
     * Calculate and update total spent
     *
     * @param int $userId Customer ID
     * @return float
     */
    public function calculateTotalSpent(int $userId): float
    {
        return $this->repository->calculateTotalSpent($userId);
    }

    /**
     * Refresh total spent for customer
     *
     * @param int $userId Customer ID
     * @return float
     */
    public function refreshTotalSpent(int $userId): float
    {
        $total = $this->repository->calculateTotalSpent($userId);
        do_action('wpdmpp_customer_total_spent_refreshed', $userId, $total);
        return $total;
    }

    /**
     * Update last login time
     *
     * @param int $userId Customer ID
     * @return bool
     */
    public function updateLastLogin(int $userId): bool
    {
        return $this->repository->updateLastLogin($userId);
    }

    /**
     * Get customer statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    // =========================================================================
    // CUSTOMER QUERIES
    // =========================================================================

    /**
     * Get top customers by spending
     *
     * @param int $limit Number of customers
     * @return Customer[]
     */
    public function getTopCustomers(int $limit = 10): array
    {
        return $this->repository->findTopCustomers($limit);
    }

    /**
     * Get recent customers
     *
     * @param int $limit Number of customers
     * @return Customer[]
     */
    public function getRecentCustomers(int $limit = 10): array
    {
        return $this->repository->findRecentCustomers($limit);
    }

    /**
     * Get customers by spending range
     *
     * @param float $minSpent Minimum total spent
     * @param float $maxSpent Maximum total spent (0 = no limit)
     * @return Customer[]
     */
    public function getCustomersBySpendingRange(float $minSpent, float $maxSpent = 0): array
    {
        return $this->repository->findByTotalSpentRange($minSpent, $maxSpent);
    }

    /**
     * Get customers registered in date range
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate   End date (Y-m-d)
     * @return Customer[]
     */
    public function getCustomersByDateRange(string $startDate, string $endDate): array
    {
        return $this->repository->findByRegistrationDateRange($startDate, $endDate);
    }

    // =========================================================================
    // CUSTOMER ORDERS
    // =========================================================================

    /**
     * Get customer orders
     *
     * @param int  $userId        Customer ID
     * @param bool $completedOnly Only completed orders
     * @return array
     */
    public function getCustomerOrders(int $userId, bool $completedOnly = false): array
    {
        if (class_exists('\\WPDMPP\\Order\\OrderService')) {
            $orderService = OrderService::instance();
            return $orderService->getUserOrders($userId, $completedOnly);
        }

        return [];
    }

    /**
     * Get customer order count
     *
     * @param int  $userId        Customer ID
     * @param bool $completedOnly Only count completed orders
     * @return int
     */
    public function getOrderCount(int $userId, bool $completedOnly = false): int
    {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ahm_orders WHERE uid = %d";
        if ($completedOnly) {
            $sql .= " AND order_status = 'Completed'";
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $userId));
    }

    /**
     * Get customer purchased items
     *
     * @param int $userId Customer ID
     * @return array
     */
    public function getPurchasedItems(int $userId): array
    {
        if (class_exists('\\WPDMPP\\Order\\OrderService')) {
            $orderService = OrderService::instance();
            return $orderService->getPurchasedItems($userId);
        }

        return [];
    }

    // =========================================================================
    // PRODUCT ROLE MANAGEMENT
    // =========================================================================

    /**
     * Process active roles for customer based on purchases
     *
     * @param int $userId Customer ID
     * @return bool
     */
    public function processActiveRoles(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $items = $this->getPurchasedItems($userId);

        if (empty($items)) {
            return false;
        }

        // First remove roles for non-completed orders
        foreach ($items as $item) {
            if (isset($item->order_status) && $item->order_status !== 'Completed') {
                $this->removeProductRole($item->pid, $item->cid ?? $userId);
            }
        }

        // Then assign roles for completed orders
        foreach ($items as $item) {
            if (isset($item->order_status) && $item->order_status === 'Completed') {
                $this->assignProductRole($item->pid, $item->cid ?? $userId);
            }
        }

        do_action('wpdmpp_customer_roles_processed', $userId, $items);

        return true;
    }

    /**
     * Assign product role to customer
     *
     * @param int $productId Product ID
     * @param int $userId    User ID
     * @return bool
     */
    private function assignProductRole(int $productId, int $userId): bool
    {
        if (class_exists('\\WPDMPP\\Product')) {
            $product = new \WPDMPP\Product($productId);
            $product->assignRole($userId);
            return true;
        }

        return false;
    }

    /**
     * Remove product role from customer
     *
     * @param int $productId Product ID
     * @param int $userId    User ID
     * @return bool
     */
    private function removeProductRole(int $productId, int $userId): bool
    {
        if (class_exists('\\WPDMPP\\Product')) {
            $product = new \WPDMPP\Product($productId);
            $product->removeRole($userId);
            return true;
        }

        return false;
    }

    // =========================================================================
    // CUSTOMER INFO FROM ORDER
    // =========================================================================

    /**
     * Get customer info from order
     *
     * @param string $orderId Order ID
     * @return array ['name' => string, 'email' => string]
     */
    public function getCustomerInfoFromOrder(string $orderId): array
    {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT uid, billing_info FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s",
            $orderId
        ));

        if (!$order) {
            return ['name' => '', 'email' => ''];
        }

        // Try to get from user
        if ($order->uid > 0) {
            $customer = $this->getCustomer((int) $order->uid);
            if ($customer && $customer->exists()) {
                return [
                    'name' => $customer->getFullName(),
                    'email' => $customer->getEmail(),
                ];
            }
        }

        // Fallback to billing info
        $billingInfo = maybe_unserialize($order->billing_info);
        if (is_array($billingInfo)) {
            $name = trim(($billingInfo['first_name'] ?? '') . ' ' . ($billingInfo['last_name'] ?? ''));
            return [
                'name' => $name ?: ($billingInfo['order_name'] ?? ''),
                'email' => $billingInfo['order_email'] ?? '',
            ];
        }

        return ['name' => '', 'email' => ''];
    }

    // =========================================================================
    // CUSTOMER PROFILE DATA
    // =========================================================================

    /**
     * Get full customer profile data
     *
     * @param int $userId Customer ID
     * @return array|null
     */
    public function getCustomerProfile(int $userId): ?array
    {
        $customer = $this->getCustomer($userId);
        if (!$customer || !$customer->exists()) {
            return null;
        }

        $orders = $this->getCustomerOrders($userId);
        $completedOrders = array_filter($orders, function ($order) {
            if (is_object($order)) {
                return ($order->order_status ?? '') === 'Completed';
            }
            return ($order['order_status'] ?? '') === 'Completed';
        });

        return [
            'customer' => $customer->toArray(),
            'orders' => [
                'total' => count($orders),
                'completed' => count($completedOrders),
            ],
            'purchases' => $this->getPurchasedItems($userId),
        ];
    }

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    /**
     * Recalculate total spent for all customers
     *
     * @return int Number of customers updated
     */
    public function recalculateAllTotalSpent(): int
    {
        $customers = $this->getCustomers();
        $count = 0;

        foreach ($customers as $customer) {
            $this->calculateTotalSpent($customer->getId());
            $count++;
        }

        do_action('wpdmpp_all_customer_totals_recalculated', $count);

        return $count;
    }

    /**
     * Sync customer roles (add role to users with completed orders)
     *
     * @return int Number of users updated
     */
    public function syncCustomerRoles(): int
    {
        global $wpdb;

        // Get all users with completed orders who don't have the customer role
        $userIds = $wpdb->get_col(
            "SELECT DISTINCT uid FROM {$wpdb->prefix}ahm_orders WHERE order_status = 'Completed' AND uid > 0"
        );

        $count = 0;
        foreach ($userIds as $userId) {
            if ($this->addCustomer((int) $userId)) {
                $count++;
            }
        }

        do_action('wpdmpp_customer_roles_synced', $count);

        return $count;
    }
}
