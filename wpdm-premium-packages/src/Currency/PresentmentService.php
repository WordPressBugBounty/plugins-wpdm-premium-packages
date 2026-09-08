<?php
/**
 * Which currency the shopper sees and is charged in.
 *
 * Distinct from the reporting (base) currency: that one is fixed and every report
 * converts to it, whereas this varies per visitor and per request. Keeping the two
 * apart is what lets a shopper see prices in their own currency without the store's
 * accounting moving around underneath it.
 *
 * @package WPDMPP\Currency
 * @since   7.2.0
 */

namespace WPDMPP\Currency;

use WPDM\__\Session;
use WPDMPP\Core\CurrencyService;
use WPDMPP\Payment\PaymentService;

defined('ABSPATH') || exit;

class PresentmentService
{
    /**
     * @var PresentmentService|null
     */
    private static $instance = null;

    /**
     * Session key holding the shopper's choice.
     */
    private const SESSION_KEY = 'wpdmpp_currency';

    /**
     * Resolved for this request; null until first asked.
     *
     * @var string|null
     */
    private ?string $current = null;

    /**
     * @var string[]|null
     */
    private ?array $available = null;

    private function __construct()
    {
    }

    /**
     * @return PresentmentService
     */
    public static function getInstance(): PresentmentService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // =========================================================================
    // CURRENT CURRENCY
    // =========================================================================

    /**
     * The currency this request is priced in.
     *
     * A cart already in progress pins the currency: re-pricing a part-filled cart
     * mid-session would change what the shopper had already agreed to, and the
     * totals they have been looking at would silently move.
     *
     * @return string
     */
    public function getCurrent(): string
    {
        if ($this->current !== null) {
            return $this->current;
        }

        $store = $this->getStoreCurrency();

        // Multi-currency off, or nothing valid to switch to: nothing to resolve.
        if (!$this->isEnabled()) {
            return $this->current = $store;
        }

        $chosen = Session::get(self::SESSION_KEY);
        if (is_string($chosen) && $chosen !== '' && $this->isAvailable($chosen)) {
            return $this->current = strtoupper($chosen);
        }

        return $this->current = $store;
    }

    /**
     * Record the shopper's choice for the rest of their session.
     *
     * @param string $code
     *
     * @return bool True when the currency was accepted.
     */
    public function setCurrent(string $code): bool
    {
        $code = strtoupper(trim($code));

        if (!$this->isAvailable($code)) {
            return false;
        }

        Session::set(self::SESSION_KEY, $code, WEEK_IN_SECONDS);
        $this->current = $code;

        /**
         * Fires when a shopper switches currency.
         *
         * @param string $code
         */
        do_action('wpdmpp_currency_switched', $code);

        return true;
    }

    /**
     * The store's own configured currency — the default and the fallback.
     *
     * Read straight from settings rather than through wpdmpp_currency_code(), which
     * now returns the presentment currency and would recurse.
     *
     * @return string
     */
    public function getStoreCurrency(): string
    {
        $settings = get_option('_wpdmpp_settings');
        $code     = isset($settings['currency']) && $settings['currency'] !== '' ? $settings['currency'] : 'USD';

        return strtoupper($code);
    }

    // =========================================================================
    // AVAILABILITY
    // =========================================================================

    /**
     * Whether shoppers may switch currency at all.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        if (!(int) get_wpdmpp_option('multicurrency_enabled', 0, 'int')) {
            return false;
        }

        // Stale rates mean any converted price is a guess. Rather than quote a
        // figure that may be days out of date, fall back to the store currency
        // until a refresh succeeds.
        if (ExchangeRateService::getInstance()->isStale()) {
            return false;
        }

        return count($this->getAvailable()) > 1;
    }

    /**
     * Currencies a shopper can actually be charged in.
     *
     * Three conditions, all of which must hold. Enabled in settings, because the
     * merchant chose it. A usable exchange rate, because otherwise the price is
     * unknown. And accepted by at least one enabled gateway — offering a currency
     * no gateway takes produces a failure at the last step of checkout, which is
     * the worst possible place to discover it.
     *
     * @return string[]
     */
    public function getAvailable(): array
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $store   = $this->getStoreCurrency();
        $enabled = get_wpdmpp_option('enabled_currencies', []);
        $enabled = is_array($enabled) ? array_map('strtoupper', array_filter($enabled)) : [];

        // The store currency is always on offer; it needs no rate and every gateway
        // that is configured at all can take it.
        $enabled[] = $store;
        $enabled   = array_values(array_unique($enabled));

        $currencyService = CurrencyService::getInstance();
        $rateService     = ExchangeRateService::getInstance();
        $gatewaySupport  = $this->getGatewaySupportedCurrencies();

        $available = [];
        foreach ($enabled as $code) {
            if (!$currencyService->isValidCurrency($code)) {
                continue;
            }

            if ($code !== $store) {
                if ($rateService->getRate($store, $code) === null) {
                    continue;
                }

                if (!empty($gatewaySupport) && !in_array($code, $gatewaySupport, true)) {
                    continue;
                }
            }

            $available[] = $code;
        }

        /**
         * Filter the currencies offered to shoppers.
         *
         * @param string[] $available
         * @param string   $store
         */
        $this->available = array_values(array_unique(apply_filters('wpdmpp_available_currencies', $available, $store)));

        return $this->available;
    }

    /**
     * @param string $code
     *
     * @return bool
     */
    public function isAvailable(string $code): bool
    {
        return in_array(strtoupper(trim($code)), $this->getAvailable(), true);
    }

    /**
     * Union of the currencies every enabled gateway accepts.
     *
     * A union rather than an intersection: a shopper only needs one gateway able to
     * take their currency, and the checkout can offer just that gateway. An empty
     * result means no gateway declared a list, in which case no gating is applied.
     *
     * @return string[]
     */
    public function getGatewaySupportedCurrencies(): array
    {
        if (!class_exists('\WPDMPP\Payment\PaymentService')) {
            return [];
        }

        $currencyService = CurrencyService::getInstance();
        $supported       = [];

        foreach (PaymentService::instance()->getGateways(true) as $id => $gateway) {
            $codes = array_keys($currencyService->getGatewayCurrencies((string) $id));
            if (!empty($codes)) {
                $supported = array_merge($supported, $codes);
            }
        }

        return array_values(array_unique(array_map('strtoupper', $supported)));
    }

    // =========================================================================
    // CART LOCKING
    // =========================================================================





}
