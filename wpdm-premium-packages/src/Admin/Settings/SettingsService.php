<?php
/**
 * Settings Service
 *
 * Manages Premium Package settings tabs and pages in WPDM settings.
 *
 * @package WPDMPP\Admin\Settings
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Settings;

defined('ABSPATH') || exit;

class SettingsService
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
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
     * Register all settings hooks
     *
     * @return void
     */
    public function register(): void
    {
        // Add Premium Package settings tab
        add_filter('add_wpdm_settings_tab', [$this, 'addSettingsTab']);

        // Add privacy settings panel
        add_filter('wpdm_privacy_settings_panel', [$this, 'renderPrivacySettings']);

        // AJAX handler for saving settings
        add_action('wp_ajax_wpdmpp_save_settings', [$this, 'ajaxSaveSettings']);
    }

    /**
     * Add Premium Package settings tab to WPDM settings
     *
     * @param array $tabs Existing tabs
     * @return array
     */
    public function addSettingsTab(array $tabs): array
    {
        $tabs['ppsettings'] = wpdm_create_settings_tab(
            'ppsettings',
            __('Premium Package', 'wpdm-premium-packages'),
            [$this, 'renderSettingsPage'],
            'fa-solid fa-basket-shopping'
        );

        return $tabs;
    }

    /**
     * Render the main settings page
     *
     * @return void
     */
    public function renderSettingsPage(): void
    {
        $show_db_update_notice = 0;

        if (\WPDMPP_INSTALLER::dbUpdateRequired()) {
            $show_db_update_notice = 1;
            \WPDMPP_INSTALLER::updateDB();
        }

        // New path in src/Admin/Settings/views/
        $settingsFile = __DIR__ . '/views/settings.php';
        if (file_exists($settingsFile)) {
            include $settingsFile;
        } else {
            // Fallback to old path for backward compatibility
            $settingsFile = WPDMPP_BASE_DIR . 'includes/settings/settings.php';
            if (file_exists($settingsFile)) {
                include $settingsFile;
            }
        }
    }

    /**
     * Render privacy settings panel
     *
     * @return void
     */
    public function renderPrivacySettings(): void
    {
        $checkoutPrivacy = get_option('__wpdm_checkout_privacy');
        $checkoutPrivacyLabel = get_option('__wpdm_checkout_privacy_label');
        ?>
        <div class="panel panel-default">
            <div class="panel-heading"><?php esc_html_e('Checkout Settings', 'wpdm-premium-packages'); ?></div>
            <div class="panel-body">

                <div class="form-group">
                    <input type="hidden" value="0" name="__wpdm_checkout_privacy"/>
                    <label>
                        <input style="margin: 0 10px 0 0"
                               type="checkbox" <?php checked($checkoutPrivacy, 1); ?>
                               value="1"
                               name="__wpdm_checkout_privacy">
                        <?php esc_html_e('Must agree with privacy policy before checkout', 'wpdm-premium-packages'); ?>
                    </label><br/>
                    <em><?php esc_html_e('User must agree with privacy policy before they purchase any item', 'wpdm-premium-packages'); ?></em>
                </div>
                <div class="form-group">
                    <label><?php esc_html_e('Privacy policy label:', 'wpdm-premium-packages'); ?></label>
                    <input type="text" class="form-control"
                           value="<?php echo esc_attr($checkoutPrivacyLabel); ?>"
                           name="__wpdm_checkout_privacy_label">
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for saving settings
     *
     * @return void
     */
    public function ajaxSaveSettings(): void
    {
        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_send_json_error(['message' => __('Permission denied', 'wpdm-premium-packages')], 403);
        }

        // Use WPDM core's nonce for compatibility with the settings page form
        if (!wp_verify_nonce(wpdm_query_var('__wpdms_nonce', 'txt'), WPDMSET_NONCE_KEY)) {
            wp_send_json_error(['message' => __('Security check failed', 'wpdm-premium-packages')], 403);
        }

        // Get settings array from request (WPDM core uses _wpdmpp_settings)
        $settings = isset($_POST['_wpdmpp_settings']) ? $_POST['_wpdmpp_settings'] : [];

        if (!empty($settings) && is_array($settings)) {
            // Sanitize settings
            $settings = wpdm_sanitize_array($settings);

            // Allow plugins to modify settings before saving
            $settings = apply_filters('wpdmpp_before_save_settings', $settings);

            // Save settings
            update_option('_wpdmpp_settings', $settings);

            // Fire action after saving
            do_action('wpdmpp_after_save_settings');
        }

        wp_send_json(['success' => true, 'msg' => __('Settings Saved Successfully', 'wpdm-premium-packages'), 'settings' => $settings]);
    }

    /**
     * Sanitize settings array
     *
     * @param array $settings Raw settings
     * @return array Sanitized settings
     */
    private function sanitizeSettings(array $settings): array
    {
        $sanitized = [];

        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeSettings($value);
            } else {
                // Apply appropriate sanitization based on key pattern
                if (strpos($key, 'email') !== false) {
                    $sanitized[$key] = sanitize_email($value);
                } elseif (strpos($key, 'url') !== false) {
                    $sanitized[$key] = esc_url_raw($value);
                } elseif (strpos($key, 'html') !== false || strpos($key, 'content') !== false) {
                    $sanitized[$key] = wp_kses_post($value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Get a specific setting value
     *
     * @param string $key Setting key (supports dot notation: 'Paypal/client_id')
     * @param mixed $default Default value
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        return get_wpdmpp_option($key, $default);
    }

    /**
     * Update a specific setting value
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool
     */
    public function updateSetting(string $key, $value): bool
    {
        $settings = get_option('_wpdmpp_settings', []);

        if (!is_array($settings)) {
            $settings = [];
        }

        // Handle nested keys (e.g., 'Paypal/client_id')
        $keys = explode('/', $key);
        $current = &$settings;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }

        return update_option('_wpdmpp_settings', $settings);
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public function getAllSettings(): array
    {
        $settings = get_option('_wpdmpp_settings', []);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Reset settings to defaults
     *
     * @return bool
     */
    public function resetSettings(): bool
    {
        return delete_option('_wpdmpp_settings');
    }
}
