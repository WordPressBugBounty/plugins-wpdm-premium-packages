<?php
/**
 * Cash Payment Gateway
 *
 * Simple cash/offline payment gateway.
 *
 * @package WPDMPP\Payment\Gateways
 * @since 7.0.0
 */

namespace WPDMPP\Payment\Gateways;

use WPDMPP\Payment\AbstractGateway;
use WPDMPP\Order\OrderService;
use WPDM\__\Session;

defined('ABSPATH') || exit;

class CashGateway extends AbstractGateway {

    /**
     * Gateway ID
     *
     * @var string
     */
    protected string $id = 'cash';

    /**
     * Settings key
     *
     * @var string
     */
    protected string $settingsKey = 'Cash';

    /**
     * Gateway title
     *
     * @var string
     */
    protected string $title = 'Cash Payment';

    /**
     * Gateway description
     *
     * @var string
     */
    protected string $description = 'Pay with cash on delivery or in-person payment.';

    /**
     * Supported features
     *
     * @var array
     */
    protected array $supports = [];

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->icon = defined('WPDMPP_BASE_URL')
            ? WPDMPP_BASE_URL . 'assets/images/cash.svg'
            : '';
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool {
        return (bool) get_wpdmpp_option('Cash/enabled', false);
    }

    /**
     * {@inheritdoc}
     */
    public function renderSettings(): string {
        return $this->formStart()
            . $this->textareaField(
                'instructions',
                __('Instructions', 'wpdm-premium-packages'),
                4,
                __('Instructions for customers who select cash payment...', 'wpdm-premium-packages'),
                __('This message will be displayed to customers after placing an order.', 'wpdm-premium-packages')
            )
            . $this->checkboxField(
                'auto_complete',
                __('Auto-complete orders', 'wpdm-premium-packages'),
                __('Automatically mark orders as completed. Use with caution.', 'wpdm-premium-packages')
            )
            . $this->formEnd();
    }

    /**
     * {@inheritdoc}
     */
    public function getCheckoutFields(): string {
        $instructions = get_wpdmpp_option('Cash/instructions', '');
        if (empty($instructions)) {
            return '';
        }

        return sprintf(
            '<div class="wpdmpp-cash-instructions">%s</div>',
            wp_kses_post($instructions)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processPayment(array $orderData): array {
        $orderId = $orderData['order_id'] ?? null;

        if (!$orderId) {
            return $this->errorResponse(__('Missing order information.', 'wpdm-premium-packages'));
        }

        try {
            $orderService = OrderService::instance();

            // Update order with payment method
            $billingInfo = $orderData['billing'] ?? [];
            $orderService->updateOrder([
                'payment_method' => 'Cash',
                'billing_info' => maybe_serialize($billingInfo),
            ], $orderId);

            // Auto-complete if enabled
            $autoComplete = get_wpdmpp_option('Cash/auto_complete', 0);
            if ($autoComplete) {
                $orderService->completeOrder($orderId, true, 'Cash');
                do_action('wpdmpp_payment_completed', $orderId);
            }

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

            $message = $autoComplete
                ? __('Order completed successfully.', 'wpdm-premium-packages')
                : __('Order placed successfully. Awaiting payment confirmation.', 'wpdm-premium-packages');

            return $this->successResponse(
                $message,
                [
                    'order_id' => $orderId,
                    'redirect_url' => wpdm_user_dashboard_url(["udb_page" => "purchases/order/{$orderId}/"]),
                ]
            );

        } catch (\Exception $e) {
            $this->log('Cash payment processing failed', ['error' => $e->getMessage()], 'error');
            return $this->errorResponse($e->getMessage());
        }
    }
}
