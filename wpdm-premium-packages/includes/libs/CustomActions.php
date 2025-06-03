<?php
namespace WPDMPP\Libs;

use WPDM\__\__;
use WPDM\__\FileSystem;
use WPDM\__\Session;
use WPDMPP\Product;
use WPDMPP\WPDMPremiumPackage;

if (!defined('ABSPATH')) {
    exit;
}

class CustomActions {

    function __construct()
    {

    }

    function execute(){
        add_action('wp_ajax_delete_renew_entry', array($this, 'deleteRenewEntry'));
        //add_action("init", array($this, "getLicenseDetails"), 1);
        add_action("wp_ajax_wpdmpp_admin_cart_html", array($this, "generateCartHTML"));
        add_action("wp_ajax_wpdmpp_empty_cart", array($this, "emptyCart"));
        add_action("wp_ajax_wpdmpp_change_transection_id", array($this, "changeTransectionID"));
        add_action("wp_ajax_wpdmpp_admin_save_custom_order", array($this, "saveCustomOrder"));
        add_action("wp_ajax_wpdmpp_download_hostory", array($this, "loadDownloadHistory"));
        add_action("wp_ajax_wpdmpp_change_payout_status", array($this, "changePayoutStatus"));
        add_action("wp_ajax_wpdmpp_load_buynow_button", array($this, "buyNowButton"));
        add_action("wp_ajax_nopriv_wpdmpp_load_buynow_button", array($this, "buyNowButton"));
        add_action("wp_ajax_wpdmpp_updateOrderExpiryDate", array($this, "updateOrderExpiryDate"));
        add_action("wp_ajax_wpdmpp_recalculateCustomerValue", array($this, "recalculateCustomerValue"));
        add_action("wp_ajax_wpdmpp_updateOrderRenews", array($this, "updateOrderRenews"));
        add_action("wp_ajax_wpdmpp_acr_activity_log", array($this, "acrActivityLog"));

        add_action("wp_ajax_wpdmpp_payout_settings", array($this, "payoutSettings"));


        add_action("wpdmpp_after_addtocart", array($this, 'afterAddToCart'));

        //add_filter("wpdm_admin_page_header_menus/templates", [$this, 'templatesMenu']);
        //add_filter("wpdm_menu_page_content/templates/note_templates", [$this, 'noteTemplates']);

        add_action("wp_ajax_wpdmpp_edit_order", [$this, "editOrder"]);
        add_action("wp_ajax_wpdmpp_payment_intent", [$this, "payNow"]);
    }

	function orderConfirmationEmail() {
        __::isAuthentic('ocemnonce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP, true);
        Order::sendConfirmationEmail(wpdm_query_var('order_id'));
        wp_send_json(['success' => true, 'msg' => __('Order confirmation email sent!', WPDMPP_TEXT_DOMAIN)]);
    }

    function afterAddToCart($cart_data)
    {
        if(isset($_REQUEST['custom_order'])) {
           $this->generateCartHTML($cart_data);
        }
    }

    /**
     * @usage Refund order
     */
    function addRefund(){
        global $wpdb;
        __::isAuthentic('wpdmpparnonnce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP, true);
        $refund_amount = wpdm_query_var('refund', 'double');
        $order = new Order(wpdm_query_var('order_id'));
        $refund = (double)$order->refund + (double)$refund_amount;
        $order->set('total', (double)$order->total - (double)$refund_amount);
        $order->set('refund', $refund);
        $order->save();
        $wpdb->insert("{$wpdb->prefix}ahm_refunds", array('order_id' => wpdm_query_var('order_id'), 'amount' => $refund_amount, 'reason' => wpdm_query_var('reason'), 'date' => time()));

        do_action("wpdmpp_admin_order_refund_added", wpdm_query_var('order_id', 'txt'), $refund_amount);

        wp_send_json(array('msg' => sprintf(__('%s refunded',"wpdm-premium-packages"), wpdmpp_price_format($refund_amount)), 'amount' => '-'.wpdmpp_price_format($refund), 'total' => wpdmpp_price_format($order->total)));
    }

    function recalculateCustomerValue()
    {
        __::isAuthentic('__rcvnonce', WPDM_PRI_NONCE, 'manage_options');

        global $wpdb;
        $total = $wpdb->get_var("select count(DISTINCT uid) from {$wpdb->prefix}ahm_orders where (order_status='Completed' or order_status='Expired') and uid > 0");
        $items_per_page = 20;
        $total_pages = $total / $items_per_page;
        $current_page = wpdm_query_var('cp', 'int');
        $current_page = $current_page > 0 ? $current_page : 1;
        $start = ($current_page - 1) * $items_per_page;
        //select uid, count(order_id) total_orders, SUM(total) as total_purchases from {$wpdb->prefix}ahm_orders where order_status='Completed' and uid > 0 group by uid ORDER BY total_purchases DESC limit 0, 20
        $customers = $wpdb->get_results("select uid, count(order_id) total_orders, SUM(total) as total_purchases from {$wpdb->prefix}ahm_orders where order_status='Completed' and uid > 0 group by uid ORDER BY total_purchases DESC limit $start, $items_per_page");
        foreach ($customers as $customer) {
            User::calculateSpent($customer->uid);
            $user = get_user_by('id', $customer->uid);
            $user->add_role('wpdmpp_customer');
        }
	    $response['continue'] = $current_page < $total_pages;
	    $response['total'] = $total;
	    $response['nextpage'] = $current_page + 1;
	    $response['progress'] = (($start + $items_per_page)/$total)*100;
	    $response['progress'] = min( $response['progress'], 100 );

	    wp_send_json($response);

    }

    function updateOrderRenews()
    {
        __::isAuthentic('__rennonce', WPDM_PRI_NONCE, 'manage_options');

        global $wpdb;
	    $total = wpdm_query_var('total', 'int');
        if($total === 0)
            $total = $wpdb->get_var("select count(order_id) as total from {$wpdb->prefix}ahm_orders where (order_status='Completed' or order_status='Expired')");
        $items_per_page = 20;
        $total_pages = $total / $items_per_page;
        $current_page = wpdm_query_var('cp', 'int');
        $current_page = $current_page > 0 ? $current_page : 1;
        $start = ( $current_page -1 ) * $items_per_page;
        //select uid, count(order_id) total_orders, SUM(total) as total_purchases from {$wpdb->prefix}ahm_orders where order_status='Completed' and uid > 0 group by uid ORDER BY total_purchases DESC limit 0, 20
        $orders = $wpdb->get_results("select order_id, total from {$wpdb->prefix}ahm_orders order by `date` desc limit $start, $items_per_page");
        foreach ($orders as $order) {
            $wpdb->update("{$wpdb->prefix}ahm_order_renews", ['total' => $order->total], ['order_id' => $order->order_id]);
        }
	    $response['continue'] = $current_page < $total_pages;
	    $response['total'] = $total;
	    $response['nextpage'] = $current_page + 1;
	    $response['progress'] = (($start + $items_per_page)/$total)*100;
	    $response['progress'] = min( $response['progress'], 100 );

	    wp_send_json($response);

    }

    /**
     * @usage Change Payment Method
     */
    function updateOrderExpiryDate(){
        global $wpdb;

	    __::isAuthentic('wpdmppuednonnce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP, true);

        $order_id = wpdm_query_var('order_id', 'txt');

        if(wpdm_query_var('renew', 'int') === 1) {
            $order = new Order($order_id);
            Order::renewOrder($order_id, $order->trans_id, false, strtotime(wpdm_query_var('renewdate')));
        }
        else
            $wpdb->update("{$wpdb->prefix}ahm_orders", array( 'expire_date' => strtotime($_POST['expiredate']) ), array('order_id'=> sanitize_text_field( $_POST['order_id']) ) );

        do_action("wpdmpp_admin_order_expiry_updated", wpdm_query_var('order_id', 'txt'));

        wp_send_json(array('msg' => __('Order expiry date updated',"wpdm-premium-packages"), 'date' => $_POST['expiredate'] ));
    }

    /**
     * @usage Change Payment Method
     */
    function updatePM(){
        global $wpdb;
	    __::isAuthentic('wpdmppasyncrequest', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP);

        $wpdb->update("{$wpdb->prefix}ahm_orders",array('payment_method'=> sanitize_text_field($_POST['pm']) ),array('order_id'=> sanitize_text_field( $_POST['order_id']) ) );

        do_action("wpdmpp_admin_payment_method_updated", wpdm_query_var('order_id', 'txt'), wpdm_query_var('pm', 'txt'));

        wp_send_json(array('msg' => __('Payment method updated',"wpdm-premium-packages"), 'pmname' => str_replace("wpdm_", "", sanitize_text_field($_POST['pm']))));
    }

    /**
     * @usage Updates Payment Status
     */
    function updatePS(){
        global $wpdb;
	    __::isAuthentic('wpdmppasyncrequest', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP);
        $wpdb->update("{$wpdb->prefix}ahm_orders",array('payment_status'=> sanitize_text_field($_POST['status']) ),array('order_id'=> sanitize_text_field( $_POST['order_id']) ) );

        do_action("wpdmpp_admin_payment_status_updated", wpdm_query_var('order_id', 'txt'), wpdm_query_var('status', 'txt'));

        die(__('Payment status updated',"wpdm-premium-packages"));
    }

    /**
     * @usage Updates Payment Status
     */
    function changeTransectionID(){
        global $wpdb;
        if(!current_user_can(WPDMPP_MENU_ACCESS_CAP)) return;

        $ret = $wpdb->update("{$wpdb->prefix}ahm_orders",array('trans_id'=> sanitize_text_field($_POST['trans_id']) ),array('order_id'=> sanitize_text_field( $_POST['order_id']) ) );

        die(__('Transection ID updated',"wpdm-premium-packages"));
    }


    /**
     * @usage Updates Order Status
     */
    function updateOS(){
        global $wpdb;

	    __::isAuthentic('wpdmppasyncrequest', WPDM_PRI_NONCE, WPDMPP_ADMIN_CAP);

        $status = sanitize_text_field($_POST['status']);
        $order_id = sanitize_text_field($_POST['order_id']);

        $settings = maybe_unserialize(get_option('_wpdmpp_settings'));

        $update_data = array();

        if($status == 'Renew'){
            //$status = 'Completed';
            //$update_data['payment_status'] = 'Completed';
            //$update_data['expire_date'] = strtotime("+".$settings['order_validity_period']." days");
            $order = new Order($order_id);
            Order::renewOrder($order_id, $order->trans_id, false);

            die(__('Order Renewed Successfully!',"wpdm-premium-packages"));
        }

        $update_data['order_status'] = $status;

        $wpdb->update("{$wpdb->prefix}ahm_orders", $update_data,array('order_id'=>$order_id));

        //Let the customer of that order know about order status change
        $siteurl = home_url("/");
        $order = $wpdb->get_row("select * from {$wpdb->prefix}ahm_orders where order_id='".$order_id."'");
        $user_info = get_userdata($order->uid);
        $admin_email = get_bloginfo("admin_email");


        $logo = isset($settings['logo_url'])&&$settings['logo_url']!=""?"<img src='{$settings['logo_url']}' alt='".get_bloginfo('name')."'/>":get_bloginfo('name');

        $params = array(
            'date' => date(get_option('date_format'),time()),
            'homeurl' => home_url('/'),
            'sitename' => get_bloginfo('name'),
            'order_link' => "<a href='".wpdmpp_orders_page('id='.$order_id)."'>".wpdmpp_orders_page('id='.$order_id)."</a>",
            'to_email' => $user_info->user_email,
            'orderid' => $order_id,
            'order_url' => wpdmpp_orders_page('id='.$order_id),
            'order_status' => $status,
            'order_url_admin' => admin_url('edit.php?post_type=wpdmpro&page=orders&task=vieworder&id='.$order_id),
            'img_logo' => $logo
        );

        do_action("wpdmpp_admin_order_status_updated", $order_id, $status);

        \WPDM\__\Email::send("os-notification", $params);
        die(__('Order status updated',"wpdm-premium-packages"));
    }

    /**
     * @usage Payment for Order
     * @param array $post_data
     */
    function payNow($post_data = array()){
        global $wpdb, $current_user;
        $current_user = wp_get_current_user();
        if(!$post_data || count($post_data) == 0) $post_data = wpdm_sanitize_array($_POST);
        $order = new Order();
        $corder = $order->getOrder(sanitize_text_field($post_data['order_id']));
        $payment = new Payment();
        wpdmpp_empty_cart();

        Session::set('renew_orderid', $corder->order_id);

        $total = $corder->total; //Order::recalculateTotal($corder->order_id);

        $items = Order::getOrderItems($corder->order_id);

        if(!isset($post_data['payment_method']) || $post_data['payment_method'] == '') {
            $post_data['payment_method'] = $corder->payment_method;
        }

        $payment->InitiateProcessor(sanitize_text_field($post_data['payment_method']));
        $payment->Processor->OrderTitle = "Order# ".$corder->order_id;

        if($corder->order_status == 'Expired')
            $payment->Processor->InvoiceNo = $corder->order_id."_renew_".date("Ymd");
        else
            $payment->Processor->InvoiceNo = $corder->order_id;

        $payment->Processor->Custom = $corder->order_id;
        $payment->Processor->Amount = number_format($total,2,".","");

        //echo $payment->Processor->InvoiceNo;die();
        //$payment->Processor->Amount = wpdmpp_get_cart_total();

        ob_start();
        $billing_required = isset($payment->Processor->billing) ? (int)$payment->Processor->billing : 0;
        $billing = [];
        if(is_user_logged_in()) {
            $billing          = maybe_unserialize( get_user_meta( get_current_user_id(), 'user_billing_shipping', true ) );
            $billing          = is_array( $billing ) && isset( $billing['billing'] ) ? $billing['billing'] : $billing;
            if(!is_array($billing)) $billing = [];
            $billing['email'] = isset( $billing['email'] ) ? $billing['email'] : $current_user->user_email;
        }

	    $checkout_page = get_wpdmpp_option( 'checkout_page_style', '-2col', 'username' );
        if (get_wpdmpp_option('billing_address') == 1 || wpdmpp_tax_active() || $billing_required) {
            // Ask Billing Address When Checkout
            include \WPDM\__\Template::locate("checkout-cart{$checkout_page}/checkout-billing-info.php", dirname(__FILE__) . '/templates'.WPDM()->bsversion."/", WPDMPP_TPL_FALLBACK);
        } else {
            // Ask only Name and Email When Checkout
            include \WPDM\__\Template::locate("checkout-cart{$checkout_page}/checkout-name-email.php", dirname(__FILE__) . '/templates'.WPDM()->bsversion."/", WPDMPP_TPL_FALLBACK);
        }
        $billing_form = ob_get_clean();

        if(method_exists($payment->Processor, 'customPayButton')){
            $cb = $payment->Processor->customPayButton($corder->order_id);
            if($cb != '') {
                echo $cb;
                die();
            }
        }
        echo $payment->Processor->ShowPaymentForm(1);
        die();
    }

    function templatesMenu($items)
    {
        $items[] = ['link' => "edit.php?post_type=wpdmpro&page=templates&_type=note_templates", "name" => __("Order Note Templates", "download-manager"), "active" => (wpdm_query_var('_type') === 'note_templates')];
        return $items;
    }

    function noteTemplates()
    {
        include WPDMPP_BASE_DIR.'includes/menus/templates/note-templates.php';
    }


    /**
     * Add Order Note ( Called through wpdmpp_async_request function )
     */
    function addNote(){
	    __::isAuthentic('wpdmppasyncrequest', WPDM_PUB_NONCE, 'read');
        global $wpdb;
        $id = sanitize_text_field($_REQUEST['order_id']);
        $note = wp_kses($_REQUEST['note'], array('strong' => array(), 'b' => array(), 'br' => array(), 'p' => array(), 'hr' => array(), 'a' => array('href' => array(), 'title' => array())));
        $note = wpdm_escs($note);
        $data = array('note' => $note );
        if(isset($_REQUEST['admin'])) $data['admin'] = 1;
        if(isset($_REQUEST['seller'])) $data['seller'] = 1;
        if(isset($_REQUEST['customer'])) $data['customer'] = 1;
        if(isset($_REQUEST['file'])) $data['file'] = wpdm_sanitize_array($_REQUEST['file']);

        if( Order::add_note($id, $data)) {

            $copy = array();
            if(isset($data['admin'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Admin &nbsp; ';
            if(isset($data['seller'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Seller &nbsp; ';
            if(isset($data['customer'])) $copy[] = '<input type=checkbox checked=checked disabled=disabled /> Customer &nbsp; ';
            $copy = implode("", $copy);
            ?>

            <div class="panel panel-default card mb-3">
                <div class="panel-body card-body">
                    <?php $note = wpautop(strip_tags(stripcslashes($data['note']),"<a><strong><b><img>")); echo preg_replace('/((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a target="_blank" href="\1">\1</a>', $note); ?>
                </div>
                <?php if(isset($_REQUEST['file'])){ ?>
                    <div class="panel-footer card-footer text-right">
                        <?php foreach($_REQUEST['file'] as $file){ ?>
                            <a href="#" style="margin-left: 10px"><i class="fa fa-paperclip"></i> <?php echo esc_attr($file); ?></a> &nbsp;
                        <?php } ?>
                    </div>
                <?php } ?>
                <div class="panel-footer card-footer text-right"><small><em><i class="fa fa-clock-o"></i> <?php echo date(get_option('date_format') . " h:i", time()); ?></em></small>
                    <div class="pull-left"><small><em><?php if($copy!='') echo "Copy sent to ".$copy; ?></em></small></div>
                </div>
            </div>
        <?php }
        else
            echo "error";
    }

    /**
     * Verify License key
     */
    function verifyLicense(){
        global $wpdb, $wpdmpp_settings;
        $key = esc_sql($_POST['key']);
        $domain = esc_sql($_POST['domain']);
        $data = $wpdb->get_row("select l.*,o.uid,o.payment_status,o.order_status, o.expire_date as order_expire_date from {$wpdb->prefix}ahm_licenses l,{$wpdb->prefix}ahm_orders o where l.licenseno='$key' and l.oid=o.order_id and o.order_status IN ('Completed','Expired')",ARRAY_A);

        if(!$data)
            die('invalid');

        $wpdmpp_settings['order_validity_period'] = (int)$wpdmpp_settings['order_validity_period'] > 0 ? (int)$wpdmpp_settings['order_validity_period'] : 365;
        if($data['order_expire_date'] == 0)
            $data['order_expire_date'] = $data['date'] + ($wpdmpp_settings['order_validity_period']*24*3600);

        if($data['order_expire_date'] < time()) $data['order_status'] = 'Expired';

        if($data['order_status'] === 'Expired' && !isset($wpdmpp_settings['license_key_validity']))
            die('invalid');


        if($data['domain'] == ''){
            $domain = serialize(array($domain));
            $dt = time();
            //$copy = get_post_meta($data['pid'],'__wpdm_license_usage_limit', true);
            //if(!$copy) $copy = 1;
            //$lic = $wpdb->get_var("select license from {$wpdb->prefix}ahm_license where oid = '' and pid = ''");
            $wpdb->query("update {$wpdb->prefix}ahm_licenses set domain = '{$domain}',activation_date='{$dt}' where licenseno='$key'");
            die('valid');
        }
        elseif((@in_array($domain,@maybe_unserialize($data['domain']))||$domain==$data['domain']))
            die('valid');
        elseif(count(unserialize($data['domain']))<$data['domain_limit']&&!in_array($domain,@unserialize($data['domain']))){
            $data['domain'] = unserialize($data['domain']);
            $data['domain'][] = $domain;
            $domain = serialize($data['domain']);
            $wpdb->query("update {$wpdb->prefix}ahm_licenses set domain = '{$domain}' where licenseno='$key'");
            die('valid');
        }
        else
            die('invalid');
    }

    /**
     * Saves current cart
     */
    function saveCart(){
        $cartdata = wpdmpp_get_cart_data();
        $cartinfo = array('cartitems' => $cartdata, 'coupon' => wpdmpp_get_cart_coupon());
        $cartinfo = \WPDM\__\Crypt::Encrypt($cartinfo);
        $id = uniqid();
        file_put_contents(WPDM_CACHE_DIR.'saved-cart-'.$id.'.txt', $cartinfo);
        Session::set( 'savedcartid' ,  $id);
        $cart_url = wpdmpp_cart_page(array('savedcart' => $id));
        wp_send_json(array('success' => true, 'url' => $cart_url, 'id' => $id));
    }

    /**
     * Email the current cart link to the provided email address
     *

    function emailCart(){
        if(isset($_REQUEST['email']) && isset($_REQUEST['carturl'])){
            if(!is_email($_REQUEST['email'])) return;
            $cid = wpdmpp_sanitize_alphanum($_REQUEST['cartid']);
            $carturl = wpdmpp_cart_page("savedcart=".$cid);
            $params = array(
                'date'      => date(get_option('date_format'),time()),
                'homeurl'   => home_url('/'),
                'sitename'  => get_bloginfo('name'),
                'to_email'  => sanitize_email($_REQUEST['email']),
                'carturl'   => $carturl
            );

            \WPDM\__\Email::send("email-saved-cart", $params);

            do_action("wpdm_pp_emailed_cart", esc_url($_REQUEST['cartid']));

            die('sent');
        }
    }
    */

    function deleteRenewEntry(){
        if(wp_verify_nonce(wpdm_query_var('_dre', 'txt'), NONCE_KEY) && current_user_can(WPDM_ADMIN_CAP)){
           global $wpdb;
           $renew = $wpdb->get_row("select * from {$wpdb->prefix}ahm_order_renews where ID = '".wpdm_query_var('id', 'int')."'");
           $date = date("Ymd", $renew->date);
           $order = new Order($renew->order_id);
           $renewdate = date("Ymd", $order->expire_date);
           $wpdb->delete("{$wpdb->prefix}ahm_order_renews", array('ID' => wpdm_query_var('id', 'int')));

           $sup_period = get_wpdmpp_option('order_validity_period', 365)*86400;
           $order->expire_date = $order->expire_date - $sup_period;
           $order->update(array('expire_date' => $order->expire_date, 'order_status' => 'Expired', 'payment_status' => 'Expired'), $renew->order_id);

           wp_send_json(array('msg' => 'Deleted', 'success' => 1));
        }
    }

    function dieJson($array){
        header('Content-type: application/json');
        echo json_encode($array);
        die();
    }

    function getOrder($ID){
        global $wpdb;
        $order = new Order();
        $ex = strtotime("1 year ago");
        $o = $wpdb->get_row("select * from {$wpdb->prefix}ahm_orders where order_id = '{$ID}'");
        //precho($o); die();
        if($o->order_status == 'Expired') return $o;

        $o->expire_date = (int)$o->expire_date;
        if($o->expire_date <= 0) {
            $sup_period = get_wpdmpp_option('order_validity_period', 365)*86400;
            $o->expire_date = $o->date + $sup_period;
            $order->update(array('expire_date' => $o->expire_date), $o->order_id);
        }
        if((int)$o->expire_date < time() && $o->expire_date > 0){
            $order->Update(array('order_status' => 'Expired', 'payment_status' => 'Expired'), $o->order_id);
            $o->order_status = $o->payment_status = 'Expired';
        }

        return $o;

    }

    function editOrder()
    {
        __::isAuthentic('__eononce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP);

        if(wpdm_query_var('task') === 'add_product') {
            $order = new Order(wpdm_query_var('order'));
            $order->addItem(wpdm_query_var('product', 'int'), wpdm_query_var('license', 'txt'));
        }

	    if(wpdm_query_var('task') === 'remove_product') {
		    $order = new Order(wpdm_query_var('order'));
		    $order->removeItem(wpdm_query_var('product', 'int'));
	    }

        wp_send_json(['success' => true]);

    }


    function getLicenseDetails(){
        if(wpdm_query_var("wpdmppaction") == "getlicensedetails"){
            $data = \WPDM\__\Session::get("licensedetails");
            $seconds_to_cache = 86400;
            $lic = wpdm_query_var("license");
            if(!$data) {
                global $wpdb;
                $download_url = "";
                if(!$lic) die();
                $license = $wpdb->get_row("select * from {$wpdb->prefix}ahm_licenses where licenseno = '{$lic}'");
                if(!$license) $data = array('download_url' => '', 'order_id' => 'Not Fount!', 'license_key' => $lic, 'order_status' => 'Not Found!');
                else {
                    $order = $this->getOrder($license->oid);
                    //precho($order); die();
                    if (is_object($order)) {
                        if ($order->order_status === 'Completed' && $order->expire_date > time()) {
                            $files = get_post_meta($license->pid, '__wpdm_files', true);
                            foreach ($files as $index => $file) {
                                $download_url = WPDMPremiumPackage::customerDownloadURL($license->pid, $license->oid) . "&ind={$index}"; //home_url("/?wpdmdl={$license->pid}&oid={$license->oid}&ind=" . $index);
                                break;
                            }
                        } else {
                            $order->order_status = 'Expired';
                        }
                        $data = array('download_url' => $download_url, 'order_id' => $license->oid, 'order_status' => $order->order_status, 'expire' => $order->expire_date);
                    } else {
                        $data = array('download_url' => '', 'order_id' => null, 'license_key' => $lic, 'order_status' => 404);
                    }
                    \WPDM\__\Session::set("licensedetails", $data, $seconds_to_cache);
                }
            } else {
                $data['cached'] = true;
            }
            //file_put_contents(ABSPATH.'/lcs/'.$lic.'.json', json_encode($data));
            $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
            header("Expires: $ts");
            header("Pragma: cache");
            header("Cache-Control: max-age=$seconds_to_cache");
            wp_send_json($data);

        }
    }

    function generateCartHTML($cart_data = null){
        $cart_data = $cart_data ?: WPDMPP()->cart->getItems();
        ob_start();
        foreach ($cart_data as $id => $info) {
            ?>
            <tr id="citem-<?= $id; ?>">
                <td align="left"><strong><?php echo $info['product_name']; ?></strong><?= wpdm_valueof($info, 'license/info/name') ? '  &mdash; ' . wpdm_valueof($info, 'license/info/name') . ' License' : '' ?></td>
                <td align="left"><?php echo wpdmpp_price_format($info['price']); ?></td>
                <td align="left"><?php echo $info['quantity']; ?></td>
                <td align="right" style="width: 150px;text-align: right"><?php echo wpdmpp_price_format($info['price']*$info['quantity']); ?></td>
                <td align="right" style="width: 60px;text-align: right"><button type="button" data-pid="<?= $id; ?>" class="btn btn-xs btn-danger btn-delete-cart-item"><i class="fas fa-trash"></i></button></td>
            </tr>
            <?php
        }
        $html = ob_get_clean();
        wp_send_json(['cart_html' => $html, 'cart_total' => wpdmpp_price_format(wpdmpp_get_cart_total())]);
        die();
    }

    function saveCustomOrder(){
        if(wp_verify_nonce(wpdm_query_var('__nonce'), NONCE_KEY) && current_user_can('manage_options')){
            $o = new Order();
            $order_id = $o->open();
            $o->set('uid', 0);
            $o->set('payment_method', 'Cash');
            $o->save();
            Order::complete_order($order_id, false);
            wp_send_json(array('status'=>1, 'oid' => $order_id));
            die();
        }
        wp_send_json(array('status' => 0));
        die();
    }

    function loadDownloadHistory(){
        if(wp_verify_nonce(wpdm_query_var('__dlhnonce', 'txt'), NONCE_KEY)){
            $oid = wpdm_query_var('oid', 'txt');
            global $wpdb;
            $data = $wpdb->get_results("select * from {$wpdb->prefix}ahm_download_stats where oid = '{$oid}' order by `timestamp` DESC");

            echo "<table class='table table-striped'><tr><th>IP</th><th style='text-align: right;'>Download Time</th></tr>";
            foreach ($data as $d){
                $time = wp_date(get_option('date_format')." ".get_option('time_format'), $d->timestamp);
                $ip = $d->ip != ''?$d->ip:'██████████';
                echo "<tr><td>{$ip}</td><td style='text-align: right;'>{$time}</td></tr>";
            }
            echo "</table>";
            die();
        }
        die('Nonce key is expired, refresh the page and try again.');
    }

    /**
     * Withdraw money from paypal notification
     */
    function changePayoutStatus()
    {

        if (current_user_can(WPDMPP_ADMIN_CAP) && wp_verify_nonce(wpdm_query_var('__psnonce', 'txt'), NONCE_KEY)) {
            global $wpdb;
            $status = $wpdb->get_var("select status from {$wpdb->prefix}ahm_withdraws where id = '".wpdm_query_var('id', 'int')."'");
            $status = $status == 1?0:1;
            $wpdb->update(
                "{$wpdb->prefix}ahm_withdraws",
                array(
                    'status' => $status
                ),
                array('id' => wpdm_query_var('id', 'int')),
                array(
                    '%d'
                ),
                array('%d')
            );
            wp_send_json(array('status' => $status?'Completed':'Pending'));
            die();
        }


    }

    function buyNowButton(){
        $product_id = wpdm_query_var('pid', 'int');
        $license = wpdm_query_var('license', 'txt');
        $product = new Product($product_id);
        $price = $product->getLicensePrice($license);
        $params = array('title' => __('Buy Now', WPDMPP_TEXT_DOMAIN));
        include wpdm_tpl_path("add-to-cart/buy-now.php", WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
        die();
    }

    function emptyCart()
    {
        WPDMPP()->cart->clear();
        wp_send_json(['success' => true]);
    }

    function acrActivityLog()
    {
        global $wpdb;
	    $order_id = wpdm_query_var('order_id', 'txt');
        $order = $wpdb->get_row("select * from {$wpdb->wpdmpp_abandoned_orders} where order_id='$order_id'");
        $logs = json_decode($order->activity_log);
        echo "<ul class='list-group'>";
        foreach ($logs as $log) {
            $date = wp_date(get_option('date_format'), $log->time);
            echo "<li class='list-group-item'>{$log->msg} &mdash; <span class='color-purple'>{$date}</span></li>";
        }
        echo "</ul>";
        die();
    }

    function payoutSettings()
    {
        __::isAuthentic('__wpdmpp_payout', WPDM_PRI_NONCE, WPDM_ADMIN_CAP);

        update_option("wpdmpp_payout_duration", absint(wpdm_query_var('payout_duration')), false);
        update_option("wpdmpp_payout_min_amount", wpdm_query_var('payout_min_amount'), false);
        update_option("wpdmpp_active_pom", wpdm_query_var('active_pom'), false);
	    update_option("wpdmpp_user_comission", wpdm_query_var('comission'), false);

        wp_send_json(['succuess' => true, 'msg' => __('Options saved successfully!', WPDMPP_TEXT_DOMAIN)]);
    }
}

$customActions = new CustomActions();
$customActions->execute();

