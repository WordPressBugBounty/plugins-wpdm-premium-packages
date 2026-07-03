<?php
/**
 * Billing Info Service
 *
 * Manages customer billing information for checkout and user profiles.
 *
 * @package WPDMPP\Customer
 * @since 7.0.0
 */

namespace WPDMPP\Customer;

defined('ABSPATH') || exit;

class BillingInfoService {

    /**
     * Singleton instance
     *
     * @var BillingInfoService|null
     */
    private static ?BillingInfoService $instance = null;

    /**
     * User meta key for billing info
     */
    public const META_KEY = 'user_billing_shipping';

    /**
     * Whether service has been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Default billing fields
     *
     * @var array
     */
    private array $defaultFields = [
        'first_name' => '',
        'last_name' => '',
        'company' => '',
        'address_1' => '',
        'address_2' => '',
        'city' => '',
        'postcode' => '',
        'country' => '',
        'state' => '',
        'email' => '',
        'phone' => '',
        'taxid' => '',
    ];

    /**
     * Get singleton instance
     *
     * @return BillingInfoService
     */
    public static function getInstance(): BillingInfoService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {}

    /**
     * Register all hooks
     */
    public function register(): void {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        // Admin profile hooks
        add_action('show_user_profile', [$this, 'renderAdminFields']);
        add_action('edit_user_profile', [$this, 'renderAdminFields']);
        add_action('personal_options_update', [$this, 'saveAdminFields']);
        add_action('edit_user_profile_update', [$this, 'saveAdminFields']);

        // Frontend profile hooks
        add_action('wpdm_edit_profile_form', [$this, 'renderFrontendFields']);
        add_action('wpdm_update_profile', [$this, 'saveFrontendFields']);
    }

    /**
     * Get billing info for a user
     *
     * @param int $userId User ID
     * @return array Billing info array
     */
    public function getBillingInfo(int $userId): array {
        $stored = maybe_unserialize(get_user_meta($userId, self::META_KEY, true));
        $billing = $this->extractBilling($stored);

        // Populate defaults from user data
        $user = get_user_by('id', $userId);
        if (!$user) {
            return $this->defaultFields;
        }

        $defaults = $this->defaultFields;
        $defaults['first_name'] = $user->first_name;
        $defaults['last_name'] = $user->last_name;
        $defaults['email'] = $user->user_email;

        // Merge with stored values
        if (!is_array($billing)) {
            $billing = [];
        }

        foreach ($defaults as $field => $defaultValue) {
            if (empty($billing[$field])) {
                $billing[$field] = $defaultValue;
            }
        }

        return $billing;
    }

    /**
     * Get full billing/shipping data (includes shipping if stored)
     *
     * @param int $userId User ID
     * @return array Full billing/shipping array
     */
    public function getFullBillingData(int $userId): array {
        $stored = maybe_unserialize(get_user_meta($userId, self::META_KEY, true));

        if (!is_array($stored)) {
            return [
                'billing' => $this->getBillingInfo($userId),
            ];
        }

        return $stored;
    }

    /**
     * Save billing info for a user
     *
     * @param int   $userId User ID
     * @param array $data   Billing data to save
     * @return bool Success status
     */
    public function saveBillingInfo(int $userId, array $data): bool {
        if ($userId <= 0) {
            return false;
        }

        $sanitized = $this->sanitizeBillingData($data);
        return (bool) update_user_meta($userId, self::META_KEY, $sanitized);
    }

    /**
     * Update specific billing fields for a user
     *
     * @param int   $userId User ID
     * @param array $fields Fields to update
     * @return bool Success status
     */
    public function updateBillingFields(int $userId, array $fields): bool {
        $existing = $this->getFullBillingData($userId);

        // Merge with existing data
        if (!isset($existing['billing'])) {
            $existing['billing'] = [];
        }

        foreach ($fields as $key => $value) {
            $existing['billing'][$key] = $value;
        }

        return $this->saveBillingInfo($userId, $existing);
    }

    /**
     * Render billing fields on admin user profile
     *
     * @param \WP_User $user User object
     */
    public function renderAdminFields(\WP_User $user): void {
        $billing = $this->getBillingInfo($user->ID);

        ob_start();
        echo '<div class="w3eden" style="width: 800px;max-width: 100%">';

        $template = $this->locateTemplate('dashboard/billing-info.php');
        if ($template && file_exists($template)) {
            include $template;
        } else {
            $this->renderDefaultFields($billing, 'admin');
        }

        echo '</div>';
        $output = ob_get_clean();

        /**
         * Filter the billing info fields HTML for admin profile
         *
         * @param string   $output  HTML output
         * @param \WP_User $user    User object
         */
        echo apply_filters('wpdmpp_add_billing_info_fields', $output, $user);
    }

    /**
     * Render billing fields on frontend dashboard
     */
    public function renderFrontendFields(): void {
        $userId = get_current_user_id();
        if (!$userId) {
            return;
        }

        $billing = $this->getBillingInfo($userId);
        $store = maybe_unserialize(get_user_meta($userId, '__wpdm_store', true));

        $template = $this->locateTemplate('dashboard/billing-info.php');
        if ($template && file_exists($template)) {
            include $template;
        } else {
            $this->renderDefaultFields($billing, 'frontend');
        }
    }

    /**
     * Save billing fields from admin profile
     *
     * @param int $userId User ID
     */
    public function saveAdminFields(int $userId): void {
        if (!current_user_can('edit_user', $userId)) {
            return;
        }

        if (!isset($_POST['checkout'])) {
            return;
        }

        $data = wpdm_sanitize_array($_POST['checkout']);
        $this->saveBillingInfo($userId, $data);

        // Handle customer role toggle (admin only)
        if (current_user_can(WPDMPP_ADMIN_CAP)) {
            if (isset($_REQUEST['wpdmpp_customer'])) {
                $this->addCustomerRole($userId);
            } else {
                $this->removeCustomerRole($userId);
            }
        }
    }

    /**
     * Save billing fields from frontend profile
     */
    public function saveFrontendFields(): void {
        $userId = get_current_user_id();
        if (!$userId) {
            return;
        }

        if (!isset($_POST['checkout'])) {
            return;
        }

        $data = wpdm_sanitize_array($_POST['checkout']);
        $this->saveBillingInfo($userId, $data);
    }

    /**
     * Validate billing info completeness
     *
     * @param array $billing Billing data
     * @return array Validation result ['valid' => bool, 'missing' => array]
     */
    public function validateBillingInfo(array $billing): array {
        $required = $this->getRequiredFields();
        $missing = [];

        foreach ($required as $field) {
            if (empty($billing[$field])) {
                $missing[] = $field;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Get required billing fields
     *
     * @return array Required field names
     */
    public function getRequiredFields(): array {
        $required = ['first_name', 'last_name', 'email'];

        /**
         * Filter the required billing fields
         *
         * @param array $required Required field names
         */
        return apply_filters('wpdmpp_required_billing_fields', $required);
    }

    /**
     * Get billing field labels
     *
     * @return array Field labels keyed by field name
     */
    public function getFieldLabels(): array {
        return [
            'first_name' => __('First Name', 'wpdm-premium-packages'),
            'last_name' => __('Last Name', 'wpdm-premium-packages'),
            'company' => __('Company', 'wpdm-premium-packages'),
            'address_1' => __('Address Line 1', 'wpdm-premium-packages'),
            'address_2' => __('Address Line 2', 'wpdm-premium-packages'),
            'city' => __('City', 'wpdm-premium-packages'),
            'postcode' => __('Postcode / ZIP', 'wpdm-premium-packages'),
            'country' => __('Country', 'wpdm-premium-packages'),
            'state' => __('State / Province', 'wpdm-premium-packages'),
            'email' => __('Email', 'wpdm-premium-packages'),
            'phone' => __('Phone', 'wpdm-premium-packages'),
            'taxid' => __('Tax ID / VAT', 'wpdm-premium-packages'),
        ];
    }

    /**
     * Check if billing info is complete
     *
     * @param int $userId User ID
     * @return bool True if all required fields are filled
     */
    public function isComplete(int $userId): bool {
        $billing = $this->getBillingInfo($userId);
        $validation = $this->validateBillingInfo($billing);
        return $validation['valid'];
    }

    /**
     * Get formatted billing address
     *
     * @param int    $userId User ID
     * @param string $separator Line separator
     * @return string Formatted address
     */
    public function getFormattedAddress(int $userId, string $separator = "\n"): string {
        $billing = $this->getBillingInfo($userId);
        $parts = [];

        // Name
        $name = trim($billing['first_name'] . ' ' . $billing['last_name']);
        if ($name) {
            $parts[] = $name;
        }

        // Company
        if (!empty($billing['company'])) {
            $parts[] = $billing['company'];
        }

        // Address lines
        if (!empty($billing['address_1'])) {
            $parts[] = $billing['address_1'];
        }
        if (!empty($billing['address_2'])) {
            $parts[] = $billing['address_2'];
        }

        // City, State, Postcode
        $cityLine = array_filter([
            $billing['city'] ?? '',
            $billing['state'] ?? '',
            $billing['postcode'] ?? '',
        ]);
        if (!empty($cityLine)) {
            $parts[] = implode(', ', $cityLine);
        }

        // Country
        if (!empty($billing['country'])) {
            $parts[] = $billing['country'];
        }

        return implode($separator, $parts);
    }

    /**
     * Extract billing array from stored data
     *
     * @param mixed $stored Stored data
     * @return array Billing array
     */
    private function extractBilling($stored): array {
        if (!is_array($stored)) {
            return $this->defaultFields;
        }

        // Check if nested under 'billing' key
        if (isset($stored['billing']) && is_array($stored['billing'])) {
            return $stored['billing'];
        }

        return $stored;
    }

    /**
     * Sanitize billing data
     *
     * @param array $data Raw data
     * @return array Sanitized data
     */
    private function sanitizeBillingData(array $data): array {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeBillingData($value);
            } elseif ($key === 'email') {
                $sanitized[$key] = sanitize_email($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Locate template file
     *
     * @param string $template Template filename
     * @return string|null Full path to template or null
     */
    private function locateTemplate(string $template): ?string {
        if (function_exists('wpdm_tpl_path')) {
            $path = wpdm_tpl_path($template, WPDMPP_TPL_DIR);
            if ($path) {
                return $path;
            }
        }

        // Fallback to plugin views directory
        $fallback = WPDMPP_BASE_DIR . 'templates/' . $template;
        if (file_exists($fallback)) {
            return $fallback;
        }

        return null;
    }

    /**
     * Render default billing fields when no template is found
     *
     * @param array  $billing Billing data
     * @param string $context Context (admin or frontend)
     */
    private function renderDefaultFields(array $billing, string $context): void {
        $labels = $this->getFieldLabels();
        $required = $this->getRequiredFields();

        echo '<h3>' . esc_html__('Billing Information', 'wpdm-premium-packages') . '</h3>';
        echo '<table class="form-table">';

        foreach ($this->defaultFields as $field => $default) {
            $value = $billing[$field] ?? $default;
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $isRequired = in_array($field, $required);
            $inputName = "checkout[billing][{$field}]";

            echo '<tr>';
            echo '<th><label for="billing_' . esc_attr($field) . '">' . esc_html($label);
            if ($isRequired) {
                echo ' <span class="required">*</span>';
            }
            echo '</label></th>';
            echo '<td>';

            if ($field === 'country') {
                $this->renderCountrySelect($inputName, $value);
            } else {
                echo '<input type="' . ($field === 'email' ? 'email' : 'text') . '" ';
                echo 'name="' . esc_attr($inputName) . '" ';
                echo 'id="billing_' . esc_attr($field) . '" ';
                echo 'value="' . esc_attr($value) . '" ';
                echo 'class="regular-text" ';
                if ($isRequired) {
                    echo 'required ';
                }
                echo '/>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    /**
     * Render country select dropdown
     *
     * @param string $name  Input name
     * @param string $value Selected value
     */
    private function renderCountrySelect(string $name, string $value): void {
        $countries = $this->getCountries();

        echo '<select name="' . esc_attr($name) . '" class="regular-text">';
        echo '<option value="">' . esc_html__('Select Country', 'wpdm-premium-packages') . '</option>';

        foreach ($countries as $code => $country) {
            $selected = selected($value, $code, false);
            echo '<option value="' . esc_attr($code) . '"' . $selected . '>' . esc_html($country) . '</option>';
        }

        echo '</select>';
    }

    /**
     * Get list of countries
     *
     * @return array Countries array [code => name]
     */
    private function getCountries(): array {
        // Common countries subset
        $countries = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IE' => 'Ireland',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'PL' => 'Poland',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'IN' => 'India',
            'JP' => 'Japan',
            'CN' => 'China',
            'KR' => 'South Korea',
            'SG' => 'Singapore',
            'NZ' => 'New Zealand',
        ];

        /**
         * Filter the list of countries for billing
         *
         * @param array $countries Countries array
         */
        return apply_filters('wpdmpp_billing_countries', $countries);
    }

    /**
     * Add customer role to user
     *
     * @param int $userId User ID
     */
    private function addCustomerRole(int $userId): void {
        CustomerService::getInstance()->addCustomer($userId);
    }

    /**
     * Remove customer role from user
     *
     * @param int $userId User ID
     */
    private function removeCustomerRole(int $userId): void {
        if (class_exists('\WPDMPP\Customer\CustomerService')) {
            CustomerService::getInstance()->removeCustomer($userId);
        }
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
