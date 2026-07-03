<?php
/**
 * Coupon Entity Class
 *
 * Represents a coupon/discount code with validation logic.
 *
 * @package WPDMPP\Coupon
 * @since 7.0.0
 */

namespace WPDMPP\Coupon;

defined('ABSPATH') || exit;

class Coupon {

    /**
     * Discount types
     */
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    /**
     * Validation error codes
     */
    public const ERROR_NOT_FOUND = 'not_found';
    public const ERROR_EXPIRED = 'expired';
    public const ERROR_USAGE_LIMIT = 'usage_limit_reached';
    public const ERROR_MIN_AMOUNT = 'min_amount_not_met';
    public const ERROR_MAX_AMOUNT = 'max_amount_exceeded';
    public const ERROR_PRODUCT_NOT_IN_CART = 'product_not_in_cart';
    public const ERROR_EMAIL_NOT_ALLOWED = 'email_not_allowed';
    public const ERROR_ALREADY_APPLIED = 'already_applied';

    /**
     * Database ID
     *
     * @var int|null
     */
    private ?int $id = null;

    /**
     * Coupon code
     *
     * @var string
     */
    private string $code;

    /**
     * Description
     *
     * @var string
     */
    private string $description = '';

    /**
     * Discount type (percent or fixed)
     *
     * @var string
     */
    private string $type = self::TYPE_PERCENT;

    /**
     * Discount amount (percentage or fixed amount)
     *
     * @var float
     */
    private float $discount = 0.0;

    /**
     * Minimum order amount required
     *
     * @var float
     */
    private float $minOrderAmount = 0.0;

    /**
     * Maximum order amount allowed
     *
     * @var float
     */
    private float $maxOrderAmount = 0.0;

    /**
     * Product ID (0 for cart-wide coupon)
     *
     * @var int
     */
    private int $productId = 0;

    /**
     * Allowed emails (comma-separated or array)
     *
     * @var array
     */
    private array $allowedEmails = [];

    /**
     * Expiration date (unix timestamp, 0 for never)
     *
     * @var int
     */
    private int $expireDate = 0;

    /**
     * Usage limit (0 for unlimited)
     *
     * @var int
     */
    private int $usageLimit = 0;

    /**
     * Number of times used
     *
     * @var int
     */
    private int $used = 0;

    /**
     * Auto-apply flag
     *
     * @var bool
     */
    private bool $autoApply = false;

    /**
     * Constructor
     *
     * @param string $code Coupon code
     */
    public function __construct(string $code = '') {
        $this->code = strtoupper(trim($code));
    }

    /**
     * Create from database row
     *
     * @param object|array $row Database row
     * @return self
     */
    public static function fromDatabase($row): self {
        $row = (array) $row;

        $coupon = new self($row['code'] ?? '');
        $coupon->id = isset($row['ID']) ? (int) $row['ID'] : null;
        $coupon->description = $row['description'] ?? '';
        $coupon->type = $row['type'] ?? self::TYPE_PERCENT;
        $coupon->discount = (float) ($row['discount'] ?? 0);
        $coupon->minOrderAmount = (float) ($row['min_order_amount'] ?? 0);
        $coupon->maxOrderAmount = (float) ($row['max_order_amount'] ?? 0);
        $coupon->productId = (int) ($row['product'] ?? 0);
        $coupon->expireDate = (int) ($row['expire_date'] ?? 0);
        $coupon->usageLimit = (int) ($row['usage_limit'] ?? 0);
        $coupon->used = (int) ($row['used'] ?? 0);
        $coupon->autoApply = (bool) ($row['auto_apply'] ?? false);

        // Parse allowed emails
        $allowedEmails = $row['allowed_emails'] ?? '';
        if (is_string($allowedEmails) && !empty($allowedEmails)) {
            $coupon->allowedEmails = array_map('trim', explode(',', $allowedEmails));
        } elseif (is_array($allowedEmails)) {
            $coupon->allowedEmails = $allowedEmails;
        }

        return $coupon;
    }

    /**
     * Create a new coupon
     *
     * @param array $data Coupon data
     * @return self
     */
    public static function create(array $data): self {
        $coupon = new self($data['code'] ?? '');
        $coupon->description = $data['description'] ?? '';
        $coupon->type = $data['type'] ?? self::TYPE_PERCENT;
        $coupon->discount = (float) ($data['discount'] ?? 0);
        $coupon->minOrderAmount = (float) ($data['min_order_amount'] ?? 0);
        $coupon->maxOrderAmount = (float) ($data['max_order_amount'] ?? 0);
        $coupon->productId = (int) ($data['product'] ?? ($data['product_id'] ?? 0));
        $coupon->usageLimit = (int) ($data['usage_limit'] ?? 0);
        $coupon->autoApply = (bool) ($data['auto_apply'] ?? false);

        // Handle expire date
        if (!empty($data['expire_date'])) {
            if (is_numeric($data['expire_date'])) {
                $coupon->expireDate = (int) $data['expire_date'];
            } else {
                $coupon->expireDate = strtotime($data['expire_date']);
            }
        }

        // Handle allowed emails
        if (!empty($data['allowed_emails'])) {
            if (is_string($data['allowed_emails'])) {
                $coupon->allowedEmails = array_map('trim', explode(',', $data['allowed_emails']));
            } else {
                $coupon->allowedEmails = (array) $data['allowed_emails'];
            }
        }

        return $coupon;
    }

    // ==================
    // Getters
    // ==================

    public function getId(): ?int {
        return $this->id;
    }

    public function getCode(): string {
        return $this->code;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getType(): string {
        return $this->type;
    }

    public function isPercentage(): bool {
        return $this->type === self::TYPE_PERCENT;
    }

    public function isFixed(): bool {
        return $this->type === self::TYPE_FIXED;
    }

    public function getDiscount(): float {
        return $this->discount;
    }

    public function getMinOrderAmount(): float {
        return $this->minOrderAmount;
    }

    public function getMaxOrderAmount(): float {
        return $this->maxOrderAmount;
    }

    public function getProductId(): int {
        return $this->productId;
    }

    public function isProductSpecific(): bool {
        return $this->productId > 0;
    }

    public function isCartWide(): bool {
        return $this->productId === 0;
    }

    public function getAllowedEmails(): array {
        return $this->allowedEmails;
    }

    public function hasEmailRestriction(): bool {
        return !empty($this->allowedEmails);
    }

    public function getExpireDate(): int {
        return $this->expireDate;
    }

    public function getFormattedExpireDate(string $format = ''): string {
        if ($this->expireDate === 0) {
            return __('Never', 'wpdm-premium-packages');
        }
        if (empty($format)) {
            $format = get_option('date_format');
        }
        return wp_date($format, $this->expireDate);
    }

    public function isExpired(): bool {
        return $this->expireDate > 0 && $this->expireDate < time();
    }

    public function getUsageLimit(): int {
        return $this->usageLimit;
    }

    public function hasUsageLimit(): bool {
        return $this->usageLimit > 0;
    }

    public function getUsed(): int {
        return $this->used;
    }

    public function getRemainingUses(): int {
        if (!$this->hasUsageLimit()) {
            return PHP_INT_MAX;
        }
        return max(0, $this->usageLimit - $this->used);
    }

    public function isUsageLimitReached(): bool {
        return $this->hasUsageLimit() && $this->used >= $this->usageLimit;
    }

    public function hasAutoApply(): bool {
        return $this->autoApply;
    }

    // ==================
    // Setters
    // ==================

    public function setId(int $id): self {
        $this->id = $id;
        return $this;
    }

    public function setCode(string $code): self {
        $this->code = strtoupper(trim($code));
        return $this;
    }

    public function setDescription(string $description): self {
        $this->description = $description;
        return $this;
    }

    public function setType(string $type): self {
        if (in_array($type, [self::TYPE_PERCENT, self::TYPE_FIXED])) {
            $this->type = $type;
        }
        return $this;
    }

    public function setDiscount(float $discount): self {
        $this->discount = max(0, $discount);
        return $this;
    }

    public function setMinOrderAmount(float $amount): self {
        $this->minOrderAmount = max(0, $amount);
        return $this;
    }

    public function setMaxOrderAmount(float $amount): self {
        $this->maxOrderAmount = max(0, $amount);
        return $this;
    }

    public function setProductId(int $productId): self {
        $this->productId = max(0, $productId);
        return $this;
    }

    public function setAllowedEmails(array $emails): self {
        $this->allowedEmails = array_map('sanitize_email', $emails);
        return $this;
    }

    public function setExpireDate(int $timestamp): self {
        $this->expireDate = max(0, $timestamp);
        return $this;
    }

    public function setUsageLimit(int $limit): self {
        $this->usageLimit = max(0, $limit);
        return $this;
    }

    public function setUsed(int $used): self {
        $this->used = max(0, $used);
        return $this;
    }

    public function setAutoApply(bool $autoApply): self {
        $this->autoApply = $autoApply;
        return $this;
    }

    // ==================
    // Validation
    // ==================

    /**
     * Validate coupon against cart/order
     *
     * @param float       $cartTotal   Cart subtotal
     * @param array       $cartItems   Cart items (product_id => item_data)
     * @param string|null $email       Customer email
     * @param int|null    $productId   Specific product to validate against
     * @return array ['valid' => bool, 'error' => string|null, 'discount' => float]
     */
    public function validate(float $cartTotal, array $cartItems = [], ?string $email = null, ?int $productId = null): array {
        // Check if expired
        if ($this->isExpired()) {
            return [
                'valid' => false,
                'error' => self::ERROR_EXPIRED,
                'message' => __('This coupon has expired.', 'wpdm-premium-packages'),
                'discount' => 0,
            ];
        }

        // Check usage limit
        if ($this->isUsageLimitReached()) {
            return [
                'valid' => false,
                'error' => self::ERROR_USAGE_LIMIT,
                'message' => __('This coupon has reached its usage limit.', 'wpdm-premium-packages'),
                'discount' => 0,
            ];
        }

        // Check minimum order amount
        if ($this->minOrderAmount > 0 && $cartTotal < $this->minOrderAmount) {
            return [
                'valid' => false,
                'error' => self::ERROR_MIN_AMOUNT,
                'message' => sprintf(
                    __('Minimum order amount of %s required.', 'wpdm-premium-packages'),
                    function_exists('wpdmpp_price_format') ? wpdmpp_price_format($this->minOrderAmount) : '$' . number_format($this->minOrderAmount, 2)
                ),
                'discount' => 0,
            ];
        }

        // Check maximum order amount
        if ($this->maxOrderAmount > 0 && $cartTotal > $this->maxOrderAmount) {
            return [
                'valid' => false,
                'error' => self::ERROR_MAX_AMOUNT,
                'message' => sprintf(
                    __('Maximum order amount of %s exceeded.', 'wpdm-premium-packages'),
                    function_exists('wpdmpp_price_format') ? wpdmpp_price_format($this->maxOrderAmount) : '$' . number_format($this->maxOrderAmount, 2)
                ),
                'discount' => 0,
            ];
        }

        // Check product restriction
        if ($this->isProductSpecific()) {
            if (!isset($cartItems[$this->productId])) {
                return [
                    'valid' => false,
                    'error' => self::ERROR_PRODUCT_NOT_IN_CART,
                    'message' => __('This coupon is not valid for items in your cart.', 'wpdm-premium-packages'),
                    'discount' => 0,
                ];
            }
        }

        // Check email restriction. A restricted coupon with no known email must
        // NOT pass — treating null as "skip" would let anyone use it.
        if ($this->hasEmailRestriction()) {
            if ($email === null || trim($email) === '') {
                return [
                    'valid' => false,
                    'error' => self::ERROR_EMAIL_NOT_ALLOWED,
                    'message' => __('This coupon is restricted to specific customers. Please enter your billing email first.', 'wpdm-premium-packages'),
                    'discount' => 0,
                ];
            }
            $emailLower = strtolower(trim($email));
            $allowed = array_map('strtolower', array_map('trim', $this->allowedEmails));
            if (!in_array($emailLower, $allowed, true)) {
                return [
                    'valid' => false,
                    'error' => self::ERROR_EMAIL_NOT_ALLOWED,
                    'message' => __('This coupon is not valid for your email address.', 'wpdm-premium-packages'),
                    'discount' => 0,
                ];
            }
        }

        // Calculate discount
        $discount = $this->calculateDiscount($cartTotal, $cartItems, $productId);

        return [
            'valid' => true,
            'error' => null,
            'message' => '',
            'discount' => $discount,
        ];
    }

    /**
     * Calculate discount amount
     *
     * @param float    $cartTotal Cart subtotal
     * @param array    $cartItems Cart items
     * @param int|null $productId Specific product ID
     * @return float
     */
    public function calculateDiscount(float $cartTotal, array $cartItems = [], ?int $productId = null): float {
        // Determine the amount to apply discount to
        $applicableAmount = $cartTotal;

        if ($this->isProductSpecific() && isset($cartItems[$this->productId])) {
            $item = $cartItems[$this->productId];
            $price = (float) ($item['price'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 1);
            $applicableAmount = $price * $quantity;
        }

        // Calculate discount
        $discount = 0.0;

        if ($this->isFixed()) {
            if ($this->isProductSpecific() && isset($cartItems[$this->productId])) {
                // Fixed discount per item
                $quantity = (int) ($cartItems[$this->productId]['quantity'] ?? 1);
                $discount = $this->discount * $quantity;
            } else {
                // Fixed discount for cart
                $discount = $this->discount;
            }
        } else {
            // Percentage discount
            $discount = $applicableAmount * ($this->discount / 100);
        }

        // Don't exceed the applicable amount
        if ($discount > $applicableAmount) {
            $discount = $applicableAmount;
        }

        return round($discount, 2);
    }

    /**
     * Increment usage count
     *
     * @return self
     */
    public function incrementUsage(): self {
        $this->used++;
        return $this;
    }

    // ==================
    // Conversion
    // ==================

    /**
     * Convert to array for database storage
     *
     * @return array
     */
    public function toDatabase(): array {
        return [
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            'discount' => $this->discount,
            'min_order_amount' => $this->minOrderAmount,
            'max_order_amount' => $this->maxOrderAmount,
            'product' => $this->productId,
            'allowed_emails' => implode(',', $this->allowedEmails),
            'expire_date' => $this->expireDate,
            'usage_limit' => $this->usageLimit,
            'used' => $this->used,
            'auto_apply' => (int) $this->autoApply,
        ];
    }

    /**
     * Convert to array for API response
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            'type_label' => $this->isPercentage() ? __('Percentage', 'wpdm-premium-packages') : __('Fixed', 'wpdm-premium-packages'),
            'discount' => $this->discount,
            'discount_formatted' => $this->isPercentage() ? $this->discount . '%' : (function_exists('wpdmpp_price_format') ? wpdmpp_price_format($this->discount) : '$' . number_format($this->discount, 2)),
            'min_order_amount' => $this->minOrderAmount,
            'max_order_amount' => $this->maxOrderAmount,
            'product_id' => $this->productId,
            'is_product_specific' => $this->isProductSpecific(),
            'allowed_emails' => $this->allowedEmails,
            'has_email_restriction' => $this->hasEmailRestriction(),
            'expire_date' => $this->expireDate,
            'expire_date_formatted' => $this->getFormattedExpireDate(),
            'is_expired' => $this->isExpired(),
            'usage_limit' => $this->usageLimit,
            'has_usage_limit' => $this->hasUsageLimit(),
            'used' => $this->used,
            'remaining_uses' => $this->hasUsageLimit() ? $this->getRemainingUses() : null,
            'is_usage_limit_reached' => $this->isUsageLimitReached(),
            'auto_apply' => $this->autoApply,
        ];
    }

    /**
     * Get a summary string for display
     *
     * @return string
     */
    public function getSummary(): string {
        if ($this->isPercentage()) {
            return sprintf('%s%% off', $this->discount);
        }

        $formatted = function_exists('wpdmpp_price_format')
            ? wpdmpp_price_format($this->discount)
            : '$' . number_format($this->discount, 2);

        return sprintf('%s off', $formatted);
    }
}
