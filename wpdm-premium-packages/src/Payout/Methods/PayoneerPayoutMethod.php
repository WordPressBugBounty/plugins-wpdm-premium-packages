<?php
/**
 * Payoneer Payout Method
 *
 * Payoneer payout implementation.
 *
 * @package WPDMPP\Payout\Methods
 * @since 7.0.0
 */

namespace WPDMPP\Payout\Methods;

use WPDMPP\Payout\AbstractPayoutMethod;
use WPDMPP\Payout\Withdraw;

defined('ABSPATH') || exit;

class PayoneerPayoutMethod extends AbstractPayoutMethod
{
    /**
     * Method ID
     *
     * @var string
     */
    protected string $id = 'payoneer';

    /**
     * Method name
     *
     * @var string
     */
    protected string $name = 'Payoneer';

    /**
     * Method icon URL
     *
     * @var string
     */
    protected string $icon = 'https://www.payoneer.com/wp-content/uploads/payoneer-circle.png';

    /**
     * Default minimum amount
     *
     * @var float
     */
    protected float $defaultMinimum = 50.0;

    /**
     * Get the payout form/link HTML for admin to process payout
     *
     * @param Withdraw $request   Withdrawal request
     * @param array    $account   User's account info for this method
     * @return string HTML
     */
    public function getPayoutLink(Withdraw $request, array $account): string
    {
        $payoneerId = $account['account'] ?? '';

        $html = sprintf(
            '<a href="https://myaccount.payoneer.com/ma/pay/makeapayment" target="_blank"
                class="btn btn-info btn-sm">%s</a>',
            esc_html__('Pay Now', 'wpdm-premium-packages')
        );

        if (!empty($payoneerId)) {
            $html .= sprintf(
                '<br><small class="text-muted">%s: %s</small>',
                esc_html__('Payoneer ID', 'wpdm-premium-packages'),
                esc_html($payoneerId)
            );
        }

        return $html;
    }

    /**
     * Render user account settings form
     *
     * @param array $savedAccount User's saved account data
     * @return string HTML form fields
     */
    public function renderAccountForm(array $savedAccount = []): string
    {
        $payoneerId = $savedAccount['payoneer'] ?? '';

        return sprintf(
            '<div class="form-group">
                <label for="payout_payoneer_id">%s</label>
                <input type="text" name="account[payoneer]" id="payout_payoneer_id"
                       class="form-control" value="%s"
                       placeholder="%s">
                <small class="form-text text-muted">%s</small>
            </div>',
            esc_html__('Payoneer Account Email/ID', 'wpdm-premium-packages'),
            esc_attr($payoneerId),
            esc_attr__('your@email.com or Payoneer ID', 'wpdm-premium-packages'),
            esc_html__('Enter your Payoneer account email or ID.', 'wpdm-premium-packages')
        );
    }

    /**
     * Validate user account data
     *
     * @param array $data Account data to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateAccount(array $data): array
    {
        $errors = [];
        $payoneerId = $data['payoneer'] ?? '';

        if (empty($payoneerId)) {
            $errors['payoneer'] = __('Payoneer account ID is required.', 'wpdm-premium-packages');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
