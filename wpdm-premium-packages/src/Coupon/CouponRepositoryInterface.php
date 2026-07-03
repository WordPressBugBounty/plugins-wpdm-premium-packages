<?php
/**
 * Coupon Repository Interface
 *
 * Defines the contract for coupon storage operations.
 *
 * @package WPDMPP\Coupon
 * @since 7.0.0
 */

namespace WPDMPP\Coupon;

defined('ABSPATH') || exit;

interface CouponRepositoryInterface {

    /**
     * Find a coupon by code
     *
     * @param string $code Coupon code
     * @return Coupon|null
     */
    public function findByCode(string $code): ?Coupon;

    /**
     * Find a coupon by ID
     *
     * @param int $id Database ID
     * @return Coupon|null
     */
    public function findById(int $id): ?Coupon;

    /**
     * Get all coupons with optional filtering
     *
     * @param array $args Query arguments
     * @return array ['coupons' => Coupon[], 'total' => int]
     */
    public function findAll(array $args = []): array;

    /**
     * Get active (non-expired, not exhausted) coupons
     *
     * @return Coupon[]
     */
    public function findActive(): array;

    /**
     * Get auto-apply coupons that match the given amount
     *
     * @param float $cartTotal Cart total amount
     * @return Coupon[]
     */
    public function findAutoApply(float $cartTotal): array;

    /**
     * Get coupons for a specific product
     *
     * @param int $productId Product ID
     * @return Coupon[]
     */
    public function findByProduct(int $productId): array;

    /**
     * Save a coupon (insert or update)
     *
     * @param Coupon $coupon Coupon to save
     * @return bool
     */
    public function save(Coupon $coupon): bool;

    /**
     * Delete a coupon
     *
     * @param int $id Coupon ID
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Delete a coupon by code
     *
     * @param string $code Coupon code
     * @return bool
     */
    public function deleteByCode(string $code): bool;

    /**
     * Check if coupon code exists
     *
     * @param string $code Coupon code
     * @param int|null $excludeId ID to exclude (for updates)
     * @return bool
     */
    public function codeExists(string $code, ?int $excludeId = null): bool;

    /**
     * Increment coupon usage count
     *
     * @param string $code Coupon code
     * @return bool
     */
    public function incrementUsage(string $code): bool;

    /**
     * Get orders that used a specific coupon
     *
     * @param string $code Coupon code
     * @return array
     */
    public function getOrdersByCoupon(string $code): array;

    /**
     * Get total count of coupons
     *
     * @param array $args Filter arguments
     * @return int
     */
    public function count(array $args = []): int;

    /**
     * Get coupon usage statistics
     *
     * @param string $code Coupon code
     * @return array
     */
    public function getUsageStats(string $code): array;
}
