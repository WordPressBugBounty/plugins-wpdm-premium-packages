<?php
/**
 * Order Item Value Object
 *
 * Represents a single line item in an order.
 * This is an immutable value object.
 *
 * @package WPDMPP\Order
 * @since 7.0.0
 */

namespace WPDMPP\Order;

defined('ABSPATH') || exit;

class OrderItem {

    /**
     * Order item ID (database primary key)
     *
     * @var int|null
     */
    private ?int $id;

    /**
     * Order ID this item belongs to
     *
     * @var string
     */
    private string $orderId;

    /**
     * Product ID
     *
     * @var int
     */
    private int $productId;

    /**
     * Product type (default or dynamic)
     *
     * @var string
     */
    private string $productType;

    /**
     * Product name
     *
     * @var string
     */
    private string $productName;

    /**
     * Quantity
     *
     * @var int
     */
    private int $quantity;

    /**
     * Unit price
     *
     * @var float
     */
    private float $price;

    /**
     * License information
     *
     * @var array
     */
    private array $license;

    /**
     * Extra gigs/add-ons
     *
     * @var array
     */
    private array $extraGigs;

    /**
     * Coupon code applied
     *
     * @var string
     */
    private string $coupon;

    /**
     * Coupon discount amount
     *
     * @var float
     */
    private float $couponDiscount;

    /**
     * Role discount amount
     *
     * @var float
     */
    private float $roleDiscount;

    /**
     * Site commission
     *
     * @var float
     */
    private float $siteCommission;

    /**
     * Seller ID
     *
     * @var int
     */
    private int $sellerId;

    /**
     * Customer ID
     *
     * @var int
     */
    private int $customerId;

    /**
     * Item date
     *
     * @var string
     */
    private string $date;

    /**
     * Constructor
     *
     * @param int    $productId   Product ID
     * @param string $orderId     Order ID
     * @param array  $data        Item data
     */
    public function __construct(int $productId, string $orderId, array $data = []) {
        $this->productId = $productId;
        $this->orderId = $orderId;

        $this->id = isset($data['id']) ? (int) $data['id'] : null;
        $this->productType = $data['product_type'] ?? 'default';
        $this->productName = $data['product_name'] ?? '';
        $this->quantity = max(1, (int) ($data['quantity'] ?? 1));
        $this->price = (float) ($data['price'] ?? 0);

        // Handle license - can be serialized string or array
        $license = $data['license'] ?? [];
        if (is_string($license)) {
            $license = maybe_unserialize($license);
        }
        $this->license = is_array($license) ? $license : [];

        // Handle extra gigs - can be serialized string or array
        $extraGigs = $data['extra_gigs'] ?? [];
        if (is_string($extraGigs)) {
            $extraGigs = maybe_unserialize($extraGigs);
        }
        $this->extraGigs = is_array($extraGigs) ? $extraGigs : [];

        $this->coupon = $data['coupon'] ?? '';
        $this->couponDiscount = (float) ($data['coupon_discount'] ?? 0);
        $this->roleDiscount = (float) ($data['role_discount'] ?? 0);
        $this->siteCommission = (float) ($data['site_commission'] ?? 0);
        $this->sellerId = (int) ($data['sid'] ?? ($data['seller_id'] ?? 0));
        $this->customerId = (int) ($data['cid'] ?? ($data['customer_id'] ?? 0));
        $this->date = $data['date'] ?? '';
    }

    /**
     * Create from database row
     *
     * @param object|array $row Database row
     * @return self
     */
    public static function fromDatabase($row): self {
        $row = (array) $row;

        return new self(
            (int) $row['pid'],
            $row['oid'],
            [
                'id' => $row['ID'] ?? null,
                'product_type' => $row['product_type'] ?? 'default',
                'product_name' => $row['product_name'] ?? '',
                'quantity' => $row['quantity'] ?? 1,
                'price' => $row['price'] ?? 0,
                'license' => $row['license'] ?? [],
                'extra_gigs' => $row['extra_gigs'] ?? [],
                'coupon' => $row['coupon'] ?? '',
                'coupon_discount' => $row['coupon_discount'] ?? 0,
                'role_discount' => $row['role_discount'] ?? 0,
                'site_commission' => $row['site_commission'] ?? 0,
                'sid' => $row['sid'] ?? 0,
                'cid' => $row['cid'] ?? 0,
                'date' => $row['date'] ?? '',
            ]
        );
    }

    /**
     * Create from cart item data
     *
     * @param int    $productId Product ID
     * @param string $orderId   Order ID
     * @param array  $cartItem  Cart item data
     * @param int    $customerId Customer ID
     * @return self
     */
    public static function fromCartItem(int $productId, string $orderId, array $cartItem, int $customerId = 0): self {
        $productType = $cartItem['product_type'] ?? 'default';
        $sellerId = 0;

        if ($productType !== 'dynamic') {
            $product = get_post($productId);
            $sellerId = $product ? (int) $product->post_author : 0;
        }

        return new self($productId, $orderId, [
            'product_type' => $productType,
            'product_name' => $cartItem['product_name'] ?? get_the_title($productId),
            'quantity' => $cartItem['quantity'] ?? 1,
            'price' => $cartItem['price'] ?? 0,
            'license' => $cartItem['license'] ?? [],
            'extra_gigs' => $cartItem['extra_gigs'] ?? [],
            'coupon' => $cartItem['coupon'] ?? '',
            'coupon_discount' => $cartItem['coupon_amount'] ?? ($cartItem['coupon_discount'] ?? 0),
            'role_discount' => $cartItem['discount_amount'] ?? ($cartItem['role_discount'] ?? 0),
            'site_commission' => 0,
            'sid' => $sellerId,
            'cid' => $customerId,
            'date' => current_time('mysql'),
        ]);
    }

    /**
     * Get item ID
     *
     * @return int|null
     */
    public function getId(): ?int {
        return $this->id;
    }

    /**
     * Get order ID
     *
     * @return string
     */
    public function getOrderId(): string {
        return $this->orderId;
    }

    /**
     * Get product ID
     *
     * @return int
     */
    public function getProductId(): int {
        return $this->productId;
    }

    /**
     * Get product type
     *
     * @return string
     */
    public function getProductType(): string {
        return $this->productType;
    }

    /**
     * Get product name
     *
     * @return string
     */
    public function getProductName(): string {
        if (empty($this->productName) && $this->productType !== 'dynamic') {
            return get_the_title($this->productId);
        }
        return $this->productName;
    }

    /**
     * Get quantity
     *
     * @return int
     */
    public function getQuantity(): int {
        return $this->quantity;
    }

    /**
     * Get unit price
     *
     * @return float
     */
    public function getPrice(): float {
        return $this->price;
    }

    /**
     * Get license information
     *
     * @return array
     */
    public function getLicense(): array {
        return $this->license;
    }

    /**
     * Get license name
     *
     * @return string
     */
    public function getLicenseName(): string {
        if (isset($this->license['info']['name'])) {
            return $this->license['info']['name'];
        }
        if (isset($this->license['id'])) {
            return $this->license['id'];
        }
        return '';
    }

    /**
     * Get extra gigs/add-ons
     *
     * @return array
     */
    public function getExtraGigs(): array {
        return $this->extraGigs;
    }

    /**
     * Calculate gigs cost
     *
     * @return float
     */
    public function getGigsCost(): float {
        if (empty($this->extraGigs) || $this->productType === 'dynamic') {
            return 0.0;
        }

        if (class_exists('\WPDMPP\Product')) {
            $product = new \WPDMPP\Product($this->productId, $this->productType);
            return $product->gigsCost($this->extraGigs);
        }

        return 0.0;
    }

    /**
     * Get coupon code
     *
     * @return string
     */
    public function getCoupon(): string {
        return $this->coupon;
    }

    /**
     * Get coupon discount amount
     *
     * @return float
     */
    public function getCouponDiscount(): float {
        return $this->couponDiscount;
    }

    /**
     * Get role discount amount
     *
     * @return float
     */
    public function getRoleDiscount(): float {
        return $this->roleDiscount;
    }

    /**
     * Get total discount
     *
     * @return float
     */
    public function getTotalDiscount(): float {
        return $this->couponDiscount + $this->roleDiscount;
    }

    /**
     * Get site commission
     *
     * @return float
     */
    public function getSiteCommission(): float {
        return $this->siteCommission;
    }

    /**
     * Get seller ID
     *
     * @return int
     */
    public function getSellerId(): int {
        return $this->sellerId;
    }

    /**
     * Get customer ID
     *
     * @return int
     */
    public function getCustomerId(): int {
        return $this->customerId;
    }

    /**
     * Get item date
     *
     * @return string
     */
    public function getDate(): string {
        return $this->date;
    }

    /**
     * Get subtotal (price + gigs) * quantity
     *
     * @return float
     */
    public function getSubtotal(): float {
        return ($this->price + $this->getGigsCost()) * $this->quantity;
    }

    /**
     * Get line total (subtotal - discounts)
     *
     * @return float
     */
    public function getLineTotal(): float {
        return max(0, $this->getSubtotal() - $this->getTotalDiscount());
    }

    /**
     * Convert to array for database storage
     *
     * @return array
     */
    public function toDatabase(): array {
        $now = current_time('mysql');
        $timestamp = strtotime($this->date ?: $now);

        return [
            'oid' => $this->orderId,
            'pid' => $this->productId,
            'product_type' => $this->productType,
            'product_name' => $this->getProductName(),
            'license' => serialize($this->license),
            'quantity' => $this->quantity,
            'price' => $this->price,
            'extra_gigs' => serialize($this->extraGigs),
            'coupon' => $this->coupon,
            'coupon_discount' => $this->couponDiscount,
            'role_discount' => $this->roleDiscount,
            'site_commission' => $this->siteCommission,
            'date' => $this->date ?: $now,
            'year' => date('Y', $timestamp),
            'month' => date('m', $timestamp),
            'day' => date('d', $timestamp),
            'sid' => $this->sellerId,
            'cid' => $this->customerId,
        ];
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'product_type' => $this->productType,
            'product_name' => $this->getProductName(),
            'quantity' => $this->quantity,
            'price' => $this->price,
            'license' => $this->license,
            'license_name' => $this->getLicenseName(),
            'extra_gigs' => $this->extraGigs,
            'gigs_cost' => $this->getGigsCost(),
            'coupon' => $this->coupon,
            'coupon_discount' => $this->couponDiscount,
            'role_discount' => $this->roleDiscount,
            'total_discount' => $this->getTotalDiscount(),
            'site_commission' => $this->siteCommission,
            'seller_id' => $this->sellerId,
            'customer_id' => $this->customerId,
            'date' => $this->date,
            'subtotal' => $this->getSubtotal(),
            'line_total' => $this->getLineTotal(),
        ];
    }

    /**
     * Get download URLs for this item
     *
     * @return array
     */
    public function getDownloadUrls(): array {
        if ($this->productType === 'dynamic') {
            return [];
        }

        $urls = [];
        $files = get_post_meta($this->productId, '__wpdm_files', true);

        if (is_array($files) && class_exists('\WPDMPP\WPDMPremiumPackage')) {
            $baseUrl = \WPDMPP\WPDMPremiumPackage::customerDownloadURL($this->productId, $this->orderId);

            foreach ($files as $id => $filepath) {
                $filename = basename($filepath);
                $urls[$filename] = $baseUrl . "&ind={$id}";
            }
        }

        return $urls;
    }
}
