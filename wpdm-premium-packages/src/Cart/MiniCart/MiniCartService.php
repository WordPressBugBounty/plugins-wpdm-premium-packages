<?php
/**
 * Mini Cart Service
 *
 * Handles mini cart frontend display, settings management, and asset enqueuing.
 * Supports three display modes: dropdown, slide panel, and floating button.
 *
 * @package WPDMPP\Cart\MiniCart
 * @since 7.0.0
 */

namespace WPDMPP\Cart\MiniCart;

use WPDMPP\Cart\CartService;

defined('ABSPATH') || exit;

class MiniCartService {

    /**
     * Singleton instance
     *
     * @var MiniCartService|null
     */
    private static ?MiniCartService $instance = null;

    /**
     * Mini cart settings
     *
     * @var array|null
     */
    private ?array $settings = null;

    /**
     * Whether service has been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Option name for settings
     */
    public const OPTION_KEY = 'wpdmpp_mini_cart_settings';

    /**
     * Display styles
     */
    public const STYLE_DROPDOWN = 'dropdown';
    public const STYLE_SLIDE_PANEL = 'slide_panel';
    public const STYLE_FLOATING = 'floating';

    /**
     * Positions
     */
    public const POSITION_TOP_RIGHT = 'top-right';
    public const POSITION_TOP_LEFT = 'top-left';
    public const POSITION_BOTTOM_RIGHT = 'bottom-right';
    public const POSITION_BOTTOM_LEFT = 'bottom-left';

    /**
     * Icon styles
     */
    public const ICON_CART = 'cart';
    public const ICON_BAG = 'bag';
    public const ICON_BASKET = 'basket';

    /**
     * Get singleton instance
     *
     * @return MiniCartService
     */
    public static function getInstance(): MiniCartService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {}

    /**
     * Register hooks for frontend display
     */
    public function register(): void {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        // Register admin REST routes for settings
        add_action('rest_api_init', [$this, 'registerSettingsRoutes']);

        // Skip frontend hooks if not enabled
        if (!$this->isEnabled()) {
            return;
        }

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // Auto-inject if enabled
        $settings = $this->getSettings();
        if (!empty($settings['auto_inject'])) {
            add_action('wp_footer', [$this, 'renderAutoInject']);
        }

        // Register shortcode
        add_shortcode('wpdmpp_mini_cart', [$this, 'shortcode']);
    }

    /**
     * Register REST API routes for mini cart settings (admin)
     */
    public function registerSettingsRoutes(): void {
        $namespace = 'wpdmpp/v1';

        register_rest_route($namespace, '/mini-cart/settings', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'restGetSettings'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route($namespace, '/mini-cart/settings', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'restUpdateSettings'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * REST: Get mini cart settings
     *
     * @return \WP_REST_Response
     */
    public function restGetSettings(): \WP_REST_Response {
        return new \WP_REST_Response([
            'success' => true,
            'data' => $this->getSettings(),
        ], 200);
    }

    /**
     * REST: Update mini cart settings
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function restUpdateSettings(\WP_REST_Request $request): \WP_REST_Response {
        $settings = $request->get_json_params();
        $result = $this->saveSettings($settings);

        return new \WP_REST_Response([
            'success' => $result,
            'message' => $result
                ? __('Settings saved.', 'wpdm-premium-packages')
                : __('Failed to save settings.', 'wpdm-premium-packages'),
            'data' => $this->getSettings(),
        ], $result ? 200 : 500);
    }

    /**
     * Check if mini cart is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool {
        $settings = $this->getSettings();
        return !empty($settings['enabled']);
    }

    /**
     * Get current settings
     *
     * @return array
     */
    public function getSettings(): array {
        if ($this->settings === null) {
            $this->settings = get_option(self::OPTION_KEY, $this->getDefaultSettings());
        }
        return $this->settings;
    }

    /**
     * Get default settings
     *
     * @return array
     */
    public function getDefaultSettings(): array {
        return [
            'enabled' => true,
            'display_style' => self::STYLE_DROPDOWN,
            'position' => self::POSITION_TOP_RIGHT,
            'show_item_count' => true,
            'show_subtotal' => true,
            'show_thumbnails' => true,
            'auto_open_on_add' => true,
            'auto_close_delay' => 3000,
            'mobile_full_screen' => true,
            'mobile_breakpoint' => 768,
            'trigger_selector' => '',
            'auto_inject' => false,
            'auto_inject_position' => 'header',
            'custom_css' => '',
            'icon_style' => self::ICON_CART,
            'primary_color' => '#6366f1',
            'text_color' => '#1e293b',
        ];
    }

    /**
     * Save settings
     *
     * @param array $settings Settings data
     * @return bool Success
     */
    public function saveSettings(array $settings): bool {
        $defaults = $this->getDefaultSettings();

        // Merge with defaults to ensure all keys exist
        $settings = wp_parse_args($settings, $defaults);

        // Sanitize settings
        $sanitized = [
            'enabled' => (bool) $settings['enabled'],
            'display_style' => in_array($settings['display_style'], [self::STYLE_DROPDOWN, self::STYLE_SLIDE_PANEL, self::STYLE_FLOATING])
                ? $settings['display_style']
                : self::STYLE_DROPDOWN,
            'position' => in_array($settings['position'], [self::POSITION_TOP_RIGHT, self::POSITION_TOP_LEFT, self::POSITION_BOTTOM_RIGHT, self::POSITION_BOTTOM_LEFT])
                ? $settings['position']
                : self::POSITION_TOP_RIGHT,
            'show_item_count' => (bool) $settings['show_item_count'],
            'show_subtotal' => (bool) $settings['show_subtotal'],
            'show_thumbnails' => (bool) $settings['show_thumbnails'],
            'auto_open_on_add' => (bool) $settings['auto_open_on_add'],
            'auto_close_delay' => (int) $settings['auto_close_delay'],
            'mobile_full_screen' => (bool) $settings['mobile_full_screen'],
            'mobile_breakpoint' => (int) $settings['mobile_breakpoint'],
            'trigger_selector' => sanitize_text_field($settings['trigger_selector']),
            'auto_inject' => (bool) $settings['auto_inject'],
            'auto_inject_position' => in_array($settings['auto_inject_position'], ['header', 'menu', 'custom'])
                ? $settings['auto_inject_position']
                : 'header',
            'custom_css' => wp_strip_all_tags($settings['custom_css']),
            'icon_style' => in_array($settings['icon_style'], [self::ICON_CART, self::ICON_BAG, self::ICON_BASKET])
                ? $settings['icon_style']
                : self::ICON_CART,
            'primary_color' => sanitize_hex_color($settings['primary_color']) ?: '#6366f1',
            'text_color' => sanitize_hex_color($settings['text_color']) ?: '#1e293b',
        ];

        $result = update_option(self::OPTION_KEY, $sanitized);

        // Clear cached settings
        $this->settings = $sanitized;

        return $result;
    }

    /**
     * Enqueue mini cart assets
     */
    public function enqueueAssets(): void {
        $settings = $this->getSettings();

        // CSS
        wp_enqueue_style(
            'wpdmpp-mini-cart',
            WPDMPP_BASE_URL . 'assets/css/mini-cart.css',
            [],
            WPDMPP_VERSION
        );

        // Add custom CSS if set
        if (!empty($settings['custom_css'])) {
            wp_add_inline_style('wpdmpp-mini-cart', $settings['custom_css']);
        }

        // Add CSS variables for customization
        $custom_vars = sprintf(
            ':root { --wpdmpp-mc-primary: %s; --wpdmpp-mc-text: %s; --wpdmpp-mc-breakpoint: %dpx; }',
            esc_attr($settings['primary_color'] ?? '#6366f1'),
            esc_attr($settings['text_color'] ?? '#1e293b'),
            (int) ($settings['mobile_breakpoint'] ?? 768)
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

        // Localize script with cart data and settings
        wp_localize_script('wpdmpp-mini-cart', 'wpdmppMiniCart', $this->getScriptData());
    }

    /**
     * Get script localization data
     *
     * @return array
     */
    public function getScriptData(): array {
        $settings = $this->getSettings();
        $cartData = $this->getCartData();

        return [
            'restUrl' => rest_url('wpdmpp/v1/cart'),
            'nonce' => wp_create_nonce('wp_rest'),
            'colorScheme' => get_option('__wpdm_color_scheme', 'system'),
            'settings' => [
                'displayStyle' => $settings['display_style'] ?? self::STYLE_DROPDOWN,
                'position' => $settings['position'] ?? self::POSITION_TOP_RIGHT,
                'autoOpenOnAdd' => $settings['auto_open_on_add'] ?? true,
                'autoCloseDelay' => (int) ($settings['auto_close_delay'] ?? 3000),
                'mobileFullScreen' => $settings['mobile_full_screen'] ?? true,
                'mobileBreakpoint' => (int) ($settings['mobile_breakpoint'] ?? 768),
                'triggerSelector' => $settings['trigger_selector'] ?? '',
                'showThumbnails' => $settings['show_thumbnails'] ?? true,
                'showItemCount' => $settings['show_item_count'] ?? true,
                'showSubtotal' => $settings['show_subtotal'] ?? true,
            ],
            'cartData' => $cartData,
            'cartUrl' => function_exists('wpdmpp_cart_page') ? wpdmpp_cart_page() : '',
            'checkoutUrl' => function_exists('wpdmpp_cart_page') ? wpdmpp_cart_page(['step' => 'checkout']) : '',
            'strings' => $this->getStrings(),
        ];
    }

    /**
     * Get translatable strings for JavaScript
     *
     * @return array
     */
    public function getStrings(): array {
        return [
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
        ];
    }

    /**
     * Get cart data for initial render
     *
     * @return array
     */
    public function getCartData(): array {
        $cartItems = function_exists('wpdmpp_get_cart_items') ? wpdmpp_get_cart_items() : [];
        $itemCount = 0;

        foreach ($cartItems as $item) {
            // Handle both CartItem objects and legacy arrays
            if (is_object($item) && method_exists($item, 'getQuantity')) {
                $itemCount += (int) $item->getQuantity();
            } else {
                $itemCount += isset($item['quantity']) ? (int) $item['quantity'] : 1;
            }
        }

        $cartTotal = function_exists('wpdmpp_get_cart_total') ? wpdmpp_get_cart_total() : 0;

        return [
            'item_count' => $itemCount,
            'total_formatted' => function_exists('wpdmpp_price_format') ? wpdmpp_price_format($cartTotal) : '$' . number_format($cartTotal, 2),
            'is_empty' => empty($cartItems),
        ];
    }

    /**
     * Render auto-injected mini cart in footer
     */
    public function renderAutoInject(): void {
        $settings = $this->getSettings();
        echo $this->render([
            'display_style' => $settings['display_style'],
            'auto_injected' => true,
        ]);
    }

    /**
     * Shortcode handler
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function shortcode($atts = []): string {
        $settings = $this->getSettings();

        $atts = shortcode_atts([
            'display_style' => $settings['display_style'] ?? self::STYLE_DROPDOWN,
            'position' => $settings['position'] ?? self::POSITION_TOP_RIGHT,
            'show_count' => 'yes',
            'show_total' => 'yes',
            'icon' => $settings['icon_style'] ?? self::ICON_CART,
            'class' => '',
        ], $atts, 'wpdmpp_mini_cart');

        return $this->render($atts);
    }

    /**
     * Render mini cart HTML
     *
     * @param array $args Render arguments
     * @return string HTML
     */
    public function render(array $args = []): string {
        $defaults = [
            'display_style' => self::STYLE_DROPDOWN,
            'position' => self::POSITION_TOP_RIGHT,
            'show_count' => 'yes',
            'show_total' => 'yes',
            'icon' => self::ICON_CART,
            'class' => '',
            'auto_injected' => false,
        ];

        $args = wp_parse_args($args, $defaults);
        $settings = $this->getSettings();

        // Get initial cart data
        $cartData = $this->getCartData();
        $itemCount = $cartData['item_count'];
        $cartTotal = function_exists('wpdmpp_get_cart_total') ? wpdmpp_get_cart_total() : 0;

        // Build CSS classes
        $wrapperClasses = [
            'wpdmpp-mini-cart',
            'wpdmpp-mc-' . esc_attr($args['display_style']),
            'wpdmpp-mc-pos-' . esc_attr($args['position']),
        ];

        if ($args['auto_injected']) {
            $wrapperClasses[] = 'wpdmpp-mc-auto-injected';
        }

        if (!empty($args['class'])) {
            $wrapperClasses[] = esc_attr($args['class']);
        }

        if (!empty($settings['mobile_full_screen'])) {
            $wrapperClasses[] = 'wpdmpp-mc-mobile-fullscreen';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapperClasses)); ?>" data-style="<?php echo esc_attr($args['display_style']); ?>" data-wpdm-scheme="<?php echo esc_attr(get_option('__wpdm_color_scheme', 'system')); ?>">
            <!-- Trigger Button -->
            <button type="button" class="wpdmpp-mc-trigger" aria-label="<?php esc_attr_e('Shopping Cart', 'wpdm-premium-packages'); ?>" aria-expanded="false">
                <span class="wpdmpp-mc-icon">
                    <?php echo $this->getIcon($args['icon']); ?>
                </span>
                <?php if ($args['show_count'] === 'yes' && !empty($settings['show_item_count'])): ?>
                    <span class="wpdmpp-mc-count <?php echo $itemCount === 0 ? 'wpdmpp-mc-count--empty' : ''; ?>" data-count="<?php echo esc_attr($itemCount); ?>">
                        <?php echo esc_html($itemCount); ?>
                    </span>
                <?php endif; ?>
                <?php if ($args['show_total'] === 'yes' && !empty($settings['show_subtotal'])): ?>
                    <span class="wpdmpp-mc-total">
                        <?php echo function_exists('wpdmpp_price_format') ? wpdmpp_price_format($cartTotal) : '$' . number_format($cartTotal, 2); ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Cart Panel -->
            <div class="wpdmpp-mc-panel" aria-hidden="true">
                <!-- Panel Header -->
                <div class="wpdmpp-mc-panel-header">
                    <h3 class="wpdmpp-mc-panel-title">
                        <?php esc_html_e('Shopping Cart', 'wpdm-premium-packages'); ?>
                        <span class="wpdmpp-mc-panel-count">(<?php echo esc_html($itemCount); ?>)</span>
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
                        <?php if ($itemCount > 0): ?>
                            <?php echo $this->renderCartItems($settings); ?>
                        <?php else: ?>
                            <?php echo $this->renderEmptyCart(); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Panel Footer -->
                <?php if ($itemCount > 0): ?>
                    <div class="wpdmpp-mc-panel-footer">
                        <div class="wpdmpp-mc-subtotal">
                            <span class="wpdmpp-mc-subtotal-label"><?php esc_html_e('Subtotal', 'wpdm-premium-packages'); ?></span>
                            <span class="wpdmpp-mc-subtotal-value"><?php echo function_exists('wpdmpp_price_format') ? wpdmpp_price_format($cartTotal) : '$' . number_format($cartTotal, 2); ?></span>
                        </div>
                        <div class="wpdmpp-mc-actions">
                            <a href="<?php echo esc_url(function_exists('wpdmpp_cart_page') ? wpdmpp_cart_page() : '#'); ?>" class="wpdmpp-mc-btn wpdmpp-mc-btn--secondary">
                                <?php esc_html_e('View Cart', 'wpdm-premium-packages'); ?>
                            </a>
                            <a href="<?php echo esc_url(function_exists('wpdmpp_cart_page') ? wpdmpp_cart_page(['step' => 'checkout']) : '#'); ?>" class="wpdmpp-mc-btn wpdmpp-mc-btn--primary">
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
     * Render cart items HTML
     *
     * @param array $settings Mini cart settings
     * @return string HTML
     */
    public function renderCartItems(array $settings): string {
        $cartItems = function_exists('wpdmpp_get_cart_data') ? wpdmpp_get_cart_data() : [];

        if (!is_array($cartItems) || empty($cartItems)) {
            return '';
        }

        $html = '';

        foreach ($cartItems as $pid => $item) {
            if (!$pid || get_post_type($pid) !== 'wpdmpro') {
                continue;
            }

            $post = get_post($pid);
            if (!$post) {
                continue;
            }

            // Handle both CartItem objects and legacy arrays
            if (is_object($item) && method_exists($item, 'getQuantity')) {
                $quantity = (int) $item->getQuantity();
                $price = (float) $item->getPrice();
                $prices = method_exists($item, 'getGigsCost') ? (float) $item->getGigsCost() : 0;
            } else {
                $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                $price = isset($item['price']) ? (float) $item['price'] : 0;
                $prices = isset($item['prices']) ? (float) $item['prices'] : 0;
            }
            $unitPrice = $price + $prices;
            $lineTotal = $unitPrice * $quantity;

            // Get thumbnail
            $thumbnailUrl = '';
            if (!empty($settings['show_thumbnails'])) {
                $thumbnailId = get_post_thumbnail_id($pid);
                $thumbnailUrl = $thumbnailId ? wp_get_attachment_image_url($thumbnailId, 'thumbnail') : '';
                if (!$thumbnailUrl) {
                    $icon = get_post_meta($pid, '__wpdm_icon', true);
                    $thumbnailUrl = $icon ?: '';
                }
            }

            $formatPrice = function($amount) {
                return function_exists('wpdmpp_price_format') ? wpdmpp_price_format($amount) : '$' . number_format($amount, 2);
            };

            $html .= '<div class="wpdmpp-mc-item" data-product-id="' . esc_attr($pid) . '">';

            if ($thumbnailUrl) {
                $html .= '<div class="wpdmpp-mc-item-thumb">';
                $html .= '<img src="' . esc_url($thumbnailUrl) . '" alt="' . esc_attr($post->post_title) . '">';
                $html .= '</div>';
            }

            $html .= '<div class="wpdmpp-mc-item-details">';
            $html .= '<a href="' . esc_url(get_permalink($pid)) . '" class="wpdmpp-mc-item-name">' . esc_html($post->post_title) . '</a>';
            $html .= '<div class="wpdmpp-mc-item-meta">';
            $html .= '<span class="wpdmpp-mc-item-qty">' . esc_html($quantity) . ' &times; </span>';
            $html .= '<span class="wpdmpp-mc-item-price">' . $formatPrice($unitPrice) . '</span>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div class="wpdmpp-mc-item-actions">';
            $html .= '<span class="wpdmpp-mc-item-total">' . $formatPrice($lineTotal) . '</span>';
            $html .= '<button type="button" class="wpdmpp-mc-item-remove" data-product-id="' . esc_attr($pid) . '" aria-label="' . esc_attr__('Remove item', 'wpdm-premium-packages') . '">';
            $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            $html .= '</button>';
            $html .= '</div>';

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Render empty cart HTML
     *
     * @return string HTML
     */
    public function renderEmptyCart(): string {
        $continueUrl = function_exists('wpdmpp_continue_shopping_url') ? wpdmpp_continue_shopping_url() : home_url('/');

        ob_start();
        ?>
        <div class="wpdmpp-mc-empty">
            <div class="wpdmpp-mc-empty-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
            </div>
            <p class="wpdmpp-mc-empty-text"><?php esc_html_e('Your cart is empty', 'wpdm-premium-packages'); ?></p>
            <a href="<?php echo esc_url($continueUrl); ?>" class="wpdmpp-mc-btn wpdmpp-mc-btn--secondary">
                <?php esc_html_e('Continue Shopping', 'wpdm-premium-packages'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get cart icon SVG
     *
     * @param string $style Icon style (cart, bag, basket)
     * @return string SVG HTML
     */
    public function getIcon(string $style = 'cart'): string {
        $icons = [
            self::ICON_CART => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',

            self::ICON_BAG => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>',

            self::ICON_BASKET => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.757 1.071a.5.5 0 0 1 .172.686L3.383 6h17.234l-2.546-4.243a.5.5 0 1 1 .858-.514l2.909 4.848A1 1 0 0 1 21 7.5H3a1 1 0 0 1-.838-1.409l2.909-4.848a.5.5 0 0 1 .686-.172z"></path><path d="M3 8h18l-1.5 10a2 2 0 0 1-2 1.5H6.5a2 2 0 0 1-2-1.5L3 8z"></path><line x1="12" y1="12" x2="12" y2="16"></line><line x1="8" y1="12" x2="8" y2="16"></line><line x1="16" y1="12" x2="16" y2="16"></line></svg>',
        ];

        return $icons[$style] ?? $icons[self::ICON_CART];
    }

    /**
     * Render shortcode (static alias for legacy compatibility)
     *
     * @param array $atts Shortcode attributes
     * @return string HTML
     */
    public static function renderShortcode(array $atts = []): string {
        return self::getInstance()->shortcode($atts);
    }
}
