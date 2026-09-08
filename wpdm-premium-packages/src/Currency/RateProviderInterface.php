<?php
/**
 * Contract for a source of exchange rates.
 *
 * @package WPDMPP\Currency
 * @since   7.2.0
 */

namespace WPDMPP\Currency;

defined('ABSPATH') || exit;

interface RateProviderInterface
{
    /**
     * Identifier used in settings and recorded against every stored rate, so a
     * figure can always be traced back to where it came from.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Human readable name for the settings screen.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Whether this provider is usable right now — configured, keyed, reachable.
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Fetch rates expressed as: 1 unit of $base buys N units of each quote currency.
     *
     * Implementations return only the pairs they can supply. A partial result is
     * expected and fine; a failure must return an error rather than an empty set,
     * so the caller can tell "nothing changed" apart from "the fetch broke" and
     * avoid treating an outage as a legitimately empty rate table.
     *
     * @param string   $base       Base currency code.
     * @param string[] $quotes     Currency codes wanted.
     *
     * @return array{success: bool, rates: array<string, float>, error: string}
     */
    public function fetchRates(string $base, array $quotes): array;
}
