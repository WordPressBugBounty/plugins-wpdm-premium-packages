<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WPDMPP\UI\Icons;
?>

<div class="w3eden admin-orders">

	<?php
	$menus = [
		[
			'link'   => "edit.php?post_type=wpdmpro&page=orders",
			"name"   => __( "All Orders", WPDMPP_TEXT_DOMAIN ),
			"active" => ! wpdm_query_var( 'task' )
		],
		[
			'link'   => "edit.php?post_type=wpdmpro&page=orders&task=show_renews",
			"name"   => __( "Renewed Orders", WPDMPP_TEXT_DOMAIN ),
			"active" => ( wpdm_query_var( 'task' ) === 'show_renews' )
		],
		[
			'link'   => "edit.php?post_type=wpdmpro&page=orders&task=createorder",
			"name"   => __( "Create New", WPDMPP_TEXT_DOMAIN ),
			"active" => ( wpdm_query_var( 'task' ) === 'createorder' )
		],
		[
			'link'   => "edit.php?post_type=wpdmpro&page=orders&task=acr_attempts",
			"name"   => __( "Order Recovery Attempts", WPDMPP_TEXT_DOMAIN ),
			"active" => ( wpdm_query_var( 'task' ) === 'acr_attempts' )
		],
	];

    if(wpdm_query_var('task') === 'vieworder')
        $menus[] = [
	        'link'   => "edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=" . wpdm_query_var( 'id', 'txt' ),
	        "name"   => __( "Order #" . wpdm_query_var( 'id', 'txt' ), "download-manager" ),
	        "active" => true
        ];


    if(wpdm_query_var('task') !== 'acr_attempts') {
        $actions = [
                [
                        "type"  => "button",
                        "id"    => "delete_selected",
                        "class" => "danger btn-sm",
                        "name"  =>  __( "Delete Selected", "wpdm-premium-packages" ),
                        "attrs" => [ "id" => "delete_selected", "style" => "display:none" ]
                ],
        ];
    } else $actions = [];

	$menu_content_pages = [
		''             => 'list-orders.php',
		'createorder'  => 'create-order.php',
		'acr_attempts' => 'acr-attempts.php',
		'vieworder'    => 'view-order.php',
		'show_renews'    => 'list-order-renews.php'
	];

	WPDM()->admin->pageHeader( esc_attr__( "Orders", "wpdm-premium-packages" ), 'cart-arrow-down color-purple', $menus, $actions );
	?>

    <div class="wpdm-admin-page-content">

        <div class="panel-body-np">

			<?php
			if ( isset( $msg ) && !empty( $msg )):
				echo "<div class='alert alert-info alert-floating'>$msg</div>";
			endif;

			if ( isset( $menu_content_pages[ wpdm_query_var( 'task' ) ] ) ) {
				include __DIR__ . '/' . sanitize_file_name($menu_content_pages[ wpdm_query_var( 'task' ) ]);
			} else {
				echo 'No content available!';
			}
			?>


        </div>
    </div>

    <script>
        jQuery(function ($) {

            $('#order-search').submit(function (e) {
                var params = $(this).serialize();

                e.preventDefault();
                WPDM.blockUI('#orders-form');
                $(this).ajaxSubmit({
                    success: function (res) {
                        $('#orders-form').html($(res).find('#orders-form').html());
                        $('#order-search').html($(res).find('#order-search').html());
                        window.history.pushState({
                            "html": res,
                            "pageTitle": "response.pageTitle"
                        }, "", "edit.php?" + params);
                        WPDM.unblockUI('#orders-form');
                        $('.ttip').tooltip();
                        // The swap replaced the .datep inputs with fresh DOM nodes, so
                        // re-bind the datepicker (the page-load init only applied to the
                        // originals — without this, From/To stop opening after a search).
                        $('.datep').datetimepicker({dateFormat: "yy-mm-dd", timeFormat: "HH:mm"});
                        // Rows re-render unchecked; the button sits outside #orders-form
                        // so reset it to disabled to match the cleared selection.
                        wpdmppToggleDeleteBtn();
                    }
                });
            });

            var wpdmppDeleteNonce = '<?php echo wp_create_nonce( 'wpdmpp_delete_orders' ); ?>';

            // In-page toast. WPDM.notify() renders the styled toast directly (unlike
            // WPDM.pushNotify(), which routes through the browser Notification API and
            // shows nothing without notification permission).
            function wpdmppDeleteToast(message, type) {
                type = type || 'success';
                var heading = (type === 'error')
                    ? '<?php echo esc_js( __( 'Error', 'wpdm-premium-packages' ) ); ?>'
                    : '<?php echo esc_js( __( 'Done', 'wpdm-premium-packages' ) ); ?>';
                WPDM.notify('<strong>' + heading + '</strong><br/>' + message, type, 'top-right');
            }

            $("#delete_selected").on('click', function () {
                var ids = $('.cboid:checked').map(function () { return this.value; }).get();
                if (ids.length === 0) {
                    WPDM.notify("<?php echo esc_js( __( 'Please select at least one order to delete.', 'wpdm-premium-packages' ) ); ?>", 'warning', 'top-right');
                    return false;
                }
                if (!confirm("<?php echo esc_js( __( 'Are you sure you want to delete selected orders?', 'wpdm-premium-packages' ) ); ?>")) {
                    return false;
                }
                WPDM.blockUI('#orders-form');
                $.post(ajaxurl, {
                    action: 'wpdmpp_delete_orders',
                    _wpnonce: wpdmppDeleteNonce,
                    ids: ids
                }, function (res) {
                    WPDM.unblockUI('#orders-form');
                    if (res && res.success) {
                        $.each(res.deleted || [], function (i, oid) {
                            $('.cboid[value="' + oid + '"]').closest('tr').fadeOut(250, function () { $(this).remove(); });
                        });
                        wpdmppDeleteToast(res.message, 'success');
                    } else {
                        wpdmppDeleteToast((res && res.message) ? res.message : "<?php echo esc_js( __( 'Could not delete the selected orders.', 'wpdm-premium-packages' ) ); ?>", 'error');
                    }
                }, 'json');
            });

            // "Delete Selected" stays hidden until at least one order is ticked.
            function wpdmppToggleDeleteBtn() {
                var checked = $('#orders-form .cboid:checked').length;
                $('#delete_selected').toggle(checked > 0);
            }

            // Select-all (header/footer) toggles every row checkbox, and the header
            // stays in sync when rows are ticked individually. Target th.column-cb
            // (only the header cells carry it) so this never matches the per-row
            // .cboid checkboxes, which sit in th.check-column[scope=row].
            // Delegated so it keeps working after the list refreshes via #order-search.
            $(document).on('change', '#orders-form th.column-cb input[type="checkbox"]', function () {
                var checked = this.checked;
                $('#orders-form .cboid').prop('checked', checked);
                $('#orders-form th.column-cb input[type="checkbox"]').prop('checked', checked);
                wpdmppToggleDeleteBtn();
            });
            $(document).on('change', '#orders-form .cboid', function () {
                var total = $('#orders-form .cboid').length;
                var checked = $('#orders-form .cboid:checked').length;
                $('#orders-form th.column-cb input[type="checkbox"]').prop('checked', total > 0 && total === checked);
                wpdmppToggleDeleteBtn();
            });

            $('span.wpdmpp-status-badge').tooltip({
                placement: 'bottom',
                padding: 10,
                template: '<div class="tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>'
            });
            $('.datep').datetimepicker({dateFormat: "yy-mm-dd", timeFormat: "HH:mm"});

            $('body').on('click', '.manual-renewal', function (e) {
                e.preventDefault();
                $this = $(this);
                $this.html(<?php echo wp_json_encode( Icons::spinner(16) ); ?>);
                $.get(ajaxurl, {
                    orderid: $(this).data('order'),
                    action: 'wpdmpp_toggle_manual_renew',
                    '__mrnonce': '<?php echo wp_create_nonce( NONCE_KEY ); ?>'
                }, function (res) {
                    console.log(res.mrenew);
                    if (res.mrenew !== undefined) {
                        $this.html(<?php echo wp_json_encode( Icons::get('circle-dot', 16) ); ?>);
                        if (res.mrenew === 0) {
                            $this.removeClass('color-green').addClass('text-muted');
                        } else {
                            $this.removeClass('text-muted').addClass('color-green');
                        }
                    }
                });
            });
            $('body').on('click', '.auto-renew-order', function (e) {
                e.preventDefault();
                var $link = $(this);
                if ($link.data('busy')) return false; // ignore clicks while a toggle is in flight
                if (!confirm('<?php echo esc_js( __( 'Are you sure?', WPDMPP_TEXT_DOMAIN ) ); ?>')) return false;

                // The clickable badge rendered by Icons::statusBadge().
                var $badge = $link.find('.wpdmpp-status-badge');
                var prevHtml = $badge.html();
                var prevClass = $badge.attr('class');

                // Progress indicator: swap the icon for a spinner.
                $link.data('busy', true);
                $badge.html(<?php echo wp_json_encode( Icons::spinner( 14 ) ); ?>);

                $.get(ajaxurl, {
                    orderid: $link.data('order'),
                    action: 'wpdmpp_toggle_auto_renew',
                    '__arnonce': '<?php echo wp_create_nonce( NONCE_KEY ); ?>'
                }, function (res) {
                    $link.data('busy', false);
                    if (res && res.renew !== undefined) {
                        // Live status change — mirror the server-rendered badge exactly.
                        if (parseInt(res.renew, 10) === 1) {
                            $badge.attr('class', 'wpdmpp-status-badge renew-active')
                                  .html(<?php echo wp_json_encode( Icons::get( 'check', 14 ) ); ?>);
                            WPDM.notify('<?php echo esc_js( __( 'Auto-renew enabled', WPDMPP_TEXT_DOMAIN ) ); ?>', 'success', 'top-right');
                        } else {
                            $badge.attr('class', 'wpdmpp-status-badge renew-cancelled')
                                  .html(<?php echo wp_json_encode( Icons::get( 'close', 14 ) ); ?>);
                            WPDM.notify('<?php echo esc_js( __( 'Auto-renew disabled', WPDMPP_TEXT_DOMAIN ) ); ?>', 'success', 'top-right');
                        }
                    } else {
                        $badge.attr('class', prevClass).html(prevHtml);
                        WPDM.notify((res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Could not update auto-renew status.', WPDMPP_TEXT_DOMAIN ) ); ?>', 'error', 'top-right');
                    }
                }, 'json').fail(function () {
                    $link.data('busy', false);
                    $badge.attr('class', prevClass).html(prevHtml);
                    WPDM.notify('<?php echo esc_js( __( 'Request failed. Please try again.', WPDMPP_TEXT_DOMAIN ) ); ?>', 'error', 'top-right');
                });
            });

            var __oid = [];
            $('#expire-orders').on('click', function (e) {
                e.preventDefault();
                $('#expire-orders').html(<?php echo wp_json_encode( Icons::spinner(14) ); ?>).attr('disabled', 'disabled');
                $('.cboid').each(function (i) {
                    __oid[i] = $(this).val();

                });
                $.post(ajaxurl, {
                    action: 'wpdmpp_expire_orders',
                    oids: __oid,
                    __oenonce: '<?php echo wp_create_nonce( NONCE_KEY ); ?>'
                }, function (res) {
                    $('#expire-orders').html(<?php echo wp_json_encode( Icons::get('check-double', 14) . ' Done!' ); ?>);
                });
            });

            $('.ttip').tooltip();
        });
    </script>

