<?php
/**
 * Cart Storage Interface
 *
 * Defines the contract for cart persistence.
 * Implementations can use WordPress options, sessions, database tables, etc.
 *
 * @package WPDMPP\Cart
 * @since 7.0.0
 */

namespace WPDMPP\Cart;

defined('ABSPATH') || exit;

interface CartStorageInterface {

    /**
     * Load cart data from storage
     *
     * @param string $cartId
     * @return Cart|null
     */
    public function load(string $cartId): ?Cart;

    /**
     * Save cart data to storage
     *
     * @param Cart $cart
     * @return bool
     */
    public function save(Cart $cart): bool;

    /**
     * Delete cart from storage
     *
     * @param string $cartId
     * @return bool
     */
    public function delete(string $cartId): bool;

    /**
     * Check if cart exists in storage
     *
     * @param string $cartId
     * @return bool
     */
    public function exists(string $cartId): bool;

    /**
     * Load coupon data for a cart
     *
     * @param string $cartId
     * @return array|null
     */
    public function loadCoupon(string $cartId): ?array;

    /**
     * Save coupon data for a cart
     *
     * @param string $cartId
     * @param array $couponData
     * @return bool
     */
    public function saveCoupon(string $cartId, array $couponData): bool;

    /**
     * Delete coupon data for a cart
     *
     * @param string $cartId
     * @return bool
     */
    public function deleteCoupon(string $cartId): bool;

    /**
     * Load tax data for a cart
     *
     * @param string $cartId
     * @return float
     */
    public function loadTax(string $cartId): float;

    /**
     * Save tax data for a cart
     *
     * @param string $cartId
     * @param float $tax
     * @return bool
     */
    public function saveTax(string $cartId, float $tax): bool;

    /**
     * Check if cart is locked
     *
     * @param string $cartId
     * @return bool
     */
    public function isLocked(string $cartId): bool;

    /**
     * Lock a cart
     *
     * @param string $cartId
     * @return bool
     */
    public function lock(string $cartId): bool;

    /**
     * Unlock a cart
     *
     * @param string $cartId
     * @return bool
     */
    public function unlock(string $cartId): bool;

    /**
     * Get recurring flag for a cart
     *
     * @param string $cartId
     * @return bool|null
     */
    public function getRecurring(string $cartId): ?bool;

    /**
     * Set recurring flag for a cart
     *
     * @param string $cartId
     * @param bool $recurring
     * @return bool
     */
    public function setRecurring(string $cartId, bool $recurring): bool;

    /**
     * Clear recurring flag for a cart
     *
     * @param string $cartId
     * @return bool
     */
    public function clearRecurring(string $cartId): bool;

    /**
     * Transfer cart from one ID to another (e.g., guest to user)
     *
     * @param string $fromCartId
     * @param string $toCartId
     * @return bool
     */
    public function transfer(string $fromCartId, string $toCartId): bool;

    /**
     * Delete all carts (cleanup)
     *
     * @return int Number of carts deleted
     */
    public function deleteAll(): int;
}
