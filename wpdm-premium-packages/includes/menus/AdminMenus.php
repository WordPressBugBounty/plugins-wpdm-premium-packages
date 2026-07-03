<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPDMPPAdminMenus' ) ):

	class WPDMPPAdminMenus {

		function __construct() {
			if ( is_admin() ) {
				add_action( 'admin_menu', array( $this, 'wpdmpp_menu' ) );
			}

		}

		/**
		 * Menu for the Premium Package
		 */
		function wpdmpp_menu() {
			add_submenu_page( 'edit.php?post_type=wpdmpro', __( 'Orders', "wpdm-premium-packages" ), __( 'Orders', "wpdm-premium-packages" ), WPDM_MENU_ACCESS_CAP, 'orders', array(
				$this,
				'wpdmpp_orders'
			) );
			add_submenu_page( 'edit.php?post_type=wpdmpro', __( 'License Manager', "wpdm-premium-packages" ), __( 'License Manager', "wpdm-premium-packages" ), WPDM_MENU_ACCESS_CAP, 'pp-license', array(
				$this,
				'wpdmpp_license'
			) );
			add_submenu_page( 'edit.php?post_type=wpdmpro', __( 'Coupon Codes', "wpdm-premium-packages" ), __( 'Coupon Codes', "wpdm-premium-packages" ), WPDM_MENU_ACCESS_CAP, 'pp-coupon-codes', array(
				$this,
				'wpdmpp_all_coupons'
			) );
			add_submenu_page( 'edit.php?post_type=wpdmpro', __( 'Customers', "wpdm-premium-packages" ), __( 'Customers', "wpdm-premium-packages" ), WPDM_MENU_ACCESS_CAP, 'customers', array(
				$this,
				'wpdmpp_customers'
			) );
			add_submenu_page( 'edit.php?post_type=wpdmpro', __( 'Payouts', "wpdm-premium-packages" ), __( 'Payouts', "wpdm-premium-packages" ), WPDM_MENU_ACCESS_CAP, 'payouts', array(
				$this,
				'wpdmpp_all_payouts'
			) );
		}

		/**
		 * All Orders list
		 *
		 * Delegates to OrderAdminService for rendering.
		 */
		function wpdmpp_orders() {
			\WPDMPP\Admin\Order\OrderAdminService::getInstance()->render();
		}

		/**
		 * License Manager
		 *
		 * Delegates to LicenseAdminService for rendering.
		 */
		function wpdmpp_license() {
			\WPDMPP\Admin\License\LicenseAdminService::getInstance()->render();
		}

		/**
		 * Coupon Codes
		 *
		 * Delegates to CouponAdminService for rendering.
		 */
		function wpdmpp_all_coupons() {
			\WPDMPP\Admin\Coupon\CouponAdminService::getInstance()->render();
		}

		/**
		 * Payouts section
		 *
		 * Delegates to PayoutAdminService for rendering.
		 */
		function wpdmpp_all_payouts() {
			\WPDMPP\Admin\Payout\PayoutAdminService::getInstance()->render();
		}

		/**
		 * Customers
		 *
		 * Delegates to CustomerAdminService for rendering.
		 */
		function wpdmpp_customers() {
			\WPDMPP\Admin\Customer\CustomerAdminService::getInstance()->render();
		}

		/**
		 * Customer profile tab content
		 *
		 * @deprecated 7.0.0 Use CustomerAdminService instead
		 */
		function customer_profile() {
			\WPDMPP\Admin\Customer\CustomerAdminService::getInstance()->renderCustomerPurchases();
		}

	}

endif;

new WPDMPPAdminMenus();
