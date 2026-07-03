<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPDMPP\UI\Icons;

global $wpdb;

$order = $orderObj->getRawOrder( $order_id );

$billing_info = maybe_unserialize($order->billing_info);


if($order) {
$order->items = unserialize( $order->items );
$oitems       = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$wpdb->prefix}ahm_order_items WHERE oid = %s",
	$order->order_id
) );

$currency      = maybe_unserialize( $order->currency );
$currency_sign = is_array( $currency ) && isset( $currency['sign'] ) ? $currency['sign'] : '$';

if ( $order->uid > 0 ) {
	$user = new WP_User( $order->uid );
	//$role = is_object($user) ? [0] : '';
} else {
	$user = null;
}
$settings     = maybe_unserialize( get_option( '_wpdmpp_settings' ) );
$total_coupon = wpdmpp_get_all_coupon( unserialize( $order->cart_data ) );

$sbilling = array
(
	'first_name'  => '',
	'last_name'   => '',
	'company'     => '',
	'address_1'   => '',
	'address_2'   => '',
	'city'        => '',
	'postcode'    => '',
	'country'     => '',
	'state'       => '',
	'email'       => '',
	'order_email' => '',
	'phone'       => ''
);
$billing  = unserialize( $order->billing_info );
$billing  = shortcode_atts( $sbilling, $billing );


$renews = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$wpdb->prefix}ahm_order_renews WHERE order_id = %s",
	$order->order_id
) );

?>
<?php ob_start(); ?>
<table width="100%" cellspacing="0" class="table wpdmpp-od__items">
    <thead>
    <tr>
        <th align="left"><?php _e( "Item Name", "wpdm-premium-packages" ); ?></th>
        <th align="left"><?php _e( "Unit Price", "wpdm-premium-packages" ); ?></th>
        <th align="left"><?php _e( "Quantity", "wpdm-premium-packages" ); ?></th>
        <th align="left"><?php _e( "Role Discount", "wpdm-premium-packages" ); ?></th>
        <th align="right" class="text-right" style="width: 100px"><?php _e( "Total", "wpdm-premium-packages" ); ?></th>
    </tr>
    </thead>
	<?php
	//$cart_data = unserialize($order->cart_data);
	$cart_data = \WPDMPP\Order\OrderService::instance()->getOrderItemsAsArrays( $order->order_id );

	$currency      = maybe_unserialize( $order->currency );
	$currency_sign = is_array( $currency ) && isset( $currency['sign'] ) ? $currency['sign'] : '$';

	$payment_method              = str_replace( "WPDM_", "", $order->payment_method );

    $coupon_discount = 0;
    $role_discount           = 0;
    $shipping                = 0;
    $order_total             = 0;

	if ( is_array( $cart_data ) && ! empty( $cart_data ) ):
		foreach ( $cart_data as $pid => $item ):

			$currency_sign_before = wpdmpp_currency_sign_position() == 'before' ? $currency_sign : '';
			$currency_sign_after = wpdmpp_currency_sign_position() == 'after' ? $currency_sign : '';

			$license = isset( $item['license'] ) ? maybe_unserialize( $item['license'] ) : null;

			if ( $license ) {
				$license = isset( $license['info'], $license['info']['name'] ) ? '<span class="ttip color-purple" title="' . esc_html( $license['info']['description'] ) . '">' . sprintf( __( "%s License", "wpdm-premium-packages" ), $license['info']['name'] ) . '</span>' : '';
			}


			if ( ! isset( $item['coupon_amount'] ) || $item['coupon_amount'] == "" ) {
				$item['coupon_amount'] = 0.00;
			}

			if ( ! isset( $item['discount_amount'] ) || $item['discount_amount'] == "" ) {
				$item['discount_amount'] = 0.00;
			}

			if ( ! isset( $item['prices'] ) || $item['prices'] == "" ) {
				$item['prices'] = 0.00;
			}

			$title              = get_the_title( $item['pid'] );
			$title              = $title ? $title : '&mdash; The item is not available anymore &mdash;';
			$coupon_discount    += $item['coupon_discount'];
			$role_discount      += $item['role_discount'];
			$item_cost          = WPDMPP()->order->itemCost( $item );
			$order_total        += $item_cost; //(($item['price'] + $item['prices']) * (int)$item['quantity']) - $item['coupon_discount'] - $item['role_discount'];
			$item['extra_gigs'] = maybe_unserialize( $item['extra_gigs'] );


			$item['price'] = (double) $item['price'];
			//echo "<pre>";print_r($item['quantity']);

			?>
            <tr>
                <td>
                    <div style="display: flex;">
                        <div  style="margin-right: 8px"><a href="#" class="text-muted ttip pprmo_item" data-pid="<?php echo (int)$item['pid']; ?>" title="<?php esc_attr_e('Remove item', WPDMPP_TEXT_DOMAIN); ?>"><?php echo Icons::get('trash', 14); ?></a> </div>
                        <div>
                            <strong><?php WPDMPP()->cart->itemLink( $item ); ?></strong>
                            <div>
		                        <?php if ( (int) get_post_meta( $item['pid'], '__wpdm_enable_license_key', true ) === 1 ) { ?>
                                    <div style="margin-right: 5px;float: left">[ <a target="_blank" href="<?php echo admin_url("/edit.php?post_type=wpdmpro&page=pp-license&task=search_license&oid={$order->order_id}") ?>">View License Key</a> ]
                                    </div>
		                        <?php } ?>
		                        <?php WPDMPP()->cart->itemInfo( $item ); ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td><?php echo wpdmpp_price_format( $item['price'], $currency_sign, true ); ?></td>
                <td><?php echo (int) $item['quantity']; ?></td>
                <td><?php echo wpdmpp_price_format( $item['role_discount'], $currency_sign, true ); ?></td>
                <td class="text-right"><?php echo wpdmpp_price_format( $item_cost, $currency_sign, true ); ?></td>
            </tr>
		<?php

		endforeach;
	endif;
	?>
    <tr class="wpdmpp-od__sumrow">
        <td colspan="4" class="text-right"><?php _e( 'Cart Total', 'wpdm-premium-packages' ); ?></td>
        <td class="text-right"><?php echo wpdmpp_price_format( $order_total, $currency_sign, true ); ?></td>
    </tr>
    <tr class="wpdmpp-od__sumrow">
        <td colspan="4" class="text-right"><?php _e( 'Cart Coupon Discount', 'wpdm-premium-packages' );  ?> <div class="badge badge-success"><?php echo esc_html($order->coupon_code); ?></div></td>
        <td class="text-right">-<?php echo wpdmpp_price_format( $order->coupon_discount, $currency_sign, true ); ?></td>
    </tr>
    <tr class="wpdmpp-od__sumrow">
        <td colspan="4" class="text-right"><?php _e( 'Tax', 'wpdm-premium-packages' ); ?></td>
        <td class="text-right">+<?php echo wpdmpp_price_format( $order->tax, $currency_sign, true ); ?></td>
    </tr>
    <tr id="refundrow" class="wpdmpp-od__sumrow" <?php if ( (int) $order->refund == 0 ) {
		echo "style='display:none;'";
	} ?>>
        <td colspan="4" class="text-right"><?php _e( 'Refund', 'wpdm-premium-packages' ); ?></td>
        <td class="text-right" id="refundamount">-<?php echo wpdmpp_price_format( $order->refund, $currency_sign, true ); ?></td>
    </tr>
    <tr class="wpdmpp-od__grand">
        <td colspan="4" class="text-right"><?php _e( 'Total', 'wpdm-premium-packages' ); ?></td>
        <td class="text-right"><strong id="totalamount"
                                       class="order_total"><?php echo wpdmpp_price_format( $order->total, $currency_sign, true ); ?></strong>
        </td>
    </tr>
</table>
<?php $content = ob_get_clean(); ?>



        <div class="view-order wpdmpp-od-admin">
<?php
$wod_pm = str_ireplace( "wpdm_", "", $order->payment_method );
$wod_item_count = is_array( $oitems ) ? count( $oitems ) : 0;
$wod_cname = ( $order->uid > 0 && $user ) ? $user->display_name : trim( $billing['first_name'] . ' ' . $billing['last_name'] );
$wod_initials = '';
foreach ( preg_split( '/\s+/', trim( (string) $wod_cname ) ) as $wod_w ) { if ( $wod_w !== '' ) { $wod_initials .= strtoupper( $wod_w[0] ); } }
$wod_initials = substr( $wod_initials !== '' ? $wod_initials : 'NA', 0, 2 );
$wod_stcolor = function ( $s ) {
	$s = strtolower( (string) $s );
	if ( in_array( $s, [ 'completed', 'bonus', 'gifted' ], true ) ) return [ '#16a34a', '#e7f6ec' ];
	if ( $s === 'processing' ) return [ '#4f46e5', '#eceaff' ];
	if ( $s === 'pending' ) return [ '#d97706', '#fef3e2' ];
	if ( in_array( $s, [ 'cancelled', 'refunded', 'disputed', 'expired' ], true ) ) return [ '#dc2626', '#fdeaea' ];
	return [ '#6b7280', '#f1f2f6' ];
};
list( $wod_oc, $wod_ob ) = $wod_stcolor( $order->order_status );
list( $wod_pc, $wod_pb ) = $wod_stcolor( $order->payment_status );
?>
<style>
.wpdmpp-od-admin{
	--wod-ink:#16192c;--wod-mut:#7b8193;--wod-mut2:#9298a8;--wod-sec:#3b4154;
	--wod-line:#ececf2;--wod-line2:#f1f2f6;--wod-soft:#f4f5f8;
	--wod-primary:#4f46e5;--wod-primary-h:#4338ca;--wod-primary-l:#eef0ff;
	--wod-green:#16a34a;--wod-green-l:#e7f6ec;--wod-red:#e0413a;--wod-red-l:#fdf2f2;--wod-amber:#d97706;
	--wod-mono:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,monospace;
 	color:var(--wod-ink);-webkit-font-smoothing:antialiased;
}
.wpdmpp-od-admin *{box-sizing:border-box;}
.wpdmpp-od-admin a{text-decoration:none;}
.wpdmpp-od-admin .wod-card{background:#fff;border:1px solid var(--wod-line);border-radius:16px;box-shadow:0 1px 2px rgba(16,24,40,.04);overflow:hidden;}
.wpdmpp-od-admin .wod-card__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 22px;border-bottom:1px solid var(--wod-line2);font-size:15px;font-weight:700;color:var(--wod-ink);}
.wpdmpp-od-admin .wod-card__head .wod-count{font-size:12.5px;font-weight:600;color:var(--wod-mut2);}
.wpdmpp-od-admin .wod-card__head-l{display:flex;align-items:center;gap:9px;}
.wpdmpp-od-admin .wod-card__head-l svg{color:var(--wod-primary);}
.wpdmpp-od-admin .wod-card__body{padding:20px 22px;}
.wpdmpp-od-admin .wod-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin:0 0 22px;}
.wpdmpp-od-admin .wod-title{margin:0;font-size:26px;font-weight:800;letter-spacing:-.5px;line-height:1.15;color:var(--wod-ink);}
.wpdmpp-od-admin .wod-sub{margin:9px 0 0;font-size:13.5px;color:var(--wod-mut);font-weight:500;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.wpdmpp-od-admin .wod-sub__sep{color:#c3c7d4;}
.wpdmpp-od-admin .wod-head__actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.wpdmpp-od-admin .wod-pill{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:700;padding:5px 12px;border-radius:999px;text-transform:capitalize;}
.wpdmpp-od-admin .wod-pill__dot{width:7px;height:7px;border-radius:50%;background:currentColor;}
.wpdmpp-od-admin .wod-pill__k{font-weight:600;opacity:.7;}
.wpdmpp-od-admin .wod-summary{margin-bottom:22px;}
.wpdmpp-od-admin .wod-summary__grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;padding:22px;}
@media(max-width:980px){.wpdmpp-od-admin .wod-summary__grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;}}
.wpdmpp-od-admin .wod-field__label{font-size:12px;color:var(--wod-mut2);font-weight:600;margin-bottom:8px;}
.wpdmpp-od-admin .wod-field__val{font-size:15.5px;font-weight:700;display:flex;align-items:center;gap:8px;flex-wrap:wrap;line-height:1.3;}
.wpdmpp-od-admin .wod-mono{font-family:var(--wod-mono);}
.wpdmpp-od-admin .wod-tnid{flex:1 1 0;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.wpdmpp-od-admin .wod-iconbtn{display:inline-flex;align-items:center;justify-content:center;flex:none;width:26px;height:26px;padding:0;border:1px solid var(--wod-line);border-radius:8px;background:#fff;color:var(--wod-mut2);cursor:pointer;transition:border-color .15s ease,color .15s ease,background .15s ease;box-shadow:0 1px 2px rgba(16,24,40,.04);}
.wpdmpp-od-admin .wod-iconbtn:hover,.wpdmpp-od-admin .wod-iconbtn:focus{border-color:var(--wod-primary);color:var(--wod-primary);background:var(--wod-primary-l);outline:none;}
.wpdmpp-od-admin .wod-iconbtn svg{display:block;width:13px;height:13px;}
.wpdmpp-od-admin .wod-muted{color:var(--wod-mut2);font-weight:600;}
.wpdmpp-od-admin .wod-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:24px;align-items:start;}
@media(max-width:1080px){.wpdmpp-od-admin .wod-grid{grid-template-columns:minmax(0,1fr);}}
.wpdmpp-od-admin .wod-col{display:flex;flex-direction:column;gap:24px;min-width:0;}
.wpdmpp-od-admin .wod-card .wpdmpp-od__items{margin:0;width:100%;background:transparent;border-collapse:collapse;}
.wpdmpp-od-admin .wod-card .wpdmpp-od__items > thead > tr > th{background:#fafbfc;border:0;border-bottom:1px solid var(--wod-line2);padding:13px 22px;font-size:11.5px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:#9aa0b0;}
.wpdmpp-od-admin .wod-card .wpdmpp-od__items > tbody > tr > td{border:0;border-bottom:1px solid #f4f5f8;padding:15px 22px;font-size:13.5px;font-weight:500;color:var(--wod-sec);vertical-align:middle;}
.wpdmpp-od-admin .wod-card .wpdmpp-od__items .wpdmpp-od__sumrow td{background:#fbfbfd;font-weight:600;color:var(--wod-mut);}
.wpdmpp-od-admin .wod-card .wpdmpp-od__items .wpdmpp-od__grand td{font-weight:800;font-size:15px;color:var(--wod-ink);}
.wpdmpp-od-admin .wod-card .wpdmpp-od__items a{color:var(--wod-primary);font-weight:600;}
.wpdmpp-od-admin .wod-card .wpdmpp-od__items .pprmo_item{color:var(--wod-red);}
.wpdmpp-od-admin .wod-card__foot{display:flex;justify-content:flex-end;padding:15px 22px;border-top:1px solid var(--wod-line2);background:#fff;}
.wpdmpp-od-admin .wod-ctrl{margin-bottom:18px;}
.wpdmpp-od-admin .wod-ctrl:last-child{margin-bottom:0;}
.wpdmpp-od-admin .wod-ctrl > label{display:block;font-size:12px;font-weight:600;color:var(--wod-mut2);margin-bottom:8px;}
.wpdmpp-od-admin .wod-ctrl__row{display:flex;gap:10px;align-items:center;}
.wpdmpp-od-admin #msg{margin:16px 0 0;border-radius:11px;font-size:13px;}
.wpdmpp-od-admin .wod-cust__top{display:flex;align-items:center;gap:13px;margin-bottom:18px;}
.wpdmpp-od-admin .wod-avatar{flex:none;width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:15px;letter-spacing:.5px;}
.wpdmpp-od-admin .wod-cust__name{font-size:15px;font-weight:700;}
.wpdmpp-od-admin .wod-cust__meta{font-size:12.5px;color:var(--wod-mut2);font-weight:500;margin-top:2px;}
.wpdmpp-od-admin .wod-cust table{margin:0;background:transparent;width:100%;border-collapse:collapse;}
.wpdmpp-od-admin .wod-cust table td,.wpdmpp-od-admin .wod-cust table th{border:0;padding:5px 0;font-size:13.5px;vertical-align:top;}
.wpdmpp-od-admin .wod-cust table td:first-child{color:var(--wod-mut2);font-weight:500;width:62px;white-space:nowrap;}
.wpdmpp-od-admin .wod-cust table a{color:var(--wod-primary);font-weight:600;}
.wpdmpp-od-admin .wod-cust table th{color:var(--wod-mut);font-size:12px;font-weight:600;padding-top:10px;text-align:left;}
.wpdmpp-od-admin .wod-tiles{display:flex;flex-direction:column;gap:6px;margin-top:16px;}
.wpdmpp-od-admin .wod-tile{display:flex;align-items:flex-start;gap:12px;padding:6px 0;}
.wpdmpp-od-admin .wod-tile__ic{flex:none;width:34px;height:34px;border-radius:9px;background:var(--wod-soft);display:flex;align-items:center;justify-content:center;color:var(--wod-mut);}
.wpdmpp-od-admin .wod-tile__ic svg{width:16px;height:16px;}
.wpdmpp-od-admin .wod-tile__k{font-size:12px;color:var(--wod-mut2);font-weight:600;}
.wpdmpp-od-admin .wod-tile__v{font-size:13.5px;font-weight:600;margin-top:2px;line-height:1.5;}
.wpdmpp-od-admin .wod-drow{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-top:1px solid var(--wod-line2);}
.wpdmpp-od-admin .wod-drow:first-child{border-top:0;}
.wpdmpp-od-admin .wod-drow__k{font-size:13px;color:var(--wod-mut);font-weight:500;display:flex;align-items:center;gap:8px;}
.wpdmpp-od-admin .wod-drow__k svg{color:var(--wod-mut2);}
.wpdmpp-od-admin .wod-drow__v{font-size:13.5px;font-weight:600;text-align:right;display:flex;align-items:center;gap:8px;}
.wpdmpp-od-admin .wod-acts{display:inline-flex;align-items:center;gap:8px;font-size:14px;}
.wpdmpp-od-admin .auto-renew-order.wod-iconbtn .wpdmpp-status-badge{width:auto;height:auto;border:0;border-radius:0;line-height:0;}
.wpdmpp-od-admin .wpdmpp-od__section-title{font-size:15px;font-weight:700;color:var(--wod-ink);margin:0 0 14px;display:flex;align-items:center;gap:8px;}
.wpdmpp-od-admin #changepmmodal .wod-pmlist{display:flex;flex-direction:column;gap:8px;}
.wpdmpp-od-admin #changepmmodal .wod-pm-option{display:flex;align-items:center;gap:11px;padding:12px 14px;border:1px solid var(--wod-line);border-radius:11px;font-size:14px;font-weight:600;color:var(--wod-sec);cursor:pointer;transition:background .15s,border-color .15s;}
.wpdmpp-od-admin #changepmmodal .wod-pm-option:hover{background:var(--wod-primary-l);border-color:#dcdffb;color:var(--wod-primary);}
.wpdmpp-od-admin #changepmmodal .wod-pm-option svg{color:var(--wod-primary);flex:none;}
.w3eden .btn.btn-xs { line-height: 24px; }
</style>

            <span id="lng" class="color-red" style="margin-left: 20px;display: none"><?php echo Icons::spinner(16); ?> <?php _e( 'Please Wait...', 'wpdm-premium-packages' ); ?></span>

            <div class="wod-head">
                <div>
                    <h1 class="wod-title"><?php _e( "Order", "wpdm-premium-packages" ); ?> <span class="wod-mono">#<?php echo esc_html( $order->order_id ); ?></span></h1>
                    <div class="wod-sub">
                        <span><?php printf( __( "Placed on %s", "wpdm-premium-packages" ), wp_date( "M d, Y \\a\\t h:i a", $order->date ) ); ?></span>
                        <span class="wod-sub__sep">·</span>
                        <span><?php printf( _n( "%d item", "%d items", $wod_item_count, "wpdm-premium-packages" ), $wod_item_count ); ?></span>
                        <span class="wod-sub__sep">·</span>
                        <span class="wod-pill" style="color:<?php echo esc_attr( $wod_oc ); ?>;background:<?php echo esc_attr( $wod_ob ); ?>;"><span class="wod-pill__dot"></span><span class="wod-pill__k"><?php _e( "Order", "wpdm-premium-packages" ); ?></span> <?php echo esc_html( $order->order_status ); ?></span>
                        <span class="wod-pill" style="color:<?php echo esc_attr( $wod_pc ); ?>;background:<?php echo esc_attr( $wod_pb ); ?>;"><span class="wod-pill__dot"></span><span class="wod-pill__k"><?php _e( "Payment", "wpdm-premium-packages" ); ?></span> <?php echo esc_html( $order->payment_status ); ?></span>
                    </div>
                </div>
                <div class="wod-head__actions">
                    <div class="wpdm-order-actions">
                        <button class="wpdm-order-actions__trigger" type="button" id="orderActionsBtn" aria-haspopup="true" aria-expanded="false">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                            <span><?php _e('Actions', 'wpdm-premium-packages'); ?></span>
                            <svg class="wpdm-order-actions__chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="wpdm-order-actions__menu" id="orderActionsMenu">
                            <a href="#" class="wpdm-order-actions__item" id="dlh">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path></svg>
                                <?php _e('Download History', 'wpdm-premium-packages'); ?>
                            </a>
                            <a href="#" class="wpdm-order-actions__item" onclick="window.open('?id=<?php echo wpdm_query_var('id'); ?>&wpdminvoice=1','Invoice','height=720, width=750, toolbar=0'); return false;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                <?php _e('View Invoice', 'wpdm-premium-packages'); ?>
                            </a>
                            <a href="#" class="wpdm-order-actions__item" id="oceml">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                <?php _e('Resend Confirmation Email', 'wpdm-premium-packages'); ?>
                            </a>
                            <?php do_action("wpdmpp_order_action_menu_item", $order); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wod-card wod-summary">
                <div class="wod-card__head"><?php _e( "Summary", "wpdm-premium-packages" ); ?></div>
                <div class="wod-summary__grid">
                    <div>
                        <div class="wod-field__label"><?php _e( "Transaction ID", "wpdm-premium-packages" ); ?></div>
                        <div class="wod-field__val">
                            <span class="wod-mono wod-tnid" id="tnid" style="font-size:14px;"<?php echo $order->trans_id ? ' title="' . esc_attr( $order->trans_id ) . '"' : ''; ?>><?php echo $order->trans_id ? apply_filters( "wpdmpp_admin_order_details_trans_id", $order->trans_id, $wod_pm ) : '—'; ?></span>
                            <button type="button" class="wod-iconbtn ttip" data-toggle="modal" data-target="#changetrannid" title="<?php esc_attr_e( 'Change Transaction ID', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get('pencil', 12); ?></button>
                        </div>
                    </div>
                    <div>
                        <div class="wod-field__label"><?php _e( "Order Date", "wpdm-premium-packages" ); ?></div>
                        <div class="wod-field__val"><?php echo wp_date( "M d, Y", $order->date ); ?></div>
                    </div>
                    <div>
                        <div class="wod-field__label"><?php _e( "Payment Method", "wpdm-premium-packages" ); ?></div>
                        <div class="wod-field__val">
                            <span id="pmname"><?php echo esc_html( $wod_pm ); ?></span>
                            <button type="button" class="wod-iconbtn ttip" id="paymentMethodBtn" data-toggle="modal" data-target="#changepmmodal" title="<?php _e( "Change Payment Method", "wpdm-premium-packages" ); ?>"><?php echo Icons::get('credit-card', 12); ?></button>
                        </div>
                    </div>
                    <div>
                        <div class="wod-field__label"><?php _e( "Order Total", "wpdm-premium-packages" ); ?></div>
                        <div class="wod-field__val"><span class="order_total" style="font-size:18px;font-weight:800;"><?php echo wpdmpp_price_format( $order->total, $currency_sign, true ); ?></span></div>
                    </div>
                    <div>
                        <div class="wod-field__label">
                            <?php $order->auto_renew == 1 ? _e( "Auto-Renew Date", "wpdm-premium-packages" ) : _e( "Expiry Date", "wpdm-premium-packages" ); ?>
                        </div>
                        <div class="wod-field__val">
                            <span id="xdate" style="font-size:14px;"><?php echo wp_date( "M d, Y", $order->expire_date ); ?></span>
                            <span class="wod-acts">
                                <button type="button" class="wod-iconbtn ttip" data-toggle="modal" data-target="#changeexpire" title="<?php esc_attr_e( 'Change Expiry Date', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get('pencil', 12); ?></button>
                                <?php if(get_wpdmpp_option('disable_manual_renew', 0, 'int')) { ?>
                                    <a href="#" class="<?= (int)\WPDMPP\Order\OrderService::instance()->getMeta($order_id, 'manual_renew') ? 'color-green' : 'text-muted' ?> manual-renewal ttip" data-order="<?php echo $order->order_id; ?>" title="<?= __('Manual order renewal status', WPDMPP_TEXT_DOMAIN)?>"><?php echo Icons::get('circle-dot', 22); ?></a>
                                <?php } ?>
                            <a href="#" class="auto-renew-order wod-iconbtn ttip" data-order="<?php echo $order->order_id; ?>" title="<?= __('Activate/Deactivate auto-renewal', WPDMPP_TEXT_DOMAIN)?>"><?php echo Icons::statusBadge($order->auto_renew == 1 ? 'check' : 'close', 'renew-' . ($order->auto_renew == 0 ? 'cancelled' : 'active'), 14); ?></a>

                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" tabindex="-1" role="dialog" id="changetrannid">
                <div class="modal-dialog" role="document" style="width: 350px">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title"><?php echo __( "Change Transection ID", "wpdm-premium-packages" ); ?></h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <input type="text" placeholder="<?php echo esc_attr__( "New Transection ID", "wpdm-premium-packages" ); ?>" value="<?php echo esc_attr($order->trans_id); ?>" class="form-control input-lg" id="changetid"/>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="change_transection_id" class="btn btn-primary"><?php echo __( "Change", "wpdm-premium-packages" ); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change payment method modal -->
            <div class="modal fade" tabindex="-1" role="dialog" id="changepmmodal">
                <div class="modal-dialog" role="document" style="width: 380px">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title"><?php _e( "Change Payment Method", "wpdm-premium-packages" ); ?></h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="wod-pmlist">
                                <?php
                                $payment_methods = WPDMPP()->active_payment_gateways();
                                foreach ( $payment_methods as $payment_method ) {
                                    $payment_method_class = $payment_method;
                                    $payment_method_name  = str_replace( "WPDM_", "", $payment_method );
                                    ?>
                                    <a href="#" class="wod-pm-option changepm" data-pm="<?php echo esc_attr($payment_method_class); ?>"><?php echo Icons::get('credit-card', 16); ?> <span><?php echo esc_html($payment_method_name); ?></span></a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

	        <?php do_action("wpdmpp_order_details_before_order_info", $order); ?>

            <div class="wod-grid">

                <div class="wod-col">
                    <?php do_action("wpdmpp_order_details_before_order_items", $order); ?>

                    <div class="wod-card">
                        <div class="wod-card__head">
                            <span class="wod-card__head-l"><?php echo Icons::get('shopping-bag', 17); ?> <?php _e( "Ordered Items", "wpdm-premium-packages" ); ?></span>
                            <button type="button" class="btn btn-info btn-xs" data-toggle="modal" data-target="#addproduct"><?php echo Icons::get('plus', 12); ?> <?php _e('Add Item', WPDMPP_TEXT_DOMAIN); ?></button>
                        </div>
                        <div style="overflow-x:auto;">
							<?php echo $content; ?>
                        </div>
                        <div class="wod-card__foot">
                            <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#refundmodal"><?php _e( "Refund", "wpdm-premium-packages" ); ?></button>
                        </div>
                    </div>

					<?php
					do_action("wpdmpp_order_details_after_order_items", $order);
					include( dirname( __FILE__ ) . '/renew-invoices.php' );
					?>

                    <div class="wod-card">
                        <div class="wod-card__body">
							<?php
							echo "<div class='wpdmpp-od__section-title'>" . Icons::get('edit', 16) . " " . __( "Order Notes", "wpdm-premium-packages" ) . "</div>";
							include( dirname( __FILE__ ) . '/order-notes.php' );
							?>
                        </div>
                    </div>
                </div>

                <div class="wod-col">

                    <div class="wod-card wod-cust">
                        <div class="wod-card__head"><span class="wod-card__head-l"><?php echo Icons::get('user-circle', 17); ?> <?php _e( "Customer", "wpdm-premium-packages" ); ?></span>
                            <button type="button" class="btn btn-xs btn-warning pull-right" data-toggle="modal" data-target="#changecustomer"><?php _e( 'Change', 'wpdm-premium-packages' ) ?></button>
                        </div>
                        <div class="wod-card__body">
                            <div class="wod-cust__top">
                                <div class="wod-avatar"><?php echo esc_html( $wod_initials ); ?></div>
                                <div>
                                    <div class="wod-cust__name"><?php echo esc_html( $wod_cname !== '' ? $wod_cname : __( 'Guest', 'wpdm-premium-packages' ) ); ?></div>
                                    <div class="wod-cust__meta"><?php echo $order->uid > 0 ? esc_html__( 'Registered customer', 'wpdm-premium-packages' ) : esc_html__( 'Guest order', 'wpdm-premium-packages' ); ?></div>
                                </div>
                            </div>
							<?php if ( $order->uid > 0 ) { ?>
                            <table id="cintable">
                                <tbody>
                                <tr>
                                    <td><?php _e( "Name", "wpdm-premium-packages" ); ?></td>
                                    <td>
                                        <a href='edit.php?post_type=wpdmpro&page=customers&view=profile&id=<?php echo (int) $user->ID; ?>'><?php echo esc_html( $user->display_name ); ?></a>
                                        <a class="text-filter" title="<?php _e('All orders placed by this customer','wpdm-premium-packages'); ?>" href="edit.php?post_type=wpdmpro&page=orders&customer=<?php echo (int) $user->ID; ?>&focus=<?php echo esc_attr( $order->order_id ); ?>"><?php echo Icons::get('search', 14); ?></a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php _e( "Email", "wpdm-premium-packages" ); ?></td>
                                    <td>
                                        <a href='mailto:<?php echo esc_attr( $user->user_email ); ?>'><?php echo esc_html( $user->user_email ); ?></a>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                            <div class="modal fade" tabindex="-1" role="dialog" id="changecustomer">
                                <div class="modal-dialog" role="document" style="width: 350px">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title"><?php echo __( "Change Customer", "wpdm-premium-packages" ); ?></h4>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="text" placeholder="<?php echo __( "Username or Email", "wpdm-premium-packages" ); ?>" class="form-control input-lg" id="changec"/>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" id="save_customer_change" class="btn btn-primary"><?php echo __( "Change", "wpdm-premium-packages" ); ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
							<?php } else { ?>
                            <table>
                                <tbody>
                                <tr>
                                    <td><?php _e( "Name", "wpdm-premium-packages" ); ?></td>
                                    <td><?php echo esc_html( $billing['first_name'] . ' ' . $billing['last_name'] ); ?></td>
                                </tr>
                                <tr>
                                    <td><?php _e( "Email", "wpdm-premium-packages" ); ?></td>
                                    <td><a href="mailto:<?php echo esc_attr( $billing['order_email'] ); ?>"><?php echo esc_html( $billing['order_email'] ); ?></a></td>
                                </tr>
                                </tbody>
                            </table>
                            <table>
                                <thead><tr><th><?php echo __( "This order is not associated with any registered user", "wpdm-premium-packages" ); ?></th></tr></thead>
                                <tr>
                                    <td id="ausre">
                                        <div class="input-group"><input placeholder="Username or Email" type="text" class="form-control" id="ausr"><div class="input-group-btn"><input type="button" id="ausra" class="btn btn-primary" value="<?php echo __( "Assign User", "wpdm-premium-packages" ); ?>"></div></div>
                                    </td>
                                </tr>
                            </table>
							<?php } ?>
							<?php if ( ! empty( $billing['company'] ) || ! empty( $billing['city'] ) || ! empty( $billing['country'] ) ) { ?>
                            <div class="wod-tiles">
                                <div class="wod-tile">
                                    <span class="wod-tile__ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"></rect><path d="M3 10h18"></path></svg></span>
                                    <div>
                                        <div class="wod-tile__k"><?php _e( "Billing Details", "wpdm-premium-packages" ); ?></div>
                                        <div class="wod-tile__v"><?php
                                            $wod_bits = array_filter( [ $billing['company'], trim( $billing['city'] . ' ' . $billing['state'] ), $billing['country'] ] );
                                            echo esc_html( implode( ' · ', $wod_bits ) );
                                        ?></div>
                                    </div>
                                </div>
                            </div>
							<?php } ?>
                        </div>
                    </div>

                    <div class="wod-card">
                        <div class="wod-card__head"><span class="wod-card__head-l"><?php echo Icons::get('settings', 17); ?> <?php _e( "Order Management", "wpdm-premium-packages" ); ?></span></div>
                        <div class="wod-card__body" id="orderbar">
                            <div class="wod-ctrl">
                                <label id="oslabel"><?php _e( "Order Status", "wpdm-premium-packages" ); ?></label>
                                <div class="wod-ctrl__row">
                                    <select id="osv" name="order_status" title="<?php _e( "Select Order Status", "wpdm-premium-packages" ); ?>" class="form-control wpdm-custom-select ttip">
                                        <option value="Pending" disabled><?php _e( "Order Status", "wpdm-premium-packages" ); ?></option>
                                        <option <?php selected( $order->order_status, 'Pending' ); ?> value="Pending">Pending</option>
                                        <option <?php selected( $order->order_status, 'Processing' ); ?> value="Processing">Processing</option>
                                        <option <?php selected( $order->order_status, 'Completed' ); ?> value="Completed">Completed</option>
                                        <option <?php selected( $order->order_status, 'Expired' ); ?> value="Expired">Expired</option>
                                        <option <?php selected( $order->order_status, 'Cancelled' ); ?> value="Cancelled">Cancelled</option>
                                        <option value="Renew" class="text-success text-renew">Renew Order</option>
                                    </select>
                                    <input type="button" id="update_os" class="btn btn-primary" value="<?php esc_attr_e( 'Update', 'wpdm-premium-packages' ); ?>">
                                </div>
                            </div>
                            <div class="wod-ctrl">
                                <label id="pslabel"><?php _e( "Payment Status", "wpdm-premium-packages" ); ?></label>
                                <div class="wod-ctrl__row">
                                    <select id="psv" title="<?php _e( "Select Payment Status", "wpdm-premium-packages" ); ?>" class="wpdm-custom-select form-control ttip" name="payment_status">
                                        <option value="Pending" disabled><?php _e( "Payment Status", "wpdm-premium-packages" ); ?></option>
                                        <option <?php selected( $order->payment_status, 'Pending' ); ?> value="Pending">Pending</option>
                                        <option <?php selected( $order->payment_status, 'Processing' ); ?> value="Processing">Processing</option>
                                        <option <?php selected( $order->payment_status, 'Completed' ); ?> value="Completed">Completed</option>
                                        <option <?php selected( $order->payment_status, 'Bonus' ); ?> value="Bonus">Bonus</option>
                                        <option <?php selected( $order->payment_status, 'Gifted' ); ?> value="Gifted">Gifted</option>
                                        <option <?php selected( $order->payment_status, 'Cancelled' ); ?> value="Cancelled">Cancelled</option>
                                        <option <?php selected( $order->payment_status, 'Disputed' ); ?> value="Disputed">Disputed</option>
                                        <option <?php selected( $order->payment_status, 'Refunded' ); ?> value="Refunded">Refunded</option>
                                    </select>
                                    <input id="update_ps" type="button" class="btn btn-primary" value="<?php esc_attr_e( 'Update', 'wpdm-premium-packages' ); ?>">
                                </div>
                            </div>
                            <div id="msg" style="display:none;" class="alert alert-success"><?php _e( "Message", "wpdm-premium-packages" ); ?></div>
                        </div>
                    </div>

                    <div class="wod-card">
                        <div class="wod-card__head"><span class="wod-card__head-l"><?php echo Icons::get('server', 17); ?> <?php _e( "Order Info", "wpdm-premium-packages" ); ?></span></div>
                        <div class="wod-card__body">
                            <div class="wod-drow">
                                <span class="wod-drow__k"><?php echo Icons::get('server', 14); ?> <?php _e( "IP Address", "wpdm-premium-packages" ); ?></span>
                                <span class="wod-drow__v wod-mono"><?php echo esc_html( $order->IP ); ?></span>
                            </div>
                            <div class="wod-drow">
                                <span class="wod-drow__k"><?php echo Icons::get('search', 14); ?> <?php _e( "Location", "wpdm-premium-packages" ); ?></span>
                                <span class="wod-drow__v"><span id="iploc"><script>
                                    jQuery(function ($) {
                                        $.getJSON("https://ipapi.co/<?php echo esc_js( $order->IP ); ?>/json/", function (data) {
                                            var table_body = "";
                                            if (data.error !== true && data.reserved !== true) {
                                                table_body += data.city + ", ";
                                                table_body += data.region + ", ";
                                                table_body += data.country;
                                                $("#iploc").html(table_body);
                                            } else {
                                                $("#iploc").html('Private');
                                            }
                                        });
                                    });
                                </script></span></span>
                            </div>
                            <div class="wod-drow">
                                <span class="wod-drow__k"><?php echo Icons::get('tag', 14); ?> <?php _e( "Coupon Discount", "wpdm-premium-packages" ); ?></span>
                                <span class="wod-drow__v"><?php echo wpdmpp_price_format( $total_coupon + $order->cart_discount, true, true ); ?></span>
                            </div>
                            <div class="wod-drow">
                                <span class="wod-drow__k"><?php echo Icons::get('circle-dot', 14); ?> <?php _e( "Role Discount", "wpdm-premium-packages" ); ?></span>
                                <span class="wod-drow__v"><?php echo wpdmpp_price_format( $role_discount, true, true ); ?></span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <?php do_action("wpdmpp_order_details_after_order_notes", $order); ?>
        </div>


    <!-- refund -->
    <div class="modal fade" id="refundmodal" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <form method="post" id="refundform">
                    <input type="hidden" name="wpdmpparnonnce" value="<?php echo wp_create_nonce(WPDM_PRI_NONCE) ?>"/>
                    <input type="hidden" name="action" value="wpdmpp_add_refund"/>
                    <input type="hidden" name="order_id" value="<?php echo wpdm_query_var( 'id' ); ?>"/>
                    <div class="modal-header" style="display: flex; justify-content: space-between">
                        <strong><?php _e( "Refund", "wpdm-premium-packages" ); ?></strong>
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"><?php echo Icons::get('close', 14); ?></button>
                    </div>
                    <div class="modal-header text-center" style="background: #fafafa">
                        <h4 style="padding: 0;margin: 0;"><?php _e( "Order Total", "wpdm-premium-packages" ); ?>: <span
                                    class="order_total"><?php echo wpdmpp_price_format( $order->total ); ?></span></h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <strong style="margin-bottom: 10px;display: block"><?php _e( "Refund Amount", "wpdm-premium-packages" ); ?>
                                :</strong>
                            <input type="text" class="form-control input-lg" name="refund"/>
                        </div>
                        <div class="form-group">
                            <strong style="margin-bottom: 10px;display: block"><?php _e( "Reason For Refund", "wpdm-premium-packages" ); ?>
                                :</strong>
                            <textarea type="text" class="form-control" name="reason"></textarea>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <button type="submit"
                                class="btn btn-block btn-primary btn-lg"><?php _e( "Apply Refund", "wpdm-premium-packages" ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- add product -->
    <div class="modal fade" id="addproduct" tabindex="-1" role="dialog" aria-labelledby="addproductLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="addproductLabel"><?php _e('Select Product','wpdm-premium-packages'); ?></h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="text" placeholder="<?php _e('Search Product...','wpdm-premium-packages'); ?>" class="form-control input-lg" id="srcp">
                    <br/>
                    <div class="list-group" id="productlist"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- expire -->
    <div class="modal fade" id="changeexpire" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <form method="post" id="changeexpireform">
                    <input type="hidden" name="wpdmppuednonnce" value="<?php echo wp_create_nonce(WPDM_PRI_NONCE);  ?>">
                    <input type="hidden" name="action" value="wpdmpp_updateOrderExpiryDate">
                    <input type="hidden" name="order_id" value="<?php echo wpdm_query_var( 'id' ); ?>"/>
                    <div class="modal-header" style="display: flex; justify-content: space-between">
                        <h4><?php _e( "Change Expire Date", "wpdm-premium-packages" ); ?></h4>
                        <button class="close" type="button" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <strong style="margin-bottom: 10px;display: block"><?php _e( "Select Date", "wpdm-premium-packages" ); ?>:</strong>
                            <input type="text" class="form-control input-lg datetime" value="<?php echo wp_date( "M d, Y h:i a", $order->expire_date ); ?>" id="expiredate_field" name="expiredate"/>
                        </div>
                        <div class="form-group">
                            <label style="margin-bottom: 10px;display: block"><input id="dorenew" type="checkbox" name="renew" value="1" /> <?php _e( "Renew Order", "wpdm-premium-packages" ); ?></label>
                            <input type="text" class="form-control input-lg datetime" value="<?php echo wp_date( "M d, Y h:i a", $order->expire_date ); ?>" name="renewdate"/>
                        </div>

                    </div>
                    <div class="panel-footer">
                        <button type="submit"
                                class="btn btn-block btn-primary btn-lg"><?php _e( "Apply Changes", "wpdm-premium-packages" ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>





<script>

    jQuery(function ($) {
        var _reload = 0, $body = $('body');

		<?php
		$style = array(
			'Pending'    => 'btn-warning',
			'Expired'    => 'btn-danger',
			'Processing' => 'btn-info',
			'Completed'  => 'btn-success',
			'Bonus'      => 'btn-success',
			'Gifted'     => 'btn-success',
			'Cancelled'  => 'btn-danger',
			'Disputed'   => 'btn-danger',
			'Refunded'   => 'btn-danger'
		);
		$oid = sanitize_text_field( $_GET['id'] );
		?>
        //$('select#osv').selectpicker({style: '<?php echo isset( $style[ $order->order_status ] ) ? $style[ $order->order_status ] : 'btn-default'; ?>'});
        //$('select#psv').selectpicker({style: '<?php echo $style[ $order->payment_status ]; ?>'});

        $('#refundform').on('submit', function (e) {
            e.preventDefault();
            WPDM.blockUI('#refundform');
            $(this).ajaxSubmit({
                url: ajaxurl,
                success: function (response) {
                    $('#refundrow').show();
                    $('#refundamount').html(response.amount)
                    $('.order_total').html(response.total)
                    WPDM.notify('<?php echo ( Icons::get('check-double', 14) ); ?> ' + response.msg, 'success', 'top-center', 7000);
                    WPDM.unblockUI('#refundform');
                    $('#refundform').trigger('reset');
                    $('#refundmodal').modal('hide');
                }
            });
        });


        $('#changeexpireform').on('submit', function (e) {
            e.preventDefault();
            WPDM.blockUI('#changeexpireform');
            $(this).ajaxSubmit({
                url: ajaxurl,
                success: function (response) {
                    $('#xdate').html(response.date)
                    WPDM.notify('<?php echo ( Icons::get('check-double', 14) ); ?>' + response.msg, 'success', 'top-center', 7000);
                    WPDM.unblockUI('#changeexpireform');
                    $('#changeexpire').modal('hide');
                }
            });
        });

        $('#dorenew').on('change', function () {
            if($(this).is(':checked'))
                $('#expiredate_field').attr('disabled', 'disabled');
            else
                $('#expiredate_field').removeAttr('disabled');
        });

        $('#update_os').click(function () {
            WPDM.blockUI('#orderbar');
            $.post(ajaxurl, {
                action: 'wpdmpp_update_order_status',
                wpdmppasyncrequest: '<?php echo wp_create_nonce(WPDM_PRI_NONCE); ?>',
                order_id: '<?php echo $oid; ?>',
                status: $('#osv').val()
            }, function (res) {
                WPDM.notify(<?php echo wp_json_encode( Icons::get('check-double', 14) ); ?> + res, 'success', 'top-center', 7000);
                WPDM.unblockUI('#orderbar');

            });
        });


        $('#oceml').click(function (e) {
            e.preventDefault();
            WPDM.confirm('<?= __('Order Confirmation Email', WPDM_TEXT_DOMAIN); ?>', '<?= __('Resending order confirmation email...', WPDM_TEXT_DOMAIN); ?>', [
                {
                    label: 'Yes, Confirm!',
                    class: 'btn btn-success',
                    callback: function () {
                        let $mod = $(this);
                        $mod.find('.modal-body').html(<?php echo wp_json_encode( Icons::spinner(14) . ' Processing...' ); ?>);

                        $.post(ajaxurl, {
                            action: 'wpdmpp_order_confirmation_email',
                            ocemnonce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>',
                            order_id: '<?php echo $oid; ?>',
                        }, function (res) {
                            $('#pmname').html(res.pmname);
                            if(res.success === false)
                                WPDM.notify('<?php echo ( Icons::get('times-circle', 14) . ' ' ); ?> ' + res.message, 'danger', 'top-center', 4000);
                            else
                                WPDM.notify('<?php echo ( Icons::get('check-double', 14) ); ?> ' + res.msg, 'success', 'top-center', 4000);
                            $mod.modal('hide');
                        });
                    }
                },
                {
                    label: 'No, Later',
                    class: 'btn btn-info',
                    callback: function () {
                        $(this).modal('hide');
                    }
                }
            ]);


        });


         $('.changepm').click(function (e) {
            e.preventDefault();
            WPDM.blockUI('#orderbar');
            // Add loading state to trigger button
            var $trigger = $('#paymentMethodBtn');
            $trigger.addClass('loading').prop('disabled', true);
            $trigger.find('svg').css('animation', 'wpdm-spin 1s linear infinite');
            $.post(ajaxurl, {
                action: 'wpdmpp_update_payment_method',
                wpdmppasyncrequest: '<?php echo wp_create_nonce(WPDM_PRI_NONCE); ?>',
                order_id: '<?php echo $oid; ?>',
                pm: $(this).data('pm')
            }, function (res) {
                $('#pmname').html(res.pmname);
                $('#changepmmodal').modal('hide');
                WPDM.notify('<?php echo ( Icons::get('check-double', 14) ); ?> ' + res.msg, 'success', 'top-center', 4000);
                WPDM.unblockUI('#orderbar');
                // Remove loading state
                $trigger.removeClass('loading').prop('disabled', false);
                $trigger.find('svg').css('animation', '');
            });
        });


        $('#update_ps').click(function () {
            WPDM.blockUI('#orderbar');
            $.post(ajaxurl, {
                action: 'wpdmpp_update_payment_status',
                wpdmppasyncrequest: '<?php echo wp_create_nonce(WPDM_PRI_NONCE); ?>',
                order_id: '<?php echo $oid; ?>',
                status: $('#psv').val()
            }, function (res) {
                WPDM.notify(<?php echo wp_json_encode( Icons::get('check-double', 14) ); ?> + res, 'success', 'top-center', 4000);
                WPDM.unblockUI('#orderbar');
            });
        });

        var _assignUserStrings = {
            name:        <?php echo wp_json_encode( __( 'Customer Name:', 'wpdm-premium-packages' ) ); ?>,
            email:       <?php echo wp_json_encode( __( 'Customer Email:', 'wpdm-premium-packages' ) ); ?>,
            change:      <?php echo wp_json_encode( __( 'Change', 'wpdm-premium-packages' ) ); ?>,
            allOrders:   <?php echo wp_json_encode( __( 'All orders placed by this customer', 'wpdm-premium-packages' ) ); ?>,
            requestFail: <?php echo wp_json_encode( __( 'Request failed:', 'wpdm-premium-packages' ) ); ?>,
            genericFail: <?php echo wp_json_encode( __( 'Failed to update customer.', 'wpdm-premium-packages' ) ); ?>
        };
        // REST endpoint: POST /wp-json/wpdmpp/v1/admin/orders/{id}/assign-user
        // Scheme-relative path so the call inherits the page's protocol (avoids HTTPS/HTTP mismatch).
        var _assignUserUrl   = <?php echo wp_json_encode( wp_make_link_relative( rest_url( 'wpdmpp/v1/admin/orders/' . rawurlencode( $oid ) . '/assign-user' ) ) ); ?>;
        var _assignUserNonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

        function _assignUserError(xhr, fallback) {
            var json = xhr && xhr.responseJSON;
            if (json && json.message) return json.message;
            return fallback + ' ' + (xhr && (xhr.statusText || xhr.status) || '');
        }

        $('#save_customer_change').on('click', function () {
            WPDM.blockUI('#changecustomer .modal-content');
            $.ajax({
                url: _assignUserUrl,
                method: 'POST',
                dataType: 'json',
                headers: { 'X-WP-Nonce': _assignUserNonce },
                data: { assignuser: $('#changec').val() }
            }).done(function (res) {
                if (!res || !res.success) {
                    var msg = (res && res.message) ? res.message : _assignUserStrings.genericFail;
                    WPDM.notify(<?php echo wp_json_encode( Icons::get('times-circle', 14) . ' ' ); ?> + msg, 'danger', 'top-center', 5000);
                    return;
                }
                var u = res.data.user;
                var $tbody = $('<tbody/>');
                $tbody.append(
                    $('<tr/>').append(
                        $('<td/>').text(_assignUserStrings.name),
                        $('<td/>').append(
                            $('<a/>', { href: u.profile_url, text: u.display_name || u.login }),
                            ' ',
                            $('<a/>', { href: u.orders_url, 'class': 'text-filter', title: _assignUserStrings.allOrders }).html(u.search_icon || ''),
                            $('<br/>')
                        )
                    ),
                    $('<tr/>').append(
                        $('<td/>').text(_assignUserStrings.email),
                        $('<td/>').append(
                            $('<button/>', {
                                type: 'button',
                                'class': 'btn btn-xs btn-warning pull-right',
                                'data-toggle': 'modal',
                                'data-target': '#changecustomer',
                                text: _assignUserStrings.change
                            }),
                            $('<a/>', { href: 'mailto:' + u.email, text: u.email })
                        )
                    )
                );
                $('#cintable').empty().append($tbody);
                WPDM.notify(<?php echo wp_json_encode( Icons::get('check-double', 14) . ' ' ); ?> + res.message, 'success', 'top-center', 5000);
                $('#changecustomer').modal('hide');
            }).fail(function (xhr) {
                WPDM.notify(<?php echo wp_json_encode( Icons::get('times-circle', 14) . ' ' ); ?> + _assignUserError(xhr, _assignUserStrings.requestFail), 'danger', 'top-center', 5000);
            }).always(function () {
                WPDM.unblockUI('#changecustomer .modal-content');
            });
        });

        $('#change_transection_id').on('click', function () {
            WPDM.blockUI('#changetrannid .modal-content');
            $.post(ajaxurl, {
                action: 'wpdmpp_change_transection_id',
                order_id: '<?php echo $oid; ?>',
                trans_id: $('#changetid').val(),
                ctinonce: '<?php echo wp_create_nonce( WPDM_PRI_NONCE );?>'
            }, function (res) {
                $('#tnid').html("( " + $('#changetid').val() + " )");
                WPDM.unblockUI('#changetrannid .modal-content');
                alert(res);
                $('#changetrannid').modal('hide');
            }).fail(function (res) {
                alert('<?= esc_attr__( 'Action Failed!', WPDMPP_TEXT_DOMAIN ) ?>');
                WPDM.unblockUI('#changetrannid .modal-content');
            });
        });

        var ruf = $('#ausre').html();
        $body.on('click', '#ausre .alert', function () {
            $('#ausre').html(ruf);
        });
        $body.on('click', '#ausra', function () {
            var ausr = $('#ausr').val();
            $('#ausre').html(<?php echo wp_json_encode( "<div class='alert alert-primary' style='padding:7px 15px;border-radius:2px;margin:0'>" . Icons::spinner(14) . ' ' . __( 'Please Wait...', 'wpdm-premium-packages' ) . '</div>' ); ?>);
            $.ajax({
                url: _assignUserUrl,
                method: 'POST',
                dataType: 'json',
                headers: { 'X-WP-Nonce': _assignUserNonce },
                data: { assignuser: ausr }
            }).done(function (res) {
                if (res && res.success) {
                    var $ok = $('<div class="alert alert-success" style="padding:7px 15px;border-radius:2px;margin:0"/>').text(res.message);
                    $('#ausre').empty().append($ok);
                } else {
                    var msg = (res && res.message) ? res.message : _assignUserStrings.genericFail;
                    var $err = $('<div class="alert alert-danger" style="padding:7px 15px;background:rgba(255,0,23,0.05);border-radius:2px;margin:0"/>').text(msg);
                    $('#ausre').empty().append($err);
                }
            }).fail(function (xhr) {
                var $err = $('<div class="alert alert-danger" style="padding:7px 15px;background:rgba(255,0,23,0.05);border-radius:2px;margin:0"/>')
                    .text(_assignUserError(xhr, _assignUserStrings.requestFail));
                $('#ausre').empty().append($err);
            });
        });


        $('#dlh').on('click', function () {
            __bootModal("Download History", <?php echo wp_json_encode( "<div id='dlhh'>" . Icons::spinner(14) . ' Loading...</div>' ); ?>, 400);
            $('#dlhh').load(ajaxurl, {
                action: 'wpdmpp_download_hostory',
                oid: '<?php echo wpdm_query_var( 'id', 'txt' ); ?>',
                __dlhnonce: '<?php echo wp_create_nonce( NONCE_KEY ); ?>'
            });
        });



        // Use scheme-relative URL so the request always matches the current page origin
        // (avoids http→https mixed-content/CORS when force_ssl_admin or rest_url scheme differs).
        var _wpdmppSearchUrl = <?php echo wp_json_encode( wp_make_link_relative( wpdm_rest_url( 'search' ) ) ); ?>;
        // Pre-rendered SVG icon string. Stored in a JS var rather than embedded inline
        // so the SVG's double-quoted attributes don't terminate the surrounding JS string literal.
        var _wpdmppPlusIcon  = <?php echo wp_json_encode( Icons::get( 'plus-circle', 16, 'color-green' ) ); ?>;
        var _wpdmppSearchTimer = null;
        var _wpdmppSearchXhr   = null;

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

            // Cancel any in-flight search so results stay in order
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
        $body.on('input keyup', '#srcp', function () {
            console.log('Searching...');
            clearTimeout(_wpdmppSearchTimer);
            _wpdmppSearchTimer = setTimeout(search_product, 200);
        });

        $body.on('click', '.insert-pid', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            $(this).html(<?php echo wp_json_encode( Icons::spinner(16) ); ?>);

            //wpdmpp_admin_cart.push($(this).data('pid')."|".$(this).data('license'));

            //window.localStorage.setItem("wpdmpp_admin_cart", JSON.stringify(wpdmpp_admin_cart));

            var $this = $(this);
            $.get(ajaxurl, {order: '<?php echo esc_js($order->order_id); ?>',product: $(this).data('pid'), license: $(this).data('license'), action: 'wpdmpp_edit_order', task: 'add_product', __eononce: '<?php echo esc_js(wp_create_nonce(WPDM_PRI_NONCE)); ?>'}, function (res) {
                $this.html(<?php echo wp_json_encode( Icons::get('check-circle', 16, 'color-green') ); ?>);
                _reload = 1;
            });


        });

        $('#addproduct').on('hidden.bs.modal', function (e) {
            if(_reload === 1)
                window.location.reload();
        });

        $body.on('click', '.pprmo_item', function () {
            if(!confirm('<?php echo esc_js(__('Are you sure?', WPDMPP_TEXT_DOMAIN)); ?>')) return false;
            $(this).html(<?php echo wp_json_encode( Icons::spinner(14) ); ?>);
            $.get(ajaxurl, {order: '<?php echo esc_js($order->order_id); ?>',product: $(this).data('pid'), action: 'wpdmpp_edit_order', task: 'remove_product', __eononce: '<?php echo esc_js(wp_create_nonce(WPDM_PRI_NONCE)); ?>'}, function (res) {
                window.location.reload();
            });
        });



        $('.datetime').datetimepicker({
            dateFormat: "M dd, yy",
            timeFormat: "hh:mm tt"
        });
        $('.ttip').tooltip();

        // Order Actions Dropdown Toggle
        $('#orderActionsBtn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $parent = $(this).closest('.wpdm-order-actions');
            $parent.toggleClass('open');
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.wpdm-order-actions').length) {
                $('.wpdm-order-actions').removeClass('open');
            }
        });

        // Close dropdown when clicking a menu item
        $('.wpdm-order-actions__item').on('click', function() {
            $(this).closest('.wpdm-order-actions').removeClass('open');
        });

        // Close dropdown on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.wpdm-order-actions').removeClass('open');
                $('.wpdm-pm-dropdown').removeClass('open');
            }
        });

        // Payment method change uses a Bootstrap modal (#changepmmodal) — the
        // .changepm handler above performs the AJAX and hides the modal on success.

    });
</script>
<style>
    .chzn-search input {
        display: none;
    }
    .w3eden .badge.badge-success {
        background: var(--color-success);font-weight: 400;font-size: 11px;border-radius: 3px;text-transform: uppercase;letter-spacing: 1px;
    }

    .chzn-results {
        padding-top: 5px !important;
    }

    .btn-group.bootstrap-select .btn {
        border-radius: 3px !important;
    }

    a:focus {
        outline: none !important;
    }

    .panel-heading {
        font-weight: bold;
    }

    .text-renew * {
        font-weight: 800;
        color: #1e9460;
    }

    .w3eden .dropdown-menu > li {
        margin-bottom: 0;
    }

    .w3eden .dropdown-menu > li > a {
        padding: 5px 20px;
    }

    a.list-item {
        display: block;
        padding: 0 20px;
        line-height: 40px;
        color: #666666;
        text-decoration: none;
        font-size: 11px;
    }

    a.list-item:hover {
        text-decoration: none;
    }

    a.list-item:not(:last-child) {
        border-bottom: 1px solid #dddddd;
    }

    /* Order Actions Dropdown - Modern Design */
    .wpdm-order-actions {
        position: relative;
        display: inline-block;
    }

    .wpdm-order-actions__trigger {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 500;
        color: #475569;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .wpdm-order-actions__trigger:hover {
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border-color: #cbd5e1;
        color: #1e293b;
    }

    .wpdm-order-actions__trigger:focus {
        outline: none;
        border-color: var(--color-info, #0ea5e9);
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    }

    .wpdm-order-actions__trigger svg {
        flex-shrink: 0;
        color: #64748b;
    }

    .wpdm-order-actions__trigger span {
        white-space: nowrap;
    }

    .wpdm-order-actions__chevron {
        transition: transform 0.2s ease;
    }

    .wpdm-order-actions.open .wpdm-order-actions__chevron {
        transform: rotate(180deg);
    }

    .wpdm-order-actions__menu {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        min-width: 220px;
        padding: 6px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all 0.2s ease;
        z-index: 1000;
    }

    .wpdm-order-actions.open .wpdm-order-actions__menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .wpdm-order-actions__item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        font-size: 13px;
        color: #475569;
        text-decoration: none !important;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .wpdm-order-actions__item:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .wpdm-order-actions__item:active {
        background: #e2e8f0;
    }

    .wpdm-order-actions__item svg {
        flex-shrink: 0;
        color: #64748b;
        transition: color 0.15s ease;
    }

    .wpdm-order-actions__item:hover svg {
        color: var(--color-info, #0ea5e9);
    }

    /* Separator between items (optional) */
    .wpdm-order-actions__separator {
        height: 1px;
        margin: 6px 0;
        background: #e2e8f0;
    }

    /* Payment Method Dropdown - Modern Design */
    .wpdm-pm-dropdown {
        position: relative;
        display: inline-block;
    }

    .wpdm-pm-dropdown__trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 6px;
        color: var(--color-info, #0ea5e9);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .wpdm-pm-dropdown__trigger:hover svg{
        stroke: var(--admin-color);
    }

    .wpdm-pm-dropdown__trigger svg {
        flex-shrink: 0;
    }

    .wpdm-pm-dropdown__menu {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        width: 240px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: all 0.2s ease;
        z-index: 1000;
        overflow: hidden;
    }

    .wpdm-pm-dropdown.open .wpdm-pm-dropdown__menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .wpdm-pm-dropdown__header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
    }

    .wpdm-pm-dropdown__header svg {
        color: #64748b;
    }

    .wpdm-pm-dropdown__body {
        max-height: 200px;
        overflow-y: auto;
        padding: 6px;
    }

    .wpdm-pm-dropdown__item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        font-size: 13px;
        color: #475569;
        text-decoration: none !important;
        border-radius: 6px;
        transition: all 0.15s ease;
    }

    .wpdm-pm-dropdown__item:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .wpdm-pm-dropdown__item:active {
        background: #e2e8f0;
    }

    .wpdm-pm-dropdown__item svg {
        flex-shrink: 0;
        color: #94a3b8;
        transition: color 0.15s ease;
    }

    .wpdm-pm-dropdown__item:hover svg {
        color: var(--color-success, #10b981);
    }

    .wpdm-pm-dropdown__item.selected {
        background: rgba(16, 185, 129, 0.1);
        color: var(--color-success, #10b981);
    }

    .wpdm-pm-dropdown__item.selected svg {
        color: var(--color-success, #10b981);
    }

    /* Custom scrollbar for payment methods */
    .wpdm-pm-dropdown__body::-webkit-scrollbar {
        width: 6px;
    }

    .wpdm-pm-dropdown__body::-webkit-scrollbar-track {
        background: transparent;
    }

    .wpdm-pm-dropdown__body::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 3px;
    }

    .wpdm-pm-dropdown__body::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }

    /* Spin animation for loading states */
    @keyframes wpdm-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .wpdm-pm-dropdown__trigger.loading {
        opacity: 0.6;
        cursor: wait;
    }
</style>
<?php
} else {
    ?>
    <div class="text-center">
        <div class="alert alert-danger lead" style="border-radius: 3px;display: inline-block;">
            <?php echo Icons::get('times-circle', 16); ?>
            <?php
            echo esc_attr__('Error: No matching order found!', WPDMPP_TEXT_DOMAIN);
            ?>
        </div>
    </div>
    <?php
}
