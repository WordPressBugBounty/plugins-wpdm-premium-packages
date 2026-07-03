<?php
/**
 * Cart Item Value Object
 *
 * Represents a single item in the shopping cart.
 * Immutable value object - use with* methods to create modified copies.
 *
 * @package WPDMPP\Cart
 * @since 7.0.0
 */

namespace WPDMPP\Cart;

defined('ABSPATH') || exit;

class CartItem {

    /**
     * Product ID
     * @var int
     */
    private int $productId;

    /**
     * Product name
     * @var string
     */
    private string $productName;

    /**
     * Product type (standard or dynamic)
     * @var string
     */
    private string $productType;

    /**
     * Quantity
     * @var int
     */
    private int $quantity;

    /**
     * Base price per unit
     * @var float
     */
    private float $price;

    /**
     * License information
     * @var array
     */
    private array $license;

    /**
     * Extra gigs/variations
     * @var array
     */
    private array $extraGigs;

    /**
     * Selected files
     * @var array
     */
    private array $files;

    /**
     * Role discount amount
     * @var float
     */
    private float $roleDiscount;

    /**
     * Coupon code applied
     * @var string
     */
    private string $coupon;

    /**
     * Coupon discount amount
     * @var float
     */
    private float $couponDiscount;

    /**
     * Additional item info (for dynamic items)
     * @var array
     */
    private array $info;

    /**
     * Create a new CartItem
     *
     * @param int $productId
     * @param array $data
     */
    public function __construct(int $productId, array $data = []) {
        $this->productId = $productId;
        $this->productName = $data['product_name'] ?? get_the_title($productId) ?: '';
        $this->productType = $data['product_type'] ?? 'standard';
        $this->quantity = max(1, (int) ($data['quantity'] ?? 1));
        $this->price = (float) ($data['price'] ?? 0);
        // Handle license as string (ID) or array
        $license = $data['license'] ?? [];
        if (is_string($license)) {
            $this->license = ['id' => $license];
        } else {
            $this->license = is_array($license) ? $license : [];
        }
        // Handle arrays that might be passed as strings
        $extraGigs = $data['extra_gigs'] ?? [];
        $this->extraGigs = is_array($extraGigs) ? $extraGigs : [];

        $files = $data['files'] ?? [];
        $this->files = is_array($files) ? $files : [];

        $this->roleDiscount = (float) ($data['role_discount'] ?? 0);
        $this->coupon = $data['coupon'] ?? '';
        $this->couponDiscount = (float) ($data['coupon_discount'] ?? 0);

        $info = $data['info'] ?? [];
        $this->info = is_array($info) ? $info : [];
    }

    /**
     * Create CartItem from legacy array format
     *
     * @param int $productId
     * @param array $legacyData
     * @return self
     */
    public static function fromLegacy(int $productId, array $legacyData): self {
        return new self($productId, [
            'product_name' => $legacyData['product_name'] ?? '',
            'product_type' => $legacyData['product_type'] ?? 'standard',
            'quantity' => $legacyData['quantity'] ?? 1,
            'price' => $legacyData['price'] ?? 0,
            'license' => $legacyData['license'] ?? [],
            'extra_gigs' => $legacyData['extra_gigs'] ?? [],
            'files' => $legacyData['files'] ?? [],
            'role_discount' => $legacyData['role_discount'] ?? 0,
            'coupon' => $legacyData['coupon'] ?? '',
            'coupon_discount' => $legacyData['coupon_discount'] ?? 0,
            'info' => $legacyData['info'] ?? [],
        ]);
    }

    /**
     * Convert to legacy array format
     *
     * @return array
     */
    public function toLegacy(): array {
        return [
            'pid' => $this->productId,
            'product_name' => $this->productName,
            'product_type' => $this->productType,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'license' => $this->license,
            'extra_gigs' => $this->extraGigs,
            'files' => $this->files,
            'role_discount' => $this->roleDiscount,
            'coupon' => $this->coupon,
            'coupon_discount' => $this->couponDiscount,
            'info' => $this->info,
        ];
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array {
        $gigsCost = $this->getGigsCost();
        return [
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_type' => $this->productType,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'license' => $this->license,
            'extra_gigs' => $this->extraGigs,
            'files' => $this->files,
            'role_discount' => $this->roleDiscount,
            'coupon' => $this->coupon,
            'coupon_discount' => $this->couponDiscount,
            'info' => $this->info,
            'unit_total' => $this->getUnitTotal(),
            'line_total' => $this->getLineTotal(),
            'gigs_cost' => $gigsCost,
            // Legacy keys for backward compatibility
            'prices' => $gigsCost,
            'discount_amount' => $this->roleDiscount,
            'coupon_amount' => $this->couponDiscount,
            'pid' => $this->productId,
            'name' => $this->productName,
        ];
    }

    // Getters

    public function getProductId(): int {
        return $this->productId;
    }

    public function getProductName(): string {
        return $this->productName;
    }

    public function getProductType(): string {
        return $this->productType;
    }

    public function getQuantity(): int {
        return $this->quantity;
    }

    public function getPrice(): float {
        return $this->price;
    }

    public function getLicense(): array {
        return $this->license;
    }

    public function getLicenseId(): string {
        return $this->license['id'] ?? '';
    }

    public function getLicenseName(): string {
        return $this->license['info']['name'] ?? '';
    }

    public function getExtraGigs(): array {
        return $this->extraGigs;
    }

    public function getFiles(): array {
        return $this->files;
    }

    public function getRoleDiscount(): float {
        return $this->roleDiscount;
    }

    public function getCoupon(): string {
        return $this->coupon;
    }

    public function getCouponDiscount(): float {
        return $this->couponDiscount;
    }

    public function getInfo(): array {
        return $this->info;
    }

    public function isDynamic(): bool {
        return $this->productType === 'dynamic';
    }

    /**
     * Calculate gigs/variations cost
     *
     * @return float
     */
    public function getGigsCost(): float {
        $cost = 0.0;
        foreach ($this->extraGigs as $gig) {
            $cost += (float) ($gig['option_price'] ?? 0);
        }
        return $cost;
    }

    /**
     * Get unit total (price + gigs cost)
     *
     * @return float
     */
    public function getUnitTotal(): float {
        return $this->price + $this->getGigsCost();
    }

    /**
     * Get line total before discounts
     *
     * @return float
     */
    public function getSubtotal(): float {
        return $this->getUnitTotal() * $this->quantity;
    }

    /**
     * Get line total after discounts
     *
     * @return float
     */
    public function getLineTotal(): float {
        $subtotal = $this->getSubtotal();
        $subtotal -= $this->roleDiscount;
        $subtotal -= $this->couponDiscount;
        return max(0, $subtotal);
    }

    /**
     * Get total discount amount
     *
     * @return float
     */
    public function getTotalDiscount(): float {
        return $this->roleDiscount + $this->couponDiscount;
    }

    // Immutable "with" methods for creating modified copies

    /**
     * Create copy with new quantity
     *
     * @param int $quantity
     * @return self
     */
    public function withQuantity(int $quantity): self {
        $data = $this->toLegacy();
        $data['quantity'] = max(1, $quantity);
        return new self($this->productId, $data);
    }

    /**
     * Create copy with coupon applied
     *
     * @param string $code
     * @param float $discount
     * @return self
     */
    public function withCoupon(string $code, float $discount): self {
        $data = $this->toLegacy();
        $data['coupon'] = $code;
        $data['coupon_discount'] = $discount;
        return new self($this->productId, $data);
    }

    /**
     * Create copy without coupon
     *
     * @return self
     */
    public function withoutCoupon(): self {
        $data = $this->toLegacy();
        $data['coupon'] = '';
        $data['coupon_discount'] = 0;
        return new self($this->productId, $data);
    }

    /**
     * Create copy with role discount
     *
     * @param float $discount
     * @return self
     */
    public function withRoleDiscount(float $discount): self {
        $data = $this->toLegacy();
        $data['role_discount'] = $discount;
        return new self($this->productId, $data);
    }

    /**
     * Get product permalink
     *
     * @return string
     */
    public function getPermalink(): string {
        if ($this->isDynamic()) {
            return '';
        }
        return get_permalink($this->productId) ?: '';
    }

    /**
     * Get product thumbnail URL
     *
     * @param string $size
     * @return string
     */
    public function getThumbnailUrl(string $size = 'thumbnail'): string {
        if ($this->isDynamic()) {
            return $this->info['image'] ?? '';
        }

        $thumbnailId = get_post_thumbnail_id($this->productId);
        if ($thumbnailId) {
            return wp_get_attachment_image_url($thumbnailId, $size) ?: '';
        }

        // Fallback to package icon
        $icon = get_post_meta($this->productId, '__wpdm_icon', true);
        return $icon ?: '';
    }
}
