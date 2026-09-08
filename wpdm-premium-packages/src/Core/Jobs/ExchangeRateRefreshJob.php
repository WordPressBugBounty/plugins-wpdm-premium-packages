<?php
/**
 * Daily exchange rate refresh.
 *
 * @package WPDMPP\Core\Jobs
 * @since   7.2.0
 */

namespace WPDMPP\Core\Jobs;

use WPDM\__\Jobs\Job;
use WPDMPP\Currency\ExchangeRateService;

defined('ABSPATH') || exit;

class ExchangeRateRefreshJob extends Job
{
    /**
     * Unique code. CronJob::create() returns any existing job with this code
     * untouched rather than replacing it, which is why changing the interval goes
     * through reschedule() below instead of scheduling again.
     */
    public const CODE = 'wpdmpp_exchange_rate_refresh';

    /**
     * Fetch and store a rate snapshot.
     *
     * Wakes more often than it refreshes and gates on the configured interval,
     * matching the daily sales job: a site with unreliable cron gets several
     * chances to fire within each window without spending several windows' worth
     * of a provider's quota.
     *
     * @param object|array $data Job payload.
     *
     * @return bool
     */
    public function handle($data): bool
    {
        $service = ExchangeRateService::getInstance();

        // A single-currency store has nothing to convert, so leave the provider
        // alone entirely rather than burning quota on a request nobody reads.
        if (!$service->requiresRates()) {
            $this->log('Only one currency in use; skipping rate refresh');
            return true;
        }

        $lastRun  = (int) get_option('__wpdmpp_rates_last_attempt', 0);
        $interval = $service->getRefreshIntervalHours() * HOUR_IN_SECONDS;
        if ($lastRun > 0 && (time() - $lastRun) < $interval) {
            $this->log('Rates were refreshed recently; skipping');
            return true;
        }

        update_option('__wpdmpp_rates_last_attempt', time(), false);

        $result = $service->refresh();

        if (empty($result['success'])) {
            // Report the failure but return true: a provider outage is not a job
            // defect, and retrying immediately would not help. The previous rates
            // stay in place and the staleness warning surfaces it in admin.
            $this->log('Rate refresh failed: ' . $result['message']);

            return true;
        }

        $this->log($result['message']);

        // Keep the snapshot history bounded; the newest row per pair is always kept.
        $service->prune();

        return true;
    }

    /**
     * Register the recurring job.
     *
     * @return int|false
     */
    public static function schedule()
    {
        if (!class_exists('\WPDM\__\CronJob')) {
            return false;
        }

        return \WPDM\__\CronJob::create(
            self::class,
            [],
            0,                  // start now
            0,                  // repeat indefinitely
            self::wakeInterval(),
            'premium-packages',
            5,
            3,
            self::CODE
        );
    }

    /**
     * Apply a changed interval to the job already in the queue.
     *
     * Scheduling again would not do it: CronJob::create() finds the existing row by
     * code and hands it back as-is, so the setting would read as changed while the
     * job kept its old cadence.
     *
     * @return void
     */
    public static function reschedule(): void
    {
        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->prefix}ahm_cron_jobs WHERE code = %s AND status IN ('pending', 'running')",
            self::CODE
        ));

        if (!$existing) {
            self::schedule();
            return;
        }

        $wpdb->update(
            "{$wpdb->prefix}ahm_cron_jobs",
            ['interval_seconds' => self::wakeInterval()],
            ['ID' => (int) $existing],
            ['%d'],
            ['%d']
        );
    }

    /**
     * How often the job wakes, as opposed to how often it refreshes.
     *
     * Half the configured interval, so a missed tick still leaves a chance to fire
     * inside the window, with a floor of an hour so a one-hour setting does not ask
     * cron for something it cannot deliver.
     *
     * @return int Seconds.
     */
    private static function wakeInterval(): int
    {
        $hours = ExchangeRateService::getInstance()->getRefreshIntervalHours();

        return (int) max(HOUR_IN_SECONDS, floor($hours / 2) * HOUR_IN_SECONDS);
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'Exchange Rate Refresh';
    }
}
