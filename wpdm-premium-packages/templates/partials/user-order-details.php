<?php
/**
 * Order details in Frontend User Dashboard >> Purchases >> Order details page
 *
 * This template can be overridden by copying it to yourtheme/download-manager/user-dashboard/order-details.php.
 *
 * @version     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
global $wpdmpp_settings;
$current_user = wp_get_current_user();
$currency_sign = wpdm_valueof($order, 'currency/sign');
$download_button_label = esc_attr__( 'Download', 'download-manager' );

    if(\WPDM\__\Session::get('wpdm_global_msg_success')){
        echo "<div class='alert alert-success'>".esc_html(\WPDM\__\Session::get('wpdm_global_msg_success'))."</div>";
        \WPDM\__\Session::clear('wpdm_global_msg_success');
    }
    if(\WPDM\__\Session::get('wpdm_global_msg_error')){
        echo "<div class='alert alert-danger'>".esc_html(\WPDM\__\Session::get('wpdm_global_msg_error'))."</div>";
        \WPDM\__\Session::clear('wpdm_global_msg_error');
    }

    ?>

    <?php do_action("wpdmpp_before_order_details", $order); ?>

    <div class="card card-default card-purchases dashboard-card mb-3">
        <div class="card-header">
            <?php if ( $order->order_status == 'Completed' ) { //Show invoice button ?>
                <span class="pull-right btn-group">
                    <button id="od-fullwidth-view" class="btn btn-xs btn-primary ttip hidden-xs" title="Toggle Full-Width"><i class="fa fa-arrows-alt-h"></i></button>
                    <button type="button" class="btn btn-info btn-xs white btn-invoice" onclick="WPDM.popupWindow('<?=  home_url("/?id={$order->order_id}&wpdminvoice=1") ?>', 'Invoice', 800, 810)">
                        <i class="fa-solid fa-file-invoice"></i> <?php _e('Invoice', 'wpdm-premium-packages'); ?>
                    </button>
                    <?php echo $extbtns; ?>
                </span>
                <a href="<?php echo $link; ?>" style="display:inline;with:auto;"><?php _e("All Orders","wpdm-premium-packages"); ?></a> &nbsp;<i class="fa fa-angle-double-right"></i>&nbsp;<?php echo $order->title; ?>
            <?php } else { ?>
                <span class="pull-right btn-group">
                    <button id="od-fullwidth-view" class="btn btn-xs btn-primary ttip hidden-xs" title="Toggle Full-Width"><i class="fa fa-arrows-alt-h"></i></button>
                    <?php echo $extbtns; ?>
                </span>
                <a href="<?php echo $link; ?>"><?php _e("All Orders","wpdm-premium-packages"); ?></a>&nbsp;<i class="fa fa-angle-double-right"></i>&nbsp;<?php echo $order->title; ?>
            <?php } ?>
        </div>
        <div class="card-body1">
            <table class="table wpdm-table-clean" style="margin:0">
                <thead>
                <tr>
                    <th><?php _e("Product","wpdm-premium-packages"); ?></th>
                    <th class="hidden-xs"><?php _e("Quantity","wpdm-premium-packages"); ?></th>
                    <th class="hidden-xs"><?php _e("Unit Price","wpdm-premium-packages"); ?></th>
                    <?php if($coupon_discount > 0){ ?>
                        <th class="hidden-xs"><?php _e("Coupon Discount","wpdm-premium-packages"); ?></th>
                    <?php }
                    if($role_discount > 0){
                        ?>
                        <th class="hidden-xs"><?php _e("Role Discount","wpdm-premium-packages"); ?></th>
                    <?php } ?>
                    <th><?php _e("License","wpdm-premium-packages"); ?></th>
                    <th class='text-right' align='right'><?php _e("Total","wpdm-premium-packages"); ?></th>
                    <?php if ($order->order_status == 'Completed') { ?>
                        <th class='text-right hidden-xs' align='right'><?php _e("Download","wpdm-premium-packages"); ?></th>
                    <?php } ?>
                </tr>
                </thead>
                <?php
                $total = 0;
                $cart_items = maybe_unserialize($order->cart_data);

                foreach ($items as $item) {
	                $ditem = null;
                    if(wpdm_valueof($item, 'product_type') !== 'dynamic')
                        $ditem = get_post($item['pid']);

                    $discount_r     = $item['role_discount'];
                    $prices         = 0;
                    $variations     = "";
                    $discount       = $discount_r;

                    $itotal         = number_format(((($item['price'] + $prices) * $item['quantity']) - $discount - $item['coupon_discount']), 2, ".", "");
                    $total          += $itotal;
                    $download_link  = \WPDMPP\WPDMPremiumPackage::customerDownloadURL($item['pid'], $order->order_id); //home_url("/?wpdmdl={$item['pid']}&oid={$order->order_id}");
                    $licenseurl     = home_url("/?task=getlicensekey&file={$item['pid']}&oid={$order->order_id}");
                    $order_item     = "";

                    $license = maybe_unserialize($item['license']);

                    $license = isset($license['info'], $license['info']['name'])?'<span class="ttip color-purple" title="'.esc_html($license['info']['description']).'">'.sprintf(__("%s License","wpdm-premium-packages"), $license['info']['name']).'</span>':'';



                        if (get_post_meta($item['pid'], '__wpdm_enable_license_key', true) == 1 && $order->order_status == 'Completed') {
                            $licenseg = <<<LIC
<a id="lic_{$item['pid']}_{$order->order_id}_btn" onclick="return getkey('{$item['pid']}','{$order->order_id}', '#'+this.id);" class="btn btn-primary btn-xs" data-placement="top" data-toggle="popover" href="#"><i class="fa fa-key white"></i></a>
LIC;
                        } else
                            $licenseg = "&mdash;";

                        $indf   = "";
                        //$files  = maybe_unserialize(get_post_meta($item['pid'], '__wpdm_files', true));
	                    $files = WPDM()->package->getFiles($item['pid'], true);
                        $cart   = maybe_unserialize($order->cart_data);

                        $srcfiles = "";

                        if (is_array($files) && count($files) > 5)
                            $srcfiles = '<input placeholder="' . sprintf(__("Search File... ( Total Files: %d )", "download-manager"), count($files)) . '" style="border-radius: 0;border: 0 !important;border-bottom: 1px solid #939393 !important;" type="search" class="form-control bg-white wpdm-ord-search-file" data-filelist="#wpdm-filelist-area-' . $item['pid'] . '" />';


	                    $sfiles = isset($cart[$item['pid']], $cart[$item['pid']]['files'])?$cart[$item['pid']]['files']: array();
                        $sfiles = is_array($sfiles) ? $sfiles: array();
                        $cfiles = array();
                        foreach ($sfiles as $fID){
                            if($fID !== '')
                                $cfiles[$fID] = $files[$fID];
                        }

                        //Get files for purchased license

                        if(count($cfiles) === 0) {
                            $all_licenses = wpdmpp_get_licenses();
                            $starter = array_keys($all_licenses)[0];
                            $_license = wpdm_valueof($cart, "{$item['pid']}/license/id");
                            if(!$_license) $_license = $starter;
                            $license_pack = $ditem ? get_post_meta($item['pid'], "__wpdm_license_pack", true) : '';
                            $license_pack = wpdm_valueof($license_pack, $_license);
                            if(is_array($license_pack)) {
                                foreach ($license_pack as $fID) {
                                    if(isset($files[$fID]))
                                        $cfiles[$fID] = $files[$fID];
                                }
                            }
                        }

                        if(is_array($cfiles) && count($cfiles) > 0)
                            $files = $cfiles;

                        if(!is_array($files)) $files = [];

                        if (count($files) > 1 && $order->order_status == 'Completed') {
                            $index = 0;

                            foreach ($files as $ind => $ff) {
                                $data = $ditem ? get_post_meta($ditem->ID,'__wpdm_fileinfo', true) : [];
	                            $title = isset($data[$ind], $data[$ind]['title']) && !empty($data[$ind]['title']) ? $data[$ind]['title'] : basename($ff);
                                $index = $ind;
                                $ff = "<li class='list-group-item'>" . $title . " <a class='btn btn-xs btn-success pull-right' href=\"{$download_link}&ind={$index}\">".__("Download","download-manager")."</a></li>";
                                $indf .= "$ff";
                            }
                        }
                        $discount = number_format(floatval($discount), 2);
                        $item['price'] = number_format($item['price'], 2);


                        ?>

                        <tr class="item">
                        <td>
                            <div class="media">
                                <div class="mr-3">
                                    <?php WPDMPP()->cart->itemThumb($item, true, ['style' => 'width: 38px']) ?>
                                </div>
                                <div class="media-body">
                                    <div><strong><?php WPDMPP()->cart->itemLink($item) ?></strong></div><?php WPDMPP()->cart->itemInfo($item); ?>
                                </div>
                            </div>
                        </td>
                        <td class="hidden-xs"><?php echo $item['quantity']; ?></td>
                        <td class="hidden-xs"><?php echo wpdmpp_price_format($item['price'], $currency_sign, true); ?></td>
                        <?php if($coupon_discount > 0){ ?>
                            <td class="hidden-xs"><?php echo wpdmpp_price_format($item['coupon_discount'], $currency_sign, true); ?></td>
                        <?php }
                        if($role_discount > 0){
                            ?>
                            <td class="hidden-xs"><?php echo wpdmpp_price_format($discount, $currency_sign, true); ?></td>
                        <?php } ?>
                        <td id="lic_<?php echo $item['pid'].'_'.$order->order_id; ?>" ><?php echo $licenseg; ?></td>
                        <td class='text-right wpdmpp-amount-col' align='right'><?php echo WPDMPP()->order->itemCost($item, $currency_sign); ?></td>

                    <?php

                    if ($order->order_status == 'Completed') {

                        $show_download_button = (
                                //When there are multiple files and multi-file download is not disabled
                                (is_array($files) && count($files) > 1 && !(int)get_wpdmpp_option('disable_multi_file_download', 0))
                                // Or when there is only one file
                                || ( is_array($files) && count($files) === 1 )
                        );

                        $spec = "";
                        if (is_array($files) && count($files) > 1)
                            $spec = <<<SPEC
<a class="btn btn-xs btn-success btn-group-item" href="#" data-toggle="modal" data-target="#dpop" onclick="jQuery('#dpop .modal-body').html(jQuery('#indvd-{$ditem->ID}').html());">{$download_button_label}</a></div><div  id="indvd-{$ditem->ID}" style="display:none;">{$srcfiles}<ul style="max-height: 300px;overflow: auto" id="wpdm-filelist-area-{$ditem->ID}" class='list-group list-group-flush m-0'>{$indf}</ul>
SPEC;
                        ?>

                        <td class='text-right hidden-xs'>
                            <div class="btn-group">
                                <?php if ($show_download_button){ ?>
                                <a href="<?php echo $download_link; ?>" class="btn btn-xs btn-success btn-group-item"><i class="fa fa-arrow-alt-circle-down white"></i> <?=$download_button_label ?></a>
                                <?php } ?>
                                <?php echo $spec; ?>
                            </div>
                        </td>
                        </tr>
                        <tr class="visible-xs">
                            <td class='text-center' colspan="3">

                                    <?php if ($show_download_button){ ?>
                                        <a href="<?php echo $download_link; ?>" class="btn btn-xs btn-success btn-group-item"><i class="fa fa-arrow-alt-circle-down white"></i> <?=$download_button_label ?></a>
                                    <?php } ?>
                                    <?php echo $spec; ?>

                            </td>
                        </tr>
                    <?php } else { ?>

                        </tr>
                    <?php }

                    $order_item = apply_filters("wpdmpp_order_item", "", $item);

                    if ($order_item != '') { ?>
                        <tr><td colspan='<?php echo $colspan+1; ?>'><?php echo $order_item; ?></td></tr>
                    <?php }
                }
                $wpdmpp_settings['order_validity_period'] = (int)$wpdmpp_settings['order_validity_period'] > 0 ? (int)$wpdmpp_settings['order_validity_period'] : 365;
                $vdlink         = __("If you still want to complete this order ", "wpdm-premium-packages");
                $vdlink_expired = sprintf(__("Get continuous support and update for another %d days", "wpdm-premium-packages"), $wpdmpp_settings['order_validity_period']);
                $pnow           = __("Pay Now", "wpdm-premium-packages");
                $pnow_expired   = __("Renew Now", "wpdm-premium-packages");
                $usermeta       = maybe_unserialize(get_user_meta($current_user->ID, 'user_billing_shipping', true));

                if(is_array($usermeta)) extract($usermeta);

                $order->cart_discount   = number_format($order->cart_discount, 2, ".", "");
                $order->total           = number_format($order->total, 2, ".", "");
                $total                  = number_format($total, 2, ".", "");
                ?>

                <tr class="item hidden-xs">
                    <td colspan="<?php echo ($order->order_status == 'Completed') ? $colspan : $colspan + 1; ?>" class='text-right' align='right'><b><?php _e("Subtotal","wpdm-premium-packages"); ?></b></td>
                    <td class='text-right wpdmpp-amount-col' ><b><?php echo wpdmpp_price_format($order->subtotal, $currency_sign); ?></b></td>
                    <?php if ($order->order_status == 'Completed') { ?>
                        <td>&nbsp;</td>
                    <?php } ?>
                </tr>
                <tr class="item hidden-xs">
                    <td colspan="<?php echo ($order->order_status == 'Completed') ? $colspan : $colspan + 1; ?>" class='text-right' align='right'><b><?php _e("Coupon Discount","wpdm-premium-packages"); ?></b></td>
                    <td class='text-right wpdmpp-amount-col' align='right'><b>- <?php echo wpdmpp_price_format($order->coupon_discount, $currency_sign); ?></b></td>
                    <?php if ($order->order_status == 'Completed') { ?>
                        <td>&nbsp;</td>
                    <?php } ?>
                </tr>
                <tr class="item hidden-xs">
                    <td colspan="<?php echo ($order->order_status == 'Completed') ? $colspan : $colspan + 1; ?>" class='text-right' align='right'><b><?php _e("Tax","wpdm-premium-packages"); ?></b></td>
                    <td class='text-right wpdmpp-amount-col' align='right'><b>+ <?php echo wpdmpp_price_format($order->tax, $currency_sign); ?></b></td>
                    <?php if ($order->order_status == 'Completed') { ?>
                        <td>&nbsp;</td>
                    <?php } ?>
                </tr>
                <tr class="item hidden-xs">
                    <td colspan="<?php echo ($order->order_status == 'Completed') ? $colspan : $colspan + 1; ?>" class='text-right' align='right'><b><?php _e("Total","wpdm-premium-packages"); ?></b></td>
                    <td class='text-right' align='right'><b><?php echo wpdmpp_price_format($order->total, $currency_sign); ?></b></td>
                    <?php if ($order->order_status == 'Completed') { ?>
                        <td>&nbsp;</td>
                    <?php } ?>
                </tr>
            </table>
        </div>

        <div class="modal fade" id="dpop" data-backdrop="static">
            <div class="modal-dialog  modal-dialog-centered" style="margin-top:100px;">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">

                        <strong class="modal-title"><?php _e('Download Single File','wpdm-premium-packages'); ?></strong>

                    </div>
                    <div class="modal-body p-0">

                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-xs" data-dismiss="modal"><?php _e('Close','wpdm-premium-packages'); ?></button>
                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->




    </div>
    <?php

    if ($order->order_status != 'Completed') {

        $purl = home_url('/?pay_now=' . $order->order_id);
        $homeurl = home_url('/');

        if ($order->order_status == 'Expired') {
            $vdlink = $vdlink_expired;
            $pnow = $pnow_expired;
            $order_specific_manual_renewal = (int)\WPDMPP\Libs\Order::getMeta($order_id, 'manual_renew');
            $disable_manual_renewal_period = ((int)get_wpdmpp_option('disable_manual_renewal_period', 90))*86400;
            $disable_manual_renewal_time = $order->expire_date + $disable_manual_renewal_period;
            $manual_renewal_allowed = $disable_manual_renewal_time < time() && (int)get_wpdmpp_option('disable_manual_renew');
            //if ((int)get_wpdmpp_option('disable_manual_renew') !== 1 || $order_specific_manual_renewal === 1) {
            if (!$manual_renewal_allowed || $order_specific_manual_renewal === 1) {
    ?>

    <div class="card p-3 mb-3 card-renew"  id="proceed_<?php echo $order->order_id; ?>">
         <div class="media">
             <div class="media-body" style="line-height: 34px"><?php echo $vdlink; ?></div>
             <div class="ml-3">
                 <a class='btn btn-success white' onclick="return proceed2payment_<?php echo $order->order_id; ?>(this)" href="#"><b><?php echo $pnow; ?></b></a>
             </div>
         </div>
    </div>
            <?php } else{
                ?>
                <div class="card p-3 mb-3 card-renew"  id="proceed_<?php echo $order->order_id; ?>">
                    <div class="media">
                        <div class="media-body" style="line-height: 34px"><?php echo __('Manual renewal for your order is not possible. Please create new order.', WPDMPP_TEXT_DOMAIN); ?></div>
                        <div class="ml-3">
                            <a class='btn btn-success white' href="<?php echo wpdmpp_cart_url(['clone_order' => $order->order_id]) ?>"><b><?php echo __('Create New Order', WPDMPP_TEXT_DOMAIN); ?></b></a>
                        </div>
                    </div>
                </div>
                <?php
            } } else {
            ?>
            <div class="card p-3 mb-3 card-renew"  id="proceed_<?php echo $order->order_id; ?>">
                <div class="media">
                    <div class="media-body" style="line-height: 34px"><?php echo $vdlink; ?></div>
                    <div class="ml-3">
                        <a class='btn btn-success white' onclick="return proceed2payment_<?php echo $order->order_id; ?>(this)" href="#"><b><?php echo $pnow; ?></b></a>
                    </div>
                </div>
            </div>
            <?php
        } ?>
        <script>
            function proceed2payment_<?php echo $order->order_id; ?>(ob){
                jQuery('#proceed_<?php echo $order->order_id; ?>').html('Processing...');
                jQuery.post(wpdm_url.ajax,{action:'wpdmpp_payment_intent', order_id:'<?php echo $order->order_id; ?>'},function(res){
                    jQuery('#proceed_<?php echo $order->order_id; ?>').html(res);
                });
                return false;
            }
        </script>
    <?php } ?>

    <?php do_action("wpdmpp_after_order_details", $order); ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light"><?php _e('Order Status', 'wpdm-premium-packages'); ?></div>
                <div class="card-body"><?php echo $order->order_status; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light"><?php _e('Order Date', 'wpdm-premium-packages'); ?></div>
                <div class="card-body"><?php echo wp_date(get_option('date_format'), $order->date); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light"><?php _e('Payment Method', 'wpdm-premium-packages'); ?></div>
                <div class="card-body"><?php echo WPDMPP()->payment->getMethod($order->payment_method)->GatewayName; ?></div>
            </div>
        </div>
        <?php if(isset($wpdmpp_settings['auto_renew']) && $wpdmpp_settings['auto_renew'] == 1) { ?>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light"><?php _e('Auto Renew', 'wpdm-premium-packages'); ?></div>
                <?php
                $auto_reenew = $order->auto_renew==0?'Inactive':'Active';
                ?>
                <div class="card-body">
	                <?php if($order->order_status == 'Completed'){ ?>

		                <?php echo (int)$order->auto_renew === 1?'<button data-toggle="modal" data-target="#cansubModal" id="btn-acs" class="btn btn-danger btn-xs pull-right">Cancel</button>':'<button id="btn-actsub" class="btn btn-success btn-xs pull-right" onclick="WPDM.bootAlert(\'Reactivate Subscription\', \'Please send an order note to reactivate your subscription\', 400, true)">Activate</button>'; ?>

                        <div class="modal fade" id="cansubModal">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="width: 400px">
                                    <div class="modal-header">

                                        <strong class="modal-title"><?php _e('Cancel Subscription!','wpdm-premium-packages'); ?></strong>

                                    </div>
                                    <div class="modal-body">
                                        <p>
                                            If you cancel auto-renew feature, you will be blocked from support forum and plugin updates after subscription period expires ( <?php echo wp_date(get_option('date_format'),  $order->expire_date); ?> ).<br/>
                                            <strong>Are you sure?</strong>
                                        </p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-success btn-xs" id="btn-ka" data-dismiss="modal"><?php _e('Keep Subscription Active','wpdm-premium-packages'); ?></button>
                                        <button type="button" class="btn btn-danger btn-xs" id="btn-cansub"><?php _e('Cancel Subscription','wpdm-premium-packages'); ?></button>
                                    </div>
                                </div><!-- /.modal-content -->
                            </div><!-- /.modal-dialog -->
                        </div><!-- /.modal -->

	                <?php } ?>
	                <?php echo $auto_reenew; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light"><?php echo $order->auto_renew == 1?'Next Renew':'Expiry'; ?> Date</div>
                <div class="card-body"><?php echo wp_date(get_option('date_format'),  $order->expire_date); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light"><?php _e('Renew Cycle', 'wpdm-premium-packages'); ?></div>
                <?php
                $renew_count = (int)$wpdb->get_var("select count(ID) from {$wpdb->prefix}ahm_order_renews where order_id='{$order->order_id}'");
                $auto_reenew = $order->auto_renew==0?'Inactive':'Active';
                ?>
                <div class="card-body"><?php echo $renew_count; ?></div>
            </div>
        </div>
        <?php } ?>


    </div>


    <script>


        jQuery(function($){
            var fullwidth = 0;
            $('body').on('click','#od-fullwidth-view', function(){
                fullwidth = fullwidth == 0 ? 1 : 0;
                $('#wpdm-dashboard-sidebar').toggle();
                $('#wpdm-dashboard-contents').toggleClass('col-md-9 col-md-12');
            });

            $('#btn-cansub').on('click', function () {
                var $this = $(this);
                $('#btn-ka, #btn-acs').attr('disabled','disabled');
                $this.attr('disabled','disabled').html('<i class="fa fa-spin fa-refresh"></i>');
                $.post(wpdm_url.ajax, {action: 'wpdmpp_cancel_subscription', __cansub: '<?php echo wp_create_nonce(NONCE_KEY) ?>', orderid: '<?php echo $order->order_id ?>'}, function (res) {
                    $this.html('Canceled');
                    $('#csstatus').removeClass('badge-success').addClass('badge-danger').html('Inactive');
                    $('#btn-acs').html('Canceled');
                    $('#cansubModal').modal('hide');
                });
            });

            $('body').on("keyup", '.wpdm-ord-search-file', function () {
                var value = $(this).val().toLowerCase();
                var filelist_item = $(this).data('filelist') + " li";
                $(filelist_item).filter(function () {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
        });

    </script>

    <?php

    include wpdm_tpl_path('partials/renew-invoices.php', WPDMPP_TPL_DIR);

    include wpdm_tpl_path('partials/order-notes.php', WPDMPP_TPL_DIR);

