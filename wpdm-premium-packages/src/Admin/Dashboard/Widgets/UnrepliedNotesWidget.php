<?php
/**
 * Order Notes Awaiting Reply Dashboard Widget
 *
 * Lists orders whose latest note is from the customer, newest first.
 *
 * @package WPDMPP\Admin\Dashboard\Widgets
 */

namespace WPDMPP\Admin\Dashboard\Widgets;

use WPDMPP\Admin\Dashboard\AbstractWidget;

defined('ABSPATH') || exit;

class UnrepliedNotesWidget extends AbstractWidget
{
    /**
     * Widget ID
     *
     * @var string
     */
    protected string $id = 'unreplied_notes';

    /**
     * Widget title
     *
     * @var string
     */
    protected string $title;

    /**
     * Number of orders to display
     *
     * @var int
     */
    protected int $limit = 6;

    /**
     * Cache duration (15 minutes); a new note clears it sooner, see getCacheKey()
     *
     * @var int
     */
    protected int $cacheDuration = 900;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->title = __('WPDM Order Notes Awaiting Reply', 'wpdm-premium-packages');
    }

    /**
     * Get cache key
     *
     * Changes whenever a note is added to any order, so a reply, or a new note
     * from a customer, shows the next time the dashboard loads.
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return 'wpdmpp_unreplied_notes_' . (int) get_option('_wpdmpp_order_notes_updated', 0);
    }

    /**
     * Get widget data
     *
     * @return array
     */
    public function getData(): array
    {
        $cached = $this->getCachedData();
        if ($cached !== null) {
            return $cached;
        }

        global $wpdb;

        // Notes are stored serialized in order_notes under a "messages" key
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT order_id, uid, billing_info, order_notes
             FROM {$wpdb->prefix}ahm_orders
             WHERE order_notes LIKE %s",
            '%' . $wpdb->esc_like('s:8:"messages";') . '%'
        ));

        $userIds = [];
        foreach ($orders as $order) {
            if ((int) $order->uid > 0) {
                $userIds[] = (int) $order->uid;
            }
        }
        if (!empty($userIds)) {
            cache_users(array_unique($userIds));
        }

        $threads = [];
        foreach ($orders as $order) {
            $thread = $this->awaitingReply($order);
            if ($thread) {
                $threads[] = $thread;
            }
        }

        // Most recently received first
        usort($threads, function ($a, $b) {
            return $b['received'] <=> $a['received'];
        });

        $data = [
            'threads' => array_slice($threads, 0, $this->limit),
            'total'   => count($threads),
        ];

        $this->setCachedData($data);

        return $data;
    }

    /**
     * The customer's unanswered notes on an order, or null if the latest note isn't theirs
     *
     * @param object $order Order row with order_id, uid, billing_info and order_notes
     * @return array|null
     */
    private function awaitingReply(object $order): ?array
    {
        $notes = maybe_unserialize($order->order_notes);
        $messages = is_array($notes) && !empty($notes['messages']) && is_array($notes['messages']) ? $notes['messages'] : [];
        if (!$messages) {
            return null;
        }
        ksort($messages);

        $orderUid = (int) $order->uid;
        $user = $orderUid > 0 ? get_userdata($orderUid) : false;
        $billing = maybe_unserialize($order->billing_info);
        $billing = is_array($billing) ? $billing : [];

        // Customer notes since the last note from anyone else
        $unanswered = [];
        foreach ($messages as $time => $message) {
            if (!is_array($message)) {
                continue;
            }
            if ($this->isFromCustomer($message, $orderUid, $user ? $user->display_name : '')) {
                $unanswered[$time] = $message;
            } else {
                $unanswered = [];
            }
        }
        if (!$unanswered) {
            return null;
        }

        $latestTime = (int) array_key_last($unanswered);
        $latest = $unanswered[$latestTime];
        $received = (int) ($latest['date'] ?? $latestTime);

        $email = $user ? $user->user_email : (string) ($billing['order_email'] ?? '');
        $name = $user ? $user->display_name : trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        if ($name === '') {
            $name = $email ? explode('@', $email)[0] : __('Guest', 'wpdm-premium-packages');
        }

        $text = trim(wp_strip_all_tags((string) ($latest['note'] ?? '')));

        return [
            'order_id'  => $order->order_id,
            'customer'  => $name,
            'email'     => $email,
            'avatar'    => $user ? get_avatar_url($user->ID, ['size' => 80]) : ($email ? get_avatar_url($email, ['size' => 80]) : ''),
            'note'      => $text !== '' ? wp_trim_words($text, 20) : '',
            'files'     => !empty($latest['file']) && is_array($latest['file']) ? count($latest['file']) : 0,
            'count'     => count($unanswered),
            'received'  => $received,
            'time_ago'  => $this->timeAgo($received),
            'order_url' => admin_url('edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . rawurlencode($order->order_id)) . '#post-order-note',
        ];
    }

    /**
     * Whether a note was written by the order's customer
     *
     * Notes record their author's user ID; guest orders and the guest's notes both
     * have 0. Store admins never count as the customer, even on their own orders.
     * Notes saved before author IDs were recorded are matched by the customer's name.
     *
     * @param array  $message      Note
     * @param int    $orderUid     Order owner's user ID, 0 for guest orders
     * @param string $customerName Order owner's display name
     * @return bool
     */
    private function isFromCustomer(array $message, int $orderUid, string $customerName): bool
    {
        if (isset($message['uid'])) {
            $authorId = (int) $message['uid'];
            if ($authorId > 0 && user_can($authorId, WPDMPP_ADMIN_CAP)) {
                return false;
            }
            return $authorId === $orderUid;
        }

        return $customerName !== '' && ($message['by'] ?? '') === $customerName;
    }

    /**
     * Render the widget content
     *
     * @return void
     */
    public function render(): void
    {
        $data = $this->getData();
        $threads = $data['threads'];
        $total = $data['total'];
        include $this->getTemplatePath();
    }
}
