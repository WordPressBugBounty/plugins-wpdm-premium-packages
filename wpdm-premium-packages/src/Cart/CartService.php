<?php
/**
 * Cart Service
 *
 * Main orchestration class for cart operations.
 * Provides a singleton interface for managing shopping carts.
 *
 * @package WPDMPP\Cart
 * @since 7.0.0
 */

namespace WPDMPP\Cart;

use WPDMPP\Cart\Storage\OptionsStorage;
use WPDM\__\Session;

defined('ABSPATH') || exit;

class CartService {

    /**
     * Singleton instance
     * @var self|null
     */
    private static ?CartService $instance = null;

    /**
     * Cart storage implementation
     * @var CartStorageInterface
     */
    private CartStorageInterface $storage;

    /**
     * Current cart instance (cached)
     * @var Cart|null
     */
    private ?Cart $currentCart = null;

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
        $this->storage = new OptionsStorage();
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     */
    private function setupHooks(): void {
        // Transfer guest cart to user cart on login
        add_action('wp_login', [$this, 'onUserLogin'], 10, 2);
    }

    /**
     * Set custom storage implementation
     *
     * @param CartStorageInterface $storage
     * @return self
     */
    public function setStorage(CartStorageInterface $storage): self {
        $this->storage = $storage;
        $this->currentCart = null; // Reset cached cart
        return $this;
    }

    /**
     * Get the storage instance
     *
     * @return CartStorageInterface
     */
    public function getStorage(): CartStorageInterface {
        return $this->storage;
    }

    // -------------------------------------------------------------------------
    // Cart ID Management
    // -------------------------------------------------------------------------

    /**
     * Get cart ID for current user/session
     *
     * @return string
     */
    public function getCartId(): string {
        if (is_user_logged_in()) {
            return get_current_user_id() . '_cart';
        }

        return $this->getDeviceId() . '_cart';
    }

    /**
     * Get device ID for guest users
     *
     * @return string
     */
    private function getDeviceId(): string {
        if (class_exists('\WPDM\__\Session')) {
            return Session::deviceID();
        }

        // Fallback if Session class not available
        if (!empty($_COOKIE['__wpdm_client'])) {
            return sanitize_text_field($_COOKIE['__wpdm_client']);
        }

        // Generate a new device ID
        $deviceId = wp_generate_password(32, false);
        setcookie('__wpdm_client', $deviceId, time() + (86400 * 30), COOKIEPATH, COOKIE_DOMAIN);
        return $deviceId;
    }

    // -------------------------------------------------------------------------
    // Cart Operations
    // -------------------------------------------------------------------------

    /**
     * Get the current cart
     *
     * @param bool $fresh Force fresh load from storage
     * @return Cart
     */
    public function getCart(bool $fresh = false): Cart {
        if ($this->currentCart === null || $fresh) {
            $cartId = $this->getCartId();
            $this->currentCart = $this->storage->load($cartId);

            if ($this->currentCart === null) {
                $this->currentCart = new Cart($cartId);
            }
        }

        return $this->currentCart;
    }

    /**
     * Save the current cart
     *
     * @param Cart|null $cart
     * @return bool
     */
    public function saveCart(?Cart $cart = null): bool {
        $cart = $cart ?? $this->currentCart;

        if ($cart === null) {
            return false;
        }

        $result = $this->storage->save($cart);

        if ($result) {
            do_action('wpdmpp_cart_updated', $cart);
        }

        return $result;
    }

    /**
     * Add item to cart
     *
     * @param int $productId
     * @param array $data Item data (license, quantity, extra_gigs, files, etc.)
     * @return Cart
     */
    public function addItem(int $productId, array $data = []): Cart {
        $cart = $this->getCart();

        // A locked cart holds an exclusive recurring subscription (e.g. a
        // membership plan). Regular products and subscriptions can't share a
        // cart, so adding a product replaces the subscription instead of being
        // silently rejected — mirroring addDynamicItem() clearing the cart when
        // a recurring item is added.
        if ($cart->isLocked()) {
            $cart->clear();
        }

        // Build CartItem from data
        $itemData = $this->buildItemData($productId, $data);
        $item = new CartItem($productId, $itemData);

        // Apply filter for extensibility
        $item = apply_filters('wpdmpp_before_addtocart_item', $item, $productId, $data);

        $cart->addItem($item);
        $this->saveCart($cart);

        do_action('wpdmpp_item_added_to_cart', $productId, $item, $cart);

        // Try auto-applying a coupon if none is applied
        $this->tryAutoApplyCoupon();

        return $cart;
    }

    /**
     * Add a dynamic item to cart (non-product items like subscriptions)
     *
     * @param int|string $itemId
     * @param string $name
     * @param float $price
     * @param array $extras
     * @return Cart
     */
    public function addDynamicItem($itemId, string $name, float $price, array $extras = []): Cart {
        $cart = $this->getCart();

        $isRecurring = !empty($extras['recurring']);

        // If recurring, clear cart (lock after adding item)
        if ($isRecurring) {
            $cart->clear();
            $cart->setRecurring(true);
        } elseif ($cart->isLocked()) {
            return $cart;
        }

        $item = new CartItem((int) $itemId, [
            'product_name' => $name,
            'product_type' => 'dynamic',
            'quantity' => 1,
            'price' => $price,
            'license' => [],
            'extra_gigs' => [],
            'files' => [],
            'role_discount' => 0,
            'coupon' => '',
            'coupon_discount' => 0,
            'info' => $extras,
        ]);

        $cart->addItem($item);

        // Lock after adding item so addItem() doesn't reject it
        if ($isRecurring) {
            $cart->lock();
        }

        $this->saveCart($cart);

        do_action('wpdmpp_dynamic_item_added', $itemId, $item, $cart);

        return $cart;
    }

    /**
     * Remove item from cart
     *
     * @param int $productId
     * @return Cart
     */
    public function removeItem(int $productId): Cart {
        $cart = $this->getCart();
        $cart->removeItem($productId);

        if ($cart->isEmpty()) {
            $this->clearCart();
        } else {
            $this->saveCart($cart);
        }

        do_action('wpdmpp_item_removed_from_cart', $productId, $cart);

        return $cart;
    }

    /**
     * Update item quantity
     *
     * @param int $productId
     * @param int $quantity
     * @return Cart
     */
    public function updateItemQuantity(int $productId, int $quantity): Cart {
        $cart = $this->getCart();
        $cart->updateQuantity($productId, $quantity);
        $this->saveCart($cart);

        return $cart;
    }

    /**
     * Update an existing item's data (full data merge)
     *
     * Unlike updateItemQuantity(), this merges arbitrary item data
     * (price, license, coupon, extra gigs, info) into the existing item,
     * mirroring the legacy Cart::updateItem() array_merge semantics. No-op
     * if the product is not in the cart.
     *
     * @param int   $productId Product ID
     * @param array $data      Partial item data to merge over the existing item
     * @return Cart
     */
    public function updateItem(int $productId, array $data = []): Cart {
        $cart = $this->getCart();
        $existing = $cart->getItem($productId);

        if ($existing) {
            $merged = array_merge($existing->toArray(), $data);
            // addItem() replaces the item with the same product ID.
            $cart->addItem(new CartItem($productId, $merged));
            // saveCart() fires the wpdmpp_cart_updated hook.
            $this->saveCart($cart);
        }

        return $cart;
    }

    /**
     * Clear the cart
     *
     * @return bool
     */
    public function clearCart(): bool {
        $cartId = $this->getCartId();
        $result = $this->storage->delete($cartId);

        $this->currentCart = new Cart($cartId);

        // Clear session data
        if (class_exists('\WPDM\__\Session')) {
            if (Session::get('orderid')) {
                Session::set('last_order', Session::get('orderid'));
                Session::clear('orderid');
                Session::clear('tax');
                Session::clear('subtotal');
            }
        }

        do_action('wpdmpp_cart_cleared', $cartId);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Coupon Operations
    // -------------------------------------------------------------------------

    /**
     * Apply coupon to cart
     *
     * @param string $code
     * @param string|null $email Customer email for restricted coupons (falls back to the logged-in user)
     * @return array{success: bool, message: string, discount: float}
     */
    public function applyCoupon(string $code, ?string $email = null): array {
        $cart = $this->getCart();

        // Validate the coupon using CouponService
        $couponService = \WPDMPP\Coupon\CouponService::getInstance();
        $coupon = $couponService->findByCode($code);

        if (!$coupon) {
            return [
                'success' => false,
                'message' => __('Coupon not found', 'wpdm-premium-packages'),
                'discount' => 0,
            ];
        }

        if (($email === null || trim($email) === '') && is_user_logged_in()) {
            $email = wp_get_current_user()->user_email;
        }

        // Plain item arrays so product-specific restrictions and discounts resolve
        $items = [];
        foreach ($cart->getItems() as $productId => $item) {
            $items[$productId] = [
                'price' => $item->getPrice(),
                'quantity' => $item->getQuantity(),
            ];
        }

        $result = $couponService->validateCoupon($code, (float) $cart->getSubtotal(), $items, $email ?: null);
        $discount = $result['discount'] ?? 0;

        if (!$result['valid'] || $discount <= 0) {
            return [
                'success' => false,
                'message' => $result['message'] ?? __('Invalid or expired coupon code', 'wpdm-premium-packages'),
                'discount' => 0,
            ];
        }

        // Remove existing coupon first
        $cart->removeCoupon();

        // Apply the new coupon
        $discountFormatted = $coupon->getType() === 'percent'
            ? $coupon->getDiscount() . '%'
            : wpdmpp_price_format($coupon->getDiscount());

        $note = $coupon->getProductId() > 0
            ? sprintf(
                __('This is a product specific coupon code. %s coupon discount has been applied on %s', 'wpdm-premium-packages'),
                $discountFormatted,
                get_the_title($coupon->getProductId())
            )
            : '';

        $cart->applyCoupon($code, $discount, $coupon->getProductId(), $coupon->getDescription() ?: $note);
        $this->saveCart($cart);

        do_action('wpdmpp_coupon_applied', $code, $discount, $cart);

        return [
            'success' => true,
            'message' => sprintf(__('Coupon applied! You saved %s', 'wpdm-premium-packages'), wpdmpp_price_format($discount)),
            'discount' => $discount,
        ];
    }

    /**
     * Try to auto-apply the best available coupon to the cart
     *
     * Called after cart changes (item added/removed). Skips if a coupon
     * is already applied or the cart is empty.
     *
     * @return void
     */
    public function tryAutoApplyCoupon(): void {
        $cart = $this->getCart();

        // Skip if cart is empty or already has a coupon
        if ($cart->isEmpty() || $cart->getCouponCode() !== '') {
            return;
        }

        $couponService = \WPDMPP\Coupon\CouponService::getInstance();
        $bestCoupon = $couponService->findBestAutoApplyCoupon(
            (float) $cart->getSubtotal(),
            $cart->toLegacy()
        );

        if ($bestCoupon) {
            $this->applyCoupon($bestCoupon->getCode());
        }
    }

    /**
     * Remove coupon from cart
     *
     * @return bool
     */
    public function removeCoupon(): bool {
        $cart = $this->getCart();
        $cart->removeCoupon();
        $this->saveCart($cart);

        do_action('wpdmpp_coupon_removed', $cart);

        return true;
    }

    // -------------------------------------------------------------------------
    // Tax Calculation
    // -------------------------------------------------------------------------

    /**
     * Calculate and apply tax
     *
     * @param string $country
     * @param string $state
     * @return float
     */
    public function calculateTax(string $country, string $state = ''): float {
        $cart = $this->getCart();

        if (!function_exists('get_wpdmpp_option') || !get_wpdmpp_option('tax/enable', 0, 'int')) {
            $cart->setTax(0);
            $this->saveCart($cart);
            return 0;
        }

        $rate = function_exists('wpdmpp_tax_rate') ? wpdmpp_tax_rate($country, $state) : 0;
        $tax = $cart->calculateTax($rate);

        $this->saveCart($cart);

        return $tax;
    }

    // -------------------------------------------------------------------------
    // Cart Lock Management (for subscription flows)
    // -------------------------------------------------------------------------

    /**
     * Lock the cart
     *
     * @return bool
     */
    public function lockCart(): bool {
        $cart = $this->getCart();
        $cart->lock();
        return $this->saveCart($cart);
    }

    /**
     * Unlock the cart
     *
     * @return bool
     */
    public function unlockCart(): bool {
        $cart = $this->getCart();
        $cart->unlock();
        return $this->saveCart($cart);
    }

    /**
     * Check if cart is locked
     *
     * @return bool
     */
    public function isCartLocked(): bool {
        return $this->getCart()->isLocked();
    }

    // -------------------------------------------------------------------------
    // Cart Queries
    // -------------------------------------------------------------------------

    /**
     * Get cart items
     *
     * @return CartItem[]
     */
    public function getItems(): array {
        return $this->getCart()->getItems();
    }

    /**
     * Get item count
     *
     * @return int
     */
    public function getItemCount(): int {
        return $this->getCart()->count();
    }

    /**
     * Get total quantity
     *
     * @return int
     */
    public function getTotalQuantity(): int {
        return $this->getCart()->getTotalQuantity();
    }

    /**
     * Check if cart is empty
     *
     * @return bool
     */
    public function isEmpty(): bool {
        return $this->getCart()->isEmpty();
    }

    /**
     * Get cart subtotal
     *
     * @return float
     */
    public function getSubtotal(): float {
        return $this->getCart()->getSubtotal();
    }

    /**
     * Get cart total
     *
     * @param bool $includeTax
     * @return float
     */
    public function getTotal(bool $includeTax = false): float {
        return $this->getCart()->getTotal($includeTax);
    }

    /**
     * Get cart tax
     *
     * @return float
     */
    public function getTax(): float {
        return $this->getCart()->getTax();
    }

    /**
     * Get total discount
     *
     * @return float
     */
    public function getTotalDiscount(): float {
        return $this->getCart()->getTotalDiscount();
    }

    /**
     * Check if cart is recurring
     *
     * @return bool
     */
    public function isRecurring(): bool {
        $cart = $this->getCart();
        $cartRecurring = $cart->isRecurring();

        // If explicitly set, use that value
        if ($cartRecurring !== null) {
            return $cartRecurring;
        }

        // Otherwise, check system setting
        if (function_exists('get_wpdmpp_option')) {
            return (bool) get_wpdmpp_option('auto_renew', 0, 'int');
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Save/Load Cart
    // -------------------------------------------------------------------------

    /**
     * Save cart to file for later retrieval
     *
     * @return string|false Saved cart ID or false on failure
     */
    public function saveCartToFile() {
        $cart = $this->getCart();

        if ($cart->isEmpty()) {
            return false;
        }

        $cartInfo = [
            'cartitems' => $cart->toLegacy(),
            'coupon' => $cart->getCoupon(),
        ];

        if (!class_exists('\WPDM\__\Crypt')) {
            return false;
        }

        $encrypted = \WPDM\__\Crypt::encrypt($cartInfo);
        $id = uniqid();

        if (!defined('WPDM_CACHE_DIR')) {
            return false;
        }

        file_put_contents(WPDM_CACHE_DIR . 'saved-cart-' . $id . '.txt', $encrypted);

        if (class_exists('\WPDM\__\Session')) {
            Session::set('savedcartid', $id);
        }

        return $id;
    }

    /**
     * Load saved cart from file
     *
     * @param string $savedCartId
     * @return bool
     */
    public function loadSavedCart(string $savedCartId): bool {
        if (!defined('WPDM_CACHE_DIR') || !class_exists('\WPDM\__\Crypt')) {
            return false;
        }

        $cartFile = WPDM_CACHE_DIR . 'saved-cart-' . $savedCartId . '.txt';

        if (!file_exists($cartFile)) {
            return false;
        }

        $encrypted = file_get_contents($cartFile);
        $savedCartData = \WPDM\__\Crypt::decrypt($encrypted, true);

        if (!is_array($savedCartData) || empty($savedCartData)) {
            return false;
        }

        $cartItems = $savedCartData['cartitems'] ?? $savedCartData;
        $couponData = $savedCartData['coupon'] ?? null;

        $cart = Cart::fromLegacy($cartItems, $this->getCartId());

        if ($couponData && !empty($couponData['code'])) {
            $cart->applyCoupon(
                $couponData['code'],
                $couponData['discount'] ?? 0,
                $couponData['product_id'] ?? 0,
                $couponData['note'] ?? ''
            );
        }

        $this->currentCart = $cart;
        $this->saveCart($cart);

        return true;
    }

    // -------------------------------------------------------------------------
    // Event Handlers
    // -------------------------------------------------------------------------

    /**
     * Handle user login - transfer guest cart to user
     *
     * @param string $userLogin
     * @param \WP_User $user
     */
    public function onUserLogin(string $userLogin, \WP_User $user): void {
        $deviceId = $this->getDeviceId();

        if ($this->storage instanceof OptionsStorage) {
            $this->storage->migrateGuestToUser($user->ID, $deviceId);
        } else {
            // Generic transfer for other storage implementations
            $guestCartId = $deviceId . '_cart';
            $userCartId = $user->ID . '_cart';
            $this->storage->transfer($guestCartId, $userCartId);
        }

        // Reset cached cart
        $this->currentCart = null;

        do_action('wpdmpp_cart_transferred_to_user', $user->ID, $deviceId);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    /**
     * Build item data from product and request data
     *
     * @param int $productId
     * @param array $data
     * @return array
     */
    private function buildItemData(int $productId, array $data): array {
        $product = null;
        $productName = get_the_title($productId) ?: '';
        $basePrice = 0;
        $license = [];
        $gigs = [];
        $files = [];
        $roleDiscount = 0;

        // Use Product class if available
        if (class_exists('\WPDMPP\Product\Product')) {
            $product = new \WPDMPP\Product\Product($productId);

            $licenseRaw = $data['license'] ?? '';
            $licenseId = is_array($licenseRaw) ? ($licenseRaw['id'] ?? '') : (string) $licenseRaw;
            // Use effective price so the product-level sale price (__wpdm_sales_price)
            // is honored for non-licensed products. For licensed tiers this still
            // resolves to getLicensePrice(), which has its own per-license sale handling.
            $basePrice = $product->getEffectivePrice($licenseId);
            $license = $product->getLicenseInfo($licenseId);

            // Process extra gigs (variations)
            if (!empty($data['extra_gigs']) && is_array($data['extra_gigs'])) {
                $extraGigs = get_post_meta($productId, '__wpdm_variation', true);
                if (is_array($extraGigs)) {
                    foreach ($extraGigs as $gigGroupId => $gigGroup) {
                        foreach ($gigGroup as $gigId => $gig) {
                            if (in_array($gigId, $data['extra_gigs'])) {
                                $gigs[$gigId] = $gig;
                            }
                        }
                    }
                }
            }

            // Process selected files
            if (!empty($data['files'])) {
                $selectedFiles = is_array($data['files']) ? $data['files'] : explode(',', $data['files']);
                $files = $selectedFiles;

                // Calculate file prices from meta
                $allFiles = get_post_meta($productId, '__wpdm_files', true);
                $filePricesMeta = get_post_meta($productId, '__wpdm_fileprices', true);
                if (is_array($filePricesMeta) && is_array($allFiles)) {
                    $filesPrice = 0;
                    foreach ($selectedFiles as $sf) {
                        $fileIndex = array_search($sf, $allFiles);
                        if ($fileIndex !== false && isset($filePricesMeta[$fileIndex])) {
                            $filesPrice += (float) $filePricesMeta[$fileIndex];
                        }
                    }
                    // If file prices are lower than base price, use file prices
                    if ($filesPrice > 0 && $filesPrice < $basePrice) {
                        $basePrice = $filesPrice;
                    }
                }
            }

            // Calculate role discount
            if (!$product->isPayAsYouWant()) {
                $gigsCost = array_sum(array_column($gigs, 'option_price'));
                $packagePrice = $basePrice + $gigsCost;
                $roleDiscountPercent = $product->getRoleDiscount();
                $roleDiscount = ($packagePrice * $roleDiscountPercent / 100);
            } else {
                // Pay as you want
                $wantToPay = (float) ($data['iwantopay'] ?? 0);
                if ($wantToPay > $basePrice) {
                    $basePrice = $wantToPay;
                }
            }
        } else {
            // Fallback without Product class
            $basePrice = (float) ($data['price'] ?? 0);
            $license = $data['license'] ?? [];
            $gigs = $data['extra_gigs'] ?? [];
            $files = $data['files'] ?? [];
            $roleDiscount = (float) ($data['role_discount'] ?? 0);
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        return [
            'product_name' => $productName,
            'product_type' => $data['product_type'] ?? 'standard',
            'quantity' => $quantity,
            'price' => $basePrice,
            'license' => $license,
            'extra_gigs' => $gigs,
            'files' => $files,
            'role_discount' => $roleDiscount,
            'coupon' => '',
            'coupon_discount' => 0,
            'info' => $data['info'] ?? [],
        ];
    }

    /**
     * Get cart data in format suitable for checkout
     *
     * @return array
     */
    public function getCheckoutData(): array {
        $cart = $this->getCart();

        return [
            'cart_id' => $cart->getCartId(),
            'items' => $cart->toLegacy(),
            'subtotal' => $cart->getSubtotal(),
            'discount' => $cart->getTotalDiscount(),
            'coupon' => $cart->getCoupon(),
            'tax' => $cart->getTax(),
            'total' => $cart->getTotal(),
            'total_with_tax' => $cart->getTotalWithTax(),
            'is_recurring' => $this->isRecurring(),
            'item_count' => $cart->count(),
        ];
    }
}
