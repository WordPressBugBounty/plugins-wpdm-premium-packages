<?php
/**
 * Customer Repository Interface
 *
 * Contract for customer data storage and retrieval.
 *
 * @package WPDMPP\Customer
 * @since 7.0.0
 */

namespace WPDMPP\Customer;

defined('ABSPATH') || exit;

interface CustomerRepositoryInterface
{
    /**
     * Find customer by ID
     *
     * @param int $id Customer/User ID
     * @return Customer|null
     */
    public function findById(int $id): ?Customer;

    /**
     * Find customer by email
     *
     * @param string $email Email address
     * @return Customer|null
     */
    public function findByEmail(string $email): ?Customer;

    /**
     * Find all customers
     *
     * @param array $args Query arguments
     * @return Customer[]
     */
    public function findAll(array $args = []): array;

    /**
     * Find customers with pagination
     *
     * @param int   $page     Page number
     * @param int   $perPage  Items per page
     * @param array $args     Additional query arguments
     * @return array ['customers' => Customer[], 'total' => int, 'pages' => int]
     */
    public function findPaginated(int $page = 1, int $perPage = 20, array $args = []): array;

    /**
     * Search customers
     *
     * @param string $search Search term
     * @param array  $args   Additional arguments
     * @return Customer[]
     */
    public function search(string $search, array $args = []): array;

    /**
     * Save customer meta
     *
     * @param Customer $customer Customer object
     * @return bool
     */
    public function save(Customer $customer): bool;

    /**
     * Delete customer (remove role only, doesn't delete user)
     *
     * @param int $id Customer ID
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Count total customers
     *
     * @param array $args Query arguments
     * @return int
     */
    public function count(array $args = []): int;

    /**
     * Add customer role to user
     *
     * @param int $userId User ID
     * @return bool
     */
    public function addCustomerRole(int $userId): bool;

    /**
     * Remove customer role from user
     *
     * @param int $userId User ID
     * @return bool
     */
    public function removeCustomerRole(int $userId): bool;

    /**
     * Check if user is a customer
     *
     * @param int $userId User ID
     * @return bool
     */
    public function isCustomer(int $userId): bool;

    /**
     * Get total spent for customer
     *
     * @param int $userId Customer ID
     * @return float
     */
    public function getTotalSpent(int $userId): float;

    /**
     * Calculate and update total spent
     *
     * @param int $userId Customer ID
     * @return float
     */
    public function calculateTotalSpent(int $userId): float;

    /**
     * Update last login time
     *
     * @param int $userId Customer ID
     * @return bool
     */
    public function updateLastLogin(int $userId): bool;

    /**
     * Get customers by total spent range
     *
     * @param float $minSpent Minimum total spent
     * @param float $maxSpent Maximum total spent (0 = no limit)
     * @return Customer[]
     */
    public function findByTotalSpentRange(float $minSpent, float $maxSpent = 0): array;

    /**
     * Get top customers by total spent
     *
     * @param int $limit Number of customers
     * @return Customer[]
     */
    public function findTopCustomers(int $limit = 10): array;

    /**
     * Get recently registered customers
     *
     * @param int $limit Number of customers
     * @return Customer[]
     */
    public function findRecentCustomers(int $limit = 10): array;

    /**
     * Get customers who registered in date range
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate   End date (Y-m-d)
     * @return Customer[]
     */
    public function findByRegistrationDateRange(string $startDate, string $endDate): array;

    /**
     * Get customer statistics
     *
     * @return array
     */
    public function getStatistics(): array;
}
