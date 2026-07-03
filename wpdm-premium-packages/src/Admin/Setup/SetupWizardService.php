<?php
/**
 * Setup Wizard Service
 *
 * Guides new users through the basic steps to configure the Premium Packages
 * add-on (store basics, pages, and payment gateways).
 *
 * Migrated from includes/settings/wizard/class.SetupWizard.php
 * (WPDMPP\Libs\Premium_Packages_Setup_Wizard) to the PSR-4 architecture.
 *
 * @package WPDMPP\Admin\Setup
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Setup;

use WPDMPP\Admin\HasViews;

defined('ABSPATH') || exit;

class SetupWizardService
{
    use HasViews;

    /**
     * Singleton instance
     *
     * @var SetupWizardService|null
     */
    private static ?SetupWizardService $instance = null;

    /**
     * Whether hooks have been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Current step slug
     *
     * @var string
     */
    private string $step = '';

    /**
     * Steps definition (slug => ['name', 'view' callable, 'handler' callable])
     *
     * @var array
     */
    private array $steps = [];

    /**
     * Get singleton instance
     *
     * @return SetupWizardService
     */
    public static function getInstance(): SetupWizardService
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
     * Register admin hooks.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        if (!current_user_can('manage_options')) {
            return;
        }

        add_action('admin_menu', [$this, 'registerPage']);
        add_action('admin_init', [$this, 'maybeRedirectToWizard'], 5);
        add_action('admin_init', [$this, 'maybeRenderWizard']);
    }

    /**
     * One-time redirect to the wizard right after a fresh-install activation.
     *
     * The activation hook in the main plugin file sets the transient for
     * fresh installs only; consume it here and send the admin to the wizard.
     */
    public function maybeRedirectToWizard(): void
    {
        if (!get_transient('_wpdmpp_setup_wizard_redirect')) {
            return;
        }
        delete_transient('_wpdmpp_setup_wizard_redirect');

        // Never hijack bulk activations, AJAX requests, or the wizard itself.
        if (wp_doing_ajax() || is_network_admin() || isset($_GET['activate-multi'])) {
            return;
        }
        if (!empty($_GET['page']) && 'wpdmpp-setup' === $_GET['page']) {
            return;
        }

        wp_safe_redirect(admin_url('index.php?page=wpdmpp-setup'));
        exit;
    }

    /**
     * Register the hidden dashboard page that hosts the wizard.
     */
    public function registerPage(): void
    {
        add_dashboard_page('', '', 'manage_options', 'wpdmpp-setup', '');
    }

    /**
     * Render the wizard when on its page, then exit.
     */
    public function maybeRenderWizard(): void
    {
        if (empty($_GET['page']) || 'wpdmpp-setup' !== $_GET['page']) {
            return;
        }

        $default_steps = [
            'basics' => [
                'name'    => __('Basics', 'wpdm-premium-packages'),
                'view'    => [$this, 'viewBasics'],
                'handler' => [$this, 'saveBasics'],
            ],
            'pages' => [
                'name'    => __('Pages', 'wpdm-premium-packages'),
                'view'    => [$this, 'viewPages'],
                'handler' => [$this, 'savePages'],
            ],
            'payment' => [
                'name'    => __('Payment', 'wpdm-premium-packages'),
                'view'    => [$this, 'viewPayment'],
                'handler' => [$this, 'savePayment'],
            ],
            'ready' => [
                'name'    => __('Ready!', 'wpdm-premium-packages'),
                'view'    => [$this, 'viewReady'],
                'handler' => '',
            ],
        ];

        $this->steps = apply_filters('wpdmpp_setup_wizard_steps', $default_steps);
        $this->step  = isset($_GET['step']) ? sanitize_key($_GET['step']) : current(array_keys($this->steps));

        wp_enqueue_style('wpdmpp-wizard', WPDMPP_BASE_URL . 'assets/wizard/wizard.css', [], WPDMPP_VERSION);
        wp_register_script('wpdmpp-wizard', WPDMPP_BASE_URL . 'assets/wizard/wizard.js', [], WPDMPP_VERSION, false);

        if (!empty($_POST['save_step']) && !empty($this->steps[$this->step]['handler'])) {
            call_user_func($this->steps[$this->step]['handler'], $this);
        }

        $keys    = array_keys($this->steps);
        $idx     = (int) array_search($this->step, $keys, true);
        $total   = count($keys);
        $prevUrl = $idx > 0 ? esc_url(add_query_arg('step', $keys[$idx - 1], remove_query_arg('activate_error'))) : '';
        $isLast  = ($idx === $total - 1);

        ob_start();
        $this->renderView('header', ['steps' => $this->steps, 'current' => $this->step]);
        $this->renderContent();
        $this->renderView('footer', [
            'step'    => $this->step,
            'prevUrl' => $prevUrl,
            'isLast'  => $isLast,
            'current' => $idx,
            'total'   => $total,
        ]);
        exit;
    }

    /**
     * Render the current step's view.
     */
    private function renderContent(): void
    {
        if (isset($this->steps[$this->step]['view'])) {
            call_user_func($this->steps[$this->step]['view'], $this);
        }
    }

    /**
     * Get the URL for the next step's screen.
     *
     * @param string $step Slug (default: current step)
     * @return string URL for next step, admin URL if last step, empty on failure.
     */
    public function getNextStepLink(string $step = ''): string
    {
        if (!$step) {
            $step = $this->step;
        }

        $keys = array_keys($this->steps);
        if (end($keys) === $step) {
            return admin_url();
        }

        $step_index = array_search($step, $keys);
        if (false === $step_index) {
            return '';
        }

        return add_query_arg('step', $keys[$step_index + 1], remove_query_arg('activate_error'));
    }

    /* -------------------------------------------------------------------------
     * Step views
     * ---------------------------------------------------------------------- */

    public function viewBasics(): void
    {
        $this->renderView('step-basics', ['settings' => $this->getSettings()]);
    }

    public function viewPages(): void
    {
        $this->renderView('step-pages', ['settings' => $this->getSettings()]);
    }

    public function viewPayment(): void
    {
        $this->renderView('step-payment', ['settings' => $this->getSettings()]);
    }

    public function viewReady(): void
    {
        // We've made it! Don't prompt the user to run the wizard again.
        update_option('wpdmpp_setp_wizard_notice', 'hide');
        $this->renderView('step-ready');
    }

    /* -------------------------------------------------------------------------
     * Step save handlers
     * ---------------------------------------------------------------------- */

    /**
     * Save Basics settings.
     */
    public function saveBasics(): void
    {
        check_admin_referer('wpdmpp-setup');

        $settings = $this->getSettings();

        if (isset($_POST['_wpdmpp_settings']['billing_address'])) {
            $settings['billing_address'] = sanitize_text_field($_POST['_wpdmpp_settings']['billing_address']);
        } else {
            unset($settings['billing_address']);
        }

        if (isset($_POST['_wpdmpp_settings']['guest_checkout'])) {
            $settings['guest_checkout'] = (int) $_POST['_wpdmpp_settings']['guest_checkout'];
        } else {
            unset($settings['guest_checkout']);
        }

        if (isset($_POST['_wpdmpp_settings']['guest_download'])) {
            $settings['guest_download'] = (int) $_POST['_wpdmpp_settings']['guest_download'];
        } else {
            unset($settings['guest_download']);
        }

        if (isset($_POST['_wpdmpp_settings']['wpdmpp_after_addtocart_redirect'])) {
            $settings['wpdmpp_after_addtocart_redirect'] = sanitize_text_field($_POST['_wpdmpp_settings']['wpdmpp_after_addtocart_redirect']);
        } else {
            unset($settings['wpdmpp_after_addtocart_redirect']);
        }

        if (isset($_POST['_wpdmpp_settings']['tax']['enable'])) {
            $settings['tax']['enable'] = (int) $_POST['_wpdmpp_settings']['tax']['enable'];
        } else {
            unset($settings['tax']['enable']);
        }

        update_option('_wpdmpp_settings', $settings);

        wp_redirect(esc_url_raw($this->getNextStepLink()));
        exit;
    }

    /**
     * Save Pages settings.
     */
    public function savePages(): void
    {
        check_admin_referer('wpdmpp-setup');

        $settings = $this->getSettings();

        $settings['page_id']               = isset($_POST['_wpdmpp_settings']['page_id']) ? intval($_POST['_wpdmpp_settings']['page_id']) : '';
        $settings['orders_page_id']        = isset($_POST['_wpdmpp_settings']['orders_page_id']) ? intval($_POST['_wpdmpp_settings']['orders_page_id']) : '';
        $settings['guest_order_page_id']   = isset($_POST['_wpdmpp_settings']['guest_order_page_id']) ? intval($_POST['_wpdmpp_settings']['guest_order_page_id']) : '';
        $settings['continue_shopping_url'] = isset($_POST['_wpdmpp_settings']['continue_shopping_url']) ? esc_url($_POST['_wpdmpp_settings']['continue_shopping_url']) : '';

        update_option('_wpdmpp_settings', $settings);

        wp_redirect(esc_url_raw($this->getNextStepLink()));
        exit;
    }

    /**
     * Save Payments settings.
     */
    public function savePayment(): void
    {
        check_admin_referer('wpdmpp-setup');

        $settings = $this->getSettings();

        // PayPal (Smart Buttons — REST API credentials)
        $settings['PayPal']['enabled'] = isset($_POST['_wpdmpp_settings']['PayPal']['enabled']) ? 1 : 0;
        $settings['PayPal']['title']   = isset($_POST['_wpdmpp_settings']['PayPal']['title']) && $_POST['_wpdmpp_settings']['PayPal']['title'] != '' ? sanitize_text_field($_POST['_wpdmpp_settings']['PayPal']['title']) : 'PayPal';

        $mode = isset($_POST['_wpdmpp_settings']['PayPal']['Paypal_mode']) && $_POST['_wpdmpp_settings']['PayPal']['Paypal_mode'] === 'sandbox' ? 'sandbox' : 'production';
        $settings['PayPal']['Paypal_mode'] = $mode;

        // Store the Client ID / Secret in the keys the gateway reads for the chosen environment.
        $clientId     = isset($_POST['_wpdmpp_settings']['PayPal']['client_id']) ? sanitize_text_field($_POST['_wpdmpp_settings']['PayPal']['client_id']) : '';
        $clientSecret = isset($_POST['_wpdmpp_settings']['PayPal']['client_secret']) ? sanitize_text_field($_POST['_wpdmpp_settings']['PayPal']['client_secret']) : '';

        if ($mode === 'sandbox') {
            $settings['PayPal']['client_id_sandbox']     = $clientId;
            $settings['PayPal']['client_secret_sandbox'] = $clientSecret;
        } else {
            $settings['PayPal']['client_id']     = $clientId;
            $settings['PayPal']['client_secret'] = $clientSecret;
        }

        // TestPay
        $settings['TestPay']['enabled']    = isset($_POST['_wpdmpp_settings']['TestPay']['enabled']) ? 1 : 0;
        $settings['TestPay']['title']      = isset($_POST['_wpdmpp_settings']['TestPay']['title']) && $_POST['_wpdmpp_settings']['TestPay']['title'] != '' ? sanitize_text_field($_POST['_wpdmpp_settings']['TestPay']['title']) : 'Test Pay';
        $settings['TestPay']['cancel_url'] = isset($_POST['_wpdmpp_settings']['TestPay']['cancel_url']) ? esc_url($_POST['_wpdmpp_settings']['TestPay']['cancel_url']) : '';
        $settings['TestPay']['return_url'] = isset($_POST['_wpdmpp_settings']['TestPay']['return_url']) ? esc_url($_POST['_wpdmpp_settings']['TestPay']['return_url']) : '';

        // Cheque
        $settings['Cheque']['enabled'] = isset($_POST['_wpdmpp_settings']['Cheque']['enabled']) ? 1 : 0;
        $settings['Cheque']['title']   = isset($_POST['_wpdmpp_settings']['Cheque']['title']) && $_POST['_wpdmpp_settings']['Cheque']['title'] != '' ? sanitize_text_field($_POST['_wpdmpp_settings']['Cheque']['title']) : 'Pay with Cheque';

        // Cash
        $settings['Cash']['enabled'] = isset($_POST['_wpdmpp_settings']['Cash']['enabled']) ? 1 : 0;
        $settings['Cash']['title']   = isset($_POST['_wpdmpp_settings']['Cash']['title']) && $_POST['_wpdmpp_settings']['Cash']['title'] != '' ? sanitize_text_field($_POST['_wpdmpp_settings']['Cash']['title']) : 'Pay with Cash';

        update_option('_wpdmpp_settings', $settings);

        wp_redirect(esc_url_raw($this->getNextStepLink()));
        exit;
    }

    /* -------------------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------------- */

    /**
     * Get the stored plugin settings as an array.
     *
     * @return array
     */
    private function getSettings(): array
    {
        $settings = maybe_unserialize(get_option('_wpdmpp_settings'));
        return is_array($settings) ? $settings : [];
    }
}
