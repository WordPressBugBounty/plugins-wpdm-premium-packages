<?php
/**
 * Abstract Payment Gateway
 *
 * Base class for all payment gateways with common functionality.
 *
 * @package WPDMPP\Payment
 * @since 7.0.0
 */

namespace WPDMPP\Payment;

defined('ABSPATH') || exit;

abstract class AbstractGateway implements PaymentGatewayInterface {

    use GatewayFormBuilder;

    /**
     * Gateway ID
     *
     * @var string
     */
    protected string $id = '';

    /**
     * Gateway title
     *
     * @var string
     */
    protected string $title = '';

    /**
     * Gateway description
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Gateway icon URL
     *
     * @var string
     */
    protected string $icon = '';

    /**
     * Settings key for option storage
     *
     * @var string
     */
    protected string $settingsKey = '';

    /**
     * Supported features
     *
     * @var array
     */
    protected array $supports = [];

    /**
     * Gateway settings
     *
     * @var array
     */
    protected array $settings = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->loadSettings();
    }

    /**
     * Load gateway settings from database
     */
    protected function loadSettings(): void {
        $settings = get_option('_wpdmpp_' . $this->id, []);
        $this->settings = is_array($settings) ? $settings : [];
    }

    /**
     * Save gateway settings
     *
     * @param array $settings Settings to save
     * @return bool
     */
    public function saveSettings(array $settings): bool {
        $this->settings = $settings;
        return update_option('_wpdmpp_' . $this->id, $settings);
    }

    /**
     * Get a specific setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function getSetting(string $key, $default = '') {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Get the settings key prefix for this gateway
     * Used by GatewayFormBuilder trait for field names and settings storage.
     *
     * @return string
     */
    public function getSettingsKey(): string {
        return $this->settingsKey ?: $this->id;
    }

    /**
     * Get an option value for this gateway (alias for getFormOption)
     * This bridges the existing getSetting method with the form builder.
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function getOption(string $key, $default = '') {
        return get_wpdmpp_option($this->getSettingsKey() . '/' . $key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle(): string {
        return $this->title;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    public function getIcon(): string {
        return $this->icon;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool {
        return (bool) $this->getSetting('enabled', false);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $feature): bool {
        return in_array($feature, $this->supports, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getCheckoutFields(): string {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function validatePaymentData(array $data): array {
        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    /**
     * Log gateway activity
     *
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $level Log level (info, error, debug)
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_message = sprintf(
            '[WPDMPP %s Gateway] [%s] %s | Context: %s',
            strtoupper($this->id),
            strtoupper($level),
            $message,
            wp_json_encode($context)
        );

        error_log($log_message);
    }

    /**
     * Get the order object
     *
     * @return \WPDMPP\Order\OrderService|null
     */
    protected function getOrderHandler() {
        if (function_exists('WPDMPP') && isset(WPDMPP()->order)) {
            return WPDMPP()->order;
        }
        return null;
    }

    /**
     * Get the cart object
     *
     * @return \WPDMPP\Libs\Cart|null
     */
    protected function getCartHandler() {
        if (function_exists('WPDMPP') && isset(WPDMPP()->cart)) {
            return WPDMPP()->cart;
        }
        return null;
    }

    /**
     * Format price for display
     *
     * @param float $amount Amount to format
     * @return string Formatted price
     */
    protected function formatPrice(float $amount): string {
        if (function_exists('wpdmpp_price_format')) {
            return wpdmpp_price_format($amount);
        }
        return number_format($amount, 2);
    }

    /**
     * Get currency code
     *
     * @return string
     */
    protected function getCurrency(): string {
        return get_option('_wpdmpp_currency', 'USD');
    }

    /**
     * Create a redirect response
     *
     * @param string $url Redirect URL
     * @return array
     */
    protected function redirectResponse(string $url): array {
        return [
            'success' => true,
            'redirect' => true,
            'redirect_url' => $url,
        ];
    }

    /**
     * Create a success response
     *
     * @param string $message Success message
     * @param array $data Additional data
     * @return array
     */
    protected function successResponse(string $message = '', array $data = []): array {
        return array_merge([
            'success' => true,
            'message' => $message,
        ], $data);
    }

    /**
     * Create an error response
     *
     * @param string $message Error message
     * @param array $data Additional data
     * @return array
     */
    protected function errorResponse(string $message, array $data = []): array {
        return array_merge([
            'success' => false,
            'message' => $message,
        ], $data);
    }
}
