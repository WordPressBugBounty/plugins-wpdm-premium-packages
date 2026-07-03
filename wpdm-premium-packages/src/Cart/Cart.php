<?php
/**
 * Cart Collection Class
 *
 * Manages a collection of CartItem objects.
 * Provides methods for cart operations, totals calculation, and coupon management.
 *
 * @package WPDMPP\Cart
 * @since 7.0.0
 */

namespace WPDMPP\Cart;

defined('ABSPATH') || exit;

class Cart implements \Countable, \IteratorAggregate {

    /**
     * Cart items indexed by product ID
     * @var CartItem[]
     */
    private array $items = [];

    /**
     * Cart identifier
     * @var string
     */
    private string $cartId = '';

    /**
     * Coupon information
     * @var array{code: string, discount: float, product_id: int, note: string}|null
     */
    private ?array $coupon = null;

    /**
     * Tax amount
     * @var float
     */
    private float $tax = 0.0;

    /**
     * Whether cart is locked (for subscription flows)
     * @var bool
     */
    private bool $locked = false;

    /**
     * Whether this is a recurring cart
     * @var bool|null
     */
    private ?bool $recurring = null;

    /**
     * Create a new Cart
     *
     * @param string $cartId
     */
    public function __construct(string $cartId = '') {
        $this->cartId = $cartId;
    }

    /**
     * Create Cart from array of CartItems
     *
     * @param CartItem[] $items
     * @param string $cartId
     * @return self
     */
    public static function fromItems(array $items, string $cartId = ''): self {
        $cart = new self($cartId);
        foreach ($items as $item) {
            if ($item instanceof CartItem) {
                $cart->items[$item->getProductId()] = $item;
            }
        }
        return $cart;
    }

    /**
     * Create Cart from legacy array format
     *
     * @param array $legacyData
     * @param string $cartId
     * @return self
     */
    public static function fromLegacy(array $legacyData, string $cartId = ''): self {
        $cart = new self($cartId);
        foreach ($legacyData as $productId => $itemData) {
            if (is_array($itemData)) {
                $cart->items[$productId] = CartItem::fromLegacy((int) $productId, $itemData);
            }
        }
        return $cart;
    }

    /**
     * Convert to legacy array format
     *
     * @return array
     */
    public function toLegacy(): array {
        $legacy = [];
        foreach ($this->items as $productId => $item) {
            $legacy[$productId] = $item->toLegacy();
        }
        return $legacy;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'cart_id' => $this->cartId,
            'items' => array_map(fn(CartItem $item) => $item->toArray(), $this->items),
            'subtotal' => $this->getSubtotal(),
            'role_discount' => $this->getTotalRoleDiscount(),
            'coupon' => $this->coupon,
            'coupon_discount' => $this->getCouponDiscount(),
            'tax' => $this->tax,
            'total' => $this->getTotal(),
            'item_count' => $this->count(),
            'is_locked' => $this->locked,
            'is_recurring' => $this->recurring,
        ];
    }

    // -------------------------------------------------------------------------
    // Item Management
    // -------------------------------------------------------------------------

    /**
     * Add an item to the cart
     *
     * @param CartItem $item
     * @return self
     */
    public function addItem(CartItem $item): self {
        if ($this->locked) {
            return $this;
        }

        $productId = $item->getProductId();

        // If item exists, replace it (legacy behavior)
        $this->items[$productId] = $item;

        return $this;
    }

    /**
     * Remove an item from the cart
     *
     * @param int $productId
     * @return self
     */
    public function removeItem(int $productId): self {
        unset($this->items[$productId]);
        return $this;
    }

    /**
     * Get an item by product ID
     *
     * @param int $productId
     * @return CartItem|null
     */
    public function getItem(int $productId): ?CartItem {
        return $this->items[$productId] ?? null;
    }

    /**
     * Check if item exists in cart
     *
     * @param int $productId
     * @return bool
     */
    public function hasItem(int $productId): bool {
        return isset($this->items[$productId]);
    }

    /**
     * Get all items
     *
     * @return CartItem[]
     */
    public function getItems(): array {
        return $this->items;
    }

    /**
     * Update item quantity
     *
     * @param int $productId
     * @param int $quantity
     * @return self
     */
    public function updateQuantity(int $productId, int $quantity): self {
        if (isset($this->items[$productId])) {
            $this->items[$productId] = $this->items[$productId]->withQuantity($quantity);
        }
        return $this;
    }

    /**
     * Clear all items
     *
     * @return self
     */
    public function clear(): self {
        $this->items = [];
        $this->coupon = null;
        $this->tax = 0.0;
        $this->locked = false;
        $this->recurring = null;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Coupon Management
    // -------------------------------------------------------------------------

    /**
     * Apply a coupon to the cart
     *
     * @param string $code
     * @param float $discount
     * @param int $productId Specific product (0 = cart-wide)
     * @param string $note
     * @return self
     */
    public function applyCoupon(string $code, float $discount, int $productId = 0, string $note = ''): self {
        $this->coupon = [
            'code' => $code,
            'discount' => $discount,
            'product_id' => $productId,
            'note' => $note,
        ];
        return $this;
    }

    /**
     * Remove coupon from the cart
     *
     * @return self
     */
    public function removeCoupon(): self {
        $this->coupon = null;

        // Also clear item-level coupon discounts
        foreach ($this->items as $productId => $item) {
            if ($item->getCoupon()) {
                $this->items[$productId] = $item->withoutCoupon();
            }
        }

        return $this;
    }

    /**
     * Get applied coupon
     *
     * @return array|null
     */
    public function getCoupon(): ?array {
        return $this->coupon;
    }

    /**
     * Get coupon code
     *
     * @return string
     */
    public function getCouponCode(): string {
        return $this->coupon['code'] ?? '';
    }

    /**
     * Get coupon discount amount
     *
     * @return float
     */
    public function getCouponDiscount(): float {
        return $this->coupon['discount'] ?? 0.0;
    }

    // -------------------------------------------------------------------------
    // Tax Management
    // -------------------------------------------------------------------------

    /**
     * Set tax amount
     *
     * @param float $tax
     * @return self
     */
    public function setTax(float $tax): self {
        $this->tax = $tax;
        return $this;
    }

    /**
     * Get tax amount
     *
     * @return float
     */
    public function getTax(): float {
        return $this->tax;
    }

    /**
     * Calculate tax based on rate and location
     *
     * @param float $rate Tax rate percentage
     * @return float
     */
    public function calculateTax(float $rate): float {
        if ($rate <= 0) {
            $this->tax = 0.0;
            return 0.0;
        }

        $taxableAmount = $this->getSubtotal() - $this->getTotalDiscount();
        $this->tax = ($taxableAmount * $rate) / 100;

        return $this->tax;
    }

    // -------------------------------------------------------------------------
    // Locking (for subscription flows)
    // -------------------------------------------------------------------------

    /**
     * Lock the cart
     *
     * @return self
     */
    public function lock(): self {
        $this->locked = true;
        return $this;
    }

    /**
     * Unlock the cart
     *
     * @return self
     */
    public function unlock(): self {
        $this->locked = false;
        $this->recurring = null;
        return $this;
    }

    /**
     * Check if cart is locked
     *
     * @return bool
     */
    public function isLocked(): bool {
        return $this->locked;
    }

    /**
     * Set recurring flag
     *
     * @param bool $recurring
     * @return self
     */
    public function setRecurring(bool $recurring): self {
        $this->recurring = $recurring;
        return $this;
    }

    /**
     * Check if recurring
     *
     * @return bool|null
     */
    public function isRecurring(): ?bool {
        return $this->recurring;
    }

    // -------------------------------------------------------------------------
    // Totals Calculation
    // -------------------------------------------------------------------------

    /**
     * Get subtotal (sum of all item line totals before cart-level discounts)
     *
     * @return float
     */
    public function getSubtotal(): float {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $subtotal += $item->getSubtotal();
        }
        return $subtotal;
    }

    /**
     * Get total role discount from all items
     *
     * @return float
     */
    public function getTotalRoleDiscount(): float {
        $discount = 0.0;
        foreach ($this->items as $item) {
            $discount += $item->getRoleDiscount();
        }
        return $discount;
    }

    /**
     * Get total item-level coupon discount
     *
     * @return float
     */
    public function getTotalItemCouponDiscount(): float {
        $discount = 0.0;
        foreach ($this->items as $item) {
            $discount += $item->getCouponDiscount();
        }
        return $discount;
    }

    /**
     * Get total discount (role + coupon)
     *
     * @return float
     */
    public function getTotalDiscount(): float {
        return $this->getTotalRoleDiscount() + $this->getCouponDiscount() + $this->getTotalItemCouponDiscount();
    }

    /**
     * Get cart total after all discounts
     *
     * @param bool $includeTax
     * @return float
     */
    public function getTotal(bool $includeTax = false): float {
        $total = $this->getSubtotal();
        $total -= $this->getTotalRoleDiscount();
        $total -= $this->getTotalItemCouponDiscount();
        $total -= $this->getCouponDiscount();

        if ($includeTax) {
            $total += $this->tax;
        }

        return max(0, $total);
    }

    /**
     * Get cart total with tax
     *
     * @return float
     */
    public function getTotalWithTax(): float {
        return $this->getTotal(true);
    }

    // -------------------------------------------------------------------------
    // Cart Properties
    // -------------------------------------------------------------------------

    /**
     * Get cart ID
     *
     * @return string
     */
    public function getCartId(): string {
        return $this->cartId;
    }

    /**
     * Set cart ID
     *
     * @param string $cartId
     * @return self
     */
    public function setCartId(string $cartId): self {
        $this->cartId = $cartId;
        return $this;
    }

    /**
     * Check if cart is empty
     *
     * @return bool
     */
    public function isEmpty(): bool {
        return empty($this->items);
    }

    /**
     * Check if cart contains only dynamic items
     *
     * @return bool
     */
    public function isDynamicOnly(): bool {
        if ($this->isEmpty()) {
            return false;
        }

        foreach ($this->items as $item) {
            if (!$item->isDynamic()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if cart contains any dynamic items
     *
     * @return bool
     */
    public function hasDynamicItems(): bool {
        foreach ($this->items as $item) {
            if ($item->isDynamic()) {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Countable & IteratorAggregate Implementation
    // -------------------------------------------------------------------------

    /**
     * Get item count
     *
     * @return int
     */
    public function count(): int {
        return count($this->items);
    }

    /**
     * Get total quantity of all items
     *
     * @return int
     */
    public function getTotalQuantity(): int {
        $quantity = 0;
        foreach ($this->items as $item) {
            $quantity += $item->getQuantity();
        }
        return $quantity;
    }

    /**
     * Get iterator for items
     *
     * @return \ArrayIterator<int, CartItem>
     */
    public function getIterator(): \ArrayIterator {
        return new \ArrayIterator($this->items);
    }

    // -------------------------------------------------------------------------
    // Product IDs
    // -------------------------------------------------------------------------

    /**
     * Get all product IDs in cart
     *
     * @return int[]
     */
    public function getProductIds(): array {
        return array_keys($this->items);
    }

    /**
     * Get product IDs for standard (non-dynamic) items
     *
     * @return int[]
     */
    public function getStandardProductIds(): array {
        $ids = [];
        foreach ($this->items as $productId => $item) {
            if (!$item->isDynamic()) {
                $ids[] = $productId;
            }
        }
        return $ids;
    }
}
