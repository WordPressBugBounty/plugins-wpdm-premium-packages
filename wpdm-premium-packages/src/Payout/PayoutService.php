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
     * Calculate a seller's balances
     *
     * Earnings are derived from completed orders on packages authored by the
     * given user, less the site commission for that user's role. Every existing
     * withdrawal row (pending or paid) encumbers the balance, so an amount can
     * never be requested twice.
     *
     * "Matured" earnings are those from orders older than the configured payout
     * duration; only matured funds may be withdrawn.
     *
     * @param int $userId User ID (0 for current user)
     * @return array ['total_sales', 'total_earning', 'total_withdraws', 'balance', 'matured', 'pending']
     */
    public function getSellerBalances(int $userId = 0): array
    {
        global $wpdb;

        if ($userId <= 0) {
            $userId = get_current_user_id();
        }

        if ($userId <= 0) {
            return [
                'total_sales' => 0.0,
                'total_earning' => 0.0,
                'total_withdraws' => 0.0,
                'balance' => 0.0,
                'matured' => 0.0,
                'pending' => 0.0,
            ];
        }

        $salesSql = "SELECT SUM(i.price * i.quantity)
             FROM {$wpdb->prefix}ahm_orders o,
                  {$wpdb->prefix}ahm_order_items i,
                  {$wpdb->prefix}posts p
             WHERE p.post_author = %d
             AND i.oid = o.order_id
             AND i.pid = p.ID
             AND i.quantity > 0
             AND o.payment_status = 'Completed'";

        $totalSales = (float) $wpdb->get_var($wpdb->prepare($salesSql, $userId));

        $commission = (float) wpdmpp_site_commission($userId);
        $totalEarning = $totalSales - ($totalSales * $commission / 100);

        // Every withdrawal row holds funds — pending requests included.
        $totalWithdraws = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}ahm_withdraws WHERE uid = %d",
            $userId
        ));

        $balance = $totalEarning - $totalWithdraws;

        // Matured earnings: orders placed before the payout duration cutoff.
        $payoutDuration = (int) get_option('wpdmpp_payout_duration');
        $maturedTime = time() - ($payoutDuration * DAY_IN_SECONDS);

        $maturedSales = (float) $wpdb->get_var($wpdb->prepare(
            $salesSql . ' AND o.date < %d',
            $userId,
            $maturedTime
        ));

        $maturedEarning = $maturedSales - ($maturedSales * $commission / 100);
        $matured = $maturedEarning - $totalWithdraws;

        return [
            'total_sales' => $totalSales,
            'total_earning' => $totalEarning,
            'total_withdraws' => $totalWithdraws,
            'balance' => $balance,
            'matured' => $matured,
            'pending' => $balance - $matured,
        ];
    }

    /**
     * Get the amount a user is currently allowed to withdraw
     *
     * @param int $userId User ID (0 for current user)
     * @return float
     */
    public function getAvailableBalance(int $userId = 0): float
    {
        $balances = $this->getSellerBalances($userId);

        return max(0.0, (float) $balances['matured']);
    }

    /**
     * Acquire an advisory lock scoped to a user's payout balance
     *
     * Returns the lock name on success, or null if the lock could not be taken
     * (the caller still proceeds — the balance check alone remains correct for
     * everything except a same-user race).
     *
     * @param int $userId User ID
     * @return string|null
     */
    private function acquireUserLock(int $userId): ?string
    {
        global $wpdb;

        $name = substr('wpdmpp_payout_' . DB_NAME . '_' . $userId, 0, 64);

        $acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, 10));

        return ((int) $acquired === 1) ? $name : null;
    }

    /**
     * Release a lock taken by acquireUserLock()
     *
     * @param string|null $name Lock name
     */
    private function releaseUserLock(?string $name): void
    {
        if ($name === null) {
            return;
        }

        global $wpdb;

        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
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

        // Serialize the balance check and the insert for this user, so two
        // concurrent requests cannot each be approved against the same funds.
        $lock = $this->acquireUserLock($userId);

        // Validate the request against real, matured earnings. This is the
        // authoritative check — never trust a client-supplied amount.
        $available = $this->getAvailableBalance($userId);

        if ($available <= 0) {
            $this->releaseUserLock($lock);

            return [
                'success' => false,
                'message' => __('You have no matured balance available for withdrawal.', 'wpdm-premium-packages'),
                'withdrawal' => null,
            ];
        }

        if ($amount > $available) {
            $this->releaseUserLock($lock);

            return [
                'success' => false,
                'message' => sprintf(
                    __('Requested amount exceeds your available balance of %s.', 'wpdm-premium-packages'),
                    wpdmpp_price_format($available)
                ),
                'withdrawal' => null,
            ];
        }

        // Create withdrawal request. `status` is an integer column:
        // 0 = pending, 1 = paid.
        $data = [
            'uid' => $userId,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_account' => is_scalar($accounts[$paymentMethod]) ? (string) $accounts[$paymentMethod] : '',
            'status' => 0,
            'date' => time(),
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'ahm_withdraws',
            $data,
            ['%d', '%f', '%s', '%s', '%d', '%d']
        );

        $this->releaseUserLock($lock);

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
