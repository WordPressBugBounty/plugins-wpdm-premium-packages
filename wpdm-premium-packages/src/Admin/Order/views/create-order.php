<?php
/**
 * Create new order
 *
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

use WPDMPP\UI\Icons;

global $wpdb;

$order_id = uniqid();

$sbilling =  array
(
    'first_name' => '',
    'last_name' => '',
    'company' => '',
    'address_1' => '',
    'address_2' => '',
    'city' => '',
    'postcode' => '',
    'country' => '',
    'state' => '',
    'email' => '',
    'order_email' => '',
    'phone' => ''
);

?>
<?php ob_start(); ?>

<table width="100%" cellspacing="0" class="table">
    <thead>
    <tr>
        <th align="left"><?php _e("Item Name","wpdm-premium-packages");?></th>
        <th align="left"><?php _e("Unit Price","wpdm-premium-packages");?></th>
        <th align="left"><?php _e("Quantity","wpdm-premium-packages");?></th>
        <th align="right" style="width: 150px;text-align: right"><?php _e("Subtotal","wpdm-premium-packages");?></th>
        <th align="right" style="width: 60px;text-align: right"></th>
    </tr>
    </thead>
    <tbody id="admin-cart-body">

    </tbody>

</table>
<?php $content = ob_get_clean(); ?>


    <div class="row">
        <div class=" col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading"><?php _e("Order ID", "wpdm-premium-packages"); ?></div>
                <div class="panel-body">
                    <span class="lead">&mdash; &mdash; &mdash; &mdash;</span>
                </div>
            </div>
        </div>
        <div class=" col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading"><?php _e("Order Date", "wpdm-premium-packages"); ?></div>
                <div class="panel-body">
                    <span class="lead"><?php echo wp_date("M d, Y h:i a", time()); ?></span>
                </div>
            </div>
        </div>
        <div class=" col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading"><?php _e("Order Total", "wpdm-premium-packages"); ?></div>
                <div class="panel-body">
                    <span class="lead" id="ototal"><?php echo $currency_sign ; ?>0.00</span>
                </div>
            </div>
        </div>

        <div style="clear: both"></div>
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading"><?php _e("Order Items", "wpdm-premium-packages"); ?></div>
				<?php echo $content; ?>
                <div class="panel-footer">
                    <button class="btn btn-info" type="button"  data-toggle="modal" data-target="#myModal"><?php echo Icons::get('plus-circle', 14); ?> Add Item</button>
                    <button class="btn btn-danger btn-ec" type="button"><?php echo Icons::get('trash', 14); ?> Empty Cart</button>
                    <button class="btn btn-success btn-sord" type="button"><?php echo Icons::get('save', 14); ?> Save Order</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="myModalLabel"><?php _e('Select Product','wpdm-premium-packages'); ?></h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><?php echo Icons::get('close', 14); ?></button>
                </div>
                <div class="modal-body">
                    <input type="text" placeholder="<?php _e('Search Product...','wpdm-premium-packages'); ?>" class="form-control input-lg" id="srcp">
                    <br/>
                    <div class="list-group" id="productlist"></div>
                </div>
            </div>
        </div>
    </div>



<script>

    jQuery(function($){

        // Scheme-relative REST URL inherits the page protocol (avoids HTTP/HTTPS mixed-content/CORS).
        var _wpdmppSearchUrl   = <?php echo wp_json_encode( wp_make_link_relative( wpdm_rest_url( 'search' ) ) ); ?>;
        // Pre-rendered SVG icon. Kept in a JS var so the SVG's double-quoted attributes
        // don't terminate the surrounding JS string literal (parse error).
        var _wpdmppPlusIcon    = <?php echo wp_json_encode( Icons::get( 'plus-circle', 16, 'color-green' ) ); ?>;
        var _wpdmppSearchTimer = null;
        var _wpdmppSearchXhr   = null;
        // Nonce for the admin-cart AJAX endpoints (wpdmpp_admin_cart_html / wpdmpp_empty_cart).
        // Both run __::isAuthentic('wpdmpp_cart_nonce', WPDM_PRI_NONCE, ...) and die without it,
        // which previously left "Empty Cart" unable to actually clear the server-side cart.
        var _wpdmppCartNonce   = <?php echo wp_json_encode( wp_create_nonce( WPDM_PRI_NONCE ) ); ?>;

        function _wpdmppEscape(s) {
            return $('<div/>').text(s == null ? '' : String(s)).html();
        }

        function _wpdmppRowHtml(pid, license, index, label) {
            return '<div class="list-group-item">' +
                '<a style="opacity:1;margin-right:-5px;transform:scale(1.4)" href="#"' +
                ' data-pid="' + _wpdmppEscape(pid) + '"' +
                ' data-license="' + _wpdmppEscape(license) + '"' +
                ' data-index="' + _wpdmppEscape(index) + '"' +
                ' class="pull-right insert-pid">' + _wpdmppPlusIcon + '</a>' +
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
                        var licenses = pkg.licenses;
                        var title    = _wpdmppEscape(pkg.post_title);
                        if (!licenses) {
                            $list.append(_wpdmppRowHtml(pkg.ID, '', i, title));
                        } else {
                            $.each(licenses, function (licid, license) {
                                var label = title + ' &mdash; <span class="text-info">' + _wpdmppEscape(license.name) + '</span>';
                                $list.append(_wpdmppRowHtml(pkg.ID, licid, i, label));
                            });
                        }
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


        $('#admin-cart-body').html(<?php echo wp_json_encode( '<tr><td colspan="4">' . Icons::spinner(14) . ' Fetching Cart...</td></tr>' ); ?>);
        $.get(ajaxurl, {action: 'wpdmpp_admin_cart_html', wpdmpp_cart_nonce: _wpdmppCartNonce}, function (res) {
            $('#admin-cart-body').html(res.cart_html);
            $('#ototal').html(res.cart_total);
        });

        $('body').on('click', '.insert-pid', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            $(this).html(<?php echo wp_json_encode( Icons::spinner(16) ); ?>);

            //wpdmpp_admin_cart.push($(this).data('pid')."|".$(this).data('license'));

            //window.localStorage.setItem("wpdmpp_admin_cart", JSON.stringify(wpdmpp_admin_cart));

            var $this = $(this);
            $.get('<?= home_url('/') ?>', {addtocart: $(this).data('pid'), license: $(this).data('license'), custom_order: 1}, function (res) {
                //$('#admin-cart-body').html(res.cart_html);
                //$('#ototal').html(res.cart_total);
                $.get(ajaxurl, {action: 'wpdmpp_admin_cart_html', wpdmpp_cart_nonce: _wpdmppCartNonce}, function (res) {
                    $('#admin-cart-body').html(res.cart_html);
                    $('#ototal').html(res.cart_total);
                });
                $this.html(<?php echo wp_json_encode( Icons::get('check-circle', 16, 'color-green') ); ?>);
            });


        });



        $('.btn-ec').on('click', function () {
            wpdm_boot_popup("Clearing Cart", "Are you sure?", [
                {
                    class: 'btn btn-danger',
                    label: 'Yes, Clear!',
                    callback: function () {
                        var modal = this;
                        $.get(ajaxurl, {action: 'wpdmpp_empty_cart', wpdmpp_cart_nonce: _wpdmppCartNonce}, function (){
                            $('#admin-cart-body').html(<?php echo wp_json_encode( '<tr><td colspan="4">' . Icons::get('shopping-cart', 14) . ' ' . __( 'Cart is empty', 'wpdm-premium-packages' ) . '</td></tr>' ); ?>);
                            $('#ototal').html('<?php echo esc_js( $currency_sign ); ?>0.00');
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

        });

        $('body').on('click', '.btn-delete-cart-item', function (e) {
            e.preventDefault();
            if(!confirm('<?= esc_attr__( 'Delete item from cart?', WPDMPP_TEXT_DOMAIN ) ?>')) return;
            var pid = $(this).data('pid');
            $.get('<?= home_url('/') ?>', {wpdmpp_remove_cart_item: pid}, function (){
                // Re-read the server cart so both the rows and the order total stay
                // in sync (just hiding the row left #ototal stale until reload).
                $.get(ajaxurl, {action: 'wpdmpp_admin_cart_html', wpdmpp_cart_nonce: _wpdmppCartNonce}, function (res) {
                    $('#admin-cart-body').html(res.cart_html);
                    $('#ototal').html(res.cart_total);
                });
            });
        });

        $('.btn-sord').on('click', function () {
            wpdm_boot_popup("Saving Order", "You won't be able to edit order items after saving it. Please re-check if all items are added properly", [
                {
                    class: 'btn btn-success',
                    label: 'Save Order',
                    callback: function () {
                        //$('#admin-cart-body').html('<tr><td colspan="4"><?php echo Icons::spinner(14); ?> Saving Cart...</td></tr>');
                        var $this = this;
                        $this.find('.modal-body').html('<?php echo Icons::spinner(14); ?> Saving Order...');
                        $.get(ajaxurl, {action: 'wpdmpp_admin_save_custom_order', oid: '<?php echo $order_id; ?>', __nonce: '<?php echo wp_create_nonce(NONCE_KEY); ?>'}, function (res) {
                            if(res.status == 1) {
                                window.localStorage.removeItem("wpdmpp_admin_cart");
                                location.href = "edit.php?post_type=wpdmpro&page=orders&task=vieworder&id="+res.oid;
                                $this.modal('hide');
                            }
                            else
                                alert(res);
                        });
                    }
                },
                {
                    class: 'btn btn-default',
                    label: 'Check Again',
                    callback: function () {
                        this.modal('hide');
                    }

                }
            ]);

        });


    });
</script>
<style>
    .chzn-search input{ display: none; }.chzn-results{ padding-top: 5px !important; }
    .btn-group.bootstrap-select .btn{ border-radius: 3px !important; }
    a:focus{ outline: none !important; }
    .panel-heading{ font-weight: bold; }
    .text-renew *{ font-weight: 800; color: #1e9460; }
    .w3eden .dropdown-menu > li{ margin-bottom: 0; }
    .w3eden .dropdown-menu > li > a{ padding: 5px 20px; }
</style>
