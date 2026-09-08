<?php
/**
 * Daily Sales Summary Job
 *
 * Sends a daily sales summary email to the site administrator.
 *
 * @package WPDMPP\Core\Jobs
 * @since 7.0.0
 */

namespace WPDMPP\Core\Jobs;

use WPDM\__\Jobs\Job;
use WPDM\__\Email;
use WPDM\__\__MailUI;

defined('ABSPATH') || exit;

class DailySalesSummaryJob extends Job
{
    /**
     * Execute the daily sales summary job
     *
     * @param object|array $data Job payload
     * @return bool
     */
    public function handle($data): bool
    {
        global $wpdb;

        $this->log('Starting daily sales summary job');

        // Check if already sent today
        $lastSent = get_option('__wpdmpp_ssm_sent');
        if ($lastSent && date('Ymd', $lastSent) === date('Ymd')) {
            $this->log('Daily sales summary already sent today');
            return true;
        }

        // Get yesterday's date range
        $yesterdayStart = strtotime('yesterday 00:00:00');
        $yesterdayEnd = strtotime('yesterday 23:59:59');

        // Get new completed orders
        $newOrders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_orders
             WHERE `date` >= %d AND `date` <= %d
             AND order_status = 'Completed'
             AND payment_status = 'Completed'",
            $yesterdayStart,
            $yesterdayEnd
        ));

        // Get renewed orders
        $renewedOrders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ahm_order_renews
             WHERE `date` >= %d AND `date` <= %d",
            $yesterdayStart,
            $yesterdayEnd
        ));

        // Build summary data
        $totalSales = 0;
        $orderCount = count($newOrders);
        $renewCount = count($renewedOrders);
        $tableData = [];

        foreach ($newOrders as $order) {
            $tableData[] = [
                __('New', 'wpdm-premium-packages'),
                $order->order_id,
                wpdmpp_price_format($order->total),
            ];
            $totalSales += (float) $order->total;
        }

        foreach ($renewedOrders as $order) {
            $tableData[] = [
                __('Renew', 'wpdm-premium-packages'),
                $order->order_id,
                wpdmpp_price_format($order->total),
            ];
            $totalSales += (float) $order->total;
        }

        // Skip if no sales
        if ($orderCount === 0 && $renewCount === 0) {
            $this->log('No sales yesterday, skipping summary email');
            update_option('__wpdmpp_ssm_sent', time(), false);
            return true;
        }

        // Build email content
        $formattedTotal = wpdmpp_price_format($totalSales);
        $table = $this->buildTable(
            [__('Type', 'wpdm-premium-packages'), __('Order ID', 'wpdm-premium-packages'), __('Amount', 'wpdm-premium-packages')],
            $tableData,
            $formattedTotal
        );

        $cards = $this->buildStatCards([
            [__('Total Sales', 'wpdm-premium-packages'), $formattedTotal, true],
            [__('New Purchases', 'wpdm-premium-packages'), (string) $orderCount, false],
            [__('Renewals', 'wpdm-premium-packages'), (string) $renewCount, false],
        ]);

        $message = __("Here's a quick snapshot of yesterday's sales performance:", 'wpdm-premium-packages');
        $message .= $cards . $table;

        // Send email
        $params = [
            'subject' => sprintf(
                __('[%s] Daily Sales Overview - %s', 'wpdm-premium-packages'),
                get_bloginfo('name'),
                date(get_option('date_format'), strtotime('yesterday'))
            ),
            'to_email' => get_option('admin_email'),
            'message' => $message,
        ];

        Email::send('default', $params);

        // Mark as sent
        update_option('__wpdmpp_ssm_sent', time(), false);

        $this->log("Daily sales summary sent: {$orderCount} new orders, {$renewCount} renewals, {$formattedTotal} total");

        /**
         * Fires after daily sales summary is sent
         *
         * @param float $totalSales  Total sales amount
         * @param int   $orderCount  Number of new orders
         * @param int   $renewCount  Number of renewals
         */
        do_action('wpdmpp_daily_sales_summary_sent', $totalSales, $orderCount, $renewCount);

        return true;
    }

    /**
     * Build the order breakdown table.
     *
     * Written as inline-styled table markup rather than through __MailUI so the
     * columns can be aligned and typed properly: amounts right aligned so they
     * compare down the column, order ids in a monospaced face, and the row type
     * as a coloured badge. Mail clients drop <style> blocks, so every rule is
     * inline, and the markup carries no blank lines because the message is run
     * through wpautop() before sending.
     *
     * @param array $headers Column headings.
     * @param array $data    Rows of [type, order id, amount].
     * @param string $total  Formatted total, shown in the footer row.
     * @return string
     */
    private function buildTable(array $headers, array $data, string $total = ''): string
    {
        $th = 'padding:10px 12px;font-size:11px;font-weight:600;letter-spacing:0.06em;'
            . 'text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;';

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;'
            . 'font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;">';

        // Pin the narrow columns so the badge and the amount sit next to their
        // neighbours instead of the browser dividing the width evenly.
        $widths = ['18%', '', '22%'];

        $html .= '<tr>';
        foreach (array_values($headers) as $i => $header) {
            // Amount is the last column and reads better flush right.
            $align = $i === count($headers) - 1 ? 'right' : 'left';
            $width = isset($widths[$i]) && $widths[$i] !== '' ? 'width:' . $widths[$i] . ';' : '';
            $html .= '<th align="' . $align . '" style="' . $th . $width . 'text-align:' . $align . ';">'
                . esc_html($header) . '</th>';
        }
        $html .= '</tr>';

        foreach ($data as $n => $row) {
            $row = array_values($row);
            // Banding keeps long lists readable where borders alone would not.
            $bg = $n % 2 === 1 ? 'background:#f9fafb;' : '';
            $td = 'padding:11px 12px;font-size:14px;color:#111827;border-bottom:1px solid #f3f4f6;' . $bg;

            $html .= '<tr>'
                . '<td style="' . $td . '">' . $this->typeBadge((string) ($row[0] ?? '')) . '</td>'
                . '<td style="' . $td . 'font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;'
                . 'font-size:13px;color:#4b5563;">' . esc_html((string) ($row[1] ?? '')) . '</td>'
                . '<td align="right" style="' . $td . 'text-align:right;font-weight:600;white-space:nowrap;">'
                . esc_html((string) ($row[2] ?? '')) . '</td>'
                . '</tr>';
        }

        if ($total !== '') {
            $tf = 'padding:12px;font-size:14px;background:#f9fafb;border-top:1px solid #e5e7eb;';
            $html .= '<tr>'
                . '<td colspan="2" style="' . $tf . 'font-weight:600;color:#374151;">'
                . esc_html__('Total', 'wpdm-premium-packages') . '</td>'
                . '<td align="right" style="' . $tf . 'text-align:right;font-weight:700;color:#111827;'
                . 'white-space:nowrap;">' . esc_html($total) . '</td>'
                . '</tr>';
        }

        return $html . '</table>';
    }

    /**
     * Coloured badge for a row's type, so New and Renew are separable at a glance.
     *
     * @param string $type Already translated label.
     * @return string
     */
    private function typeBadge(string $type): string
    {
        // Compared against the untranslated source so the colour survives translation.
        $isRenewal = $type === __('Renew', 'wpdm-premium-packages');
        $colours   = $isRenewal ? ['#eef2ff', '#4338ca'] : ['#ecfdf5', '#047857'];

        return '<span style="display:inline-block;padding:3px 9px;border-radius:11px;font-size:12px;'
            . 'font-weight:600;background:' . $colours[0] . ';color:' . $colours[1] . ';">'
            . esc_html($type) . '</span>';
    }

    /**
     * Build the row of headline figures.
     *
     * Equal thirds via percentage widths on the outer cells, each holding its own
     * bordered table - the layout mail clients render consistently, since float
     * and flex are unavailable. The leading figure is accented to give the row a
     * focal point instead of three identical boxes.
     *
     * @param array $cards List of [label, value, is_primary].
     * @return string
     */
    private function buildStatCards(array $cards): string
    {
        $count = max(1, count($cards));
        $width = round(100 / $count, 2);

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:100%;border-collapse:separate;margin:18px 0;'
            . 'font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;"><tr>';

        foreach (array_values($cards) as $i => $card) {
            [$label, $value, $primary] = array_pad((array) $card, 3, false);

            $bg     = $primary ? '#eef2ff' : '#f9fafb';
            $border = $primary ? '#c7d2fe' : '#e5e7eb';
            $colour = $primary ? '#4f46e5' : '#111827';
            // Gutter between cards; the last one runs to the edge.
            $gutter = $i < $count - 1 ? 'padding-right:10px;' : '';

            $html .= '<td width="' . $width . '%" valign="top" style="width:' . $width . '%;' . $gutter . '">'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
                . ' style="width:100%;background:' . $bg . ';border:1px solid ' . $border . ';border-radius:8px;">'
                . '<tr><td style="padding:14px 16px;">'
                . '<div style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;'
                . 'color:#6b7280;">' . esc_html((string) $label) . '</div>'
                . '<div style="font-size:26px;font-weight:700;line-height:1.25;padding-top:6px;color:'
                . $colour . ';">' . esc_html((string) $value) . '</div>'
                . '</td></tr></table></td>';
        }

        return $html . '</tr></table>';
    }

    /**
     * Schedule this job
     *
     * @return int|false Job ID or false on failure
     */
    public static function schedule(): int|false
    {
        if (!class_exists('\WPDM\__\CronJob')) {
            return false;
        }

        return \WPDM\__\CronJob::create(
            self::class,
            [],
            0,                              // Execute now
            0,                              // Infinite repeat
            21600,                          // Every 6 hours (but only sends once per day due to check)
            'premium-packages',             // Queue name
            5,                              // Priority
            3,                              // Max attempts
            'wpdmpp_daily_sales_summary'    // Unique code
        );
    }

    /**
     * Get job name
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'Daily Sales Summary';
    }

    /**
     * Get job description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Sends a daily sales summary email to the site administrator with order and renewal statistics.';
    }
}
