<?php
/**
 * Rates from exchangerate.host.
 *
 * Chosen as the shipped default because it exposes a plain JSON endpoint keyed on
 * a single access token, which suits a plugin installed across many sites: each
 * store brings its own key and its own quota, and nothing is shared or proxied
 * through us.
 *
 * The response is deliberately parsed defensively. A rate feed that silently
 * changes shape, or answers with an HTML error page from a proxy, must surface as
 * a failed refresh rather than as an empty rate table that looks like success.
 *
 * @package WPDMPP\Currency\Providers
 * @since   7.2.0
 */

namespace WPDMPP\Currency\Providers;

use WPDMPP\Currency\RateProviderInterface;

defined('ABSPATH') || exit;

class ExchangeRateHostProvider implements RateProviderInterface
{
    public const ID = 'exchangerate_host';

    private const ENDPOINT = 'https://api.exchangerate.host/live';

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return self::ID;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return __('exchangerate.host', 'wpdm-premium-packages');
    }

    /**
     * @inheritDoc
     */
    public function isConfigured(): bool
    {
        return $this->getAccessKey() !== '';
    }

    /**
     * @return string
     */
    private function getAccessKey(): string
    {
        return trim((string) get_wpdmpp_option('rate_provider_key', ''));
    }

    /**
     * @inheritDoc
     */
    public function fetchRates(string $base, array $quotes): array
    {
        $fail = static fn(string $msg): array => ['success' => false, 'rates' => [], 'error' => $msg];

        $key = $this->getAccessKey();
        if ($key === '') {
            return $fail(__('No API key has been entered for the rate provider.', 'wpdm-premium-packages'));
        }

        $base   = strtoupper($base);
        $quotes = array_values(array_unique(array_map('strtoupper', $quotes)));

        if (empty($quotes)) {
            return ['success' => true, 'rates' => [], 'error' => ''];
        }

        $url = add_query_arg(
            [
                'access_key' => $key,
                'source'     => $base,
                'currencies' => implode(',', $quotes),
            ],
            self::ENDPOINT
        );

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return $fail($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return $fail(sprintf(
                /* translators: %d: HTTP status code */
                __('The rate provider returned HTTP %d.', 'wpdm-premium-packages'),
                $code
            ));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body)) {
            return $fail(__('The rate provider returned a response that could not be read.', 'wpdm-premium-packages'));
        }

        // The API reports its own failures with success=false and an error object,
        // at HTTP 200, so the status code alone is not enough to trust the payload.
        if (isset($body['success']) && ! $body['success']) {
            $info = $body['error']['info'] ?? $body['error']['type'] ?? '';

            return $fail($info !== ''
                ? (string) $info
                : __('The rate provider rejected the request.', 'wpdm-premium-packages'));
        }

        $quoted = $body['quotes'] ?? $body['rates'] ?? null;
        if (!is_array($quoted) || empty($quoted)) {
            return $fail(__('The rate provider returned no rates.', 'wpdm-premium-packages'));
        }

        $rates = [];
        foreach ($quoted as $pair => $rate) {
            if (!is_numeric($rate) || (float) $rate <= 0) {
                continue;
            }

            // Keys arrive as the concatenated pair, "USDEUR", or occasionally as the
            // bare quote code. Both are accepted; anything else is skipped rather
            // than guessed at.
            $pair  = strtoupper((string) $pair);
            $quote = strpos($pair, $base) === 0 ? substr($pair, strlen($base)) : $pair;

            if (in_array($quote, $quotes, true)) {
                $rates[$quote] = (float) $rate;
            }
        }

        if (empty($rates)) {
            return $fail(__('None of the requested currencies were returned by the rate provider.', 'wpdm-premium-packages'));
        }

        return ['success' => true, 'rates' => $rates, 'error' => ''];
    }
}
