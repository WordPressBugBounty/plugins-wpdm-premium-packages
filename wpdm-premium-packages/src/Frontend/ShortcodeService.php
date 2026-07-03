<?php
/**
 * Shortcode Service
 *
 * Handles registration and rendering of all Premium Packages shortcodes.
 *
 * @package WPDMPP\Frontend
 * @since 7.0.0
 */

namespace WPDMPP\Frontend;

use WPDM\__\__;
use WPDM\__\Session;
use WPDM\__\Template;
use WPDMPP\Cart\CartService;
use WPDMPP\Order\OrderService;
use WPDMPP\Product\ProductService;

defined('ABSPATH') || exit;

class ShortcodeService {

    /**
     * Singleton instance
     *
     * @var ShortcodeService|null
     */
    private static ?ShortcodeService $instance = null;

    /**
     * Whether the service has been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Get singleton instance
     *
     * @return ShortcodeService
     */
    public static function getInstance(): ShortcodeService {
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
     * Register all shortcodes
     */
    public function register(): void {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        // Core shortcodes
        add_shortcode('wpdmpp_seller_dashboard', [$this, 'sellerDashboard']);
        add_shortcode('wpdmpp_earnings', [$this, 'earnings']);
        add_shortcode('wpdmpp_purchases', [$this, 'userPurchases']);
        add_shortcode('wpdmpp_guest_orders', [$this, 'guestOrders']);
        add_shortcode('wpdmpp_buynow', [$this, 'buyNow']);
        add_shortcode('wpdmpp_pay_link', [$this, 'payLink']);

        // Cart shortcodes - delegate to CartService
        add_shortcode('wpdmpp_cart', [$this, 'cart']);
        add_shortcode('wpdm-pp-cart', [$this, 'cart']);

        // Withdraws shortcode - handled by Payout module
        add_shortcode('wpdmpp_withdraws', [$this, 'withdraws']);

        // Mini cart shortcode
        add_shortcode('wpdmpp_mini_cart', [$this, 'miniCart']);

        // Register frontend dashboard tabs
        add_filter('wpdm_frontend', [$this, 'registerDashboardTabs']);
    }

    /**
     * Register seller dashboard tabs in WPDM frontend
     *
     * @param array $tabs Existing tabs
     * @return array Modified tabs
     */
    public function registerDashboardTabs(array $tabs): array {
        // Add seller dashboard as first tab
        $sellerTabs = [
            'seller-dashboard' => [
                'label' => __('Dashboard', 'wpdm-premium-packages'),
                'shortcode' => '[wpdmpp_seller_dashboard]'
            ]
        ];

        // Add sales and withdraws tabs
        $tabs['sales'] = [
            'label' => __('Sales', 'wpdm-premium-packages'),
            'shortcode' => '[wpdmpp_earnings]'
        ];

        $tabs['withdraws'] = [
            'label' => __('Withdraws', 'wpdm-premium-packages'),
            'shortcode' => '[wpdmpp_withdraws]'
        ];

        return $sellerTabs + $tabs;
    }

    /**
     * Seller dashboard shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function sellerDashboard(array $atts = []): string {
        if (!is_user_logged_in()) {
            return $this->getLoginForm();
        }

        ob_start();
        wp_register_script('wpdmpp-seller-dashboard', WPDMPP_BASE_URL . '/assets/js/Chart.js', [], WPDMPP_VERSION, true);

        $template = WPDM()->template->locate('wpdm-pp-seller-dashboard.php', WPDMPP_TPL_DIR);
        if ($template && file_exists($template)) {
            include $template;
        } else {
            $this->renderView('seller-dashboard.php');
        }

        return ob_get_clean();
    }

    /**
     * Earnings shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function earnings(array $atts = []): string {
        if (!is_user_logged_in()) {
            return $this->getLoginForm();
        }

        ob_start();

        $template = WPDM()->template->locate('wpdm-pp-earnings.php', WPDMPP_TPL_DIR);
        if ($template && file_exists($template)) {
            include $template;
        } else {
            $this->renderView('earnings.php');
        }

        return ob_get_clean();
    }

    /**
     * User purchases shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function userPurchases(array $atts = []): string {
        global $current_user;

        $current_user = wp_get_current_user();
        $wpdmpp_settings = get_option('_wpdmpp_settings');

        // Same dashboard stylesheet that powers the WPDM user dashboard. Enqueue it here
        // so the [wpdmpp_purchases] shortcode renders correctly when used on a regular
        // page instead of the dashboard route.
        wp_enqueue_style(
            'wpdmpp-dashboard',
            WPDMPP_BASE_URL . 'assets/css/wpdmpp-dashboard.css',
            [],
            WPDMPP_VERSION
        );

        ob_start();
        echo '<div class="w3eden">';

        if (!is_user_logged_in()) {
            // Show login/registration form
            echo WPDM()->user->login->form();

            // If guest order is enabled, show guest order page link
            if (Session::get('last_order') && isset($wpdmpp_settings['guest_download']) && $wpdmpp_settings['guest_download'] == 1) {
                $partialTemplate = Template::locate('partials/guest_order_page_link.php', WPDMPP_TPL_DIR);
                if ($partialTemplate && file_exists($partialTemplate)) {
                    include_once $partialTemplate;
                }
            }
        } else {
            // List all orders made by the user
            $orderService = OrderService::instance();
            $myorders = $orderService->getUserOrders($current_user->ID, false);

            // No legacy fallback needed - OrderService handles all order queries

            $dashboard = true;
            $template = wpdm_tpl_path('wpdm-pp-purchases.php', WPDMPP_TPL_DIR);
            if ($template && file_exists($template)) {
                include_once $template;
            } else {
                $this->renderView('purchases.php', [
                    'orders' => $myorders,
                    'dashboard' => $dashboard
                ]);
            }
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Guest orders shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function guestOrders(array $atts = []): string {
        global $post;

        // Check if guest download is enabled
        if (get_wpdmpp_option('guest_download') != 1) {
            return '<p>' . esc_html__('Enable guest download from Premium Packages settings', 'wpdm-premium-packages') . '</p>';
        }

        // Initialize guest order session
        if (is_object($post) && get_the_permalink() == wpdmpp_guest_order_page() && !Session::get('guest_order_init')) {
            Session::set('guest_order_init', uniqid());
        }

        wp_enqueue_style('wpdmpp-dashboard', WPDMPP_BASE_URL . 'assets/css/wpdmpp-dashboard.css', [], WPDMPP_VERSION);

        ob_start();

        $template = wpdm_tpl_path('wpdm-pp-guest-orders.php', WPDMPP_TPL_DIR);
        if ($template && file_exists($template)) {
            include $template;
        } else {
            $this->renderView('guest-orders.php');
        }

        return ob_get_clean();
    }

    /**
     * Buy now shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function buyNow(array $atts = []): string {
        $atts = shortcode_atts([
            'id' => 0,
            'license' => '',
        ], $atts);

        $product_id = (int) $atts['id'];

        if ($product_id <= 0) {
            return '<p>' . esc_html__('Product ID is missing!', 'wpdm-premium-packages') . '</p>';
        }

        ob_start();

        $productService = ProductService::getInstance();
        $product = $productService->getProduct($product_id);
        $license = sanitize_text_field($atts['license']);
        $price = $product ? $product->getLicensePrice($license) : 0;

        $params = ['title' => __('Buy Now', 'wpdm-premium-packages')];

        echo "<div class='__wpdmpp_buy_now_zone_{$product_id}'>";

        $template = wpdm_tpl_path('add-to-cart/buy-now.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
        if ($template && file_exists($template)) {
            include $template;
        }

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Pay link shortcode for custom payment links
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function payLink(array $atts = []): string {
        $atts = shortcode_atts([
            'price' => 0,
            'recurring' => 0,
            'label' => __('Pay Now', 'wpdm-premium-packages'),
            'cssclass' => 'wpdm-pay-now-link',
        ], $atts);

        $price = (float) $atts['price'];

        if ($price <= 0) {
            return '';
        }

        $args = [
            'addtocart' => 'dynamic',
            'price' => $price,
            'recurring' => (int) $atts['recurring'],
        ];

        $url = add_query_arg($args, home_url('/'));
        $label = esc_html($atts['label']);
        $class = esc_attr($atts['cssclass']);

        return sprintf(
            '<span class="w3eden wpdmpp-pay-link"><a href="%s" class="%s">%s</a></span>',
            esc_url($url),
            $class,
            $label
        );
    }

    /**
     * Cart shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function cart(array $atts = []): string {
        $cartService = CartService::instance();

        // Auto-apply coupon if eligible
        $cartService->tryAutoApplyCoupon();

        ob_start();

        // Check for legacy cart template first
        $template = wpdm_tpl_path('wpdm-pp-cart.php', WPDMPP_TPL_DIR);
        if ($template && file_exists($template)) {
            $cart = $cartService->getCart();
            $items = $cartService->getItems();
            $total = $cartService->getTotal();
            include $template;
        } else {
            // Use the legacy Cart class for now
            if (class_exists('\WPDMPP\Libs\Cart')) {
                $legacyCart = new \WPDMPP\Libs\Cart();
                echo $legacyCart->render($atts);
            }
        }

        return ob_get_clean();
    }

    /**
     * Withdraws shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function withdraws(array $atts = []): string {
        if (!is_user_logged_in()) {
            return $this->getLoginForm();
        }

        $payoutService = \WPDMPP\Payout\PayoutService::getInstance();
        $withdrawals = $payoutService->getWithdrawals(['uid' => get_current_user_id()]);
        $requests = [];
        foreach ($withdrawals as $withdrawal) {
            $obj = (object) $withdrawal->toArray();
            $obj->user = $withdrawal->getUser();
            $requests[] = $obj;
        }
        $methods = $payoutService->getMethodsArray();
        $accounts = get_user_meta(get_current_user_id(), '__wpdmpp_payment_account', true);

        ob_start();
        $template = \WPDM\__\Template::locate('dashboard/withdraws.php', WPDMPP_TPL_DIR);
        if ($template && file_exists($template)) {
            include $template;
        }
        return ob_get_clean();
    }

    /**
     * Mini cart shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function miniCart(array $atts = []): string {
        $atts = shortcode_atts([
            'style' => 'dropdown', // dropdown, slide-panel, floating
            'display_style' => '',
            'position' => '',
            'show_count' => 'yes',
            'show_total' => 'yes',
            'icon' => '',
            'class' => '',
        ], $atts);

        // Map 'style' to 'display_style' for backward compatibility
        if (empty($atts['display_style']) && !empty($atts['style'])) {
            $atts['display_style'] = $atts['style'];
        }

        return \WPDMPP\Cart\MiniCart\MiniCartService::getInstance()->shortcode($atts);
    }

    /**
     * Get login form HTML
     *
     * @return string Login form HTML
     */
    private function getLoginForm(): string {
        ob_start();
        echo '<div class="w3eden">';
        echo WPDM()->user->login->form();
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Render a view template
     *
     * @param string $template Template filename
     * @param array  $data     Data to pass to template
     */
    private function renderView(string $template, array $data = []): void {
        $viewPath = __DIR__ . '/views/' . $template;

        if (file_exists($viewPath)) {
            extract($data);
            include $viewPath;
        }
    }

    /**
     * Get view path for a template
     *
     * @param string $template Template filename
     * @return string Full path to template
     */
    public function getViewPath(string $template): string {
        return __DIR__ . '/views/' . $template;
    }
}
