<?php
/**
 * Cheque Payment Gateway
 *
 * Payment gateway for cheque/check payments.
 *
 * @package WPDMPP\Payment\Gateways
 * @since 7.0.0
 */

namespace WPDMPP\Payment\Gateways;

use WPDMPP\Payment\AbstractGateway;
use WPDMPP\Order\OrderService;
use WPDM\__\Session;

defined('ABSPATH') || exit;

class ChequeGateway extends AbstractGateway {

    /**
     * Gateway ID
     *
     * @var string
     */
    protected string $id = 'cheque';

    /**
     * Settings key
     *
     * @var string
     */
    protected string $settingsKey = 'Cheque';

    /**
     * Gateway title
     *
     * @var string
     */
    protected string $title = 'Cheque Payment';

    /**
     * Gateway description
     *
     * @var string
     */
    protected string $description = 'Pay by cheque or bank transfer.';

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
            ? WPDMPP_BASE_URL . 'assets/images/cheque.svg'
            : '';
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool {
        return (bool) get_wpdmpp_option('Cheque/enabled', false);
    }

    /**
     * {@inheritdoc}
     */
    public function renderSettings(): string {
        return $this->formStart()
            . $this->textField(
                'payee_name',
                __('Payee Name', 'wpdm-premium-packages'),
                __('Name to write on the cheque', 'wpdm-premium-packages')
            )
            . $this->textareaField(
                'mailing_address',
                __('Mailing Address', 'wpdm-premium-packages'),
                3,
                __('Address where customers should mail cheques', 'wpdm-premium-packages')
            )
            . $this->textareaField(
                'instructions',
                __('Instructions', 'wpdm-premium-packages'),
                4,
                __('Additional instructions for customers...', 'wpdm-premium-packages')
            )
            . $this->formEnd();
    }

    /**
     * {@inheritdoc}
     */
    public function getCheckoutFields(): string {
        $payeeName = get_wpdmpp_option('Cheque/payee_name', '');
        $mailingAddress = get_wpdmpp_option('Cheque/mailing_address', '');
        $instructions = get_wpdmpp_option('Cheque/instructions', '');

        ob_start();
        ?>
        <div class="wpdmpp-cheque-instructions">
            <?php if ($payeeName): ?>
                <p>
                    <strong><?php esc_html_e('Make cheque payable to:', 'wpdm-premium-packages'); ?></strong>
                    <?php echo esc_html($payeeName); ?>
                </p>
            <?php endif; ?>

            <?php if ($mailingAddress): ?>
                <p>
                    <strong><?php esc_html_e('Mail to:', 'wpdm-premium-packages'); ?></strong><br />
                    <?php echo nl2br(esc_html($mailingAddress)); ?>
                </p>
            <?php endif; ?>

            <?php if ($instructions): ?>
                <div class="wpdmpp-cheque-notes">
                    <?php echo wp_kses_post($instructions); ?>
                </div>
            <?php endif; ?>
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

        try {
            $orderService = OrderService::instance();

            // Update order with payment method (order remains pending until cheque is received)
            $billingInfo = $orderData['billing'] ?? [];
            $orderService->updateOrder([
                'payment_method' => 'Cheque',
                'payment_status' => 'Pending',
                'billing_info' => maybe_serialize($billingInfo),
            ], $orderId);

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

            return $this->successResponse(
                __('Order placed. Please mail your cheque to complete the payment.', 'wpdm-premium-packages'),
                [
                    'order_id' => $orderId,
                    'redirect_url' => wpdm_user_dashboard_url(["udb_page" => "purchases/order/{$orderId}/"]),
                ]
            );

        } catch (\Exception $e) {
            $this->log('Cheque payment processing failed', ['error' => $e->getMessage()], 'error');
            return $this->errorResponse($e->getMessage());
        }
    }
}
