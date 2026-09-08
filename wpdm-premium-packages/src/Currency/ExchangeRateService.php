<?php
/**
 * Exchange rate storage, lookup and freshness.
 *
 * Rates are kept as dated snapshots in wpdmpp_rates. A refresh appends rows; it
 * never updates them. That costs a little storage and buys two things: the rate
 * that applied on any past day can be recovered, and a bad fetch can be traced
 * and discarded without having destroyed what it replaced.
 *
 * @package WPDMPP\Currency
 * @since   7.2.0
 */

namespace WPDMPP\Currency;


defined('ABSPATH') || exit;

class ExchangeRateService
{
    /**
     * @var ExchangeRateService|null
     */
    private static $instance = null;

    /**
     * Resolved rates for this request, keyed BASE_QUOTE.
     *
     * @var array<string, float>
     */
    private array $cache = [];

    /**
     * @var RateProviderInterface[]|null
     */
    private ?array $providers = null;

    /**
     * Default age, in hours, past which rates are considered stale.
     */
    public const DEFAULT_MAX_AGE_HOURS = 36;

    /**
     * Hours between refreshes when nothing is configured.
     *
     * Twelve because the default provider republishes once a day from central bank
     * data: asking twice a day catches the new publication promptly without
     * spending requests on numbers that have not changed.
     */
    public const DEFAULT_REFRESH_INTERVAL_HOURS = 12;

    /**
     * Bounds for the configured interval. The lower one keeps a misconfigured site
     * from hammering a provider on every cron tick; the upper is a week, past which
     * the rates would be stale under any reasonable max-age setting.
     */
    public const MIN_REFRESH_INTERVAL_HOURS = 1;
    public const MAX_REFRESH_INTERVAL_HOURS = 168;

    private function __construct()
    {
    }

    /**
     * @return ExchangeRateService
     */
    public static function getInstance(): ExchangeRateService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // =========================================================================
    // PROVIDERS
    // =========================================================================

    /**
     * Every registered provider, keyed by id.
     *
     * @return RateProviderInterface[]
     */
    public function getProviders(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $providers = [
        ];

        /**
         * Register additional rate providers.
         *
         * @param RateProviderInterface[] $providers Keyed by provider id.
         */
        $providers = apply_filters('wpdmpp_rate_providers', $providers);

        $this->providers = array_filter(
            $providers,
            static fn($p) => $p instanceof RateProviderInterface
        );

        return $this->providers;
    }

    /**
     * The provider selected in settings, if it is registered and usable.
     *
     * @return RateProviderInterface|null
     */
    public function getActiveProvider(): ?RateProviderInterface
    {
        // Frankfurter is the default because it works with no configuration at all.
        $default   = \WPDMPP\Currency\Providers\FrankfurterProvider::ID;
        $id        = (string) get_wpdmpp_option('rate_provider', $default);
        $providers = $this->getProviders();

        return $providers[$id] ?? null;
    }

    // =========================================================================
    // LOOKUP
    // =========================================================================

    /**
     * Rate to convert 1 unit of $from into $to.
     *
     * Resolution order is deliberate: same-currency is free, a stored
     * rate beats a fetched one because a human overrode it on purpose, then the
     * newest stored snapshot, then the inverse of the opposite pair. Returns null
     * when nothing is known — callers must treat that as "cannot convert" and not
     * silently substitute 1.0, which would quietly misprice the order.
     *
     * @param string $from
     * @param string $to
     *
     * @return float|null
     */
    public function getRate(string $from, string $to): ?float
    {
        $from = strtoupper(trim($from));
        $to   = strtoupper(trim($to));

        if ($from === '' || $to === '') {
            return null;
        }

        if ($from === $to) {
            return 1.0;
        }

        $key = $from . '_' . $to;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $rate = $this->resolveRate($from, $to);

        /**
         * Filter a resolved exchange rate.
         *
         * @param float|null $rate
         * @param string     $from
         * @param string     $to
         */
        $rate = apply_filters('wpdmpp_exchange_rate', $rate, $from, $to);

        $this->cache[$key] = is_numeric($rate) && (float) $rate > 0 ? (float) $rate : null;

        return $this->cache[$key];
    }

    /**
     * @param string $from
     * @param string $to
     *
     * @return float|null
     */
    private function resolveRate(string $from, string $to): ?float
    {
        $stored = $this->getStoredRate($from, $to);
        if ($stored !== null) {
            return $stored;
        }

        // Only one direction of a pair is usually fetched; the other is its inverse.
        $inverse = $this->getStoredRate($to, $from);
        if ($inverse !== null && $inverse > 0) {
            return 1 / $inverse;
        }

        return null;
    }

    /**
     * Newest stored rate for a pair.
     *
     * @param string   $from
     * @param string   $to
     * @param int|null $asOf Newest snapshot at or before this timestamp; null for latest.
     *
     * @return float|null
     */
    public function getStoredRate(string $from, string $to, ?int $asOf = null): ?float
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wpdmpp_rates';
        $sql   = "SELECT rate FROM {$table} WHERE base_currency = %s AND quote_currency = %s";
        $args  = [strtoupper($from), strtoupper($to)];

        if ($asOf !== null) {
            $sql   .= ' AND fetched_at <= %d';
            $args[] = $asOf;
        }

        $sql .= ' ORDER BY fetched_at DESC, id DESC LIMIT 1';

        $rate = $wpdb->get_var($wpdb->prepare($sql, $args));

        return $rate !== null && (float) $rate > 0 ? (float) $rate : null;
    }

    /**
     * Convert an amount, or null when no rate is available.
     *
     * @param float  $amount
     * @param string $from
     * @param string $to
     *
     * @return float|null
     */
    public function convert(float $amount, string $from, string $to): ?float
    {
        $rate = $this->getRate($from, $to);

        return $rate === null ? null : $amount * $rate;
    }

    // =========================================================================
    // STORAGE
    // =========================================================================

    /**
     * Append a snapshot for one pair.
     *
     * @param string $base
     * @param string $quote
     * @param float  $rate
     * @param string $provider
     * @param int    $fetchedAt
     *
     * @return bool
     */
    public function storeRate(string $base, string $quote, float $rate, string $provider = '', int $fetchedAt = 0): bool
    {
        global $wpdb;

        $base  = strtoupper(trim($base));
        $quote = strtoupper(trim($quote));

        if ($base === '' || $quote === '' || $rate <= 0) {
            return false;
        }

        $this->cache = [];

        return (bool) $wpdb->insert(
            $wpdb->prefix . 'wpdmpp_rates',
            [
                'base_currency'  => $base,
                'quote_currency' => $quote,
                'rate'           => $rate,
                'provider'       => $provider,
                'fetched_at'     => $fetchedAt > 0 ? $fetchedAt : time(),
            ],
            ['%s', '%s', '%f', '%s', '%d']
        );
    }

    /**
     * Store a whole set of quotes against one base in a single snapshot.
     *
     * @param string               $base
     * @param array<string, float> $rates
     * @param string               $provider
     *
     * @return int Number of pairs stored.
     */
    public function storeRates(string $base, array $rates, string $provider = ''): int
    {
        $now    = time();
        $stored = 0;

        foreach ($rates as $quote => $rate) {
            if ($this->storeRate($base, (string) $quote, (float) $rate, $provider, $now)) {
                $stored++;
            }
        }

        if ($stored > 0) {
            update_option('__wpdmpp_rates_updated', $now, false);

            /**
             * Fires after a rate refresh has been stored.
             *
             * @param string $base
             * @param array  $rates
             * @param string $provider
             */
            do_action('wpdmpp_rates_updated', $base, $rates, $provider);
        }

        return $stored;
    }

    // =========================================================================
    // FRESHNESS
    // =========================================================================

    /**
     * When rates were last successfully refreshed. Zero if never.
     *
     * @return int
     */
    public function getLastUpdated(): int
    {
        $updated = (int) get_option('__wpdmpp_rates_updated', 0);

        if ($updated > 0) {
            return $updated;
        }

        // Rates can be written outside a refresh — a historical restatement stores
        // per-date snapshots, for instance — so fall back to the newest row rather
        // than reporting "never" while the table plainly holds rates.
        global $wpdb;

        return (int) $wpdb->get_var("SELECT MAX(fetched_at) FROM {$wpdb->prefix}wpdmpp_rates");
    }

    /**
     * How long to leave between refreshes.
     *
     * The job itself wakes more often than this; this is the gate that decides
     * whether a wake-up actually calls the provider.
     *
     * @return int Hours.
     */
    public function getRefreshIntervalHours(): int
    {
        $hours = (int) get_wpdmpp_option(
            'rate_refresh_interval_hours',
            self::DEFAULT_REFRESH_INTERVAL_HOURS,
            'int'
        );

        if ($hours < self::MIN_REFRESH_INTERVAL_HOURS) {
            return self::DEFAULT_REFRESH_INTERVAL_HOURS;
        }

        return min($hours, self::MAX_REFRESH_INTERVAL_HOURS);
    }

    /**
     * Configured staleness threshold, in hours.
     *
     * @return int
     */
    public function getMaxAgeHours(): int
    {
        $hours = (int) get_wpdmpp_option('rate_max_age_hours', self::DEFAULT_MAX_AGE_HOURS, 'int');

        return $hours > 0 ? $hours : self::DEFAULT_MAX_AGE_HOURS;
    }

    /**
     * Whether the stored rates are too old to price against.
     *
     * A store with only one currency is never stale — it has nothing to convert,
     * so an empty rate table is the correct state rather than a fault.
     *
     * @return bool
     */
    public function isStale(): bool
    {
        if (!$this->hasAnyRates()) {
            return $this->requiresRates();
        }

        $updated = $this->getLastUpdated();
        if ($updated <= 0) {
            return true;
        }

        return (time() - $updated) > ($this->getMaxAgeHours() * HOUR_IN_SECONDS);
    }

    /**
     * Whether this store actually needs rates: more than one currency in play.
     *
     * @return bool
     */
    public function requiresRates(): bool
    {
        $enabled = get_wpdmpp_option('enabled_currencies', []);
        $enabled = is_array($enabled) ? array_filter($enabled) : [];

        return count($enabled) > 1;
    }

    /**
     * @return bool
     */
    public function hasAnyRates(): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wpdmpp_rates") > 0;
    }

    /**
     * Age of the rate table in a form suited to an admin screen.
     *
     * @return array{updated: int, age_human: string, stale: bool, provider: string, pairs: int}
     */
    public function getHealth(): array
    {
        global $wpdb;

        $updated = $this->getLastUpdated();
        $table   = $wpdb->prefix . 'wpdmpp_rates';

        return [
            'updated'   => $updated,
            'age_human' => $updated > 0
                ? sprintf(
                    /* translators: %s: human readable time difference */
                    __('%s ago', 'wpdm-premium-packages'),
                    human_time_diff($updated, time())
                )
                : __('never', 'wpdm-premium-packages'),
            'stale'     => $this->isStale(),
            'provider'  => (string) $wpdb->get_var("SELECT provider FROM {$table} ORDER BY fetched_at DESC, id DESC LIMIT 1"),
            'pairs'     => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM (
                    SELECT base_currency, quote_currency FROM {$table} GROUP BY base_currency, quote_currency
                 ) AS pairs"
            ),
        ];
    }

    /**
     * Reporting currencies stamped on existing orders that are not the configured one.
     *
     * Base totals are denominated in whatever the reporting currency was when the
     * order was written, and changing the setting afterwards does not restamp them.
     * The figures stay correct but the label stops matching, so a total in one
     * currency ends up displayed with another's symbol. Detected rather than
     * silently corrected, because only the operator knows which is intended.
     *
     * @return array<string, int> Currency code => order count, excluding the configured one.
     */
    public function getMismatchedBaseCurrencies(): array
    {
        global $wpdb;

        $configured = function_exists('wpdmpp_base_currency_code') ? wpdmpp_base_currency_code() : '';

        if ($configured === '') {
            return [];
        }

        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT base_currency, COUNT(*) AS n
               FROM {$wpdb->prefix}ahm_orders
              WHERE base_currency <> '' AND base_currency <> %s
           GROUP BY base_currency",
            $configured
        ));

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->base_currency] = (int) $row->n;
        }

        return $out;
    }

    /**
     * The newest snapshot for every pair, for display.
     *
     * @return array<int, object>
     */
    public function getLatestRates(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wpdmpp_rates';

        return (array) $wpdb->get_results(
            "SELECT r.base_currency, r.quote_currency, r.rate, r.provider, r.fetched_at
               FROM {$table} r
               INNER JOIN (
                    SELECT base_currency, quote_currency, MAX(fetched_at) AS newest
                      FROM {$table}
                  GROUP BY base_currency, quote_currency
               ) latest
                  ON latest.base_currency = r.base_currency
                 AND latest.quote_currency = r.quote_currency
                 AND latest.newest = r.fetched_at
           ORDER BY r.base_currency, r.quote_currency"
        );
    }

    /**
     * Discard snapshots older than the retention window, keeping the newest row for
     * every pair regardless of age so a lookup never falls off the end.
     *
     * @param int $days
     *
     * @return int Rows removed.
     */
    public function prune(int $days = 400): int
    {
        global $wpdb;

        $table  = $wpdb->prefix . 'wpdmpp_rates';
        $cutoff = time() - ($days * DAY_IN_SECONDS);

        return (int) $wpdb->query($wpdb->prepare(
            "DELETE r FROM {$table} r
               INNER JOIN (
                    SELECT base_currency, quote_currency, MAX(fetched_at) AS newest
                      FROM {$table}
                  GROUP BY base_currency, quote_currency
               ) latest
                  ON latest.base_currency = r.base_currency
                 AND latest.quote_currency = r.quote_currency
              WHERE r.fetched_at < %d
                AND r.fetched_at < latest.newest",
            $cutoff
        ));
    }

    // =========================================================================
    // REFRESH
    // =========================================================================

    /**
     * Ask the active provider for fresh rates and store what comes back.
     *
     * @return array{success: bool, stored: int, message: string}
     */
    public function refresh(): array
    {
        return $this->recordOutcome($this->attemptRefresh());
    }

    /**
     * Record the outcome of a refresh so admin reflects the latest attempt.
     *
     * Previously only the scheduled job cleared this, so a failure recorded once
     * outlived its cause: refreshing from the settings button would succeed while
     * the notice went on reporting an error from a provider that might no longer
     * even be selected.
     *
     * @param array $result
     *
     * @return array
     */
    private function recordOutcome(array $result): array
    {
        if (empty($result['success'])) {
            update_option('__wpdmpp_rates_last_error', $result['message'], false);
        } else {
            delete_option('__wpdmpp_rates_last_error');
        }

        return $result;
    }

    /**
     * Fetch and store a snapshot, reporting what happened.
     *
     * @return array{success: bool, stored: int, message: string}
     */
    private function attemptRefresh(): array
    {
        $provider = $this->getActiveProvider();

        if ($provider === null) {
            return ['success' => false, 'stored' => 0, 'message' => __('No rate provider is selected.', 'wpdm-premium-packages')];
        }

        if (!$provider->isConfigured()) {
            return [
                'success' => false,
                'stored'  => 0,
                'message' => sprintf(
                    /* translators: %s: provider name */
                    __('%s is not configured yet.', 'wpdm-premium-packages'),
                    $provider->getTitle()
                ),
            ];
        }

        $base   = function_exists('wpdmpp_base_currency_code') ? wpdmpp_base_currency_code() : 'USD';
        $quotes = $this->getQuoteCurrencies($base);

        if (empty($quotes)) {
            return ['success' => true, 'stored' => 0, 'message' => __('Only one currency is in use, so there is nothing to convert.', 'wpdm-premium-packages')];
        }

        $result = $provider->fetchRates($base, $quotes);

        if (empty($result['success'])) {
            return [
                'success' => false,
                'stored'  => 0,
                'message' => $result['error'] !== '' ? $result['error'] : __('The rate provider did not respond.', 'wpdm-premium-packages'),
            ];
        }

        $stored = $this->storeRates($base, $result['rates'] ?? [], $provider->getId());

        return [
            'success' => true,
            'stored'  => $stored,
            'message' => sprintf(
                /* translators: 1: number of rates, 2: provider name */
                _n('Stored %1$d rate from %2$s.', 'Stored %1$d rates from %2$s.', $stored, 'wpdm-premium-packages'),
                $stored,
                $provider->getTitle()
            ),
        ];
    }

    /**
     * Restate legacy orders using the rate that applied on the day each was placed.
     *
     * Materially better than one blanket rate: an order from two years ago is
     * valued as it actually was, not as it would be today. Requires a provider that
     * can answer for a past date; callers fall back to restateLegacyOrders() when
     * none is available.
     *
     * Rates are fetched once per distinct order date rather than once per order,
     * since many orders share a day, and are stored as dated snapshots so the work
     * is not repeated on a second run.
     *
     * @param string|null $currency Limit to one currency, or null for all.
     * @param int         $maxDates Safety cap on how many days to fetch in one pass.
     *
     * @return array{updated: int, dates: int, skipped: int, error: string}
     */
    public function restateLegacyOrdersHistorically(?string $currency = null, int $maxDates = 60): array
    {
        global $wpdb;

        $provider = $this->getActiveProvider();

        if (!$provider instanceof HistoricalRateProviderInterface) {
            return [
                'updated' => 0,
                'dates'   => 0,
                'skipped' => 0,
                'error'   => __('The selected rate provider cannot supply rates for past dates.', 'wpdm-premium-packages'),
            ];
        }

        $base   = function_exists('wpdmpp_base_currency_code') ? wpdmpp_base_currency_code() : 'USD';
        $orders = $wpdb->prefix . 'ahm_orders';
        $items  = $wpdb->prefix . 'ahm_order_items';

        $sql  = "SELECT order_id, currency_code, total, `date`
                   FROM {$orders}
                  WHERE currency_code <> '' AND currency_code <> %s AND exchange_rate = 1";
        $args = [$base];

        if ($currency !== null) {
            $sql   .= ' AND currency_code = %s';
            $args[] = strtoupper($currency);
        }

        $rows = (array) $wpdb->get_results($wpdb->prepare($sql . ' ORDER BY `date` ASC', $args));

        if (empty($rows)) {
            return ['updated' => 0, 'dates' => 0, 'skipped' => 0, 'error' => ''];
        }

        // Group by the day an order was placed, so one fetch serves every order
        // that shares it.
        $byDate = [];
        foreach ($rows as $row) {
            $day = gmdate('Y-m-d', (int) $row->date);
            $byDate[$day][] = $row;
        }

        $updated = 0;
        $skipped = 0;
        $seen    = 0;

        foreach ($byDate as $day => $dayRows) {
            if ($seen >= $maxDates) {
                $skipped += count($dayRows);
                continue;
            }
            $seen++;

            $quotes = array_values(array_unique(array_map(
                static fn($r) => strtoupper($r->currency_code),
                $dayRows
            )));

            // Reuse an already-stored snapshot for that day before going out again.
            $needed = [];
            foreach ($quotes as $q) {
                if ($this->getStoredRateOn($base, $q, $day) === null) {
                    $needed[] = $q;
                }
            }

            if (!empty($needed)) {
                $result = $provider->fetchHistoricalRates($base, $needed, $day);

                if (!empty($result['success']) && !empty($result['rates'])) {
                    $stamp = strtotime(($result['date'] ?: $day) . ' 12:00:00') ?: strtotime($day . ' 12:00:00');
                    foreach ($result['rates'] as $q => $r) {
                        $this->storeRate($base, (string) $q, (float) $r, $provider->getId(), (int) $stamp);
                    }
                }
            }

            foreach ($dayRows as $row) {
                $quote = strtoupper($row->currency_code);
                $rate  = $this->getStoredRateOn($base, $quote, $day);

                // base->quote is what the provider publishes; converting an order
                // priced in the quote currency needs the other direction.
                if ($rate === null || $rate <= 0) {
                    $skipped++;
                    continue;
                }

                $toBase = 1 / $rate;

                $wpdb->query($wpdb->prepare(
                    "UPDATE {$orders}
                        SET exchange_rate = %f, base_currency = %s, base_total = ROUND(total * %f, 4)
                      WHERE order_id = %s",
                    $toBase, $base, $toBase, $row->order_id
                ));

                $wpdb->query($wpdb->prepare(
                    "UPDATE {$items}
                        SET base_price = ROUND(price * %f, 4),
                            base_site_commission = ROUND(site_commission * %f, 4)
                      WHERE oid = %s",
                    $toBase, $toBase, $row->order_id
                ));

                $updated++;
            }
        }

        if ($updated > 0) {
            do_action('wpdmpp_legacy_orders_restated_historically', $updated, $seen);
        }

        return ['updated' => $updated, 'dates' => $seen, 'skipped' => $skipped, 'error' => ''];
    }

    /**
     * The rate published closest before a given day, within a short window.
     *
     * The window stops a lookup silently reaching back months when a day has no
     * snapshot; better to report nothing than to value an order from a rate taken
     * at a completely different time.
     *
     * @param string $base
     * @param string $quote
     * @param string $day   Y-m-d.
     *
     * @return float|null
     */
    public function getStoredRateOn(string $base, string $quote, string $day): ?float
    {
        $end   = strtotime($day . ' 23:59:59');
        if ($end === false) {
            return null;
        }

        $rate = $this->getStoredRate($base, $quote, $end);
        if ($rate === null) {
            return null;
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'wpdmpp_rates';
        $fetched = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT fetched_at FROM {$table}
              WHERE base_currency = %s AND quote_currency = %s AND fetched_at <= %d
           ORDER BY fetched_at DESC, id DESC LIMIT 1",
            strtoupper($base), strtoupper($quote), $end
        ));

        // Markets close for weekends and holidays, so a few days' tolerance is
        // normal; a month's gap is not.
        return ($end - $fetched) <= (10 * DAY_IN_SECONDS) ? $rate : null;
    }

    /**
     * Re-state orders that were migrated at a rate of 1.0 now that a real rate for
     * their currency is known.
     *
     * This is the one place history is allowed to change, and only because the
     * previous figure was a placeholder rather than a captured rate: orders that
     * predate rate tracking were left at 1.0 and flagged. Once a rate is known,
     * restating them is strictly more truthful than leaving a known-wrong number in
     * the reports.
     *
     * Orders whose rate was captured at checkout are never touched - they are
     * identified by having a rate other than exactly 1.0, or by being in the base
     * currency where 1.0 is genuinely correct.
     *
     * @param string|null $currency Limit to one currency code, or null for all.
     *
     * @return array{updated: int, skipped: int, currencies: array<string, float>}
     */
    public function restateLegacyOrders(?string $currency = null): array
    {
        global $wpdb;

        $base    = function_exists('wpdmpp_base_currency_code') ? wpdmpp_base_currency_code() : 'USD';
        $orders  = $wpdb->prefix . 'ahm_orders';
        $items   = $wpdb->prefix . 'ahm_order_items';

        $sql  = "SELECT DISTINCT currency_code FROM {$orders}
                  WHERE currency_code <> '' AND currency_code <> %s AND exchange_rate = 1";
        $args = [$base];

        if ($currency !== null) {
            $sql   .= ' AND currency_code = %s';
            $args[] = strtoupper($currency);
        }

        $codes   = (array) $wpdb->get_col($wpdb->prepare($sql, $args));
        $updated = 0;
        $skipped = 0;
        $applied = [];

        foreach ($codes as $code) {
            $rate = $this->getRate($code, $base);

            // No rate for this currency yet: leave the orders flagged rather than
            // guessing, which is the whole point of the warning in admin.
            if ($rate === null || $rate <= 0) {
                $skipped++;
                continue;
            }

            $wpdb->query($wpdb->prepare(
                "UPDATE {$orders}
                    SET exchange_rate = %f,
                        base_currency = %s,
                        base_total = ROUND(total * %f, 4)
                  WHERE currency_code = %s AND exchange_rate = 1",
                $rate, $base, $rate, $code
            ));
            $affected = (int) $wpdb->rows_affected;

            $wpdb->query($wpdb->prepare(
                "UPDATE {$items} i
                    INNER JOIN {$orders} o ON o.order_id = i.oid
                    SET i.base_price = ROUND(i.price * %f, 4),
                        i.base_site_commission = ROUND(i.site_commission * %f, 4)
                  WHERE o.currency_code = %s AND o.exchange_rate = %f",
                $rate, $rate, $code, $rate
            ));

            $updated       += $affected;
            $applied[$code] = $rate;
        }

        if ($updated > 0) {
            do_action('wpdmpp_legacy_orders_restated', $updated, $applied);
        }

        return ['updated' => $updated, 'skipped' => $skipped, 'currencies' => $applied];
    }

    /**
     * Currencies that need a rate against the base: everything enabled for the
     * storefront, plus any currency already recorded against an existing order so
     * historical reporting can be corrected.
     *
     * @param string $base
     *
     * @return string[]
     */
    public function getQuoteCurrencies(string $base): array
    {
        global $wpdb;

        $enabled = get_wpdmpp_option('enabled_currencies', []);
        $enabled = is_array($enabled) ? $enabled : [];

        $historical = (array) $wpdb->get_col(
            "SELECT DISTINCT currency_code FROM {$wpdb->prefix}ahm_orders WHERE currency_code <> ''"
        );

        $codes = array_unique(array_map('strtoupper', array_merge($enabled, $historical)));
        $codes = array_diff($codes, [strtoupper($base)]);

        // Anything not a real ISO code — the "Credits" pseudo-currency seen in the
        // wild, for instance — has no rate and must not be sent to a provider.
        $currencyService = \WPDMPP\Core\CurrencyService::getInstance();
        $codes = array_filter($codes, static fn($c) => $currencyService->isValidCurrency($c));

        return array_values($codes);
    }
}
