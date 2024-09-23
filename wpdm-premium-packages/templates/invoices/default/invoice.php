<?php
/**
 * Premium Package Invoice Template
 *
 * This template can be overridden by copying it to yourtheme/download-manager/invoices/default/invoice.php.
 *
 * @version     1.0.0
 */

use WPDM\__\Messages;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! is_user_logged_in() && ! \WPDM\__\Session::get('guest_order') ) {

    $orderid    = isset( $_GET['id'] ) ? sanitize_text_field($_GET['id']) : '';
    $orderurl   = wpdm_user_dashboard_url().'?udb_page=purchases/order/' . $orderid;

    ?><div class="require-login">Please <a href="<?php echo wp_login_url( $orderurl ); ?>"><b>Login or Register</b></a> to access this page.</div><?php
    die();
} else {

    global $wpdb, $current_user;
    $settings           = get_option('_wpdmpp_settings');
    $_ohtml             = "";
    $oid = sanitize_text_field($_GET['id']);
    $order              = new \WPDMPP\Libs\Order();
    $oid                = is_user_logged_in() ? $oid : \WPDM\__\Session::get('guest_order');
    $order              = $order->GetOrder($oid);
$order->currency    = maybe_unserialize($order->currency);
$csign              = is_array($order->currency) && isset($order->currency['sign']) ? $order->currency['sign'] : '$';
$csign_before       = wpdmpp_currency_sign_position() == 'before' ? $csign : '';
$csign_after        = wpdmpp_currency_sign_position() == 'after' ? $csign : '';

//echo '<pre>';print_r($order);echo '</pre>';die();
$user_billing = maybe_unserialize(get_user_meta($order->uid, 'user_billing_shipping', true));
$user_billing = isset($user_billing['billing'])?$user_billing['billing']:array();
$billing_defaults =  array
(
'first_name'    => '',
'last_name'     => '',
'company'       => '',
'address_1'     => '',
'address_2'     => '',
'city'          => '',
'postcode'      => '',
'country'       => '',
'state'         => '',
'order_email'   => '',
'email'   => '',
'phone'         => '',
'taxid'         => ''
);

if ( ( isset( $settings['billing_address'] ) && $settings['billing_address'] == 1 ) || $order->uid == 0){

// Asked billing address in checkout, Here we use order specific billing info
// Or guest order invoice. Billing info is linked to the order

$billing_info_from_order    = unserialize($order->billing_info);
$billing_defaults               = shortcode_atts($billing_defaults, $user_billing);
$billing_info               = shortcode_atts($billing_defaults, $billing_info_from_order);
}
else{
// Skiped billing address in checkout, get billing address from saved user info

$billing_info       = shortcode_atts($billing_defaults, $user_billing);;

// Due to index mismatch in order email and saved billing email
$billing_info['order_email'] = isset($billing_info['email'])?$billing_info['email']:'';
}

if($billing_info['first_name']      == '' ||
$billing_info['last_name']      == '' ||
$billing_info['address_1'].$billing_info['address_2']      == '' ||
$billing_info['postcode']       == '' ||
$billing_info['state'].$billing_info['city']          == ''
){

$updatebilling = wpdm_user_dashboard_url(array('udb_page' => 'edit-profile'));
Messages::warning("Critical billing info is missing. Please update your billing info to generate invoice properly.<br style='margin-bottom: 10px;display: block'/><a class='btn btn-warning' target='_top' onclick=\"window.opener.location.href='$updatebilling';window.close();return false;\" href='#'>Update Billing Info</a>", 1);
}

$sign = get_wpdmpp_option('signature', '');
$coup               = __("Coupon Discount","wpdm-premium-packages");
$role_dis           = __("Role Discount","wpdm-premium-packages");
$item_name_label    = __('Item Name', 'wpdm-premium-packages');
$quantity_label     = __('Quantity', 'wpdm-premium-packages');
$unit_price_label   = __('Unit Price', 'wpdm-premium-packages');
$net_subtotal_label = __('Subtotal', 'wpdm-premium-packages');
$discount_label     = __('Discount', 'wpdm-premium-packages');
$nettotal_label     = __('Total', 'wpdm-premium-packages');
$total_label        = __('Total', 'wpdm-premium-packages');
$vat_label          = __('Tax', 'wpdm-premium-packages');

$ordertotal         = number_format($order->total, 2);
$unit_prices        = unserialize($order->unit_prices);
$cart_discount      = number_format($order->discount, 2);
$tax                = number_format($order->tax, 2);

$item_table         = <<<OTH
    <table class="info table table-borderless mb-0 position-relative bg-white" style="z-index: 2;">
    <thead class="sidebar-bg text-white">
    <tr id="header_row">
        <th>{$item_name_label}</th>
        <th>{$quantity_label}</th>
        <th class='item_r' style="text-align: right;">{$unit_price_label}</th>
        <!--th class='item_r' style="text-align: right;">{$coup}</th>
        <th class='item_r' style="text-align: right;">{$role_dis}</th-->
        <th class='item_r' style="text-align: right;">{$net_subtotal_label}</th>
    </tr>
    </thead>
    <!--tfoot>
    <tr id="discount_tr">
        <td colspan="3" class="item_r" style="text-align:right">{$discount_label}</td>
        <td class="item_r text-right">{$csign_before}{$cart_discount}{$csign_after}</td>
    </tr>
    <tr id="vat_tr">

        <td  colspan="3" class="item_r" style="text-align:right" class="item_r">{$vat_label}</td>
        <td class="item_r text-right">{$csign_before}{$tax}{$csign_after}</td>
    </tr>
    <tr id="total_tr">

        <td  colspan="3" class="item_r" style="text-align:right" class="total" id="total_currency">{$total_label}</td>
        <td class="total text-right">{$csign_before}{$ordertotal}{$csign_after}</td>
    </tr>
    </tfoot-->
    <tbody class="table-striped">
    OTH;
    $items = \WPDMPP\Libs\Order::GetOrderItems($order->order_id);
    $total = 0;
    foreach ($items as $item) {

    $ditem = get_post($item['pid']);
    if (! is_object( $ditem ) ) {
    $ditem              = new stdClass();
    $ditem->ID          = 0;
    $ditem->post_title  = "[Item Deleted]";
    }

    $meta           = get_post_meta($ditem->ID, 'wpdmpp_list_opts', true);
    $price          = $item['price'] * $item['quantity'];
    $discount_r     = $item['role_discount'];
    $prices         = 0;
    $variations     = "";
    $discount       = $discount_r;


    $itotal         = WPDMPP()->order->itemCost($item);
    $total          += $itotal;
    $order_item     = "";
    $discount       = number_format(floatval($discount), 2);
    $item['price']  = number_format($item['price'], 2);
    $item_info = WPDMPP()->cart->itemInfo($item, false);
    $product_name = $item['product_name'] ? $item['product_name'] : get_the_title($item['pid']);
    $_ohtml .= <<<ITEM
        <tr class="item">
        <td class="text-left" style="padding-left: 1.8rem"><strong>{$product_name}</strong><br><small>{$item_info}</small></td>
        <td class="text-center">{$item['quantity']}</td>
        <td class="text-right">{$csign_before}{$item['price']}{$csign_after}</td>
        <!--td class="text-right">{$csign_before}{$item['coupon_discount']}{$csign_after}</td>
        <td class="text-right">{$csign_before}{$discount}{$csign_after}</td -->
        <td class='text-right' align='right'>{$csign_before}{$itotal}{$csign_after}</td>
        ITEM;


        $order_item = apply_filters("wpdmpp_order_item", "", $item);

        if ( $order_item != '' )
        $_ohtml .= "<tr><td colspan='7'>" . $order_item . "</td></tr>";
    }

    $item_table .= $_ohtml."</tbody></table>";
	$billing_info['phone'] = $billing_info['phone'] ? "<div id='phone'>".__('Phone', WPDMPP_TEXT_DOMAIN).": {$billing_info['phone']}</div>" : '';
	$billing_info['taxid'] = $billing_info['taxid'] ? "<div id='phone'>".__('Tax ID', WPDMPP_TEXT_DOMAIN).": {$billing_info['taxid']}</div>" : '';
$invoice['client_info'] = <<<CINF
<div class="vcard" id="client-details">
    <h4 class="fn">{$billing_info['first_name']} {$billing_info['last_name']}</h4>
    <div class="org"><h3>{$billing_info['company']}</h3></div>
    <div class="sfs">
    <div class="adr">
        <div class="street-address">
            {$billing_info['address_1']}
            {$billing_info['address_2']}
        </div>
        <!-- street-address -->
        <div class="locality">{$billing_info['postcode']}, {$billing_info['city']}, {$billing_info['state']}, {$billing_info['country']}</div>
        <div id="client-email"><span class="order-email">Email: {$billing_info['order_email']}</span></div>
    </div>
    <!-- adr -->
    {$billing_info['phone']}
    {$billing_info['taxid']}
    </div>
</div>
CINF;
}
?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download Manager - Invoice</title>
      <style>

      </style>
	  <?php \WPDM\__\Apply::uiColors(true); ?>
    <link rel="stylesheet" href="<?= WPDMPP_BASE_URL ?>templates/invoices/default/assets/bootstrap.min.css">
    <link rel="stylesheet" href="<?= WPDMPP_BASE_URL ?>templates/invoices/default/assets/main.css">
</head>
  <body>
    <section class="next-invoice mx-auto">
        <div class="wrapper bg-white position-relative shadow-lg">
            <div class="container-fluid px-0">
                <div class="row gx-0">
                    <div class="col-sm-4 content-bg">
                        <div class="invoice-logo-wrapper position-relative sidebar-bg p-4">
	                        <?php if($settings['invoice_logo'] != ""){ ?>
                                <img style="width: auto; height: 50px;" class="media-object" src="<?php echo $settings['invoice_logo']; ?>">
	                        <?php } ?>
                            <img class="corners img-fluid" style="filter: grayscale(1);" src="<?= WPDMPP_BASE_URL ?>templates/invoices/default/assets/corners.png" alt="Shape">
                        </div>
        
                        <div class="invoice-to p-4 text-white">
                            <h6 class="mt-2">Invoice To:</h6>
	                        <?php echo $invoice['client_info']; ?>
                        </div>
                    </div>
        
                    <div class="col-sm-4 ms-auto">
                        <div class="invoice-id my-4 px-4  position-relative">
                            <h1 class="mb-1 text-accent"><?php echo __('INVOICE', WPDMPP_TEXT_DOMAIN) ?></h1>
                            <p>#<?= $order->order_id ?></p>
                            <p><?php _e('Date', WPDMPP_TEXT_DOMAIN); ?>: <?php echo wp_date(get_option('date_format'),(isset($_GET['renew']) ? (int)$_GET['renew'] : $order->date)); ?></p>
	                        <?php /* if(isset($_GET['renew'])){ ?>
                                <p><?php _e('Order Renewed On','wpdm-premium-packages'); ?>
	                                <?php echo wp_date(get_option('date_format'),(int)$_GET['renew']); ?>
                                </p>
	                        <?php } */ ?>
                        </div>
        
                        <div class="invoice-from pb-5">
                            <h6><?php _e('Invoice From', WPDMPP_TEXT_DOMAIN); ?>:</h6>
                            <h4 class="mb-1"><?php bloginfo('sitename'); ?></h4>
                            <p><?php echo nl2br($settings['invoice_company_address']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="container-fluid px-0">
                <div class="table-wrap px-4 position-relative table-responsive-md">
                    <?php echo $item_table; ?>
                </div>
            </div>
    
            <div class="container-fluid px-0">
                <div class="row gx-0">
                    <div class="col-sm-4 p-4 d-flex content-bg text-white">
                        <div class="invoice-method align-self-end">
                            <h6 class=""><?php _e('Payment Method', WPDMPP_TEXT_DOMAIN); ?>:</h6>
                            <p><?php echo str_replace("WPDM_", "", $order->payment_method); ?></p>
                        </div>
                    </div>
    
                    <div class="col-sm-8 px-4 pb-4">
                        <div class="row">
                            <div class="col pt-5 text-center align-self-end">
                                <?php if ($sign) { ?>
                               <div class="sign mx-auto">
                                <img class="img-fluid" src="<?= $sign ?>"  alt="signiture">
                                <h6 class="text-center pt-3 mt-3 border-top mb-4"><?php _e('AUTHORIZED SIGN', WPDMPP_TEXT_DOMAIN); ?></h6>
                               </div>
                                <?php } ?>
                            </div>
                            <div class="col">
                                <table class="total table table-borderless">
                                    <tbody class="table-striped">
                                        <tr>
                                            <th><?= $net_subtotal_label ?></th>
                                            <td><?= wpdmpp_price_format($order->subtotal); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= $discount_label ?></th>
                                            <td class="pb-4">-<?= wpdmpp_price_format($order->coupon_discount); ?></td>
                                        </tr>
                                        <?php if($tax > 0) { ?>
                                        <tr>
                                            <th><?= $vat_label ?></th>
                                            <td><?= $tax ?></td>
                                        </tr>
                                        <?php } ?>
                                        <tr class="sidebar-bg text-white sidebar-bg">
                                            <th><?= $nettotal_label ?></th>
                                            <td><?= wpdmpp_price_format($order->total); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row gx-0">
                    <div class="col-sm-4 p-4 content-bg text-white">
                        <div class="invoice-contact pt-4">
                            <h6><?= __('Payment Status', WPDMPP_TEXT_DOMAIN)?></h6>
                            <p><?= __('Completed', WPDMPP_TEXT_DOMAIN) ?></p>
                        </div>
                    </div>
                    <div class="col-sm-8 px-5 pt-3 pb-4">
                        <h5 class="mb-3 mt-0"><?php echo get_wpdmpp_option('invoice_thanks'); ?></h5>
                        <?php $terms = get_wpdmpp_option('invoice_terms_acceptance');
                        if($terms) {  ?>
                        <h6><?php _e('Terms & Conditions', WPDMPP_TEXT_DOMAIN); ?>:</h6>
                        <?php echo wpautop($terms); ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-border sidebar-bg position-relative">
            <svg xmlns="http://www.w3.org/2000/svg" class="position-absolute end-0" viewBox="0 0 15.42 15.42" style="height: 40px; top: -40px"><defs><style>.cls-1{fill:var(--color-primary);}</style></defs><g id="Layer_2" data-name="Layer 2"><g id="Layer_1-2" data-name="Layer 1"><polygon class="cls-1" points="15.42 0 15.42 15.42 0 15.42 15.42 0"/></g></g></svg>
        </div>
    </section>
  </body>
</html>