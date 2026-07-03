<?php
/**
 * Order Entity Class
 *
 * Represents an order with its items and metadata.
 *
 * @package WPDMPP\Order
 * @since 7.0.0
 */

namespace WPDMPP\Order;

defined('ABSPATH') || exit;

class Order implements \Countable, \IteratorAggregate {

    /**
     * Order statuses
     */
    public const STATUS_PROCESSING = 'Processing';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_EXPIRED = 'Expired';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUS_REFUNDED = 'Refunded';
    public const STATUS_PENDING = 'Pending';

    /**
     * Payment statuses
     */
    public const PAYMENT_PROCESSING = 'Processing';
    public const PAYMENT_COMPLETED = 'Completed';
    public const PAYMENT_FAILED = 'Failed';
    public const PAYMENT_REFUNDED = 'Refunded';
    public const PAYMENT_PENDING = 'Pending';

    /**
     * Database ID
     *
     * @var int|null
     */
    private ?int $id = null;

    /**
     * Order ID (public identifier like WPDMPP65A4B3C2)
     *
     * @var string
     */
    private string $orderId;

    /**
     * Transaction ID from payment gateway
     *
     * @var string
     */
    private string $transactionId = '';

    /**
     * Order title
     *
     * @var string
     */
    private string $title = '';

    /**
     * User ID (0 for guests)
     *
     * @var int
     */
    private int $userId = 0;

    /**
     * Order date (unix timestamp)
     *
     * @var int
     */
    private int $date = 0;

    /**
     * Expiration date (unix timestamp, 0 for never)
     *
     * @var int
     */
    private int $expireDate = 0;

    /**
     * Auto-renew enabled
     *
     * @var bool
     */
    private bool $autoRenew = false;

    /**
     * Order items
     *
     * @var OrderItem[]
     */
    private array $items = [];

    /**
     * Product IDs (legacy support)
     *
     * @var array
     */
    private array $productIds = [];

    /**
     * Subtotal (before tax and discounts)
     *
     * @var float
     */
    private float $subtotal = 0.0;

    /**
     * Coupon discount amount
     *
     * @var float
     */
    private float $couponDiscount = 0.0;

    /**
     * Cart/role discount amount
     *
     * @var float
     */
    private float $cartDiscount = 0.0;

    /**
     * Tax amount
     *
     * @var float
     */
    private float $tax = 0.0;

    /**
     * Order total
     *
     * @var float
     */
    private float $total = 0.0;

    /**
     * Refund amount
     *
     * @var float
     */
    private float $refund = 0.0;

    /**
     * Order status
     *
     * @var string
     */
    private string $orderStatus = self::STATUS_PROCESSING;

    /**
     * Payment status
     *
     * @var string
     */
    private string $paymentStatus = self::PAYMENT_PROCESSING;

    /**
     * Payment method identifier
     *
     * @var string
     */
    private string $paymentMethod = '';

    /**
     * Coupon code
     *
     * @var string
     */
    private string $couponCode = '';

    /**
     * Currency data
     *
     * @var array
     */
    private array $currency = [];

    /**
     * Billing information
     *
     * @var array
     */
    private array $billingInfo = [];

    /**
     * Cart data (legacy support)
     *
     * @var array
     */
    private array $cartData = [];

    /**
     * Download enabled
     *
     * @var bool
     */
    private bool $download = false;

    /**
     * Customer IP address
     *
     * @var string
     */
    private string $ip = '';

    /**
     * IPN data
     *
     * @var string
     */
    private string $ipn = '';

    /**
     * Order notes
     *
     * @var array
     */
    private array $notes = [];

    /**
     * Meta data
     *
     * @var array
     */
    private array $metaData = [];

    /**
     * Constructor
     *
     * @param string $orderId Order ID
     */
    public function __construct(string $orderId = '') {
        if (empty($orderId)) {
            $this->orderId = $this->generateOrderId();
        } else {
            $this->orderId = $orderId;
        }

        $this->date = time();
        $this->currency = [
            'sign' => function_exists('wpdmpp_currency_sign') ? wpdmpp_currency_sign() : '$',
            'code' => function_exists('wpdmpp_currency_code') ? wpdmpp_currency_code() : 'USD',
        ];
    }

    /**
     * Generate a unique order ID
     *
     * @return string
     */
    private function generateOrderId(): string {
        $prefix = 'WPDMPP';
        if (function_exists('get_wpdmpp_option')) {
            $prefix = strtoupper(get_wpdmpp_option('order_id_prefix', 'WPDMPP'));
        }
        return $prefix . strtoupper(uniqid());
    }

    /**
     * Create from database row
     *
     * @param object|array $row Database row
     * @return self
     */
    public static function fromDatabase($row): self {
        $row = (array) $row;

        $order = new self($row['order_id']);
        $order->id = isset($row['ID']) ? (int) $row['ID'] : null;
        $order->transactionId = $row['trans_id'] ?? '';
        $order->title = $row['title'] ?? '';
        $order->userId = (int) ($row['uid'] ?? 0);
        $order->date = (int) ($row['date'] ?? 0);
        $order->expireDate = (int) ($row['expire_date'] ?? 0);
        $order->autoRenew = (bool) ($row['auto_renew'] ?? false);

        // Handle serialized product IDs
        $items = $row['items'] ?? '';
        if (is_string($items)) {
            $items = maybe_unserialize($items);
        }
        $order->productIds = is_array($items) ? $items : [];

        $order->subtotal = (float) ($row['subtotal'] ?? 0);
        $order->couponDiscount = (float) ($row['coupon_discount'] ?? 0);
        $order->cartDiscount = (float) ($row['cart_discount'] ?? 0);
        $order->tax = (float) ($row['tax'] ?? 0);
        $order->total = (float) ($row['total'] ?? 0);
        $order->refund = (float) ($row['refund'] ?? 0);

        $order->orderStatus = $row['order_status'] ?? self::STATUS_PROCESSING;
        $order->paymentStatus = $row['payment_status'] ?? self::PAYMENT_PROCESSING;
        $order->paymentMethod = $row['payment_method'] ?? '';
        $order->couponCode = $row['coupon_code'] ?? '';

        // Handle serialized currency
        $currency = $row['currency'] ?? '';
        if (is_string($currency)) {
            $currency = maybe_unserialize($currency);
        }
        $order->currency = is_array($currency) ? $currency : ['sign' => '$', 'code' => 'USD'];

        // Handle serialized billing info
        $billing = $row['billing_info'] ?? '';
        if (is_string($billing)) {
            $billing = maybe_unserialize($billing);
        }
        $order->billingInfo = is_array($billing) ? $billing : [];

        // Handle serialized cart data
        $cartData = $row['cart_data'] ?? '';
        if (is_string($cartData)) {
            $cartData = maybe_unserialize($cartData);
        }
        $order->cartData = is_array($cartData) ? $cartData : [];

        $order->download = (bool) ($row['download'] ?? false);
        $order->ip = $row['IP'] ?? '';
        $order->ipn = $row['ipn'] ?? '';

        // Handle serialized order notes
        $notes = $row['order_notes'] ?? '';
        if (is_string($notes)) {
            $notes = maybe_unserialize($notes);
        }
        $order->notes = is_array($notes) ? $notes : [];

        // Handle serialized meta data
        $metaData = $row['meta_data'] ?? '';
        if (is_string($metaData)) {
            $metaData = maybe_unserialize($metaData);
        }
        $order->metaData = is_array($metaData) ? $metaData : [];

        return $order;
    }

    /**
     * Create from cart
     *
     * @param array $cartItems Cart items array
     * @param array $billingInfo Billing information
     * @param string $paymentMethod Payment method
     * @return self
     */
    public static function fromCart(array $cartItems, array $billingInfo = [], string $paymentMethod = ''): self {
        $order = new self();

        // Set order title
        $orderTitle = function_exists('get_wpdmpp_option') ? get_wpdmpp_option('order_title') : 'Order #{{ORDER_ID}}';
        $items = array_values($cartItems);
        $firstItem = array_shift($items);
        $productName = $firstItem['product_name'] ?? '';
        $orderTitle = str_replace(['{{PRODUCT_NAME}}', '{{ORDER_ID}}'], [$productName, $order->orderId], $orderTitle);
        $order->title = $orderTitle;

        // Set user
        $order->userId = get_current_user_id();

        // Set payment method
        $order->paymentMethod = $paymentMethod;

        // Set billing info
        $order->billingInfo = $billingInfo;

        // Store cart data for legacy compatibility
        $order->cartData = $cartItems;
        $order->productIds = array_keys($cartItems);

        // Set IP
        if (class_exists('\WPDM\__\__')) {
            $order->ip = \WPDM\__\__::get_client_ip();
        } else {
            $order->ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        // Convert cart items to order items
        foreach ($cartItems as $productId => $itemData) {
            $orderItem = OrderItem::fromCartItem($productId, $order->orderId, $itemData, $order->userId);
            $order->items[$productId] = $orderItem;
        }

        // Calculate totals
        $order->recalculateTotals();

        return $order;
    }

    /**
     * Recalculate order totals from items
     *
     * @return void
     */
    public function recalculateTotals(): void {
        $subtotal = 0.0;
        $couponDiscount = 0.0;
        $roleDiscount = 0.0;

        foreach ($this->items as $item) {
            $subtotal += $item->getSubtotal();
            $couponDiscount += $item->getCouponDiscount();
            $roleDiscount += $item->getRoleDiscount();
        }

        $this->subtotal = $subtotal;
        $this->couponDiscount = $couponDiscount;
        $this->cartDiscount = $roleDiscount;
        $this->total = $subtotal - $couponDiscount - $roleDiscount + $this->tax;
    }

    // ==================
    // Getters
    // ==================

    public function getId(): ?int {
        return $this->id;
    }

    public function getOrderId(): string {
        return $this->orderId;
    }

    public function getTransactionId(): string {
        return $this->transactionId;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function getUserId(): int {
        return $this->userId;
    }

    public function getDate(): int {
        return $this->date;
    }

    public function getFormattedDate(string $format = ''): string {
        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }
        return $this->date > 0 ? wp_date($format, $this->date) : '';
    }

    public function getExpireDate(): int {
        return $this->expireDate;
    }

    public function getFormattedExpireDate(string $format = ''): string {
        if (empty($format)) {
            $format = get_option('date_format');
        }
        return $this->expireDate > 0 ? wp_date($format, $this->expireDate) : __('Never', 'wpdm-premium-packages');
    }

    public function isExpired(): bool {
        return $this->expireDate > 0 && $this->expireDate < time();
    }

    public function hasAutoRenew(): bool {
        return $this->autoRenew;
    }

    /**
     * @return OrderItem[]
     */
    public function getItems(): array {
        return $this->items;
    }

    public function getItem(int $productId): ?OrderItem {
        return $this->items[$productId] ?? null;
    }

    public function hasItem(int $productId): bool {
        return isset($this->items[$productId]);
    }

    public function getProductIds(): array {
        return array_keys($this->items) ?: $this->productIds;
    }

    public function getSubtotal(): float {
        return $this->subtotal;
    }

    public function getCouponDiscount(): float {
        return $this->couponDiscount;
    }

    public function getCartDiscount(): float {
        return $this->cartDiscount;
    }

    public function getTotalDiscount(): float {
        return $this->couponDiscount + $this->cartDiscount;
    }

    public function getTax(): float {
        return $this->tax;
    }

    public function getTotal(): float {
        return $this->total;
    }

    public function getRefund(): float {
        return $this->refund;
    }

    public function getOrderStatus(): string {
        return $this->orderStatus;
    }

    public function getPaymentStatus(): string {
        return $this->paymentStatus;
    }

    public function getPaymentMethod(): string {
        return $this->paymentMethod;
    }

    public function getPaymentMethodLabel(): string {
        return str_replace(['Wpdm_', 'WPDM_'], '', $this->paymentMethod);
    }

    public function getCouponCode(): string {
        return $this->couponCode;
    }

    public function getCurrency(): array {
        return $this->currency;
    }

    public function getCurrencySign(): string {
        return $this->currency['sign'] ?? '$';
    }

    public function getCurrencyCode(): string {
        return $this->currency['code'] ?? 'USD';
    }

    public function getBillingInfo(): array {
        return $this->billingInfo;
    }

    public function getBillingEmail(): string {
        return $this->billingInfo['order_email'] ?? ($this->billingInfo['email'] ?? '');
    }

    public function getBillingName(): string {
        $firstName = $this->billingInfo['first_name'] ?? '';
        $lastName = $this->billingInfo['last_name'] ?? '';
        return trim("{$firstName} {$lastName}");
    }

    public function getCartData(): array {
        return $this->cartData;
    }

    public function canDownload(): bool {
        return $this->download || $this->orderStatus === self::STATUS_COMPLETED;
    }

    public function getAutoRenew(): bool {
        return $this->autoRenew;
    }

    public function getIp(): string {
        return $this->ip;
    }

    public function getIpn(): string {
        return $this->ipn;
    }

    public function getNotes(): array {
        return $this->notes;
    }

    public function getMeta(string $key, $default = null) {
        return $this->metaData[$key] ?? $default;
    }

    public function getAllMeta(): array {
        return $this->metaData;
    }

    // ==================
    // Setters
    // ==================

    public function setId(int $id): self {
        $this->id = $id;
        return $this;
    }

    public function setTransactionId(string $transactionId): self {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function setTitle(string $title): self {
        $this->title = $title;
        return $this;
    }

    public function setUserId(int $userId): self {
        $this->userId = $userId;
        return $this;
    }

    public function setDate(int $date): self {
        $this->date = $date;
        return $this;
    }

    public function setExpireDate(int $expireDate): self {
        $this->expireDate = $expireDate;
        return $this;
    }

    public function setAutoRenew(bool $autoRenew): self {
        $this->autoRenew = $autoRenew;
        return $this;
    }

    public function addItem(OrderItem $item): self {
        $this->items[$item->getProductId()] = $item;
        return $this;
    }

    public function removeItem(int $productId): self {
        unset($this->items[$productId]);
        return $this;
    }

    public function setItems(array $items): self {
        $this->items = [];
        foreach ($items as $item) {
            if ($item instanceof OrderItem) {
                $this->items[$item->getProductId()] = $item;
            }
        }
        return $this;
    }

    public function setSubtotal(float $subtotal): self {
        $this->subtotal = $subtotal;
        return $this;
    }

    public function setCouponDiscount(float $discount): self {
        $this->couponDiscount = $discount;
        return $this;
    }

    public function setCartDiscount(float $discount): self {
        $this->cartDiscount = $discount;
        return $this;
    }

    public function setTax(float $tax): self {
        $this->tax = $tax;
        return $this;
    }

    public function setTotal(float $total): self {
        $this->total = $total;
        return $this;
    }

    public function setRefund(float $refund): self {
        $this->refund = $refund;
        return $this;
    }

    public function setOrderStatus(string $status): self {
        $this->orderStatus = $status;
        return $this;
    }

    public function setPaymentStatus(string $status): self {
        $this->paymentStatus = $status;
        return $this;
    }

    public function setPaymentMethod(string $method): self {
        $this->paymentMethod = $method;
        return $this;
    }

    public function setCouponCode(string $code): self {
        $this->couponCode = $code;
        return $this;
    }

    public function setCurrency(array $currency): self {
        $this->currency = $currency;
        return $this;
    }

    public function setBillingInfo(array $billingInfo): self {
        $this->billingInfo = $billingInfo;
        return $this;
    }

    public function setCartData(array $cartData): self {
        $this->cartData = $cartData;
        return $this;
    }

    public function setDownload(bool $download): self {
        $this->download = $download;
        return $this;
    }

    public function setIp(string $ip): self {
        $this->ip = $ip;
        return $this;
    }

    public function setIpn(string $ipn): self {
        $this->ipn = $ipn;
        return $this;
    }

    public function setNotes(array $notes): self {
        $this->notes = $notes;
        return $this;
    }

    public function addNote(string $note, string $author = ''): self {
        $this->notes[] = [
            'note' => $note,
            'author' => $author ?: wp_get_current_user()->display_name,
            'date' => time(),
        ];
        return $this;
    }

    public function setMeta(string $key, $value): self {
        $this->metaData[$key] = $value;
        return $this;
    }

    public function setAllMeta(array $metaData): self {
        $this->metaData = $metaData;
        return $this;
    }

    // ==================
    // Status methods
    // ==================

    public function isProcessing(): bool {
        return $this->orderStatus === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool {
        return $this->orderStatus === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool {
        return $this->orderStatus === self::STATUS_CANCELLED;
    }

    public function isRefunded(): bool {
        return $this->orderStatus === self::STATUS_REFUNDED;
    }

    public function isPending(): bool {
        return $this->orderStatus === self::STATUS_PENDING;
    }

    public function isPaymentCompleted(): bool {
        return $this->paymentStatus === self::PAYMENT_COMPLETED;
    }

    public function canBeCompleted(): bool {
        return in_array($this->orderStatus, [self::STATUS_PROCESSING, self::STATUS_PENDING, self::STATUS_EXPIRED]);
    }

    public function canBeCancelled(): bool {
        return in_array($this->orderStatus, [self::STATUS_PROCESSING, self::STATUS_PENDING]);
    }

    public function canBeRefunded(): bool {
        return $this->orderStatus === self::STATUS_COMPLETED && $this->refund < $this->total;
    }

    // ==================
    // Countable & IteratorAggregate
    // ==================

    public function count(): int {
        return count($this->items);
    }

    public function getIterator(): \ArrayIterator {
        return new \ArrayIterator($this->items);
    }

    // ==================
    // Conversion methods
    // ==================

    /**
     * Convert to array for database storage
     *
     * @return array
     */
    public function toDatabase(): array {
        return [
            'order_id' => $this->orderId,
            'trans_id' => $this->transactionId,
            'title' => $this->title,
            'uid' => $this->userId,
            'date' => $this->date,
            'expire_date' => $this->expireDate,
            'auto_renew' => (int) $this->autoRenew,
            'items' => serialize($this->getProductIds()),
            'subtotal' => $this->subtotal,
            'coupon_discount' => $this->couponDiscount,
            'cart_discount' => $this->cartDiscount,
            'tax' => $this->tax,
            'total' => function_exists('wpdmpp_price_format')
                ? wpdmpp_price_format($this->total, false, false)
                : number_format($this->total, 2, '.', ''),
            'refund' => $this->refund,
            'order_status' => $this->orderStatus,
            'payment_status' => $this->paymentStatus,
            'payment_method' => $this->paymentMethod,
            'coupon_code' => $this->couponCode,
            'currency' => serialize($this->currency),
            'billing_info' => serialize($this->billingInfo),
            'cart_data' => serialize($this->cartData),
            'download' => (int) $this->download,
            'IP' => $this->ip,
            'ipn' => $this->ipn,
            'meta_data' => serialize($this->metaData),
        ];
    }

    /**
     * Convert to array for API response
     *
     * @return array
     */
    public function toArray(): array {
        $items = [];
        foreach ($this->items as $item) {
            $items[] = $item->toArray();
        }

        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'transaction_id' => $this->transactionId,
            'title' => $this->title,
            'user_id' => $this->userId,
            'date' => $this->date,
            'date_formatted' => $this->getFormattedDate(),
            'expire_date' => $this->expireDate,
            'expire_date_formatted' => $this->getFormattedExpireDate(),
            'is_expired' => $this->isExpired(),
            'auto_renew' => $this->autoRenew,
            'items' => $items,
            'item_count' => count($this->items),
            'product_ids' => $this->getProductIds(),
            'subtotal' => $this->subtotal,
            'coupon_discount' => $this->couponDiscount,
            'cart_discount' => $this->cartDiscount,
            'total_discount' => $this->getTotalDiscount(),
            'tax' => $this->tax,
            'total' => $this->total,
            'refund' => $this->refund,
            'order_status' => $this->orderStatus,
            'payment_status' => $this->paymentStatus,
            'payment_method' => $this->paymentMethod,
            'payment_method_label' => $this->getPaymentMethodLabel(),
            'coupon_code' => $this->couponCode,
            'currency' => $this->currency,
            'currency_sign' => $this->getCurrencySign(),
            'billing_info' => $this->billingInfo,
            'billing_name' => $this->getBillingName(),
            'billing_email' => $this->getBillingEmail(),
            'can_download' => $this->canDownload(),
            'ip' => $this->ip,
            'notes' => $this->notes,
        ];
    }

    /**
     * Format total with currency
     *
     * @return string
     */
    public function getFormattedTotal(): string {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($this->total, true, true);
        }
        return $this->getCurrencySign() . number_format($this->total, 2);
    }

    /**
     * Format subtotal with currency
     *
     * @return string
     */
    public function getFormattedSubtotal(): string {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($this->subtotal, true, true);
        }
        return $this->getCurrencySign() . number_format($this->subtotal, 2);
    }

    /**
     * Get order URL for customer
     *
     * @return string
     */
    public function getOrderUrl(): string {
        if (function_exists('wpdmpp_orders_page')) {
            return wpdmpp_orders_page('id=' . $this->orderId);
        }
        return '';
    }

    /**
     * Get guest order URL
     *
     * @return string
     */
    public function getGuestOrderUrl(): string {
        if (function_exists('wpdmpp_guest_order_page')) {
            return wpdmpp_guest_order_page('id=' . $this->orderId);
        }
        return '';
    }

    /**
     * Get admin order URL
     *
     * @return string
     */
    public function getAdminUrl(): string {
        return admin_url('edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $this->orderId);
    }
}
