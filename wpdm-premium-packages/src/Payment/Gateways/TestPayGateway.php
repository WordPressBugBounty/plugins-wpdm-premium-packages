<?php
/**
 * TestPay Payment Gateway
 *
 * A test payment gateway for development and testing purposes.
 *
 * @package WPDMPP\Payment\Gateways
 * @since 7.0.0
 */

namespace WPDMPP\Payment\Gateways;

use WPDMPP\Payment\AbstractGateway;
use WPDMPP\Order\OrderService;
use WPDM\__\Session;

defined('ABSPATH') || exit;

class TestPayGateway extends AbstractGateway {

    /**
     * Gateway ID
     *
     * @var string
     */
    protected string $id = 'testpay';

    /**
     * Settings key
     *
     * @var string
     */
    protected string $settingsKey = 'TestPay';

    /**
     * Gateway title
     *
     * @var string
     */
    protected string $title = 'Test Payment';

    /**
     * Gateway description
     *
     * @var string
     */
    protected string $description = 'Test payment gateway for development purposes. Do not use in production.';

    /**
     * Supported features
     *
     * @var array
     */
    protected array $supports = ['recurring', 'refunds'];

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->icon = defined('WPDMPP_BASE_URL')
            ? WPDMPP_BASE_URL . 'assets/images/testpay.svg'
            : '';
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool {
        return (bool) get_wpdmpp_option('TestPay/enabled', false);
    }

    /**
     * {@inheritdoc}
     */
    public function renderSettings(): string {
        return $this->formStart()
            . $this->alert(
                '<strong>' . __('Warning:', 'wpdm-premium-packages') . '</strong> '
                . __('This is a test gateway for development purposes only. It will auto-complete all orders without actual payment. Do NOT enable in production.', 'wpdm-premium-packages'),
                'warning'
            )
            . $this->checkboxField(
                'simulate_failure',
                __('Simulate payment failures', 'wpdm-premium-packages'),
                __('When enabled, all payments will fail. Useful for testing error handling.', 'wpdm-premium-packages')
            )
            . $this->numberField(
                'delay',
                __('Simulated Delay (seconds)', 'wpdm-premium-packages'),
                0,
                10,
                __('Add artificial delay to simulate processing time. Max 10 seconds.', 'wpdm-premium-packages')
            )
            . $this->formEnd();
    }

    /**
     * {@inheritdoc}
     */
    public function getCheckoutFields(): string {
        ob_start();
        ?>
        <div class="wpdmpp-testpay-notice" style="background: #fff3cd; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
            <strong style="color: #856404;">⚠️ <?php esc_html_e('Test Mode', 'wpdm-premium-packages'); ?></strong>
            <p style="color: #856404; margin: 5px 0 0;">
                <?php esc_html_e('This is a test payment. No actual charge will be made.', 'wpdm-premium-packages'); ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * {@inheritdoc}
     */
    public function processPayment(array $orderData): array {
        $orderId = $orderData['order_id'] ?? null;

        if (!$orderId) {
            return $this->errorResponse(__('Missing order information.', 'wpdm-premium-packages'));
        }

        // Simulate delay if configured
        $delay = min((int) get_wpdmpp_option('TestPay/delay', 0), 10);
        if ($delay > 0) {
            sleep($delay);
        }

        // Simulate failure if configured
        $simulateFailure = get_wpdmpp_option('TestPay/simulate_failure', 0);
        if ($simulateFailure) {
            $this->log('Simulated payment failure', ['order_id' => $orderId]);
            return $this->errorResponse(__('Payment failed (simulated failure).', 'wpdm-premium-packages'));
        }

        try {
            $orderService = OrderService::instance();

            // Generate fake transaction ID
            $transactionId = 'TEST_' . strtoupper(wp_generate_password(16, false));

            // Update order with payment method
            $billingInfo = $orderData['billing'] ?? [];
            $orderService->updateOrder([
                'payment_method' => 'TestPay',
                'trans_id' => $transactionId,
                'billing_info' => maybe_serialize($billingInfo),
            ], $orderId);

            // Complete the order
            $orderService->completeOrder($orderId, true, 'TestPay');

            // Set session for guest orders
            Session::set('guest_order_init', uniqid(), 18000);
            Session::set('guest_order', $orderId, 18000);
            if (!empty($billingInfo['order_email'])) {
                Session::set('order_email', $billingInfo['order_email'], 18000);
            }

            // Clear cart
            if (function_exists('wpdmpp_empty_cart')) {
                wpdmpp_empty_cart();
            }

            do_action('wpdmpp_payment_completed', $orderId);

            $this->log('Test payment completed', [
                'order_id' => $orderId,
                'transaction_id' => $transactionId,
            ]);

            return $this->successResponse(
                __('Test payment completed successfully.', 'wpdm-premium-packages'),
                [
                    'order_id' => $orderId,
                    'transaction_id' => $transactionId,
                    'redirect_url' => wpdm_user_dashboard_url(["udb_page" => "purchases/order/{$orderId}/"]),
                ]
            );

        } catch (\Exception $e) {
            $this->log('Test payment processing failed', ['error' => $e->getMessage()], 'error');
            return $this->errorResponse($e->getMessage());
        }
    }
}
