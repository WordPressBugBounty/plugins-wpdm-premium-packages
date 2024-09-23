<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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


	$actions = [
		[
			"type"  => "button",
			"id"    => "delete_selected",
			"class" => "danger btn-sm",
			"name"  => '<i class="sinc far fa-arrow-alt-circle-down"></i> ' . __( "Delete Selected", "wpdm-premium-packages" ),
			"attrs" => [ "id" => "delete_selected" ]
		],
	];

	$menu_content_pages = [
		''             => 'list-orders.php',
		'createorder'  => 'create-order.php',
		'acr_attempts' => 'acr-attempts.php',
		'vieworder'    => 'view-order.php',
		'show_renews'    => 'list-order-renews.php'
	];

	WPDM()->admin->pageHeader( esc_attr__( "Orders", "wpdm-premium-packages" ), 'cart-arrow-down color-purple', $menus, $actions );
	?>

    <div class="wpdm-admin-page-content" id="wpdm-wrapper-panel">

        <div class="panel-body">

			<?php
			if ( isset( $msg ) ):
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
                    }
                });
            });

            $("#delete_selected").on('click', function () {
                if (confirm("<?php _e( 'Are you sure you want to delete selected orders?', 'wpdm-premium-packages' ); ?>")) {
                    $("#delete_confirm").val("1");
                    WPDM.blockUI('#orders-form');
                    $('#orders-form').ajaxSubmit({
                        success: function (res) {
                            $('#orders-form').html($(res).find('#orders-form').html());
                            $('#order-search').html($(res).find('#order-search').html());
                            WPDM.pushNotify("Done!", "Selected items are deleted successfully");
                            WPDM.unblockUI('#orders-form');
                        }
                    });
                } else {
                    return false;
                }
            });

            $(".delete-all-sts").on('click', function () {
                var status = $(this).data('status');
                if (confirm("<?php _e( 'Are you sure to delete all \'#_#\' orders?', 'wpdm-premium-packages' ); ?>".replace("#_#", status))) {
                    location.href = "edit.php?post_type=wpdmpro&page=orders&delete_all_by_payment_sts=" + status;
                } else {
                    return false;
                }
            });

            $('span.fa-stack').tooltip({
                placement: 'bottom',
                padding: 10,
                template: '<div class="tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>'
            });
            $('.datep').datetimepicker({dateFormat: "yy-mm-dd", timeFormat: "HH:mm"});

            $('body').on('click', '.manual-renewal', function (e) {
                e.preventDefault();
                $this = $(this);
                $this.find('.fa-solid').removeClass('fa-circle-dot').addClass('fa-sun fa-spin');
                $.get(ajaxurl, {
                    orderid: $(this).data('order'),
                    action: 'wpdmpp_toggle_manual_renew',
                    '__mrnonce': '<?php echo wp_create_nonce( NONCE_KEY ); ?>'
                }, function (res) {
                    console.log(res.mrenew);
                    if (res.mrenew !== undefined) {
                        $this.find('.fa-solid').removeClass('fa-sun fa-spin').addClass('fa-circle-dot');
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
                if(!confirm(('<?= __('Are you sure?', WPDMPP_TEXT_DOMAIN)?>'))) return false;
                $this = $(this);
                $(this).find('.fa').removeClass('fa-circle-check').removeClass('fa-circle-xmark').addClass('fa-sun fa-spin');
                $.get(ajaxurl, {
                    orderid: $(this).data('order'),
                    action: 'wpdmpp_toggle_auto_renew',
                    '__arnonce': '<?php echo wp_create_nonce( NONCE_KEY ); ?>'
                }, function (res) {
                    if (res.renew !== undefined) {
                        if (res.renew === 0) {
                            $this.find('.rns').removeClass('renew-active').addClass('renew-cancelled');
                            $this.find('.fa').removeClass('fa-sun fa-spin').addClass('fa-circle-xmark');
                        } else {
                            $this.find('.fa').removeClass('fa-sun fa-spin').addClass('fa-circle-check');
                            $this.find('.rns').removeClass('renew-cancelled').addClass('renew-active');
                        }
                    }
                });
            });

            var __oid = [];
            $('#expire-orders').on('click', function (e) {
                e.preventDefault();
                $('#expire-orders').html("<i class='fa fa-sun fa-spin'></i>").attr('disabled', 'disabled');
                $('.cboid').each(function (i) {
                    __oid[i] = $(this).val();

                });
                $.post(ajaxurl, {
                    action: 'wpdmpp_expire_orders',
                    oids: __oid,
                    __oenonce: '<?php echo wp_create_nonce( NONCE_KEY ); ?>'
                }, function (res) {
                    $('#expire-orders').html("<i class=\"fas fa-check-double\"></i> Done!");
                });
            });

            $('.ttip').tooltip();
        });
    </script>

