<?php
/**
 * Payout Method Interface
 *
 * Contract for payout method implementations.
 *
 * @package WPDMPP\Payout
 * @since 7.0.0
 */

namespace WPDMPP\Payout;

defined('ABSPATH') || exit;

interface PayoutMethodInterface
{
    /**
     * Get method ID
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get method name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get method icon URL
     *
     * @return string
     */
    public function getIcon(): string;

    /**
     * Get minimum payout amount
     *
     * @return float
     */
    public function getMinimumAmount(): float;

    /**
     * Check if method is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Get the payout form/link HTML for admin to process payout
     *
     * @param Withdraw $request   Withdrawal request
     * @param array    $account   User's account info for this method
     * @return string HTML
     */
    public function getPayoutLink(Withdraw $request, array $account): string;

    /**
     * Render user account settings form
     *
     * @param array $savedAccount User's saved account data
     * @return string HTML form fields
     */
    public function renderAccountForm(array $savedAccount = []): string;

    /**
     * Validate user account data
     *
     * @param array $data Account data to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateAccount(array $data): array;

    /**
     * Process a payout (if automated)
     *
     * @param Withdraw $request Withdrawal request
     * @param array    $account User's account info
     * @return array ['success' => bool, 'message' => string, 'transaction_id' => string|null]
     */
    public function processPayout(Withdraw $request, array $account): array;
}
