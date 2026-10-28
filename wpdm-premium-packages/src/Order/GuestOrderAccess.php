<?php
/**
 * Proof of ownership for orders that aren't linked to a user account.
 *
 * @package WPDMPP\Order
 * @since 7.3.1
 */

namespace WPDMPP\Order;

use WPDM\__\Crypt;
use WPDM\__\Session;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Who may read, change or claim a guest order.
 *
 * A guest order is found by its order ID, which is built from a timestamp and is
 * not a secret, so knowing it proves nothing. Every operation on a guest order
 * must also prove the caller is the buyer, in one of two ways:
 *
 * - this browser session holds the order: the `guest_order` session value is set
 *   when a guest completes checkout, or after they look the order up with its
 *   email on the guest orders page;
 * - the caller gives the order's email.
 *
 * The `orderid` session value is not proof: abandoned-cart recovery links set it
 * for any order ID.
 */
class GuestOrderAccess {

    /**
     * The guest order this session proved it owns.
     *
     * @return string Order ID, or '' if none.
     */
    public static function sessionOrderId(): string {
        if (!class_exists(Session::class)) {
            return '';
        }
        $orderId = Session::get('guest_order');
        return is_string($orderId) ? $orderId : '';
    }

    /**
     * Whether this session proved it owns the order.
     *
     * @param string $orderId Order ID.
     * @return bool
     */
    public static function sessionOwns(string $orderId): bool {
        $held = self::sessionOrderId();
        return $orderId !== '' && $held !== '' && hash_equals($held, $orderId);
    }

    /**
     * Whether an email is the order's email.
     *
     * @param Order  $order Order.
     * @param string $email Email given by the caller.
     * @return bool
     */
    public static function emailMatches(Order $order, string $email): bool {
        $email      = sanitize_email($email);
        $orderEmail = sanitize_email($order->getBillingEmail());
        return $email !== '' && $orderEmail !== '' && is_email($email) && strtolower($email) === strtolower($orderEmail);
    }

    /**
     * Whether the current visitor may read the order.
     *
     * Store admins may read any order. A user's order is readable by that user. A
     * guest order is readable only by the session that holds it.
     *
     * @param Order $order Order.
     * @return bool
     */
    public static function canView(Order $order): bool {
        if (defined('WPDMPP_ADMIN_CAP') && current_user_can(WPDMPP_ADMIN_CAP)) {
            return true;
        }
        $userId = $order->getUserId();
        if ($userId > 0) {
            return is_user_logged_in() && $userId === get_current_user_id();
        }
        return self::sessionOwns($order->getOrderId());
    }

    /**
     * Whether the signed-in user may link a guest order to their account.
     *
     * @param Order  $order Order.
     * @param string $email Order email given by the user, if any.
     * @return bool
     */
    public static function canClaim(Order $order, string $email = ''): bool {
        if (!is_user_logged_in() || $order->getUserId() !== 0) {
            return false;
        }
        return self::sessionOwns($order->getOrderId()) || self::emailMatches($order, $email);
    }

    /**
     * An encrypted order reference that only works for one purpose.
     *
     * Download Manager encrypts other values with the same key, such as the login
     * name in password reset links. A bare encrypted order ID can't be told apart
     * from those, so order references carry their purpose.
     *
     * @param string $orderId Order ID.
     * @param string $purpose What the reference is for, e.g. 'guest-billing'.
     * @return string
     */
    public static function token(string $orderId, string $purpose): string {
        return Crypt::encrypt(['purpose' => $purpose, 'order' => $orderId]);
    }

    /**
     * The order ID in a reference made by token(), if it was made for this purpose.
     *
     * @param string $token   Encrypted reference.
     * @param string $purpose Expected purpose.
     * @return string Order ID, or '' if the reference is invalid or for another purpose.
     */
    public static function tokenOrderId(string $token, string $purpose): string {
        if ($token === '') {
            return '';
        }
        $data = Crypt::decrypt($token, true);
        if (!is_array($data) || ($data['purpose'] ?? '') !== $purpose || !is_string($data['order'] ?? null)) {
            return '';
        }
        return $data['order'];
    }
}
