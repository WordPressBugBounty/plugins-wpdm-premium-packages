<?php
/**
 * PayPal Payout Method
 *
 * PayPal payout implementation.
 *
 * @package WPDMPP\Payout\Methods
 * @since 7.0.0
 */

namespace WPDMPP\Payout\Methods;

use WPDMPP\Payout\AbstractPayoutMethod;
use WPDMPP\Payout\Withdraw;

defined('ABSPATH') || exit;

class PayPalPayoutMethod extends AbstractPayoutMethod
{
    /**
     * Method ID
     *
     * @var string
     */
    protected string $id = 'paypal';

    /**
     * Method name
     *
     * @var string
     */
    protected string $name = 'PayPal';

    /**
     * Method icon URL
     *
     * @var string
     */
    protected string $icon = '';

    /**
     * Default minimum amount
     *
     * @var float
     */
    protected float $defaultMinimum = 10.0;

    /**
     * Constructor
     */
    public function __construct()
    {
        if (defined('WPDMPP_BASE_URL')) {
            $this->icon = WPDMPP_BASE_URL . 'assets/images/paypal.png';
        }
    }

    /**
     * Get the payout form/link HTML for admin to process payout
     *
     * @param Withdraw $request   Withdrawal request
     * @param array    $account   User's account info for this method
     * @return string HTML
     */
    public function getPayoutLink(Withdraw $request, array $account): string
    {
        $paypalEmail = $account['account'] ?? '';

        if (empty($paypalEmail)) {
            return '<span class="text-danger">' . esc_html__('No PayPal email configured', 'wpdm-premium-packages') . '</span>';
        }

        $siteName = $this->getSiteName();
        $currencyCode = $this->getCurrencyCode();

        $html = sprintf(
            '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" id="payPalForm_%d" name="payPalForm">
                <input type="hidden" name="item_number" value="PAYOUT%d">
                <input type="hidden" name="cmd" value="_xclick">
                <input type="hidden" name="item_name" value="Payout from %s">
                <input type="hidden" name="no_note" value="1">
                <input type="hidden" name="business" value="%s">
                <input type="hidden" name="currency_code" value="%s">
                <input type="hidden" name="return" value="%s">
                <input type="hidden" name="notify_url" value="%s">
                <input name="amount" type="hidden" id="amount" value="%s">
                <input type="hidden" name="custom" value="%d">
                <button name="sub" class="btn btn-info btn-sm" type="submit">%s</button>
            </form>',
            $request->getId(),
            $request->getId(),
            esc_attr($siteName),
            esc_attr($paypalEmail),
            esc_attr($currencyCode),
            esc_url(home_url('/')),
            esc_url(home_url('/?action=withdraw_paypal_notification')),
            esc_attr($request->getAmount()),
            $request->getId(),
            esc_html__('Pay Now', 'wpdm-premium-packages')
        );

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
        $email = $savedAccount['paypal'] ?? '';

        return sprintf(
            '<div class="form-group">
                <label for="payout_paypal_email">%s</label>
                <input type="email" name="account[paypal]" id="payout_paypal_email"
                       class="form-control" value="%s"
                       placeholder="%s">
                <small class="form-text text-muted">%s</small>
            </div>',
            esc_html__('PayPal Email', 'wpdm-premium-packages'),
            esc_attr($email),
            esc_attr__('your@email.com', 'wpdm-premium-packages'),
            esc_html__('Enter the email address associated with your PayPal account.', 'wpdm-premium-packages')
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
        $email = $data['paypal'] ?? '';

        if (empty($email)) {
            $errors['paypal'] = __('PayPal email is required.', 'wpdm-premium-packages');
        } elseif (!is_email($email)) {
            $errors['paypal'] = __('Please enter a valid email address.', 'wpdm-premium-packages');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
