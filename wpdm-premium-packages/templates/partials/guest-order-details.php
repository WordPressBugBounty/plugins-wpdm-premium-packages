<?php
/**
 * Order details Template for [wpdm-pp-guest-orders] shortcode
 *
 * This template can be overridden by copying it to yourtheme/download-manager/partials/guest-order-details.php.
 *
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

global $wpdmpp_settings;
$wpdmpp_settings['order_validity_period'] = (int)$wpdmpp_settings['order_validity_period'] > 0 ? (int)$wpdmpp_settings['order_validity_period'] : 365;

if( \WPDM\__\Session::get('guest_order') ){

    $csign              =  wpdmpp_currency_sign();
    $link               = get_permalink();
    $order              = new \WPDMPP\Libs\Order();
    $order              = $order->GetOrder(\WPDM\__\Session::get('guest_order'));
    $order->currency    = maybe_unserialize($order->currency);
    $csign              = isset($order->currency['sign']) ? $order->currency['sign'] : '$';
    $csign_before       = wpdmpp_currency_sign_position() == 'before' ? $csign : '';
    $csign_after        = wpdmpp_currency_sign_position() == 'after' ? $csign : '';
    $cart_data          = unserialize($order->cart_data);
    $items              = \WPDMPP\Libs\Order::GetOrderItems($order->order_id);

    if (count($items) == 0) {
        foreach ($cart_data as $pid => $noi) {
            $newi = get_posts(array('post_type' => 'wpdmpro', 'meta_key' => '__wpdm_legacy_id', 'meta_value' => $pid));
            $new_cart_data[$newi[0]->ID] = array("quantity" => $noi, "variation" => "", "price" => get_post_meta($newi[0]->ID, "__wpdm_base_price", true));
            $new_order_items[] = $newi[0]->ID;
        }

        \WPDMPP\Libs\Order::Update(array('cart_data' => serialize($new_cart_data), 'items' => serialize($new_order_items)), $order->order_id);
        \WPDMPP\Libs\Order::UpdateOrderItems($new_cart_data, $order->order_id);
        $items = \WPDMPP\Libs\Order::GetOrderItems($order->order_id);
    }

    $order->title = $order->title ? $order->title : sprintf(__('Order # %s', 'wpdm-premium-packages'), $order->order_id);
    ?>
    <div class="card card-default card-purchases">
        <div class="card-header">
            <?php if ($order->order_status == 'Completed') { //Show invoice button ?>
                <span class="pull-right"  style="margin-top:-3px;">
                    <button class="btn btn-primary btn-xs white btn-billing" id="edit-billing" data-toggle="modal" data-target="#billing-modal">
                        <i class="fas fa-pencil-alt"></i> <?php _e("Edit Billing Info", "wpdm-premium-packages"); ?>
                    </button>
                    <a class="btn btn-info btn-xs white btn-invoice" href="#"
                       onclick="window.open('?id=<?php echo $order->order_id; ?>&amp;wpdminvoice=1','<?php _e("Invoice", "wpdm-premium-packages"); ?>','height=720, width = 800, toolbar=0'); return false;">
                        <i class="fa fa-file-text-o"></i> <?php _e('Invoice', 'wpdm-premium-packages'); ?>
                    </a>
                    <a class="btn btn-danger btn-xs" href="<?php echo home_url('/?exitgo=1'); ?>">Logout</a>
                </span>
                <?php echo $order->title; ?>
            <?php }else{ ?>
                <a href="<?php echo $link; ?>"><?php _e("Purchases","wpdm-premium-packages"); ?></a> &nbsp;<i class="fa fa-angle-double-right"></i>  &nbsp;<?php echo $order->title; ?>
            <?php } ?>
        </div>
        <div class="card-body1">
            <table class="table" style="margin:0;border:0;">
                <thead>
                <tr>
                    <th><?php _e("Product", "wpdm-premium-packages"); ?></th>
                    <th><?php _e("Quantity", "wpdm-premium-packagee"); ?></th>
                    <th><?php _e("Unit Price", "wpdm-premium-packages"); ?></th>
                    <th><?php _e("Coupon Discount", "wpdm-premium-packages"); ?></th>
                    <!--<th><?php /*_e("License", "wpdm-premium-packages"); */?></th>-->
                    <th class='text-right' align='right'><?php _e("Total", "wpdm-premium-packages"); ?></th>
                    <th class='text-right' align='right'><?php _e("Download", "wpdm-premium-packages"); ?></th>
                </tr>
                </thead>
                <?php

                $total = 0;

                foreach ($items as $item) {
                    $ditem          = get_post($item['pid']);

                    if ( ! is_object( $ditem ) ) {
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
                    $_variations    = isset($item['variations']) ? maybe_unserialize($item['variations']) : [];

                    if(is_array($_variations)) {
	                    foreach ( $_variations as $vr ) {
		                    $variations .= "{$vr['name']}: +{$csign_before}" . number_format( floatval( $vr['price'] ), 2 ) . $csign_after;
		                    $prices     += number_format( floatval( $vr['price'] ), 2, ".", "" );
	                    }
                    }

                    $itotal         = number_format(((($item['price'] + $prices) * $item['quantity']) - $discount - $item['coupon_discount']), 2, ".", "");
                    $total         += $itotal;
                    $download_link  = \WPDMPP\WPDMPremiumPackage::customerDownloadURL( $item['pid'], $order->order_id ); //home_url("/?wpdmdl={$item['pid']}&oid={$order->order_id}");
                    //$download_link  = home_url("/?wpdmgdl=".trim(base64_encode("{$item['pid']}|{$order->order_id}|".time()), "="));
                    $licenseurl     = home_url("/?task=getlicensekey&file={$item['pid']}&oid={$order->order_id}");
                    $order_item     = "";

                    if ($order->order_status == 'Completed') {
                        if (get_post_meta($item['pid'], '__wpdm_enable_license_key', true) ===  1) {

                            $licenseg = <<<LIC
<a id="lic_{$item['pid']}_{$order->order_id}_btn" onclick="return getkey('{$item['pid']}','{$order->order_id}', '#'+this.id);" class="btn btn-primary btn-xs" data-placement="top" data-toggle="popover" href="#"><i class="fa fa-key white"></i></a>
LIC;

                        } else
                            $licenseg = "&mdash;";

                        $indf       = "";
                        $files      = maybe_unserialize(get_post_meta($ditem->ID, '__wpdm_files', true));

                        $cart       = maybe_unserialize($order->cart_data);
                        $sfiles     = $cart[$item['pid']]['files'];
                        $sfiles     = is_array($sfiles)?array_keys($sfiles):array();
                        $cfiles     = array();

                        foreach ($sfiles as $fID){
                            if(isset($files[$fID]))
                                $cfiles[$fID] = $files[$fID];
                        }

                        if(count($cfiles) > 0)
                            $files  = $cfiles;

                        if (count($files) > 1 && $order->order_status == 'Completed') {
                            $index  = 0;

                            foreach ($files as $ind => $ff) {
                                $data   = get_post_meta($ditem->ID, '__wpdm_fileinfo', true);
                                $title  = isset($data[$ind], $data[$ind]['title']) ? $data[$ind]['title'] : basename($ff);
                                $index  = $ind;
                                $ff     = "<a class='list-group-item ttip' title='".__('Click to download', WPDMPP_TEXT_DOMAIN)."' style='padding:10px 15px;' href='{$download_link}&ind={$index}'>" . $title . " <span class='pull-right'><i class='fa fa-arrow-alt-circle-down'></i></span></a>";
                                $indf   .= "$ff";
                            }
                        }

                        $discount       = number_format(floatval($discount), 2,".", ",");
                        $item['price']  = number_format($item['price'], 2,".", ",");
                        ?>

                        <tr class="item">
                        <td><?php echo $ditem->post_title.'<br>'.$variations; ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo wpdmpp_price_format($item['price']); ?></td>
                        <td><?php echo wpdmpp_price_format($item['coupon_discount']); ?></td>

                        <!--<td id="lic_<?php /*echo $item['pid'].'_'.$order->order_id; */?>" ><?php /*echo $licenseg; */?></td>-->
                        <td class='text-right' align='right'><?php echo wpdmpp_price_format($itotal); ?></td>

                    <?php } else {

                        $discount = number_format(floatval($discount), 2);
                        $item['price'] = number_format($item['price'], 2);

                        ?>
                        <tr class="item">
                        <td><?php echo $ditem->post_title.'<br>'.$variations; ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo wpdmpp_price_format($item['price']); ?></td>
                        <td><?php echo wpdmpp_price_format($item['coupon_discount']); ?></td>

                        <!--<td>&mdash;</td>-->
                        <td class='text-right' align='right'><?php echo wpdmpp_price_format($itotal); ?></td>
                        <?php
                    }

                    $single_items_actions = apply_filters("wpdmpp_order_item_details_frontend", "", $order, $ditem);

                    if ($order->order_status == 'Completed') {
	                    $show_download_button = (
		                    //When there are multiple files and multi-file download is not disabled
		                    (is_array($files) && count($files) > 1 && !(int)get_wpdmpp_option('disable_multi_file_download', 0))
		                    // Or when there is only one file
		                    || ( is_array($files) && count($files) === 1 )
	                    );

                        $spec = "";
                        if (count($files) > 1) $spec = <<<SPEC
<a class="btn btn-xs btn-success btn-group-item" href="#" data-toggle="modal" data-target="#dpop" onclick="jQuery('#dpop .modal-body').html(jQuery('#indvd-{$ditem->ID}').html());"><i class="fa fa-list"></i></a></div><div  id="indvd-{$ditem->ID}" style="display:none;"><div class='list-group list-group-flush'>{$indf}</div>
SPEC;

                        ?>

                        <td class='text-right' align='right'>
                            <div class="btn-group">
	                            <?php if ($show_download_button){ ?>
                                <a href="<?php echo $download_link; ?>" class="btn btn-xs btn-success btn-group-item"><i class="fa fa-arrow-down white"></i></a>
                                <?php } ?>
                                <?php echo $spec.$single_items_actions; ?>
                            </div>
                        </td>
                        </tr>
                    <?php } else { ?>
                        <td  class='text-right' align='right'>&mdash;</td>
                        </tr>
                    <?php }

                    $order_item = apply_filters("wpdmpp_order_item", "", $item);

                    if ($order_item != '') { ?>
                        <tr><td colspan='6'><?php echo $order_item; ?></td></tr>
                    <?php }
                }


                $vdlink = __("If you still want to complete this order ", "wpdm-premium-packages");
                $vdlink_expired = sprintf(__("If you want to get continuous support and update for another %d days", "wpdm-premium-packages"), $wpdmpp_settings['order_validity_period']);
                $pnow = __("Pay Now", "wpdm-premium-packages");
                $pnow_expired = __("Renew Now", "wpdm-premium-packages");
                $order->cart_discount = number_format($order->cart_discount, 2, ".", "");
                $order->total = number_format($order->total, 2, ".", "");

                ?>

                <tr class="item">
                    <td colspan="5" class='text-right' align='right'><b><?php _e("Discount", "wpdm-premium-packages"); ?></b></td>
                    <td class='text-right' align='right'><b><?php echo wpdmpp_price_format($order->cart_discount); ?></b></td>

                </tr>
                <tr class="item">
                    <td colspan="5" class='text-right' align='right'><b><?php _e("Total", "wpdm-premium-packages"); ?></b></td>
                    <td class='text-right' align='right'><b><?php echo wpdmpp_price_format($order->total); ?></b></td>

                </tr>
            </table>


        </div>
        <div class="modal fade " tabindex="-1" role="dialog" id="dpop"  data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title m-0 text-white"><?php _e('Purchased Files','wpdm-premium-packages'); ?></h5>
                    </div>
                    <div class="modal-body p-0">

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-xs btn-secondary" data-dismiss="modal">
                             <?php _e('Close', 'wpdm-premium-packages'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php

        if ($order->order_status != 'Completed') {
            if ($order->order_status == 'Expired') {
                $vdlink = $vdlink_expired;
                $pnow = $pnow_expired;
            }
            $purl = home_url('/?pay_now=' . $order->order_id);
            ?>
            <div class="card-footer" style="line-height: 30px !important;">
                <b><?php _e("Order Status", "wpdm-premium-packages"); ?> : <span class='label label-danger'><?php echo $order->order_status; ?></span></b>&nbsp;&nbsp;
                <div class='pull-right'>
                    <?php echo $vdlink; ?>
                    <div class="pull-right" style="margin-left:10px" id="proceed_<?php echo $order->order_id; ?>">
                        <a class='btn btn-success white btn-sm' onclick="return proceed2payment_<?php echo $order->order_id; ?>(this)" href="#"><b><?php echo $pnow; ?></b></a>
                    </div>
                </div>
            </div>
            <script>
                function proceed2payment_<?php echo $order->order_id; ?>(ob){
                    jQuery('#proceed_<?php echo $order->order_id; ?>').html('Processing...');
                    jQuery.post('<?php echo $purl; ?>',{action:'wpdmpp_payment_intent', order_id:'<?php echo $order->order_id; ?>'},function(res){
                        jQuery('#proceed_<?php echo $order->order_id; ?>').html(res);
                    });
                    return false;
                }
            </script>
            <?php
        }

        $homeurl = home_url('/');
        ?>
    </div>
    <script>


        //To show license TaCs
        function viewlic(file, order_id){
            var res = " You have to accept these Terms and Conditions before using this product.";
            jQuery('#lic_'+file+'_'+order_id+'_view').html("<i class='fa fa-spin fa-spinner white'></i>");
            jQuery('#lic_'+file+'_'+order_id+'_view').popover({html: true, title: "Terms and Conditions<button class='pull-right btn btn-danger btn-xs xx' rel='#lic_"+file+"_"+order_id+"_view' id='cppo'>&times;</button>", content: res}).popover('show');
            jQuery('#lic_'+file+'_'+order_id+'_view').html("<i class='fa fa-copy white'></i>");

            jQuery('.xx').on("click",function(e) {

                jQuery(jQuery(this).attr("rel")).popover('destroy');
                return false;
            });

            return false;
        }
    </script>
    <style>.white{ color: #ffffff !important; } </style>

    <?php //include wpdm_tpl_path('partials/order-notes.php', WPDMPP_TPL_DIR);

}
