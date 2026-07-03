<?php
/**
 * Options-Based Cart Storage
 *
 * Implements cart persistence using WordPress options table.
 * This is the default storage method, compatible with the legacy system.
 *
 * Storage keys:
 * - Cart items: {cartId} (option name)
 * - Coupon: {cartId}_coupon (TempStorage)
 * - Tax: {cartId}_tax (TempStorage)
 * - Lock: __wpdm_cart_locked_{cartId} (TempStorage)
 * - Recurring: __rec_{cartId} (TempStorage)
 *
 * @package WPDMPP\Cart\Storage
 * @since 7.0.0
 */

namespace WPDMPP\Cart\Storage;

use WPDMPP\Cart\Cart;
use WPDMPP\Cart\CartItem;
use WPDMPP\Cart\CartStorageInterface;
use WPDM\__\TempStorage;

defined('ABSPATH') || exit;

class OptionsStorage implements CartStorageInterface {

    /**
     * Load cart data from storage
     *
     * @param string $cartId
     * @return Cart|null
     */
    public function load(string $cartId): ?Cart {
        $cartData = maybe_unserialize(get_option($cartId));

        if (!is_array($cartData) || empty($cartData)) {
            return null;
        }

        $cart = Cart::fromLegacy($cartData, $cartId);

        // Load associated data
        $coupon = $this->loadCoupon($cartId);
        if ($coupon) {
            $cart->applyCoupon(
                $coupon['code'] ?? '',
                $coupon['discount'] ?? 0.0,
                $coupon['product_id'] ?? 0,
                $coupon['note'] ?? ''
            );
        }

        $tax = $this->loadTax($cartId);
        $cart->setTax($tax);

        if ($this->isLocked($cartId)) {
            $cart->lock();
        }

        $recurring = $this->getRecurring($cartId);
        if ($recurring !== null) {
            $cart->setRecurring($recurring);
        }

        return $cart;
    }

    /**
     * Save cart data to storage
     *
     * @param Cart $cart
     * @return bool
     */
    public function save(Cart $cart): bool {
        $cartId = $cart->getCartId();
        if (!$cartId) {
            return false;
        }

        $legacyData = $cart->toLegacy();

        if (empty($legacyData)) {
            return $this->delete($cartId);
        }

        $result = update_option($cartId, $legacyData, false);

        // Save associated data
        $coupon = $cart->getCoupon();
        if ($coupon) {
            $this->saveCoupon($cartId, $coupon);
        } else {
            $this->deleteCoupon($cartId);
        }

        $this->saveTax($cartId, $cart->getTax());

        if ($cart->isLocked()) {
            $this->lock($cartId);
        } else {
            $this->unlock($cartId);
        }

        $recurring = $cart->isRecurring();
        if ($recurring !== null) {
            $this->setRecurring($cartId, $recurring);
        }

        return $result !== false;
    }

    /**
     * Delete cart from storage
     *
     * @param string $cartId
     * @return bool
     */
    public function delete(string $cartId): bool {
        delete_option($cartId);
        $this->deleteCoupon($cartId);
        $this->unlock($cartId);
        $this->clearRecurring($cartId);

        // Clear TempStorage tax
        if (class_exists('\WPDM\__\TempStorage')) {
            TempStorage::kill($cartId . '_tax');
        }

        return true;
    }

    /**
     * Check if cart exists in storage
     *
     * @param string $cartId
     * @return bool
     */
    public function exists(string $cartId): bool {
        $cartData = get_option($cartId);
        return is_array($cartData) && !empty($cartData);
    }

    /**
     * Load coupon data for a cart
     *
     * @param string $cartId
     * @return array|null
     */
    public function loadCoupon(string $cartId): ?array {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return null;
        }

        $couponData = TempStorage::get($cartId . '_coupon');

        if (!is_array($couponData) || empty($couponData['code'])) {
            return null;
        }

        return [
            'code' => $couponData['code'] ?? '',
            'discount' => (float) ($couponData['discount'] ?? $couponData['coupon_discount'] ?? 0),
            'product_id' => (int) ($couponData['product_id'] ?? 0),
            'note' => $couponData['note'] ?? '',
        ];
    }

    /**
     * Save coupon data for a cart
     *
     * @param string $cartId
     * @param array $couponData
     * @return bool
     */
    public function saveCoupon(string $cartId, array $couponData): bool {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return false;
        }

        // Store in legacy format for compatibility
        $legacyCoupon = [
            'code' => $couponData['code'] ?? '',
            'coupon' => $couponData['code'] ?? '',
            'discount' => $couponData['discount'] ?? 0,
            'coupon_discount' => $couponData['discount'] ?? 0,
            'product_id' => $couponData['product_id'] ?? 0,
            'note' => $couponData['note'] ?? '',
        ];

        TempStorage::set($cartId . '_coupon', $legacyCoupon);
        return true;
    }

    /**
     * Delete coupon data for a cart
     *
     * @param string $cartId
     * @return bool
     */
    public function deleteCoupon(string $cartId): bool {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return false;
        }

        TempStorage::kill($cartId . '_coupon');
        return true;
    }

    /**
     * Load tax data for a cart
     *
     * @param string $cartId
     * @return float
     */
    public function loadTax(string $cartId): float {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return 0.0;
        }

        return (float) TempStorage::get($cartId . '_tax');
    }

    /**
     * Save tax data for a cart
     *
     * @param string $cartId
     * @param float $tax
     * @return bool
     */
    public function saveTax(string $cartId, float $tax): bool {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return false;
        }

        TempStorage::set($cartId . '_tax', $tax);
        return true;
    }

    /**
     * Check if cart is locked
     *
     * @param string $cartId
     * @return bool
     */
    public function isLocked(string $cartId): bool {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return false;
        }

        return (bool) TempStorage::get('__wpdm_cart_locked_' . $cartId);
    }

    /**
     * Lock a cart
     *
     * @param string $cartId
     * @return bool
     */
    public function lock(string $cartId): bool {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return false;
        }

        TempStorage::set('__wpdm_cart_locked_' . $cartId, 1);
        return true;
    }

    /**
     * Unlock a cart
     *
     * @param string $cartId
     * @return bool
     */
    public function unlock(string $cartId): bool {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return false;
        }

        TempStorage::kill('__wpdm_cart_locked_' . $cartId);
        $this->clearRecurring($cartId);
        return true;
    }

    /**
     * Get recurring flag for a cart
     *
     * @param string $cartId
     * @return bool|null
     */
    public function getRecurring(string $cartId): ?bool {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return null;
        }

        $value = TempStorage::get('__rec_' . $cartId);

        if ($value === 'YES') {
            return true;
        } elseif ($value === 'NO') {
            return false;
        }

        return null;
    }

    /**
     * Set recurring flag for a cart
     *
     * @param string $cartId
     * @param bool $recurring
     * @return bool
     */
    public function setRecurring(string $cartId, bool $recurring): bool {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return false;
        }

        TempStorage::set('__rec_' . $cartId, $recurring ? 'YES' : 'NO');
        return true;
    }

    /**
     * Clear recurring flag for a cart
     *
     * @param string $cartId
     * @return bool
     */
    public function clearRecurring(string $cartId): bool {
        if (!class_exists('\WPDM\__\TempStorage')) {
            return false;
        }

        TempStorage::kill('__rec_' . $cartId);
        return true;
    }

    /**
     * Transfer cart from one ID to another (e.g., guest to user)
     *
     * @param string $fromCartId
     * @param string $toCartId
     * @return bool
     */
    public function transfer(string $fromCartId, string $toCartId): bool {
        $cart = $this->load($fromCartId);

        if (!$cart) {
            return false;
        }

        $cart->setCartId($toCartId);
        $this->save($cart);
        $this->delete($fromCartId);

        return true;
    }

    /**
     * Delete all carts (cleanup)
     *
     * @return int Number of carts deleted
     */
    public function deleteAll(): int {
        global $wpdb;

        // Delete cart options
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_cart'"
        );

        // Delete coupon TempStorage entries
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_coupon'"
        );

        return (int) $deleted;
    }

    /**
     * Migrate cart from guest to user on login
     *
     * @param int $userId
     * @param string $deviceId
     * @return bool
     */
    public function migrateGuestToUser(int $userId, string $deviceId): bool {
        $guestCartId = $deviceId . '_cart';
        $userCartId = $userId . '_cart';

        // Check if guest has a cart
        if (!$this->exists($guestCartId)) {
            return false;
        }

        // Check if user already has a cart
        if ($this->exists($userCartId)) {
            // Could merge or replace - current behavior is replace
            $this->delete($userCartId);
        }

        return $this->transfer($guestCartId, $userCartId);
    }
}
