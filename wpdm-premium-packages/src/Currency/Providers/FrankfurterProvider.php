<?php
/**
 * Rates from Frankfurter.
 *
 * The shipped default, because it needs no API key and imposes no quota: a plugin
 * installed across thousands of sites cannot ask every one of them to register for
 * a feed before prices will display. Data comes from central bank publications and
 * is republished once a day, which is why the refresh job runs daily rather than
 * hourly — asking more often returns the same numbers.
 *
 * Uses the v2 endpoint for its 165 currencies. The older v1 covers only about 30,
 * which would silently exclude most of the world; v2 has no filter parameter, so
 * the full table is fetched and narrowed here. One request covers every pair.
 *
 * @package WPDMPP\Currency\Providers
 * @since   7.2.0
 */

namespace WPDMPP\Currency\Providers;

use WPDMPP\Currency\HistoricalRateProviderInterface;
use WPDMPP\Currency\RateProviderInterface;

defined('ABSPATH') || exit;

class FrankfurterProvider implements RateProviderInterface, HistoricalRateProviderInterface
{
    public const ID = 'frankfurter';

    private const ENDPOINT = 'https://api.frankfurter.dev/v2/rates';

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
        return __('Frankfurter (free, no API key)', 'wpdm-premium-packages');
    }

    /**
     * Always usable — there is nothing to configure.
     *
     * @inheritDoc
     */
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function fetchRates(string $base, array $quotes): array
    {
        $result = $this->request($base, $quotes, '');

        unset($result['date']);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function fetchHistoricalRates(string $base, array $quotes, string $date): array
    {
        return $this->request($base, $quotes, $date);
    }

    /**
     * @param string   $base
     * @param string[] $quotes
     * @param string   $date   Y-m-d, or '' for the latest publication.
     *
     * @return array{success: bool, rates: array<string, float>, date: string, error: string}
     */
    private function request(string $base, array $quotes, string $date): array
    {
        $fail = static fn(string $msg): array => [
            'success' => false, 'rates' => [], 'date' => '', 'error' => $msg,
        ];

        $base   = strtoupper($base);
        $quotes = array_values(array_unique(array_map('strtoupper', $quotes)));

        if (empty($quotes)) {
            return ['success' => true, 'rates' => [], 'date' => '', 'error' => ''];
        }

        $args = ['base' => $base];
        if ($date !== '') {
            $args['date'] = $date;
        }

        $response = wp_remote_get(add_query_arg($args, self::ENDPOINT), [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return $fail($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            // Errors arrive as {"status":422,"message":"..."} rather than as a list.
            $message = is_array($body) && !empty($body['message']) ? (string) $body['message'] : '';

            return $fail($message !== ''
                ? sprintf(
                    /* translators: 1: HTTP status, 2: provider message */
                    __('The rate provider returned HTTP %1$d: %2$s', 'wpdm-premium-packages'),
                    $code,
                    $message
                )
                : sprintf(
                    /* translators: %d: HTTP status code */
                    __('The rate provider returned HTTP %d.', 'wpdm-premium-packages'),
                    $code
                ));
        }

        // v2 answers with a flat list of {date, base, quote, rate} rows. A non-list
        // means the shape changed or an error slipped through with a 200, and it is
        // safer to fail than to read zero rates out of it and call that success.
        if (!is_array($body) || !isset($body[0]) || !is_array($body[0])) {
            return $fail(__('The rate provider returned a response that could not be read.', 'wpdm-premium-packages'));
        }

        $wanted    = array_flip($quotes);
        $rates     = [];
        $asOfDate  = '';

        foreach ($body as $row) {
            if (!is_array($row) || !isset($row['quote'], $row['rate'])) {
                continue;
            }

            $quote = strtoupper((string) $row['quote']);
            if (!isset($wanted[$quote]) || !is_numeric($row['rate']) || (float) $row['rate'] <= 0) {
                continue;
            }

            $rates[$quote] = (float) $row['rate'];

            // Rows can carry different dates when a market was closed; the newest
            // one describes the snapshot as a whole.
            if (!empty($row['date']) && (string) $row['date'] > $asOfDate) {
                $asOfDate = (string) $row['date'];
            }
        }

        if (empty($rates)) {
            return $fail(sprintf(
                /* translators: %s: comma separated currency codes */
                __('The rate provider does not publish rates for: %s', 'wpdm-premium-packages'),
                implode(', ', $quotes)
            ));
        }

        return [
            'success' => true,
            'rates'   => $rates,
            'date'    => $asOfDate,
            'error'   => '',
        ];
    }
}
