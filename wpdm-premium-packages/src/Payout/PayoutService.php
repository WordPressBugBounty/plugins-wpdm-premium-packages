<?php
/**
 * Payout Service
 *
 * Main orchestration for payout/withdrawal management.
 *
 * @package WPDMPP\Payout
 * @since 7.0.0
 */

namespace WPDMPP\Payout;

use WPDMPP\Payout\Methods\PayPalPayoutMethod;
use WPDMPP\Payout\Methods\PayoneerPayoutMethod;

defined('ABSPATH') || exit;

class PayoutService
{
    /**
     * Singleton instance
     *
     * @var PayoutService|null
     */
    private static ?PayoutService $instance = null;

    /**
     * Whether the service has been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Registered payout methods
     *
     * @var array<string, PayoutMethodInterface>
     */
    private array $methods = [];

    /**
     * Get singleton instance
     *
     * @return PayoutService
     */
    public static function getInstance(): PayoutService
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
        $this->registerDefaultMethods();
    }

    /**
     * Register hooks and initialize service
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        // AJAX handlers
        add_action('wp_ajax_wpdmpp_user_payment_options', [$this, 'ajaxSaveUserPaymentAccount']);
        add_action('wp_ajax_wpdmpp_request_withdrawal', [$this, 'ajaxRequestWithdrawal']);
    }

    /**
     * Register default payout methods
     */
    private function registerDefaultMethods(): void
    {
        $this->methods['paypal'] = new PayPalPayoutMethod();
        $this->methods['payoneer'] = new PayoneerPayoutMethod();

        /**
         * Filter to register additional payout methods
         *
         * @param array $methods Current methods
         */
        $this->methods = apply_filters('wpdmpp_payout_methods_instances', $this->methods);
    }

    /**
     * Get all registered payout methods
     *
     * @param bool $enabledOnly Only return enabled methods
     * @return array<string, PayoutMethodInterface>
     */
    public function getMethods(bool $enabledOnly = false): array
    {
        if (!$enabledOnly) {
            return $this->methods;
        }

        return array_filter($this->methods, fn($method) => $method->isEnabled());
    }

    /**
     * Get a specific payout method
     *
     * @param string $methodId Method ID
     * @return PayoutMethodInterface|null
     */
    public function getMethod(string $methodId): ?PayoutMethodInterface
    {
        return $this->methods[$methodId] ?? null;
    }

    /**
     * Get methods as array (for API/legacy compatibility)
     *
     * @return array
     */
    public function getMethodsArray(): array
    {
        $result = [];

        foreach ($this->methods as $id => $method) {
            $result[$id] = $method->toArray();
        }

        return $result;
    }

    /**
     * Get user's payment accounts
     *
     * @param int $userId User ID (0 for current user)
     * @return array
     */
    public function getUserPaymentAccounts(int $userId = 0): array
    {
        if ($userId <= 0) {
            $userId = get_current_user_id();
        }

        $accounts = get_user_meta($userId, '__wpdmpp_payment_account', true);

        return is_array($accounts) ? $accounts : [];
    }

    /**
     * Save user's payment account
     *
     * @param int   $userId  User ID
     * @param array $account Account data
     * @return bool
     */
    public function saveUserPaymentAccount(int $userId, array $account): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return (bool) update_user_meta($userId, '__wpdmpp_payment_account', $account);
    }

    /**
     * Get payment account info for a payout request
     *
     * @param Withdraw $payout Payout request
     * @return array
     */
    public function getPaymentAccountForPayout(Withdraw $payout): array
    {
        $userAccounts = $this->getUserPaymentAccounts($payout->getUserId());
        $methodId = $payout->getPaymentMethod() ?: 'paypal';
        $method = $this->getMethod($methodId);

        return [
            'method' => $method,
            'method_id' => $methodId,
            'method_info' => $method ? $method->toArray() : null,
            'account' => $userAccounts[$methodId] ?? null,
        ];
    }

    /**
     * Get withdrawal requests
     *
     * @param array $params Query parameters
     * @return Withdraw[]
     */
    public function getWithdrawals(array $params = []): array
    {
        global $wpdb;

        $conditions = [];
        $values = [];
        $allowedFields = ['uid', 'status', 'payment_method', 'id'];

        foreach ($params as $field => $value) {
            if (!in_array($field, $allowedFields)) {
                continue;
            }

            if (is_int($value) || is_numeric($value)) {
                $conditions[] = "`{$field}` = %d";
                $values[] = (int) $value;
            } else {
                $conditions[] = "`{$field}` = %s";
                $values[] = $value;
            }
        }

        $sql = "SELECT * FROM {$wpdb->prefix}ahm_withdraws";

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY `date` DESC';

        if (!empty($values)) {
            $results = $wpdb->get_results($wpdb->prepare($sql, $values));
        } else {
            $results = $wpdb->get_results($sql);
        }

        $withdrawals = [];
        foreach ($results as $row) {
            $withdrawals[] = Withdraw::fromRow($row);
        }

        return $withdrawals;
    }

    /**
     * Get a single withdrawal by ID
     *
     * @param int $id Withdrawal ID
     * @return Withdraw|null
     */
    public function getWithdrawal(int $id): ?Withdraw
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_withdraws WHERE id = %d",
            $id
        ));

        return $row ? Withdraw::fromRow($row) : null;
    }

    /**
     * Get user's withdrawals
     *
     * @param int $userId User ID
     * @return Withdraw[]
     */
    public function getUserWithdrawals(int $userId): array
    {
        return $this->getWithdrawals(['uid' => $userId]);
    }

    /**
     * Request a withdrawal
     *
     * @param int    $userId        User ID
     * @param float  $amount        Amount to withdraw
     * @param string $paymentMethod Payment method ID
     * @return array ['success' => bool, 'message' => string, 'withdrawal' => Withdraw|null]
     */
    public function requestWithdrawal(int $userId, float $amount, string $paymentMethod): array
    {
        global $wpdb;

        // Validate user
        if ($userId <= 0) {
            return [
                'success' => false,
                'message' => __('Invalid user.', 'wpdm-premium-packages'),
                'withdrawal' => null,
            ];
        }

        // Validate method
        $method = $this->getMethod($paymentMethod);
        if (!$method || !$method->isEnabled()) {
            return [
                'success' => false,
                'message' => __('Invalid or disabled payment method.', 'wpdm-premium-packages'),
                'withdrawal' => null,
            ];
        }

        // Validate minimum amount
        $minAmount = $method->getMinimumAmount();
        if ($amount < $minAmount) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Minimum withdrawal amount for %s is %s.', 'wpdm-premium-packages'),
                    $method->getName(),
                    wpdmpp_price_format($minAmount)
                ),
                'withdrawal' => null,
            ];
        }

        // Check user has payment account configured
        $accounts = $this->getUserPaymentAccounts($userId);
        if (empty($accounts[$paymentMethod])) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Please configure your %s account first.', 'wpdm-premium-packages'),
                    $method->getName()
                ),
                'withdrawal' => null,
            ];
        }

        // Create withdrawal request
        $data = [
            'uid' => $userId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'status' => Withdraw::STATUS_PENDING,
            'date' => time(),
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'ahm_withdraws',
            $data,
            ['%d', '%f', '%s', '%s', '%d']
        );

        if (!$result) {
            return [
                'success' => false,
                'message' => __('Failed to create withdrawal request.', 'wpdm-premium-packages'),
                'withdrawal' => null,
            ];
        }

        $withdrawal = $this->getWithdrawal($wpdb->insert_id);

        /**
         * Fires after a withdrawal request is created
         *
         * @param Withdraw $withdrawal The withdrawal request
         */
        do_action('wpdmpp_withdrawal_requested', $withdrawal);

        return [
            'success' => true,
            'message' => __('Withdrawal request submitted successfully.', 'wpdm-premium-packages'),
            'withdrawal' => $withdrawal,
        ];
    }

    /**
     * Process a withdrawal (admin action)
     *
     * @param int    $withdrawalId  Withdrawal ID
     * @param string $transactionId Transaction ID from payment
     * @return array ['success' => bool, 'message' => string]
     */
    public function completeWithdrawal(int $withdrawalId, string $transactionId = ''): array
    {
        global $wpdb;

        $withdrawal = $this->getWithdrawal($withdrawalId);

        if (!$withdrawal) {
            return [
                'success' => false,
                'message' => __('Withdrawal not found.', 'wpdm-premium-packages'),
            ];
        }

        if (!$withdrawal->canProcess()) {
            return [
                'success' => false,
                'message' => __('This withdrawal cannot be processed.', 'wpdm-premium-packages'),
            ];
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'ahm_withdraws',
            [
                'status' => Withdraw::STATUS_COMPLETED,
                'trans_id' => $transactionId,
                'processed_date' => time(),
            ],
            ['id' => $withdrawalId],
            ['%s', '%s', '%d'],
            ['%d']
        );

        if ($result === false) {
            return [
                'success' => false,
                'message' => __('Failed to update withdrawal status.', 'wpdm-premium-packages'),
            ];
        }

        /**
         * Fires after a withdrawal is completed
         *
         * @param int    $withdrawalId  Withdrawal ID
         * @param string $transactionId Transaction ID
         */
        do_action('wpdmpp_withdrawal_completed', $withdrawalId, $transactionId);

        return [
            'success' => true,
            'message' => __('Withdrawal marked as completed.', 'wpdm-premium-packages'),
        ];
    }

    /**
     * Reject a withdrawal (admin action)
     *
     * @param int    $withdrawalId Withdrawal ID
     * @param string $reason       Rejection reason
     * @return array ['success' => bool, 'message' => string]
     */
    public function rejectWithdrawal(int $withdrawalId, string $reason = ''): array
    {
        global $wpdb;

        $withdrawal = $this->getWithdrawal($withdrawalId);

        if (!$withdrawal) {
            return [
                'success' => false,
                'message' => __('Withdrawal not found.', 'wpdm-premium-packages'),
            ];
        }

        if (!$withdrawal->canProcess()) {
            return [
                'success' => false,
                'message' => __('This withdrawal cannot be rejected.', 'wpdm-premium-packages'),
            ];
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'ahm_withdraws',
            [
                'status' => Withdraw::STATUS_REJECTED,
                'note' => $reason,
                'processed_date' => time(),
            ],
            ['id' => $withdrawalId],
            ['%s', '%s', '%d'],
            ['%d']
        );

        if ($result === false) {
            return [
                'success' => false,
                'message' => __('Failed to update withdrawal status.', 'wpdm-premium-packages'),
            ];
        }

        /**
         * Fires after a withdrawal is rejected
         *
         * @param int    $withdrawalId Withdrawal ID
         * @param string $reason       Rejection reason
         */
        do_action('wpdmpp_withdrawal_rejected', $withdrawalId, $reason);

        return [
            'success' => true,
            'message' => __('Withdrawal has been rejected.', 'wpdm-premium-packages'),
        ];
    }

    /**
     * Get minimum payout amount for a method
     *
     * @param string $methodId Method ID
     * @return float
     */
    public function getMinPayoutAmount(string $methodId): float
    {
        $method = $this->getMethod($methodId);
        return $method ? $method->getMinimumAmount() : 0.0;
    }

    /**
     * AJAX: Save user payment account
     */
    public function ajaxSaveUserPaymentAccount(): void
    {
        if (!method_exists('\WPDM\__\__', 'isAuthentic')) {
            wp_send_json_error(['message' => 'Authentication failed.']);
        }

        \WPDM\__\__::isAuthentic('__supanonce', WPDM_PUB_NONCE, 'read', true);

        $account = isset($_POST['account']) ? wpdm_sanitize_array($_POST['account']) : [];
        $userId = get_current_user_id();

        if (!$userId) {
            wp_send_json_error(['message' => __('Please log in.', 'wpdm-premium-packages')]);
        }

        $this->saveUserPaymentAccount($userId, $account);

        wp_send_json([
            'success' => true,
            'type' => 'success',
            'message' => __('Payment information has been updated!', 'wpdm-premium-packages'),
        ]);
    }

    /**
     * AJAX: Request withdrawal
     */
    public function ajaxRequestWithdrawal(): void
    {
        if (!method_exists('\WPDM\__\__', 'isAuthentic')) {
            wp_send_json_error(['message' => 'Authentication failed.']);
        }

        \WPDM\__\__::isAuthentic('__supanonce', WPDM_PUB_NONCE, 'read', true);

        $amount = (float) wpdm_query_var('amount', 'float');
        $method = wpdm_query_var('payment_method', 'txt');
        $userId = get_current_user_id();

        if (!$userId) {
            wp_send_json_error(['message' => __('Please log in.', 'wpdm-premium-packages')]);
        }

        $result = $this->requestWithdrawal($userId, $amount, $method);

        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message'],
                'withdrawal' => $result['withdrawal'] ? $result['withdrawal']->toArray() : null,
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
