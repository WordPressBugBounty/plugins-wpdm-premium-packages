<?php
/**
 * Payment Gateway Interface
 *
 * Defines the contract that all payment gateways must implement.
 *
 * @package WPDMPP\Payment
 * @since 7.0.0
 */

namespace WPDMPP\Payment;

defined('ABSPATH') || exit;

interface PaymentGatewayInterface {

    /**
     * Get the gateway ID
     *
     * @return string Unique gateway identifier
     */
    public function getId(): string;

    /**
     * Get the gateway title
     *
     * @return string Human-readable title
     */
    public function getTitle(): string;

    /**
     * Get the gateway description
     *
     * @return string Gateway description
     */
    public function getDescription(): string;

    /**
     * Get the gateway icon URL
     *
     * @return string Icon URL or empty string
     */
    public function getIcon(): string;

    /**
     * Check if gateway is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Check if gateway supports a specific feature
     *
     * @param string $feature Feature name (e.g., 'recurring', 'refunds', 'webhooks')
     * @return bool
     */
    public function supports(string $feature): bool;

    /**
     * Render the gateway settings form
     *
     * @return string HTML for settings form
     */
    public function renderSettings(): string;

    /**
     * Process the payment
     *
     * @param array $orderData Order data including billing info
     * @return array Result with 'success' boolean and additional data
     */
    public function processPayment(array $orderData): array;

    /**
     * Get form fields for checkout
     *
     * @return string HTML for checkout form fields
     */
    public function getCheckoutFields(): string;

    /**
     * Validate payment data before processing
     *
     * @param array $data Payment data to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validatePaymentData(array $data): array;
}
