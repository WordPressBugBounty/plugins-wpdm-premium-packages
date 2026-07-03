<?php
/**
 * Incomplete Order Cleanup Job
 *
 * Cleans up old incomplete/processing orders that were never completed.
 *
 * @package WPDMPP\Core\Jobs
 * @since 7.0.0
 */

namespace WPDMPP\Core\Jobs;

use WPDM\__\Jobs\Job;

defined('ABSPATH') || exit;

class IncompleteOrderCleanupJob extends Job
{
    /**
     * Execute the cleanup job
     *
     * @param object|array $data Job payload
     * @return bool
     */
    public function handle($data): bool
    {
        global $wpdb;

        $this->log('Starting incomplete order cleanup job');

        // Get days setting from data or plugin options
        $days = $this->get('days', 0);
        if ($days <= 0) {
            $days = (int) get_wpdmpp_option('delete_incomplete_order');
        }

        if ($days <= 0) {
            $this->log('Incomplete order cleanup is disabled (days = 0)');
            return true;
        }

        // Calculate cutoff date
        $cutoffDate = strtotime("-{$days} days");
        $batchLimit = $this->get('batch_limit', 20);

        // Find old incomplete orders
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_orders
             WHERE `date` <= %d
             AND order_status = 'Processing'
             AND payment_status = 'Processing'
             LIMIT %d",
            $cutoffDate,
            $batchLimit
        ));

        if (empty($orders)) {
            $this->log('No incomplete orders to clean up');
            return true;
        }

        $deleted = 0;
        foreach ($orders as $order) {
            // Delete order items first
            $wpdb->delete(
                $wpdb->prefix . 'ahm_order_items',
                ['oid' => $order->order_id]
            );

            // Delete the order
            $result = $wpdb->delete(
                $wpdb->prefix . 'ahm_orders',
                [
                    'order_id' => $order->order_id,
                    'order_status' => 'Processing',
                ],
                ['%s', '%s']
            );

            if ($result) {
                $deleted++;
                $this->log("Deleted incomplete order: {$order->order_id}");

                /**
                 * Fires after an incomplete order is deleted
                 *
                 * @param string $orderId The deleted order ID
                 * @param object $order   The order object before deletion
                 */
                do_action('wpdmpp_incomplete_order_deleted', $order->order_id, $order);
            }
        }

        $this->log("Deleted {$deleted} incomplete orders older than {$days} days");

        /**
         * Fires after incomplete order cleanup completes
         *
         * @param int $deleted Number of orders deleted
         * @param int $days    Days threshold
         */
        do_action('wpdmpp_incomplete_orders_cleanup_completed', $deleted, $days);

        return true;
    }

    /**
     * Schedule this job
     *
     * @param int $days Days after which to delete incomplete orders (0 to disable)
     * @return int|false Job ID or false on failure
     */
    public static function schedule(int $days = 0): int|false
    {
        if (!class_exists('\WPDM\__\CronJob')) {
            return false;
        }

        return \WPDM\__\CronJob::create(
            self::class,
            ['days' => $days],
            0,                              // Execute now
            0,                              // Infinite repeat
            21600,                          // Every 6 hours
            'premium-packages',             // Queue name
            5,                              // Priority
            3,                              // Max attempts
            'wpdmpp_incomplete_order_cleanup'  // Unique code
        );
    }

    /**
     * Get job name
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'Incomplete Order Cleanup';
    }

    /**
     * Get job description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Cleans up old incomplete/processing orders that were abandoned during checkout.';
    }
}
