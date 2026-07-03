<?php
/**
 * Customer Database Repository
 *
 * Implements customer storage using WordPress user system.
 *
 * @package WPDMPP\Customer\Repository
 * @since 7.0.0
 */

namespace WPDMPP\Customer\Repository;

use WPDMPP\Customer\Customer;
use WPDMPP\Customer\CustomerRepositoryInterface;

defined('ABSPATH') || exit;

class DatabaseRepository implements CustomerRepositoryInterface
{
    /**
     * Find customer by ID
     *
     * @param int $id Customer/User ID
     * @return Customer|null
     */
    public function findById(int $id): ?Customer
    {
        if ($id <= 0) {
            return null;
        }

        $user = get_user_by('id', $id);
        if (!$user) {
            return null;
        }

        return new Customer($user);
    }

    /**
     * Find customer by email
     *
     * @param string $email Email address
     * @return Customer|null
     */
    public function findByEmail(string $email): ?Customer
    {
        if (empty($email)) {
            return null;
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            return null;
        }

        return new Customer($user);
    }

    /**
     * Find all customers
     *
     * @param array $args Query arguments
     * @return Customer[]
     */
    public function findAll(array $args = []): array
    {
        $defaults = [
            'role' => Customer::ROLE,
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => -1,
        ];

        $args = wp_parse_args($args, $defaults);
        $userQuery = new \WP_User_Query($args);

        $customers = [];
        foreach ($userQuery->get_results() as $user) {
            $customers[] = new Customer($user);
        }

        return $customers;
    }

    /**
     * Find customers with pagination
     *
     * @param int   $page     Page number
     * @param int   $perPage  Items per page
     * @param array $args     Additional query arguments
     * @return array ['customers' => Customer[], 'total' => int, 'pages' => int]
     */
    public function findPaginated(int $page = 1, int $perPage = 20, array $args = []): array
    {
        $defaults = [
            'role' => Customer::ROLE,
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => $perPage,
            'paged' => $page,
            'count_total' => true,
        ];

        $args = wp_parse_args($args, $defaults);
        $userQuery = new \WP_User_Query($args);

        $customers = [];
        foreach ($userQuery->get_results() as $user) {
            $customers[] = new Customer($user);
        }

        $total = $userQuery->get_total();
        $pages = ceil($total / $perPage);

        return [
            'customers' => $customers,
            'total' => $total,
            'pages' => (int) $pages,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Search customers
     *
     * @param string $search Search term
     * @param array  $args   Additional arguments
     * @return Customer[]
     */
    public function search(string $search, array $args = []): array
    {
        if (empty($search)) {
            return $this->findAll($args);
        }

        $defaults = [
            'role' => Customer::ROLE,
            'search' => '*' . esc_attr($search) . '*',
            'search_columns' => ['ID', 'user_login', 'user_email', 'user_nicename', 'display_name'],
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => -1,
        ];

        $args = wp_parse_args($args, $defaults);
        $userQuery = new \WP_User_Query($args);

        $customers = [];
        foreach ($userQuery->get_results() as $user) {
            $customers[] = new Customer($user);
        }

        return $customers;
    }

    /**
     * Save customer meta
     *
     * @param Customer $customer Customer object
     * @return bool
     */
    public function save(Customer $customer): bool
    {
        if (!$customer->exists()) {
            return false;
        }

        $userId = $customer->getId();

        // Update total spent
        update_user_meta($userId, Customer::META_TOTAL_SPENT, $customer->getTotalSpent());

        // Update last login if set
        if ($customer->getLastLogin() > 0) {
            update_user_meta($userId, Customer::META_LAST_LOGIN, $customer->getLastLogin());
        }

        return true;
    }

    /**
     * Delete customer (remove role only, doesn't delete user)
     *
     * @param int $id Customer ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->removeCustomerRole($id);
    }

    /**
     * Count total customers
     *
     * @param array $args Query arguments
     * @return int
     */
    public function count(array $args = []): int
    {
        $defaults = [
            'role' => Customer::ROLE,
            'count_total' => true,
            'number' => 0, // Don't retrieve users, just count
        ];

        $args = wp_parse_args($args, $defaults);
        $userQuery = new \WP_User_Query($args);

        return $userQuery->get_total();
    }

    /**
     * Add customer role to user
     *
     * @param int $userId User ID
     * @return bool
     */
    public function addCustomerRole(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user instanceof \WP_User) {
            return false;
        }

        if (!in_array(Customer::ROLE, $user->roles, true)) {
            $user->add_role(Customer::ROLE);
            return true;
        }

        return false;
    }

    /**
     * Remove customer role from user
     *
     * @param int $userId User ID
     * @return bool
     */
    public function removeCustomerRole(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user instanceof \WP_User) {
            return false;
        }

        if (in_array(Customer::ROLE, $user->roles, true)) {
            $user->remove_role(Customer::ROLE);
            return true;
        }

        return false;
    }

    /**
     * Check if user is a customer
     *
     * @param int $userId User ID
     * @return bool
     */
    public function isCustomer(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user instanceof \WP_User) {
            return false;
        }

        return in_array(Customer::ROLE, $user->roles, true);
    }

    /**
     * Get total spent for customer
     *
     * @param int $userId Customer ID
     * @return float
     */
    public function getTotalSpent(int $userId): float
    {
        $total = get_user_meta($userId, Customer::META_TOTAL_SPENT, true);

        if (empty($total)) {
            // Calculate if not cached
            return $this->calculateTotalSpent($userId);
        }

        return (float) $total;
    }

    /**
     * Calculate and update total spent
     *
     * @param int $userId Customer ID
     * @return float
     */
    public function calculateTotalSpent(int $userId): float
    {
        global $wpdb;

        if ($userId <= 0) {
            return 0.0;
        }

        // Single optimized query with prepared statements
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT
                (SELECT COALESCE(SUM(total), 0) FROM {$wpdb->prefix}ahm_orders WHERE uid = %d AND order_status = 'Completed') AS order_total,
                (SELECT COALESCE(SUM(r.total), 0) FROM {$wpdb->prefix}ahm_orders o
                 INNER JOIN {$wpdb->prefix}ahm_order_renews r ON o.order_id = r.order_id
                 WHERE o.uid = %d) AS renew_total",
            $userId,
            $userId
        ));

        $total = ($totals->order_total ?? 0) + ($totals->renew_total ?? 0);

        // Update cache
        update_user_meta($userId, Customer::META_TOTAL_SPENT, $total);

        return (float) $total;
    }

    /**
     * Update last login time
     *
     * @param int $userId Customer ID
     * @return bool
     */
    public function updateLastLogin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return (bool) update_user_meta($userId, Customer::META_LAST_LOGIN, time());
    }

    /**
     * Get customers by total spent range
     *
     * @param float $minSpent Minimum total spent
     * @param float $maxSpent Maximum total spent (0 = no limit)
     * @return Customer[]
     */
    public function findByTotalSpentRange(float $minSpent, float $maxSpent = 0): array
    {
        $metaQuery = [
            [
                'key' => Customer::META_TOTAL_SPENT,
                'value' => $minSpent,
                'compare' => '>=',
                'type' => 'DECIMAL',
            ],
        ];

        if ($maxSpent > 0) {
            $metaQuery[] = [
                'key' => Customer::META_TOTAL_SPENT,
                'value' => $maxSpent,
                'compare' => '<=',
                'type' => 'DECIMAL',
            ];
            $metaQuery['relation'] = 'AND';
        }

        $args = [
            'role' => Customer::ROLE,
            'meta_query' => $metaQuery,
            'orderby' => 'meta_value_num',
            'meta_key' => Customer::META_TOTAL_SPENT,
            'order' => 'DESC',
        ];

        return $this->findAll($args);
    }

    /**
     * Get top customers by total spent
     *
     * @param int $limit Number of customers
     * @return Customer[]
     */
    public function findTopCustomers(int $limit = 10): array
    {
        $args = [
            'role' => Customer::ROLE,
            'meta_key' => Customer::META_TOTAL_SPENT,
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'number' => $limit,
        ];

        $userQuery = new \WP_User_Query($args);

        $customers = [];
        foreach ($userQuery->get_results() as $user) {
            $customers[] = new Customer($user);
        }

        return $customers;
    }

    /**
     * Get recently registered customers
     *
     * @param int $limit Number of customers
     * @return Customer[]
     */
    public function findRecentCustomers(int $limit = 10): array
    {
        $args = [
            'role' => Customer::ROLE,
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => $limit,
        ];

        $userQuery = new \WP_User_Query($args);

        $customers = [];
        foreach ($userQuery->get_results() as $user) {
            $customers[] = new Customer($user);
        }

        return $customers;
    }

    /**
     * Get customers who registered in date range
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate   End date (Y-m-d)
     * @return Customer[]
     */
    public function findByRegistrationDateRange(string $startDate, string $endDate): array
    {
        $args = [
            'role' => Customer::ROLE,
            'date_query' => [
                [
                    'after' => $startDate,
                    'before' => $endDate,
                    'inclusive' => true,
                ],
            ],
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        $userQuery = new \WP_User_Query($args);

        $customers = [];
        foreach ($userQuery->get_results() as $user) {
            $customers[] = new Customer($user);
        }

        return $customers;
    }

    /**
     * Get customer statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        global $wpdb;

        // Total customers
        $totalCustomers = $this->count();

        // New customers this month
        $thisMonthStart = date('Y-m-01 00:00:00');
        $newThisMonth = $this->count([
            'date_query' => [
                [
                    'after' => $thisMonthStart,
                    'inclusive' => true,
                ],
            ],
        ]);

        // New customers last month
        $lastMonthStart = date('Y-m-01 00:00:00', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t 23:59:59', strtotime('-1 month'));
        $newLastMonth = $this->count([
            'date_query' => [
                [
                    'after' => $lastMonthStart,
                    'before' => $lastMonthEnd,
                    'inclusive' => true,
                ],
            ],
        ]);

        // Total revenue from all customers
        $totalRevenue = $wpdb->get_var(
            "SELECT SUM(total) FROM {$wpdb->prefix}ahm_orders WHERE order_status = 'Completed'"
        );

        // Average order value
        $avgOrderValue = $wpdb->get_var(
            "SELECT AVG(total) FROM {$wpdb->prefix}ahm_orders WHERE order_status = 'Completed'"
        );

        // Customers with orders
        $customersWithOrders = $wpdb->get_var(
            "SELECT COUNT(DISTINCT uid) FROM {$wpdb->prefix}ahm_orders WHERE order_status = 'Completed' AND uid > 0"
        );

        return [
            'total_customers' => (int) $totalCustomers,
            'new_this_month' => (int) $newThisMonth,
            'new_last_month' => (int) $newLastMonth,
            'growth_percentage' => $newLastMonth > 0 ? round((($newThisMonth - $newLastMonth) / $newLastMonth) * 100, 2) : 0,
            'total_revenue' => (float) ($totalRevenue ?? 0),
            'average_order_value' => (float) ($avgOrderValue ?? 0),
            'customers_with_orders' => (int) ($customersWithOrders ?? 0),
        ];
    }
}
