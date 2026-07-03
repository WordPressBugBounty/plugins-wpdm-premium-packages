<?php
if(!defined('ABSPATH')) die('Dream more!');
use WPDMPP\UI\Icons;


global $wpdb;


// getAllRenews() joins ahm_order_renews (alias r) with ahm_orders (alias o).
// Filters on order columns must be qualified with o.* and renewal-date filters with r.*.
if ( isset( $_REQUEST['oid'] ) && $_REQUEST['oid'] ) {
	$qry[] = "o.order_id='" . sanitize_text_field( $_REQUEST['oid'] ) . "'";
}
if ( isset( $_REQUEST['customer'] ) && $_REQUEST['customer'] != '' ) {
	$customer = esc_sql( $_REQUEST['customer'] );
	if ( is_email( $customer ) ) {
		$customer = email_exists( $customer );
	}
	$qry[] = "o.uid='{$customer}'";
}
if ( wpdm_query_var( 'ost' ) != 'Expiring' ) {
	if ( isset( $_REQUEST['ost'] ) && $_REQUEST['ost'] ) {
		$qry[] = "o.order_status='" . sanitize_text_field( $_REQUEST['ost'] ) . "'";
	}
	if ( isset( $_REQUEST['pst'] ) && $_REQUEST['pst'] ) {
		$qry[] = "o.payment_status='" . sanitize_text_field( $_REQUEST['pst'] ) . "'";
	}

	if ( isset( $_REQUEST['sdate'], $_REQUEST['edate'] ) && ( $_REQUEST['sdate'] != '' || $_REQUEST['edate'] != '' ) ) {
		$_REQUEST['edate'] = $_REQUEST['edate'] ? $_REQUEST['edate'] : $_REQUEST['sdate'];
		$_REQUEST['sdate'] = $_REQUEST['sdate'] ? $_REQUEST['sdate'] : $_REQUEST['edate'];
		$sdate             = strtotime( $_REQUEST['sdate'] );
		$edate             = strtotime( $_REQUEST['edate'] );
		$qry[]             = "(r.`date` >=$sdate and r.`date` <=$edate)";
	}
} else {
	$qry[] = "o.order_status='Completed'";
	$sdate = wpdm_query_var( 'sdate' ) != '' ? strtotime( wpdm_query_var( 'sdate' ) ) : time();
	$edate = wpdm_query_var( 'edate' ) != '' ? strtotime( wpdm_query_var( 'edate' ) ) : strtotime( "+7 days" );
	$qry[] = "(o.`expire_date` >=$sdate and o.`expire_date` <=$edate)";

}

if ( isset( $qry ) ) {
	$qry = "where " . implode( " and ", $qry );
} else {
	$qry = "";
}

if ( wpdm_query_var( 'orderby' ) != '' ) {
	$orderby = sanitize_text_field( wpdm_query_var( 'orderby' ) );
	$_order  = wpdm_query_var( 'order' ) == 'asc' ? 'asc' : 'desc';
	$qry     = $qry . " order by {$orderby} $_order";
} else {
	$qry = "$qry order by r.`date` desc";
}

$t      = $orderObj->totalRenews( );
$orders = $orderObj->getAllRenews( $qry, $s, $l );

$osi = array('Pending'=>'ellipsis','Processing'=>'clock','Completed'=>'check','Cancelled'=>'close','Refunded'=>'redo','Expired' => 'times-circle','Gifted' => 'gift','Disputed'=>'info-circle');
if(!wpdm_query_var('customer') && !wpdm_query_var('oid')) {
	$completed  = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_orders where payment_status='Completed' or payment_status='Expired'" );
	$expired    = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_orders where payment_status='Expired'" );
	$refunded   = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_orders where payment_status='Refunded'" );
	$abandoned  = $wpdb->get_row( "select sum(total) as sales, count(total) as orders from {$wpdb->prefix}ahm_orders where payment_status='Processing'" );
	$allrenews  = $wpdb->get_row( "select sum(total) as sales, count(total) as orders, order_id from {$wpdb->prefix}ahm_order_renews" );

	$sdatet     = strtotime( date( "Y-m-d" ) . " 00:00:00" );
	$edatet     = strtotime( date( "Y-m-d" ) . " 23:59:59" );
	$newtoday   = $wpdb->get_row( $wpdb->prepare(
		"SELECT SUM(total) AS sales, COUNT(total) AS orders FROM {$wpdb->prefix}ahm_orders
		 WHERE payment_status = 'Completed' AND `date` >= %d AND `date` <= %d",
		$sdatet, $edatet
	) );
	$renewtoday = $wpdb->get_row( $wpdb->prepare(
		"SELECT SUM(total) AS sales, COUNT(total) AS orders FROM {$wpdb->prefix}ahm_order_renews
		 WHERE `date` >= %d AND `date` <= %d",
		$sdatet, $edatet
	) );

}
$order_ids = [];
foreach ( $orders as $order ) {
    $order_ids[] = $order->order_id;
}
$renews = [];
if ( ! empty( $order_ids ) ) {
	$placeholders = implode( ', ', array_fill( 0, count( $order_ids ), '%s' ) );
	$renews = $wpdb->get_results( $wpdb->prepare(
		"SELECT COUNT(*) AS renew_cycle, order_id FROM {$wpdb->prefix}ahm_order_renews
		 WHERE order_id IN ($placeholders) GROUP BY order_id",
		$order_ids
	) );
}
$renew_cycle = array();
foreach ($renews as $renew){
	$renew_cycle[$renew->order_id] = $renew->renew_cycle;
}
$has_filter = wpdm_query_var( 'oid' ) !== '' || wpdm_query_var( 'customer' ) !== '' || wpdm_query_var( 'ost' ) !== '' || wpdm_query_var( 'pst' ) !== '' || wpdm_query_var( 'sdate' ) !== '' || wpdm_query_var( 'edate' ) !== '';
$show_stats = ! wpdm_query_var( 'customer' ) && ! wpdm_query_var( 'oid' );
?>
<div class="wpdmpp-ol">
<style>
/* ==========================================================================
   Renewed-orders list — shares the orders-list enterprise surface. Scoped under
   .wpdmpp-ol so it never leaks into other admin screens.
   ========================================================================== */
.wpdmpp-ol{
	--ol-surface:var(--color-bg-card,#fff);
	--ol-surface-2:#f8fafc;
	--ol-text:var(--color-text,#0f172a);
	--ol-muted:var(--color-muted,#64748b);
	--ol-faint:#94a3b8;
	--ol-border:var(--color-border,#e7eaef);
	--ol-border-soft:#eef1f5;
	--ol-primary:var(--color-primary,#4f46e5);
	--ol-primary-rgb:var(--color-primary-rgb,79,70,229);
	--ol-radius:14px;
	--ol-radius-sm:9px;
	--ol-shadow:0 1px 2px rgba(15,23,42,.04),0 10px 30px -12px rgba(15,23,42,.12);
	--ol-shadow-sm:0 1px 2px rgba(15,23,42,.05);
	--ol-font:var(--wpdm-font,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Inter,Helvetica,Arial,sans-serif);
	font-family:var(--ol-font);
	color:var(--ol-text);
	font-size:14px;
	line-height:1.5;
}
.wpdmpp-ol *{box-sizing:border-box;}
.wpdmpp-ol a{color:var(--ol-primary);text-decoration:none;}
.wpdmpp-ol a:hover{text-decoration:underline;}
.ui-datepicker.ui-widget{z-index:9999 !important;}

.wpdmpp-ol__kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin:0 0 20px;}
.wpdmpp-ol__kpi{background:var(--ol-surface);border:1px solid var(--ol-border);border-radius:16px;padding:18px 18px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
.wpdmpp-ol__kpi-top{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.wpdmpp-ol__kpi-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:11px;flex:0 0 auto;background:#f1f5f9;color:var(--ol-faint);}
.wpdmpp-ol__kpi-icon svg{width:19px;height:19px;}
.wpdmpp-ol__kpi-label{font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--ol-muted);}
.wpdmpp-ol__kpi-value{display:block;font-size:25px;line-height:1.1;font-weight:700;letter-spacing:-.025em;color:var(--ol-text);font-variant-numeric:tabular-nums;}
.wpdmpp-ol__kpi-meta{display:flex;align-items:center;gap:7px;margin-top:9px;font-size:12.5px;color:var(--ol-faint);font-variant-numeric:tabular-nums;}
.wpdmpp-ol__kpi-meta b{color:var(--ol-muted);font-weight:600;}
.wpdmpp-ol__kpi-dot{width:7px;height:7px;border-radius:50%;flex:0 0 auto;background:var(--ol-faint);}
.wpdmpp-ol__kpi--success .wpdmpp-ol__kpi-icon{background:#ecfdf5;color:#059669;}
.wpdmpp-ol__kpi--success .wpdmpp-ol__kpi-dot{background:#10b981;}
.wpdmpp-ol__kpi--indigo .wpdmpp-ol__kpi-icon{background:#eef2ff;color:#4f46e5;}
.wpdmpp-ol__kpi--indigo .wpdmpp-ol__kpi-dot{background:#6366f1;}
.wpdmpp-ol__kpi--sky .wpdmpp-ol__kpi-icon{background:#e0f2fe;color:#0284c7;}
.wpdmpp-ol__kpi--sky .wpdmpp-ol__kpi-dot{background:#0ea5e9;}
.wpdmpp-ol__kpi--teal .wpdmpp-ol__kpi-icon{background:#f0fdfa;color:#0d9488;}
.wpdmpp-ol__kpi--teal .wpdmpp-ol__kpi-dot{background:#14b8a6;}
.wpdmpp-ol__kpi--rose .wpdmpp-ol__kpi-icon{background:#fff1f2;color:#e11d48;}
.wpdmpp-ol__kpi--rose .wpdmpp-ol__kpi-dot{background:#f43f5e;}
.wpdmpp-ol__kpi--violet .wpdmpp-ol__kpi-icon{background:#f5f3ff;color:#7c3aed;}
.wpdmpp-ol__kpi--violet .wpdmpp-ol__kpi-dot{background:#8b5cf6;}

.wpdmpp-ol .panel.panel-default{background:var(--ol-surface);border:1px solid var(--ol-border);border-radius:var(--ol-radius);box-shadow:var(--ol-shadow-sm);margin-bottom:16px;overflow:hidden;}
.wpdmpp-ol .panel .panel-body{padding:16px 18px;}

.wpdmpp-ol__toolbar{display:flex;flex-wrap:wrap;align-items:end;gap:12px 14px;}
.wpdmpp-ol__field{display:flex;flex-direction:column;gap:6px;min-width:0;flex:1 1 160px;}
.wpdmpp-ol__field--wide{flex:1.4 1 210px;}
.wpdmpp-ol__field--action{flex:0 0 auto;}
.wpdmpp-ol__label{font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--ol-muted);}
.wpdmpp-ol__search-btn.btn{height:38px;display:inline-flex;align-items:center;gap:7px;padding:0 20px;border-radius:var(--ol-radius-sm);background:var(--ol-primary);border:1px solid var(--ol-primary);color:#fff;font-weight:600;font-size:13.5px;box-shadow:var(--ol-shadow-sm);transition:filter .15s ease,box-shadow .15s ease;}
.wpdmpp-ol__search-btn.btn:hover{filter:brightness(1.06);color:#fff;box-shadow:0 4px 14px -4px rgba(var(--ol-primary-rgb),.5);}
.wpdmpp-ol__search-btn.btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(var(--ol-primary-rgb),.35);}
.wpdmpp-ol__search-btn svg{width:15px;height:15px;}

.wpdmpp-ol__resultbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:11px 18px;border-top:1px solid var(--ol-border-soft);background:var(--ol-surface-2);font-size:13px;color:var(--ol-muted);}
.wpdmpp-ol__resultbar .ol-count{font-weight:600;color:var(--ol-text);font-variant-numeric:tabular-nums;}
.wpdmpp-ol__resultbar .ol-total{font-variant-numeric:tabular-nums;}
.wpdmpp-ol__resultbar .ol-total b{color:#15803d;}

.wpdmpp-ol .wpdmpp-orders-tablewrap.panel.panel-default{width:100%;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;padding:0;}
.wpdmpp-ol table.table-wpdmpp{width:100%;min-width:1140px;border-collapse:separate;border-spacing:0;margin:0;background:var(--ol-surface);font-variant-numeric:tabular-nums;}
.wpdmpp-ol table.table-wpdmpp thead th{position:sticky;top:0;z-index:2;background:var(--ol-surface-2);padding:11px 14px;text-align:left;vertical-align:middle;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--ol-muted);white-space:nowrap;border-bottom:1px solid var(--ol-border);}
.wpdmpp-ol table.table-wpdmpp thead th a{color:var(--ol-muted);font-weight:600;display:inline-flex;align-items:center;gap:4px;}
.wpdmpp-ol table.table-wpdmpp thead th a:hover{color:var(--ol-text);text-decoration:none;}
.wpdmpp-ol table.table-wpdmpp tbody td{padding:13px 14px;vertical-align:middle;color:var(--ol-text);font-size:13.5px;background:transparent;}
.wpdmpp-ol table.table-wpdmpp.table-striped tbody tr:nth-of-type(odd) td{background:transparent;}
.wpdmpp-ol table.table-wpdmpp tbody tr{transition:background .12s ease;}
.wpdmpp-ol table.table-wpdmpp tbody tr:hover td{background:rgba(var(--ol-primary-rgb),.035);}
.wpdmpp-ol table.table-wpdmpp tbody tr:last-child td{border-bottom:0;}
.wpdmpp-ol table.table-wpdmpp tbody tr.row-focus td{background:rgba(var(--ol-primary-rgb),.08);box-shadow:inset 3px 0 0 var(--ol-primary);}
.wpdmpp-ol .check-column{width:42px;text-align:center;}
.wpdmpp-ol .check-column input[type=checkbox]{margin:0;cursor:pointer;}
.wpdmpp-ol .ol-col-order{width:230px;}
.wpdmpp-ol .ol-col-icon{width:46px;text-align:center;}
.wpdmpp-ol .ol-th-ico{display:inline-flex;align-items:center;justify-content:center;color:var(--ol-faint);}
.wpdmpp-ol .ol-th-ico svg{width:16px;height:16px;}
.wpdmpp-ol .ol-order-id{display:inline-block;max-width:230px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom;font-weight:700;font-size:14px;letter-spacing:-.01em;}
.wpdmpp-ol .ol-sub{display:flex;align-items:center;gap:10px;max-width:230px;font-size:12px;color:var(--ol-faint);margin-top:4px;}
.wpdmpp-ol .ol-sub svg{width:13px;height:13px;vertical-align:-2px;}
.wpdmpp-ol .ol-sub__items{flex:0 0 auto;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;}
.wpdmpp-ol .ol-sub__txn{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.wpdmpp-ol .ol-total-amt{font-weight:700;font-size:14px;color:var(--ol-text);white-space:nowrap;}
.wpdmpp-ol .ol-via{display:block;font-size:11.5px;color:var(--ol-faint);margin-top:2px;text-transform:capitalize;white-space:nowrap;}
.wpdmpp-ol .ol-total-cell{white-space:nowrap;}
.wpdmpp-ol .ol-pay-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-left:7px;vertical-align:middle;background:#94a3b8;box-shadow:0 0 0 3px rgba(148,163,184,.16);cursor:default;}
.wpdmpp-ol .ol-pay-dot--Completed,.wpdmpp-ol .ol-pay-dot--Bonus{background:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.16);}
.wpdmpp-ol .ol-pay-dot--Pending,.wpdmpp-ol .ol-pay-dot--Processing{background:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.18);}
.wpdmpp-ol .ol-pay-dot--Cancelled,.wpdmpp-ol .ol-pay-dot--Refunded,.wpdmpp-ol .ol-pay-dot--Disputed{background:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.16);}
.wpdmpp-ol .ol-pay-dot--Expired{background:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.16);}
.wpdmpp-ol .ol-pay-dot--Gifted{background:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,.16);}
.wpdmpp-ol .ol-cust-name{font-weight:600;}
.wpdmpp-ol .ol-cust-email{font-size:12.5px;color:var(--ol-muted);}
.wpdmpp-ol .text-filter{color:var(--ol-faint);display:inline-flex;vertical-align:middle;}
.wpdmpp-ol .text-filter:hover{color:var(--ol-primary);}
.wpdmpp-ol .ol-date{font-size:13px;color:var(--ol-text);}
.wpdmpp-ol .ol-renewed{font-size:13px;color:#15803d;font-weight:600;}
.wpdmpp-ol .ol-date__stack{display:inline-flex;flex-direction:column;line-height:1.3;}
.wpdmpp-ol .ol-date__d{white-space:nowrap;}
.wpdmpp-ol .ol-date__t{font-size:11.5px;color:var(--ol-faint);font-weight:normal;white-space:nowrap;}
.wpdmpp-ol .ol-cycle{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;color:var(--ol-muted);}
.wpdmpp-ol .ol-cycle svg{width:13px;height:13px;}
.wpdmpp-ol .ol-muted{color:var(--ol-muted);}

.wpdmpp-ol .ol-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px 3px 8px;border-radius:999px;font-size:11.5px;font-weight:600;letter-spacing:.01em;white-space:nowrap;background:#f1f5f9;color:#475569;border:1px solid transparent;}
.wpdmpp-ol .ol-pill svg{width:13px;height:13px;}
.wpdmpp-ol .ol-pill--Completed,.wpdmpp-ol .ol-pill--Bonus{background:#dcfce7;color:#15803d;}
.wpdmpp-ol .ol-pill--Pending,.wpdmpp-ol .ol-pill--Processing{background:#fef3c7;color:#b45309;}
.wpdmpp-ol .ol-pill--Cancelled,.wpdmpp-ol .ol-pill--Refunded,.wpdmpp-ol .ol-pill--Disputed{background:#fee2e2;color:#b91c1c;}
.wpdmpp-ol .ol-pill--Expired{background:#dbeafe;color:#1d4ed8;}
.wpdmpp-ol .ol-pill--Gifted{background:#ede9fe;color:#6d28d9;}

.wpdmpp-ol .ol-ind{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;}
.wpdmpp-ol .ol-ind svg{width:15px;height:15px;}
.wpdmpp-ol .ol-ind--on{background:#dcfce7;color:#15803d;}
.wpdmpp-ol .ol-ind--off{background:#f1f5f9;color:var(--ol-faint);}
.wpdmpp-ol a.auto-renew-order{display:inline-flex;}
.wpdmpp-ol .wpdmpp-status-badge{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:#f1f5f9;color:var(--ol-faint);}
.wpdmpp-ol .wpdmpp-status-badge svg{width:15px;height:15px;}
.wpdmpp-ol .wpdmpp-status-badge.renew-active{background:#dcfce7;color:#15803d;}
.wpdmpp-ol .wpdmpp-status-badge.renew-cancelled{background:#fee2e2;color:#b91c1c;}

.wpdmpp-ol .ol-empty td{padding:0;border:0;}
.wpdmpp-ol .ol-empty__inner{display:flex;flex-direction:column;align-items:center;gap:10px;padding:54px 20px;text-align:center;color:var(--ol-muted);}
.wpdmpp-ol .ol-empty__icon{display:flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:14px;background:var(--ol-surface-2);color:var(--ol-faint);}
.wpdmpp-ol .ol-empty__icon svg{width:24px;height:24px;}
.wpdmpp-ol .ol-empty__title{font-size:15px;font-weight:600;color:var(--ol-text);}
.wpdmpp-ol .ol-empty__hint{font-size:13px;color:var(--ol-faint);max-width:360px;}

.wpdmpp-ol .tablenav{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;height:auto;margin-top:14px;padding:2px;}
.wpdmpp-ol .tablenav-pages{margin:0;display:flex;align-items:center;flex-wrap:wrap;gap:6px;}
.wpdmpp-ol .tablenav .displaying-num{font-size:12.5px;color:var(--ol-muted);font-variant-numeric:tabular-nums;margin-right:8px;}
.wpdmpp-ol .tablenav-pages .page-numbers{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 9px;margin:0;border:1px solid var(--ol-border);border-radius:8px;background:var(--ol-surface);color:var(--ol-text);font-size:13px;font-weight:600;text-decoration:none;transition:background .12s ease,border-color .12s ease,color .12s ease;}
.wpdmpp-ol .tablenav-pages .page-numbers:hover{background:var(--ol-surface-2);border-color:var(--ol-faint);text-decoration:none;}
.wpdmpp-ol .tablenav-pages .page-numbers.current{background:var(--ol-primary);border-color:var(--ol-primary);color:#fff;}
.wpdmpp-ol .tablenav-pages .page-numbers.dots{border-color:transparent;background:transparent;}

.wpdmpp-ol .ol-expiring ul{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;}
.wpdmpp-ol .ol-expiring li{display:flex;align-items:center;gap:9px;font-size:13.5px;color:var(--ol-text);}

#wpdmpp-renews-mobile{display:none;}
.wpmo-list{display:flex;flex-direction:column;gap:12px;}
.wpmo-card{background:var(--ol-surface,#fff);border:1px solid var(--ol-border,#e7eaef);border-radius:14px;padding:15px 16px;box-shadow:0 1px 2px rgba(15,23,42,.05);}
.wpmo-card__head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px;}
.wpmo-card__id{font-size:15px;font-weight:700;text-decoration:none;letter-spacing:-.01em;}
.wpmo-amount{font-size:19px;font-weight:700;color:var(--ol-text,#0f172a);margin-bottom:12px;font-variant-numeric:tabular-nums;}
.wpmo-via{font-size:12px;font-weight:400;color:var(--ol-muted,#64748b);text-transform:capitalize;}
.wpmo-meta{display:flex;flex-direction:column;gap:9px;padding-top:11px;border-top:1px solid var(--ol-border-soft,#eef1f5);}
.wpmo-row{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;font-size:13px;line-height:1.4;}
.wpmo-k{color:var(--ol-muted,#64748b);font-weight:600;flex:0 0 auto;}
.wpmo-v{text-align:right;color:var(--ol-text,#0f172a);word-break:break-word;min-width:0;font-variant-numeric:tabular-nums;}
.wpmo-v a{word-break:break-all;}
.wpmo-on{color:#15803d;font-weight:600;}
.wpmo-off{color:var(--ol-faint,#94a3b8);}
.wpmo-pill{display:inline-flex;align-items:center;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.01em;background:#f1f5f9;color:#475569;white-space:nowrap;}
.wpmo-pill--completed,.wpmo-pill--bonus{background:#dcfce7;color:#15803d;}
.wpmo-pill--processing,.wpmo-pill--pending{background:#fef3c7;color:#b45309;}
.wpmo-pill--cancelled,.wpmo-pill--refunded,.wpmo-pill--disputed{background:#fee2e2;color:#b91c1c;}
.wpmo-pill--expired{background:#dbeafe;color:#1d4ed8;}
.wpmo-pill--gifted{background:#ede9fe;color:#6d28d9;}
.wpmo-view{display:block;text-align:center;margin-top:14px;padding:10px 16px;border-radius:9px;background:var(--ol-primary,#4f46e5);color:#fff !important;font-weight:600;text-decoration:none;}
.wpmo-empty{padding:34px;text-align:center;color:var(--ol-muted,#64748b);}

@media screen and (max-width:782px){
	.wpdmpp-ol__toolbar{gap:10px;}
	.wpdmpp-ol__field,.wpdmpp-ol__field--wide{flex:1 1 100%;}
	.wpdmpp-ol__field--action{flex:1 1 100%;}
	.wpdmpp-ol table.table-wpdmpp{min-width:0;}
	.wpdmpp-orders-responsive.is-vue-ready .wpdmpp-orders-tablewrap{display:none;}
	.wpdmpp-orders-responsive.is-vue-ready #wpdmpp-renews-mobile{display:block;}
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp thead,
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp tfoot{display:none;}
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp tbody,
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp tr,
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp td,
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp th{display:block;width:100% !important;box-sizing:border-box;}
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp tr{margin:0 0 14px;padding:6px 14px;border:1px solid var(--ol-border,#e7eaef);border-radius:10px;background:var(--ol-surface,#fff);}
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp td,
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp th.check-column{display:flex;align-items:center;justify-content:space-between;gap:12px;text-align:right;padding:9px 0;border-bottom:1px solid var(--ol-border-soft,#eef1f5);}
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp tr td:last-child{border-bottom:0;}
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp td::before,
	.wpdmpp-orders-responsive:not(.is-vue-ready) .table-wpdmpp th.check-column::before{content:attr(data-label);font-weight:600;color:var(--ol-muted,#64748b);text-align:left;margin-right:auto;}
}
@media (prefers-reduced-motion:reduce){
	.wpdmpp-ol *{transition:none !important;}
}
</style>

<?php if($show_stats) { ?>
	<div class="wpdmpp-ol__kpis">
		<div class="wpdmpp-ol__kpi wpdmpp-ol__kpi--success">
			<div class="wpdmpp-ol__kpi-top"><span class="wpdmpp-ol__kpi-icon"><?php echo Icons::get('check-circle', 19); ?></span><span class="wpdmpp-ol__kpi-label"><?php echo __( "Completed", WPDMPP_TEXT_DOMAIN ); ?></span></div>
			<span class="wpdmpp-ol__kpi-value"><?php echo wpdmpp_price_format($completed->sales, true, true); ?></span>
			<span class="wpdmpp-ol__kpi-meta"><span class="wpdmpp-ol__kpi-dot"></span><b><?php echo (int)$completed->orders; ?></b> <?php _e("orders","wpdm-premium-packages"); ?></span>
		</div>
		<div class="wpdmpp-ol__kpi wpdmpp-ol__kpi--indigo">
			<div class="wpdmpp-ol__kpi-top"><span class="wpdmpp-ol__kpi-icon"><?php echo Icons::get('sync', 19); ?></span><span class="wpdmpp-ol__kpi-label"><?php echo __( "Renewed", WPDMPP_TEXT_DOMAIN ); ?></span></div>
			<span class="wpdmpp-ol__kpi-value"><?php echo wpdmpp_price_format($allrenews->sales, true, true); ?></span>
			<span class="wpdmpp-ol__kpi-meta"><span class="wpdmpp-ol__kpi-dot"></span><b><?php echo (int)$allrenews->orders; ?></b> <?php _e("renewals","wpdm-premium-packages"); ?></span>
		</div>
		<div class="wpdmpp-ol__kpi wpdmpp-ol__kpi--sky">
			<div class="wpdmpp-ol__kpi-top"><span class="wpdmpp-ol__kpi-icon"><?php echo Icons::get('plus-circle', 19); ?></span><span class="wpdmpp-ol__kpi-label"><?php echo __( "New Today", WPDMPP_TEXT_DOMAIN ); ?></span></div>
			<span class="wpdmpp-ol__kpi-value"><?php echo wpdmpp_price_format($newtoday->sales, true, true); ?></span>
			<span class="wpdmpp-ol__kpi-meta"><span class="wpdmpp-ol__kpi-dot"></span><b><?php echo (int)$newtoday->orders; ?></b> <?php _e("orders","wpdm-premium-packages"); ?></span>
		</div>
		<div class="wpdmpp-ol__kpi wpdmpp-ol__kpi--teal">
			<div class="wpdmpp-ol__kpi-top"><span class="wpdmpp-ol__kpi-icon"><?php echo Icons::get('calendar', 19); ?></span><span class="wpdmpp-ol__kpi-label"><?php echo __( "Renewed Today", WPDMPP_TEXT_DOMAIN ); ?></span></div>
			<span class="wpdmpp-ol__kpi-value"><?php echo wpdmpp_price_format(@$renewtoday->sales, true, true); ?></span>
			<span class="wpdmpp-ol__kpi-meta"><span class="wpdmpp-ol__kpi-dot"></span><b><?php echo (int)@$renewtoday->orders; ?></b> <?php _e("renewals","wpdm-premium-packages"); ?></span>
		</div>
		<div class="wpdmpp-ol__kpi wpdmpp-ol__kpi--rose">
			<div class="wpdmpp-ol__kpi-top"><span class="wpdmpp-ol__kpi-icon"><?php echo Icons::get('redo', 19); ?></span><span class="wpdmpp-ol__kpi-label"><?php echo __( "Refunded", WPDMPP_TEXT_DOMAIN ); ?></span></div>
			<span class="wpdmpp-ol__kpi-value"><?php echo wpdmpp_price_format($refunded->sales, true, true); ?></span>
			<span class="wpdmpp-ol__kpi-meta"><span class="wpdmpp-ol__kpi-dot"></span><b><?php echo (int)$refunded->orders; ?></b> <?php _e("orders","wpdm-premium-packages"); ?></span>
		</div>
		<div class="wpdmpp-ol__kpi wpdmpp-ol__kpi--violet">
			<div class="wpdmpp-ol__kpi-top"><span class="wpdmpp-ol__kpi-icon"><?php echo Icons::get('clock', 19); ?></span><span class="wpdmpp-ol__kpi-label"><?php echo __( "Expired", WPDMPP_TEXT_DOMAIN ); ?></span></div>
			<span class="wpdmpp-ol__kpi-value"><?php echo wpdmpp_price_format($expired->sales, true, true); ?></span>
			<span class="wpdmpp-ol__kpi-meta"><span class="wpdmpp-ol__kpi-dot"></span><b><?php echo (int)$expired->orders; ?></b> <?php _e("orders","wpdm-premium-packages"); ?></span>
		</div>
	</div>
<?php } ?>
<div class="clear"></div>
<div class="wpdmpp-ol__main">
	<form method="get" action="" id="order-search">
		<input type="hidden" name="post_type" value="wpdmpro">
		<input type="hidden" name="page" value="orders">
		<input type="hidden" name="task" value="show_renews">
		<div class="panel panel-default">
			<div class="panel-body">
				<div class="wpdmpp-ol__toolbar">
					<div class="wpdmpp-ol__field">
						<label class="wpdmpp-ol__label" for="ol-f-ost"><?php _e('Order Status','wpdm-premium-packages'); ?></label>
						<select class="select-action form-control wpdm-custom-select" id="ol-f-ost" name="ost">
							<option value=""><?php _e('All statuses','wpdm-premium-packages'); ?></option>
							<option value="Pending" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Pending'?'selected=selected':''; ?>>Pending</option>
							<option value="Processing" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Processing'?'selected=selected':''; ?>>Processing</option>
							<option value="Completed" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Completed'?'selected=selected':''; ?>>Completed</option>
							<option value="Cancelled" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Cancelled'?'selected=selected':''; ?>>Cancelled</option>
							<option value="Expiring" <?php if(isset($_REQUEST['ost'])) echo $_REQUEST['ost']=='Expiring'?'selected=selected':''; ?>><?php _e('Expiring ( On Selected Period )','wpdm-premium-packages'); ?></option>
						</select>
					</div>
					<div class="wpdmpp-ol__field">
						<label class="wpdmpp-ol__label" for="ol-f-pst"><?php _e('Payment Status','wpdm-premium-packages'); ?></label>
						<select class="select-action form-control wpdm-custom-select" id="ol-f-pst" name="pst">
							<option value=""><?php _e('All payments','wpdm-premium-packages'); ?></option>
							<option value="Pending" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Pending'?'selected=selected':''; ?>>Pending</option>
							<option value="Processing" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Processing'?'selected=selected':''; ?>>Processing</option>
							<option value="Completed" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Completed'?'selected=selected':''; ?>>Completed</option>
							<option value="Bonus" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Bonus'?'selected=selected':''; ?>>Bonus</option>
							<option value="Gifted" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Gifted'?'selected=selected':''; ?>>Gifted</option>
							<option value="Cancelled" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Cancelled'?'selected=selected':''; ?>>Cancelled</option>
							<option value="Disputed" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Disputed'?'selected=selected':''; ?>>Disputed</option>
							<option value="Refunded" <?php if(isset($_REQUEST['pst'])) echo $_REQUEST['pst']=='Refunded'?'selected=selected':''; ?>>Refunded</option>
						</select>
					</div>
					<div class="wpdmpp-ol__field">
						<label class="wpdmpp-ol__label" for="ol-f-sdate"><?php _e('From','wpdm-premium-packages'); ?></label>
						<input class="form-control datep" type="text" placeholder="<?php _e("Start date","wpdm-premium-packages");?>" id="ol-f-sdate" name="sdate" value="<?php if(isset($_REQUEST['sdate'])) echo esc_attr($_REQUEST['sdate']); ?>">
					</div>
					<div class="wpdmpp-ol__field">
						<label class="wpdmpp-ol__label" for="ol-f-edate"><?php _e('To','wpdm-premium-packages'); ?></label>
						<input class="form-control datep" type="text" placeholder="<?php _e("End date","wpdm-premium-packages");?>" id="ol-f-edate" name="edate" value="<?php if(isset($_REQUEST['edate'])) echo esc_attr($_REQUEST['edate']); ?>">
					</div>
					<div class="wpdmpp-ol__field">
						<label class="wpdmpp-ol__label" for="ol-f-oid"><?php _e('Order ID','wpdm-premium-packages'); ?></label>
						<input class="form-control" type="text" placeholder="<?php _e("e.g. WPDMPP65A4B3C2","wpdm-premium-packages");?>" id="ol-f-oid" name="oid" value="<?php if(isset($_REQUEST['oid'])) echo esc_attr($_REQUEST['oid']); ?>">
					</div>
					<div class="wpdmpp-ol__field wpdmpp-ol__field--wide">
						<label class="wpdmpp-ol__label" for="ol-f-customer"><?php _e('Customer','wpdm-premium-packages'); ?></label>
						<input class="form-control" type="text" placeholder="<?php _e("ID, email or username","wpdm-premium-packages");?>" id="ol-f-customer" name="customer" value="<?php if(isset($_REQUEST['customer'])) echo esc_attr($_REQUEST['customer']); ?>">
					</div>
					<div class="wpdmpp-ol__field wpdmpp-ol__field--action">
						<button type="submit" class="btn btn-light" id="doaction" name="doaction"><?php echo Icons::get('search', 14); ?> <?php _e('Search','wpdm-premium-packages'); ?></button>
					</div>
				</div>
			</div>
			<div class="wpdmpp-ol__resultbar">
				<span class="ol-count"><?php echo number_format_i18n($t); ?> <?php _e("renewal(s) found","wpdm-premium-packages");?></span>
				<?php if($show_stats) { ?>
				<span class="ol-total"><?php _e("Total Sales:","wpdm-premium-packages");?> <b><?php echo wpdmpp_price_format($completed->sales, true, true); ?></b></span>
				<?php } ?>
			</div>
		</div>
	</form>
	<div class="clear"></div>
	<form method="get" action="<?php echo admin_url('/edit.php'); ?>" id="orders-form">
		<input type="hidden" name="post_type" value="wpdmpro">
		<input type="hidden" name="page" value="orders">
		<input type="hidden" name="task" value="show_renews">

		<?php if(wpdm_query_var('ost') == 'Expiring'){ ?>
			<div class="panel panel-default ol-expiring">
				<div class="panel-body">
					<ul>
						<li><?php echo Icons::get('check-circle', 16); ?> <?php _e('Update order status to expired','wpdm-premium-packages'); ?></li>
						<li><?php echo Icons::get('check-circle', 16); ?> <?php _e('Send email notification to customers','wpdm-premium-packages'); ?></li>
					</ul>
				</div>
				<div class="wpdmpp-ol__resultbar" style="justify-content:flex-end">
					<a href="#" class="btn wpdmpp-ol__search-btn" id="expire-orders"><?php _e('Execute','wpdm-premium-packages'); ?></a>
				</div>
			</div>
		<?php } ?>

		<div class="wpdmpp-orders-responsive">
		<div class="panel panel-default wpdmpp-orders-tablewrap">
			<table cellspacing="0" class="table table-striped table-wpdmpp">
				<thead>
				<tr>
					<th style="width: 42px" class="manage-column column-cb check-column" scope="col"><input type="checkbox" aria-label="<?php esc_attr_e('Select all renewals','wpdm-premium-packages'); ?>"></th>
					<th class="manage-column" scope="col"><?php _e("Status","wpdm-premium-packages");?></th>
					<th class="manage-column ol-col-order" scope="col"><?php _e("Order","wpdm-premium-packages");?></th>
					<th class="manage-column" scope="col"><?php _e("Total","wpdm-premium-packages");?></th>
					<th class="manage-column" scope="col"><?php _e("Customer","wpdm-premium-packages");?></th>
					<th class="manage-column column-parent" scope="col"><?php _e("Order Date","wpdm-premium-packages");?></th>
					<th class="manage-column" scope="col"><?php _e("Renew Cycle","wpdm-premium-packages");?></th>
					<th class="manage-column" scope="col"><?php _e("Renewed On","wpdm-premium-packages");?></th>
					<th class="manage-column ol-col-icon" scope="col" title="<?php esc_attr_e('Downloaded','wpdm-premium-packages'); ?>"><span class="ol-th-ico" role="img" aria-label="<?php esc_attr_e('Downloaded','wpdm-premium-packages'); ?>"><?php echo Icons::get('download', 16); ?></span></th>
					<th class="manage-column ol-col-icon" scope="col" title="<?php esc_attr_e('Auto-Renew','wpdm-premium-packages'); ?>"><span class="ol-th-ico" role="img" aria-label="<?php esc_attr_e('Auto-Renew','wpdm-premium-packages'); ?>"><?php echo Icons::get('sync', 16); ?></span></th>
					<?php do_action("wpdmpp_orders_custom_column_th"); ?>
				</tr>
				</thead>

				<tbody class="list:post" id="the-list">
				<?php
				$z = 'alternate';
				$mobile_orders = array();
				foreach($orders as $order) {
					$user_info = get_userdata($order->uid);
					$z = $z == 'alternate' ? '' : 'alternate';
					$currency = maybe_unserialize($order->currency);
					$currency = is_array($currency) && isset($currency['sign'])?$currency['sign']:'$';
					$citems = maybe_unserialize($order->cart_data);
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
					$billing = unserialize($order->billing_info);
					$billing = shortcode_atts($sbilling, $billing);
					$items = 0;
					if(is_array($citems)){
						foreach($citems as $ci){
							$items += (int)wpdm_valueof($ci,'quantity');
						}}
					$oitems = maybe_unserialize($order->cart_data);
					$product_name = array();
					if(is_array($oitems)) {
						foreach ($oitems as $oitem) {
							$product_name[] = wpdm_valueof($oitem, 'product_title', wpdm_valueof($oitem, 'post_title'));
						}
					}
					$product_names = implode(", ", $product_name);
					$_product_name = (isset($product_name[0])) ? $product_name[0] : '';
					$order_title = $order->title ? $order->title : $_product_name;
					if($order->expire_date == 0)
						$order->expire_date = $order->date + (get_wpdmpp_option('order_validity_period', 365) * 86400);
					$cycle_text = isset($renew_cycle[$order->order_id]) ? sprintf(__("%d time(s)", 'wpdm-premium-packages'), $renew_cycle[$order->order_id]) : __('First Purchase', 'wpdm-premium-packages');

					$mobile_orders[] = array(
						'id'             => $order->order_id,
						'view_url'       => admin_url( 'edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=' . rawurlencode( $order->order_id ) ),
						'order_status'   => $order->order_status,
						'total'          => wpdmpp_price_format( $order->total, true, true ),
						'payment_method' => str_replace( 'WPDM_', '', $order->payment_method ),
						'items'          => (int) $items,
						'products'       => $product_names,
						'customer'       => is_object( $user_info ) ? $user_info->display_name : trim( $billing['first_name'] . ' ' . $billing['last_name'] ),
						'email'          => is_object( $user_info ) ? $user_info->user_email : $billing['order_email'],
						'order_date'     => wp_date( get_option('date_format') . ' ' . get_option('time_format'), $order->date ),
						'renew_cycle'    => $cycle_text,
						'renewed_on'     => wp_date( get_option('date_format') . ' ' . get_option('time_format'), $order->renew_date ),
						'downloaded'     => (int) $order->download,
						'auto_renew'     => (int) $order->auto_renew,
					);

					?>
					<tr class="<?php echo wpdm_query_var('focus') === $order->order_id ? 'row-focus' : '' ?>">
						<th class="check-column" scope="row" data-label="<?php esc_attr_e('Select','wpdm-premium-packages'); ?>"><input type="checkbox" class="cboid" value="<?php echo esc_attr( $order->order_id ); ?>" name="id[]" aria-label="<?php echo esc_attr( sprintf( __('Select order %s','wpdm-premium-packages'), $order->order_id ) ); ?>"></th>
						<td class="" data-label="<?php esc_attr_e('Status','wpdm-premium-packages'); ?>">
							<span class="ol-pill ol-pill--<?php echo esc_attr($order->order_status); ?>"><?php echo Icons::get($osi[$order->order_status] ?? 'info-circle', 13); ?> <?php echo esc_html($order->order_status); ?></span>
						</td>
						<td class="ol-col-order" data-label="<?php esc_attr_e('Order','wpdm-premium-packages'); ?>">
							<a class="ol-order-id" title="<?php echo esc_attr($order->order_id); ?> — <?php echo esc_attr__( "View Order Details", WPDMPP_TEXT_DOMAIN ) ?>" href="edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=<?php echo esc_attr( $order->order_id ); ?>"><?php echo esc_html( $order->order_id ); ?></a>
							<div class="ol-sub">
								<span class="ol-sub__items"><span class="ttip" title="<?php echo esc_attr($product_names); ?>"><?php echo Icons::get('list', 13); ?></span> <?php echo (int)$items; ?> <?php $items > 1 ? _e("items","wpdm-premium-packages"):_e("item","wpdm-premium-packages");?></span>
								<?php if($order->trans_id !== '') { ?><span class="ol-sub__txn ttip" title="<?php echo esc_attr__( "Transaction ID", WPDMPP_TEXT_DOMAIN ) ?>: <?php echo esc_attr($order->trans_id); ?>"><?php echo Icons::get('bullseye', 13); ?> <?php echo esc_html( apply_filters("wpdmpp_admin_order_details_trans_id", $order->trans_id, $order->payment_method) ); ?></span><?php } ?>
							</div>
						</td>
						<td class="ol-total-cell" data-label="<?php esc_attr_e('Total','wpdm-premium-packages'); ?>">
							<span class="ol-total-amt"><?php echo wpdmpp_price_format($order->total,true, true); ?></span><span class="ol-pay-dot ol-pay-dot--<?php echo esc_attr($order->payment_status); ?> ttip" title="<?php echo esc_attr( sprintf( __('Payment: %s','wpdm-premium-packages'), $order->payment_status ) ); ?>"></span>
							<span class="ol-via"><?php _e('via','wpdm-premium-packages'); echo " ".esc_html(str_replace("WPDM_", "", $order->payment_method)); ?></span>
						</td>
						<td class="" data-label="<?php esc_attr_e('Customer','wpdm-premium-packages'); ?>">
							<?php if(is_object($user_info)){ ?>
								<a class="ol-cust-name" href="edit.php?post_type=wpdmpro&page=customers&view=profile&id=<?php echo (int) $user_info->ID; ?>"><?php echo esc_html( $user_info->display_name ); ?></a>
								<a class="text-filter" title="<?php _e('All orders placed by this customer','wpdm-premium-packages'); ?>" href="edit.php?post_type=wpdmpro&page=orders&customer=<?php echo (int) $user_info->ID; ?>&focus=<?php echo esc_attr( $order->order_id ); ?>"><?php echo Icons::get('search', 13); ?></a><br/>
								<a class="ol-cust-email" href="mailto:<?php echo esc_attr( $user_info->user_email ); ?>"><?php echo esc_html( $user_info->user_email ); ?></a>
							<?php } else { ?>
								<span class="ol-cust-name"><?php echo esc_html( $billing['first_name'].' '.$billing['last_name'] ); ?></span>
								<a class="text-filter" href="edit.php?post_type=wpdmpro&page=orders&customer=<?php echo esc_attr( $billing['order_email'] ); ?>"><?php echo Icons::get('search', 13); ?></a><br/>
								<a class="ol-cust-email" href="mailto:<?php echo esc_attr( $billing['order_email'] ); ?>"><?php echo esc_html( $billing['order_email'] ); ?></a>
							<?php }?>
						</td>
						<td class="ol-date" data-label="<?php esc_attr_e('Order Date','wpdm-premium-packages'); ?>"><span class="ol-date__stack"><span class="ol-date__d"><?php echo wp_date(get_option('date_format'), $order->date); ?></span><span class="ol-date__t"><?php echo wp_date(get_option('time_format'), $order->date); ?></span></span></td>
						<td class="" data-label="<?php esc_attr_e('Renew Cycle','wpdm-premium-packages'); ?>"><span class="ol-cycle"><?php echo Icons::get('sync', 13); ?> <?php echo esc_html( $cycle_text ); ?></span></td>
						<td class="ol-renewed" data-label="<?php esc_attr_e('Renewed On','wpdm-premium-packages'); ?>"><span class="ol-date__stack"><span class="ol-date__d"><?php echo wp_date(get_option('date_format'), $order->renew_date); ?></span><span class="ol-date__t"><?php echo wp_date(get_option('time_format'), $order->renew_date); ?></span></span></td>
						<td class="ol-col-icon" data-label="<?php esc_attr_e('Downloaded','wpdm-premium-packages'); ?>">
							<span class="ol-ind <?php echo $order->download==0?'ol-ind--off':'ol-ind--on'; ?>"><?php echo Icons::get($order->download==0?'close':'check', 15); ?></span>
						</td>
						<td class="ol-col-icon" data-label="<?php esc_attr_e('Auto Renew','wpdm-premium-packages'); ?>">
							<a href="#" class="auto-renew-order" data-order="<?php echo esc_attr( $order->order_id ); ?>">
								<?php echo Icons::statusBadge($order->auto_renew==1?'check':'close', 'renew-'.($order->auto_renew==0?'cancelled':'active'), 14); ?>
							</a>
						</td>
						<?php do_action("wpdmpp_orders_custom_column_td", $order); ?>
					</tr>
				<?php } ?>
				<?php if(empty($orders)){ ?>
					<tr class="ol-empty">
						<td colspan="20">
							<div class="ol-empty__inner">
								<div class="ol-empty__icon"><?php echo Icons::get('sync', 24); ?></div>
								<div class="ol-empty__title"><?php _e('No renewed orders found','wpdm-premium-packages'); ?></div>
								<div class="ol-empty__hint"><?php _e('No renewals match the current filters. Try clearing the search or adjusting the date range.','wpdm-premium-packages'); ?></div>
							</div>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<div id="wpdmpp-renews-mobile"></div>
		</div><!-- /.wpdmpp-orders-responsive -->
		<script>
			window.wpdmppRenewsData = <?php echo wp_json_encode( array(
				'items' => $mobile_orders,
				'l10n'  => array(
					'customer'    => __( 'Customer', 'wpdm-premium-packages' ),
					'items'       => __( 'Items', 'wpdm-premium-packages' ),
					'order_date'  => __( 'Order Date', 'wpdm-premium-packages' ),
					'renew_cycle' => __( 'Renew Cycle', 'wpdm-premium-packages' ),
					'renewed_on'  => __( 'Renewed On', 'wpdm-premium-packages' ),
					'auto_renew'  => __( 'Auto Renew', 'wpdm-premium-packages' ),
					'downloaded'  => __( 'Downloaded', 'wpdm-premium-packages' ),
					'via'         => __( 'via', 'wpdm-premium-packages' ),
					'yes'         => __( 'Yes', 'wpdm-premium-packages' ),
					'no'          => __( 'No', 'wpdm-premium-packages' ),
					'view'        => __( 'View Order', 'wpdm-premium-packages' ),
					'empty'       => __( 'No renewed orders found.', 'wpdm-premium-packages' ),
				),
			) ); ?>;
		</script>
		<script>
			(function () {
				var cfg = window.wpdmppRenewsData || { items: [], l10n: {} };
				if (typeof Vue === 'undefined' || !document.getElementById('wpdmpp-renews-mobile')) return;
				Vue.createApp({
					data: function () { return { orders: cfg.items || [], L: cfg.l10n || {} }; },
					methods: {
						pill: function (s) { return 'wpmo-pill--' + String(s || '').toLowerCase(); }
					},
					template:
						'<div class="wpmo-list">' +
						'  <p v-if="!orders.length" class="wpmo-empty">{{ L.empty }}</p>' +
						'  <div class="wpmo-card" v-for="o in orders" :key="o.id">' +
						'    <div class="wpmo-card__head">' +
						'      <a class="wpmo-card__id" :href="o.view_url">{{ o.id }}</a>' +
						'      <span class="wpmo-pill" :class="pill(o.order_status)">{{ o.order_status }}</span>' +
						'    </div>' +
						'    <div class="wpmo-amount">{{ o.total }} <span class="wpmo-via" v-if="o.payment_method">{{ L.via }} {{ o.payment_method }}</span></div>' +
						'    <div class="wpmo-meta">' +
						'      <div class="wpmo-row"><span class="wpmo-k">{{ L.customer }}</span><span class="wpmo-v"><template v-if="o.customer">{{ o.customer }}<br></template><a v-if="o.email" :href="\'mailto:\' + o.email">{{ o.email }}</a></span></div>' +
						'      <div class="wpmo-row"><span class="wpmo-k">{{ L.order_date }}</span><span class="wpmo-v">{{ o.order_date }}</span></div>' +
						'      <div class="wpmo-row"><span class="wpmo-k">{{ L.renew_cycle }}</span><span class="wpmo-v">{{ o.renew_cycle }}</span></div>' +
						'      <div class="wpmo-row"><span class="wpmo-k">{{ L.renewed_on }}</span><span class="wpmo-v wpmo-on">{{ o.renewed_on }}</span></div>' +
						'      <div class="wpmo-row"><span class="wpmo-k">{{ L.auto_renew }}</span><span class="wpmo-v" :class="o.auto_renew ? \'wpmo-on\' : \'wpmo-off\'">{{ o.auto_renew ? L.yes : L.no }}</span></div>' +
						'      <div class="wpmo-row"><span class="wpmo-k">{{ L.downloaded }}</span><span class="wpmo-v" :class="o.downloaded ? \'wpmo-on\' : \'wpmo-off\'">{{ o.downloaded ? L.yes : L.no }}</span></div>' +
						'    </div>' +
						'    <a class="wpmo-view" :href="o.view_url">{{ L.view }}</a>' +
						'  </div>' +
						'</div>'
				}).mount('#wpdmpp-renews-mobile');
				var wrap = document.querySelector('.wpdmpp-orders-responsive');
				if (wrap) wrap.classList.add('is-vue-ready');
			})();
		</script>
		<?php
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'total' => ceil($t/$l),
			'current' => $p
		));
		?>

		<div id="ajax-response"></div>

		<div class="tablenav">
			<?php
			if ( $page_links ) {
				?>
				<div class="tablenav-pages">
					<?php
					$paged = wpdm_query_var('paged');
					$paged = (int)$paged > 0?$paged:1;
					$page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
						number_format_i18n( ( $paged - 1 ) * $l + 1 ),
						number_format_i18n( min( $paged * $l, $t ) ),
						number_format_i18n( $t ),
						$page_links
					);

					echo $page_links_text; ?>
				</div>
			<?php } ?>
		</div>

	</form>
</div>
<br class="clear">
</div>
