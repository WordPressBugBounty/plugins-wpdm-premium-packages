<?php
/**
 * Order Service
 *
 * Main orchestration class for order operations.
 * Provides a unified API for creating, managing, and completing orders.
 *
 * @package WPDMPP\Order
 * @since 7.0.0
 */

namespace WPDMPP\Order;

use WPDMPP\Order\Repository\DatabaseRepository;
use WPDM\__\Session;
use WPDM\__\Email;

defined('ABSPATH') || exit;

class OrderService {

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Order repository
     *
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $repository;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->repository = new DatabaseRepository();
    }

    /**
     * Set a custom repository
     *
     * @param OrderRepositoryInterface $repository
     * @return void
     */
    public function setRepository(OrderRepositoryInterface $repository): void {
        $this->repository = $repository;
    }

    /**
     * Get the repository
     *
     * @return OrderRepositoryInterface
     */
    public function getRepository(): OrderRepositoryInterface {
        return $this->repository;
    }

    /**
     * Create an order from cart
     *
     * @param array  $cartItems     Cart items
     * @param array  $billingInfo   Billing information
     * @param string $paymentMethod Payment method
     * @return Order|false
     */
    public function createFromCart(array $cartItems, array $billingInfo = [], string $paymentMethod = '') {
        if (empty($cartItems)) {
            return false;
        }

        // Get billing info from request if not provided
        if (empty($billingInfo) && function_exists('wpdm_query_var')) {
            $billingInfo = wpdm_query_var('billing') ?: [];
        }

        // Get payment method from request if not provided
        if (empty($paymentMethod) && function_exists('wpdm_query_var')) {
            $paymentMethod = wpdm_query_var('method') ?: '';
        }

        // Calculate cart totals
        $subtotal = 0;
        $couponDiscount = 0;
        $roleDiscount = 0;

        foreach ($cartItems as $productId => $item) {
            $price = (float) ($item['price'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 1);
            $subtotal += $price * $quantity;
            // Support both legacy (coupon_amount) and new (coupon_discount) field names
            $couponDiscount += (float) ($item['coupon_amount'] ?? $item['coupon_discount'] ?? 0);
            $roleDiscount += (float) ($item['discount_amount'] ?? $item['role_discount'] ?? 0);
        }

        // Get coupon code and cart-level discount from cart
        $couponCode = '';
        if (function_exists('WPDMPP') && is_callable([WPDMPP()->cart, 'getCoupon'])) {
            $couponCode = WPDMPP()->cart->getCoupon('code') ?? '';

            // If per-item coupon discount is 0 but cart has a coupon applied,
            // use the cart-level coupon discount (new cart system stores discount at cart level)
            if ($couponDiscount == 0 && $couponCode !== '') {
                $couponDiscount = (float) (WPDMPP()->cart->getCoupon('discount') ?? 0);
            }
        }

        // Calculate tax
        $billingCountry = $billingInfo['country'] ?? '';
        $billingState = $billingInfo['state'] ?? '';
        $tax = 0;

        if (function_exists('WPDMPP') && is_callable([WPDMPP()->cart, 'calculateTax'])) {
            $tax = WPDMPP()->cart->calculateTax(
                $subtotal - $couponDiscount - $roleDiscount,
                $billingCountry,
                $billingState,
                false
            );
        }

        $total = $subtotal - $couponDiscount - $roleDiscount + $tax;

        // Create order entity
        $order = Order::fromCart($cartItems, $billingInfo, $paymentMethod);
        $order->setSubtotal($subtotal);
        $order->setCouponDiscount($couponDiscount);
        $order->setCartDiscount($roleDiscount);
        $order->setTax($tax);
        $order->setTotal($total);
        $order->setCouponCode($couponCode);
        $order->setAutoRenew((bool) get_wpdmpp_option('auto_renew', 0));

        // Save to database
        $saved = $this->repository->save($order);

        if (!$saved) {
            if (class_exists('\WPDMPP\Libs\Logger')) {
                \WPDMPP\Libs\Logger::error('Order creation failed - database insert error', [
                    'order_id' => $order->getOrderId(),
                    'user_id' => $order->getUserId(),
                    'total' => $total,
                    'items_count' => count($cartItems),
                ]);
            }
            return false;
        }

        // Store order ID in session
        if (class_exists('\WPDM\__\Session')) {
            Session::set('orderid', $order->getOrderId());
        }

        // Log order creation
        if (class_exists('\WPDMPP\Libs\Logger')) {
            \WPDMPP\Libs\Logger::order($order->getOrderId(), 'New order created', [
                'user_id' => $order->getUserId(),
                'total' => $total,
                'items_count' => count($cartItems),
            ]);
        }

        // Fire action hook
        do_action('wpdmpp_new_order_created', $order->getOrderId());
        do_action('wpdmpp_order_created', $order);

        return $order;
    }

    /**
     * Get an order by ID
     *
     * @param string $orderId Order ID
     * @return Order|null
     */
    public function getOrder(string $orderId): ?Order {
        return $this->repository->find($orderId);
    }

    /**
     * Get an order as a raw database row (stdClass)
     *
     * Used by admin view templates that access order properties directly.
     *
     * @param string $orderId Order ID
     * @return \stdClass|null
     */
    public function getRawOrder(string $orderId): ?\stdClass {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s",
            $orderId
        ));
    }

    /**
     * Get an order by transaction ID
     *
     * @param string $transactionId Transaction ID
     * @return Order|null
     */
    public function getOrderByTransactionId(string $transactionId): ?Order {
        return $this->repository->findByTransactionId($transactionId);
    }

    /**
     * Get orders for a user
     *
     * @param int  $userId        User ID
     * @param bool $completedOnly Only completed/expired orders
     * @return Order[]
     */
    public function getUserOrders(int $userId, bool $completedOnly = false): array {
        return $this->repository->findByUser($userId, $completedOnly);
    }

    /**
     * Get raw user orders as stdClass objects (for templates with direct property access)
     */
    public function getRawUserOrders(int $userId, bool $completedOnly = false): array {
        global $wpdb;
        $statusFilter = $completedOnly
            ? "AND (order_status = 'Completed' OR order_status = 'Expired')"
            : '';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_orders WHERE uid = %d {$statusFilter} ORDER BY order_status DESC, date DESC",
            $userId
        ));
    }

    /**
     * Get orders with pagination
     *
     * @param array $args Query arguments
     * @return array ['orders' => Order[], 'total' => int]
     */
    public function getOrders(array $args = []): array {
        return $this->repository->findAll($args);
    }

    /**
     * Count total orders matching a raw SQL WHERE clause
     *
     * Used by admin order list view.
     *
     * @param string $query Raw SQL WHERE + ORDER BY clause
     * @return int
     */
    public function totalOrders(string $query = ''): int {
        global $wpdb;
        // Strip ORDER BY from count query
        $countQuery = preg_replace('/\s+ORDER BY\s+.+$/i', '', $query);
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ahm_orders {$countQuery}");
    }

    /**
     * Get all orders matching a raw SQL WHERE clause with pagination
     *
     * Used by admin order list view. Returns raw stdClass objects.
     *
     * @param string $query  Raw SQL WHERE + ORDER BY clause
     * @param int    $offset Pagination offset
     * @param int    $limit  Results per page
     * @return array
     */
    public function GetAllOrders(string $query = '', int $offset = 0, int $limit = 15): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ahm_orders {$query} LIMIT {$offset}, {$limit}");
    }

    /**
     * Count total order renewals
     *
     * @return int
     */
    public function totalRenews(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ahm_order_renews");
    }

    /**
     * Get all order renewals with pagination
     *
     * @param string $query  Raw SQL WHERE + ORDER BY clause
     * @param int    $offset Pagination offset
     * @param int    $limit  Results per page
     * @return array
     */
    public function getAllRenews(string $query = '', int $offset = 0, int $limit = 15): array {
        global $wpdb;
        $offset = max(0, $offset);
        $limit  = max(1, $limit);
        // Join with ahm_orders so each renewal row carries the parent order's full data
        // (uid, currency, cart_data, billing_info, title, order_status, payment_status, expire_date).
        // The renewal's own date/total are aliased so they don't shadow the order columns.
        $sql = "SELECT o.*, r.ID AS renew_id, r.date AS renew_date, r.total AS renew_total
                FROM {$wpdb->prefix}ahm_order_renews r
                INNER JOIN {$wpdb->prefix}ahm_orders o ON o.order_id = r.order_id
                {$query} LIMIT {$offset}, {$limit}";
        $rows = $wpdb->get_results($sql);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Update an order
     *
     * @param array  $data    Data to update
     * @param string $orderId Order ID
     * @return bool
     */
    public function updateOrder(array $data, string $orderId): bool {
        $result = $this->repository->update($data, $orderId);

        if ($result) {
            do_action('wpdmpp_order_updated', $orderId, $data);
        }

        return $result;
    }

    /**
     * Complete an order
     *
     * @param string      $orderId       Order ID
     * @param bool        $sendEmail     Send confirmation email
     * @param string|null $paymentMethod Payment method used
     * @return bool
     */
    public function completeOrder(string $orderId, bool $sendEmail = true, ?string $paymentMethod = null): bool {
        // Handle renewal suffix
        if (strpos($orderId, 'renew') !== false) {
            $parts = explode('_', $orderId);
            $orderId = $parts[0];
        }

        $order = $this->repository->find($orderId);
        if (!$order) {
            if (class_exists('\WPDMPP\Libs\Logger')) {
                \WPDMPP\Libs\Logger::warning('complete_order called for non-existent order', [
                    'order_id' => $orderId,
                ]);
            }
            return false;
        }

        // Clear carts
        if (function_exists('wpdmpp_clear_user_cart')) {
            wpdmpp_clear_user_cart($order->getUserId());
        }
        if (function_exists('wpdmpp_empty_cart')) {
            wpdmpp_empty_cart();
        }

        // Calculate expiration date
        $validityPeriod = (int) get_wpdmpp_option('order_validity_period', 0);
        $expireDate = $validityPeriod > 0 ? strtotime("+{$validityPeriod} days") : 0;
        $autoRenew = (bool) get_wpdmpp_option('auto_renew', 0);

        $updateData = [
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_COMPLETED,
            'expire_date' => $expireDate,
            'auto_renew' => (int) $autoRenew,
        ];

        // Update date only if order was processing
        if ($order->isProcessing()) {
            $updateData['date'] = time();
        }

        $this->repository->update($updateData, $orderId);

        // Handle guest session
        if (!is_user_logged_in()) {
            if (class_exists('\WPDM\__\Session')) {
                Session::set('guest_order', $orderId, 18000);
                Session::set('order_email', $order->getBillingEmail(), 18000);
            }
        } else {
            // Add user as customer
            $customerService = \WPDMPP\Customer\CustomerService::getInstance();
            $customerService->addCustomer($order->getUserId());
            $customerService->processActiveRoles($order->getUserId());
        }

        // Check if this is a renewal
        $wasExpired = $order->getOrderStatus() === Order::STATUS_EXPIRED;

        if ($wasExpired) {
            // Add renewal record
            $this->repository->addRenewal(
                $orderId,
                $order->getTotal(),
                $order->getTransactionId()
            );

            if (class_exists('\WPDMPP\Libs\Logger')) {
                \WPDMPP\Libs\Logger::order($orderId, 'Order renewed', [
                    'user_id' => $order->getUserId(),
                    'total' => $order->getTotal(),
                    'payment_method' => $paymentMethod,
                ]);
            }

            do_action('wpdmpp_order_renewed', $orderId);
        } else {
            if (class_exists('\WPDMPP\Libs\Logger')) {
                \WPDMPP\Libs\Logger::order($orderId, 'Order completed', [
                    'user_id' => $order->getUserId(),
                    'total' => $order->getTotal(),
                    'payment_method' => $paymentMethod ?? $order->getPaymentMethod(),
                ]);
            }

            do_action('wpdmpp_order_completed', $orderId);
        }

        // Increase coupon usage
        $couponCode = $order->getCouponCode();
        $couponDiscount = $order->getCouponDiscount();

        if ($couponDiscount > 0 && !empty($couponCode)) {
            \WPDMPP\Coupon\CouponService::getInstance()->incrementUsage($couponCode);
        }

        // Send emails
        if ($sendEmail) {
            $this->sendCompletionEmails($order, $wasExpired);
        }

        return true;
    }

    /**
     * Renew an order
     *
     * @param string      $orderId        Order ID
     * @param string      $subscriptionId Subscription ID
     * @param bool        $sendEmail      Send notification email
     * @param int|null    $timestamp      Renewal timestamp
     * @param string|null $invoice        Invoice reference
     * @return bool
     */
    public function renewOrder(
        string $orderId,
        string $subscriptionId = '',
        bool $sendEmail = true,
        ?int $timestamp = null,
        ?string $invoice = null
    ): int|bool {
        $order = null;

        // Try to find by order ID or subscription/transaction ID
        if (!empty($subscriptionId)) {
            $order = $this->repository->findByTransactionId($subscriptionId);
        }

        if (!$order) {
            $order = $this->repository->find($orderId);
        }

        if (!$order) {
            return false;
        }

        // Calculate new expiration date
        $validityPeriod = (int) get_wpdmpp_option('order_validity_period', 365);
        $baseTimestamp = $timestamp ?? time();
        $expireDate = $validityPeriod > 0 ? $baseTimestamp + ($validityPeriod * 86400) : 0;
		//wpdmdd(wp_date('Y-m-d H:i:s', $baseTimestamp), $baseTimestamp);
        // Update order
        $this->repository->update([
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_COMPLETED,
            'auto_renew' => 1,
            'expire_date' => $expireDate,
        ], $order->getOrderId());

        // Add renewal record
        $this->repository->addRenewal(
            $order->getOrderId(),
            $order->getTotal(),
            $subscriptionId,
            $invoice ?? '',
	        $timestamp ?? time()
        );

        do_action('wpdmpp_order_renewed', $order->getOrderId());

        // Send email
        if ($sendEmail) {
            $this->sendRenewalEmail($order);
        }

        return $expireDate;
    }

    /**
     * Cancel an order
     *
     * @param string $orderId Order ID
     * @param string $reason  Cancellation reason
     * @return bool
     */
    public function cancelOrder(string $orderId, string $reason = ''): bool {
        $order = $this->repository->find($orderId);
        if (!$order || !$order->canBeCancelled()) {
            return false;
        }

        $this->repository->update([
            'order_status' => Order::STATUS_CANCELLED,
        ], $orderId);

        if (!empty($reason)) {
            $this->repository->addNote($orderId, "Order cancelled: {$reason}");
        }

        if (class_exists('\WPDMPP\Libs\Logger')) {
            \WPDMPP\Libs\Logger::order($orderId, 'Order cancelled', [
                'user_id' => $order->getUserId(),
                'reason' => $reason,
            ]);
        }

        do_action('wpdmpp_order_cancelled', $orderId, $reason);

        return true;
    }

    /**
     * Expire an order
     *
     * @param string $orderId Order ID
     * @return bool
     */
    public function expireOrder(string $orderId): bool {
        $order = $this->repository->find($orderId);
        if (!$order) {
            return false;
        }

        $this->repository->update([
            'order_status' => Order::STATUS_EXPIRED,
        ], $orderId);

        if (class_exists('\WPDMPP\Libs\Logger')) {
            \WPDMPP\Libs\Logger::order($orderId, 'Order expired', [
                'user_id' => $order->getUserId(),
            ]);
        }

        do_action('wpdmpp_order_expired', $orderId);

        return true;
    }

    /**
     * Refund an order
     *
     * @param string     $orderId Order ID
     * @param float|null $amount  Refund amount (null for full refund)
     * @param string     $reason  Refund reason
     * @return bool
     */
    public function refundOrder(string $orderId, ?float $amount = null, string $reason = ''): bool {
        $order = $this->repository->find($orderId);
        if (!$order || !$order->canBeRefunded()) {
            return false;
        }

        // Calculate refund amount
        if ($amount === null) {
            $amount = $order->getTotal() - $order->getRefund();
        }

        $totalRefund = $order->getRefund() + $amount;

        // Determine if full refund
        $isFullRefund = $totalRefund >= $order->getTotal();

        $this->repository->update([
            'refund' => $totalRefund,
            'order_status' => $isFullRefund ? Order::STATUS_REFUNDED : $order->getOrderStatus(),
        ], $orderId);

        if (!empty($reason)) {
            $this->repository->addNote($orderId, "Refund ({$amount}): {$reason}");
        }

        if (class_exists('\WPDMPP\Libs\Logger')) {
            \WPDMPP\Libs\Logger::order($orderId, 'Order refunded', [
                'user_id' => $order->getUserId(),
                'amount' => $amount,
                'reason' => $reason,
            ]);
        }

        do_action('wpdmpp_order_refunded', $orderId, $amount, $reason);

        return true;
    }

    /**
     * Delete an order
     *
     * @param string $orderId Order ID
     * @return bool
     */
    public function deleteOrder(string $orderId): bool {
        $order = $this->repository->find($orderId);
        if (!$order) {
            return false;
        }

        $result = $this->repository->delete($orderId);

        if ($result) {
            do_action('wpdmpp_order_deleted', $orderId);
        }

        return $result;
    }

    /**
     * Add a note to an order
     *
     * Stores the note directly in the order_notes DB column under messages[$timestamp].
     *
     * @param string $orderId Order ID
     * @param string $note    Note content
     * @param array  $options {
     *     Optional. Note options.
     *     @type array  $files    Attached filenames.
     *     @type bool   $admin    Send copy to admin.
     *     @type bool   $seller   Send copy to seller.
     *     @type bool   $customer Send copy to customer.
     *     @type string $author   Author display name (defaults to current user).
     * }
     * @return array|false The stored note data on success, false on failure
     */
    public function addNote(string $orderId, string $note, array $options = []) {
        global $wpdb;

        $data = ['note' => $note];

        if (!empty($options['files']))    $data['file']     = $options['files'];
        if (!empty($options['admin']))    $data['admin']    = 1;
        if (!empty($options['seller']))   $data['seller']   = 1;
        if (!empty($options['customer'])) $data['customer'] = 1;

        $data['by'] = !empty($options['author'])
            ? $options['author']
            : wp_get_current_user()->display_name;
        $data['uid'] = get_current_user_id();

        $time = time();
        $data['date'] = $time;

        // Read existing order_notes
        $order_row = $wpdb->get_row($wpdb->prepare(
            "SELECT order_notes FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s", $orderId
        ));
        $meta = $order_row ? maybe_unserialize($order_row->order_notes) : [];
        if (!is_array($meta)) $meta = [];

        $meta['messages'][$time] = $data;

        $result = $wpdb->update(
            $wpdb->prefix . 'ahm_orders',
            ['order_notes' => maybe_serialize($meta)],
            ['order_id' => $orderId]
        );

        if ($result === false) {
            return false;
        }

        // Email a copy of the note to the selected recipients. Restores behavior
        // lost when the legacy Order::add_note() was removed in the OrderService
        // migration. All note paths (REST user/admin + legacy AJAX) funnel here.
        $this->sendNoteNotifications($orderId, $note, $data, $options);

        $data['time'] = $time;
        return $data;
    }

    /**
     * Email a copy of an order note to the selected recipients.
     *
     * Mirrors the original legacy Order::add_note() which emailed the admin and
     * customer copies via the "default" template; the seller copy (resolved from
     * the purchased items' authors) is also wired here. Sending is skipped when
     * no recipient flag is set, or when an explicit `email => 0` opt-out is passed.
     *
     * @param array $data    Stored note data (carries admin/seller/customer flags).
     * @param array $options Original options passed to addNote().
     */
    private function sendNoteNotifications(string $orderId, string $note, array $data, array $options): void
    {
        // Legacy opt-out: an explicit email=0 suppresses notifications.
        if (isset($options['email']) && (int) $options['email'] === 0) {
            return;
        }

        $wantAdmin    = !empty($data['admin']);
        $wantCustomer = !empty($data['customer']);
        $wantSeller   = !empty($data['seller']);
        if (!$wantAdmin && !$wantCustomer && !$wantSeller) {
            return;
        }

        $order = $this->getOrder($orderId);
        if (!$order) {
            return;
        }

        $noteHtml = wp_kses($note, [
            'strong' => [], 'b' => [], 'br' => [], 'p' => [], 'hr' => [],
            'em' => [], 'i' => [],
            'a' => ['href' => [], 'title' => []],
        ]);

        $customerLink = '<a class="button" style="display:block;margin:0;padding:10px 0 !important;" href="'
            . esc_url(wpdmpp_orders_page('id=' . $orderId)) . '">' . __('View Order', 'wpdm-premium-packages') . '</a>';
        $adminLink = '<a class="button" style="display:block;margin:0;padding:10px 0 !important;" href="'
            . esc_url(admin_url('edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $orderId)) . '">'
            . __('View Order', 'wpdm-premium-packages') . '</a>';

        $subject = sprintf(__('New Note: Order# %s', 'wpdm-premium-packages'), $orderId);
        $sent    = []; // dedupe recipients by email

        $send = function ($email, string $link) use ($subject, $noteHtml, &$sent): void {
            $email = is_string($email) ? trim($email) : '';
            if ($email === '' || !is_email($email) || isset($sent[strtolower($email)])) {
                return;
            }
            $sent[strtolower($email)] = true;
            $message = '<strong>' . __('Note:', 'wpdm-premium-packages') . '</strong>'
                . '<div class="uibox" style="padding:20px;background:#ffffff;border:1px solid #d7dcea;border-radius:4px;margin:10px 0">'
                . wpautop($noteHtml) . $link . '</div>';
            Email::send('default', [
                'subject'  => $subject,
                'to_email' => $email,
                'message'  => $message,
            ]);
        };

        if ($wantAdmin) {
            $send(get_option('admin_email'), $adminLink);
        }

        if ($wantCustomer) {
            // Account email (legacy behavior), with guest fallback to billing email.
            $customerEmail = '';
            if ($order->getUserId() > 0) {
                $user = get_userdata($order->getUserId());
                if ($user) {
                    $customerEmail = $user->user_email;
                }
            }
            if ($customerEmail === '') {
                $customerEmail = $order->getBillingEmail();
            }
            $send($customerEmail, $customerLink);
        }

        if ($wantSeller) {
            foreach ($order->getProductIds() as $pid) {
                $pid = (int) $pid;
                if ($pid <= 0) {
                    continue; // dynamic items have no product/author
                }
                $authorId = (int) get_post_field('post_author', $pid);
                if ($authorId <= 0) {
                    continue;
                }
                $seller = get_userdata($authorId);
                if ($seller) {
                    $send($seller->user_email, $customerLink);
                }
            }
        }
    }

    /**
     * Get purchased items for a user
     *
     * @param int|null $userId User ID (current user if null)
     * @return array
     */
    public function getPurchasedItems(?int $userId = null): array {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return [];
        }

        return $this->repository->getPurchasedItems($userId);
    }

    /**
     * Check if user has purchased a product
     *
     * @param int      $productId Product ID
     * @param int|null $userId    User ID (current user if null)
     * @return bool
     */
    public function hasPurchased(int $productId, ?int $userId = null): bool {
        if ($userId === null) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return false;
        }

        return $this->repository->hasPurchased($productId, $userId);
    }

    /**
     * Get order statistics
     *
     * @return array
     */
    public function getStatistics(): array {
        $repo = $this->repository;

        // Get counts by status
        $countByStatus = [];
        if ($repo instanceof DatabaseRepository) {
            $countByStatus = $repo->getCountByStatus();
        }

        return [
            'total' => $this->repository->count(),
            'completed' => $this->repository->count(['status' => Order::STATUS_COMPLETED]),
            'processing' => $this->repository->count(['status' => Order::STATUS_PROCESSING]),
            'expired' => $this->repository->count(['status' => Order::STATUS_EXPIRED]),
            'cancelled' => $this->repository->count(['status' => Order::STATUS_CANCELLED]),
            'by_status' => $countByStatus,
        ];
    }

    /**
     * Send order completion emails
     *
     * @param Order $order     Order object
     * @param bool  $isRenewal Is this a renewal
     * @return void
     */
    private function sendCompletionEmails(Order $order, bool $isRenewal = false): void {
        if (!class_exists('\WPDM\__\Email')) {
            return;
        }

        $settings = get_option('_wpdmpp_settings');
        $logo = !empty($settings['logo_url'])
            ? "<img src='{$settings['logo_url']}' alt='" . get_bloginfo('name') . "'/>"
            : get_bloginfo('name');

        $buyerEmail = $order->getBillingEmail();
        $name = $order->getBillingName();

        // Get buyer info from user if not in billing
        $userId = $order->getUserId();
        if ($userId && empty($buyerEmail)) {
            $userInfo = get_userdata($userId);
            if ($userInfo) {
                $name = $userInfo->display_name;
                $buyerEmail = $userInfo->user_email;
            }
        }

        $orderId = $order->getOrderId();

        $params = [
            'date' => wp_date(get_option('date_format'), time()),
            'homeurl' => home_url('/'),
            'sitename' => get_bloginfo('name'),
            'order_link' => "<a href='" . $order->getOrderUrl() . "'>" . $order->getOrderUrl() . "</a>",
            'register_link' => "<a href='" . $order->getOrderUrl() . "'>" . $order->getOrderUrl() . "</a>",
            'name' => $name,
            'orderid' => $orderId,
            'to_email' => $buyerEmail,
            'order_url' => $order->getOrderUrl(),
            'guest_order_url' => $order->getGuestOrderUrl(),
            'order_url_admin' => $order->getAdminUrl(),
            'img_logo' => $logo,
            'payment_method' => $order->getPaymentMethodLabel(),
            'order_total' => $order->getFormattedTotal(),
        ];

        // Build items table
        $params['items'] = $this->buildItemsTable($order);

        // Send to buyer
        if (!$userId) {
            Email::send('purchase-confirmation-guest', $params);
        } else {
            Email::send('purchase-confirmation', $params);
        }

        // Send to admin
        $params['to_email'] = get_option('admin_email');
        Email::send('sale-notification', $params);
    }

    /**
     * Send renewal email
     *
     * @param Order $order Order object
     * @return void
     */
    private function sendRenewalEmail(Order $order): void {
        if (!class_exists('\WPDM\__\Email')) {
            return;
        }

        $settings = get_option('_wpdmpp_settings');
        $logo = !empty($settings['logo_url'])
            ? "<img src='{$settings['logo_url']}' alt='" . get_bloginfo('name') . "'/>"
            : get_bloginfo('name');

        $buyerEmail = $order->getBillingEmail();
        $name = $order->getBillingName();

        $userId = $order->getUserId();
        if ($userId && empty($buyerEmail)) {
            $userInfo = get_userdata($userId);
            if ($userInfo) {
                $name = $userInfo->display_name;
                $buyerEmail = $userInfo->user_email;
            }
        }

        $params = [
            'date' => wp_date(get_option('date_format'), time()),
            'homeurl' => home_url('/'),
            'sitename' => get_bloginfo('name'),
            'order_link' => "<a href='" . $order->getOrderUrl() . "'>" . $order->getOrderUrl() . "</a>",
            'register_link' => "<a href='" . $order->getOrderUrl() . "'>" . $order->getOrderUrl() . "</a>",
            'name' => $name,
            'orderid' => $order->getOrderId(),
            'to_email' => $buyerEmail,
            'order_url' => $order->getOrderUrl(),
            'order_url_admin' => $order->getAdminUrl(),
            'img_logo' => $logo,
        ];

        Email::send('renew-confirmation', $params);
    }

    /**
     * Build items table HTML for emails
     *
     * @param Order $order Order object
     * @return string
     */
    private function buildItemsTable(Order $order): string {
        $currencySign = $order->getCurrencySign();
        $items = $order->getItems();

        $html = "<table class='email cart-table' style='width: 100%;border: 0;' cellpadding='0' cellspacing='0'>";
        $html .= "<tr><th>Product Name</th><th>License</th><th style='width:80px;text-align:right'>Price</th></tr>";

        foreach ($items as $item) {
            $productName = $item->getProductName();
            $licenseName = $item->getLicenseName() ?: '&mdash;';
            $price = $currencySign . number_format($item->getPrice(), 2);

            if ($item->getProductType() !== 'dynamic') {
                $productName = "<a href='" . get_permalink($item->getProductId()) . "'>{$productName}</a>";
            }

            $html .= "<tr><td>{$productName}</td><td>{$licenseName}</td><td style='width:80px;text-align:right'>{$price}</td></tr>";
        }

        // Add cart total
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->getLineTotal();
        }

        $html .= "<tr><th colspan='2' style='text-align: right'>" . __('Cart Total', 'wpdm-premium-packages') . "</th>";
        $html .= "<th style='text-align: right'>" . $currencySign . number_format($subtotal, 2) . "</th></tr>";

        // Add coupon discount
        if ($order->getCouponDiscount() > 0) {
            $html .= "<tr><th colspan='2' style='text-align: right'>" . __('Coupon Discount', 'wpdm-premium-packages') . "</th>";
            $html .= "<th style='text-align: right'>-" . $currencySign . number_format($order->getCouponDiscount(), 2) . "</th></tr>";
        }

        // Add tax
        if ($order->getTax() > 0) {
            $html .= "<tr><th colspan='2' style='text-align: right'>" . __('Tax', 'wpdm-premium-packages') . "</th>";
            $html .= "<th style='text-align: right'>" . $currencySign . number_format($order->getTax(), 2) . "</th></tr>";
        }

        // Add order total
        $html .= "<tr><th colspan='2' style='text-align: right'>" . __('Order Total', 'wpdm-premium-packages') . "</th>";
        $html .= "<th style='text-align: right'>" . $order->getFormattedTotal() . "</th></tr>";

        $html .= "</table>";

        return $html;
    }

    /**
     * Recalculate order totals
     *
     * @param string $orderId Order ID
     * @return bool
     */
    public function recalculateTotals(string $orderId): bool {
        $order = $this->repository->find($orderId);
        if (!$order) {
            return false;
        }

        $order->recalculateTotals();
        return $this->repository->save($order);
    }

    /**
     * Check and expire old orders
     *
     * @return int Number of expired orders
     */
    public function processExpiredOrders(): int {
        global $wpdb;

        $now = time();
        $ordersTable = $wpdb->prefix . 'ahm_orders';

        // Find and expire orders
        $expiredOrders = $wpdb->get_col($wpdb->prepare(
            "SELECT order_id FROM {$ordersTable}
            WHERE expire_date > 0 AND expire_date < %d
            AND order_status = 'Completed'",
            $now
        ));

        $count = 0;
        foreach ($expiredOrders as $orderId) {
            if ($this->expireOrder($orderId)) {
                $count++;
            }
        }

        return $count;
    }

    // ==================
    // Convenience methods (legacy bridge)
    // ==================

    /**
     * Get order meta from order_notes column
     *
     * Legacy orders store key-value meta in the serialized order_notes column.
     *
     * @param string $orderId Order ID
     * @param string $key     Meta key
     * @return mixed
     */
    public function getMeta(string $orderId, string $key) {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s",
            $orderId
        ));

        if (!$order) {
            return null;
        }

        $meta = maybe_unserialize($order->order_notes ?? '');
        if (!is_array($meta)) {
            $meta = [];
        }

        return $meta[$key] ?? null;
    }

    /**
     * Update order meta in order_notes column
     *
     * @param string $orderId Order ID
     * @param string $key     Meta key
     * @param mixed  $value   Meta value
     * @return bool
     */
    public function updateMeta(string $orderId, string $key, $value): bool {
        global $wpdb;

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT order_notes FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s",
            $orderId
        ));

        $meta = [];
        if ($order && $order->order_notes) {
            $meta = maybe_unserialize($order->order_notes);
            if (!is_array($meta)) {
                $meta = [];
            }
        }

        $meta[$key] = $value;

        return $this->updateOrder(['order_notes' => maybe_serialize($meta)], $orderId);
    }

    /**
     * Get order items as OrderItem objects
     *
     * @param string $orderId Order ID
     * @return OrderItem[]
     */
    public function getOrderItems(string $orderId): array {
        return $this->repository->getItems($orderId);
    }

    /**
     * Get order items as arrays (for templates)
     *
     * @param string $orderId Order ID
     * @return array
     */
    public function getOrderItemsAsArrays(string $orderId): array {
        global $wpdb;

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_order_items WHERE oid = %s",
            $orderId
        ), ARRAY_A);

        return is_array($items) ? $items : [];
    }

    /**
     * Save order items from cart data
     *
     * Replaces all order items with new cart data.
     *
     * @param array  $cartData Cart data array
     * @param string $orderId  Order ID
     * @param bool   $admin    Whether this is an admin operation
     * @return bool
     */
    public function saveOrderItems($cartData, string $orderId, bool $admin = false): bool {
        global $wpdb;

        $cartData = maybe_unserialize($cartData);
        $order = $this->getOrder($orderId);

        if (!$order) {
            return false;
        }

        if ($order->getOrderStatus() !== Order::STATUS_PROCESSING && !$admin) {
            return false;
        }

        $time = $order->getDate() ?: time();

        // Delete existing items
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ahm_order_items WHERE oid = %s",
            $orderId
        ));

        if (empty($cartData)) {
            return true;
        }

        foreach ($cartData as $pid => $cdt) {
            $productType = wpdm_valueof($cdt, 'product_type');
            $coupon = wpdm_valueof($cdt, 'coupon');
            $couponAmount = (float) wpdm_valueof($cdt, 'coupon_amount', 0);
            $roleDisc = (float) wpdm_valueof($cdt, 'discount_amount', 0);
            $productName = wpdm_valueof($cdt, 'product_name');

            $sid = $productType === 'dynamic' ? 0 : (int) get_post_field('post_author', $pid);
            $cid = $order->getUserId();

            $wpdb->insert(
                "{$wpdb->prefix}ahm_order_items",
                [
                    'oid' => $orderId,
                    'pid' => $pid,
                    'product_type' => $productType,
                    'product_name' => $productName,
                    'license' => maybe_serialize($cdt['license'] ?? []),
                    'quantity' => (int) ($cdt['quantity'] ?? 1),
                    'price' => (float) ($cdt['price'] ?? 0),
                    'extra_gigs' => maybe_serialize($cdt['extra_gigs'] ?? []),
                    'coupon' => $coupon,
                    'coupon_discount' => $couponAmount,
                    'role_discount' => $roleDisc,
                    'site_commission' => 0,
                    'date' => wp_date("Y-m-d H:i:s", $time),
                    'year' => wp_date('Y', $time),
                    'month' => wp_date('m', $time),
                    'day' => wp_date('d', $time),
                    'sid' => $sid,
                    'cid' => $cid
                ]
            );
        }

        return true;
    }

    /**
     * Send order confirmation email
     *
     * Public wrapper for sendCompletionEmails.
     *
     * @param string $orderId Order ID
     * @return bool
     */
    public function sendConfirmationEmail(string $orderId): bool {
        $order = $this->repository->find($orderId);
        if (!$order) {
            return false;
        }

        $this->sendCompletionEmails($order, false);
        return true;
    }

    /**
     * Build HTML items table for emails
     *
     * Public wrapper for buildItemsTable.
     *
     * @param string $orderId Order ID
     * @return string
     */
    public function buildItemsTableHtml(string $orderId): string {
        $order = $this->repository->find($orderId);
        if (!$order) {
            return '';
        }

        return $this->buildItemsTable($order);
    }

    /**
     * Calculate line total for a single item
     *
     * @param object|array $item   Order item
     * @param bool         $format Whether to format the price
     * @return float|string
     */
    public function itemCost($item, bool $format = false) {
        if (is_object($item)) {
            $item = (array) $item;
        }

        $price = (float) ($item['price'] ?? 0);
        $gigsCost = (float) ($item['prices'] ?? 0);

        if ($gigsCost === 0.0 && !empty($item['extra_gigs'])) {
            $extraGigs = $item['extra_gigs'];
            if (is_string($extraGigs)) {
                $extraGigs = maybe_unserialize($extraGigs);
            }
            if (is_array($extraGigs)) {
                foreach ($extraGigs as $gig) {
                    $gigsCost += (float) ($gig['option_price'] ?? $gig['price'] ?? 0);
                }
            }
        }

        $quantity = (int) ($item['quantity'] ?? 1);
        $cost = ($price + $gigsCost) * $quantity;

        $roleDiscount = (float) ($item['role_discount'] ?? $item['discount_amount'] ?? 0);
        $cost -= $roleDiscount;

        $couponDiscount = (float) ($item['coupon_discount'] ?? $item['coupon_amount'] ?? 0);
        $cost -= $couponDiscount;

        $cost = max(0, $cost);

        if ($format) {
            return wpdmpp_price_format($cost);
        }

        return $cost;
    }

    /**
     * Display user order details (for frontend)
     *
     * @param string|null $orderId Order ID
     * @return void
     */
    public function userOrderDetails(?string $orderId = null): void {
        global $wpdb, $wpdmpp_settings;

        $current_user = wp_get_current_user();

        if (!wpdm_query_var('udb_page') || !$orderId) {
            $orderId = wpdm_query_var('id');
        }

        if (!$orderId) {
            \WPDM\__\Messages::error(__('Invalid Order ID!', 'wpdm-premium-packages'));
            return;
        }

        $order = $this->getOrder($orderId);

        if (!$order) {
            \WPDM\__\Messages::error(__('Invalid Order ID!', 'wpdm-premium-packages'));
            return;
        }

        $csign = wpdmpp_currency_sign();
        $csign_before = wpdmpp_currency_sign_position() == 'before' ? $csign : '';
        $csign_after = wpdmpp_currency_sign_position() == 'after' ? $csign : '';
        $link = wpdm_query_var('udb_page') ? get_permalink() . "?udb_page=purchases/" : get_permalink();

        // Check order status and set expire date if needed
        if ($order->getExpireDate() == 0 && get_wpdmpp_option('order_validity_period', 365) > 0) {
            $expire_date = $order->getDate() + (get_wpdmpp_option('order_validity_period', 365) * 86400);
            $this->updateOrder(['expire_date' => $expire_date], $orderId);
            if (time() > $expire_date) {
                $this->updateOrder([
                    'order_status' => 'Expired',
                    'payment_status' => 'Expired'
                ], $orderId);
            }
            // Refresh the order
            $order = $this->getOrder($orderId);
        }

        $date = wp_date("Y-m-d h:i a", $order->getDate());
        $expire_date = $order->getExpireDate();

        $renews = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_order_renews WHERE order_id = %s",
            $orderId
        ));

        // Assign guest order to current user if applicable
        if ($order->getUserId() == 0 && is_user_logged_in()) {
            $this->updateOrder(['uid' => $current_user->ID], $orderId);
            $order = $this->getOrder($orderId);
        }

        if ($order->getUserId() == $current_user->ID || current_user_can('manage_options')) {
            $currency = $order->getCurrency();
            $csign = $currency['sign'] ?? '$';
            $csign_before = wpdmpp_currency_sign_position() == 'before' ? $csign : '';
            $csign_after = wpdmpp_currency_sign_position() == 'after' ? $csign : '';
            $cart_data = $order->getCartData();
            $items = $this->getOrderItemsAsArrays($orderId);

            // Handle legacy orders without items in order_items table
            if (is_array($items) && count($items) == 0 && is_array($cart_data) && !empty($cart_data)) {
                $new_cart_data = [];
                foreach ($cart_data as $pid => $noi) {
                    $newi = get_posts([
                        'post_type' => 'wpdmpro',
                        'meta_key' => '__wpdm_legacy_id',
                        'meta_value' => $pid
                    ]);
                    if (is_array($newi) && count($newi) > 0) {
                        $new_cart_data[$newi[0]->ID] = [
                            "quantity" => $noi,
                            "variation" => "",
                            "price" => get_post_meta($newi[0]->ID, "__wpdm_base_price", true)
                        ];
                    }
                }

                if (!empty($new_cart_data)) {
                    $this->updateOrder([
                        'cart_data' => serialize($new_cart_data),
                        'items' => serialize(array_keys($new_cart_data))
                    ], $orderId);
                    $this->saveOrderItems($new_cart_data, $orderId, true);
                    $items = $this->getOrderItemsAsArrays($orderId);
                }
            }

            $title = $order->getTitle() ?: sprintf(__('Order # %s', 'wpdm-premium-packages'), $orderId);

            // Calculate discounts for colspan
            $colspan = 6;
            $coupon_discount = $role_discount = 0;
            foreach ($items as $item) {
                $coupon_discount += (float) ($item['coupon_discount'] ?? 0);
                $role_discount += (float) ($item['role_discount'] ?? 0);
            }
            if ($coupon_discount == 0) {
                $colspan--;
            }
            if ($role_discount == 0) {
                $colspan--;
            }
            if ($order->getOrderStatus() !== 'Completed') {
                $colspan--;
            }

            // For template compatibility - $o used as legacy Order instance
            $o = $this;
            $extbtns = "";
            $extbtns = apply_filters("wpdmpp_order_details_frontend", $extbtns, $order);

            include wpdm_tpl_path('partials/user-order-details.php', WPDMPP_TPL_DIR);
        } else {
            \WPDM\__\Messages::error(__('Order does not belong to you!', 'wpdm-premium-packages'));
        }
    }
}
