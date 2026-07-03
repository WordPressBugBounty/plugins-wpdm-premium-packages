<?php
/**
 * Order Admin Service
 *
 * Handles the Orders admin page functionality.
 *
 * @package WPDMPP\Admin\Order
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Order;

use WPDM\__\__;
use WPDMPP\Admin\HasViews;
use WPDMPP\Order\OrderService;


defined('ABSPATH') || exit;

class OrderAdminService
{
    use HasViews;

    /**
     * Singleton instance
     *
     * @var OrderAdminService|null
     */
    private static ?OrderAdminService $instance = null;

    /**
     * Whether AJAX handlers have been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Get singleton instance
     *
     * @return OrderAdminService
     */
    public static function getInstance(): OrderAdminService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
    }

    /**
     * Register AJAX handlers for order admin operations
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        // Migrated from wpdmpp_async_request dispatcher (were broken — now fixed with direct hooks)
        add_action('wp_ajax_wpdmpp_add_refund', [$this, 'addRefund']);
        add_action('wp_ajax_wpdmpp_update_order_status', [$this, 'updateOrderStatus']);
        add_action('wp_ajax_wpdmpp_update_payment_method', [$this, 'updatePaymentMethod']);
        add_action('wp_ajax_wpdmpp_update_payment_status', [$this, 'updatePaymentStatus']);
        // Note: wpdmpp_add_order_note migrated to REST API (wpdmpp/v1/admin/orders/{id}/note)
        add_action('wp_ajax_wpdmpp_order_confirmation_email', [$this, 'orderConfirmationEmail']);

        // Migrated from CustomActions::execute()
        add_action('wp_ajax_wpdmpp_change_transection_id', [$this, 'changeTransactionId']);
        add_action('wp_ajax_wpdmpp_updateOrderExpiryDate', [$this, 'updateOrderExpiryDate']);
        add_action('wp_ajax_delete_renew_entry', [$this, 'deleteRenewEntry']);
        add_action('wp_ajax_wpdmpp_updateOrderRenews', [$this, 'updateOrderRenews']);
        add_action('wp_ajax_wpdmpp_edit_order', [$this, 'editOrder']);
        add_action('wp_ajax_wpdmpp_admin_save_custom_order', [$this, 'saveCustomOrder']);
        add_action('wp_ajax_wpdmpp_download_hostory', [$this, 'loadDownloadHistory']);
        add_action('wp_ajax_wpdmpp_acr_activity_log', [$this, 'acrActivityLog']);
        add_action('wp_ajax_wpdmpp_admin_cart_html', [$this, 'generateCartHTML']);
        add_action('wp_ajax_wpdmpp_empty_cart', [$this, 'emptyCart']);
        add_action('wpdmpp_after_addtocart', [$this, 'afterAddToCart']);

        // Asynchronous deletion of selected orders.
        add_action('wp_ajax_wpdmpp_delete_orders', [$this, 'deleteOrders']);
    }

    /**
     * Asynchronously delete the selected orders.
     *
     * Accepts an explicit selection (`ids[]`). Each order is removed via
     * OrderService::deleteOrder() so items, renewals and meta are cascaded and the
     * wpdmpp_order_deleted hook fires.
     * Returns JSON: { success, count, requested, deleted: [order_id, …], message }.
     */
    public function deleteOrders(): void
    {
        if (!current_user_can(WPDMPP_ADMIN_CAP) || !check_ajax_referer('wpdmpp_delete_orders', '_wpnonce', false)) {
            wp_send_json([
                'success' => false,
                'message' => esc_html__('Permission denied or your session expired. Please refresh and try again.', 'wpdm-premium-packages'),
            ]);
        }

        $orderService = OrderService::instance();

        // Resolve the target order IDs from the explicit selection.
        $ids = [];
        if (isset($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_values(array_filter(array_map('sanitize_text_field', wp_unslash($_POST['ids']))));
        }

        if (empty($ids)) {
            wp_send_json([
                'success' => false,
                'message' => esc_html__('No orders selected.', 'wpdm-premium-packages'),
            ]);
        }

        $deleted = [];
        foreach ($ids as $oid) {
            if ($orderService->deleteOrder($oid)) {
                $deleted[] = $oid;
            }
        }

        $count     = count($deleted);
        $requested = count($ids);

        // Nothing actually deleted (e.g. stale IDs) — report it as a failure so the
        // UI doesn't show a misleading success toast.
        if ($count === 0) {
            wp_send_json([
                'success'   => false,
                'count'     => 0,
                'requested' => $requested,
                'deleted'   => [],
                'message'   => esc_html__('No orders could be deleted.', 'wpdm-premium-packages'),
            ]);
        }

        $message = sprintf(
            _n('%d order deleted.', '%d orders deleted.', $count, 'wpdm-premium-packages'),
            $count
        );
        if ($count < $requested) {
            $message .= ' ' . sprintf(
                /* translators: %d: number of orders that could not be deleted */
                __('%d could not be deleted.', 'wpdm-premium-packages'),
                $requested - $count
            );
        }

        wp_send_json([
            'success'   => true,
            'count'     => $count,
            'requested' => $requested,
            'deleted'   => $deleted,
            'message'   => $message,
        ]);
    }

    /**
     * Resend order confirmation email
     */
    public function orderConfirmationEmail(): void
    {
        __::isAuthentic('ocemnonce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP, true);
        OrderService::instance()->sendConfirmationEmail(wpdm_query_var('order_id'));
        wp_send_json(['success' => true, 'msg' => __('Order confirmation email sent!', WPDMPP_TEXT_DOMAIN)]);
    }

    /**
     * Hook callback after add-to-cart for custom orders
     */
    public function afterAddToCart($cart_data): void
    {
        if (isset($_REQUEST['custom_order'])) {
            $this->generateCartHTML($cart_data);
        }
    }

    /**
     * Add a refund to an order
     */
    public function addRefund(): void
    {
        global $wpdb;
        __::isAuthentic('wpdmpparnonnce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP, true);
        $refund_amount = wpdm_query_var('refund', 'double');
        $order_id = wpdm_query_var('order_id', 'txt');
        $orderService = OrderService::instance();
        $order = $orderService->getOrder($order_id);
        if (!$order) {
            wp_send_json(['msg' => __('Order not found', 'wpdm-premium-packages')]);
            return;
        }
        $refund = (double)$order->getRefund() + (double)$refund_amount;
        $newTotal = (double)$order->getTotal() - (double)$refund_amount;
        $orderService->updateOrder(['total' => $newTotal, 'refund' => $refund], $order_id);
        $wpdb->insert("{$wpdb->prefix}ahm_refunds", array('order_id' => $order_id, 'amount' => $refund_amount, 'reason' => sanitize_textarea_field(wpdm_query_var('reason', 'txt')), 'date' => time()));

        do_action("wpdmpp_admin_order_refund_added", wpdm_query_var('order_id', 'txt'), $refund_amount);

        wp_send_json(array('msg' => sprintf(__('%s refunded', "wpdm-premium-packages"), wpdmpp_price_format($refund_amount)), 'amount' => '-' . wpdmpp_price_format($refund), 'total' => wpdmpp_price_format($newTotal)));
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(): void
    {
        global $wpdb;

        __::isAuthentic('wpdmppasyncrequest', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP);

        $status = sanitize_text_field($_POST['status']);
        $order_id = sanitize_text_field($_POST['order_id']);

        $settings = maybe_unserialize(get_option('_wpdmpp_settings'));

        $update_data = array();

        if ($status == 'Renew') {
            $orderService = OrderService::instance();
            $order = $orderService->getOrder($order_id);
            $orderService->renewOrder($order_id, $order ? $order->getTransactionId() : '', false);

            wp_die(esc_html__('Order Renewed Successfully!', 'wpdm-premium-packages'));
        }

        $update_data['order_status'] = $status;

        $wpdb->update("{$wpdb->prefix}ahm_orders", $update_data, array('order_id' => $order_id));

        $siteurl = home_url("/");
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ahm_orders WHERE order_id = %s", $order_id));
        $user_info = get_userdata($order->uid);
        $admin_email = get_bloginfo("admin_email");

        $logo = isset($settings['logo_url']) && $settings['logo_url'] != "" ? "<img src='{$settings['logo_url']}' alt='" . get_bloginfo('name') . "'/>" : get_bloginfo('name');

        $params = array(
            'date' => date(get_option('date_format'), time()),
            'homeurl' => home_url('/'),
            'sitename' => get_bloginfo('name'),
            'order_link' => "<a href='" . wpdmpp_orders_page('id=' . $order_id) . "'>" . wpdmpp_orders_page('id=' . $order_id) . "</a>",
            'to_email' => $user_info->user_email,
            'orderid' => $order_id,
            'order_url' => wpdmpp_orders_page('id=' . $order_id),
            'order_status' => $status,
            'order_url_admin' => admin_url('edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . $order_id),
            'img_logo' => $logo
        );

        do_action("wpdmpp_admin_order_status_updated", $order_id, $status);

        \WPDM\__\Email::send("os-notification", $params);
        wp_die(esc_html__('Order status updated', 'wpdm-premium-packages'));
    }

    /**
     * Update payment method
     */
    public function updatePaymentMethod(): void
    {
        global $wpdb;
        __::isAuthentic('wpdmppasyncrequest', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP);

        $wpdb->update("{$wpdb->prefix}ahm_orders", array('payment_method' => sanitize_text_field($_POST['pm'])), array('order_id' => sanitize_text_field($_POST['order_id'])));

        do_action("wpdmpp_admin_payment_method_updated", wpdm_query_var('order_id', 'txt'), wpdm_query_var('pm', 'txt'));

        wp_send_json(array('msg' => __('Payment method updated', "wpdm-premium-packages"), 'pmname' => str_replace("wpdm_", "", sanitize_text_field($_POST['pm']))));
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(): void
    {
        global $wpdb;
        __::isAuthentic('wpdmppasyncrequest', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP);
        $wpdb->update("{$wpdb->prefix}ahm_orders", array('payment_status' => sanitize_text_field($_POST['status'])), array('order_id' => sanitize_text_field($_POST['order_id'])));

        do_action("wpdmpp_admin_payment_status_updated", wpdm_query_var('order_id', 'txt'), wpdm_query_var('status', 'txt'));

        wp_die(esc_html__('Payment status updated', 'wpdm-premium-packages'));
    }

    /**
     * Change transaction ID
     */
    public function changeTransactionId(): void
    {
        global $wpdb;
        __::isAuthentic('ctinonce', WPDM_PRI_NONCE, WPDM_MENU_ACCESS_CAP);

        $wpdb->update("{$wpdb->prefix}ahm_orders", array('trans_id' => sanitize_text_field($_POST['trans_id'])), array('order_id' => sanitize_text_field($_POST['order_id'])));

        wp_die(esc_html__('Transaction ID updated', 'wpdm-premium-packages'));
    }

    /**
     * Update order expiry date
     */
    public function updateOrderExpiryDate(): void
    {
        global $wpdb;

        __::isAuthentic('wpdmppuednonnce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP, true);

        $order_id = wpdm_query_var('order_id', 'txt');

        if (wpdm_query_var('renew', 'int') === 1) {
            $orderService = OrderService::instance();
            $order = $orderService->getOrder($order_id);
            $renewdate = wpdm_query_var('renewdate', 'txt');
            $renewdate_timestamp = strtotime($renewdate);
            if ($renewdate_timestamp === false) {
                $renewdate_timestamp = time();
            }
            //wpdmdd($renewdate, $renewdate_timestamp);
            $_renewdate = $orderService->renewOrder($order_id, $order ? $order->getTransactionId() : '', false, $renewdate_timestamp);

            $display_date = wp_date(get_option('date_format')." ".get_option('time_format'), $_renewdate);
        } else {
            $expiredate = wpdm_query_var('expiredate', 'txt');
            $expiredate_timestamp = strtotime(sanitize_text_field($expiredate));
            if ($expiredate_timestamp === false) {
                $expiredate_timestamp = time() + (365 * 86400);
            }
            $wpdb->update("{$wpdb->prefix}ahm_orders", array('expire_date' => $expiredate_timestamp), array('order_id' => $order_id));
            $display_date = $expiredate;
        }

        do_action("wpdmpp_admin_order_expiry_updated", $order_id);

        wp_send_json(array('msg' => __('Order expiry date updated', "wpdm-premium-packages"), 'date' => sanitize_text_field($display_date)));
    }

    /**
     * Delete a renewal entry
     */
    public function deleteRenewEntry(): void
    {
        if (wp_verify_nonce(wpdm_query_var('_dre', 'txt'), NONCE_KEY) && current_user_can(WPDM_ADMIN_CAP)) {
            global $wpdb;
            $renew = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ahm_order_renews WHERE ID = %d", wpdm_query_var('id', 'int')));
            $orderService = OrderService::instance();
            $order = $orderService->getOrder($renew->order_id);
            $wpdb->delete("{$wpdb->prefix}ahm_order_renews", array('ID' => wpdm_query_var('id', 'int')));

            $sup_period = get_wpdmpp_option('order_validity_period', 365) * 86400;
            $newExpireDate = $order ? $order->getExpireDate() - $sup_period : 0;
            $orderData = [ 'expire_date' => $newExpireDate ];
            $newExpireDateTS = strtotime($newExpireDate);
            if($newExpireDateTS < time()) {
                $orderData['order_status'] = 'Expired';
                $orderData['payment_status'] = 'Expired';
            }
            $orderService->updateOrder($orderData, $renew->order_id);

            wp_send_json(array('msg' => 'Deleted', 'success' => 1));
        }
    }

    /**
     * Update order renewal totals
     */
    public function updateOrderRenews(): void
    {
        __::isAuthentic('__rennonce', WPDM_PRI_NONCE, 'manage_options');

        global $wpdb;
        $total = wpdm_query_var('total', 'int');
        if ($total === 0) {
            $total = $wpdb->get_var("select count(order_id) as total from {$wpdb->prefix}ahm_orders where (order_status='Completed' or order_status='Expired')");
        }
        $items_per_page = 20;
        $total_pages = $total / $items_per_page;
        $current_page = wpdm_query_var('cp', 'int');
        $current_page = $current_page > 0 ? $current_page : 1;
        $start = ($current_page - 1) * $items_per_page;
        $orders = $wpdb->get_results($wpdb->prepare("SELECT order_id, total FROM {$wpdb->prefix}ahm_orders ORDER BY `date` DESC LIMIT %d, %d", $start, $items_per_page));
        foreach ($orders as $order) {
            $wpdb->update("{$wpdb->prefix}ahm_order_renews", ['total' => $order->total], ['order_id' => $order->order_id]);
        }
        $response['continue'] = $current_page < $total_pages;
        $response['total'] = $total;
        $response['nextpage'] = $current_page + 1;
        $response['progress'] = (($start + $items_per_page) / $total) * 100;
        $response['progress'] = min($response['progress'], 100);

        wp_send_json($response);
    }

    /**
     * Edit order (add/remove product)
     */
    public function editOrder(): void
    {
        __::isAuthentic('__eononce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP);

        global $wpdb;
        $orderId = wpdm_query_var('order');

        if (wpdm_query_var('task') === 'add_product') {
            $pid = wpdm_query_var('product', 'int');
            $license = wpdm_query_var('license', 'txt');
            $price = (float) get_post_meta($pid, '__wpdm_base_price', true);
            $wpdb->insert("{$wpdb->prefix}ahm_order_items", [
                'oid' => $orderId,
                'pid' => $pid,
                'price' => $price,
                'quantity' => 1,
                'license' => $license,
                'date' => time(),
            ]);
        }

        if (wpdm_query_var('task') === 'remove_product') {
            $pid = wpdm_query_var('product', 'int');
            $wpdb->delete("{$wpdb->prefix}ahm_order_items", ['oid' => $orderId, 'pid' => $pid]);
        }

        wp_send_json(['success' => true]);
    }

    /**
     * Save a custom order
     */
    public function saveCustomOrder(): void
    {
        if (wp_verify_nonce(wpdm_query_var('__nonce'), NONCE_KEY) && current_user_can('manage_options')) {
            $orderService = OrderService::instance();
            $cartItems = WPDMPP()->cart->getCart();
            $order = $orderService->createFromCart($cartItems, [], 'Cash');
            if ($order) {
                $order_id = $order->getOrderId();
                $orderService->updateOrder(['uid' => 0], $order_id);
                $orderService->completeOrder($order_id, false);
                // Clear the cart so the next manual order starts empty instead of
                // inheriting the items just saved.
                WPDMPP()->cart->clear();
                wp_send_json(array('status' => 1, 'oid' => $order_id));
            }
        }
        wp_send_json(array('status' => 0));
    }

    /**
     * Load download history for an order
     */
    public function loadDownloadHistory(): void
    {
        if (!wp_verify_nonce(wpdm_query_var('__dlhnonce', 'txt'), NONCE_KEY)) {
            wp_send_json_error(array('message' => 'Nonce key is expired, refresh the page and try again.'), 403);
        }

        if (!current_user_can(WPDMPP_ADMIN_CAP)) {
            wp_send_json_error(array('message' => 'Permission denied'), 403);
        }

        $oid = wpdm_query_var('oid', 'txt');
        global $wpdb;
        $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ahm_download_stats WHERE oid = %s ORDER BY `timestamp` DESC", $oid));

        echo "<table class='table table-striped'><tr><th>" . esc_html__('IP', 'wpdm-premium-packages') . "</th><th style='text-align: right;'>" . esc_html__('Download Time', 'wpdm-premium-packages') . "</th></tr>";
        foreach ($data as $d) {
            $time = wp_date(get_option('date_format') . " " . get_option('time_format'), $d->timestamp);
            $ip = $d->ip != '' ? esc_html($d->ip) : '██████████';
            echo "<tr><td>" . $ip . "</td><td style='text-align: right;'>" . esc_html($time) . "</td></tr>";
        }
        echo "</table>";
        wp_die();
    }

    /**
     * Load ACR activity log for an order
     */
    public function acrActivityLog(): void
    {
        __::isAuthentic('__acr_nonce', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP);
        global $wpdb;
        $order_id = wpdm_query_var('order_id', 'txt');
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ahm_acr_emails WHERE order_id = %s", $order_id));
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found', 'wpdm-premium-packages')]);
        }
        // Entries are stored keyed by an integer timestamp, so the JSON is an OBJECT,
        // not an array. Decode associatively (true) or json_decode() returns a stdClass
        // and the is_array() gate below silently skips every entry (blank modal).
        $logs = json_decode($order->activity_log, true);
        echo "<ul class='list-group'>";
        if (is_array($logs) && !empty($logs)) {
            foreach ($logs as $key => $log) {
                // 'time' may be an int timestamp or a pre-formatted date string; the key is the timestamp.
                $time = isset($log['time']) ? $log['time'] : $key;
                $date = is_numeric($time) ? wp_date(get_option('date_format'), (int) $time) : (string) $time;
                $msg  = isset($log['msg']) ? $log['msg'] : '';
                echo "<li class='list-group-item'>" . esc_html($msg) . " &mdash; <span class='color-purple'>" . esc_html($date) . "</span></li>";
            }
        } else {
            echo "<li class='list-group-item text-muted'>" . esc_html__('No activity yet', 'wpdm-premium-packages') . "</li>";
        }
        echo "</ul>";
        wp_die();
    }

    /**
     * Generate cart HTML for custom order builder
     */
    public function generateCartHTML($cart_data = null): void
    {
        // Skip nonce check when called internally from afterAddToCart()
        if ($cart_data === null) {
            __::isAuthentic('wpdmpp_cart_nonce', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP);
        }
        $cart_data = $cart_data ?: WPDMPP()->cart->getItems();
        ob_start();
        foreach ($cart_data as $id => $info) {
            $license_name = wpdm_valueof($info, 'license/info/name');
            ?>
            <tr id="citem-<?php echo esc_attr($id); ?>">
                <td align="left"><strong><?php echo esc_html($info['product_name']); ?></strong><?php echo $license_name ? '  &mdash; ' . esc_html($license_name) . ' License' : ''; ?></td>
                <td align="left"><?php echo esc_html(wpdmpp_price_format($info['price'])); ?></td>
                <td align="left"><?php echo (int)$info['quantity']; ?></td>
                <td align="right" style="width: 150px;text-align: right"><?php echo esc_html(wpdmpp_price_format($info['price'] * $info['quantity'])); ?></td>
                <td align="right" style="width: 60px;text-align: right"><button type="button" data-pid="<?php echo esc_attr($id); ?>" class="btn btn-xs btn-danger btn-delete-cart-item"><?php echo \WPDMPP\UI\Icons::get('trash', 14); ?></button></td>
            </tr>
            <?php
        }
        $html = ob_get_clean();
        wp_send_json(['cart_html' => $html, 'cart_total' => wpdmpp_price_format(wpdmpp_get_cart_total())]);
    }

    /**
     * Empty the admin cart
     */
    public function emptyCart(): void
    {
        __::isAuthentic('wpdmpp_cart_nonce', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP);
        WPDMPP()->cart->clear();
        wp_send_json(['success' => true]);
    }

    /**
     * Add order note (admin AJAX handler)
     */
    public function addNote(): void
    {
        __::isAuthentic('wpdmppasyncrequest', WPDM_PUB_NONCE, 'read');

        $id = sanitize_text_field($_REQUEST['order_id']);
        $note = wp_kses($_REQUEST['note'], array('strong' => array(), 'b' => array(), 'br' => array(), 'p' => array(), 'hr' => array(), 'a' => array('href' => array(), 'title' => array())));
        $note = wpdm_escs($note);

        $options = [];
        if (isset($_REQUEST['admin']))    $options['admin']    = true;
        if (isset($_REQUEST['seller']))   $options['seller']   = true;
        if (isset($_REQUEST['customer'])) $options['customer'] = true;
        if (isset($_REQUEST['file']))     $options['files']    = wpdm_sanitize_array($_REQUEST['file']);

        $data = OrderService::instance()->addNote($id, $note, $options);

        if ($data === false) {
            echo "error";
            return;
        }

        $time = $data['time'];

        // Admin Bootstrap markup
        $copy = array();
        if (isset($data['admin'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Admin &nbsp; ';
        if (isset($data['seller'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Seller &nbsp; ';
        if (isset($data['customer'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Customer &nbsp; ';
        $copy = implode("", $copy);
        ?>
        <div class="panel panel-default card mb-3">
            <div class="panel-body card-body">
                <?php $noteHtml = wpautop(strip_tags(stripcslashes($data['note']), "<a><strong><b><img>"));
                echo preg_replace('/((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a target="_blank" href="\1">\1</a>', $noteHtml); ?>
            </div>
            <?php if (isset($data['file']) && is_array($data['file'])) { ?>
                <div class="panel-footer card-footer text-right">
                    <?php foreach ($data['file'] as $file) { ?>
                        <a href="#" style="margin-left: 10px"><?php echo \WPDMPP\UI\Icons::get('paperclip', 14); ?> <?php echo esc_attr($file); ?></a> &nbsp;
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="panel-footer card-footer text-right"><small><em><?php echo \WPDMPP\UI\Icons::get('clock', 12); ?> <?php echo date(get_option('date_format') . " h:i", $time); ?></em></small>
                <div class="pull-left"><small><em><?php if ($copy != '') echo "Copy sent to " . $copy; ?></em></small></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the orders page
     *
     * Handles listing, viewing, and managing orders.
     *
     * @return void
     */
    public function render(): void
    {
        if (!current_user_can(WPDM_MENU_ACCESS_CAP)) {
            return;
        }

        global $wpdb;

        $orderObj = OrderService::instance();
        $l = 15;
        $currency_sign = wpdmpp_currency_sign();
        $p = wpdm_query_var('paged', 'int');
        $p = $p > 0 ? $p : 1;
        $s = ($p - 1) * $l;
        $order_id = isset($_GET['id']) && is_array($_GET['id']) ? wpdm_sanitize_array($_GET['id']) : wpdm_query_var('id', 'txt');
        $msg = '';

        // Handle delete single order. Requires the same capability as the async
        // bulk delete, and routes through deleteOrder() so items, renewals and meta
        // are cascaded and the wpdmpp_order_deleted hook fires (raw SQL did neither).
        if (isset($_GET['task']) && $_GET['task'] == 'delete_order'
            && current_user_can(WPDMPP_ADMIN_CAP)
            && wp_verify_nonce(wpdm_query_var('_wpnonce'), 'wpdmpp_delete_order')) {
            $order_id = wpdm_query_var('id', 'txt');
            $msg = $orderObj->deleteOrder($order_id)
                ? sprintf(__('Order (%s) is deleted successfully', 'wpdm-premium-packages'), $order_id)
                : __('Could not delete the order.', 'wpdm-premium-packages');
        }
        // Bulk delete (selected orders) and delete-by-payment-status are now handled
        // asynchronously via the wp_ajax_wpdmpp_delete_orders endpoint (deleteOrders()).

        // Include the orders template
        $this->includeView('orders', compact('orderObj', 'l', 'currency_sign', 'p', 's', 'order_id', 'msg'));
    }

    /**
     * Include a view template
     *
     * @param string $view View name
     * @param array $data Variables to extract
     * @return void
     */
    private function includeView(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        // Try new path first
        $templatePath = __DIR__ . '/views/' . $view . '.php';
        if (file_exists($templatePath)) {
            include $templatePath;
            return;
        }

        // Fallback to old path
        $legacyPath = WPDMPP_BASE_DIR . 'includes/menus/templates/orders/' . $view . '.php';
        if (file_exists($legacyPath)) {
            include $legacyPath;
        }
    }
}
