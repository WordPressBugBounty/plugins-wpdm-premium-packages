<?php
/**
 * Coupon Admin Service
 *
 * Handles the Coupon Codes admin page functionality including
 * form submissions and AJAX handlers for add/edit/delete operations.
 *
 * @package WPDMPP\Admin\Coupon
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Coupon;

use WPDMPP\Admin\HasViews;
use WPDMPP\Coupon\CouponService;

defined('ABSPATH') || exit;

class CouponAdminService
{
    use HasViews;

    /**
     * Singleton instance
     *
     * @var CouponAdminService|null
     */
    private static ?CouponAdminService $instance = null;

    /**
     * Whether AJAX handlers have been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Get singleton instance
     *
     * @return CouponAdminService
     */
    public static function getInstance(): CouponAdminService
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
     * Register AJAX handlers for coupon admin operations
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        add_action('wp_ajax_wpdmpp_delete_coupon', [$this, 'deleteCoupon']);
        add_action('wp_ajax_wpdmpp_get_couponed_orders', [$this, 'getCouponedOrders']);

        // Handle form submissions early (before headers are sent)
        add_action('admin_init', [$this, 'handleFormSubmission']);
    }

    /**
     * Render the coupon codes page
     *
     * @return void
     */
    public function render(): void
    {
        $task = wpdm_query_var('task', 'txt');

        switch ($task) {
            case 'new_coupon':
                $coupon = null;
                $this->includeView('new-coupon', compact('coupon'));
                break;

            case 'edit_coupon':
                $couponEntity = CouponService::getInstance()->findById(wpdm_query_var('ID', 'int'));
                $coupon = $couponEntity ? (object) $couponEntity->toDatabase() : null;
                $this->includeView('new-coupon', compact('coupon'));
                break;

            default:
                $this->includeView('coupon-codes');
                break;
        }
    }

    /**
     * Handle coupon form submissions (add/update)
     *
     * Hooked to admin_init so redirects happen before headers are sent.
     *
     * @return void
     */
    public function handleFormSubmission(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['do'])) {
            return;
        }

        // Only process on the coupon codes admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'pp-coupon-codes') {
            return;
        }

        $action = sanitize_text_field($_POST['do']);

        if ($action === 'addcoupon') {
            $this->handleAddCoupon();
        } elseif ($action === 'updatecoupon') {
            $this->handleUpdateCoupon();
        }
    }

    /**
     * Handle add coupon form submission
     *
     * @return void
     */
    private function handleAddCoupon(): void
    {
        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_die(__('You do not have permission to perform this action.', 'wpdm-premium-packages'));
        }

        if (!isset($_POST['__anc']) || !wp_verify_nonce($_POST['__anc'], NONCE_KEY)) {
            wp_die(__('Security check failed.', 'wpdm-premium-packages'));
        }

        $data = $this->sanitizeCouponData($_POST['coupon'] ?? []);
        $result = CouponService::getInstance()->createCoupon($data);

        if ($result['success']) {
            wp_safe_redirect(admin_url('edit.php?post_type=wpdmpro&page=pp-coupon-codes&msg=added'));
            exit;
        }

        // On failure, show the form again with error
        // The form will re-render via normal render() flow
    }

    /**
     * Handle update coupon form submission
     *
     * @return void
     */
    private function handleUpdateCoupon(): void
    {
        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_die(__('You do not have permission to perform this action.', 'wpdm-premium-packages'));
        }

        if (!isset($_POST['__ucc']) || !wp_verify_nonce($_POST['__ucc'], NONCE_KEY)) {
            wp_die(__('Security check failed.', 'wpdm-premium-packages'));
        }

        $couponId = wpdm_query_var('ID', 'int');
        if ($couponId <= 0) {
            return;
        }

        $data = $this->sanitizeCouponData($_POST['coupon'] ?? []);
        $result = CouponService::getInstance()->updateCoupon($couponId, $data);

        if ($result['success']) {
            wp_safe_redirect(admin_url('edit.php?post_type=wpdmpro&page=pp-coupon-codes&msg=updated'));
            exit;
        }
    }

    /**
     * Sanitize coupon form data
     *
     * @param array $raw Raw POST data
     * @return array Sanitized data
     */
    private function sanitizeCouponData(array $raw): array
    {
        return [
            'code'             => sanitize_text_field($raw['code'] ?? ''),
            'type'             => sanitize_text_field($raw['type'] ?? 'percent'),
            'discount'         => (float) ($raw['discount'] ?? 0),
            'product'          => (int) ($raw['product'] ?? 0),
            'description'      => sanitize_textarea_field($raw['description'] ?? ''),
            'expire_date'      => sanitize_text_field($raw['expire_date'] ?? ''),
            'min_order_amount' => (float) ($raw['min_order_amount'] ?? 0),
            'max_order_amount' => (float) ($raw['max_order_amount'] ?? 0),
            'usage_limit'      => (int) ($raw['usage_limit'] ?? 0),
            'allowed_emails'   => sanitize_text_field($raw['allowed_emails'] ?? ''),
            'auto_apply'       => (int) ($raw['auto_apply'] ?? 0),
        ];
    }

    /**
     * AJAX handler: Delete a coupon
     *
     * @return void
     */
    public function deleteCoupon(): void
    {
        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_send_json_error(__('Permission denied.', 'wpdm-premium-packages'));
        }

        if (!isset($_REQUEST['dcpnonce']) || !wp_verify_nonce($_REQUEST['dcpnonce'], WPDM_PRI_NONCE)) {
            wp_send_json_error(__('Security check failed.', 'wpdm-premium-packages'));
        }

        $id = (int) ($_REQUEST['ID'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(__('Invalid coupon ID.', 'wpdm-premium-packages'));
        }

        $result = CouponService::getInstance()->deleteCoupon($id);
        wp_send_json($result);
    }

    /**
     * AJAX handler: Get orders that used a specific coupon code
     *
     * @return void
     */
    public function getCouponedOrders(): void
    {
        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_die(__('Permission denied.', 'wpdm-premium-packages'));
        }

        if (!isset($_REQUEST['cononce']) || !wp_verify_nonce($_REQUEST['cononce'], WPDM_PRI_NONCE)) {
            wp_die(__('Security check failed.', 'wpdm-premium-packages'));
        }

        global $wpdb;
        $coupon_code = sanitize_text_field(wp_unslash($_REQUEST['coupon_code'] ?? ''));

        if (empty($coupon_code)) {
            wp_die(__('No coupon code specified.', 'wpdm-premium-packages'));
        }

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_orders WHERE coupon_code = %s ORDER BY date DESC",
            $coupon_code
        ));

        if (empty($orders)) {
            echo '<p>' . esc_html__('No orders found with this coupon code.', 'wpdm-premium-packages') . '</p>';
            wp_die();
        }

        echo '<table class="table table-striped" style="margin: 0">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Order ID', 'wpdm-premium-packages') . '</th>';
        echo '<th>' . esc_html__('Date', 'wpdm-premium-packages') . '</th>';
        echo '<th>' . esc_html__('Total', 'wpdm-premium-packages') . '</th>';
        echo '<th>' . esc_html__('Status', 'wpdm-premium-packages') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($orders as $order) {
            $order_url = admin_url('edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . esc_attr($order->order_id));
            echo '<tr>';
            echo '<td><a href="' . esc_url($order_url) . '" target="_blank">' . esc_html($order->order_id) . '</a></td>';
            echo '<td>' . esc_html(wp_date(get_option('date_format'), $order->date)) . '</td>';
            echo '<td>' . esc_html(wpdmpp_price_format($order->total)) . '</td>';
            echo '<td>' . esc_html($order->payment_status) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        wp_die();
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
