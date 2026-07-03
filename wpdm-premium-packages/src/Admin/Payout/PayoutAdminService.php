<?php
/**
 * Payout Admin Service
 *
 * Handles the Payouts admin page functionality.
 *
 * @package WPDMPP\Admin\Payout
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Payout;

use WPDM\__\__;
use WPDMPP\Admin\HasViews;

defined('ABSPATH') || exit;

class PayoutAdminService
{
    use HasViews;

    /**
     * Singleton instance
     *
     * @var PayoutAdminService|null
     */
    private static ?PayoutAdminService $instance = null;

    /**
     * Whether AJAX handlers have been registered
     */
    private bool $registered = false;

    /**
     * Get singleton instance
     *
     * @return PayoutAdminService
     */
    public static function getInstance(): PayoutAdminService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
    }

    /**
     * Register AJAX handlers for payout admin operations
     */
    public function register(): void
    {
        if ($this->registered) return;
        $this->registered = true;

        add_action('wp_ajax_wpdmpp_change_payout_status', [$this, 'changePayoutStatus']);
        add_action('wp_ajax_wpdmpp_payout_settings', [$this, 'payoutSettings']);
    }

    /**
     * Toggle payout status between Completed and Pending
     */
    public function changePayoutStatus(): void
    {
        if (current_user_can(WPDMPP_ADMIN_CAP) && wp_verify_nonce(wpdm_query_var('__psnonce', 'txt'), NONCE_KEY)) {
            global $wpdb;
            $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}ahm_withdraws WHERE id = %d", wpdm_query_var('id', 'int')));
            $status = $status == 1 ? 0 : 1;
            $wpdb->update(
                "{$wpdb->prefix}ahm_withdraws",
                array('status' => $status),
                array('id' => wpdm_query_var('id', 'int')),
                array('%d'),
                array('%d')
            );
            wp_send_json(array('status' => $status ? 'Completed' : 'Pending'));
        }
    }

    /**
     * Save payout settings
     */
    public function payoutSettings(): void
    {
        __::isAuthentic('__wpdmpp_payout', WPDM_PRI_NONCE, WPDM_ADMIN_CAP);

        update_option("wpdmpp_payout_duration", absint(wpdm_query_var('payout_duration', 'int')), false);

        $payout_min_amount = wpdm_query_var('payout_min_amount');
        if (is_array($payout_min_amount)) {
            $payout_min_amount = array_map('floatval', $payout_min_amount);
        } else {
            $payout_min_amount = array();
        }
        update_option("wpdmpp_payout_min_amount", $payout_min_amount, false);

        $active_pom = wpdm_query_var('active_pom');
        if (is_array($active_pom)) {
            $active_pom = array_map('sanitize_text_field', $active_pom);
        } else {
            $active_pom = array();
        }
        update_option("wpdmpp_active_pom", $active_pom, false);

        $comission = wpdm_query_var('comission');
        if (is_array($comission)) {
            $comission = array_map('floatval', $comission);
        } else {
            $comission = array();
        }
        update_option("wpdmpp_user_comission", $comission, false);

        wp_send_json(['success' => true, 'msg' => __('Options saved successfully!', WPDMPP_TEXT_DOMAIN)]);
    }

    /**
     * Render the payouts page
     *
     * @return void
     */
    public function render(): void
    {
        $this->includeView('payouts');
    }

    /**
     * Include a view template
     *
     * @param string $view View name
     * @param array $data Variables to extract
     * @return void
     */
    private function includeView(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        // Try new path first
        $templatePath = __DIR__ . '/views/' . $view . '.php';
        if (file_exists($templatePath)) {
            include $templatePath;
            return;
        }

        // Fallback to old path
        $legacyPath = WPDMPP_BASE_DIR . 'includes/menus/templates/' . $view . '.php';
        if (file_exists($legacyPath)) {
            include $legacyPath;
        }
    }
}
