<?php
/**
 * New / Edit Coupon Code form
 *
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

global $wpdb;
?>
<div class="w3eden">
    <?php
    $menus = [
        ['link' => "edit.php?post_type=wpdmpro&page=pp-coupon-codes", "name" => __("All Coupons", "wpdm-premium-packages"), "active" => false],
        ['link' => "edit.php?post_type=wpdmpro&page=pp-coupon-codes&task=new_coupon", "name" => __("Add New", "wpdm-premium-packages"), "active" => true],
    ];

    WPDM()->admin->pageHeader(esc_attr__( "Coupon Codes", "wpdm-premium-packages" ), 'ticket-alt fas color-purple', $menus);
    ?>

    <div class="wpdm-admin-page-content" id="wpdm-wrapper-panel">
    <!--<div class="panel panel-default" id="wpdm-wrapper-panel">
        <div class="panel-heading">
            <b><i class="fas fa-ticket-alt color-purple"></i> &nbsp;
                <?php /*echo wpdm_query_var('ID') > 0 ? __('Edit Coupon Code', 'wpdm-premium-packages') : __('New Coupon Code', 'wpdm-premium-packages'); */?></b>
            <div class="pull-right">
                <a href="edit.php?post_type=wpdmpro&page=pp-coupon-codes" class="btn btn-sm btn-default">
                    <i class="fas fa-long-arrow-alt-left color-green"></i> <?php /*_e('Back','wpdm-premium-packages'); */?>
                </a>
            </div>
        </div>-->
        <div class="panel-body">
            <div class="container">
                <div class="row">
                    <div class="col-md-8 col-md-offset-2">
                        <form method="post" action="" id="add-license-form">
                            <input type="hidden" name="do" value="<?php echo wpdm_query_var('ID') > 0?'updatecoupon':'addcoupon'; ?>">
                            <?php wp_nonce_field(NONCE_KEY, ((int)wpdm_query_var('ID') > 0?'__ucc':'__anc')); ?>
                            <div class="form-group">
                                <label><?php _e('Coupon Code:','wpdm-premium-packages'); ?> <span class="color-red">*</span></label>
                                <input id="title" class="form-control input-lg" type="text" required="required"  name="coupon[code]"  value="<?php echo isset($coupon)?$coupon->code:''; ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php _e('Discount Type:','wpdm-premium-packages'); ?></label>
                                        <select name="coupon[type]" id="dtypes" class="form-control">
                                            <option value="percent"><?php _e('Percent','wpdm-premium-packages'); ?> (%)</option>
                                            <option value="fixed" <?php echo isset($coupon)?selected('fixed',$coupon->type, false):''; ?>><?php _e('Fixed','wpdm-premium-packages'); ?> (<?php echo wpdmpp_currency_sign(); ?>)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php _e('Discount Amount:','wpdm-premium-packages'); ?> <span class="color-red">*</span></label>
                                        <div class="input-group">
                                            <input class="form-control" type="text" required="required" name="coupon[discount]" placeholder="Any Numeric Value" value="<?php echo isset($coupon)?$coupon->discount:''; ?>">
                                            <span class="input-group-addon color-green" style="width: 40px" id="dtp">%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php _e('Product ID:','wpdm-premium-packages'); ?> <span class="color-purple ttip" title="If you want to allow the coupon on cart total, do not select any product."><?php echo \WPDMPP\UI\Icons::get('info-circle', 16); ?></span></label>
                                        <div class="input-group">
                                            <input class="form-control" type="text" id="lpid" placeholder="Cart Coupon" name="coupon[product]" value="<?php echo isset($coupon)?$coupon->product:''; ?>">
                                            <div class="input-group-btn"><button type="button" class="btn btn-default" data-toggle="modal" data-target="#product-src-modal"><?php echo \WPDMPP\UI\Icons::get('search-plus', 16); ?></button></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><?php _e('Description:','wpdm-premium-packages'); ?></label>
                                <textarea class="form-control" cols="60" rows="4" name="coupon[description]" placeholder="Coupon Description"><?php echo isset($coupon)?$coupon->description:''; ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php _e('Expire Date:','wpdm-premium-packages'); ?></label>
                                        <input class="form-control" placeholder="Never" id="expdate" type="datetime-local" name="coupon[expire_date]" value="<?php echo isset($coupon->expire_date) && $coupon->expire_date > 0 ? esc_attr(wp_date('Y-m-d\TH:i', $coupon->expire_date)) : ''; ?>" />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php _e('Minimum Spend:','wpdm-premium-packages'); ?></label>
                                        <input class="form-control" type="number" placeholder="No Limit"  name="coupon[min_order_amount]" value="<?php echo isset($coupon)?$coupon->min_order_amount:''; ?>"/>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php _e('Maximum Spend:','wpdm-premium-packages'); ?></label>
                                        <input class="form-control" type="number" placeholder="No Limit" name="coupon[max_order_amount]" value="<?php echo isset($coupon)?$coupon->max_order_amount:''; ?>"/>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php _e('Limit Usage:','wpdm-premium-packages'); ?></label>
                                        <input class="form-control" placeholder="Unlimited" type="number" name="coupon[usage_limit]" value="<?php echo isset($coupon)?$coupon->usage_limit:''; ?>" />
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label><?php _e('Allowed Emails:','wpdm-premium-packages'); ?></label>
                                        <input class="form-control" type="text" placeholder="Multiple emails are sperated by comma(,)"  name="coupon[allowed_emails]" value="<?php echo isset($coupon)?$coupon->allowed_emails:''; ?>"/>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="panel panel-default">
                                    <div class="panel-heading">
                                        <input type="hidden" name="coupon[auto_apply]" value="0" />
                                        <label class="m-0">
                                            <input type="checkbox" name="coupon[auto_apply]" value="1" <?php if(isset($coupon)) checked(1, wpdm_valueof($coupon, 'auto_apply', ['validate' => 'int'])) ?> /> <?php _e('Auto-apply coupon code', WPDMPP_TEXT_DOMAIN); ?>
                                        </label>
                                    </div>
                                    <div class="panel-body">
                                        <div class="media">
                                            <div class="pull-right"><button class="btn btn-primary btn-lg"><?php echo \WPDMPP\UI\Icons::get('save', 16); ?> &nbsp;<?php _e('Save Coupon Code','wpdm-premium-packages'); ?></button></div>
                                            <div class="media-body">

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="product-src-modal" tabindex="-1" role="dialog" aria-labelledby="product-src-modalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="product-src-modalLabel"><?php _e('Select Product','wpdm-premium-packages'); ?></h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="text" placeholder="<?php _e('Search Product...','wpdm-premium-packages'); ?>" class="form-control input-lg" id="srcp">
                    <br/>
                    <div class="list-group" id="productlist">

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<script>
    jQuery(function($){
        // Scheme-relative REST URL inherits the page protocol (avoids HTTP/HTTPS mixed-content/CORS).
        var _wpdmppSearchUrl   = <?php echo wp_json_encode( wp_make_link_relative( wpdm_rest_url( 'search' ) ) ); ?>;
        // Pre-rendered SVGs. Kept in JS vars so the SVG's double-quoted attributes
        // don't terminate the surrounding JS string literal (parse error).
        var _wpdmppPlusIcon    = <?php echo wp_json_encode( \WPDMPP\UI\Icons::get( 'plus-circle', 16 ) ); ?>;
        var _wpdmppSpinnerIcon = <?php echo wp_json_encode( \WPDMPP\UI\Icons::spinner( 16 ) ); ?>;
        var _wpdmppSearchTimer = null;
        var _wpdmppSearchXhr   = null;

        function _wpdmppEscape(s) {
            return $('<div/>').text(s == null ? '' : String(s)).html();
        }

        function _wpdmppRowHtml(pid, index, label) {
            return '<div class="list-group-item">' +
                '<a style="opacity:1;margin-right:-5px" href="#"' +
                ' data-pid="' + _wpdmppEscape(pid) + '"' +
                ' data-index="' + _wpdmppEscape(index) + '"' +
                ' class="pull-right wpdm-insert-pid color-green">' + _wpdmppPlusIcon + '</a>' +
                label + '</div>';
        }

        function search_product()
        {
            var keyword = ($('#srcp').val() || '').trim();
            $('#productlist').html('');
            if (keyword.length < 1) return;

            if (_wpdmppSearchXhr && _wpdmppSearchXhr.readyState !== 4) {
                _wpdmppSearchXhr.abort();
            }

            _wpdmppSearchXhr = $.ajax({
                url: _wpdmppSearchUrl,
                method: 'GET',
                dataType: 'json',
                data: { search: keyword, premium: 1 },
                success: function (res) {
                    var pkgs = (res && res.packages) ? res.packages : [];
                    var $list = $('#productlist').html('');
                    if (!pkgs.length) {
                        $list.append('<div class="list-group-item text-muted">' + <?php echo wp_json_encode( __( 'No matching products found.', 'wpdm-premium-packages' ) ); ?> + '</div>');
                        return;
                    }
                    $.each(pkgs, function (i, pkg) {
                        $list.append(_wpdmppRowHtml(pkg.ID, i, _wpdmppEscape(pkg.post_title)));
                    });
                },
                error: function (xhr, status) {
                    if (status === 'abort') return;
                    var msg = <?php echo wp_json_encode( __( 'Search failed:', 'wpdm-premium-packages' ) ); ?>;
                    $('#productlist').html('<div class="list-group-item text-danger">' + msg + ' ' + _wpdmppEscape(xhr.statusText || status) + '</div>');
                }
            });
        }

        // Debounce keystrokes so we don't flood the server while the user types.
        $('body').on('input keyup', '#srcp', function () {
            clearTimeout(_wpdmppSearchTimer);
            _wpdmppSearchTimer = setTimeout(search_product, 200);
        });

        $('body').on('click', '.wpdm-insert-pid', function (e) {
            e.preventDefault();
            $('#lpid').val($(this).data('pid'));
            $('#product-src-modal').modal('hide');
        });

        $('#add-license-form').on('submit', function () {
            var $btn = $('#add-license-form .btn-primary.btn-lg');
            $btn.css('width', $btn.css('width')).html(_wpdmppSpinnerIcon + ' <?php echo esc_js(__('Saving...', 'wpdm-premium-packages')); ?>').attr('disabled', 'disabled');
        });

        $('body').on('change click', '#dtypes', function () {
            var stype = $(this).val() == 'percent' ? '%' : <?php echo wp_json_encode( wpdmpp_currency_sign() ); ?>;
            $('#dtp').html(stype);
        });
        $('.ttip').tooltip();
    });
</script>
