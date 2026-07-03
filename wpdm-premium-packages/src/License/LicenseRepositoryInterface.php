<?php
/**
 * License Repository Interface
 *
 * Defines the contract for license storage operations.
 *
 * @package WPDMPP\License
 * @since 7.0.0
 */

namespace WPDMPP\License;

defined('ABSPATH') || exit;

interface LicenseRepositoryInterface {

    /**
     * Find a license by ID
     *
     * @param int $id License ID
     * @return License|null
     */
    public function findById(int $id): ?License;

    /**
     * Find a license by license key
     *
     * @param string $licenseNo License key/number
     * @return License|null
     */
    public function findByLicenseNo(string $licenseNo): ?License;

    /**
     * Find license by order ID and product ID
     *
     * @param string $orderId Order ID
     * @param int    $productId Product ID
     * @return License|null
     */
    public function findByOrderAndProduct(string $orderId, int $productId): ?License;

    /**
     * Find all licenses for an order
     *
     * @param string $orderId Order ID
     * @return License[]
     */
    public function findByOrder(string $orderId): array;

    /**
     * Find all licenses for a product
     *
     * @param int $productId Product ID
     * @return License[]
     */
    public function findByProduct(int $productId): array;

    /**
     * Find all licenses for a user (via order ownership)
     *
     * @param int $userId User ID
     * @return License[]
     */
    public function findByUser(int $userId): array;

    /**
     * Get all licenses with optional filtering
     *
     * @param array $args Query arguments
     * @return array ['licenses' => License[], 'total' => int]
     */
    public function findAll(array $args = []): array;

    /**
     * Find licenses by domain
     *
     * @param string $domain Domain to search
     * @return License[]
     */
    public function findByDomain(string $domain): array;

    /**
     * Find expired licenses
     *
     * @return License[]
     */
    public function findExpired(): array;

    /**
     * Find active (non-expired) licenses
     *
     * @return License[]
     */
    public function findActive(): array;

    /**
     * Save a license (insert or update)
     *
     * @param License $license License to save
     * @return bool
     */
    public function save(License $license): bool;

    /**
     * Delete a license
     *
     * @param int $id License ID
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Delete license by license key
     *
     * @param string $licenseNo License key
     * @return bool
     */
    public function deleteByLicenseNo(string $licenseNo): bool;

    /**
     * Check if license key exists
     *
     * @param string $licenseNo License key
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool
     */
    public function licenseNoExists(string $licenseNo, ?int $excludeId = null): bool;

    /**
     * Update domains for a license
     *
     * @param int   $id      License ID
     * @param array $domains New domains array
     * @return bool
     */
    public function updateDomains(int $id, array $domains): bool;

    /**
     * Update license status
     *
     * @param int $id     License ID
     * @param int $status New status
     * @return bool
     */
    public function updateStatus(int $id, int $status): bool;

    /**
     * Get total count of licenses
     *
     * @param array $args Filter arguments
     * @return int
     */
    public function count(array $args = []): int;

    /**
     * Bulk delete licenses
     *
     * @param array $ids License IDs
     * @return int Number of deleted licenses
     */
    public function bulkDelete(array $ids): int;

    /**
     * Clear all domains for a license
     *
     * @param int $id License ID
     * @return bool
     */
    public function clearDomains(int $id): bool;
}
