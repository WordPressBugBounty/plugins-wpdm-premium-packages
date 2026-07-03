<?php
/**
 * Legacy Cart Class
 *
 * Provides backward compatibility bridge to new CartService.
 *
 * @package WPDMPP\Libs
 * @deprecated 7.0.0 Use \WPDMPP\Cart\CartService instead
 */

namespace WPDMPP\Libs;

use WPDMPP\Cart\CartService;

if (!defined('ABSPATH')) {
    exit;
}

class Cart
{
    /**
     * Cart data (lazy loaded)
     *
     * @var array|null
     */
    private $cart = null;

    /**
     * Constructor
     * Note: Cart data is lazy-loaded to avoid calling WordPress functions
     * before they are available (pluggable.php loads after plugins)
     */
    public function __construct()
    {
        // Don't load cart here - defer to when it's actually needed
    }

    /**
     * Get the new CartService instance
     *
     * @return CartService|null
     */
    public static function service(): ?CartService
    {
        if (class_exists(CartService::class)) {
            return CartService::instance();
        }
        return null;
    }

    /**
     * Get cart data
     *
     * @return array
     */
    public function getCart(): array
    {
        $service = self::service();
        if ($service) {
            $items = $service->getItems();
            // Convert CartItem objects to arrays for backward compatibility
            $result = [];
            foreach ($items as $key => $item) {
                if (is_object($item) && method_exists($item, 'toArray')) {
                    $result[$key] = $item->toArray();
                } else {
                    $result[$key] = $item;
                }
            }
            return $result;
        }

        // Lazy load cart data for fallback
        if ($this->cart === null) {
            $this->cart = get_option('__wpdmpp_cart_' . $this->getCartId(), []);
            if (!is_array($this->cart)) {
                $this->cart = [];
            }
        }

        return $this->cart;
    }

    /**
     * Get cart items (alias for getCart)
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->getCart();
    }

    /**
     * Get cart ID
     *
     * @return string
     */
    private function getCartId(): string
    {
        // Safety check - is_user_logged_in() may not be available during early plugin load
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        if (!isset($_COOKIE['__wpdm_client'])) {
            return 'guest_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        }

        return 'guest_' . sanitize_text_field($_COOKIE['__wpdm_client']);
    }

    /**
     * Add item to cart
     *
     * Legacy signature: addItem($productId, $license, $data)
     * New signature: addItem($productId, $data)
     *
     * @param int          $productId
     * @param string|array $licenseOrData License string or data array
     * @param array        $data          Data array (when license is provided)
     * @return array
     */
    public function addItem($productId, $licenseOrData = [], $data = []): array
    {
        // Handle legacy 3-argument signature: addItem($productId, $license, $data)
        if (is_string($licenseOrData)) {
            $data['license'] = $licenseOrData;
        } elseif (is_array($licenseOrData)) {
            $data = $licenseOrData;
        }

        $service = self::service();
        if ($service) {
            $item = $service->addItem($productId, $data);
            return $this->getCart(); // Return full cart as array
        }

        // Ensure cart is loaded
        $this->getCart();
        $this->cart[$productId] = $data;
        $this->saveCart();
        return $this->cart;
    }

    /**
     * Remove item from cart
     *
     * @param int $productId
     * @return array
     */
    public function removeItem($productId): array
    {
        $service = self::service();
        if ($service) {
            $service->removeItem($productId);
            return $service->getItems();
        }

        // Ensure cart is loaded
        $this->getCart();
        unset($this->cart[$productId]);
        $this->saveCart();
        return $this->cart;
    }

    /**
     * Clear cart
     */
    public function clear(): void
    {
        $service = self::service();
        if ($service) {
            $service->clearCart();
            return;
        }

        $this->cart = [];
        delete_option('__wpdmpp_cart_' . $this->getCartId());
    }

    /**
     * Check if cart is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        $service = self::service();
        if ($service) {
            return $service->isEmpty();
        }

        return empty($this->getCart());
    }

    /**
     * Get cart total
     *
     * @param bool $withDiscount
     * @param bool $withTax
     * @param bool $formatted Whether to return formatted price
     * @return float|string
     */
    public function cartTotal($withDiscount = true, $withTax = false, $formatted = false)
    {
        $service = self::service();
        if ($service) {
            if ($withDiscount) {
                $total = $service->getTotal($withTax);
            } else {
                // Return raw subtotal without coupon/role discounts
                $cart = $service->getCart();
                $total = $cart->getSubtotal();
                if ($withTax) {
                    $total += $cart->getTax();
                }
            }
        } else {
            $total = 0;
            $cart = $this->getCart();
            foreach ($cart as $item) {
                $price = (float) ($item['price'] ?? 0);
                $prices = (float) ($item['prices'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 1);
                $total += ($price + $prices) * $quantity;
            }

            if ($withDiscount) {
                $total -= $this->couponDiscount();
            }

            $total = max(0, $total);
        }

        if ($formatted) {
            return wpdmpp_price_format($total);
        }

        return $total;
    }

    /**
     * Get coupon discount
     *
     * @return float
     */
    public function couponDiscount(): float
    {
        $service = self::service();
        if ($service) {
            $cart = $service->getCart();
            return $cart ? $cart->getCouponDiscount() : 0;
        }

        $coupon = get_option('__wpdmpp_cart_coupon_' . $this->getCartId(), []);
        return (float) ($coupon['discount'] ?? 0);
    }

    /**
     * Calculate tax
     *
     * @param float  $subtotal
     * @param string $country
     * @param string $state
     * @return float
     */
    public function calculateTax($subtotal, $country = '', $state = ''): float
    {
        $service = self::service();
        if ($service) {
            return $service->calculateTax($country, $state);
        }

        // Basic tax calculation fallback
        $taxRate = (float) get_wpdmpp_option('tax_rate', 0);
        return $subtotal * ($taxRate / 100);
    }

    /**
     * Save cart to storage
     */
    private function saveCart(): void
    {
        update_option('__wpdmpp_cart_' . $this->getCartId(), $this->cart, false);
    }

    /**
     * Get cart items count
     *
     * @return int
     */
    public function getItemCount(): int
    {
        $service = self::service();
        if ($service) {
            return $service->getItemCount();
        }

        return count($this->getCart());
    }

    /**
     * Get cart ID
     *
     * @return string
     */
    public function getID(): string
    {
        $service = self::service();
        if ($service) {
            $cart = $service->getCart();
            return $cart ? $cart->getCartId() : $this->getCartId();
        }

        return $this->getCartId();
    }

    /**
     * Get applied coupon or specific coupon field
     *
     * @param string|null $field Optional field to retrieve (e.g., 'code', 'discount')
     * @return array|string|null
     */
    public function getCoupon($field = null)
    {
        $service = self::service();
        if ($service) {
            $cart = $service->getCart();
            $coupon = $cart ? $cart->getCoupon() : null;
        } else {
            // Fallback to option storage
            $coupon = get_option('__wpdmpp_cart_coupon_' . $this->getCartId(), null);
            $coupon = is_array($coupon) ? $coupon : null;
        }

        // If specific field requested, return that field
        if ($field !== null && is_array($coupon)) {
            return $coupon[$field] ?? null;
        }

        return $coupon;
    }

    /**
     * Apply coupon to cart
     *
     * @param string $code Coupon code
     * @return array|bool Result or false on failure
     */
    public function applyCoupon(string $code)
    {
        $service = self::service();
        if ($service) {
            return $service->applyCoupon($code);
        }

        // Basic fallback - store coupon code
        $coupon = ['code' => $code, 'discount' => 0];
        update_option('__wpdmpp_cart_coupon_' . $this->getCartId(), $coupon, false);
        return $coupon;
    }

    /**
     * Clear applied coupon
     *
     * @return void
     */
    public function clearCoupon(): void
    {
        $service = self::service();
        if ($service) {
            $service->removeCoupon();
            return;
        }

        delete_option('__wpdmpp_cart_coupon_' . $this->getCartId());
    }

    /**
     * Check if cart is locked
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        $service = self::service();
        if ($service) {
            $cart = $service->getCart();
            return $cart ? $cart->isLocked() : false;
        }

        // Fallback - check option
        return (bool) get_option('__wpdmpp_cart_locked_' . $this->getCartId(), false);
    }

    /**
     * Check if cart contains recurring/subscription items
     *
     * @return bool
     */
    public function isRecurring(): bool
    {
        $service = self::service();
        if ($service) {
            return $service->isRecurring();
        }

        // Fallback: check items for recurring flag
        $cart = $this->getCart();
        foreach ($cart as $item) {
            if (!empty($item['recurring']) || !empty($item['subscription'])) {
                return true;
            }
        }

        return (bool) get_wpdmpp_option('auto_renew', 0);
    }

    /**
     * Get cart tax amount
     *
     * @return float
     */
    public function getTax(): float
    {
        $service = self::service();
        if ($service) {
            $cart = $service->getCart();
            if ($cart && method_exists($cart, 'getTax')) {
                return $cart->getTax();
            }
        }

        // Fallback - get from stored value or calculate
        $storedTax = get_option('__wpdmpp_cart_tax_' . $this->getCartId(), null);
        if ($storedTax !== null) {
            return (float) $storedTax;
        }

        // Calculate based on settings
        $subtotal = $this->cartTotal(false, false);
        return $this->calculateTax($subtotal);
    }

    /**
     * Set cart tax amount
     *
     * @param float $tax
     * @return void
     */
    public function setTax(float $tax): void
    {
        $service = self::service();
        if ($service) {
            $cart = $service->getCart();
            if ($cart && method_exists($cart, 'setTax')) {
                $cart->setTax($tax);
                return;
            }
        }

        update_option('__wpdmpp_cart_tax_' . $this->getCartId(), $tax, false);
    }

    /**
     * Update an item in the cart
     *
     * @param int   $productId Product ID
     * @param array $data      Item data to update
     * @return array Updated cart
     */
    public function updateItem($productId, array $data = []): array
    {
        $service = self::service();
        if ($service) {
            $service->updateItem($productId, $data);
            return $service->getItems();
        }

        // Ensure cart is loaded
        $this->getCart();
        if (isset($this->cart[$productId])) {
            $this->cart[$productId] = array_merge($this->cart[$productId], $data);
            $this->saveCart();
        }
        return $this->cart;
    }

    /**
     * Add a dynamic item to cart (not from product catalog)
     *
     * Legacy signature: addDynamicItem($key, $name, $price, $data)
     * New signature: addDynamicItem($key, $data)
     *
     * @param string       $key           Unique key for the item
     * @param string|array $nameOrData    Name string or data array
     * @param float        $price         Price (when name is provided)
     * @param array        $data          Data array (when name/price provided)
     * @return array Updated cart
     */
    public function addDynamicItem($key, $nameOrData = [], $price = 0, $data = []): array
    {
        // Normalize both call styles into (name, price, extras):
        //   legacy: addDynamicItem($key, $name, $price, $data)
        //   array:  addDynamicItem($key, $dataArray)
        if (is_string($nameOrData)) {
            $name   = $nameOrData;
            $extras = is_array($data) ? $data : [];
        } else {
            $arr    = is_array($nameOrData) ? $nameOrData : [];
            $name   = (string) ($arr['product_name'] ?? $arr['name'] ?? '');
            $price  = $arr['price'] ?? $price;
            $extras = $arr;
        }

        $service = self::service();
        if ($service) {
            // CartService::addDynamicItem($itemId, string $name, float $price, array $extras)
            $service->addDynamicItem($key, (string) $name, (float) $price, (array) $extras);
            return $this->getCart();
        }

        // No new service available — store a raw cart entry.
        $this->getCart();
        $this->cart[$key] = array_merge((array) $extras, [
            'product_name' => (string) $name,
            'name'         => (string) $name,
            'price'        => (float) $price,
        ]);
        $this->saveCart();
        return $this->cart;
    }

    /**
     * Update/save the cart
     *
     * @param array|null $data Optional cart data to save
     * @return void
     */
    public function update(?array $data = null): void
    {
        $service = self::service();
        if ($service) {
            if ($data !== null) {
                // Update service with provided data
                foreach ($data as $productId => $itemData) {
                    $service->updateItem($productId, $itemData);
                }
            }
            return;
        }

        // Fallback
        if ($data !== null) {
            $this->cart = $data;
        }
        $this->saveCart();
    }

    /**
     * Render cart HTML
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render(array $atts = []): string
    {
        ob_start();

        $cart_data = $this->getCart();

        if (empty($cart_data)) {
            include wpdm_tpl_path('checkout-cart/cart-empty.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
        } else {
            include wpdm_tpl_path('checkout-cart/cart.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
        }

        return ob_get_clean();
    }

    /**
     * Output item thumbnail
     *
     * @param array $item Cart item data
     * @param bool $echo Whether to echo (true) or return (false)
     * @param array $attrs Additional attributes for the image
     * @return string|void
     */
    public function itemThumb($item, $echo = true, $attrs = [])
    {
        $pid = isset($item['product_id']) ? $item['product_id'] : (isset($item['pid']) ? $item['pid'] : 0);
        if (!$pid) {
            return $echo ? null : '';
        }

        // Merge default attrs with provided attrs
        $defaultAttrs = ['class' => 'wpdmpp-cart-item-thumb', 'style' => 'width:48px;height:auto;border-radius:4px;'];
        $imgAttrs = array_merge($defaultAttrs, $attrs);

        $thumb = get_the_post_thumbnail($pid, 'thumbnail', $imgAttrs);
        if (!$thumb) {
            $icon = get_post_meta($pid, '__wpdm_icon', true);
            if ($icon) {
                $attrStr = '';
                foreach ($imgAttrs as $key => $val) {
                    $attrStr .= ' ' . esc_attr($key) . '="' . esc_attr($val) . '"';
                }
                $thumb = '<img src="' . esc_url($icon) . '"' . $attrStr . ' alt="">';
            }
        }

        if ($echo) {
            echo $thumb;
            return;
        }
        return $thumb;
    }

    /**
     * Output item link
     *
     * @param array $item Cart item data
     * @param bool $echo Whether to echo (true) or return (false)
     * @return string|void
     */
    public function itemLink($item, $echo = true)
    {
        $pid = isset($item['product_id']) ? $item['product_id'] : (isset($item['pid']) ? $item['pid'] : 0);
        $name = isset($item['product_name']) ? $item['product_name'] : (isset($item['name']) ? $item['name'] : '');

        if ($pid && get_post($pid)) {
            $name = $name ?: get_the_title($pid);
            $html = '<a href="' . esc_url(get_permalink($pid)) . '" class="wpdmpp-cart-item-title">' . esc_html($name) . '</a>';
        } else {
            $html = '<span class="wpdmpp-cart-item-title">' . esc_html($name) . '</span>';
        }

        if ($echo) {
            echo $html;
            return;
        }
        return $html;
    }

    /**
     * Output item info (license, variations, etc.)
     *
     * @param array $item Cart item data
     * @param bool $echo Whether to echo (true) or return (false)
     * @return string|void
     */
    public function itemInfo($item, $echo = true)
    {
        $info = [];

        // License info - unserialize if needed (data from database may be serialized)
        if (!empty($item['license'])) {
            $license = maybe_unserialize($item['license']);
            if (is_array($license) && isset($license['info']['name'])) {
                $info[] = '<small class="text-muted">' . esc_html($license['info']['name']) . '</small>';
            } elseif (is_array($license) && isset($license['id'])) {
                $info[] = '<small class="text-muted">' . esc_html(ucfirst($license['id'])) . '</small>';
            } elseif (is_string($license) && !is_serialized($license)) {
                // Only show string if it's not still serialized
                $info[] = '<small class="text-muted">' . esc_html(ucfirst($license)) . '</small>';
            }
        }

        // Extra gigs - unserialize if needed
        $extra_gigs = !empty($item['extra_gigs']) ? maybe_unserialize($item['extra_gigs']) : [];
        if (!empty($extra_gigs) && is_array($extra_gigs)) {
            foreach ($extra_gigs as $gig) {
                if (isset($gig['name'])) {
                    $info[] = '<small class="text-muted">+ ' . esc_html($gig['name']) . '</small>';
                }
            }
        }

        $html = '';
        if (!empty($info)) {
            $html = '<div class="wpdmpp-cart-item-info">' . implode('<br>', $info) . '</div>';
        }

        if ($echo) {
            echo $html;
            return;
        }
        return $html;
    }

    /**
     * Get item line total (price + gigs) * quantity - discounts
     *
     * This matches the legacy implementation which calculates the line total
     * including quantity and all discounts.
     *
     * @param array $item Cart item data
     * @param bool $formatted Whether to return formatted price
     * @return float|string
     */
    public function itemCost($item, $formatted = false)
    {
        $price = (float) ($item['price'] ?? 0);
        $gigsCost = (float) ($item['prices'] ?? $item['gigs_cost'] ?? 0);

        // If gigs_cost not set, calculate from extra_gigs array
        if ($gigsCost === 0.0 && !empty($item['extra_gigs']) && is_array($item['extra_gigs'])) {
            foreach ($item['extra_gigs'] as $gig) {
                $gigsCost += (float) ($gig['option_price'] ?? $gig['price'] ?? 0);
            }
        }

        $quantity = (int) ($item['quantity'] ?? 1);
        $cost = ($price + $gigsCost) * $quantity;

        // Apply role discount
        $roleDiscount = (float) ($item['discount_amount'] ?? $item['role_discount'] ?? 0);
        $cost -= $roleDiscount;

        // Apply coupon discount
        $couponDiscount = (float) ($item['coupon_discount'] ?? $item['coupon_amount'] ?? 0);
        $cost -= $couponDiscount;

        $cost = max(0, $cost);

        if ($formatted) {
            return wpdmpp_price_format($cost);
        }

        return $cost;
    }

    /**
     * Get item unit cost (price + gigs cost, without quantity or discounts)
     *
     * @param array $item Cart item data
     * @param bool $formatted Whether to return formatted price
     * @return float|string
     */
    public function itemUnitCost($item, $formatted = false)
    {
        $price = (float) ($item['price'] ?? 0);
        $gigsCost = (float) ($item['prices'] ?? $item['gigs_cost'] ?? 0);

        // If gigs_cost not set, calculate from extra_gigs array
        if ($gigsCost === 0.0 && !empty($item['extra_gigs']) && is_array($item['extra_gigs'])) {
            foreach ($item['extra_gigs'] as $gig) {
                $gigsCost += (float) ($gig['option_price'] ?? $gig['price'] ?? 0);
            }
        }

        $cost = $price + $gigsCost;

        if ($formatted) {
            return wpdmpp_price_format($cost);
        }

        return $cost;
    }

    /**
     * Calculate total cost of all gigs in extra_gigs array
     *
     * @param array $extraGigs Array of extra gigs
     * @return float
     */
    public function gigsCost($extraGigs): float
    {
        if (!is_array($extraGigs)) {
            return 0.0;
        }

        $cost = 0.0;
        foreach ($extraGigs as $gig) {
            $cost += (float) ($gig['option_price'] ?? $gig['price'] ?? 0);
        }

        return $cost;
    }

    /**
     * Handle user login - transfer guest cart to user cart
     *
     * This method is called by the wp_login hook registered in the main plugin file.
     * It delegates to CartService::onUserLogin() to transfer any guest cart items
     * to the logged-in user's cart.
     *
     * @param string   $userLogin Username
     * @param \WP_User $user      User object
     * @return void
     */
    public function onUserLogin($userLogin, $user): void
    {
        $service = self::service();
        if ($service) {
            $service->onUserLogin($userLogin, $user);
        }
    }
}
