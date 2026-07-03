<?php
/**
 * Template for User Dashboard >> Downloads >> Coupon Codes submenu page
 *
 * @version     1.0.0
 */

if(!defined('ABSPATH')) die();
global $wpdb;
$limit  = 20;
$page   = wpdm_query_var('paged', 'int');
$page   = $page > 0 ? $page : 1;
$start  = ( $page - 1 ) * $limit;

// Build WHERE clauses with proper escaping
$where_clauses = array();
$where_values = array();

if(wpdm_query_var('code', 'txt') != '') {
    $where_clauses[] = "code LIKE %s";
    $where_values[] = '%' . $wpdb->esc_like(wpdm_query_var('code', 'txt')) . '%';
}

if(wpdm_query_var('description', 'txt') != '') {
    $where_clauses[] = "description LIKE %s";
    $where_values[] = '%' . $wpdb->esc_like(wpdm_query_var('description', 'txt')) . '%';
}

if(wpdm_query_var('product', 'int') > 0) {
    $where_clauses[] = "product = %d";
    $where_values[] = wpdm_query_var('product', 'int');
}

$cond = '';
if(count($where_clauses) > 0) {
    $cond = "WHERE " . implode(" OR ", $where_clauses);
}

if (!empty($where_values)) {
    $where_values[] = $start;
    $where_values[] = $limit;
    $coupon_codes = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ahm_coupons {$cond} ORDER BY ID DESC LIMIT %d, %d", $where_values));
    array_pop($where_values); // Remove limit
    array_pop($where_values); // Remove start
    $total_codes = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ahm_coupons {$cond}", $where_values));
} else {
    $coupon_codes = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ahm_coupons ORDER BY ID DESC LIMIT %d, %d", $start, $limit));
    $total_codes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ahm_coupons");
}
?>
<div class="w3eden payout-entries">
    <?php
    $menus = [
        ['link' => "edit.php?post_type=wpdmpro&page=pp-coupon-codes", "name" => __("All Coupons", "wpdm-premium-packages"), "active" => true],
        ['link' => "edit.php?post_type=wpdmpro&page=pp-coupon-codes&task=new_coupon", "name" => __("Add New", "wpdm-premium-packages"), "active" => false],
    ];

    $actions = [
        [ "type" => "button",  "class" => "danger btn-sm", "name" => \WPDMPP\UI\Icons::get('trash', 16) . ' '.esc_attr__( 'Delete Selected', WPDMPP_TEXT_DOMAIN ) , "attrs" => ["id" => "apply", "style" => "display:none"]],
    ];

    WPDM()->admin->pageHeader(esc_attr__( "Coupon Codes", "wpdm-premium-packages" ), 'ticket-alt fas color-purple', $menus, $actions);
    ?>

    <div class="wpdm-admin-page-content">
    <!--<div class="panel panel-default" id="wpdm-wrapper-panel">
        <div class="panel-heading">
            <b><i class="fas fa-ticket-alt color-purple"></i> &nbsp; <?php /*_e("Coupon Codes","wpdm-premium-packages");*/?></b>
            <div class="pull-right">
                <a href="edit.php?post_type=wpdmpro&page=pp-coupon-codes&task=new_coupon" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle"></i> <?php /*_e("Add New","wpdm-premium-packages");*/?></a>
                <a href="#" class="btn btn-sm btn-info src-coupon"><i class="fas fa-search"></i> <?php /*_e("Search","wpdm-premium-packages");*/?></a>
                <a href="#" class="btn btn-sm btn-default" id="delsel"><i class="fas fa-trash"></i> <?php /*_e("Delete Selected","wpdm-premium-packages");*/?></a>
            </div>
        </div>-->
        <div class="panel-body-np">

            <form method="get" action="edit.php">
                <input type="hidden" name="post_type" value="wpdmpro">
                <input type="hidden" name="page" value="pp-coupon-codes">
                <input type="hidden" name="task" value="search_coupon">
                <div class="panel panel-default" >
                    <div class="panel-body">
                        <div class="col-md-3">
                            <input type="text" placeholder="<?php _e("Coupon Code:","wpdm-premium-packages");?>" class="form-control" name="code" value="<?php echo stripslashes(wpdm_query_var('code')); ?>">
                        </div>
                        <div class="col-md-3">
                            <input type="text" placeholder="<?php _e("Product ID:","wpdm-premium-packages");?>" class="form-control" name="product" value="<?php echo stripslashes(wpdm_query_var('product')); ?>">
                        </div>
                        <div class="col-md-4">
                            <input type="text" placeholder="<?php _e("Description:","wpdm-premium-packages");?>" class="form-control" name="description" value="<?php echo stripslashes(wpdm_query_var('description')); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-secondary btn-block action"><?php _e("Search","wpdm-premium-packages");?></button>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <b><?php printf(__('%d coupon code(s) found','wpdm-premium-packages'), $total_codes); ?></b>
                    </div>
                </div>
            </form>
            <div class="panel panel-default">
            <table class="table table-striped table-wpdmpp">
                <thead>
                <tr>
                    <th style="width: 50px"><input type="checkbox" id="allc"></th>
                    <th><?php _e("Coupon Code","wpdm-premium-packages");?></th>
                    <th><?php _e("Discount","wpdm-premium-packages");?></th>
                    <th><?php _e("Type","wpdm-premium-packages");?></th>
                    <th><?php _e("Product","wpdm-premium-packages");?></th>
                    <th><?php _e("Expire Date","wpdm-premium-packages");?></th>
                    <th><?php _e("Usage / Limit","wpdm-premium-packages");?></th>
                    <th><?php _e("Spend Limit (min/max)","wpdm-premium-packages");?></th>
                    <th><?php _e("Auto-Apply","wpdm-premium-packages");?></th>
                    <th style="width: 180px"><?php _e("Action","wpdm-premium-packages");?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($coupon_codes as $coupon_code){
                    $product = get_post($coupon_code->product); ?>
                    <tr id="cr-<?php echo (int) $coupon_code->ID; ?>">
                        <td><input type="checkbox" class="allc" value="<?php echo (int) $coupon_code->ID; ?>" name="id[]"></td>
                        <td><strong <?php if($coupon_code->expire_date > 0 && $coupon_code->expire_date < time()) echo 'class="expired-coupon color-red ttip" title="Expired Coupon"'; ?>><?php echo esc_html( $coupon_code->code ); ?></strong></td>
                        <td><?php echo esc_html( $coupon_code->discount ); ?></td>
                        <td><?php echo $coupon_code->type == 'percent' ? '%' : esc_html( wpdmpp_currency_sign() ); ?></td>
                        <td><?php echo $coupon_code->product > 0 ? "<a href=''>" . esc_html( get_the_title( $coupon_code->product ) ) . "</a>" : '<span class="color-purple">' . __( 'Global Coupon', WPDMPP_TEXT_DOMAIN ) . '</span>'; ?></td>
                        <td><?php echo $coupon_code->expire_date > 0 ? esc_html( wp_date( get_option( 'date_format' ) . " h:i a", $coupon_code->expire_date ) ) : __( 'Never', "wpdm-premium-packages" ); ?></td>
                        <td><a href="#" onclick="WPDM.bootAlert('<?php echo esc_js(__('Orders with coupon code', WPDMPP_TEXT_DOMAIN)); ?>: <?php echo esc_js($coupon_code->code); ?>', {url: ajaxurl+'?action=wpdmpp_get_couponed_orders&coupon_code=<?php echo esc_js(urlencode($coupon_code->code)); ?>&cononce=<?php echo esc_js(wp_create_nonce(WPDM_PRI_NONCE)); ?>'}, 500); return false;"><?php echo (int) $coupon_code->used; ?> / <?php echo $coupon_code->usage_limit > 0 ? (int) $coupon_code->usage_limit : '∞'; ?></a></td>
                        <td><?php echo (int) $coupon_code->min_order_amount; ?> / <?php echo $coupon_code->max_order_amount == 0 ? 'No Limit' : (int) $coupon_code->max_order_amount; ?></td>
                        <td><?php echo (int) $coupon_code->auto_apply ? \WPDMPP\UI\Icons::get('check-double', 16, 'text-success') : \WPDMPP\UI\Icons::get('close', 16, 'text-danger'); ?></td>
                        <td>
                            <a href="edit.php?post_type=wpdmpro&page=pp-coupon-codes&task=edit_coupon&ID=<?php echo (int) $coupon_code->ID; ?>" class="btn btn-xs btn-info"><?php echo \WPDMPP\UI\Icons::get('pencil', 12); ?> <?php _e('Edit','wpdm-premium-packages'); ?></a>
                            <a href="#" rel="<?php echo (int) $coupon_code->ID; ?>" class="btn btn-xs btn-danger btn-delcoup"><?php echo \WPDMPP\UI\Icons::get('trash', 12); ?> <?php _e('Delete','wpdm-premium-packages'); ?></a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            </div>
            <div class="text-center">
                <?php
                $total = $wpdb->get_var("select count(*) from {$wpdb->prefix}ahm_coupons");
                echo wpdm_paginate_links($total, $limit, $page, 'paged');
                ?>
            </div>
        </div>
    </div>
</div>

<style>
    .expired-coupon{
        cursor: default;
    }
</style>
<script>
    jQuery(function ($) {
        $('.ttip').tooltip({placement: 'right'});
        $('.src-coupon').click(function (e) {
            e.preventDefault();
            $('#src-coupon').slideToggle();
        });
        $('.btn-delcoup').on('click', function (e) {
            e.preventDefault();
            var row = $('#cr-'+this.rel);
            var cpid = this.rel;
            wpdm_boot_popup("Deleting a Coupon", "Are you sure?", [
                {
                    class: 'btn btn-danger',
                    label: 'Yes, Delete!',
                    callback: function () {
                        this.find(".modal-body").html('<p><?php echo \WPDMPP\UI\Icons::spinner(16); ?> Deleting...</p>');
                        var modal = this;
                        $.get(ajaxurl + `?action=wpdmpp_delete_coupon&dcpnonce=<?php echo wp_create_nonce(WPDM_PRI_NONCE); ?>&ID=${cpid}`, function () {
                            row.slideUp();
                            modal.modal('hide');
                        });
                    }
                },
                 {
                    class: 'btn btn-default',
                    label: 'No, Later.',
                    callback: function () {
                        this.modal('hide');
                    }

                 }
            ]);
            //if(!confirm('Are you sure?')) return false;

        });
        // "Delete Selected" stays hidden until at least one coupon is ticked.
        function wpdmppToggleCouponDeleteBtn() {
            $('#apply').toggle($('.allc:checked').length > 0);
        }

        $('#allc').on('change', function () {
            $('.allc').prop('checked', $(this).prop('checked'));
            wpdmppToggleCouponDeleteBtn();
        });
        $(document).on('change', '.allc', function () {
            var $all = $('.allc');
            $('#allc').prop('checked', $all.length > 0 && $all.length === $all.filter(':checked').length);
            wpdmppToggleCouponDeleteBtn();
        });
        $('#delsel, #apply').on('click', function (e) {
            e.preventDefault();
            if(!confirm('Are you sure?')) return false;
            $('.allc').each(function () {
                if($(this).is(":checked")) delete_cc($(this).val());

            });
            $('#allc').prop('checked', false);
            $('#apply').hide();
        });

        function delete_cc(id) {
            var row = $('#cr-'+id);
            $('#cr-'+id).addClass('color-red');
            $.get(ajaxurl+'?action=wpdmpp_delete_coupon&dcpnonce=<?php echo wp_create_nonce(WPDM_PRI_NONCE); ?>&ID='+id, function () {
                row.slideUp();
            })
        }
    })
</script>
