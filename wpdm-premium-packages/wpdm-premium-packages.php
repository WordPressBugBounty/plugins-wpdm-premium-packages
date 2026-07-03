<?php
/**
 * Plugin Name:  Premium Packages - Sell Digital Products Securely
 * Plugin URI: https://www.wpdownloadmanager.com/download/premium-package-complete-digital-store-solution/
 * Description: Complete solution for selling digital products securely and easily
 * Version: 7.0.0
 * Author: WordPress Download Manager
 * Text Domain: wpdm-premium-packages
 * Author URI: https://www.wpdownloadmanager.com/
 */

namespace WPDMPP;

use WPDM\__\__;
use WPDM\__\__MailUI;
use WPDM\__\Email;
use WPDM\__\Messages;
use WPDM\__\Template;
use WPDM\__\Crypt;
use WPDM\__\FileSystem;
use WPDM\__\Session;
use WPDM\Package\FileList;
use WPDMPP\Libs\Cart;
use WPDMPP\Libs\Withdraws;
use WPDMPP\Order\OrderService;
use WPDMPP\Payment\PaymentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdmpp, $wpdmpp_settings;

if ( ! class_exists( 'WPDMPremiumPackage' ) ):
	/**
	 * @class WPDMPremiumPackage
	 */

	define( 'WPDMPP_VERSION', '7.0.0' );
	define( 'WPDMPP_BASE_DIR', dirname( __FILE__ ) . '/' );
	define( 'WPDMPP_BASE_URL', plugins_url( 'wpdm-premium-packages/' ) );
	define( 'WPDMPP_TEXT_DOMAIN', 'wpdm-premium-packages' );

	if ( ! defined( 'WPDMPP_MENU_ACCESS_CAP' ) ) {
		define( 'WPDMPP_MENU_ACCESS_CAP', 'manage_categories' );
	}

	if ( ! defined( 'WPDMPP_ADMIN_CAP' ) ) {
		define( 'WPDMPP_ADMIN_CAP', 'manage_options' );
	}

	// Load PSR-4 autoloader for new architecture (v7.0.0+)
	if ( file_exists( WPDMPP_BASE_DIR . 'src/autoload.php' ) ) {
		require_once WPDMPP_BASE_DIR . 'src/autoload.php';
	}

	if ( ! defined( 'WPDMPP_TPL_FALLBACK' ) ) {
		define( 'WPDMPP_TPL_FALLBACK', dirname( __FILE__ ) . '/templates/' );
	}

	if ( ! defined( 'WPDMPP_TPL_DIR' ) ) {
		define( 'WPDMPP_TPL_DIR', dirname( __FILE__ ) . '/templates/' );
	}

	if ( ! defined( 'WPDMPP_ADMIN_VIEWS' ) ) {
		define( 'WPDMPP_ADMIN_VIEWS', dirname( __FILE__ ) . '/src/Admin/' );
	}

	class WPDMPremiumPackage {

		/**
		 * @var Cart
		 */
		public $cart;
		/**
		 * @var OrderService
		 */
		public $order;

		public $withdraws;

		/**
		 * @var PaymentService
		 */
		public $payment;

		public $couponCodes;
		public $shortCodes;

		function __construct() {
			global $wpdmpp_settings, $payment_methods;
			$wpdmpp_settings = maybe_unserialize( get_option( '_wpdmpp_settings' ) );
			$payment_methods = [ 'TestPay', 'PayPal', 'Cash', 'Cheque' ];

			$this->init();
			$this->init_hooks();

		}

		private function init() {
			global $sap;

			if ( function_exists( 'get_option' ) ) {
				$sap = ( get_option( 'permalink_structure' ) != '' ) ? '?' : '&';
			}

			$this->include_files();

		}

		private function init_hooks() {

			// Must run before WPDMPP_INSTALLER::init — the installer seeds
			// _wpdmpp_settings, which the fresh-install check relies on being empty.
			register_activation_hook( __FILE__, array( $this, 'wpdmpp_setup_wizard_activation_flag' ) );
			register_activation_hook( __FILE__, [ \WPDMPP_INSTALLER::class, 'init' ] );
			add_action( 'upgrader_process_complete', [$this, 'update'], 10, 2);

			add_action( 'wp', [ $this, 'download' ], 1 );

			add_action( 'wp_login', [ new Cart(), 'onUserLogin' ], 10, 2 );

			// Metabox hooks are now handled by MetaboxService in the new architecture
			// Settings hooks are now handled by SettingsService in the new architecture
			// Both services register themselves when Plugin::init() is called

			add_action( 'wpdm_template_editor_menu', [ $this, 'template_editor_menu' ] );

			add_action( 'admin_notices', array( $this, 'notice' ) );
			add_action( 'admin_notices', array( $this, 'wpdmpp_run_setup_wizard_notice' ) );

			// Register billing-info profile hooks early (priority 1). Its save handler
			// listens on 'wpdm_update_profile', which core's EditProfile fires on init
			// priority 10 — so the listener must be added before then or the billing
			// data never saves. register() only adds hooks (no __()), so it is safe
			// here, before the textdomain loads in the priority-10 callback below.
			add_action( 'init', function () {
				if ( class_exists( '\WPDMPP\Customer\BillingInfoService' ) ) {
					\WPDMPP\Customer\BillingInfoService::getInstance()->register();
				}
			}, 1 );

			add_action( 'init', function () {
				// Load textdomain first — must happen before any __() calls
				$this->wpdmpp_languages();

				// Register services that use __() in their register() methods
				$this->payment->register();
				\WPDMPP\Cart\MiniCart\MiniCartService::getInstance()->register();
				if ( class_exists( '\WPDMPP\Core\Plugin' ) ) {
					\WPDMPP\Core\Plugin::instance()->init();
				}

				$this->dbTables();
				$this->clone_order();
				$this->invoice();
				$this->wpdmpp_process_guest_order();
				$this->paynow();
				$this->payment_notification();
				$this->comeplete_buynow_action();
				$this->freeDownload();

			} );



			add_action( 'wpdm_login_form', array( $this, 'wpdmpp_invoice_field' ) );
			add_action( 'wpdm_register_form', array( $this, 'wpdmpp_invoice_field' ) );
			add_action( 'wp_login', array( $this, 'wpdmpp_associate_invoice' ), 10, 2 );
			add_action( 'user_register', array( $this, 'wpdmpp_associate_invoice_signup' ), 10, 1 );

			add_action( 'wp_ajax_resolveorder', array( $this, 'wpdmpp_resolveorder' ) );

			add_action( 'wp_ajax_nopriv_gettax', array( $this, 'calculate_tax' ) );
			add_action( 'wp_ajax_gettax', array( $this, 'calculate_tax' ) );

			add_action( 'wp_ajax_wpdmpp_cancel_subscription', array( $this, 'cancel_subscription' ) );

			// Sales overview AJAX is now handled by MetaboxService

			add_action( 'wp_ajax_wpdmpp_update_withdraw_status', array( $this, 'wpdmpp_update_withdraw_status' ) );

			add_action( 'wp_ajax_wpdmpp_expire_orders', array( $this, 'expire_orders' ) );

			add_action( 'wp_ajax_wpdmpp_email_payment_link', array( $this, 'email_payment_link' ) );

			add_action( 'wp_enqueue_scripts', array( $this, 'wpdmpp_enqueue_scripts' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'wpdmpp_admin_enqueue_scripts' ) );

			if ( is_admin() ) {
				// Settings save is now handled by SettingsService::ajaxSaveSettings()
				add_action( 'wp_ajax_wpdmpp_toggle_auto_renew', array( $this, 'toggleAutoRenew' ) );
				add_action( 'wp_ajax_wpdmpp_toggle_manual_renew', array( $this, 'toggleManualRenew' ) );
				add_action( 'wp_loaded', array( $this, 'wpdmpp_hide_notices' ) );
			}

			if ( ! is_admin() ) {
				add_action( 'wpdm_login_form', array( $this, 'wpdmpp_guest_download_link' ) );
			}

			// add_meta_boxes is now handled by MetaboxService
			add_filter( 'wpdm_user_dashboard_menu', array( $this, 'wpdmpp_user_dashboard_menu' ) );

			add_filter( 'wpdm_after_prepare_package_data', array( $this, 'fetchTemplateTag' ) );
			add_filter( 'wdm_before_fetch_template', array( $this, 'fetchTemplateTag' ) );
			add_filter( 'wpdm_download_link', array( $this, 'downloadLink' ), 10, 2 );
			add_filter( 'wpdm_check_lock', array( $this, 'lockDownload' ), 10, 2 );
			add_filter( 'wpdm_single_file_download_link', array( $this, 'hideSingleFileDownloadLink' ), 10, 3 );

			//add_action( 'activated_plugin', array( $this, 'pp_save_error' ) );

		}

		function dbTables() {
			global $wpdb;
			$wpdb->wpdmpp_orders           = "{$wpdb->prefix}ahm_orders";
			$wpdb->wpdmpp_order_items      = "{$wpdb->prefix}ahm_order_items";
			$wpdb->wpdmpp_coupons          = "{$wpdb->prefix}ahm_coupons";
			$wpdb->wpdmpp_abandoned_orders = "{$wpdb->prefix}ahm_acr_emails";
		}

		/**
		 * Update plugin
		 * @param $upgrader_object
		 * @param $options
		 */
		function update( $upgrader_object, $options ) {
			$current_plugin_path_name = plugin_basename( __FILE__ );
			if(!is_array($options)) return;
			if ($options['action'] == 'update' && $options['type'] == 'plugin' ){
				if(isset($options['plugins']) && is_array($options['plugins'])) {
					foreach ($options['plugins'] as $each_plugin) {
						if ($each_plugin == $current_plugin_path_name) {
							if ( \WPDMPP_INSTALLER::dbUpdateRequired()) {
								\WPDMPP_INSTALLER::updateDB();
								return;
							}
							//flush_rewrite_rules(true);
						}
					}
				}
			}
		}


		/**
		 * Activation hook: arm the setup wizard for fresh installs only.
		 *
		 * Runs before WPDMPP_INSTALLER::init seeds defaults, so an empty
		 * _wpdmpp_settings option here means a genuinely fresh install.
		 * Arms the "Run the Setup Wizard" admin notice and sets a short-lived
		 * transient that SetupWizardService consumes to redirect to the wizard
		 * on the next admin page load.
		 */
		function wpdmpp_setup_wizard_activation_flag() {
			if ( get_option( '_wpdmpp_settings' ) || get_option( 'wpdmpp_setp_wizard_notice' ) === 'hide' ) {
				return;
			}
			update_option( 'wpdmpp_setp_wizard_notice', 'show', false );
			set_transient( '_wpdmpp_setup_wizard_redirect', 1, 30 );
		}

		function wpdmpp_run_setup_wizard_notice() {

			// Only shown while armed by a fresh-install activation; finishing
			// or skipping the wizard flips the option to 'hide'.
			if ( get_option( 'wpdmpp_setp_wizard_notice' ) !== 'show' || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
			<div class="notice notice-info is-dismissible w3eden">
				<p class="wpdmpp-notice"><?php _e( 'Thank you for installing Premium Package! You are almost ready to start selling.', 'wpdm-premium-packages' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpdmpp-setup' ) ); ?>"
					   class="btn btn-sm  btn-info"><?php _e( 'Run the Setup Wizard', 'wpdm-premium-packages' ); ?></a>
					<a class="btn btn-sm btn-warning"
					   href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wpdmpp-hide-notice', 'wizard' ), 'wpdmpp_hide_notices_nonce', '_wpdmpp_notice_nonce' ) ); ?>"><?php _e( 'Skip setup', 'wpdm-premium-packages' ); ?></a>
				</p>
			</div>
			<?php
		}

		function wpdmpp_hide_notices() {
			if ( isset( $_GET['wpdmpp-hide-notice'] ) && isset( $_GET['_wpdmpp_notice_nonce'] ) ) {
				if ( ! wp_verify_nonce( $_GET['_wpdmpp_notice_nonce'], 'wpdmpp_hide_notices_nonce' ) ) {
					wp_die( __( 'Action failed. Please refresh the page and retry.', 'wpdm-premium-packages' ) );
				}

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( __( 'Cheatin&#8217; huh?', 'wpdm-premium-packages' ) );
				}

				$hide_notice = sanitize_text_field( $_GET['wpdmpp-hide-notice'] );
				if ( $hide_notice == 'wizard' ) {
					update_option( 'wpdmpp_setp_wizard_notice', 'hide' );
				}
			}
		}

		function pp_save_error() {
			file_put_contents( ABSPATH . 'pp-errors.txt', ob_get_contents() );
		}

		function wpdmpp_languages() {
			load_plugin_textdomain( 'wpdm-premium-packages', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		function include_files() {
			include_once( dirname( __FILE__ ) . "/includes/libs/functions.php" );
			include_once( dirname( __FILE__ ) . "/includes/libs/Logger.php" );
			include_once( dirname( __FILE__ ) . "/includes/libs/Installer.php" );
			// User.php removed — now handled by CustomerService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/User.php" );
			// LicenseManager.php removed — now handled by LicenseService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/LicenseManager.php" );

			// Product.php removed — now handled by ProductService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/Product.php" );
			include_once( dirname( __FILE__ ) . "/includes/libs/Cart.php" );
			$this->cart = new Cart();

			// Order.php removed — now handled by OrderService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/Order.php" );
			$this->order = OrderService::instance();

			// Payment.php removed — now handled by PaymentService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/Payment.php" );
			$this->payment = PaymentService::instance();

			// CustomActions.php removed — AJAX handlers migrated to domain admin services
			// CustomColumns.php removed — now handled by PackageColumnsService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/CustomColumns.php" );
			// Currencies.php removed — now handled by CurrencyService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/Currencies.php" );
			// BillingInfo.php removed — now handled by BillingInfoService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/BillingInfo.php" );
			//include_once( dirname( __FILE__ ) . "/includes/libs/DashboardWidgets.php" );
			// OrderNoteTemplates.php removed — now handled by OrderNoteTemplateService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/OrderNoteTemplates.php" );
			// Withdraws bridge — delegates to PayoutService, needed by payout templates
			include_once( dirname( __FILE__ ) . "/includes/libs/Withdraws.php" );
			$this->withdraws = new Withdraws();

			// CouponCodes.php removed — AJAX handlers migrated to cart-functions.php, service is CouponService in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/CouponCodes.php" );
			//$this->couponCodes = new CouponCodes();

			// ShortCodes.php removed — now handled by ShortcodeService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/ShortCodes.php" );
			//$this->shortCodes = new ShortCodes();

			// CronJobs.php removed — now handled by CronJobService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/CronJobs.php" );
			// AbandonedOrderRecovery.php removed — now handled by AbandonedOrderService registered in Plugin.php
			//include_once( dirname( __FILE__ ) . "/includes/libs/AbandonedOrderRecovery.php" );
			include_once( dirname( __FILE__ ) . "/includes/libs/cart-functions.php" );
			include_once( dirname( __FILE__ ) . "/includes/libs/hooks.php" );

			include_once( dirname( __FILE__ ) . "/includes/menus/AdminMenus.php" );

			// Legacy payment method files removed - now handled by PaymentService gateways
			// See: src/Payment/Gateways/{PayPalGateway,CashGateway,ChequeGateway,TestPayGateway}.php

		}

		function calculate_tax() {

			$cartsubtotal = WPDMPP()->cart->cartTotal();
			$cartdiscount = WPDMPP()->cart->couponDiscount();
			$cartsubtotal -= $cartdiscount;

			//$tax_total = wpdmpp_calculate_tax2();

			$tax_total = WPDMPP()->cart->calculateTax( $cartsubtotal, wpdm_query_var( 'country', 'txt' ), wpdm_query_var( 'state', 'txt' ) );

			$total_including_tax = $cartsubtotal + $tax_total;

			$customPayButton = "";
			if ( Session::get( 'orderid' ) ) {
				WPDMPP()->order->reCalculate( Session::get( 'orderid' ) );
				if ( wpdm_query_var( 'payment_method', 'txt' ) ) {
					$customPayButton = PaymentService::instance()->getCheckoutButton( wpdm_query_var( 'payment_method', 'txt' ) );
				}
			}

			$updates = [
				'tax'            => wpdmpp_price_format( $tax_total ),
				'total'          => wpdmpp_price_format( $total_including_tax ),
				'subtotal'       => $cartsubtotal,
				'dis'            => $cartdiscount,
				'order'          => Session::get( 'orderid' ),
				'payment_button' => $customPayButton
			];

			wp_send_json( $updates );
		}


		// =========================================================================
		// METABOX FUNCTIONS MOVED TO NEW ARCHITECTURE
		// =========================================================================
		// The following metabox functions have been moved to:
		// \WPDMPP\Admin\Metabox\MetaboxService
		//
		// - wpdmpp_meta_box_sales_overview_loader() -> MetaboxService::renderSalesOverviewPlaceholder()
		// - wpdmpp_meta_box_sales_overview()        -> MetaboxService::ajaxLoadSalesOverview()
		// - add_meta_boxes()                        -> MetaboxService::addSalesOverviewMetabox()
		// - wpdmpp_meta_box_pricing()               -> MetaboxService::renderPricingMetabox()
		// - wpdmpp_meta_boxes()                     -> MetaboxService::addPricingTab()
		//
		// @since 7.0.0
		// =========================================================================

		// =========================================================================
		// SETTINGS FUNCTIONS MOVED TO NEW ARCHITECTURE
		// =========================================================================
		// The following settings functions have been moved to:
		// \WPDMPP\Admin\Settings\SettingsService
		//
		// - settings()      -> SettingsService::renderSettingsPage()
		// - settings_tab()  -> SettingsService::addSettingsTab()
		//
		// @since 7.0.0
		// =========================================================================

		/**
		 * Generate Order Invoice op request
		 */
		function invoice() {
			if ( isset( $_GET['id'] ) && $_GET['id'] != '' && isset( $_GET['wpdminvoice'] ) ) {
				ob_start();
				wp_register_style( 'wpdm-front-bootstrap', WPDM_BASE_URL . 'assets/bootstrap/css/bootstrap.css' );
				wp_register_style( 'wpdm-front', WPDM_BASE_URL . 'assets/css/front.css' );
				wp_register_style( 'wpdmpp-invoice', WPDMPP_BASE_URL . 'assets/css/invoice.css', array(
					'wpdm-front-bootstrap',
					'wpdm-front'
				) );
				include \WPDM\__\Template::locate( "invoices/default/invoice.php", WPDMPP_TPL_DIR );
				$data = ob_get_clean();

				$oid = sanitize_file_name( $_GET['id'] );
				echo $data;
				die();
			}
		}


		function wpdmpp_user_dashboard_menu( $menu ) {
			$menu = array_merge( array_splice( $menu, 0, 1 ), array(
				'purchases' => array(
					'name'     => __( 'Purchases', 'wpdm-premium-packages' ),
					'callback' => array(
						$this,
						'wpdmpp_purchased_items'
					)
				)
			), $menu );

			return $menu;
		}

		function wpdmpp_purchased_items( $params = array() ) {
			global $wpdb;
			$current_user = wp_get_current_user();
			$uid          = $current_user->ID;

			//$purchased_items = $wpdb->get_results("select oi.*,o.currency, o.date as odate, o.order_status from {$wpdb->prefix}ahm_order_items oi,{$wpdb->prefix}ahm_orders o where o.order_id = oi.oid and o.uid = {$uid} and o.order_status IN ('Expired', 'Completed') order by `date` desc");

			wpdmpp_expiry_check();

			wp_enqueue_style( 'wpdmpp-dashboard', WPDMPP_BASE_URL . 'assets/css/wpdmpp-dashboard.css', [], WPDMPP_VERSION );

			ob_start();
			if ( isset( $params[2] ) && $params[1] == 'order' ) {
				OrderService::instance()->userOrderDetails( $params[2] );
			} else {
				include_once wpdm_tpl_path( 'partials/resolve-order.php', WPDMPP_TPL_DIR );
				include_once wpdm_tpl_path( 'partials/user-orders-list.php', WPDMPP_TPL_DIR );
			}
			//else
			//    include wpdm_tpl_path('user-dashboard/purchased-items.php', WPDMPP_TPL_DIR);

			return ob_get_clean();
		}


		/**
		 * Process Guest Orders
		 */
		/**
		 * Emit a short plain-text status token for the guest-order lookup AJAX call
		 * and stop execution.
		 *
		 * Uses an explicit 200 status because wp_die() defaults to HTTP 500 for
		 * non-Ajax requests, which makes jQuery treat the (otherwise successful)
		 * response as an error and skip the success callback. A plain body also
		 * keeps the client-side anchored regexes (e.g. /^ratelimit:/) working.
		 *
		 * @param string $token Status token consumed by the front-end handler.
		 */
		private function guest_order_reply( $token ) {
			if ( ! headers_sent() ) {
				nocache_headers();
				status_header( 200 );
				header( 'Content-Type: text/plain; charset=utf-8' );
			}
			die( $token );
		}

		function wpdmpp_process_guest_order() {

			if ( wpdm_query_var( 'exitgo', 'int' ) ) {
				Session::clear( 'guest_order' );
				$return = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : home_url( '/' );
				wp_safe_redirect( $return );
				exit;
			}

			if ( isset( $_POST['__wpdmpp_go'] ) ) {

				check_ajax_referer( NONCE_KEY, '__wpdmpp_go_nonce' );

				// Rate limiting: 5 attempts per 15 minutes per IP
				$rate_limit = \WPDMPP\Security\RateLimiter::check( 'guest_order_lookup', 5, 900 );
				if ( $rate_limit['limited'] ) {
					$this->guest_order_reply( 'ratelimit:' . (int) $rate_limit['retry_after'] );
				}

				$orderid    = sanitize_text_field( $_POST['__wpdmpp_go']['order'] );
				$orderemail = sanitize_email( $_POST['__wpdmpp_go']['email'] );

				$order = OrderService::instance()->getOrder( $orderid );

				// No match for order id
				if ( ! is_object( $order ) || $order->getOrderId() != $orderid ) {
					$this->guest_order_reply( 'noordr' );
				}

				// Found a match for order id
				$billing_info  = $order->getBillingInfo();
				$billing_email = isset( $billing_info['order_email'] ) ? $billing_info['order_email'] : '';

				if ( is_email( $orderemail ) && $orderemail == $billing_email && $order->getUserId() <= 0 ) {
					// Clear rate limit on successful lookup
					\WPDMPP\Security\RateLimiter::clear( 'guest_order_lookup' );
					Session::set( 'guest_order', $orderid, 18000 );
					Session::set( 'order_email', $billing_email, 18000 );
					$this->guest_order_reply( 'success' );
				}

				// Order assigned to registered user, so no guest access, please login to access order
				if ( $order->getUserId() > 0 ) {
					$this->guest_order_reply( 'nogues' );
				}

				$this->guest_order_reply( 'noordr' );
			}

		}


		// =========================================================================
		// SETTINGS SAVE FUNCTION MOVED TO NEW ARCHITECTURE
		// =========================================================================
		// The saveSettings() function has been moved to:
		// \WPDMPP\Admin\Settings\SettingsService::ajaxSaveSettings()
		//
		// @since 7.0.0
		// @deprecated Use SettingsService::ajaxSaveSettings() instead
		// =========================================================================


		static function authorize_masterkey() {
			if ( WPDM()->package->validateMasterKey( wpdm_query_var( 'wpdmdl' ), wpdm_query_var( 'masterkey' ) ) && (int) get_wpdmpp_option( 'authorize_masterkey' ) === 1 ) {
				return true;
			}

			return false;
		}

		function download() {

			if ( wpdm_query_var( 'wpdmppd' ) !== '' || wpdm_query_var( 'wpdmppdl' ) !== '' ) {

				$wpdmdd = wpdm_query_var( 'wpdmppd' ) !== '' ? Crypt::decrypt( wpdm_query_var( 'wpdmppd' ), true ) : wpdmppdl_decode( wpdm_query_var( 'wpdmppdl' ) );

				if ( ! is_array( $wpdmdd ) || ! isset( $wpdmdd['ID'], $wpdmdd['oid'] ) ) {
					Messages::error( __( "&mdash; Invalid download link &mdash;", "wpdm-premium-packages" ), 1 );
				}

				$package = get_post( $wpdmdd['ID'], ARRAY_A );

				$PID    = (int) $wpdmdd['ID']; // Product ID
				$OID    = sanitize_text_field( $wpdmdd['oid'] ); // Order ID
				$domain = isset( $wpdmdd['domain'] ) ? sanitize_text_field( $wpdmdd['domain'] ) : '';

				$_REQUEST['oid'] = $OID;

				/*
                if (wpdm_query_var('preact') === 'login') {
                    $user = wp_signon(array('user_login' => wpdm_query_var('user'), 'user_password' => wpdm_query_var('pass')));
                    if (!$user->ID)
                        \WPDM_Messages::error(__( "Login failed!", "wpdm-premium-packages" ), 1);
                    else {
                        wp_set_current_user($user->ID);
                        Session::set('guest_order', $OID, 18000);
                    }
                }

                if (wpdm_query_var('wpdm_access_token') != '') {
                    $at = wpdm_query_var('wpdm_access_token');
                    if (!$at) die(json_encode(array('error' => 'Invalid Access Token!')));
                    $atx = explode("x", $at);
                    $uid = end($atx);
                    $uid = (int)$uid;
                    if (!$uid) die(json_encode(array('error' => 'Invalid Access Token!')));
                    $sat = get_user_meta($uid, '__wpdm_access_token', true);
                    if ($sat === '') die(json_encode(array('error' => 'Invalid Access Token!')));
                    if ($sat === $at)
                        wp_set_current_user($uid);
                    else
                        die(json_encode(array('error' => 'Invalid Access Token!')));
                }*/


				global $wpdb;
				$current_user = wp_get_current_user();
				$settings     = get_option( '_wpdmpp_settings' );

				$odata = OrderService::instance()->getOrder( $OID );
				$items = array_keys( $odata->getCartData() );
				if ( $domain !== '' && $domain === wpdm_query_var( 'domain' ) ) {

					if ( ! user_can( $odata->getUserId(), 'manage_options' ) ) {
						$current_user = get_user_by( 'id', $odata->getUserId() );
						wp_set_current_user( $odata->getUserId() );
						wp_set_auth_cookie( $odata->getUserId() );
					}
					if ( ! is_user_logged_in() ) {
						// Note: Cannot modify entity property directly; treat uid as 0 for logic below
						$odata_uid_override = 0;
					}
					$settings['guest_download'] = 1;
					Session::set( 'guest_order', $OID, 18000 );

				}

				$odata_uid = isset( $odata_uid_override ) ? $odata_uid_override : $odata->getUserId();

				$expire_date = $odata->getExpireDate() > 0 ? $odata->getExpireDate() : ( $odata->getDate() + ( get_wpdmpp_option( 'order_validity_period', 365 ) * 86400 ) );

				if ( $odata_uid != $current_user->ID && ! Session::get( 'guest_order' ) ) {
					Messages::error( __( "Invalid Access!", "wpdm-premium-packages" ), 1 );
				}
				if ( $odata->getOrderStatus() === 'Expired' || time() > $expire_date ) {
					Messages::error( __( "Sorry! Support and Update Access Period is Already Expired", "wpdm-premium-packages" ), 1 );
				}

				$base_price = get_post_meta( $PID, '__wpdm_base_price', true );


				$package['files'] = WPDM()->package->getFiles( $PID, true );

				//wpdmdd($package);
				$cart = $odata->getCartData();

				$cfiles = array();

				if ( isset( $cart[ $PID ]['files'] ) && is_array( $cart[ $PID ]['files'] ) && count( $cart[ $PID ]['files'] ) > 0 ) {
					$files = $cart[ $PID ]['files'];
					foreach ( $files as $fID ) {
						if ( $fID && isset( $package['files'][ $fID ] ) ) {
							$cfiles[ $fID ] = $package['files'][ $fID ];
						}
					}
				}

				if ( count( $cfiles ) === 0 ) {
					$all_licenses = wpdmpp_get_licenses();
					$starter      = array_keys( $all_licenses )[0];
					$_license     = wpdm_valueof( $cart, "{$PID}/license/id" );
					if ( ! $_license ) {
						$_license = $starter;
					}
					$license_pack = get_post_meta( $PID, "__wpdm_license_pack", true );
					$license_pack = wpdm_valueof( $license_pack, $_license );
					if ( is_array( $license_pack ) ) {
						foreach ( $license_pack as $fID ) {
							$cfiles[ $fID ] = $package['files'][ $fID ];
						}
					}
				}

				$package['individual_file_download'] = 1;

				if ( $base_price == 0 && $PID > 0 ) {
					//for free items
					$package['access'] = array( 'guest' );
					include( WPDM_SRC_DIR . "wpdm-start-download.php" );
				}


				//Member's Download
				if ( @in_array( $PID, $items ) && $OID != '' && is_user_logged_in() && $current_user->ID == $odata->getUserId() && $odata->getOrderStatus() == 'Completed' ) {
					//for premium item

					OrderService::instance()->updateOrder( array( 'download' => 1 ), $OID );

					if ( count( $cfiles ) > 0 && ! isset( $cfiles[ wpdm_query_var( 'ind' ) ] ) ) {
						if ( count( $cfiles ) > 1 ) {
							$zipped = \WPDM\__\FileSystem::zipFiles( $cfiles, $package['post_title'] . " " . $odata->getOrderId() );
							\WPDM\__\FileSystem::downloadFile( $zipped, basename( $zipped ) );
						} else {
							$file = array_shift( $cfiles );
							if ( ! file_exists( $file ) ) {
								$file = WPDM()->fileSystem->locateFile( $file );
							}
							\WPDM\__\FileSystem::downloadFile( $file, basename( $file ) );
						}

						exit;
					} else {
						Session::set( '__wpdmpp_authorized_download', 1 );
						$package['access'] = array( 'guest' );
						include( WPDM_SRC_DIR . "wpdm-start-download.php" );
					}
				}
				//wpdmdd($odata);
				//Guest's Download
				if ( @in_array( $PID, $items )
				     && $OID != ''
				     && $odata_uid == 0
				     && $odata->getOrderStatus() == 'Completed'
				     && isset( $settings['guest_download'] )
				     && Session::get( 'guest_order' ) === $OID ) {
					Session::set( '__wpdmpp_authorized_download', 1 );
					$package['access'] = array( 'guest' );
					OrderService::instance()->updateOrder( array( 'download' => 1 ), $OID );
					include( WPDM_SRC_DIR . "wpdm-start-download.php" );

				}

				Messages::error( __( "&mdash; Invalid download link &mdash;", "wpdm-premium-packages" ), 1 );
			}

			if ( wpdm_query_var( 'wpdmpp_file' ) ) {
				$file        = wpdm_query_var( 'wpdmpp_file' );
				$token       = wpdm_query_var( 'access_token' );
				$_token      = explode( "x", $token );
				$uid         = end( $_token );
				$valid_token = get_user_meta( $uid, "__wpdm_access_token", true );
				if ( $token === $valid_token ) {
					$files     = WPDMPP()->order->getPurchasedFiles( $uid );
					$file_path = wpdm_valueof( $files, $file );
					if ( $file_path !== '' ) {
						WPDM()->fileSystem->downloadFile( $file_path, basename( $file_path ), 10240, 0 );
						exit;
					} else {
						wp_die( esc_html__( 'Access Denied!', 'wpdm-premium-packages' ), esc_html__( 'Error', 'wpdm-premium-packages' ), array( 'response' => 403 ) );
					}
				} else {
					wp_die( esc_html__( 'Invalid Token!', 'wpdm-premium-packages' ), esc_html__( 'Error', 'wpdm-premium-packages' ), array( 'response' => 401 ) );
				}
			}

		}

		/**
		 * Create new Order
		 */
		function create_order() {
			$current_user = wp_get_current_user();

			//If session already contains an order ID
			if ( Session::get( 'orderid' ) ) {
				$order_info = OrderService::instance()->getOrder( Session::get( 'orderid' ) );
				// Check it the order ID in session is valid
				if ( is_object( $order_info ) && $order_info->getOrderId() ) {
					// Check if the order is not completed yet
					if ( $order_info->getOrderStatus() !== 'Completed' ) {
						$items = WPDMPP()->cart->getItems();
						$data  = array(
							'cart_data' => serialize( $items ),
							'items'     => serialize( array_keys( $items ) )
						);
						WPDMPP()->order->reCalculate( $order_info->getOrderId() );
						OrderService::instance()->saveOrderItems( $items, $order_info->getOrderId() );
						OrderService::instance()->updateOrder( $data, $order_info->getOrderId() );
						//Set the incomplete order ID as the current order ID
						$order_id = $order_info->getOrderId();
					} else {
						// The order is already completed, so clear the session and create a new order
						Session::clear( 'orderid' );
						$order_id = WPDMPP()->order->open();
					}
				} else {
					// The order ID in session is not valid, so create a new order
					$order_id = WPDMPP()->order->open();
				}

			} else {
				// No order ID in session, let's create a new order
				$order_id = WPDMPP()->order->open();
			}

			return $order_id;
		}

		/**
		 *  Set payment method for order
		 */
		/**
		 * Saving payment method info from checkout process
		 */
		function paynow() {
			if ( isset( $_REQUEST['task'] ) && $_REQUEST['task'] == "paynow" ) {

				if ( wpdmpp_is_cart_empty() ) {
					wp_die( '<div class="alert alert-danger" data-title="ERROR!">' . esc_html__( 'Cart is Empty!', 'wpdm-premium-packages' ) . '</div>' );
				}
				if ( ! is_user_logged_in() && ( ! isset( $_POST['billing']['order_email'] ) || ! is_email( $_POST['billing']['order_email'] ) ) ) {
					wp_die( '<div class="alert alert-danger" data-title="ERROR!">' . esc_html__( 'Please enter order confirmation email!', 'wpdm-premium-packages' ) . '</div>' );
				}

				$current_user = wp_get_current_user();

				$order_id = $this->create_order();

				OrderService::instance()->updateOrder( [ 'payment_method' => wpdm_query_var( 'payment_method', 'txt' ) ], $order_id );

				//Update users billing info
				if ( is_user_logged_in() ) {
					$billing_info                = wpdm_sanitize_array( $_POST['billing'] );
					$billing_info['order_email'] = sanitize_email( $_POST['billing']['order_email'] );
					$billing_info['email']       = sanitize_email( $_POST['billing']['order_email'] );
					$billing_info['phone']       = '';
					$customer_billing_address    = get_user_meta( $current_user->ID, 'user_billing_shipping', true );
					if ( ! $customer_billing_address ) {
						update_user_meta( $current_user->ID, 'user_billing_shipping', serialize( array( 'billing' => $billing_info ) ) );
					}
				}
				$this->place_order( $order_id );
				wp_die();
			}
		}


		/**
		 * Placing order from checkout process
		 */
		function place_order( $order_id ) {
			//if(floatval(wpdmpp_get_cart_total()) <= 0 ) return;
			global $wpdb;
			$order       = OrderService::instance()->getOrder( $order_id );
			$order_total = $order->getTotal();
			$tax         = $order->getTax();

			$items = $order->getCartData();
			//$cart_data = wpdmpp_get_cart_data();

			if ( ! is_array( $items ) || count( $items ) == 0 ) {
				Messages::Error( __( "Cart is Empty!", "wpdm-premium-packages" ), 0 );
				wp_die();
			}

			$order_title = $order->getTitle();

			do_action( "wpdm_before_placing_order", $order_id );

			// If order total is not 0 then go to payment gateway
			if ( $order_total > 0 ) {

				$result = PaymentService::instance()->processPayment( strtolower( wpdm_query_var( 'payment_method', 'txt' ) ), [
					'order_id'    => $order_id,
					'order_title' => $order_title,
					'amount'      => number_format( $order_total, 2, ".", "" ),
				]);

				if ( ! empty( $result['redirect'] ) ) {
					wpdmpp_js_redirect( $result['redirect'] );
				}

				wp_die();

			} else {
				// if order total is 0 then empty cart and redirect to home
				OrderService::instance()->completeOrder( $order_id );
				wpdmpp_empty_cart();
				wpdmpp_js_redirect( wpdmpp_orders_page( 'id=' . $order_id ) );
			}
		}

		function clone_order() {
			if ( ! is_user_logged_in() ) {
				return;
			}
			$order = OrderService::instance()->getOrder( wpdm_query_var( 'clone_order', 'txt' ) );
			if ( ! $order || ! $order->getOrderId() || (int) $order->getUserId() !== get_current_user_id() ) {
				return;
			}
			WPDMPP()->cart->clear();
			//wpdmdd($order->getCartData());
			foreach ( $order->getCartData() as $pid => $item ) {
				WPDMPP()->cart->addItem( $pid, wpdm_valueof( $item, 'license/id' ) );
			}
			wpdmpp_redirect( wpdmpp_cart_url() );
			//wpdmdd($cart_data);
		}

		function is_auto_new_active() {
			global $wpdmpp_settings;
			$wpdmpp_settings['order_validity_period'] = (int) $wpdmpp_settings['order_validity_period'] > 0 ? (int) $wpdmpp_settings['order_validity_period'] : 365;
			if ( isset( $wpdmpp_settings['auto_renew'], $wpdmpp_settings['order_validity_period'] ) && $wpdmpp_settings['auto_renew'] == 1 && $wpdmpp_settings['order_validity_period'] > 0 ) {
				return true;
			}

			return false;
		}

		/**
		 * Payment notification process/ IPN verification
		 */
		function payment_notification() {
			if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == "wpdmpp-payment-notification" ) {
				$className = sanitize_text_field( $_REQUEST['class'] );
				$gateway = PaymentService::instance()->getGateway( strtolower( $className ) );
				if ( $gateway && method_exists( $gateway, 'handleWebhook' ) ) {
					$result = $gateway->handleWebhook();
					if ( ! empty( $result['success'] ) ) {
						wp_die( 'OK' );
					}
				}
				wp_die( 'FAILED' );
			}
		}

		/**
		 * Payment notification process/ IPN verification
		 */
		function comeplete_buynow_action() {
			if ( wpdm_query_var( 'action', 'txt' ) === "wpdmpp-complete-buynow" ) {
				$className = sanitize_text_field( $_REQUEST['class'] );
				$gateway = PaymentService::instance()->getGateway( strtolower( $className ) );
				if ( $gateway && method_exists( $gateway, 'completeBuyNow' ) ) {
					$gateway->completeBuyNow();
				}
			}
		}

		function buy_now( $product_id, $license = '', $extras = array() ) {
			global $wpdmpp;
			$wpdmpp->add_to_cart( $product_id );
			$wpdmpp->create_order();
			$order = OrderService::instance()->getOrder( Session::get( 'orderid' ) );

			wpdmpp_calculate_discount();
			OrderService::instance()->saveOrderItems( wpdmpp_get_cart_data(), Session::get( 'orderid' ) );
			$order_total = WPDMPP()->order->calcOrderTotal( Session::get( 'orderid' ) );

			$tax = 0;

			foreach ( $pids as $pid ) {
				$price = wpdmpp_effective_price( $pid );
				$total += $price;
			}


			$cart_data[ $product_id ] = array(
				'ID'              => $product_id,
				'post_title'      => get_the_title( $product_id ),
				'quantity'        => 1,
				'variation'       => array(),
				'variations'      => array(),
				'files'           => array(),
				'price'           => $price,
				'prices'          => 0,
				'discount_amount' => 0
			);

			$o->newOrder( wpdm_query_var( 'oid' ), 'Custom Order', $items, $total, 0, 'Completed', 'Completed', serialize( $cart_data ) );

			OrderService::instance()->saveOrderItems( $cart_data, wpdm_query_var( 'oid' ) );

			$subtotal = wpdmpp_get_cart_subtotal();
			if ( wpdmpp_tax_active() && Session::get( 'tax' ) ) {
				$tax         = Session::get( 'tax' );
				$order_total = $subtotal + $tax;
			}
			$cart_id = wpdmpp_cart_id();
			$coupon  = wpdmpp_get_cart_coupon();

			$grand_total = $order_total - (double) wpdm_valueof( $coupon, 'discount', 0 );

			$grand_total = wpdmpp_price_format( $grand_total, false, false );
			$update_data = array(
				'subtotal'        => $subtotal,
				'cart_discount'   => 0,
				'payment_method'  => 'PayPal',
				'coupon_discount' => $coupon['discount'],
				'coupon_code'     => $coupon['code'],
				'tax'             => $tax,
				'order_notes'     => '',
				'total'           => $grand_total,
			);
			if ( is_user_logged_in() && $order->getUserId() == 0 ) {
				$update_data['uid'] = get_current_user_id();
			}
			OrderService::instance()->updateOrder( $update_data, Session::get( 'orderid' ) );

		}

		/**
		 * Withdraw money from paypal notification
		 */
		function wpdmpp_update_withdraw_status() {
			if ( current_user_can( WPDMPP_ADMIN_CAP ) && wp_verify_nonce( wpdm_query_var( '__wpdmppwn_nonce' ), NONCE_KEY ) ) {

				global $wpdb;
				$wpdb->update(
					"{$wpdb->prefix}ahm_withdraws",
					array(
						'status' => 1
					),
					array( 'id' => sanitize_text_field( $_REQUEST['wid'] ) ),
					array(
						'%d'
					),
					array( '%d' )
				);

				wp_send_json( array( 'success' => 1 ) );

			}
		}


		/**
		 * Load Scripts and Styles
		 *
		 * @param $hook
		 */
		function wpdmpp_enqueue_scripts( $hook ) {

			$settings  = get_option( '_wpdmpp_settings' );
			$cart_page = isset( $settings['page_id'] ) ? $settings['page_id'] : 0;

			$wpdmpp_js_suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			wp_enqueue_script( 'wpdm-pp-js', WPDMPP_BASE_URL . "assets/js/wpdmpp-front{$wpdmpp_js_suffix}.js", array(
				'jquery',
				'jquery-form'
			), WPDMPP_VERSION );
			wp_localize_script( 'wpdm-pp-js', 'wpdmppIcons', \WPDMPP\UI\Icons::toJson( 16 ) );
			wp_localize_script( 'wpdm-pp-js', 'wpdmppApi', [
				'root'  => esc_url_raw( rest_url( 'wpdmpp/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			] );
			if ( ! isset( $settings['disable_fron_end_css'] ) || (int) $settings['disable_fron_end_css'] === 0 ) {
				wp_enqueue_style( 'wpdmpp-front', WPDMPP_BASE_URL . 'assets/css/wpdmpp.css', 999999 );
			}

			//if((int)$cart_page === (int)get_the_ID()){
			//wp_enqueue_script('jquery-validate');
			//}

			if ( get_the_ID() == wpdm_valueof( $settings, "orders_page_id" ) || ( isset( $settings['guest_order_page_id'] ) && get_the_ID() == $settings['guest_order_page_id'] ) ) {
				wp_enqueue_script( 'thickbox' );
				wp_enqueue_style( 'thickbox' );
				wp_enqueue_script( 'media-upload' );
				wp_enqueue_media();
			}
		}

		function wpdmpp_admin_enqueue_scripts( $hook ) {
			if ( get_post_type() == 'wpdmpro' || strstr( $hook, 'dmpro_page' ) ) {
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'jquery-form' );
				wp_enqueue_script( 'jquery-ui-core' );
				wp_enqueue_script( 'jquery-ui-datepicker' );
				wp_enqueue_script( 'jquery-ui-accordion' );

				wp_enqueue_style( 'wpdmpp-admin', WPDMPP_BASE_URL . 'assets/css/wpdmpp-admin.min.css' );
				wp_enqueue_script( 'wpdmpp-admin-js', WPDMPP_BASE_URL . 'assets/js/wpdmpp-admin.js', array( 'jquery' ) );
				wp_localize_script( 'wpdmpp-admin-js', 'wpdmppIcons', \WPDMPP\UI\Icons::toJson( 16 ) );
				wp_localize_script( 'wpdmpp-admin-js', 'wpdmppApi', [
					'root'  => esc_url_raw( rest_url( 'wpdmpp/v1/' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				] );

				// Load Download Manager Scripts
				//wp_enqueue_style('wpdm-admin-bootstrap', WPDM_BASE_URL . 'assets/bootstrap3/css/bootstrap.css');
				//wp_enqueue_script('wpdm-admin-bootstrap', WPDM_BASE_URL . 'assets/bootstrap3/js/bootstrap.min.js', array('jquery'));
				wp_enqueue_script( 'jquery-validate', WPDM_BASE_URL . 'assets/js/jquery.validate.min.js', array( 'jquery' ) );
				//wp_enqueue_script('wpdm-bootstrap-select', WPDM_BASE_URL.'assets/js/bootstrap-select.min.js',  array('jquery', 'wpdm-admin-bootstrap'));
				//wp_enqueue_style('wpdm-bootstrap-select', WPDM_BASE_URL.'assets/css/bootstrap-select.min.css');
			}
		}

		/**
		 * Check if a Package is premium
		 *
		 * @param $pid
		 *
		 * @return bool
		 */
		public static function isPremium( $pid ) {
			$price = wpdmpp_product_price( $pid );
			if ( $price > 0 ) {
				return true;
			}

			return false;
		}

		/**
		 * @usage Check if user purchased an item
		 *
		 * @param $pid
		 * @param int $uid
		 *
		 * @return bool|string|null
		 */

		public static function hasPurchased( $pid, $uid = 0 ) {
			global $wpdb;
			$current_user = wp_get_current_user();
			if ( ! is_user_logged_in() && ! $uid ) {
				return false;
			}
			$uid     = $uid ? (int) $uid : (int) $current_user->ID;
			$pid     = (int) $pid;
			$orderid = $wpdb->get_var( $wpdb->prepare(
				"SELECT o.order_id FROM {$wpdb->prefix}ahm_orders o
				 INNER JOIN {$wpdb->prefix}ahm_order_items oi ON o.order_id = oi.oid
				 WHERE o.uid = %d AND oi.pid = %d AND o.order_status = 'Completed'",
				$uid, $pid
			) );

			return $orderid;
		}

		/**
		 * Generate Download URL
		 *
		 * @param $id Package ID
		 *
		 * @return string
		 */
		static function customerDownloadLink( $id ) {
			global $wpdmpp_settings;
			$downloadurl = self::customerDownloadURL( $id );
			$label       = get_post_meta( $id, '__wpdm_link_label', true );
			$label       = $label ? $label : __( 'Download', 'wpdm-premium-packages' );
			if ( $downloadurl ) {
				return "<a class='btn btn-success btn-lg' href='{$downloadurl}'>{$label}</a>";
			}

			return isset( $wpdmpp_settings['cdl_fallback'] ) && $wpdmpp_settings['cdl_fallback'] == '1' ? wpdmpp_add_to_cart_html( $id ) : "";
		}

		/**
		 * @param $pid
		 * @param null $orderid
		 * @param array $extras
		 *
		 * @return string
		 */
		static function customerDownloadURL( $pid, $orderid = null, $extras = array() ) {
			if ( ! $orderid ) {
				$orderid = self::hasPurchased( $pid );
			}
			if ( ! $orderid ) {
				return null;
			}
			$params        = is_array( $extras ) ? $extras : array();
			$params['ID']  = $pid;
			$params['oid'] = $orderid;
			if ( defined( 'WPDMPPD_PERMALINK' ) && WPDMPPD_PERMALINK === true ) {
				$wpdmppd = wpdmppdl_encode( $params );

				return home_url( "/?wpdmppdl={$wpdmppd}" );
			} else {
				$wpdmppd = Crypt::encrypt( $params );

				return home_url( "/?wpdmppd={$wpdmppd}" );
			}
		}

		function hideSingleFileDownloadLink( $link, $fileID, $package ) {
			if ( ! isset( $package['ID'] ) ) {
				return $link;
			}

			// Cache effective price per package - same for all files
			static $price_cache = [];
			$pid = $package['ID'];

			if ( ! isset( $price_cache[ $pid ] ) ) {
				$price_cache[ $pid ] = wpdmpp_effective_price( $pid );
			}

			if ( $price_cache[ $pid ] > 0 ) {
				$link = '';
			}

			return $link;
		}

		public static function hasFreeFile( $id = null ) {
			if ( ! $id ) {
				$id = get_the_ID();
			}
			$fd = maybe_unserialize( get_post_meta( $id, '__wpdm_free_downloads', true ) );
			if ( is_array( $fd ) && count( $fd ) > 0 && $fd[0] != '' ) {
				return $fd;
			}

			return false;
		}

		function freeDownload() {
			if ( isset( $_GET['wpdmdlfree'] ) ) {
				$id        = (int) $_GET['wpdmdlfree'];
				$freefiles = self::hasFreeFile( $id );

				if ( ! $freefiles ) {
					wp_die( 'No free file found!' );
				}
				$pack = array( 'ID' => $id );
				//do_action("wpdm_onstart_download", $pack);

				if ( count( $freefiles ) > 1 ) {
					foreach ( $freefiles as &$freefile ) {
						$freefile = str_replace( site_url( '/' ), ABSPATH, $freefile );
					}
					$zipped = \WPDM\__\FileSystem::zipFiles( $freefiles, get_the_title( $id ) );
					\WPDM\__\FileSystem::downloadFile( $zipped, basename( $zipped ) );
				} else {
					wp_redirect( array_pop( $freefiles ) );
				}
				exit;
			}
		}

		/**
		 * @param $id
		 * @param $link_label
		 * @param string $class
		 *
		 * @return string
		 */
		static function free_download_button( $id, $link_label, $class = 'btn btn-lg btn-info btn-block' ) {
			return "<a href='" . home_url( '/?wpdmdlfree=' . $id ) . "' class='{$class}' >" . $link_label . "</a>";
		}


		function downloadLink( $link, $package ) {
			$effective_price = wpdmpp_effective_price( $package['ID'] );
			if ( $effective_price > 0 ) {

				if ( wpdm_valueof( $package, 'template_type' ) === 'link' ) {
					return wpdmpp_waytocart( $package ) . print_r( $package, 1 );
				} else {
					return wpdmpp_add_to_cart_html( $package['ID'] );
				}
			}

			return $link;
		}


		/**
		 * @param $vars
		 *
		 * @return mixed
		 */
		function fetchTemplateTag( $vars ) {
			global $wpdb, $wpdmpp_settings;


			$effective_price           = wpdmpp_effective_price( $vars['ID'] );
			$vars['effective_price']   = $effective_price;
			$vars['currency']          = wpdmpp_currency_sign();
			$vars['currency_code']     = wpdmpp_currency_code();
			$vars['free_download_btn'] = "";

			if ( ! isset( $vars['post_author'] ) ) {
				$product = (array) get_post( $vars['ID'] );
				$vars    += $product;
			}
			$store = get_user_meta( $vars['post_author'], '__wpdm_public_profile', true );
			if ( ! isset( $vars['author_name'] ) ) {
				$author = get_userdata( $vars['post_author'] );
				if ( $author ) {
					$vars['author_name'] = $author->display_name;
				}
			}
			$vars['store_name']  = isset( $store['title'] ) ? $store['title'] : $vars['author_name'];
			$vars['store_intro'] = isset( $store['intro'] ) ? $store['intro'] : '';
			$vars['store_logo']  = isset( $store['logo'] ) && $store['logo'] != '' ? "<img class='store-logo' src='{$store['logo']}' alt='{$vars['store_name']}' />" : get_avatar( $vars['post_author'], 512 );

			if ( $effective_price > 0 && self::hasFreeFile( $vars['ID'] ) ) {
				$vars['free_download_btn'] = self::free_download_button( $vars['ID'], $vars['link_label'] );
				$vars['free_download_url'] = home_url( '/?wpdmdlfree=' . $vars['ID'] );
			} else {
				$vars['free_download_btn'] = $vars['free_download_url'] = '';
			}
			if ( $effective_price > 0 || get_post_meta( $vars['ID'], '__wpdm_pay_as_you_want', true ) == 1 ) {
				$vars['base_price']             = wpdmpp_price_format( get_post_meta( $vars['ID'], '__wpdm_base_price', true ) );
				$vars['sales_price']            = wpdmpp_price_format( get_post_meta( $vars['ID'], '__wpdm_sales_price', true ) );
				$vars['addtocart_url']          = wpdmpp_cart_page( array( 'addtocart' => $vars['ID'] ) );
				$vars['addtocart_link']         = wpdmpp_waytocart( $vars );
				$vars['addtocart_button']       = $vars['addtocart_link'];
				$vars['addtocart_form']         = wpdmpp_add_to_cart_html( $vars['ID'] );
				$vars['customer_download_link'] = $this->customerDownloadLink( $vars['ID'] );
				if ( isset( $vars['__template_type'] ) && $vars['__template_type'] == 'link' ) {
					$vars['download_link'] = $vars['addtocart_button'];
				} else {
					$vars['download_link'] = $vars['addtocart_form'];
				}
				$vars['download_link_extended'] = $vars['addtocart_form'];
				$vars['download_link_popup']    = $vars['addtocart_button'];
				$vars['price_range']            = wpdmpp_price_range( $vars['ID'] );
			} else {
				$vars['addtocart_url']          = $vars['download_url'];
				$vars['addtocart_link']         = $vars['download_link'];
				$vars['addtocart_form']         = $vars['download_link'];
				$vars['customer_download_link'] = $vars['download_link'];
				$vars['price_range']            = wpdmpp_currency_sign() . '0.00';
				$vars['sales_price']            = $vars['base_price'] = '';
			}

			return $vars;
		}

		function template_editor_menu() {
			?>
			<li class="dropdown">
				<a href="#" id="droppp" role="button" class="dropdown-toggle"
				   data-toggle="dropdown"><?php _e( 'Premium Package', 'wpdm-premium-packages' ); ?><b
						class="caret"></b></a>
				<ul class="dropdown-menu" role="menu" aria-labelledby="droppp">
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[addtocart_url]"><?php _e( 'AddToCart URL', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[addtocart_link]"><?php _e( 'AddToCart Link', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[addtocart_form]"><?php _e( 'AddToCart Form', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[customer_download_link]"><?php _e( 'Customer Download Link', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[free_download_url]"><?php _e( 'Free Download Button', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[free_download_btn]"><?php _e( 'Free Download URL', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[price_range]"><?php _e( 'Price Range', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[premium_file_list]"><?php _e( 'File List Price', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[effective_price]"><?php _e( 'Effective Item Price', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[currency_code]"><?php _e( 'Currency Code', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[currency]"><?php _e( 'Currency Sign', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[base_price]"><?php _e( 'Base Price', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[sales_price]"><?php _e( 'Sales Price', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[store_name]"><?php _e( 'Shop Name', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[store_intro]"><?php _e( 'Shop Intro', 'wpdm-premium-packages' ); ?></a>
					</li>
					<li role="presentation"><a role="menuitem" tabindex="-1"
					                           href="#[store_logo]"><?php _e( 'Shop Logo', 'wpdm-premium-packages' ); ?></a>
					</li>
				</ul>
			</li>

			<?php
		}

		function template_tag_row() {
			?>
			<tr>
				<td><input type="text" readonly="readonly" class="form-control" onclick="this.select()"
				           value="[addtocart_url]" style="font-size:10px;width: 120px;text-align: center;"></td>
				<td>- <?php echo __( 'AddToCart URL for a package', 'wpdm-premium-packages' ); ?></td>
			</tr>
			<tr>
				<td><input type="text" readonly="readonly" class="form-control" onclick="this.select()"
				           value="[addtocart_link]" style="font-size:10px;width: 120px;text-align: center;"></td>
				<td>- <?php echo __( 'AddToCart Link for a package', 'wpdm-premium-packages' ); ?></td>
			</tr>
			<tr>
				<td><input type="text" readonly="readonly" class="form-control" onclick="this.select()"
				           value="[addtocart_form]" style="font-size:10px;width: 120px;text-align: center;"></td>
				<td>- <?php echo __( 'AddToCart Form', 'wpdm-premium-packages' ); ?></td>
			</tr>
			<tr>
				<td><input type="text" readonly="readonly" class="form-control" onclick="this.select()"
				           value="[customer_download_link]" style="font-size:10px;width: 120px;text-align: center;">
				</td>
				<td>- <?php echo __( 'Customer Download Link', 'wpdm-premium-packages' ); ?></td>
			</tr>
			<tr>
				<td><input type="text" readonly="readonly" class="form-control" onclick="this.select()"
				           value="[free_download_btn]" style="font-size:10px;width: 120px;text-align: center;"></td>
				<td>- <?php echo __( 'Free Download Button', 'wpdm-premium-packages' ); ?></td>
			</tr>
			<tr>
				<td><input type="text" readonly="readonly" class="form-control" onclick="this.select()"
				           value="[free_download_url]" style="font-size:10px;width: 120px;text-align: center;"></td>
				<td>- <?php echo __( 'Free Download URL', 'wpdm-premium-packages' ); ?></td>
			</tr>
			<?php
		}

		/**
		 * Required for guest checkout
		 */
		function wpdmpp_invoice_field() {
			$oid = Session::get( "orderid" );
			if ( $oid ) {
				echo "<input type='hidden' name='invoice' value='" . sanitize_text_field( $oid ) . "' />";
			}
		}

		/**
		 * Link Guest Order when user logging in
		 *
		 * @param $user_login
		 * @param $user
		 */
		function wpdmpp_associate_invoice( $user_login, $user ) {
			if ( isset( $_POST['invoice'] ) ) {
				$orderdata = OrderService::instance()->getOrder( sanitize_text_field( $_POST['invoice'] ) );
				if ( $orderdata && intval( $orderdata->getUserId() ) == 0 ) {
					OrderService::instance()->updateOrder( array( 'uid' => $user->ID ), sanitize_text_field( $_POST['invoice'] ) );
					do_action("wpdm_associate_invoice", $user->ID, $orderdata);
				}
			}
		}

		/**
		 * Link Guest Order when user Signing Up
		 *
		 * @param $user_id
		 */
		function wpdmpp_associate_invoice_signup( $user_id ) {
			if ( isset( $_POST['invoice'] ) ) {
				$orderdata = OrderService::instance()->getOrder( sanitize_text_field( $_POST['invoice'] ) );
				if ( $orderdata && intval( $orderdata->getUserId() ) == 0 ) {
					OrderService::instance()->updateOrder( array( 'uid' => $user_id ), sanitize_text_field( $_POST['invoice'] ) );
					\WPDMPP\Customer\CustomerService::getInstance()->addCustomer( $user_id );
					do_action("wpdm_associate_invoice", $user_id, $orderdata);
				}
			}
		}

		/**
		 * Resolve unassigned Order
		 */
		function wpdmpp_resolveorder() {
			$current_user = wp_get_current_user();
			$data         = OrderService::instance()->getOrder( sanitize_text_field( $_REQUEST['orderid'] ) );
			if ( ! $data ) {
				wp_send_json_error( array( 'message' => __( 'Order not found!', 'wpdm-premium-packages' ) ) );
			}
			if ( $data->getUserId() != 0 ) {
				if ( $data->getUserId() == $current_user->ID ) {
					wp_send_json_error( array( 'message' => __( 'The order is already linked to your account!', 'wpdm-premium-packages' ) ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'The order is already linked to an account!', 'wpdm-premium-packages' ) ) );
				}
			}
			OrderService::instance()->updateOrder( array( 'uid' => $current_user->ID ), $data->getOrderId() );
			\WPDMPP\Customer\CustomerService::getInstance()->addCustomer( $current_user->ID );
			wp_send_json_success( array( 'message' => __( 'Order linked successfully!', 'wpdm-premium-packages' ) ) );
		}

		/**
		 * Filter for locked Downloads
		 *
		 * @param $lock
		 * @param $id
		 *
		 * @return string
		 */
		function lockDownload( $lock, $id ) {
			$effective_price = wpdmpp_effective_price( $id );
			if ( intval( $effective_price ) > 0 ) {
				$lock = 'locked';
			}

			return $lock;
		}

		function wpdmpp_guest_download_link() {
			global $wp_query;

			if ( isset( $wp_query->query_vars['udb_page'] ) && strstr( $wp_query->query_vars['udb_page'], 'purchases' ) && wpdmpp_guest_order_page() ):
				include_once \WPDM\__\Template::locate( "partials/guest_order_page_link.php", WPDMPP_TPL_DIR );
			endif;
		}

		function toggleAutoRenew() {
			if ( isset( $_REQUEST['__arnonce'] ) && wp_verify_nonce( $_REQUEST['__arnonce'], NONCE_KEY ) ) {
				$order = OrderService::instance()->getOrder( sanitize_text_field( $_REQUEST['orderid'] ) );
				$renew = (int)$order->hasAutoRenew() === 1 ? 0 : 1;
				OrderService::instance()->updateOrder( array( 'auto_renew' => $renew ), $order->getOrderId() );
				$dt = array( 'renew' => $renew );
				if ( $renew == 0 ) {
					$dt['payment_method'] = $order->getPaymentMethod();
					if ( PaymentService::instance()->cancelSubscription( $order->getOrderId() ) ) {
						$dt['canceled'] = 1;
					}
				}
				wp_send_json( $dt );
			} else {
				wp_send_json_error( array( 'message' => __( 'Session Expired!', 'wpdm-premium-packages' ) ) );
			}
		}

		function toggleManualRenew() {
			if ( isset( $_REQUEST['__mrnonce'] ) && wp_verify_nonce( $_REQUEST['__mrnonce'], NONCE_KEY ) ) {
				$orderID = sanitize_text_field( $_REQUEST['orderid'] );
				$mrenew  = (int) OrderService::instance()->getMeta( $orderID, 'manual_renew' );
				$mrenew  = $mrenew ? 0 : 1;
				OrderService::instance()->updateMeta( $orderID, 'manual_renew', $mrenew );
				wp_send_json_success( array( 'mrenew' => $mrenew ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Session Expired!', 'wpdm-premium-packages' ) ) );
			}
		}

		function cancel_subscription() {
			if ( isset( $_REQUEST['__cansub'] ) && wp_verify_nonce( $_REQUEST['__cansub'], NONCE_KEY ) ) {
				$order = OrderService::instance()->getOrder( sanitize_text_field( $_REQUEST['orderid'] ) );
				$renew = 0;
				OrderService::instance()->updateOrder( array( 'auto_renew' => $renew ), $order->getOrderId() );
				$dt = array( 'renew' => $renew );
				$dt['payment_method'] = $order->getPaymentMethod();
				if ( PaymentService::instance()->cancelSubscription( $order->getOrderId() ) ) {
					$dt['canceled'] = 1;
				}
				$oid     = $order->getOrderId();
				$message = "Subscription Canceled For Order# {$oid}<br/><a style='background-color:#19B999;border:none;border-radius:3px;color:#ffffff !important;display:inline-block;font-size:14px;font-weight:bold;outline:none!important;padding:5px 15px;margin:10px auto;text-decoration:none;' href='" . admin_url( "/edit.php?post_type=wpdmpro&page=orders&task=vieworder&id={$oid}" ) . "'>View Order</a>";
				$params  = array(
					'subject'  => "Subscription Canceled: Order# {$oid}",
					'to_email' => get_option( "admin_email" ),
					'message'  => $message
				);
				\WPDM\__\Email::send( 'default', $params );
				wp_send_json( $dt );
			} else {
				wp_send_json_error( array( 'message' => __( 'Session Expired!', 'wpdm-premium-packages' ) ) );
			}
		}


		function privacy_settings() {
			?>
			<div class="panel panel-default">
				<div class="panel-heading"><?php _e( 'Checkout Settings', 'wpdm-premium-packages' ); ?></div>
				<div class="panel-body">

					<div class="form-group">
						<input type="hidden" value="0" name="__wpdm_checkout_privacy"/>
						<label><input style="margin: 0 10px 0 0"
						              type="checkbox" <?php checked( get_option( '__wpdm_checkout_privacy' ), 1 ); ?>
						              value="1"
						              name="__wpdm_checkout_privacy"><?php _e( 'Must agree with privacy policy before checkout', 'wpdm-premium-packages' ); ?>
						</label><br/>
						<em><?php _e( 'User must agree with privacy policy before they purchase any item', 'wpdm-premium-packages' ); ?></em>
					</div>
					<div class="form-group">
						<label><?php _e( 'Privacy policy label:', 'wpdm-premium-packages' ); ?></label>
						<input type="text" class="form-control"
						       value="<?php echo get_option( '__wpdm_checkout_privacy_label' ); ?>"
						       name="__wpdm_checkout_privacy_label">
					</div>

				</div>
			</div>

			<?php
		}


		function expire_orders() {
			// Verify nonce
			if ( ! wp_verify_nonce( wpdm_query_var( '__wpdmpp_expire_nonce' ), 'wpdmpp_expire_orders' ) ) {
				wp_send_json_error( array( 'message' => 'Security check failed' ), 403 );
			}

			// Check capability
			if ( ! current_user_can( WPDMPP_ADMIN_CAP ) ) {
				wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
			}

			$oids = wpdm_query_var( 'oids' );
			if ( is_array( $oids ) ) {
				$oids = array_map( 'sanitize_text_field', $oids );
				foreach ( $oids as $oid ) {
					if ( ! empty( $oid ) ) {
						OrderService::instance()->expireOrder( $oid );
					}
				}
			}
			wp_send_json_success( array( 'message' => 'Done!' ) );
		}

		function email_payment_link() {
			// Verify nonce
			if ( ! wp_verify_nonce( wpdm_query_var( '__wpdmpp_payment_link_nonce' ), 'wpdmpp_email_payment_link' ) ) {
				wp_send_json_error( array( 'message' => 'Security check failed' ), 403 );
			}

			// Check capability - only admins can send payment links
			if ( ! current_user_can( WPDMPP_ADMIN_CAP ) ) {
				wp_send_json_error( array( 'message' => 'Permission denied' ), 403 );
			}

			$price       = abs( __::query_var( 'price', 'double' ) );
			$name        = sanitize_text_field( __::query_var( 'name', 'txt' ) );
			$desc        = sanitize_textarea_field( __::query_var( 'desc', 'txt' ) );
			$emails      = sanitize_email( __::query_var( 'emails', 'txt' ) );

			// Validate email
			if ( ! is_email( $emails ) ) {
				wp_send_json_error( array( 'message' => 'Invalid email address' ), 400 );
			}

			// Validate price
			if ( $price <= 0 ) {
				wp_send_json_error( array( 'message' => 'Invalid price' ), 400 );
			}

			$plink       = home_url( "/?" ) . http_build_query( array(
				'addtocart' => 'dynamic',
				'price'     => $price,
				'name'      => $name,
				'desc'      => $desc,
				'recurring' => 0
			) );
			$paymentinfo = "<small style='color: #aaaaaa'>" . esc_html__( 'Reason', WPDMPP_TEXT_DOMAIN ) . "</small><br/>" . esc_html( $name ) . "<hr style='border-top:0;border-bottom: 1px solid #dddddd;box-shadow: none'/><small style='color: #aaaaaa'>" . esc_html__( 'Description', WPDMPP_TEXT_DOMAIN ) . "</small><br/>" . esc_html( $desc ) . "<hr style='border-top:0;border-bottom: 1px solid #dddddd;box-shadow: none'/><small style='color: #aaaaaa'>" . esc_html__( 'Payment Amount', WPDMPP_TEXT_DOMAIN ) . "</small><h3 style='margin: 0'>" . esc_html( wpdmpp_price_format( $price ) ) . "</h3>";
			$msg         = __MailUI::panel( __( 'Payment request', WPDMPP_TEXT_DOMAIN ), [ wpautop( __::query_var( 'msg', 'kses' ) ) ] ) . "<div style='height: 15px;display: block'></div>";
			$msg         .= __MailUI::panel( __( 'Payment details', WPDMPP_TEXT_DOMAIN ), [ $paymentinfo ] ) . '<a class="button" style="display:block;text-align:center" href="' . esc_url( $plink ) . '">' . esc_html__( 'Proceed to payment', WPDMPP_TEXT_DOMAIN ) . '</a>';
			$params      = [
				'to_email' => $emails,
				'subject'  => sprintf( __( 'Payment request from %s', WPDMPP_TEXT_DOMAIN ), get_option( 'blogname' ) ),
				'message'  => $msg
			];
			Email::send( "default", $params );
			wp_send_json_success( array( 'message' => 'Email sent successfully' ) );
		}

		function active_payment_gateways() {
			global $payment_methods;
			$settings        = maybe_unserialize( get_option( '_wpdmpp_settings' ) );
			$payment_methods = apply_filters( 'payment_method', $payment_methods );

			if ( ! empty( $settings['pmorders'] ) && is_array( $settings['pmorders'] ) ) {
				$sorted = [];
				foreach ( $settings['pmorders'] as $pm ) {
					if ( in_array( $pm, $payment_methods, true ) ) {
						$sorted[] = $pm;
					}
				}
				foreach ( $payment_methods as $pm ) {
					if ( ! in_array( $pm, $sorted, true ) ) {
						$sorted[] = $pm;
					}
				}
				$payment_methods = $sorted;
			}

			return $payment_methods;
		}

		function notice() {
			if (is_admin() && current_user_can('manage_options') && \WPDMPP_INSTALLER::dbUpdateRequired()) {
				$message = sprintf(__('A database update is required for <strong>Premium Packages %s</strong>. Please click the button on the right to update your database now.', WPDMPP_TEXT_DOMAIN), WPDMPP_VERSION);
				printf('<div id="wpdmvnotice" class="notice notice-warning  is-dismissible w3eden"><div class="media" style="padding: 20px;line-height: 22px"><div class="ml-3 pull-right"><a class="btn btn-primary btn-sm" href="%2$s">Update Database</a></div><div class="media-body">%1$s</div></div></div>', $message, admin_url('edit.php?post_type=wpdmpro&page=wpdm-settings&tab=ppsettings&ppstab=ppbasic'));
			}
		}


	}

endif;

if ( defined( 'WPDM_VERSION' ) )
	$wpdmpp = new WPDMPremiumPackage();
else {
	class RequireWPDM {

		function __construct() {
			add_action( 'admin_notices', array( $this, 'check' ) );
		}

		function check() {
			$class   = 'notice notice-error';
			$message = '<strong>Missing a required plugin!</strong><br/>Please install/activate <a href="' . admin_url( '/plugin-install.php?tab=favorites&user=codename065' ) . '" target="_blank"><strong>WordPress Download Manager</strong></a> to use WPDM - Premium Packages plugin';
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), ( $message ) );

			if (is_admin() && current_user_can('manage_options') && class_exists('WPDMPP_INSTALLER') && \WPDMPP_INSTALLER::dbUpdateRequired()) {
				$message = sprintf(__('A database update is required for <strong>Premium Packages %s</strong>. Please click the button on the right to update your database now.', WPDMPP_TEXT_DOMAIN), WPDMPP_VERSION);
				printf('<div id="wpdmvnotice" class="notice notice-warning  is-dismissible w3eden"><div class="media" style="padding: 20px;line-height: 22px"><div class="ml-3 pull-right"><a class="btn btn-primary btn-sm" href="%2$s">Update Database</a></div><div class="media-body">%1$s</div></div></div>', $message, admin_url('edit.php?post_type=wpdmpro&page=wpdm-settings&tab=ppsettings&ppstab=ppbasic'));
			}
		}
	}

	new RequireWPDM();
}



