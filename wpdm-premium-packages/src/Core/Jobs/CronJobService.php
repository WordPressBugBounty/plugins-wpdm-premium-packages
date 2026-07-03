<?php
/**
 * Cron Job Service
 *
 * Manages registration and scheduling of Premium Package cron jobs.
 *
 * @package WPDMPP\Core\Jobs
 * @since 7.0.0
 */

namespace WPDMPP\Core\Jobs;

defined('ABSPATH') || exit;

class CronJobService
{
    /**
     * Singleton instance
     *
     * @var CronJobService|null
     */
    private static ?CronJobService $instance = null;

    /**
     * Whether the service has been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Registered job handlers
     *
     * @var array
     */
    private array $handlers = [];

    /**
     * Get singleton instance
     *
     * @return CronJobService
     */
    public static function getInstance(): CronJobService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {}

    /**
     * Register cron job handlers
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        // Register job handlers with WPDM's cron system
        add_action('wpdm_register_job_handlers', [$this, 'registerHandlers']);

        // Schedule jobs on init
        add_action('init', [$this, 'scheduleJobs'], 20);

        // Legacy support - keep old WP cron hooks working
        add_action('wpdmpp_notify_to_renew', [$this, 'runRenewalNotifications']);
        add_action('wpdmpp_delete_incomplete_order', [$this, 'runIncompleteOrderCleanup']);
        add_action('wpdmpp_daily_sales_summary', [$this, 'runDailySalesSummary']);

        // Legacy WP cron interval and scheduling
        add_filter('cron_schedules', [$this, 'addCronInterval']);
        add_action('init', [$this, 'legacySchedule']);
    }

    /**
     * Add six_hourly cron interval for legacy WP cron support
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function addCronInterval(array $schedules): array
    {
        if (!isset($schedules['six_hourly'])) {
            $schedules['six_hourly'] = [
                'interval' => 21600,
                'display' => esc_html__('Every 6 hours'),
            ];
        }
        return $schedules;
    }

    /**
     * Schedule legacy WP cron events for backward compatibility
     */
    public function legacySchedule(): void
    {
        if (!wp_next_scheduled('wpdmpp_notify_to_renew')) {
            wp_schedule_event(time() + 1800, 'six_hourly', 'wpdmpp_notify_to_renew');
        }
        if (!wp_next_scheduled('wpdmpp_delete_incomplete_order')) {
            wp_schedule_event(time() + 3600, 'six_hourly', 'wpdmpp_delete_incomplete_order');
        }
        if (!wp_next_scheduled('wpdmpp_daily_sales_summary')) {
            wp_schedule_event(time() + 3600, 'six_hourly', 'wpdmpp_daily_sales_summary');
        }
    }

    /**
     * Register job handlers with WPDM cron system
     */
    public function registerHandlers(): void
    {
        $handlers = [
            OrderRenewalNotificationJob::class,
            IncompleteOrderCleanupJob::class,
            DailySalesSummaryJob::class,
        ];

        foreach ($handlers as $handler) {
            if (class_exists($handler) && class_exists('\WPDM\__\CronJob')) {
                \WPDM\__\CronJob::registerHandler($handler);
                $this->handlers[] = $handler;
            }
        }
    }

    /**
     * Schedule jobs if not already scheduled
     */
    public function scheduleJobs(): void
    {
        // Only schedule if using new WPDM cron system
        if (!class_exists('\WPDM\__\CronJob')) {
            return;
        }

        // Check if the cron_jobs table has the required columns (added in DM v7.0.1 migration)
        // If not, skip new cron scheduling — legacy WP cron will handle these jobs
        if (!$this->isTableSchemaReady()) {
            return;
        }

        // Make sure handlers are registered first
        if (empty($this->handlers)) {
            $this->registerHandlers();
        }

        // Schedule renewal notifications
        if (!$this->isJobScheduled('wpdmpp_order_renewal_notification')) {
            OrderRenewalNotificationJob::schedule();
        }

        // Schedule incomplete order cleanup
        if (!$this->isJobScheduled('wpdmpp_incomplete_order_cleanup')) {
            IncompleteOrderCleanupJob::schedule();
        }

        // Schedule daily sales summary
        if (!$this->isJobScheduled('wpdmpp_daily_sales_summary')) {
            DailySalesSummaryJob::schedule();
        }
    }

    /**
     * Check if ahm_cron_jobs table has the required columns for the new cron system
     *
     * @return bool
     */
    private function isTableSchemaReady(): bool
    {
        global $wpdb;

        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        $result = $wpdb->get_results("SHOW COLUMNS FROM `{$wpdb->prefix}ahm_cron_jobs` LIKE 'status'");
        $ready = !empty($result);

        return $ready;
    }

    /**
     * Check if a job is already scheduled
     *
     * @param string $code Job code
     * @return bool
     */
    private function isJobScheduled(string $code): bool
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ahm_cron_jobs WHERE code = %s",
            $code
        ));

        return (int) $count > 0;
    }

    /**
     * Run renewal notifications (legacy hook handler)
     */
    public function runRenewalNotifications(): void
    {
        $job = new OrderRenewalNotificationJob();
        $job->handle([]);
    }

    /**
     * Run incomplete order cleanup (legacy hook handler)
     */
    public function runIncompleteOrderCleanup(): void
    {
        $job = new IncompleteOrderCleanupJob();
        $job->handle([]);
    }

    /**
     * Run daily sales summary (legacy hook handler)
     */
    public function runDailySalesSummary(): void
    {
        $job = new DailySalesSummaryJob();
        $job->handle([]);
    }

    /**
     * Get registered handlers
     *
     * @return array
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Manually trigger a job
     *
     * @param string $jobClass Job class name
     * @param array  $data     Job data
     * @return bool
     */
    public function runJob(string $jobClass, array $data = []): bool
    {
        if (!class_exists($jobClass)) {
            return false;
        }

        try {
            $job = new $jobClass($data);
            return $job->handle($data);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[WPDMPP CronJob] Manual job run failed for %s: %s',
                $jobClass,
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Cancel a scheduled job
     *
     * @param string $code Job code
     * @return bool
     */
    public function cancelJob(string $code): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . 'ahm_cron_jobs',
            ['code' => $code],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Get job statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        global $wpdb;

        $stats = [];

        foreach (['wpdmpp_order_renewal_notification', 'wpdmpp_incomplete_order_cleanup', 'wpdmpp_daily_sales_summary'] as $code) {
            $job = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ahm_cron_jobs WHERE code = %s LIMIT 1",
                $code
            ));

            $stats[$code] = [
                'scheduled' => !empty($job),
                'next_run' => $job ? date('Y-m-d H:i:s', $job->execute_at) : null,
                'interval' => $job ? $job->interval : null,
            ];
        }

        return $stats;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
