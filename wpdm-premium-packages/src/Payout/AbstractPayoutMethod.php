<?php
/**
 * Abstract Payout Method
 *
 * Base class for payout method implementations.
 *
 * @package WPDMPP\Payout
 * @since 7.0.0
 */

namespace WPDMPP\Payout;

defined('ABSPATH') || exit;

abstract class AbstractPayoutMethod implements PayoutMethodInterface
{
    /**
     * Method ID
     *
     * @var string
     */
    protected string $id = '';

    /**
     * Method name
     *
     * @var string
     */
    protected string $name = '';

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
     * Get method ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get method name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get method icon URL
     *
     * @return string
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Get minimum payout amount
     *
     * @return float
     */
    public function getMinimumAmount(): float
    {
        $minimums = get_option('wpdmpp_payout_min_amount', []);

        if (isset($minimums[$this->id]) && is_numeric($minimums[$this->id])) {
            return (float) $minimums[$this->id];
        }

        return $this->defaultMinimum;
    }

    /**
     * Check if method is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        $activeMethods = get_option('wpdmpp_active_pom', []);

        if (!is_array($activeMethods)) {
            return false;
        }

        return in_array($this->id, $activeMethods);
    }

    /**
     * Validate user account data - default implementation
     *
     * @param array $data Account data to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateAccount(array $data): array
    {
        return ['valid' => true, 'errors' => []];
    }

    /**
     * Process a payout - default implementation (manual processing)
     *
     * @param Withdraw $request Withdrawal request
     * @param array    $account User's account info
     * @return array ['success' => bool, 'message' => string, 'transaction_id' => string|null]
     */
    public function processPayout(Withdraw $request, array $account): array
    {
        return [
            'success' => false,
            'message' => __('Manual processing required.', 'wpdm-premium-packages'),
            'transaction_id' => null,
        ];
    }

    /**
     * Get currency code
     *
     * @return string
     */
    protected function getCurrencyCode(): string
    {
        if (function_exists('wpdmpp_currency_code')) {
            return wpdmpp_currency_code();
        }
        return 'USD';
    }

    /**
     * Get site name
     *
     * @return string
     */
    protected function getSiteName(): string
    {
        return get_bloginfo('name');
    }

    /**
     * Convert to array for API response
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'icon' => $this->getIcon(),
            'min' => $this->getMinimumAmount(),
            'active' => $this->isEnabled(),
        ];
    }
}
