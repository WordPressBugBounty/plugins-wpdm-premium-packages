<?php
/**
 * Log Admin Service
 *
 * Handles log management AJAX operations in the admin panel.
 *
 * @package WPDMPP\Admin\Log
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Log;

use WPDMPP\Libs\Logger;

defined('ABSPATH') || exit;

class LogAdminService
{
    /**
     * Singleton instance
     *
     * @var LogAdminService|null
     */
    private static ?LogAdminService $instance = null;

    /**
     * Whether AJAX handlers have been registered
     */
    private bool $registered = false;

    /**
     * Get singleton instance
     *
     * @return LogAdminService
     */
    public static function getInstance(): LogAdminService
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
     * Register AJAX handlers for log admin operations
     */
    public function register(): void
    {
        if ($this->registered) return;
        $this->registered = true;

        add_action('wp_ajax_wpdmpp_clear_logs', [$this, 'clearLogs']);
        add_action('wp_ajax_wpdmpp_view_log', [$this, 'viewLog']);
        add_action('wp_ajax_wpdmpp_download_log', [$this, 'downloadLog']);
    }

    /**
     * Clear all log files
     */
    public function clearLogs(): void
    {
        check_ajax_referer('wpdmpp_clear_logs', '_wpnonce');

        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_send_json_error(__('Permission denied', 'wpdm-premium-packages'), 403);
        }

        $deleted = Logger::clear_all_logs();

        Logger::info('Cleared all log files', ['files_deleted' => $deleted]);

        wp_send_json_success(['deleted' => $deleted]);
    }

    /**
     * View log file contents
     */
    public function viewLog(): void
    {
        check_ajax_referer('wpdmpp_view_log', '_wpnonce');

        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_send_json_error(__('Permission denied', 'wpdm-premium-packages'), 403);
        }

        $filename = wpdm_query_var('file', 'txt');
        if (empty($filename)) {
            wp_send_json_error(__('No file specified', 'wpdm-premium-packages'));
        }

        $contents = Logger::get_log_contents($filename, 500);

        if ($contents === false) {
            wp_send_json_error(__('Log file not found', 'wpdm-premium-packages'));
        }

        wp_send_json_success(['contents' => $contents]);
    }

    /**
     * Download log file
     */
    public function downloadLog(): void
    {
        if (!wp_verify_nonce(wpdm_query_var('_wpnonce'), 'wpdmpp_download_log')) {
            wp_die(__('Security check failed', 'wpdm-premium-packages'), 403);
        }

        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_die(__('Permission denied', 'wpdm-premium-packages'), 403);
        }

        $filename = wpdm_query_var('file', 'txt');
        if (empty($filename)) {
            wp_die(__('No file specified', 'wpdm-premium-packages'));
        }

        $contents = Logger::get_log_contents($filename, 0);

        if ($contents === false) {
            wp_die(__('Log file not found', 'wpdm-premium-packages'));
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . strlen($contents));
        echo $contents;
        exit;
    }
}
