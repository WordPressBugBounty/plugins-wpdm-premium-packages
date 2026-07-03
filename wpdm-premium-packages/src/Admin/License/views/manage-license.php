<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
global $wpdb;
?>
<div class="w3eden">
    <?php
    $menus = [
        ['link' => "edit.php?post_type=wpdmpro&page=pp-license", "name" => __("All Licenses", "wpdm-premium-packages"), "active" => true],
        ['link' => "edit.php?post_type=wpdmpro&page=pp-license&task=NewLicense", "name" => __("Add New", "wpdm-premium-packages"), "active" => false],
    ];

    $actions = [
        [ "type" => "button",  "class" => " btn-sm wpdm-facebook", "name" => \WPDMPP\UI\Icons::get('server', 10), "attrs" => ["id" => "server", "data-toggle" => "modal", "data-target" => "#myModal"]],
        [ "type" => "button",  "class" => "secondary btn-sm", "name" => esc_attr__( 'Delete Selected', WPDMPP_TEXT_DOMAIN ) , "attrs" => ["id" => "apply", "style" => "display:none"]],
    ];

    WPDM()->admin->pageHeader(esc_attr__( "Licenses", "wpdm-premium-packages" ), 'id-card color-purple', $menus, $actions);
    ?>

    <div class="wpdm-admin-page-content">

    <div class="panel-body-np">

    <form method="get" id="search-license-form" action="<?php echo admin_url('edit.php') ?>">
        <input type="hidden" name="post_type" value="wpdmpro">
        <input type="hidden" name="page" value="pp-license">
        <input type="hidden" name="task" value="search_license">
        <div class="panel panel-default">

            <div class="panel-body">
                <div class="col-md-3">
                    <input type="text" placeholder="<?php _e('Order ID:','wpdm-premium-packages'); ?>" class="form-control" name="oid" value="<?php echo wpdm_query_var('oid', 'escattr'); ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" placeholder="<?php _e('License No:','wpdm-premium-packages'); ?>" class="form-control" name="licenseno" value="<?php echo wpdm_query_var('licenseno', 'escattr') ?>">
                </div>
                <div class="col-md-4">
                    <input type="text" placeholder="<?php _e('Website/IP:','wpdm-premium-packages'); ?>" class="form-control" name="link" value="<?php echo wpdm_query_var('link', 'escattr'); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary btn-block action"><?php _e('Search','wpdm-premium-packages'); ?></button>
                </div>

            </div>
            <div class="panel-footer">
                <b class="wpdmpp-license-count"><?php printf(__('%d license(s) found','wpdm-premium-packages'), (int) $t); ?></b>
            </div>
        </div>
    </form>
    <form method="get" action="edit.php"  id="pp-license-form">
        <input type="hidden" name="post_type" value="wpdmpro">
        <input type="hidden" name="page" value="pp-license">
        <input type="hidden" name="task" value="delete_selected">
        <?php wp_nonce_field( NONCE_KEY, '__suc' ); ?>
        <div class="clear"></div>
        <div class="panel panel-default">
        <table cellspacing="0" class="table table-striped table-hover table-wpdmpp">
            <thead>
            <tr>
                <th style="width: 20px" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
                <th style="" class="manage-column column-media" id="media" scope="col"><?php _e('License Key','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-author" id="author" scope="col"><?php _e('Product Name','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-author" id="author" scope="col"><?php _e('Order ID','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-parent" id="parent" scope="col"><?php _e('Activation Date','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-parent" id="parent" scope="col"><?php _e('Expire Date','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-parent" id="parent" scope="col"><?php _e('Domains','wpdm-premium-packages'); ?></th>
            </tr>
            </thead>

            <tfoot>
            <tr>
                <th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
                <th style="" class="manage-column column-media" id="media" scope="col"><?php _e('License Key','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-author" id="author" scope="col"><?php _e('Product Name','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-author" id="author" scope="col"><?php _e('Order ID','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-parent" id="parent" scope="col"><?php _e('Activation Date','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-parent" id="parent" scope="col"><?php _e('Expire Date','wpdm-premium-packages'); ?></th>
                <th style="" class="manage-column column-parent" id="parent" scope="col"><?php _e('Domains','wpdm-premium-packages'); ?></th>
            </tr>
            </tfoot>

            <tbody class="list:post" id="the-list">
            <?php
            foreach ($licenses as $i => $license) {
                $license->domain = maybe_unserialize($license->domain);
                $license->domain = is_array($license->domain)?$license->domain:array();

                $_license = $wpdb->get_var( $wpdb->prepare( "SELECT license FROM {$wpdb->prefix}ahm_order_items WHERE pid = %d AND oid = %s", absint( $license->pid ), $license->oid ) );
                $_license = maybe_unserialize($_license);
                $_license = isset($_license['info'], $_license['info']['name'])?'<span class="ttip color-purple" title="'.esc_html($_license['info']['description']).'"> ' . \WPDMPP\UI\Icons::get('check-square', 16) . ' '.sprintf(__("%s License","wpdm-premium-packages"), $_license['info']['name']).'</span>':'';


                ?>
                <tr valign="top" class="author-self status-inherit" id="post-8">
                    <th class="check-column text-center" scope="row"><input type="checkbox" value="<?php echo (int) $license->id; ?>" name="id[]"></th>
                    <td class="media column-media">
                        <strong>
                            <a title="Edit" href="edit.php?post_type=wpdmpro&page=pp-license&task=editlicense&id=<?php echo (int) $license->id; ?>"><?php echo esc_html( $license->licenseno ); ?></a>
                        </strong>
                    </td>
                    <td class="author column-author"><?php echo esc_html( $license->productname ) . " {$_license}"; ?></td>
                    <td class="author column-author">
                        <a target="_blank" href="edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=<?php echo esc_attr( $license->oid ); ?>"><?php echo esc_html( $license->oid ); ?></a>
                    </td>
                    <td class="parent column-parent"><?php echo $license->activation_date ? esc_html( wp_date( get_option( 'date_format' ), $license->activation_date ) ) : 'Inactive'; ?></td>
                    <td class="parent column-parent"><?php echo $license->expire_date > 0 ? esc_html( wp_date( get_option( 'date_format' ), $license->expire_date ) ) : 'N/A'; ?></td>
                    <td class="parent column-parent"><a href="" class="unlock-license pull-right color-green" style="width: 100px;text-align: left;text-decoration: none;outline: none;" data-lid="<?php echo (int) $license->id; ?>"><?php echo \WPDMPP\UI\Icons::get('unlock', 16); ?> Unlock</a><span id="dcnt-<?php echo (int) $license->id; ?>"> <?php echo (int) count( $license->domain ); ?></span> / <?php echo $license->domain_limit ? (int) $license->domain_limit : 'NoLimit'; ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>
        <?php
        $page_links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => ceil($t / $l),
            'current' => $p
        ));
        ?>

        <div id="ajax-response"></div>
        <div class="tablenav">
            <?php if ($page_links) {
                    $paged = isset($_GET['paged'])?(int)$_GET['paged']:1;
                ?>
                <div class="tablenav-pages">
                    <?php $page_links_text = sprintf('<span class="displaying-num">' . __('Displaying %s&#8211;%s of %s') . '</span>%s',
                        number_format_i18n(($paged - 1) * $l + 1),
                        number_format_i18n(min($paged * $l, $t)),
                        number_format_i18n($t),
                        $page_links
                    );
                    echo $page_links_text; ?></div>
            <?php } ?>


            <br class="clear">
        </div>

    </form>
    </div>
    </div>
    <br class="clear">

    <!-- Modal -->
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="myModalLabel"><?php _e('License Integration','wpdm-premium-packages'); ?></h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="input-group">
                        <div class="input-group-addon">License Server URL</div>
                        <input type="text" readonly="readonly" style="background: #ffffff" class="form-control" value="<?php echo home_url('/'); ?>">
                    </div><br/>
                    <div class="panel panel-default">
                        <div class="panel-heading">Requited Parameters</div>
                    <table class="table table-striped">
                        <tr><th>Parameter Name</th><th>Parameter Value</th></tr>
                        <tr><td>wpdmLicense</td><td>validate</td></tr>
                        <tr><td>licenseKey</td><td>[license-key]</td></tr>
                        <tr><td>domain</td><td>[domain_name_or_ip]</td></tr>
                        <tr><td>productId</td><td>[product_code]</td></tr>
                    </table>
                    </div>

                </div>
                <div class="modal-footer">
                    <a href="https://www.wpdownloadmanager.com/doc/admin-panel-3/license-manager/" class="btn btn-primary" target="_blank">More Details</a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    jQuery(function ($) {
        $('.src-license').click(function (e) {
            e.preventDefault();
            $('#src-license').slideToggle();
        });
        var wpdmppLicDeleteNonce = '<?php echo wp_create_nonce('wpdmpp_delete_licenses'); ?>';

        // In-page toast (WPDM.notify renders the styled toast directly).
        function wpdmppLicToast(message, type) {
            type = type || 'success';
            var heading = (type === 'error')
                ? '<?php echo esc_js(__('Error', 'wpdm-premium-packages')); ?>'
                : '<?php echo esc_js(__('Done', 'wpdm-premium-packages')); ?>';
            WPDM.notify('<strong>' + heading + '</strong><br/>' + message, type, 'top-right');
        }

        // Delete Selected — async, so the page never reloads.
        $('#apply').on('click', function (e) {
            e.preventDefault();
            var ids = $('#pp-license-form input[name="id[]"]:checked').map(function () { return this.value; }).get();
            if (ids.length === 0) {
                wpdmppLicToast('<?php echo esc_js(__('Please select at least one license to delete.', 'wpdm-premium-packages')); ?>', 'warning');
                return false;
            }
            if (!confirm('<?php echo esc_js(__('Are you sure you want to delete the selected license(s)?', 'wpdm-premium-packages')); ?>')) {
                return false;
            }
            WPDM.blockUI('#pp-license-form');
            $.post(ajaxurl, {action: 'wpdmpp_delete_licenses', _wpnonce: wpdmppLicDeleteNonce, ids: ids}, function (res) {
                WPDM.unblockUI('#pp-license-form');
                if (res && res.success) {
                    $.each(res.deleted || [], function (i, id) {
                        $('#pp-license-form input[name="id[]"][value="' + id + '"]').closest('tr').fadeOut(250, function () { $(this).remove(); });
                    });
                    $('#apply').hide();
                    $('#pp-license-form th.column-cb input[type="checkbox"]').prop('checked', false);
                    // Keep the "N license(s) found" count in sync.
                    var $cnt = $('.wpdmpp-license-count'), ctext = $cnt.text(), match = ctext.match(/\d[\d,]*/);
                    if (match) {
                        var n = parseInt(match[0].replace(/,/g, ''), 10) - res.deleted.length;
                        $cnt.text(ctext.replace(/\d[\d,]*/, n < 0 ? 0 : n));
                    }
                    wpdmppLicToast(res.message, 'success');
                } else {
                    wpdmppLicToast((res && res.message) ? res.message : '<?php echo esc_js(__('Could not delete the selected licenses.', 'wpdm-premium-packages')); ?>', 'error');
                }
            }, 'json');
        });

        // "Delete Selected" stays hidden until at least one license is ticked.
        function wpdmppToggleDeleteBtn() {
            var checked = $('#pp-license-form input[name="id[]"]:checked').length;
            $('#apply').toggle(checked > 0);
        }

        // Select-all (header/footer) toggles every row checkbox and stays in sync
        // when rows are ticked individually. Per-row boxes carry name="id[]"; the
        // select-all boxes sit in th.column-cb, so the selectors never overlap.
        // Delegated so they survive the list refresh from #search-license-form.
        $(document).on('change', '#pp-license-form th.column-cb input[type="checkbox"]', function () {
            var checked = this.checked;
            $('#pp-license-form input[name="id[]"]').prop('checked', checked);
            $('#pp-license-form th.column-cb input[type="checkbox"]').prop('checked', checked);
            wpdmppToggleDeleteBtn();
        });
        $(document).on('change', '#pp-license-form input[name="id[]"]', function () {
            var total = $('#pp-license-form input[name="id[]"]').length;
            var checked = $('#pp-license-form input[name="id[]"]:checked').length;
            $('#pp-license-form th.column-cb input[type="checkbox"]').prop('checked', total > 0 && total === checked);
            wpdmppToggleDeleteBtn();
        });
        $('body').on('click', '.unlock-license', function (e) {
            e.preventDefault();
            var $this = $(this);
            var lid = $(this).data('lid');
            $this.html('<?php echo \WPDMPP\UI\Icons::spinner(16); ?> <?php _e('Wait...', 'wpdm-premium-packages'); ?>');
            $.post(ajaxurl, {action: 'wpdm_unlock_license', __suc: '<?php echo wp_create_nonce( NONCE_KEY ); ?>', unlock_license: lid}, function (res) {
                if (res === 'ok') {
                    $this.html('<?php echo \WPDMPP\UI\Icons::get('check-square', 16); ?> <?php _e('Unlocked', 'wpdm-premium-packages'); ?>');
                    $('#dcnt-' + lid).html("0");
                } else {
                    $this.html('<?php echo \WPDMPP\UI\Icons::get('unlock', 16); ?> <?php _e('Unlock', 'wpdm-premium-packages'); ?>');
                    wpdmppLicToast('<?php echo esc_js(__('Could not unlock the license.', 'wpdm-premium-packages')); ?>', 'error');
                }
            });
        });

        $('#search-license-form').submit(function (e) {
            e.preventDefault();
            WPDM.blockUI('#pp-license-form');
            $('#search-license-form').ajaxSubmit({
                success: function (res) {
                    var $res = $(res);
                    $('#pp-license-form').html($res.find('#pp-license-form').html());
                    // The result count lives in the search form's footer, outside
                    // #pp-license-form, so refresh it from the response too — otherwise
                    // it stays frozen at the initial total on every search.
                    $('.wpdmpp-license-count').html($res.find('.wpdmpp-license-count').html());
                    WPDM.unblockUI('#pp-license-form');
                    // Rows re-render unchecked; #apply sits in the page header (outside
                    // #pp-license-form), so reset it to disabled to match the cleared selection.
                    wpdmppToggleDeleteBtn();
                }
            });
        });

    });
</script>
