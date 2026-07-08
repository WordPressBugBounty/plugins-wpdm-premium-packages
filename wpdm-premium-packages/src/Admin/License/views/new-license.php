<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
global $wpdb;

$wpdmpp_license_form_error_key = 'wpdmpp_license_form_error_' . get_current_user_id();
$wpdmpp_license_form_error     = get_transient( $wpdmpp_license_form_error_key );
if ( $wpdmpp_license_form_error ) {
    delete_transient( $wpdmpp_license_form_error_key );
}
?>
<div class="w3eden">
    <?php
    $menus = [
        ['link' => "edit.php?post_type=wpdmpro&page=pp-license", "name" => __("All Licenses", "wpdm-premium-packages"), "active" => false],
        ['link' => "edit.php?post_type=wpdmpro&page=pp-license&task=NewLicense", "name" => __("New License", "wpdm-premium-packages"), "active" => true],
    ];

    WPDM()->admin->pageHeader(esc_attr__( "Licenses", "wpdm-premium-packages" ), 'id-card color-purple', $menus);
    ?>
    <?php if ( $wpdmpp_license_form_error ) : ?>
        <div class="notice notice-error" style="margin: 12px 0;"><p><?php echo esc_html( $wpdmpp_license_form_error ); ?></p></div>
    <?php endif; ?>

    <div class="wpdm-admin-page-content" id="wpdm-wrapper-panel">
        <div class="panel-body">
                <div class="container">
                    <div class="row">
                        <div class="col-md-8 col-md-offset-2">
                <form method="post" action="" id="add-license-form">
                    <input type="hidden" name="do" value="addlicense">
                    <?php wp_nonce_field(NONCE_KEY, '__suc'); ?>
                    <div class="form-group">
                        <label><?php _e('License No:','wpdm-premium-packages'); ?></label>
                        <input id="title" class="form-control input-lg" type="text"  name="license[licenseno]" value="<?php echo \WPDMPP\License\LicenseService::getInstance()->generateLicenseKey(); ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-4"> <div class="form-group">
                                <label><?php _e('Order ID:','wpdm-premium-packages'); ?></label>
                                <input id="title" class="form-control" type="text"  name="license[oid]">
                            </div></div>
                        <div class="col-md-4"><div class="form-group">
                                <label><?php _e('Product ID:','wpdm-premium-packages'); ?><span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input class="form-control" type="text" id="lpid" required="required"  name="license[pid]">
                                    <div class="input-group-btn"><button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#product-src-modal"><?php echo \WPDMPP\UI\Icons::get('search-plus', 12); ?></button></div>
                                </div>
                            </div></div>
                        <div class="col-md-4"><div class="form-group">
                                <label><?php _e('Domain Limit:','wpdm-premium-packages'); ?></label>
                                <input class="form-control" type="number" size="5" min="0" step="1"  name="license[domain_limit]" value="" placeholder="<?php esc_attr_e('Auto', 'wpdm-premium-packages'); ?>"/>
                                <em><?php _e('Leave empty to set it from the purchased license', 'wpdm-premium-packages'); ?></em>
                            </div></div>

                    </div>
                    <div class="form-group">
                        <label><?php _e('Domains:','wpdm-premium-packages'); ?></label>
                        <textarea class="form-control" cols="60" rows="6" name="license[domain]"></textarea>
                        <em><?php _e("One domain per line. Don't use 'http://' or 'www' only 'domain.com'","wpdm-premium-packages"); ?></em>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group">
                                <label><?php _e('Activation Date:','wpdm-premium-packages'); ?></label>
                                <input class="form-control" id="actdate" type="text" name="license[activation_date]" value="" />
                            </div></div>
                        <div class="col-md-6"><div class="form-group">
                                <label><?php _e('Expire Period:','wpdm-premium-packages'); ?></label>

                                    <input id="expdate" class="form-control" type="text"  name="license[expire_date]" value=""/>

                            </div></div>

                    </div>



                    <div class="form-group well text-right">
                        <button class="btn btn-primary btn-lg"><?php echo \WPDMPP\UI\Icons::get('save', 16); ?> Add New License</button>
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

        $('#actdate, #expdate').datetimepicker({dateFormat:"yy-mm-dd", timeFormat: "hh:mm tt"});

        // Scheme-relative REST URL inherits the page protocol (avoids HTTP/HTTPS mixed-content/CORS).
        var _wpdmppSearchUrl   = <?php echo wp_json_encode( wp_make_link_relative( wpdm_rest_url( 'search' ) ) ); ?>;
        // Pre-rendered SVG icon. Kept in a JS var so the SVG's double-quoted attributes
        // don't terminate the surrounding JS string literal (parse error).
        var _wpdmppPlusIcon    = <?php echo wp_json_encode( \WPDMPP\UI\Icons::get( 'plus-circle', 16 ) ); ?>;
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
    });
</script>
