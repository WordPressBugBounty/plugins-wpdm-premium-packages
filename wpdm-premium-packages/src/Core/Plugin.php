<?php
/**
 * Main Plugin Class
 *
 * Bootstraps the new architecture while maintaining backward compatibility.
 *
 * @package WPDMPP\Core
 * @since 7.0.0
 */

namespace WPDMPP\Core;

use WPDMPP\Api\RestApi;
use WPDMPP\Cart\CartService;
use WPDMPP\Order\OrderService;
use WPDMPP\Order\AbandonedOrderService;
use WPDMPP\Coupon\CouponService;
use WPDMPP\License\LicenseService;
use WPDMPP\Product\ProductService;
use WPDMPP\Customer\CustomerService;
use WPDMPP\Customer\BillingInfoService;
use WPDMPP\Invoice\InvoiceService;
use WPDMPP\Payment\PaymentService;
use WPDMPP\Admin\Dashboard\DashboardService;
use WPDMPP\Admin\Metabox\MetaboxService;
use WPDMPP\Admin\Settings\SettingsService;
use WPDMPP\Admin\Order\OrderAdminService;
use WPDMPP\Admin\Payout\PayoutAdminService;
use WPDMPP\Admin\Customer\CustomerAdminService;
use WPDMPP\Admin\Log\LogAdminService;
use WPDMPP\Admin\Coupon\CouponAdminService;
use WPDMPP\Admin\Package\PackageColumnsService;
use WPDMPP\Admin\Order\OrderNoteTemplateService;
use WPDMPP\Core\CurrencyService;
use WPDMPP\Core\Jobs\CronJobService;
use WPDMPP\Frontend\ShortcodeService;
use WPDMPP\Cart\MiniCart\MiniCartService;
use WPDMPP\Payout\PayoutService;

defined('ABSPATH') || exit;

class Plugin {

    /**
     * Plugin version
     */
    public const VERSION = '7.0.0';

    /**
     * Singleton instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Service container
     *
     * @var Container
     */
    private Container $container;

    /**
     * Whether the plugin has been initialized
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Get singleton instance
     *
     * @return Plugin
     */
    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        $this->container = new Container();
    }

    /**
     * Initialize the plugin
     */
    public function init(): void {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        // Register services
        $this->registerServices();

        // Initialize REST API
        $this->initRestApi();

        // Initialize frontend services
        $this->initFrontendServices();

        // Initialize admin services
        $this->initAdminServices();

        // Fire action for extensions
        do_action('wpdmpp_loaded', $this);
    }

    /**
     * Register services in the container
     */
    private function registerServices(): void {
        // Currency Service
        $this->container->singleton(CurrencyService::class, function ($c) {
            return CurrencyService::getInstance();
        });

        // Shortcode Service
        $this->container->singleton(ShortcodeService::class, function ($c) {
            return ShortcodeService::getInstance();
        });

        // REST API
        $this->container->singleton(RestApi::class, function ($c) {
            return new RestApi();
        });

        // Cart Service
        $this->container->singleton(CartService::class, function ($c) {
            return CartService::instance();
        });

        // Mini Cart Service
        $this->container->singleton(MiniCartService::class, function ($c) {
            return MiniCartService::getInstance();
        });

        // Order Service
        $this->container->singleton(OrderService::class, function ($c) {
            return OrderService::instance();
        });

        // Abandoned Order Service
        $this->container->singleton(AbandonedOrderService::class, function ($c) {
            return AbandonedOrderService::instance();
        });

        // Coupon Service
        $this->container->singleton(CouponService::class, function ($c) {
            return CouponService::getInstance();
        });

        // License Service
        $this->container->singleton(LicenseService::class, function ($c) {
            return LicenseService::getInstance();
        });

        // Product Service
        $this->container->singleton(ProductService::class, function ($c) {
            return ProductService::getInstance();
        });

        // Customer Service
        $this->container->singleton(CustomerService::class, function ($c) {
            return CustomerService::getInstance();
        });

        // Billing Info Service
        $this->container->singleton(BillingInfoService::class, function ($c) {
            return BillingInfoService::getInstance();
        });

        // Cron Job Service
        $this->container->singleton(CronJobService::class, function ($c) {
            return CronJobService::getInstance();
        });

        // Payout Service
        $this->container->singleton(PayoutService::class, function ($c) {
            return PayoutService::getInstance();
        });

        // Invoice Service
        $this->container->singleton(InvoiceService::class, function ($c) {
            return InvoiceService::getInstance();
        });

        // Payment Service
        $this->container->singleton(PaymentService::class, function ($c) {
            return PaymentService::instance();
        });

        // Admin-only services
        if (is_admin()) {
            // Dashboard Service
            $this->container->singleton(DashboardService::class, function ($c) {
                return DashboardService::getInstance();
            });

            // Metabox Service
            $this->container->singleton(MetaboxService::class, function ($c) {
                return MetaboxService::getInstance();
            });

            // Settings Service
            $this->container->singleton(SettingsService::class, function ($c) {
                return SettingsService::getInstance();
            });

            // Log Admin Service
            $this->container->singleton(LogAdminService::class, function ($c) {
                return LogAdminService::getInstance();
            });

            // Package Columns Service
            $this->container->singleton(PackageColumnsService::class, function ($c) {
                return PackageColumnsService::getInstance();
            });

            // Order Note Template Service
            $this->container->singleton(OrderNoteTemplateService::class, function ($c) {
                return OrderNoteTemplateService::getInstance();
            });
        }

        // Allow extensions to register services
        do_action('wpdmpp_register_services', $this->container);
    }

    /**
     * Initialize REST API
     */
    private function initRestApi(): void {
        $api = $this->container->get(RestApi::class);
        $api->register();
    }

    /**
     * Initialize frontend services
     */
    private function initFrontendServices(): void {
        // Register shortcodes
        $shortcodeService = $this->container->get(ShortcodeService::class);
        $shortcodeService->register();

        // Register cron job handlers (runs on both frontend and admin)
        $cronJobService = $this->container->get(CronJobService::class);
        $cronJobService->register();

        // Register abandoned order recovery handler (runs on both frontend and admin)
        $abandonedOrderService = $this->container->get(AbandonedOrderService::class);
        $abandonedOrderService->register();

        // NOTE: BillingInfoService is registered earlier (init priority 1, in the main
        // plugin file) rather than here. Its save hook listens on 'wpdm_update_profile',
        // which the core EditProfile fires on init priority 10 — the same priority this
        // Plugin::init() runs at. Registering here would add the listener too late
        // (core's updateProfile runs first), so the billing data would never save.
    }

    /**
     * Initialize admin services
     */
    private function initAdminServices(): void {
        if (!is_admin()) {
            return;
        }

        // Register dashboard widgets
        $dashboardService = $this->container->get(DashboardService::class);
        $dashboardService->register();

        // Register metabox hooks
        $metaboxService = $this->container->get(MetaboxService::class);
        $metaboxService->register();

        // Register settings hooks
        $settingsService = $this->container->get(SettingsService::class);
        $settingsService->register();

        // Register order admin AJAX handlers
        $orderAdmin = OrderAdminService::getInstance();
        $orderAdmin->register();

        // Register payout admin AJAX handlers
        $payoutAdmin = PayoutAdminService::getInstance();
        $payoutAdmin->register();

        // Register customer admin AJAX handlers
        $customerAdmin = CustomerAdminService::getInstance();
        $customerAdmin->register();

        // Register coupon admin AJAX handlers
        $couponAdmin = CouponAdminService::getInstance();
        $couponAdmin->register();

        // Register license admin form handlers
        \WPDMPP\Admin\License\LicenseAdminService::getInstance()->register();

        // Register log admin AJAX handlers
        $logAdmin = $this->container->get(LogAdminService::class);
        $logAdmin->register();

        // Register package columns service
        $packageColumns = $this->container->get(PackageColumnsService::class);
        $packageColumns->register();

        // Register order note template AJAX handlers
        $noteTemplates = $this->container->get(OrderNoteTemplateService::class);
        $noteTemplates->register();

        // Register the setup wizard (hidden dashboard page + step rendering)
        \WPDMPP\Admin\Setup\SetupWizardService::getInstance()->register();

        // Handle cache clearing via URL parameter
        add_action('admin_init', [$this, 'handleCacheClear']);
    }

    /**
     * Handle cache clearing via URL parameter
     *
     * Usage: Add ?wpdmpp_clear_cache=1 to any admin URL
     */
    public function handleCacheClear(): void {
        if (!isset($_GET['wpdmpp_clear_cache']) || $_GET['wpdmpp_clear_cache'] !== '1') {
            return;
        }

        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            return;
        }

        // Clear all dashboard widget caches
        $dashboardService = $this->dashboard();
        if ($dashboardService) {
            $dashboardService->clearAllCaches();
        }

        // Add admin notice
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>';
            esc_html_e('Dashboard widget caches cleared successfully.', 'wpdm-premium-packages');
            echo '</p></div>';
        });
    }

    /**
     * Get the service container
     *
     * @return Container
     */
    public function container(): Container {
        return $this->container;
    }

    /**
     * Get a service from the container
     *
     * @param string $service Service identifier
     * @return mixed
     */
    public function get(string $service): mixed {
        return $this->container->get($service);
    }

    /**
     * Check if a service is registered
     *
     * @param string $service Service identifier
     * @return bool
     */
    public function has(string $service): bool {
        return $this->container->has($service);
    }

    /**
     * Get CartService instance
     *
     * @return CartService
     */
    public function cart(): CartService {
        return $this->container->get(CartService::class);
    }

    /**
     * Get MiniCartService instance
     *
     * @return MiniCartService
     */
    public function miniCart(): MiniCartService {
        return $this->container->get(MiniCartService::class);
    }

    /**
     * Get OrderService instance
     *
     * @return OrderService
     */
    public function order(): OrderService {
        return $this->container->get(OrderService::class);
    }

    /**
     * Get AbandonedOrderService instance
     *
     * @return AbandonedOrderService
     */
    public function abandonedOrder(): AbandonedOrderService {
        return $this->container->get(AbandonedOrderService::class);
    }

    /**
     * Get CouponService instance
     *
     * @return CouponService
     */
    public function coupon(): CouponService {
        return $this->container->get(CouponService::class);
    }

    /**
     * Get LicenseService instance
     *
     * @return LicenseService
     */
    public function license(): LicenseService {
        return $this->container->get(LicenseService::class);
    }

    /**
     * Get ProductService instance
     *
     * @return ProductService
     */
    public function product(): ProductService {
        return $this->container->get(ProductService::class);
    }

    /**
     * Get CustomerService instance
     *
     * @return CustomerService
     */
    public function customer(): CustomerService {
        return $this->container->get(CustomerService::class);
    }

    /**
     * Get BillingInfoService instance
     *
     * @return BillingInfoService
     */
    public function billingInfo(): BillingInfoService {
        return $this->container->get(BillingInfoService::class);
    }

    /**
     * Get CronJobService instance
     *
     * @return CronJobService
     */
    public function cronJobs(): CronJobService {
        return $this->container->get(CronJobService::class);
    }

    /**
     * Get PayoutService instance
     *
     * @return PayoutService
     */
    public function payout(): PayoutService {
        return $this->container->get(PayoutService::class);
    }

    /**
     * Get InvoiceService instance
     *
     * @return InvoiceService
     */
    public function invoice(): InvoiceService {
        return $this->container->get(InvoiceService::class);
    }

    /**
     * Get PaymentService instance
     *
     * @return PaymentService
     */
    public function payment(): PaymentService {
        return $this->container->get(PaymentService::class);
    }

    /**
     * Get CurrencyService instance
     *
     * @return CurrencyService
     */
    public function currency(): CurrencyService {
        return $this->container->get(CurrencyService::class);
    }

    /**
     * Get ShortcodeService instance
     *
     * @return ShortcodeService
     */
    public function shortcode(): ShortcodeService {
        return $this->container->get(ShortcodeService::class);
    }

    /**
     * Get DashboardService instance (admin only)
     *
     * @return DashboardService|null
     */
    public function dashboard(): ?DashboardService {
        if (!is_admin() || !$this->container->has(DashboardService::class)) {
            return null;
        }
        return $this->container->get(DashboardService::class);
    }

    /**
     * Get MetaboxService instance (admin only)
     *
     * @return MetaboxService|null
     */
    public function metabox(): ?MetaboxService {
        if (!is_admin() || !$this->container->has(MetaboxService::class)) {
            return null;
        }
        return $this->container->get(MetaboxService::class);
    }

    /**
     * Get SettingsService instance (admin only)
     *
     * @return SettingsService|null
     */
    public function settings(): ?SettingsService {
        if (!is_admin() || !$this->container->has(SettingsService::class)) {
            return null;
        }
        return $this->container->get(SettingsService::class);
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}
