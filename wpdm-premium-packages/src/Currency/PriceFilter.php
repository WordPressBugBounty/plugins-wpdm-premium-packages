<?php
/**
 * Presents stored prices in the shopper's currency.
 *
 * Prices are read straight from post meta in a dozen places — templates, the cart,
 * the REST endpoints, add-on code we do not control. Converting at each of those
 * would be endless and would eventually diverge, and the divergence that matters
 * most is between what a shopper is shown and what the cart charges them.
 *
 * So the conversion happens at the source: the meta read itself. Every consumer
 * then sees the same figure without knowing currencies exist.
 *
 * Every price on a package is converted the same way - base price, sale price,
 * licence tiers and extra gig options - so they stay in proportion to one
 * another and a total built from them is coherent.
 *
 * Deliberately front-end only. The package editor reads the same keys, and
 * converting there would show a converted number in the price field and save it
 * back as the new base price, quietly re-pricing the product on every save.
 *
 * @package WPDMPP\Currency
 * @since   7.2.0
 */

namespace WPDMPP\Currency;

defined('ABSPATH') || exit;

class PriceFilter
{
    /**
     * @var PriceFilter|null
     */
    private static $instance = null;

    /**
     * Guards against recursing when the filter reads the very key it filters.
     *
     * @var bool
     */
    private bool $filtering = false;

    /**
     * Lets callers read the untouched stored value.
     *
     * @var bool
     */
    private bool $suspended = false;

    /**
     * Meta keys holding a single money amount in the store currency.
     */
    private const AMOUNT_KEYS = ['__wpdm_base_price', '__wpdm_sales_price'];

    private function __construct()
    {
    }

    /**
     * @return PriceFilter
     */
    public static function getInstance(): PriceFilter
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return void
     */
    public function register(): void
    {
        if (is_admin()) {
            return;
        }

        add_filter('get_post_metadata', [$this, 'filterMeta'], 10, 4);
    }

    /**
     * Read a stored price without conversion.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public function withoutConversion(callable $callback)
    {
        $previous        = $this->suspended;
        $this->suspended = true;

        try {
            return $callback();
        } finally {
            $this->suspended = $previous;
        }
    }

    /**
     * Convert a price meta value into the presentment currency.
     *
     * @param mixed  $value    Null unless another filter already answered.
     * @param int    $objectId
     * @param string $metaKey
     * @param bool   $single
     *
     * @return mixed Null to let WordPress read normally.
     */
    public function filterMeta($value, $objectId, $metaKey, $single)
    {
        if ($value !== null || $this->filtering || $this->suspended) {
            return $value;
        }

        if ($metaKey !== '__wpdm_license'
            && $metaKey !== '__wpdm_variation'
            && !in_array($metaKey, self::AMOUNT_KEYS, true)) {
            return $value;
        }

        $presentment = PresentmentService::getInstance();

        // Same currency, or the feature is off: nothing to do, and skipping here
        // keeps a single-currency store on exactly its old code path.
        if (!$presentment->isEnabled()) {
            return $value;
        }

        $target = $presentment->getCurrent();
        if ($target === $presentment->getStoreCurrency()) {
            return $value;
        }

        $this->filtering = true;

        try {
            $raw = get_post_meta((int) $objectId, $metaKey, true);
        } finally {
            $this->filtering = false;
        }

        if ($metaKey === '__wpdm_license') {
            $converted = $this->convertLicenses($raw, $target);
        } elseif ($metaKey === '__wpdm_variation') {
            $converted = $this->convertVariations($raw, $target);
        } else {
            $converted = $this->convertAmount($raw, $target);
        }

        if ($converted === null) {
            return $value;
        }

        // get_post_meta() with $single unwraps the first element, so a single read
        // has to be handed back wrapped or the caller receives the wrong shape.
        return $single ? [$converted] : [$converted];
    }

    /**
     * @param mixed  $raw
     * @param string $target
     *
     * @return float|null
     */
    private function convertAmount($raw, string $target): ?float
    {
        if ($raw === '' || $raw === null || !is_numeric($raw)) {
            return null;
        }

        // A sale price of zero means "no sale", not "free"; converting it would
        // still be zero but the extra work is pointless.
        if ((float) $raw <= 0) {
            return null;
        }

        return $this->present((float) $raw, $target);
    }

    /**
     * @param mixed  $raw
     * @param string $target
     *
     * @return array|null
     */
    private function convertLicenses($raw, string $target): ?array
    {
        $licenses = maybe_unserialize($raw);

        if (!is_array($licenses)) {
            return null;
        }

        foreach ($licenses as $id => &$license) {
            if (!is_array($license)) {
                continue;
            }

            foreach (['price', 'sale_price'] as $field) {
                if (isset($license[$field]) && is_numeric($license[$field]) && (float) $license[$field] > 0) {
                    $license[$field] = $this->present((float) $license[$field], $target);
                }
            }
        }
        unset($license);

        return $licenses;
    }

    /**
     * Convert the option prices of every extra gig group.
     *
     * Gigs are add-ons priced in the store currency, so leaving them unconverted
     * would let a shopper add a 10 euro option to a cart priced in dollars and be
     * charged an amount neither figure explains.
     *
     * @param mixed  $raw
     * @param string $target
     *
     * @return array|null
     */
    private function convertVariations($raw, string $target): ?array
    {
        $groups = maybe_unserialize($raw);

        if (!is_array($groups)) {
            return null;
        }

        foreach ($groups as &$group) {
            if (!is_array($group)) {
                continue;
            }

            foreach ($group as &$option) {
                // Groups carry their own scalar keys - vname, multiple - alongside
                // the numbered option arrays; only the latter hold a price.
                if (!is_array($option) || !isset($option['option_price'])) {
                    continue;
                }

                if (is_numeric($option['option_price']) && (float) $option['option_price'] != 0.0) {
                    $option['option_price'] = $this->present((float) $option['option_price'], $target);
                }
            }
            unset($option);
        }
        unset($group);

        return $groups;
    }

    /**
     * @param float  $amount
     * @param string $target
     *
     * @return float
     */
    private function present(float $amount, string $target): float
    {
        return function_exists('wpdmpp_present_price')
            ? (float) wpdmpp_present_price($amount, 0, $target)
            : $amount;
    }
}
