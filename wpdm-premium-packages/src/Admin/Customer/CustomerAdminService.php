<?php
/**
 * Customer Admin Service
 *
 * Handles the Customers admin page functionality.
 *
 * @package WPDMPP\Admin\Customer
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Customer;

use WPDM\__\__;
use WPDMPP\Admin\HasViews;
use WPDMPP\Customer\CustomerService;

defined('ABSPATH') || exit;

class CustomerAdminService
{
    use HasViews;

    /**
     * Singleton instance
     *
     * @var CustomerAdminService|null
     */
    private static ?CustomerAdminService $instance = null;

    /**
     * Whether AJAX handlers have been registered
     */
    private bool $registered = false;

    /**
     * Get singleton instance
     *
     * @return CustomerAdminService
     */
    public static function getInstance(): CustomerAdminService
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
     * Register AJAX handlers for customer admin operations
     */
    public function register(): void
    {
        if ($this->registered) return;
        $this->registered = true;

        add_action('wp_ajax_wpdmpp_recalculateCustomerValue', [$this, 'recalculateCustomerValue']);
    }

    /**
     * Recalculate customer spending totals in batches
     */
    public function recalculateCustomerValue(): void
    {
        __::isAuthentic('__rcvnonce', WPDM_PRI_NONCE, 'manage_options');

        global $wpdb;
        $total = (int) $wpdb->get_var("select count(DISTINCT uid) from {$wpdb->prefix}ahm_orders where (order_status='Completed' or order_status='Expired') and uid > 0");

        // Nothing to do (and avoids a division-by-zero below on an empty store).
        if ($total < 1) {
            wp_send_json(['continue' => false, 'total' => 0, 'nextpage' => 1, 'progress' => 100]);
        }

        $items_per_page = 20;
        $total_pages = $total / $items_per_page;
        $current_page = wpdm_query_var('cp', 'int');
        $current_page = $current_page > 0 ? $current_page : 1;
        $start = ($current_page - 1) * $items_per_page;

        // Use the same predicate as $total (Completed OR Expired) so every counted
        // customer is actually recalculated and the progress denominator is accurate.
        $customers = $wpdb->get_results($wpdb->prepare("SELECT uid, COUNT(order_id) AS total_orders, SUM(total) AS total_purchases FROM {$wpdb->prefix}ahm_orders WHERE (order_status = 'Completed' OR order_status = 'Expired') AND uid > 0 GROUP BY uid ORDER BY total_purchases DESC LIMIT %d, %d", $start, $items_per_page));
        foreach ($customers as $customer) {
            CustomerService::getInstance()->calculateTotalSpent($customer->uid);
            $user = get_user_by('id', $customer->uid);
            // The query reads uids straight from the orders table, so a uid whose
            // WP user was deleted returns false here — guard before add_role().
            if ($user instanceof \WP_User) {
                $user->add_role('wpdmpp_customer');
            }
        }

        $response['continue'] = $current_page < $total_pages;
        $response['total'] = $total;
        $response['nextpage'] = $current_page + 1;
        $response['progress'] = min((($start + $items_per_page) / $total) * 100, 100);

        wp_send_json($response);
    }

    /**
     * Render the customers page
     *
     * @return void
     */
    public function render(): void
    {
        $tabs = $this->getProfileTabs();
        $tab = wpdm_query_var('view', 'txt');
        $tab = sanitize_key($tab);

        if (isset($tabs[$tab])) {
            $this->includeView('customer-profile', compact('tabs', 'tab'));
        } else {
            $this->includeView('customers');
        }
    }

    /**
     * Get customer profile tabs
     *
     * @return array
     */
    private function getProfileTabs(): array
    {
        $tabs = [
            'profile' => [
                'name' => esc_attr__('Profile', 'wpdm-premium-packages'),
                'callback' => [$this, 'renderCustomerPurchases']
            ]
        ];

        return apply_filters('wpdmpp_customer_profile_admin_tab_content', $tabs);
    }

    /**
     * Render customer purchases tab content
     *
     * @return void
     */
    public function renderCustomerPurchases(): void
    {
        $this->includeView('customer-purchases');
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
