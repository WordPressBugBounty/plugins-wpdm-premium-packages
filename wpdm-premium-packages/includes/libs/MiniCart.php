<?php
/**
 * Mini Cart Frontend Handler
 *
 * Handles the frontend display and functionality of the mini cart
 * Supports three display modes: dropdown, slide panel, and floating button
 *
 * @package WPDMPP
 * @since 6.2.0
 */

namespace WPDMPP\Libs;

if (!defined('ABSPATH')) {
    exit;
}

class MiniCart {

    /**
     * Mini cart settings
     * @var array
     */
    private static $settings = null;

    /**
     * Initialize mini cart
     */
    public static function init() {
        // Load settings
        self::$settings = get_option('wpdmpp_mini_cart_settings', MiniCartAPI::getDefaultSettings());

        // Skip if not enabled
        if (!self::isEnabled()) {
            return;
        }

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);

        // Auto-inject if enabled
        if (self::$settings['auto_inject']) {
            add_action('wp_footer', [self::class, 'renderAutoInject']);
        }

        // Register shortcode
        add_shortcode('wpdmpp_mini_cart', [self::class, 'shortcode']);
    }

    /**
     * Check if mini cart is enabled
     *
     * @return bool
     */
    public static function isEnabled() {
        return !empty(self::$settings['enabled']);
    }

    /**
     * Get current settings
     *
     * @return array
     */
    public static function getSettings() {
        if (self::$settings === null) {
            self::$settings = get_option('wpdmpp_mini_cart_settings', MiniCartAPI::getDefaultSettings());
        }
        return self::$settings;
    }

    /**
     * Enqueue mini cart assets
     */
    public static function enqueueAssets() {
        // CSS
        wp_enqueue_style(
            'wpdmpp-mini-cart',
            WPDMPP_BASE_URL . 'assets/css/mini-cart.css',
            [],
            WPDMPP_VERSION
        );

        // Add custom CSS if set
        if (!empty(self::$settings['custom_css'])) {
            wp_add_inline_style('wpdmpp-mini-cart', self::$settings['custom_css']);
        }

        // Add CSS variables for customization
        $custom_vars = sprintf(
            ':root { --wpdmpp-mc-primary: %s; --wpdmpp-mc-text: %s; --wpdmpp-mc-breakpoint: %dpx; }',
            esc_attr(self::$settings['primary_color'] ?? '#6366f1'),
            esc_attr(self::$settings['text_color'] ?? '#1e293b'),
            (int) (self::$settings['mobile_breakpoint'] ?? 768)
        );
        wp_add_inline_style('wpdmpp-mini-cart', $custom_vars);

        // JavaScript
        wp_enqueue_script(
            'wpdmpp-mini-cart',
            WPDMPP_BASE_URL . 'assets/js/mini-cart.js',
            ['jquery'],
            WPDMPP_VERSION,
            true
        );

        // Get initial cart data for nav menu integration
        $cart_items = \wpdmpp_get_cart_items();
        $item_count = 0;
        foreach ($cart_items as $item) {
            $item_count += isset($item['quantity']) ? (int) $item['quantity'] : 1;
        }

        // Localize script
        wp_localize_script('wpdmpp-mini-cart', 'wpdmppMiniCart', [
            'restUrl' => rest_url('wpdmpp/v1/cart'),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => [
                'displayStyle' => self::$settings['display_style'] ?? 'dropdown',
                'position' => self::$settings['position'] ?? 'top-right',
                'autoOpenOnAdd' => self::$settings['auto_open_on_add'] ?? true,
                'autoCloseDelay' => (int) (self::$settings['auto_close_delay'] ?? 3000),
                'mobileFullScreen' => self::$settings['mobile_full_screen'] ?? true,
                'mobileBreakpoint' => (int) (self::$settings['mobile_breakpoint'] ?? 768),
                'triggerSelector' => self::$settings['trigger_selector'] ?? '',
                'showThumbnails' => self::$settings['show_thumbnails'] ?? true,
                'showItemCount' => self::$settings['show_item_count'] ?? true,
                'showSubtotal' => self::$settings['show_subtotal'] ?? true,
            ],
            'cartData' => [
                'item_count' => $item_count,
                'total_formatted' => \wpdmpp_price_format(\wpdmpp_get_cart_total()),
                'is_empty' => empty($cart_items),
            ],
            'cartUrl' => \wpdmpp_cart_page(),
            'checkoutUrl' => \wpdmpp_cart_page(['step' => 'checkout']),
            'strings' => [
                'cartTitle' => __('Shopping Cart', 'wpdm-premium-packages'),
                'emptyCart' => __('Your cart is empty', 'wpdm-premium-packages'),
                'subtotal' => __('Subtotal', 'wpdm-premium-packages'),
                'viewCart' => __('View Cart', 'wpdm-premium-packages'),
                'checkout' => __('Checkout', 'wpdm-premium-packages'),
                'remove' => __('Remove', 'wpdm-premium-packages'),
                'close' => __('Close', 'wpdm-premium-packages'),
                'loading' => __('Loading...', 'wpdm-premium-packages'),
                'itemAdded' => __('Item added to cart', 'wpdm-premium-packages'),
                'itemRemoved' => __('Item removed', 'wpdm-premium-packages'),
                'cartUpdated' => __('Cart updated', 'wpdm-premium-packages'),
                'continueShopping' => __('Continue Shopping', 'wpdm-premium-packages'),
            ],
        ]);
    }

    /**
     * Render auto-injected mini cart
     */
    public static function renderAutoInject() {
        echo self::render([
            'display_style' => self::$settings['display_style'],
            'auto_injected' => true,
        ]);
    }

    /**
     * Shortcode handler
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function shortcode($atts = []) {
        $atts = shortcode_atts([
            'display_style' => self::$settings['display_style'] ?? 'dropdown',
            'position' => self::$settings['position'] ?? 'top-right',
            'show_count' => 'yes',
            'show_total' => 'yes',
            'icon' => self::$settings['icon_style'] ?? 'cart',
            'class' => '',
        ], $atts, 'wpdmpp_mini_cart');

        return self::render($atts);
    }

    /**
     * Render mini cart HTML
     *
     * @param array $args Render arguments
     * @return string HTML
     */
    public static function render($args = []) {
        $defaults = [
            'display_style' => 'dropdown',
            'position' => 'top-right',
            'show_count' => 'yes',
            'show_total' => 'yes',
            'icon' => 'cart',
            'class' => '',
            'auto_injected' => false,
        ];

        $args = wp_parse_args($args, $defaults);
        $settings = self::getSettings();

        // Get initial cart data
        $cart_items = \wpdmpp_get_cart_data();
        $item_count = 0;
        if (is_array($cart_items)) {
            foreach ($cart_items as $item) {
                $item_count += isset($item['quantity']) ? (int) $item['quantity'] : 1;
            }
        }
        $cart_total = \wpdmpp_get_cart_total();

        // Build CSS classes
        $wrapper_classes = [
            'wpdmpp-mini-cart',
            'wpdmpp-mc-' . esc_attr($args['display_style']),
            'wpdmpp-mc-pos-' . esc_attr($args['position']),
        ];

        if ($args['auto_injected']) {
            $wrapper_classes[] = 'wpdmpp-mc-auto-injected';
        }

        if (!empty($args['class'])) {
            $wrapper_classes[] = esc_attr($args['class']);
        }

        if ($settings['mobile_full_screen']) {
            $wrapper_classes[] = 'wpdmpp-mc-mobile-fullscreen';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" data-style="<?php echo esc_attr($args['display_style']); ?>">
            <!-- Trigger Button -->
            <button type="button" class="wpdmpp-mc-trigger" aria-label="<?php esc_attr_e('Shopping Cart', 'wpdm-premium-packages'); ?>" aria-expanded="false">
                <span class="wpdmpp-mc-icon">
                    <?php echo self::getIcon($args['icon']); ?>
                </span>
                <?php if ($args['show_count'] === 'yes' && $settings['show_item_count']): ?>
                    <span class="wpdmpp-mc-count <?php echo $item_count === 0 ? 'wpdmpp-mc-count--empty' : ''; ?>" data-count="<?php echo esc_attr($item_count); ?>">
                        <?php echo esc_html($item_count); ?>
                    </span>
                <?php endif; ?>
                <?php if ($args['show_total'] === 'yes' && $settings['show_subtotal']): ?>
                    <span class="wpdmpp-mc-total">
                        <?php echo \wpdmpp_price_format($cart_total); ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Cart Panel -->
            <div class="wpdmpp-mc-panel" aria-hidden="true">
                <!-- Panel Header -->
                <div class="wpdmpp-mc-panel-header">
                    <h3 class="wpdmpp-mc-panel-title">
                        <?php esc_html_e('Shopping Cart', 'wpdm-premium-packages'); ?>
                        <span class="wpdmpp-mc-panel-count">(<?php echo esc_html($item_count); ?>)</span>
                    </h3>
                    <button type="button" class="wpdmpp-mc-close" aria-label="<?php esc_attr_e('Close cart', 'wpdm-premium-packages'); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <!-- Panel Body (Items) -->
                <div class="wpdmpp-mc-panel-body">
                    <div class="wpdmpp-mc-items">
                        <?php if ($item_count > 0): ?>
                            <?php echo self::renderCartItems($cart_items, $settings); ?>
                        <?php else: ?>
                            <div class="wpdmpp-mc-empty">
                                <div class="wpdmpp-mc-empty-icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="9" cy="21" r="1"></circle>
                                        <circle cx="20" cy="21" r="1"></circle>
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                    </svg>
                                </div>
                                <p class="wpdmpp-mc-empty-text"><?php esc_html_e('Your cart is empty', 'wpdm-premium-packages'); ?></p>
                                <a href="<?php echo esc_url(\wpdmpp_continue_shopping_url()); ?>" class="wpdmpp-mc-btn wpdmpp-mc-btn--secondary">
                                    <?php esc_html_e('Continue Shopping', 'wpdm-premium-packages'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Panel Footer -->
                <?php if ($item_count > 0): ?>
                    <div class="wpdmpp-mc-panel-footer">
                        <div class="wpdmpp-mc-subtotal">
                            <span class="wpdmpp-mc-subtotal-label"><?php esc_html_e('Subtotal', 'wpdm-premium-packages'); ?></span>
                            <span class="wpdmpp-mc-subtotal-value"><?php echo \wpdmpp_price_format($cart_total); ?></span>
                        </div>
                        <div class="wpdmpp-mc-actions">
                            <a href="<?php echo esc_url(\wpdmpp_cart_page()); ?>" class="wpdmpp-mc-btn wpdmpp-mc-btn--secondary">
                                <?php esc_html_e('View Cart', 'wpdm-premium-packages'); ?>
                            </a>
                            <a href="<?php echo esc_url(\wpdmpp_cart_page(['step' => 'checkout'])); ?>" class="wpdmpp-mc-btn wpdmpp-mc-btn--primary">
                                <?php esc_html_e('Checkout', 'wpdm-premium-packages'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Overlay for slide panel and mobile -->
            <div class="wpdmpp-mc-overlay"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render cart items
     *
     * @param array $cart_items Cart data
     * @param array $settings Mini cart settings
     * @return string HTML
     */
    private static function renderCartItems($cart_items, $settings) {
        if (!is_array($cart_items) || empty($cart_items)) {
            return '';
        }

        $currency_sign = \wpdmpp_currency_sign();
        $html = '';

        foreach ($cart_items as $pid => $item) {
            if (!$pid || get_post_type($pid) !== 'wpdmpro') {
                continue;
            }

            $post = get_post($pid);
            if (!$post) {
                continue;
            }

            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
            $price = isset($item['price']) ? (float) $item['price'] : 0;
            $prices = isset($item['prices']) ? (float) $item['prices'] : 0;
            $unit_price = $price + $prices;
            $line_total = $unit_price * $quantity;

            // Get thumbnail
            $thumbnail_url = '';
            if ($settings['show_thumbnails']) {
                $thumbnail_id = get_post_thumbnail_id($pid);
                $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'thumbnail') : '';
                if (!$thumbnail_url) {
                    $icon = get_post_meta($pid, '__wpdm_icon', true);
                    $thumbnail_url = $icon ?: '';
                }
            }

            $html .= '<div class="wpdmpp-mc-item" data-product-id="' . esc_attr($pid) . '">';

            if ($thumbnail_url) {
                $html .= '<div class="wpdmpp-mc-item-thumb">';
                $html .= '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($post->post_title) . '">';
                $html .= '</div>';
            }

            $html .= '<div class="wpdmpp-mc-item-details">';
            $html .= '<a href="' . esc_url(get_permalink($pid)) . '" class="wpdmpp-mc-item-name">' . esc_html($post->post_title) . '</a>';
            $html .= '<div class="wpdmpp-mc-item-meta">';
            $html .= '<span class="wpdmpp-mc-item-qty">' . esc_html($quantity) . ' &times; </span>';
            $html .= '<span class="wpdmpp-mc-item-price">' . \wpdmpp_price_format($unit_price) . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div class="wpdmpp-mc-item-actions">';
            $html .= '<span class="wpdmpp-mc-item-total">' . \wpdmpp_price_format($line_total) . '</span>';
            $html .= '<button type="button" class="wpdmpp-mc-item-remove" data-product-id="' . esc_attr($pid) . '" aria-label="' . esc_attr__('Remove item', 'wpdm-premium-packages') . '">';
            $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            $html .= '</button>';
            $html .= '</div>';

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Get cart icon SVG
     *
     * @param string $style Icon style (cart, bag, basket)
     * @return string SVG HTML
     */
    private static function getIcon($style = 'cart') {
        $icons = [
            'cart' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',

            'bag' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>',

            'basket' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.757 1.071a.5.5 0 0 1 .172.686L3.383 6h17.234l-2.546-4.243a.5.5 0 1 1 .858-.514l2.909 4.848A1 1 0 0 1 21 7.5H3a1 1 0 0 1-.838-1.409l2.909-4.848a.5.5 0 0 1 .686-.172z"></path><path d="M3 8h18l-1.5 10a2 2 0 0 1-2 1.5H6.5a2 2 0 0 1-2-1.5L3 8z"></path><line x1="12" y1="12" x2="12" y2="16"></line><line x1="8" y1="12" x2="8" y2="16"></line><line x1="16" y1="12" x2="16" y2="16"></line></svg>',
        ];

        return isset($icons[$style]) ? $icons[$style] : $icons['cart'];
    }
}

// Initialize
add_action('init', [MiniCart::class, 'init']);
