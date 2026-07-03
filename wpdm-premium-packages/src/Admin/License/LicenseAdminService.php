<?php
/**
 * License Admin Service
 *
 * Handles the License Manager admin page functionality.
 *
 * @package WPDMPP\Admin\License
 * @since 7.0.0
 */

namespace WPDMPP\Admin\License;

use WPDMPP\Admin\HasViews;
use WPDMPP\License\LicenseService;

defined('ABSPATH') || exit;

class LicenseAdminService
{
    use HasViews;

    /**
     * Singleton instance
     *
     * @var LicenseAdminService|null
     */
    private static ?LicenseAdminService $instance = null;

    /**
     * Whether hooks have been registered.
     */
    private bool $registered = false;

    /**
     * Get singleton instance
     *
     * @return LicenseAdminService
     */
    public static function getInstance(): LicenseAdminService
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
     * Register early hooks.
     *
     * Form submission must run on admin_init so the success redirect happens
     * before WP starts emitting the admin page HTML.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        add_action('admin_init', [$this, 'handleAddLicense']);
        add_action('wp_ajax_wpdmpp_delete_licenses', [$this, 'ajaxDeleteLicenses']);
        add_action('wp_ajax_wpdm_unlock_license', [$this, 'ajaxUnlockLicense']);
    }

    /**
     * AJAX: unlock a license (clear all of its registered domains).
     *
     * The view posts this action but no handler was registered (lost in the
     * admin-service migration), so the click did nothing. Echoes 'ok' on
     * success to match the view's `res === 'ok'` check.
     *
     * @return void
     */
    public function ajaxUnlockLicense(): void
    {
        if (!current_user_can('manage_options') || !wp_verify_nonce(wpdm_query_var('__suc'), NONCE_KEY)) {
            wp_die('error');
        }

        $lid = absint(wpdm_query_var('unlock_license'));
        if (!$lid) {
            wp_die('error');
        }

        $result = LicenseService::getInstance()->clearDomains($lid);
        wp_die(!empty($result['success']) ? 'ok' : 'error');
    }

    /**
     * AJAX: delete the selected licenses.
     *
     * Replaces the old form GET that verified a nonce the form never sent
     * (the form posts __suc/NONCE_KEY, the handler expected _wpnonce/
     * wpdmpp_delete_licenses), so deletion silently did nothing.
     *
     * @return void
     */
    public function ajaxDeleteLicenses(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json(['success' => false, 'message' => __('You are not allowed to do this.', 'wpdm-premium-packages')]);
        }
        if (!wp_verify_nonce(wpdm_query_var('_wpnonce'), 'wpdmpp_delete_licenses')) {
            wp_send_json(['success' => false, 'message' => __('Security check failed. Refresh the page and try again.', 'wpdm-premium-packages')]);
        }

        $ids = isset($_REQUEST['ids']) ? (array) $_REQUEST['ids'] : [];
        $ids = array_values(array_filter(array_map('absint', $ids)));
        if (empty($ids)) {
            wp_send_json(['success' => false, 'message' => __('No licenses selected.', 'wpdm-premium-packages')]);
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ahm_licenses WHERE id IN ($placeholders)", $ids));

        wp_send_json([
            'success' => true,
            'deleted' => $ids,
            'message' => sprintf(
                _n('%d license deleted.', '%d licenses deleted.', count($ids), 'wpdm-premium-packages'),
                count($ids)
            ),
        ]);
    }

    /**
     * Render the license manager page
     *
     * @return void
     */
    public function render(): void
    {
        global $wpdb;

        $l = 30;
        $p = wpdm_query_var('paged', 'int');
        $p = $p > 0 ? $p : 1;
        $s = ($p - 1) * $l;

        $task = wpdm_query_var('task', 'txt');

        switch ($task) {
            case 'NewLicense':
                $this->includeView('new-license');
                break;

            case 'editlicense':
                $this->renderEditLicense($wpdb);
                break;

            default:
                $this->renderLicenseList($wpdb, $s, $l, $p);
                break;
        }
    }

    /**
     * Handle the "Add New License" form submission.
     *
     * Hooked to admin_init so a successful insert can redirect to the license
     * list before WP emits any HTML (avoids "headers already sent").
     *
     * @return void
     */
    public function handleAddLicense(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (!isset($_POST['do']) || $_POST['do'] !== 'addlicense') {
            return;
        }
        // Only on the license admin page.
        if (!isset($_GET['page']) || $_GET['page'] !== 'pp-license') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!isset($_POST['__suc']) || !wp_verify_nonce($_POST['__suc'], NONCE_KEY)) {
            return;
        }

        $raw = isset($_POST['license']) && is_array($_POST['license']) ? wpdm_sanitize_array($_POST['license']) : [];

        // Map form field names to LicenseService::createLicense() keys.
        $data = [
            'license_no'      => isset($raw['licenseno']) ? sanitize_text_field($raw['licenseno']) : '',
            'order_id'        => isset($raw['oid']) ? sanitize_text_field($raw['oid']) : '',
            'product_id'      => isset($raw['pid']) ? (int) $raw['pid'] : 0,
            'domain_limit'    => isset($raw['domain_limit']) ? (int) $raw['domain_limit'] : 1,
            'activation_date' => isset($raw['activation_date']) ? sanitize_text_field($raw['activation_date']) : '',
            'expire_date'     => isset($raw['expire_date']) ? sanitize_text_field($raw['expire_date']) : '',
        ];

        // Domains textarea → array of trimmed, non-empty lines.
        if (isset($raw['domain']) && trim((string) $raw['domain']) !== '') {
            $domains = preg_split('/\r\n|\r|\n/', (string) $raw['domain']);
            $domains = array_filter(array_map('trim', (array) $domains), 'strlen');
            $data['domains'] = array_map('sanitize_text_field', $domains);
        }

        $result = LicenseService::getInstance()->createLicense($data);

        if (!empty($result['success'])) {
            wp_safe_redirect(admin_url('edit.php?post_type=wpdmpro&page=pp-license&msg=added'));
            exit;
        }

        // On failure, stash the first error in a transient so the re-rendered form can surface it.
        $errors  = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [];
        $message = !empty($errors) ? (string) reset($errors) : __('Failed to create license.', 'wpdm-premium-packages');
        set_transient('wpdmpp_license_form_error_' . get_current_user_id(), $message, 30);
    }

    /**
     * Render edit license form
     *
     * @param \wpdb $wpdb
     * @return void
     */
    private function renderEditLicense(\wpdb $wpdb): void
    {
        $lid = wpdm_query_var('id', 'int');

        // Handle license update
        if ($lid && isset($_POST['do']) && $_POST['do'] === 'updatelicense'
            && current_user_can('manage_options')
            && isset($_POST['__suc']) && wp_verify_nonce($_POST['__suc'], NONCE_KEY)) {

            $raw = isset($_POST['license']) && is_array($_POST['license']) ? wpdm_sanitize_array(wp_unslash($_POST['license'])) : [];

            // Only whitelisted columns; dates go into int columns as timestamps.
            $data = [];
            $formats = [];

            if (isset($raw['domain_limit'])) {
                $data['domain_limit'] = (int) $raw['domain_limit'];
                $formats[] = '%d';
            }

            if (isset($raw['domain'])) {
                $domains = preg_split('/\r\n|\r|\n/', (string) $raw['domain']);
                $domains = array_filter(array_map('trim', (array) $domains), 'strlen');
                $data['domain'] = maybe_serialize(array_values(array_map('sanitize_text_field', $domains)));
                $formats[] = '%s';
            }

            foreach (['activation_date', 'expire_date'] as $dateField) {
                if (isset($raw[$dateField])) {
                    $timestamp = strtotime(sanitize_text_field($raw[$dateField]));
                    $data[$dateField] = $timestamp ?: 0;
                    $formats[] = '%d';
                }
            }

            if ($data) {
                $wpdb->update("{$wpdb->prefix}ahm_licenses", $data, ['id' => $lid], $formats, ['%d']);
            }
        }

        $license = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ahm_licenses WHERE id = %d", $lid));
        $this->includeView('edit-license', compact('license', 'lid'));
    }

    /**
     * Render license list
     *
     * @param \wpdb $wpdb
     * @param int $s Start offset
     * @param int $l Limit
     * @param int $p Current page
     * @return void
     */
    private function renderLicenseList(\wpdb $wpdb, int $s, int $l, int $p): void
    {
        // Bulk delete is handled asynchronously via the wpdmpp_delete_licenses
        // AJAX action (see ajaxDeleteLicenses()).

        // Build WHERE clauses with prepared statements
        $where_clauses = array('1=1');
        $where_values = array();

        if (wpdm_query_var('licenseno', 'txt') != '') {
            $where_clauses[] = "licenseno = %s";
            $where_values[] = wpdm_query_var('licenseno', 'txt');
        }
        if (wpdm_query_var('oid', 'txt') != '') {
            $where_clauses[] = "oid = %s";
            $where_values[] = wpdm_query_var('oid', 'txt');
        }
        if (wpdm_query_var('link', 'txt') != '') {
            $where_clauses[] = "domain LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like(wpdm_query_var('link', 'txt')) . '%';
        }

        $where_sql = implode(' AND ', $where_clauses);

        // COUNT must use the same INNER JOIN against posts as the listing query
        // below — otherwise a license whose product post was deleted is counted
        // but never listed, so "N license(s) found" would exceed the rows shown.
        if (!empty($where_values)) {
            $t = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ahm_licenses l, {$wpdb->prefix}posts f WHERE l.pid = f.ID AND {$where_sql}", $where_values));
            $where_values[] = $s;
            $where_values[] = $l;
            $licenses = $wpdb->get_results($wpdb->prepare(
                "SELECT l.*, f.post_title AS productname FROM {$wpdb->prefix}ahm_licenses l, {$wpdb->prefix}posts f WHERE l.pid = f.ID AND {$where_sql} ORDER BY id DESC LIMIT %d, %d",
                $where_values
            ));
        } else {
            $t = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ahm_licenses l, {$wpdb->prefix}posts f WHERE l.pid = f.ID");
            $licenses = $wpdb->get_results($wpdb->prepare(
                "SELECT l.*, f.post_title AS productname FROM {$wpdb->prefix}ahm_licenses l, {$wpdb->prefix}posts f WHERE l.pid = f.ID ORDER BY id DESC LIMIT %d, %d",
                $s,
                $l
            ));
        }

        $this->includeView('manage-license', compact('licenses', 't', 'l', 's', 'p'));
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
