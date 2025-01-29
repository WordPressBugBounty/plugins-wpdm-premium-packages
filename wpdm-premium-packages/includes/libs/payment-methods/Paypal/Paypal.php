<?php

namespace WPDMPP\Libs\PaymentMethods;

use WPDM\__\Session;
use WPDMPP\Libs\Order;
use WPDMPP\PayPalAPI;
use WPDMPP\Product;

if (!defined('ABSPATH')) {
    exit;
}

include __DIR__."/PayPalAPI.php";

if (!class_exists('Paypal')) {

    class Paypal extends \WPDMPP\Libs\CommonVars
    {
        public $TestMode;

        public $GatewayUrl = "https://www.Paypal.com/cgi-bin/webscr";
        public $GatewayUrl_TestMode = "https://www.sandbox.Paypal.com/cgi-bin/webscr";
        public $Business;
        public $ReturnUrl;
        public $NotifyUrl;
        public $CancelUrl;
        public $completeBuyNow;
        public $Custom;
        public $Enabled;
        public $Currency;
        public $ClientEmail;
        public $buyer_email;
        public $ipn_response;
        public $ipd_data;
        public $client_id;
        public $client_secret;
        public $client_id_sandbox;
        public $client_secret_sandbox;
        public $GatewayName = 'PayPal';
        public $logo;
        public $PayPalMode;
        public $ImageURL;


        function __construct($Mode = 0)
        {
            global $current_user;
            $current_user = wp_get_current_user();
            if ($Mode == 1)
                $this->GatewayUrl = $this->GatewayUrl_TestMode;

            $this->Enabled = get_wpdmpp_option('Paypal/enabled');
            $this->ReturnUrl = get_wpdmpp_option('Paypal/return_url', "{{download_page}}");
            $this->NotifyUrl = home_url('?action=wpdmpp-payment-notification&class=Paypal');
            $this->completeBuyNow = home_url('?action=wpdmpp-complete-buynow&class=Paypal');
            $this->CancelUrl = get_wpdmpp_option('Paypal/cancel_url', home_url('/'));
            $this->client_id = get_wpdmpp_option('Paypal/client_id');
            $this->client_secret = get_wpdmpp_option('Paypal/client_secret');
            $this->client_id_sandbox = get_wpdmpp_option('Paypal/client_id_sandbox');
            $this->client_secret_sandbox = get_wpdmpp_option('Paypal/client_secret_sandbox');
            $this->Business = get_wpdmpp_option('Paypal/Paypal_email');
            $this->TestMode = get_wpdmpp_option('Paypal/Paypal_mode', 'production');
            $this->PayPalMode = get_wpdmpp_option('Paypal/Paypal_mode', 'production');
            $this->ImageURL = get_wpdmpp_option('Paypal/Paypal_image_url', '');
            $this->Currency = wpdmpp_currency_code();
            $this->logo = "<img src='".WPDMPP_BASE_URL."assets/images/paypal.svg' alt='PayPal' class='wpdmpp-payment-logo wpdmpp-paypal-logo' />";
            if (is_user_logged_in()) {
                $this->ClientEmail = $current_user->user_email;
            }

            if ($this->PayPalMode == 'sandbox')
                $this->GatewayUrl = $this->GatewayUrl_TestMode;
        }

        function ConfigOptions()
        {
            if ($this->Enabled) $enabled = 'checked="checked"';
            else $enabled = "";
            $webhook = get_option('wpdmpp_paypal_webhook');
	        $webhook = json_decode($webhook);
            $webhookExists = "<i class='fa fa-long-arrow-left'></i> Save PayPal api info, then click the button to create webhook";
            if($webhook) $webhookExists = "<i class='fa fa-check-double'></i> WebHook is active ( ID: {$webhook->id} )";
            $options = array(

                'Paypal_mode' => array(
                    'label' => __("Paypal Mode:", "wpdm-premium-packages"),
                    'type' => 'select',
                    'options' => array('production' => 'Live', 'sandbox' => 'Test'),
                    'selected' => $this->PayPalMode
                ),
                'Paypal_email' => array(
                    'label' => __("Paypal Email:", "wpdm-premium-packages"),
                    'type' => 'text',
                    'placeholder' => '',
                    'value' => $this->Business
                ),
                'notice' => array(
                    'type' => 'notice',
                    'notice' => "<a href='https://developer.paypal.com/developer/applications/' target='_blank'>PayPal Apps</a> | <a href='https://developer.paypal.com/developer/applications/create' target='_blank'>Create New App</a>"
                ),
                'client_id' => array(
                    'label' => __("Client ID:", "wpdm-premium-packages"),
                    'type' => 'text',
                    'placeholder' => '',
                    'value' => $this->client_id
                ),
                'client_secret' => array(
                    'label' => __("Client Secret:", "wpdm-premium-packages"),
                    'type' => 'text',
                    'placeholder' => '',
                    'value' => $this->client_secret
                ),
                'client_id_sandbox' => array(
                    'label' => __("Client ID (Sandbox):", "wpdm-premium-packages"),
                    'type' => 'text',
                    'placeholder' => '',
                    'value' => $this->client_id_sandbox
                ),
                'client_secret_sandbox' => array(
                    'label' => __("Client Secret (Sandbox):", "wpdm-premium-packages"),
                    'type' => 'text',
                    'placeholder' => '',
                    'value' => $this->client_secret_sandbox
                ),
                'cancel_url' => array(
                    'label' => __("Cancel Url:", "wpdm-premium-packages"),
                    'type' => 'text',
                    'placeholder' => '',
                    'value' => $this->CancelUrl
                ),
                'return_url' => array(
                    'label' => __("Return Url:", "wpdm-premium-packages"),
                    'type' => 'text',
                    'placeholder' => '{{download_page}}',
                    'value' => $this->ReturnUrl
                ),

                'Paypal_image_url' => array(
                    'label' => __("Checkout Page Logo Url:", "wpdm-premium-packages"),
                    'type' => 'media',
                    'placeholder' => '150x50 px',
                    'value' => $this->ImageURL
                ),
                'endpoint_label' => array(
	                'label'       => __( "Webhook endpoint URL:", "wpdm-premium-packages" ),
	                'type'        => 'notice',
	                'placeholder' => '',
	                'notice'      => "
	                <input onclick='WPDM.copyTxt(this.value)' type='text' value='" . home_url( "/?pmwebhook=Paypal" ) . "' readonly=readonly class='form-control' style='' />
	                <br/><div class='panel panel-default'><div class='panel-body'>
	                   <div class='media'><div class='pull-left'><button id='cpplwh' data-url='".admin_url('admin-ajax.php?action=wpdmpp_create_pmwebhook&pm=Paypal')."' data-rest='#pplwhm' type='button' class='btn btn-info exec-async'>".__('Create WebHook', WPDMPP_TEXT_DOMAIN)."</button></div><div id='pplwhm' class='media-body text-right text-success' style='line-height: 34px;'>{$webhookExists}</div></div> 	                   
                    </div></div>
	                "
                ),
            );

            return $options;
        }

        function testConnection()
        {
            $access_token = $this->getAccessToken();
            if($access_token)
                return "<div class='alert alert-success'><i class='fa fa-check-double'></i> ".__('Credentials are validated successfully', 'wpdm-premium-packages')."</div>";
            else
                return "<div class='alert alert-danger'>".__('Credentials are invalid!', 'wpdm-premium-packages')."</div>";
        }

        function showPaymentFormRec($AutoSubmit = 0)
        {
            global $wpdmpp_settings;

            $wpdmpp_settings['order_validity_period'] = (int)$wpdmpp_settings['order_validity_period'] > 0 ? (int)$wpdmpp_settings['order_validity_period'] : 365;

            $per = $wpdmpp_settings['order_validity_period'] / 365;
            $trm = 'Y';
            if ($per < 1) {
                $per = $wpdmpp_settings['order_validity_period'] / 30;
                $trm = 'M';
            }
            if (!is_int($per)) {
                $per = $wpdmpp_settings['order_validity_period'] / 7;
                $trm = 'W';
            }

            if ($AutoSubmit == 1) $hide = "display:none;'";
            $opu = !is_user_logged_in() && get_wpdmpp_option('guest_download') == 1 && wpdmpp_guest_order_page() != '' ? wpdmpp_guest_order_page() : wpdmpp_orders_page();
            $returnURL = str_replace('{{download_page}}', wpdmpp_orders_page("id={$this->InvoiceNo}"), $this->ReturnUrl);
            $returnURL = $returnURL ? $returnURL : wpdmpp_orders_page($this->InvoiceNo);
            $Paypal = plugins_url() . '/wpdm-premium-packages/images/Paypal.png';
            $wpdmpp_settings['order_validity_period'] = (int)$wpdmpp_settings['order_validity_period'] > 0 ? (int)$wpdmpp_settings['order_validity_period'] : 365;
            $period = $wpdmpp_settings['order_validity_period'];
            $Form = "   <form method='post' style='margin:0px;padding: 0' name='_wpdm_bnf_{$this->InvoiceNo}' id='_wpdm_bnf_{$this->InvoiceNo}' action='https://www.paypal.com/cgi-bin'>
                    <input name='cmd' value='_xclick-subscriptions' type='hidden'>
                    <!-- the next three need to be created -->

                    <input name='rm' value='2' type='hidden'>

                    <input name='lc' value='US' type='hidden'>
                    <input name='bn' value='toolkit-php' type='hidden'>

                    <input name='cbt' value='Continue' type='hidden'>
                    
                    <!-- Payment Page Information -->
                    <input name='no_shipping' value='' type='hidden'>
                    <input name='no_note' value='1' type='hidden'>
                    <input name='src' value='1' type='hidden'>
                    <input name='cn' value='Comments' type='hidden'>
                    <input name='cs' value='' type='hidden'>
                    
                    <input name='business' value='{$this->Business}' type='hidden'>
                    <input name='return' value='{$returnURL}' type='hidden'>
                    <input name='cancel_return' value='{$this->CancelUrl}' type='hidden'>
                    <input name='notify_url' value='{$this->NotifyUrl}&type=recurring' type='hidden'>
                    <input name='currency_code' value='{$this->Currency}' type='hidden'>
                    <input name='item_name' value='{$this->OrderTitle}' type='hidden'>
                    <input name='amount' value='' type='hidden'>            
                    
                    <input name='a3' value='{$this->Amount}' type='hidden'>
                    <input name='p3' value='{$per}' type='hidden'>
                    <input name='t3' value='{$trm}' type='hidden'>

                    <input name='item_number' value='{$this->InvoiceNo}' type='hidden'>
                    <input name='a1' value='{$this->Amount}' type='hidden'>
                    <input name='p1' value='{$per}' type='hidden'>
                    <input name='t1' value='{$trm}' type='hidden'>
                    <input type='hidden' name='image_url' value='{$this->ImageURL}' />                  


                    <noscript>&lt;button type='submit'&gt;Proceed Now...&lt;/button&gt;</noscript>
                 
                    </form>
         
        
        ";


            if ($AutoSubmit == 1)
                $Form .= "<div class='alert alert-progress'><i class='fas fa-sync fa-spin'></i> " . __("Proceeding to Paypal....", "wpdm-premium-packages") . "</div><script language=javascript>setTimeout('document._wpdm_bnf_{$this->InvoiceNo}.submit()',1000);</script>";

            if ($this->Business == '' || $this->Currency == '') {
                $Form = "<div class='alert alert-danger'>" . __("There are some problems with PayPal setup, please notify site admin", "wpdm-premium-packages") . "</div>";
            }

            return $Form;


        }

        function showPaymentForm($AutoSubmit = 0)
        {

            global $wpdmpp_settings;
            $wpdmpp_settings['order_validity_period'] = (int)$wpdmpp_settings['order_validity_period'] > 0 ? (int)$wpdmpp_settings['order_validity_period'] : 365;
            if (isset($wpdmpp_settings['auto_renew'], $wpdmpp_settings['order_validity_period']) && $wpdmpp_settings['auto_renew'] == 1 && $wpdmpp_settings['order_validity_period'] > 0)
                return $this->showPaymentFormRec($AutoSubmit);

            if ($AutoSubmit == 1) $hide = "display:none;'";
            $Paypal = plugins_url() . '/wpdm-premium-packages/images/Paypal.png';
            $Form = " 
                    <form method='post' style='margin:0px;' name='_wpdm_bnf_{$this->InvoiceNo}' id='_wpdm_bnf_{$this->InvoiceNo}' action='{$this->GatewayUrl}'>

                    <input type='hidden' name='business' value='{$this->Business}' />

                    <input type='hidden' name='cmd' value='_xclick' />
                    <!-- the next three need to be created -->
                    <input type='hidden' name='return' value='{$this->ReturnUrl}' />
                    <input type='hidden' name='cancel_return' value='{$this->CancelUrl}' />
                    <input type='hidden' name='notify_url' value='{$this->NotifyUrl}' />
                    <input type='hidden' name='rm' value='2' />
                    <input type='hidden' name='currency_code' value='{$this->Currency}' />
                    <input type='hidden' name='lc' value='US' />
                    <input type='hidden' name='bn' value='W3Eden_SP' />

                    <input type='hidden' name='cbt' value='Continue' />
                    
                    <!-- Payment Page Information -->
                    <input type='hidden' name='no_shipping' value='' />
                    <input type='hidden' name='no_note' value='1' />
                    <input type='hidden' name='cn' value='Comments' />
                    <input type='hidden' name='cs' value='' />
                    
                    <!-- Product Information -->
                    <input type='hidden' name='item_name' value='{$this->OrderTitle}' />
                    <input type='hidden' name='amount' value='{$this->Amount}' />

                    <input type='hidden' name='quantity' value='1' />
                    <input type='hidden' name='item_number' value='{$this->InvoiceNo}' />
                    <input type='hidden' name='email' value='{$this->ClientEmail}' />
                    <input type='hidden' name='custom' value='{$this->Custom}' />
                    <input type='hidden' name='image_url' value='{$this->ImageURL}' />
                    
                    <!-- Shipping and Misc Information -->
                     
                    <input type='hidden' name='invoice' value='{$this->InvoiceNo}' />

                    <noscript><p>Your browser doesn't support Javscript, click the button below to process the transaction.</p>
                    <button type='submit' class='btn btn-success'>Buy Now</button></noscript>
                    </form>
         
        
        ";


            if ($AutoSubmit == 1)
                $Form .= "<div class='alert alert-success'>" . __("Proceeding to Paypal....", "wpdm-premium-packages") . "</div><script language=javascript>setTimeout('document._wpdm_bnf_{$this->InvoiceNo}.submit()',1000);</script>";


            return $Form;
        }

        function verifyPayment()
        {
            $url_parsed = parse_url($this->GatewayUrl);

            //print_r($_POST);
            $this->InvoiceNo = sanitize_text_field($_POST['invoice']);
            $order = new Order();
            $orderdata = $order->GetOrder($this->InvoiceNo);
            $this->buyer_email = sanitize_email($_POST['payer_email']);

            if (floatval($orderdata->total) != floatval($_POST['mc_gross']))
                return false;

            $post_string = '';
            foreach ($_POST as $field => $value) {
                $this->ipn_data["$field"] = $value;
                $post_string .= $field . '=' . urlencode(stripslashes($value)) . '&';
            }
            $post_string .= "cmd=_notify-validate"; // append ipn command

            parse_str($post_string, $post_array);

            $remote_post_vars = array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array(
                    'host' => 'www.paypal.com',
                    'connection' => 'close',
                    'content-type' => 'application/x-www-form-urlencoded',
                    'post' => '/cgi-bin/webscr HTTP/1.1',
                    'user-agent' => 'WPDMPP IPN Verification/; ' . get_bloginfo('url')

                ),
                'sslverify' => false,
                'body' => $post_array
            );

            // Get response
            $api_response = wp_remote_post($this->GatewayUrl, $remote_post_vars);
            //print_r(wp_remote_retrieve_body( $api_response ));
            wp_send_json($api_response);
            if (is_wp_error($api_response)) {
                $this->VerificationError = 'Something went wrong.';
                return false; // Something went wrong
            }

            if (wp_remote_retrieve_body($api_response) !== 'VERIFIED') {
                $this->VerificationError = 'IPN Validation Failed.';
                return false; // Response not okay
            }

            if (wp_remote_retrieve_body($api_response) == 'VERIFIED') {
                return true; // Valid IPN transaction.
            }
        }

        function verifyNotification()
        {

            global $wpdmpp, $current_user;
            $current_user = wp_get_current_user();
            if (wpdm_query_var('__jsvalidate', 'int') === 1) {
                // Verify subscription/recurring payment
                if (wpdm_query_var('subscriptionID') !== '') {

                    if($this->PayPalMode === 'sandbox')
                        $paypalapi = new PayPalAPI($this->PayPalMode, $this->client_id_sandbox, $this->client_secret_sandbox);
                    else
                        $paypalapi = new PayPalAPI($this->PayPalMode, $this->client_id, $this->client_secret);

                    $data = $paypalapi->getSubscriptionDetails(wpdm_query_var('subscriptionID'));
                    $customer = $data->subscriber;

                    if(!is_object($data) || !isset($data->status) || $data->status !== 'ACTIVE'){
                        wp_send_json(array('success' => false, 'data' => $data));
                    }

                    $first_name = wpdm_query_var('billing/first_name');
                    $last_name = wpdm_query_var('billing/last_name');
                    $order_email = sanitize_email(wpdm_query_var('billing/order_email'));

                    if($order_email === '') {
                        $order_email = is_user_logged_in() ? $current_user->user_email : $customer->email_address;
                    }

                    $order_id = Session::get('orderid');
                    Order::update(['trans_id' => wpdm_query_var('subscriptionID'), 'billing_info' => serialize(wpdm_query_var('billing'))], $order_id);
                    Order::complete_order($order_id);

                    if (is_user_logged_in()) {
                        if(is_array(wpdm_query_var('billing')))
                            update_user_meta(get_current_user_id(), 'user_billing_shipping', serialize(array('billing' => wpdm_query_var('billing'))));;
                    }

                    Session::set('guest_order_init', uniqid(), 18000);
                    Session::set('guest_order', $order_id, 18000);
                    Session::set('order_email', $order_email, 18000);
                    wpdmpp_empty_cart();
                    wp_send_json(array('success' => true, 'redirect' => wpdmpp_orders_page("id={$order_id}"), 'data' => $data));
                }

                //Verify regular checkout payment
                $payment = $this->paymentDetails(wpdm_query_var('paypal/id'));

                if ($payment->status === 'COMPLETED') {
                    $order_id = $payment->purchase_units[0]->reference_id;
                    $payment_amount = $payment->purchase_units[0]->amount->value;
                    $cart_total = WPDMPP()->cart->cartTotal(true, true, false);
                    $order_email = wpdm_query_var('email');
                    if($order_email == '' && is_user_logged_in()) $order_email = $current_user->user_email;
                    $name = wpdm_query_var('name');
                    $name = explode(" ", $name);
                    $first_name = $name[0];
                    $last_name = isset($name[1]) ? $name[1] : $name[0];
                    //wpdmdd($payment_amount." ".$cart_total);

                    if ($payment_amount >= $cart_total) {


                        $billing_info = array(
                            'first_name' => $first_name ? $first_name : wpdm_query_var('paypal/payer/name/given_name', ['validate' => 'txt']),
                            'last_name' => $last_name ? $last_name : wpdm_query_var('paypal/payer/name/surname', ['validate' => 'txt']),
                            'order_email' => $order_email ? $order_email : wpdm_query_var('paypal/payer/email_address', ['validate' => 'email']),
                            'email' => $order_email ? $order_email : wpdm_query_var('paypal/payer/email_address', ['validate' => 'email'])
                        );

                        if(is_array(wpdm_query_var('billing')) && wpdm_query_var('billing/order_email') !== '')
                            $billing_info = wpdm_query_var('billing');


                        $data = array(
                            'payment_method' => 'Paypal',
                            'billing_info' => $billing_info,
                        );

                        $order = new Order();
                        $od = $order->Update($data, $order_id);

                        if (is_user_logged_in()) {
                            if(is_array(wpdm_query_var('billing')))
                                update_user_meta(get_current_user_id(), 'user_billing_shipping', serialize(array('billing' => wpdm_query_var('billing'))));;
                        }
                        Order::complete_order($order_id);
                        Session::set('guest_order_init', uniqid(), 18000);
                        Session::set('guest_order', $order_id, 18000);
                        Session::set('order_email', $billing_info['order_email'], 18000);
                        wpdmpp_empty_cart();
                        do_action("wpdmpp_payment_completed", $order_id);
                        wp_send_json(array('success' => true, 'redirect' => wpdmpp_orders_page("id={$order_id}"), 'data' => $data));

                    }
                }
                wp_send_json(array('success' => false, 'error' => $payment));
                die();
            }
            if ($_POST) {
                $this->InvoiceNo = sanitize_text_field($_POST['invoice']);
                return $this->verifyPayment();
            } else
                die("Problem occured in payment.");
        }

        function customPayButton($order_id = null)
        {
            global $wpdmpp, $current_user, $wpdmpp_settings;
            $current_user = wp_get_current_user();
            $client_id = (get_wpdmpp_option('Paypal/Paypal_mode') == 'sandbox') ? $this->client_id_sandbox : $this->client_id;
            $total = WPDMPP()->cart->cartTotal(true, true);

	        if(!$order_id) {
		        if ( $client_id === '' || (double) wpdmpp_get_cart_total() == 0 ) {
			        return '';
		        }
		        $order_id = WPDMPP()->create_order();
	        }
            $order = new Order($order_id);


            $order_id = $order->oid;
            ob_start();

            $env = get_wpdmpp_option('Paypal/Paypal_mode') == 'sandbox' ? 'sandbox' : 'production';
            $recurring = 0;
            $order_validity_period = get_wpdmpp_option('order_validity_period', 365, 'int');
	        $order_validity_period = !$order_validity_period ? 365 : $order_validity_period;

            if (WPDMPP()->cart->isRecurring()){
                $recurring = 1;
                if($env === 'sandbox')
                    $paypalapi = new PayPalAPI($env, $this->client_id_sandbox, $this->client_secret_sandbox);
                else
                    $paypalapi = new PayPalAPI($env, $this->client_id, $this->client_secret);

	            $interval_count = $order_validity_period / 365;
	            $interval_unit = 'YEAR';
	            if ($interval_count < 1) {
		            $interval_count = $order_validity_period / 30;
		            $interval_unit = 'MONTH';
	            }
	            if (!is_int($interval_count)) {
		            $interval_count = $order_validity_period / 7;
		            $interval_unit = 'WEEK';
	            }

                $success = $paypalapi->createProduct($order_id, $order->title, $order->title, 'https://www.wpdownloadmanager.com/', WPDM_ASSET_URL.'images/wpdm-logo.png');
                if($success)
                    $paypalapi->createPlan($order->total, $interval_count, $interval_unit);
            }

            ?>
            <div id="wpdm-paypal-button-container"></div>

            <style>
                #wpdm-paypal-button-container{
                    position: relative;
                }
                #wpdm-paypal-button-container.__blocked:before{
                    position: absolute;
                    content: "";
                    width: 100%;
                    height: 100%;
                    left: 0;
                    top: 0;
                    background: rgba(255,255,255,0.5);
                    z-index: 9999999 !important;
                }
            </style>
            <script>

                function pmf_is_email(email) {
                    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
                    return regex.test(email);
                }

                jQuery(function ($) {

                    function validate_payment_form()
                    {
                        let valid = true;
                        if($('#email_m').length > 0 && ($('#f-name').val() == '' || $('#l-name').val() == '' || !pmf_is_email($('#email_m').val()))) valid = false;
                        return valid;
                    }

                    function _ppbtnact()
                    {
                        var isvalid = validate_payment_form();
                        if(isvalid)
                            $('#wpdm-paypal-button-container').removeClass('__blocked');
                        else
                            $('#wpdm-paypal-button-container').addClass('__blocked');
                    }

                    _ppbtnact();

                    $('body').on('keydown', '#payment_form input', function () {
                        _ppbtnact();
                    });
                    $('body').on('change', '#payment_form input', function () {
                        _ppbtnact();
                    });

                    window.loadScripts = (scripts) =>  {
                        return scripts.reduce((currentPromise, scriptUrl) => {
                            return currentPromise.then(() => {
                                return new Promise((resolve, reject) => {
                                    var script = document.createElement('script');
                                    script.async = true;
                                    script.src = scriptUrl;
                                    script.onload = () => resolve();
                                    document.getElementsByTagName('head')[0].appendChild(script);
                                });
                            });
                        }, Promise.resolve());
                    };

                    <?php if($recurring === 0){ ?>
                    loadScripts(['https://www.paypal.com/sdk/js?&components=buttons,hosted-fields,funding-eligibility&currency=<?= wpdmpp_currency_code(); ?>&client-id=<?= ($env === 'sandbox') ? $this->client_id_sandbox : $this->client_id; ?>&vault=true']).then(() => {
                        paypal.Buttons({

                            env: '<?php echo $env; ?>',


                            style: {
                                layout: 'horizontal',  /* horizontal | vertical */
                                size: 'medium',    /* medium | large | responsive  */
                                shape: 'rect',      /* pill | rect  */
                                color: 'blue',       /* gold | blue | silver | white | black  */
                                label: 'checkout',
                                tagline: false
                            },


                            funding: {
                                allowed: [
                                    paypal.FUNDING.CARD

                                ],
                                disallowed: [paypal.FUNDING.CREDIT]
                            },
                            client: {
                                sandbox: '<?= get_wpdmpp_option('Paypal/client_id_sandbox'); ?>',
                                production: '<?= get_wpdmpp_option('Paypal/client_id'); ?>'
                            },
                            createOrder: function(data, actions) {

                                return actions.order.create({

                                    purchase_units: [{
                                        reference_id: '<?= $order_id ?>',
                                        description: '<?= $order->title ?>',
                                        amount: {

                                            value: '<?= number_format($order->total, 2, '.', ''); ?>',
                                            currency_code: '<?= wpdmpp_currency_code(); ?>'

                                        }

                                    }]

                                });

                            },

                            onApprove: function(data, actions) {

                                return actions.order.capture().then(function(response) {
                                    var datax = jQuery('#payment_form').serialize();
                                    console.log('Payment done:', response);
                                    $('#paymentform').append('<div class="alert alert-success">'+wpdm_js.spinner+' <?php _e('Completing Order...', 'wpdm-premium-packages'); ?></div>');
                                    $.post('<?php echo $this->NotifyUrl; ?>&__jsvalidate=1'+'&email='+$('#email_m').val()+'&name='+$('#f-name').val()+'&'+datax, {paypal: response}, function (res) {
                                        if (res.success == true) {
                                            location.href = res.redirect;
                                        }
                                    });

                                });

                            },
                            onError: function () {

                               console.log(err);

                            }
                        }).render('#wpdm-paypal-button-container');


                    });
                    <?php } else { ?>

                    loadScripts(['https://www.paypal.com/sdk/js?client-id=<?php echo ($env === 'sandbox') ? $this->client_id_sandbox : $this->client_id; ?>&vault=true']).then(() => {
                        paypal.Buttons({
                            style: {
                                layout: 'horizontal',  /* horizontal | vertical */
                                size: 'responsive',    /* medium | large | responsive  */
                                shape: 'rect',      /* pill | rect  */
                                color: 'blue',       /* gold | blue | silver | white | black  */
                                tagline: false
                            },


                            funding: {
                                allowed: [
                                    paypal.FUNDING.CARD

                                ],
                                disallowed: [paypal.FUNDING.CREDIT]
                            },


                            createSubscription: function (data, actions) {

                                return actions.subscription.create({

                                    'plan_id': '<?php echo $paypalapi->planID; ?>'

                                });

                            },


                            onApprove: function (data, actions) {
                                let datax = jQuery('#payment_form').serialize();
                                $('#selected-payment-gateway-action').addClass('blockui');
                                $('#paymentform').append('<div class="alert alert-success">'+wpdm_js.spinner+' <?php _e('Completing Order...', 'wpdm-premium-packages'); ?></div>');
                                $.post('<?php echo $this->NotifyUrl ?>&__jsvalidate=1&'+datax, data, function (res) {
                                    if (res.success == true) {
                                        location.href = res.redirect;
                                    }
                                    else {
                                        alert('Something is wrong!');
                                        $('#selected-payment-gateway-action').removeClass('blockui');
                                    }
                                });
                            }


                        }).render('#wpdm-paypal-button-container');
                    });
                    <?php } ?>
                });

            </script>
            <?php
            return ob_get_clean();
        }


        function buyNowButton($product_id, $license = '')
        {
            global $wpdmpp, $current_user;
            $current_user = wp_get_current_user();
            if($this->client_id == '' || $this->client_id_sandbox == '') return '';

	        if(!WPDMPP()->payment->isPaymentMethodActive(get_class($this))) return '';

            $product = new Product($product_id);

            $price = $product->getLicensePrice($license);
	        $_price = str_replace(".", "_", $price);

            ob_start();

            $env = get_wpdmpp_option('Paypal/Paypal_mode') == 'sandbox' ? 'sandbox' : 'production';

	        $recurring = 0;
	        $order_validity_period = get_wpdmpp_option('order_validity_period', 365, 'int');
	        $order_validity_period = !$order_validity_period ? 365 : $order_validity_period;
	        $plan_id = null;
	        if (WPDMPP()->cart->isRecurring()){
		        $recurring = 1;
		        if($env === 'sandbox')
			        $paypalapi = new PayPalAPI($env, $this->client_id_sandbox, $this->client_secret_sandbox);
		        else
			        $paypalapi = new PayPalAPI($env, $this->client_id, $this->client_secret);

		        $interval_count = $order_validity_period / 365;
		        $interval_unit = 'YEAR';
		        if ($interval_count < 1) {
			        $interval_count = $order_validity_period / 30;
			        $interval_unit = 'MONTH';
		        }
		        if (!is_int($interval_count)) {
			        $interval_count = $order_validity_period / 7;
			        $interval_unit = 'WEEK';
		        }
                $product = get_post($product_id);
                $plan_id = get_post_meta($product_id, "__ppplan_id_{$_price}", true);
                if(!$plan_id) {
	                $success = $paypalapi->createProduct( "WPDM_{$product_id}_{$_price}", $product->post_title, $product->post_title, get_permalink( $product_id ), get_the_post_thumbnail_url( $product_id ) );
                    if($success) {
	                    $paypalapi->createPlan( $price, $interval_count, $interval_unit );
	                    update_post_meta( $product_id, "__ppplan_id_{$_price}", $paypalapi->planID );
	                    $plan_id = $paypalapi->planID;
                    }
                }
	        }
            if($plan_id){
            ?>

            <div id="wpdm-paypal-button-container" style="width: 100%"></div>

            <script>

                jQuery(function ($) {
                    window.loadScripts = (scripts) =>  {
                        return scripts.reduce((currentPromise, scriptUrl) => {
                            return currentPromise.then(() => {
                                return new Promise((resolve, reject) => {
                                    var script = document.createElement('script');
                                    script.async = true;
                                    script.src = scriptUrl;
                                    script.onload = () => resolve();
                                    document.getElementsByTagName('head')[0].appendChild(script);
                                });
                            });
                        }, Promise.resolve());
                    };

			        <?php if($recurring === 0){ ?>
                    loadScripts(['https://www.paypal.com/sdk/js?currency=<?= wpdmpp_currency_code(); ?>&client-id=<?= ($env === 'sandbox') ? $this->client_id_sandbox : $this->client_id; ?>&vault=true']).then(() => {
                        paypal.Buttons({

                            env: '<?php echo $env; ?>',


                            style: {
                                layout: 'horizontal',  /* horizontal | vertical */
                                size: 'responsive',    /* medium | large | responsive  */
                                shape: 'rect',      /* pill | rect  */
                                color: 'blue',       /* gold | blue | silver | white | black  */
                                label: 'checkout',
                                tagline: false
                            },


                            funding: {
                                allowed: [
                                    paypal.FUNDING.CARD

                                ],
                                disallowed: [paypal.FUNDING.CREDIT]
                            },
                            client: {
                                sandbox: '<?= get_wpdmpp_option('Paypal/client_id_sandbox'); ?>',
                                production: '<?= get_wpdmpp_option('Paypal/client_id'); ?>'
                            },
                            createOrder: function(data, actions) {

                                return actions.order.create({

                                    purchase_units: [{
                                        reference_id: '<?= "WPDM_{$product_id}" ?>',
                                        description: '<?= $product->post_title ?>',
                                        amount: {

                                            value: '<?= $price; ?>',
                                            currency_code: '<?= wpdmpp_currency_code(); ?>'

                                        }

                                    }]

                                });

                            },

                            onApprove: function(data, actions) {

                                return actions.order.capture().then(function(response) {
                                    var datax = jQuery('#payment_form').serialize();
                                    datax = datax ? '&' + datax : '';
                                    $('#paymentform').append('<div class="alert alert-success">'+wpdm_js.spinner+' <?php _e('Completing Order...', 'wpdm-premium-packages'); ?></div>');
                                    $.post('<?php echo $this->completeBuyNow; ?>'+datax, {email: $('#email_m').val(), paypal: response, name: $('#f-name').val(), product_id: '<?= $product_id ?>', license: '<?= $license ?>'}, function (res) {
                                        if (res.success == true) {
                                            location.href = res.redirect;
                                        }
                                    });

                                });

                            },
                            onError: function (err) {

                                console.log(err);

                            }
                        }).render('#wpdm-paypal-button-container');

                    });
			        <?php } else { ?>

                    loadScripts(['https://www.paypal.com/sdk/js?client-id=<?php echo ($env === 'sandbox') ? $this->client_id_sandbox : $this->client_id; ?>&vault=true']).then(() => {
                        paypal.Buttons({
                            style: {
                                layout: 'horizontal',  /* horizontal | vertical */
                                size: 'responsive',    /* medium | large | responsive  */
                                shape: 'rect',      /* pill | rect  */
                                color: 'blue',       /* gold | blue | silver | white | black  */
                                tagline: false
                            },


                            funding: {
                                allowed: [
                                    paypal.FUNDING.CARD

                                ],
                                disallowed: [paypal.FUNDING.CREDIT]
                            },


                            createSubscription: function (data, actions) {

                                return actions.subscription.create({

                                    'plan_id': '<?php echo $plan_id; ?>'

                                });

                            },


                            onApprove: function (data, actions) {
                                var datax = jQuery('#payment_form').serializeArray();
                                datax = datax ? '&' + datax : '';

                                $('#selected-payment-gateway-action').addClass('blockui');
                                $('#paymentform').append('<div class="alert alert-success">'+wpdm_js.spinner+' <?php _e('Completing Order...', 'wpdm-premium-packages'); ?></div>');
                                $.post('<?php echo $this->completeBuyNow ?>'+datax, {subscriptionID: data.subscriptionID, name: $('#f-name').val(), email: $('#email_m').val(), product_id: '<?= $product_id ?>', license: '<?= $license ?>'}, function (res) {
                                    if (res.success == true) {
                                        location.href = res.redirect;
                                    }
                                    else {
                                        alert('Something is wrong!');
                                        $('#selected-payment-gateway-action').removeClass('blockui');
                                    }
                                });
                            }


                        }).render('#wpdm-paypal-button-container');
                    });
			        <?php } ?>
                });

            </script>

            <?php
            }
            return ob_get_clean();
        }

        function completeBuyNow()
        {

            global $wpdmpp, $current_user;
            $current_user = wp_get_current_user();

            $product_id = wpdm_query_var('product_id', 'int');
	        $license = wpdm_query_var('license', 'txt');

            WPDMPP()->cart->clear();
	        WPDMPP()->cart->addItem($product_id, $license);
	        $order_id = WPDMPP()->order->open();
            $order = new Order($order_id);

            if(wpdm_query_var('subscriptionID') !== '') {
	            if($this->PayPalMode === 'sandbox')
		            $paypalapi = new PayPalAPI($this->PayPalMode, $this->client_id_sandbox, $this->client_secret_sandbox);
	            else
		            $paypalapi = new PayPalAPI($this->PayPalMode, $this->client_id, $this->client_secret);
	            $data     = $paypalapi->getSubscriptionDetails( wpdm_query_var( 'subscriptionID' ) );
	            $customer = $data->subscriber;

	            if(!is_object($data) || !isset($data->status) || $data->status !== 'ACTIVE'){
		            wp_send_json(array('success' => false, 'data' => $data));
	            } else {
		            $name = wpdm_query_var('name');
		            $order_email =  sanitize_email(wpdm_query_var('email'));
		            if($name !== '') {
			            $name = explode(" ", $name);
			            $first_name = $name[0];
			            $last_name = isset($name[1])?sanitize_text_field($name[1]):sanitize_text_field($name[0]);
		            } else {
			            $first_name = $customer->name->given_name;
			            $last_name = $customer->name->surname;
		            }
		            if($order_email === '') {
			            $order_email = is_user_logged_in() ? $current_user->user_email : $customer->email_address;
		            }

		            $billing_info = array(
			            'first_name'  => sanitize_text_field( $first_name ),
			            'last_name'   => sanitize_text_field( $last_name ),
			            'order_email' => sanitize_email( $order_email ),
			            'email'       => sanitize_email( $order_email )
		            );

		            Order::update( [ 'billing_info' => maybe_serialize( $billing_info ), 'payment_method' => 'Paypal' ], $order_id );
		            Order::complete_order($order_id);

		            if (is_user_logged_in()) {
			            if(is_array(wpdm_query_var('billing')))
				            update_user_meta(get_current_user_id(), 'user_billing_shipping', serialize(array('billing' => wpdm_query_var('billing'))));;
		            }

		            Session::set('guest_order_init', uniqid(), 18000);
		            Session::set('guest_order', $order_id, 18000);
		            Session::set('order_email', $order_email, 18000);
		            wpdmpp_empty_cart();
		            wp_send_json(array('success' => true, 'redirect' => wpdmpp_orders_page("id={$order_id}"), 'data' => $data, 'order_id' => $order_id));
	            }


            } else {

	            $payment = $this->paymentDetails( wpdm_query_var( 'paypal/id' ) );

	            if ( $payment->status === 'COMPLETED' ) {

		            $payment_amount = $payment->purchase_units[0]->amount->value;

		            if ( $payment_amount >= $order->total ) {



			            $billing_info = array(
				            'first_name'  => sanitize_text_field( $_REQUEST['paypal']['payer']['name']['given_name'] ),
				            'last_name'   => sanitize_text_field( $_REQUEST['paypal']['payer']['name']['surname'] ),
				            'order_email' => sanitize_email( $_REQUEST['payer']['email_address'] ),
				            'email'       => sanitize_email( $_REQUEST['payer']['email_address'] )
			            );

			            Order::update( [ 'billing_info' => maybe_serialize( $billing_info ), 'payment_method' => 'Paypal' ], $order_id );

			            if ( is_user_logged_in() ) {
				            $billing_info['phone'] = '';
				            $cb                    = get_user_meta( $current_user->ID, 'user_billing_shipping', true );
				            if ( ! $cb ) {
					            update_user_meta( $current_user->ID, 'user_billing_shipping', maybe_serialize( array( 'billing' => $billing_info ) ) );
				            };
			            }

			            Order::complete_order( $order_id );

			            wpdmpp_empty_cart();
			            Session::set( 'guest_order_init', uniqid(), 18000 );
			            Session::set( 'guest_order', $order_id, 18000 );
			            Session::set( 'order_email', $billing_info['order_email'], 18000 );

			            //$order_url = is_user_logged_in() ? wpdmpp_orders_page( "id={$order_id}" ) : wpdmpp_guest_order_page( "order_id=" . $order_id );

			            wp_send_json( array( 'success' => true, 'redirect' => wpdmpp_orders_page( "id={$order_id}" ) ) );

		            }
	            }
	            wp_send_json( array( 'success' => false, 'error' => $payment ) );
            }
            die();

        }



        function getAccessToken()
        {

            $headers = array();
            $env = get_wpdmpp_option('Paypal/Paypal_mode') == 'sandbox' ? 'sandbox' : 'production';

            $clientid = $env === 'sandbox' ? get_wpdmpp_option('Paypal/client_id_sandbox') : get_wpdmpp_option('Paypal/client_id');
            $clientsecret = $env === 'sandbox' ? get_wpdmpp_option('Paypal/client_secret_sandbox') : get_wpdmpp_option('Paypal/client_secret');
            $apidomain = $env === 'sandbox' ? 'api.sandbox.paypal.com' : 'api.paypal.com';
            $auth = base64_encode($clientid . ':' . $clientsecret);

            $headers['accept'] = "application/json";
            $headers['accept-language'] = "en_US";
            $headers['content-type'] = "application/x-www-form-urlencoded";
            $headers['authorization'] = "basic $auth";
            $body['grant_type'] = 'client_credentials';
	        $args['timeout'] = 90;
            $args['body'] = $body;
            $args['headers'] = $headers;
            $data = wp_remote_post("https://{$apidomain}/v1/oauth2/token", $args);
	        $data = wp_remote_retrieve_body($data);
	        $data = json_decode($data);
            return is_object($data) && isset($data->access_token) ? $data->access_token : false;

        }

        function paymentDetails($payID)
        {
            $env = get_wpdmpp_option('Paypal/Paypal_mode') == 'sandbox' ? 'sandbox' : 'production';
            //if(current_user_can('manage_options')) $env = 'sandbox';
            $apidomain = $env === 'sandbox' ? 'api-m.sandbox.paypal.com' : 'api-m.paypal.com';

            $accessToken = $this->getAccessToken();

            if(!$accessToken) return false;

            //$url = "https://{$apidomain}/v2/payments/payment/{$payID}";
            $url = "https://{$apidomain}/v2/checkout/orders/{$payID}";
            //https://api-m.sandbox.paypal.com/v2/checkout/orders/
            $headers['Accept'] = "application/json";
            $headers['Accept-Language'] = "en_US";
            $headers['Content-Type'] = "application/json";
            $headers['Authorization'] = "Bearer $accessToken";
            $args['headers'] = $headers;
	        $args['timeout'] = 90;
            $data = wp_remote_get($url, $args);
	        $data = wp_remote_retrieve_body($data);
	        $data = json_decode($data);
            return $data;

        }

	    function handleWebHook($payload) {
		    if($this->PayPalMode === 'sandbox')
			    $paypalapi = new PayPalAPI($this->PayPalMode, $this->client_id_sandbox, $this->client_secret_sandbox);
		    else
			    $paypalapi = new PayPalAPI($this->PayPalMode, $this->client_id, $this->client_secret);
		    if ( array_key_exists( "event_type", $payload ) ) {
			    $event_type       = $payload['event_type'];
			    $event_version    = $payload['event_version'];
			    $resource         = $payload['resource'];
			    $resource_version = $payload['resource_version'];
			    $transection_id = wpdm_valueof( $payload, 'resource/billing_agreement_id' );

			    switch ( $event_type ) {
				    case 'BILLING.SUBSCRIPTION.CANCELLED':
                        $order = (new Order())->getOrderByTID($transection_id);
					    $order->cancel();
					    break;

				    case 'PAYMENT.SALE.COMPLETED':
					    $order = (new Order())->getOrderByTID($transection_id);
					    Order::renewOrder( $order->order_id, $transection_id );
					    break;
			    }
		    }

        }

        function createWebHook() {
	        if($this->PayPalMode === 'sandbox')
		        $paypalapi = new PayPalAPI($this->PayPalMode, $this->client_id_sandbox, $this->client_secret_sandbox);
	        else
		        $paypalapi = new PayPalAPI($this->PayPalMode, $this->client_id, $this->client_secret);

            $response = $paypalapi->createWebhook();
            return ['success' => true, 'response' => $response];
        }

    }

    add_filter("wpdmpp_admin_order_details_trans_id", function ($trans_id, $payment_method){
        if(strtolower($payment_method) === 'paypal')
            $trans_id =  "<a target='_blank' href='https://www.paypal.com/billing/subscriptions/{$trans_id}'>{$trans_id}</a>";
        return $trans_id;
    }, 10, 2);
}

