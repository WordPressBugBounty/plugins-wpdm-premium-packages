<?php
/**
 * Legacy Withdraws Handler
 *
 * Provides backward compatibility bridge to new PayoutService.
 *
 * @package WPDMPP\Libs
 * @deprecated 7.0.0 Use \WPDMPP\Payout\PayoutService instead
 */

namespace WPDMPP\Libs;

use WPDMPP\Payout\PayoutService;
use WPDMPP\Payout\Withdraw;

if (!defined('ABSPATH')) {
    exit;
}

class Withdraws
{
    /**
     * Get the new PayoutService instance
     *
     * @return PayoutService
     */
    public static function service(): PayoutService
    {
        return PayoutService::getInstance();
    }

    /**
     * Constructor - registers via new service
     *
     * @deprecated 7.0.0 Use PayoutService::register()
     */
    function __construct()
    {
        // Register the new service
        self::service()->register();
    }

    /**
     * Get payout methods
     *
     * @deprecated 7.0.0 Use PayoutService::getMethodsArray()
     * @return array
     */
    function getPayoutMethods()
    {
        return self::service()->getMethodsArray();
    }

    /**
     * Save user payment account (AJAX handler)
     *
     * @deprecated 7.0.0 Use PayoutService::ajaxSaveUserPaymentAccount()
     */
    function saveUserPaymentAccount()
    {
        self::service()->ajaxSaveUserPaymentAccount();
    }

    /**
     * Get withdrawal requests
     *
     * @deprecated 7.0.0 Use PayoutService::getWithdrawals()
     * @param array $params Query parameters
     * @return array
     */
    function getRequests($params = [])
    {
        $withdrawals = self::service()->getWithdrawals($params);

        // Convert to legacy format for backward compatibility
        $results = [];
        foreach ($withdrawals as $withdrawal) {
            $obj = (object) $withdrawal->toArray();
            $obj->user = $withdrawal->getUser();
            $results[] = $obj;
        }

        return $results;
    }

    /**
     * Get payment account for a payout
     *
     * @deprecated 7.0.0 Use PayoutService::getPaymentAccountForPayout()
     * @param object $payout Payout object
     * @return array
     */
    function getPaymentAccount($payout)
    {
        // Convert to Withdraw entity if needed
        if (!($payout instanceof Withdraw)) {
            $payout = Withdraw::fromRow($payout);
        }

        $info = self::service()->getPaymentAccountForPayout($payout);

        // Format for legacy compatibility
        $accounts = self::service()->getMethodsArray();
        $methodId = $payout->getPaymentMethod() ?: 'paypal';
        $account = $accounts[$methodId] ?? [];

        // Get user's account value
        $userAccounts = self::service()->getUserPaymentAccounts($payout->getUserId());
        $account['account'] = $userAccounts[$methodId] ?? '';

        // Get method instance for payoutLink
        $method = self::service()->getMethod($methodId);
        if ($method) {
            // Create legacy wrapper
            $account['method'] = new class($method, $payout) {
                private $method;
                private $withdraw;

                public function __construct($method, $withdraw) {
                    $this->method = $method;
                    $this->withdraw = $withdraw;
                }

                public function payoutLink($request, $account) {
                    return $this->method->getPayoutLink($this->withdraw, $account);
                }
            };
        }

        return $account;
    }

    /**
     * Get payout accounts
     *
     * @deprecated 7.0.0 Use PayoutService::getUserPaymentAccounts()
     * @param int|null $uid User ID
     * @return array
     */
    function payoutAccounts($uid = null)
    {
        if ($uid === null) {
            return self::service()->getMethodsArray();
        }

        return self::service()->getUserPaymentAccounts($uid);
    }

    /**
     * Process payout (placeholder)
     *
     * @deprecated 7.0.0 Use PayoutService::completeWithdrawal()
     * @param object $request Payout request
     */
    function processPayout($request)
    {
        // Placeholder - actual processing done via admin actions
    }

    /**
     * Render withdraws shortcode
     *
     * @deprecated 7.0.0 Use shortcode via ShortcodeService
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    function requests($atts = [])
    {
        ob_start();

        $requests = $this->getRequests(['uid' => get_current_user_id()]);
        $methods = $this->getPayoutMethods();
        $accounts = get_user_meta(get_current_user_id(), '__wpdmpp_payment_account', true);

        $template = \WPDM\__\Template::locate('dashboard/withdraws.php', WPDMPP_TPL_DIR);
        if ($template && file_exists($template)) {
            include $template;
        }

        return ob_get_clean();
    }

    // =========================================
    // Static convenience methods
    // =========================================

    /**
     * Get all payout methods
     *
     * @return array
     */
    public static function getPayoutMethodsStatic()
    {
        return self::service()->getMethodsArray();
    }

    /**
     * Get user withdrawals
     *
     * @param int $userId User ID
     * @return array
     */
    public static function getUserWithdrawals($userId)
    {
        return self::service()->getUserWithdrawals($userId);
    }

    /**
     * Request a withdrawal
     *
     * @param int    $userId        User ID
     * @param float  $amount        Amount
     * @param string $paymentMethod Payment method
     * @return array
     */
    public static function requestWithdrawal($userId, $amount, $paymentMethod)
    {
        return self::service()->requestWithdrawal($userId, $amount, $paymentMethod);
    }

    /**
     * Complete a withdrawal
     *
     * @param int    $withdrawalId  Withdrawal ID
     * @param string $transactionId Transaction ID
     * @return array
     */
    public static function completeWithdrawal($withdrawalId, $transactionId = '')
    {
        return self::service()->completeWithdrawal($withdrawalId, $transactionId);
    }

    /**
     * Reject a withdrawal
     *
     * @param int    $withdrawalId Withdrawal ID
     * @param string $reason       Reason
     * @return array
     */
    public static function rejectWithdrawal($withdrawalId, $reason = '')
    {
        return self::service()->rejectWithdrawal($withdrawalId, $reason);
    }

    /**
     * Get minimum payout amount
     *
     * @param string $methodId Method ID
     * @return float
     */
    public static function getMinPayoutAmount($methodId)
    {
        return self::service()->getMinPayoutAmount($methodId);
    }
}

new Withdraws();
