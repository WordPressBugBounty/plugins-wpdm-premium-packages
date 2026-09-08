<?php
/**
 * A rate provider that can answer for a past date.
 *
 * Optional, and deliberately separate from RateProviderInterface: most feeds only
 * publish today's rates, and a provider should not have to pretend otherwise.
 * Callers check for this interface before asking for history.
 *
 * @package WPDMPP\Currency
 * @since   7.2.0
 */

namespace WPDMPP\Currency;

defined('ABSPATH') || exit;

interface HistoricalRateProviderInterface
{
    /**
     * Rates as published on a given date.
     *
     * Providers typically return the closest preceding publication when the date
     * falls on a weekend or holiday, which is the correct behaviour — the rate in
     * force on a Sunday is Friday's.
     *
     * @param string   $base   Base currency code.
     * @param string[] $quotes Currency codes wanted.
     * @param string   $date   Y-m-d.
     *
     * @return array{success: bool, rates: array<string, float>, date: string, error: string}
     */
    public function fetchHistoricalRates(string $base, array $quotes, string $date): array;
}
