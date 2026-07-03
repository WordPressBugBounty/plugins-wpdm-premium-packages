<?php
/**
 * Guest order details.
 *
 * Renders the EXACT same UI as the logged-in front-end order details page by
 * preparing the order context and including partials/user-order-details.php.
 * This keeps guest and customer order views visually identical.
 *
 * This template can be overridden by copying it to yourtheme/download-manager/partials/guest-order-details.php.
 *
 * @version     4.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

global $wpdmpp_settings, $wpdb;

if (! \WPDM\__\Session::get('guest_order')) {
    return;
}

$orderService = \WPDMPP\Order\OrderService::instance();
$order        = $orderService->getOrder(\WPDM\__\Session::get('guest_order'));

if (! is_object($order)) {
    return;
}

$order_id = $order->getOrderId();

$wpdmpp_settings['order_validity_period'] = (int) ($wpdmpp_settings['order_validity_period'] ?? 0) > 0
    ? (int) $wpdmpp_settings['order_validity_period']
    : 365;

// Set the expire date if it was never stored, and expire the order if overdue
// (mirrors OrderService::userOrderDetails() so guests see the same state).
if ($order->getExpireDate() == 0 && get_wpdmpp_option('order_validity_period', 365) > 0) {
    $expire_date = $order->getDate() + (get_wpdmpp_option('order_validity_period', 365) * 86400);
    $orderService->updateOrder(['expire_date' => $expire_date], $order_id);
    if (time() > $expire_date) {
        $orderService->updateOrder([
            'order_status'   => 'Expired',
            'payment_status' => 'Expired',
        ], $order_id);
    }
    $order = $orderService->getOrder($order_id);
}

$cart_data = $order->getCartData();
$items     = $orderService->getOrderItemsAsArrays($order_id);

// Rebuild legacy orders that have no rows in the order_items table.
if (is_array($items) && count($items) == 0 && is_array($cart_data) && ! empty($cart_data)) {
    $new_cart_data = [];
    foreach ($cart_data as $pid => $noi) {
        $newi = get_posts([
            'post_type'  => 'wpdmpro',
            'meta_key'   => '__wpdm_legacy_id',
            'meta_value' => $pid,
        ]);
        if (is_array($newi) && count($newi) > 0) {
            $new_cart_data[$newi[0]->ID] = [
                'quantity'  => $noi,
                'variation' => '',
                'price'     => get_post_meta($newi[0]->ID, '__wpdm_base_price', true),
            ];
        }
    }

    if (! empty($new_cart_data)) {
        $orderService->updateOrder([
            'cart_data' => serialize($new_cart_data),
            'items'     => serialize(array_keys($new_cart_data)),
        ], $order_id);
        $orderService->saveOrderItems($new_cart_data, $order_id, true);
        $items = $orderService->getOrderItemsAsArrays($order_id);
    }
}

$title = $order->getTitle() ?: sprintf(__('Order # %s', 'wpdm-premium-packages'), $order_id);

// Guests have no "All Orders" list to return to — hide that breadcrumb link
// in the shared template (the Exit button below handles leaving).
$hide_orders_link = true;
$link = '';

$renews = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ahm_order_renews WHERE order_id = %s",
    $order_id
));

// Guest-only header actions:
//  • Edit Billing opens the modal from guest-order-billing-info.php (included by
//    the wrapper) so the customer can correct the billing details shown on the invoice.
//  • Exit clears the guest session and returns to the lookup form.
$extbtns  = '<button type="button" class="wpdmpp-btn wpdmpp-btn--sm" onclick="wpdmppOpenBillingDialog(); return false;">'
          . \WPDMPP\UI\Icons::get('pencil', 14) . ' ' . esc_html__('Edit Billing', 'wpdm-premium-packages')
          . '</button>';
$extbtns .= '<a href="' . esc_url(home_url('/?exitgo=1')) . '" class="wpdmpp-btn wpdmpp-btn--sm wpdmpp-btn--danger">'
          . \WPDMPP\UI\Icons::get('arrow-left', 14) . ' ' . esc_html__('Exit', 'wpdm-premium-packages')
          . '</a>';
$extbtns  = apply_filters('wpdmpp_order_details_frontend', $extbtns, $order);

// Render the shared front-end order details UI.
include wpdm_tpl_path('partials/user-order-details.php', WPDMPP_TPL_DIR);
