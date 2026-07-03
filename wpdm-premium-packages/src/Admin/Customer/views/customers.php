<?php
/**
 * Customers — admin list.
 *
 * Enterprise admin surface for the "Customers" page
 * (edit.php?post_type=wpdmpro&page=customers).
 *
 * Design language is shared with the Orders list (.wpdmpp-ol): a scoped
 * .wpdmpp-cl wrapper, KPI strip, filter toolbar, data table, empty state,
 * styled pagination and a Vue mobile-card fallback.
 *
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use WPDMPP\UI\Icons;
use WPDMPP\Customer\CustomerService;

global $wpdb;

$limit = 20;
$page  = (int) wpdm_query_var( 'paged', 'int' );
$page  = $page > 0 ? $page : 1;
$start = ( $page - 1 ) * $limit;

$search     = wpdm_query_var( 'search', 'txt' );
$product_id = (int) wpdm_query_var( 'product', 'int' );
$has_filter = ( $search !== '' ) || ( $product_id > 0 );

// --- Sort (whitelisted) ---------------------------------------------------
$sort_map = array(
	'name'       => 'u.display_name',
	'registered' => 'u.user_registered',
	'orders'     => 'order_count',
	'spent'      => 'total_spent',
	'last'       => 'last_order',
);
$orderby_input = wpdm_query_var( 'orderby', 'txt' );
$orderby_sql   = isset( $sort_map[ $orderby_input ] ) ? $sort_map[ $orderby_input ] : 'last_order';
$order_dir     = strtolower( wpdm_query_var( 'order', 'txt' ) ) === 'asc' ? 'ASC' : 'DESC';
$active_sort   = isset( $sort_map[ $orderby_input ] ) ? $orderby_input : 'last';

// --- Build query ----------------------------------------------------------
$base_prefix = $wpdb->prefix;
$where       = array( "(o.order_status = 'Completed' OR o.order_status = 'Expired')", 'o.uid > 0' );
$where_args  = array();

if ( $search !== '' ) {
	$like         = '%' . $wpdb->esc_like( $search ) . '%';
	$where[]      = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.user_nicename LIKE %s OR u.display_name LIKE %s OR u.ID = %d)';
	$where_args[] = $like;
	$where_args[] = $like;
	$where_args[] = $like;
	$where_args[] = $like;
	$where_args[] = absint( $search );
}

if ( $product_id > 0 ) {
	// EXISTS (not JOIN) so an order with multiple line items isn't multiplied,
	// which would otherwise inflate COUNT()/SUM() in the GROUP BY below.
	$where[]      = "EXISTS (SELECT 1 FROM {$base_prefix}ahm_order_items oi WHERE oi.oid = o.order_id AND oi.pid = %d)";
	$where_args[] = $product_id;
}

$where_sql = 'WHERE ' . implode( ' AND ', $where );

// Per-customer renewal revenue, pre-aggregated to one row per uid so the LEFT
// JOIN never multiplies order rows.
$renew_join = "LEFT JOIN (
		SELECT o2.uid AS uid, SUM(rr.total) AS renew_total
		FROM {$base_prefix}ahm_orders o2
		INNER JOIN {$base_prefix}ahm_order_renews rr ON o2.order_id = rr.order_id
		GROUP BY o2.uid
	) r ON r.uid = o.uid";

$list_sql = "SELECT
		o.uid AS uid,
		u.ID AS user_id,
		u.display_name AS display_name,
		u.user_email AS user_email,
		u.user_registered AS user_registered,
		COUNT(DISTINCT o.order_id) AS order_count,
		SUM(CASE WHEN o.order_status IN ('Completed', 'Expired') THEN o.total ELSE 0 END) + MAX(COALESCE(r.renew_total, 0)) AS total_spent,
		MAX(o.date) AS last_order,
		MIN(o.date) AS first_order
	FROM {$base_prefix}ahm_orders o
	LEFT JOIN {$base_prefix}users u ON o.uid = u.ID
	{$renew_join}
	{$where_sql}
	GROUP BY o.uid, u.ID, u.display_name, u.user_email, u.user_registered
	ORDER BY {$orderby_sql} {$order_dir}
	LIMIT %d, %d";

$list_args     = array_merge( $where_args, array( $start, $limit ) );
$all_customers = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ) );

$count_sql = "SELECT COUNT(DISTINCT o.uid)
	FROM {$base_prefix}ahm_orders o
	LEFT JOIN {$base_prefix}users u ON o.uid = u.ID
	{$where_sql}";
$total_customers = (int) ( $where_args
	? $wpdb->get_var( $wpdb->prepare( $count_sql, $where_args ) )
	: $wpdb->get_var( $count_sql ) );

// --- KPIs (unfiltered only) ----------------------------------------------
$kpi = null;
if ( ! $has_filter ) {
	$cache_key = 'wpdmpp_customer_kpi_' . wp_date( 'Y-m-d-H' );
	$kpi       = get_transient( $cache_key );

	if ( false === $kpi ) {
		// All figures on the same basis as the table rows (completed + expired
		// purchases) so the headline revenue reconciles with the Total Spent column.
		$agg = $wpdb->get_row(
			"SELECT
				COUNT(*) AS paying,
				SUM(CASE WHEN oc > 1 THEN 1 ELSE 0 END) AS repeaters,
				COALESCE(SUM(spent), 0) AS order_revenue,
				COALESCE(SUM(oc), 0) AS paid_orders
			FROM (
				SELECT uid, COUNT(DISTINCT order_id) AS oc, SUM(total) AS spent
				FROM {$base_prefix}ahm_orders
				WHERE (order_status = 'Completed' OR order_status = 'Expired') AND uid > 0
				GROUP BY uid
			) t"
		);

		$renew_revenue = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(rr.total), 0)
			FROM {$base_prefix}ahm_order_renews rr
			INNER JOIN {$base_prefix}ahm_orders o ON o.order_id = rr.order_id
			WHERE o.uid > 0"
		);

		$stats = CustomerService::getInstance()->getStatistics();

		$paying      = (int) ( $agg->paying ?? 0 );
		$repeat      = (int) ( $agg->repeaters ?? 0 );
		$paid_orders = (int) ( $agg->paid_orders ?? 0 );
		$order_rev   = (float) ( $agg->order_revenue ?? 0 );
		$revenue     = $order_rev + $renew_revenue;

		$kpi = array(
			'paying'      => $paying,
			'repeat'      => $repeat,
			'repeat_rate' => $paying > 0 ? round( ( $repeat / $paying ) * 100 ) : 0,
			'new_month'   => (int) ( $stats['new_this_month'] ?? 0 ),
			'growth'      => (float) ( $stats['growth_percentage'] ?? 0 ),
			'revenue'     => $revenue,
			'aov'         => $paid_orders > 0 ? $order_rev / $paid_orders : 0,
			'ltv'         => $paying > 0 ? $revenue / $paying : 0,
		);

		set_transient( $cache_key, $kpi, 5 * MINUTE_IN_SECONDS );
	}
}

// --- Sort link helper -----------------------------------------------------
$sort_link = static function ( $key, $label, $numeric = false ) use ( $active_sort, $order_dir ) {
	$is_active = ( $active_sort === $key );
	// Numeric columns feel right defaulting to highest-first.
	$next = $is_active ? ( $order_dir === 'ASC' ? 'desc' : 'asc' ) : ( $numeric ? 'desc' : 'asc' );
	$url  = esc_url( add_query_arg( array( 'orderby' => $key, 'order' => $next, 'paged' => 1 ) ) );
	$caret = '';
	if ( $is_active ) {
		$caret = '<span class="cl-caret">' . ( $order_dir === 'ASC' ? '&#9650;' : '&#9660;' ) . '</span>';
	}
	return '<a href="' . $url . '" class="cl-sort' . ( $is_active ? ' is-active' : '' ) . '">' . esc_html( $label ) . ' ' . $caret . '</a>';
};

// aria-sort attribute for sortable column headers.
$aria_sort = static function ( $key ) use ( $active_sort, $order_dir ) {
	if ( $active_sort !== $key ) {
		return ' aria-sort="none"';
	}
	return ' aria-sort="' . ( $order_dir === 'ASC' ? 'ascending' : 'descending' ) . '"';
};

$base_url     = 'edit.php?post_type=wpdmpro&page=customers';
$recalc_nonce = wp_create_nonce( WPDM_PRI_NONCE );

?>
<div class="w3eden">
	<?php
	$menus = array(
		array( 'link' => $base_url, 'name' => __( 'All Customers', 'wpdm-premium-packages' ), 'active' => true ),
	);

	$actions = array();
	if ( ! $has_filter && $kpi && $kpi['paying'] > 0 ) {
		$actions[] = array(
			'type'  => 'button',
			'name'  => Icons::get( 'sync', 14 ) . ' ' . esc_html__( 'Recalculate values', 'wpdm-premium-packages' ),
			'class' => 'default btn-simple',
			'attrs' => array(
				'id'        => 'wpdmpp-cl-recalc',
				'data-nonce' => $recalc_nonce,
			),
		);
	}

	WPDM()->admin->pageHeader( esc_attr__( 'Customers', 'wpdm-premium-packages' ), 'user-graduate fas color-purple', $menus, $actions );
	?>

	<div class="wpdm-admin-page-content">
		<div class="wpdmpp-cl">
			<style>
			/* ==========================================================================
			   Customers list — enterprise admin surface. Scoped under .wpdmpp-cl so it
			   never leaks into other admin screens. Tokens fall back to literals so the
			   design holds even where the WPDM theme variables aren't defined.
			   ========================================================================== */
			.wpdmpp-cl{
				--cl-surface:var(--color-bg-card,#fff);
				--cl-surface-2:#f8fafc;
				--cl-text:var(--color-text,#0f172a);
				--cl-muted:var(--color-muted,#64748b);
				--cl-faint:#94a3b8;
				--cl-border:var(--color-border,#e7eaef);
				--cl-border-soft:#eef1f5;
				--cl-primary:var(--color-primary,#4f46e5);
				--cl-primary-rgb:var(--color-primary-rgb,79,70,229);
				--cl-radius:14px;
				--cl-radius-sm:9px;
				--cl-shadow-sm:0 1px 2px rgba(15,23,42,.05);
				--cl-font:var(--wpdm-font,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Inter,Helvetica,Arial,sans-serif);
				font-family:var(--cl-font);
				color:var(--cl-text);
				font-size:14px;
				line-height:1.5;
			}
			.wpdmpp-cl *{box-sizing:border-box;}
			.wpdmpp-cl a{color:var(--cl-primary);text-decoration:none;}
			.wpdmpp-cl a:hover{text-decoration:underline;}

			/* --- KPI strip ----------------------------------------------------------- */
			.wpdmpp-cl__kpis{
				display:grid;
				grid-template-columns:repeat(auto-fit,minmax(196px,1fr));
				gap:14px;
				margin:0 0 20px;
			}
			.wpdmpp-cl__kpi{
				background:var(--cl-surface);
				border:1px solid var(--cl-border);
				border-radius:16px;
				padding:18px 18px 16px;
				box-shadow:0 1px 2px rgba(15,23,42,.04);
			}
			.wpdmpp-cl__kpi-top{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
			.wpdmpp-cl__kpi-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:11px;flex:0 0 auto;background:#f1f5f9;color:var(--cl-faint);}
			.wpdmpp-cl__kpi-icon svg{width:19px;height:19px;}
			.wpdmpp-cl__kpi-label{font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--cl-muted);}
			.wpdmpp-cl__kpi-value{display:block;font-size:25px;line-height:1.1;font-weight:700;letter-spacing:-.025em;color:var(--cl-text);font-variant-numeric:tabular-nums;}
			.wpdmpp-cl__kpi-meta{display:flex;align-items:center;gap:7px;margin-top:9px;font-size:12.5px;color:var(--cl-muted);font-variant-numeric:tabular-nums;}
			.wpdmpp-cl__kpi-meta b{color:var(--cl-muted);font-weight:600;}
			.wpdmpp-cl__kpi-dot{width:7px;height:7px;border-radius:50%;flex:0 0 auto;background:var(--cl-faint);}
			.wpdmpp-cl__kpi-trend{display:inline-flex;align-items:center;gap:3px;font-weight:600;}
			.wpdmpp-cl__kpi-trend svg{width:14px;height:14px;}
			.wpdmpp-cl__kpi-trend--up{color:#059669;}
			.wpdmpp-cl__kpi-trend--down{color:#e11d48;}
			.wpdmpp-cl__kpi-trend--flat{color:var(--cl-muted);}
			.wpdmpp-cl__kpi--indigo .wpdmpp-cl__kpi-icon{background:#eef2ff;color:#4f46e5;}
			.wpdmpp-cl__kpi--indigo .wpdmpp-cl__kpi-dot{background:#6366f1;}
			.wpdmpp-cl__kpi--sky .wpdmpp-cl__kpi-icon{background:#e0f2fe;color:#0284c7;}
			.wpdmpp-cl__kpi--sky .wpdmpp-cl__kpi-dot{background:#0ea5e9;}
			.wpdmpp-cl__kpi--success .wpdmpp-cl__kpi-icon{background:#ecfdf5;color:#059669;}
			.wpdmpp-cl__kpi--success .wpdmpp-cl__kpi-dot{background:#10b981;}
			.wpdmpp-cl__kpi--teal .wpdmpp-cl__kpi-icon{background:#f0fdfa;color:#0d9488;}
			.wpdmpp-cl__kpi--teal .wpdmpp-cl__kpi-dot{background:#14b8a6;}
			.wpdmpp-cl__kpi--violet .wpdmpp-cl__kpi-icon{background:#f5f3ff;color:#7c3aed;}
			.wpdmpp-cl__kpi--violet .wpdmpp-cl__kpi-dot{background:#8b5cf6;}
			.wpdmpp-cl__kpi--rose .wpdmpp-cl__kpi-icon{background:#fff1f2;color:#e11d48;}
			.wpdmpp-cl__kpi--rose .wpdmpp-cl__kpi-dot{background:#f43f5e;}

			/* --- Surfaces ------------------------------------------------------------ */
			.wpdmpp-cl__panel{
				background:var(--cl-surface);
				border:1px solid var(--cl-border);
				border-radius:var(--cl-radius);
				box-shadow:var(--cl-shadow-sm);
				margin-bottom:16px;
				overflow:hidden;
			}
			.wpdmpp-cl__panel-body{padding:16px 18px;}

			/* --- Filter toolbar ------------------------------------------------------ */
			.wpdmpp-cl__toolbar{display:flex;flex-wrap:wrap;align-items:end;gap:12px 14px;}
			.wpdmpp-cl__field{display:flex;flex-direction:column;gap:6px;min-width:0;flex:1 1 260px;}
			.wpdmpp-cl__field--action{flex:0 0 auto;}
			.wpdmpp-cl__label{font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--cl-muted);}
			.wpdmpp-cl__search{position:relative;display:flex;align-items:center;}
			.wpdmpp-cl__search svg{position:absolute;left:12px;color:var(--cl-faint);pointer-events:none;}
			.wpdmpp-cl__search input.form-control{padding-left:36px;}
			.wpdmpp-cl__btn--ghost{background:var(--cl-surface);color:var(--cl-muted);border-color:var(--cl-border);}
			.wpdmpp-cl__btn--ghost:hover{background:var(--cl-surface-2);color:var(--cl-text);filter:none;}

			/* active-filter chip */
			.wpdmpp-cl__chips{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0 0 14px;}
			.wpdmpp-cl__chip{
				display:inline-flex;align-items:center;gap:8px;
				padding:5px 8px 5px 12px;border-radius:999px;font-size:12.5px;font-weight:600;
				background:rgba(var(--cl-primary-rgb),.08);color:var(--cl-primary);border:1px solid rgba(var(--cl-primary-rgb),.18);
			}
			.wpdmpp-cl__chip a{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;color:inherit;background:rgba(var(--cl-primary-rgb),.14);}
			.wpdmpp-cl__chip a:hover{background:var(--cl-primary);color:#fff;text-decoration:none;}
			.wpdmpp-cl__chip svg{width:12px;height:12px;}

			/* result meta bar */
			.wpdmpp-cl__resultbar{
				display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
				padding:11px 18px;border-top:1px solid var(--cl-border-soft);
				background:var(--cl-surface-2);
				font-size:13px;color:var(--cl-muted);
			}
			.wpdmpp-cl__resultbar .cl-count{font-weight:600;color:var(--cl-text);font-variant-numeric:tabular-nums;}
			.wpdmpp-cl__resultbar .cl-total{font-variant-numeric:tabular-nums;}
			.wpdmpp-cl__resultbar .cl-total b{color:#15803d;}

			/* --- Table --------------------------------------------------------------- */
			.wpdmpp-cl__tablewrap{width:100%;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;padding:0;}
			.wpdmpp-cl table.cl-table{width:100%;min-width:880px;border-collapse:separate;border-spacing:0;margin:0;background:var(--cl-surface);}
			.wpdmpp-cl table.cl-table thead th{
				position:sticky;top:0;z-index:2;
				background:var(--cl-surface-2);
				padding:11px 16px;text-align:left;vertical-align:middle;
				font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;
				color:var(--cl-muted);white-space:nowrap;
				border-bottom:1px solid var(--cl-border);
			}
			.wpdmpp-cl table.cl-table thead th.cl-num{text-align:right;}
			.wpdmpp-cl .cl-sort{color:var(--cl-muted);font-weight:600;display:inline-flex;align-items:center;gap:4px;}
			.wpdmpp-cl .cl-sort:hover{color:var(--cl-text);text-decoration:none;}
			.wpdmpp-cl .cl-sort.is-active{color:var(--cl-text);}
			.wpdmpp-cl .cl-caret{font-size:9px;line-height:1;color:var(--cl-primary);}
			.wpdmpp-cl table.cl-table tbody td{
				padding:12px 16px;vertical-align:middle;
				border-bottom:1px solid var(--cl-border-soft);
				color:var(--cl-text);font-size:13.5px;
			}
			.wpdmpp-cl table.cl-table tbody td.cl-num{text-align:right;font-variant-numeric:tabular-nums;}
			.wpdmpp-cl table.cl-table tbody tr{transition:background .12s ease;}
			.wpdmpp-cl table.cl-table tbody tr:hover td{background:rgba(var(--cl-primary-rgb),.035);}
			.wpdmpp-cl table.cl-table tbody tr:last-child td{border-bottom:0;}

			/* customer identity cell */
			.wpdmpp-cl__cust{display:flex;align-items:center;gap:12px;min-width:0;}
			.wpdmpp-cl__avatar{flex:0 0 auto;width:40px;height:40px;border-radius:50%;overflow:hidden;background:var(--cl-surface-2);display:inline-flex;align-items:center;justify-content:center;}
			.wpdmpp-cl__avatar img{width:100%;height:100%;display:block;border-radius:50%;}
			.wpdmpp-cl__avatar--ph{font-weight:700;font-size:15px;color:#fff;letter-spacing:.01em;}
			.wpdmpp-cl__cust-main{min-width:0;}
			.wpdmpp-cl__cust-name{font-weight:600;font-size:14px;color:var(--cl-text);display:inline-flex;align-items:center;gap:7px;}
			.wpdmpp-cl__cust-name a{color:var(--cl-text);}
			.wpdmpp-cl__cust-name a:hover{color:var(--cl-primary);}
			.wpdmpp-cl__id{font-size:11px;font-weight:600;color:var(--cl-faint);background:var(--cl-surface-2);border:1px solid var(--cl-border-soft);padding:1px 6px;border-radius:6px;font-variant-numeric:tabular-nums;}
			.wpdmpp-cl__cust-email{display:block;margin-top:2px;font-size:12.5px;color:var(--cl-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:280px;}
			.wpdmpp-cl__cust-email a{color:var(--cl-muted);}
			.wpdmpp-cl__deleted{color:var(--cl-faint);font-style:italic;}

			.wpdmpp-cl .cl-date__stack{display:inline-flex;flex-direction:column;line-height:1.3;}
			.wpdmpp-cl .cl-date__d{white-space:nowrap;}
			.wpdmpp-cl .cl-date__t{font-size:11.5px;color:var(--cl-muted);white-space:nowrap;}
			.wpdmpp-cl .cl-muted{color:var(--cl-faint);}

			.wpdmpp-cl .cl-orders-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:12.5px;font-weight:600;background:#f1f5f9;color:#475569;font-variant-numeric:tabular-nums;}
			.wpdmpp-cl .cl-orders-pill svg{width:13px;height:13px;opacity:.7;}
			.wpdmpp-cl .cl-spent{font-weight:700;font-size:14px;color:#15803d;white-space:nowrap;}
			.wpdmpp-cl .cl-spent--zero{color:var(--cl-faint);font-weight:600;}

			/* row actions */
			.wpdmpp-cl__actions{display:inline-flex;align-items:center;gap:6px;justify-content:flex-end;}
			.wpdmpp-cl__act{
				display:inline-flex;align-items:center;justify-content:center;
				width:30px;height:30px;border-radius:8px;flex:0 0 auto;
				background:var(--cl-surface);border:1px solid var(--cl-border);color:var(--cl-muted);
				transition:background .12s ease,border-color .12s ease,color .12s ease;
			}
			.wpdmpp-cl__act svg{width:15px;height:15px;}
			.wpdmpp-cl__act:hover{background:rgba(var(--cl-primary-rgb),.08);border-color:rgba(var(--cl-primary-rgb),.32);color:var(--cl-primary);text-decoration:none;}
			.wpdmpp-cl__act--primary{background:var(--cl-primary);border-color:var(--cl-primary);color:#fff;}
			.wpdmpp-cl__act--primary:hover{background:var(--cl-primary);color:#fff;filter:brightness(1.07);}

			/* empty state */
			.wpdmpp-cl .cl-empty td{padding:0;border:0;}
			.wpdmpp-cl .cl-empty__inner{display:flex;flex-direction:column;align-items:center;gap:10px;padding:56px 20px;text-align:center;}
			.wpdmpp-cl .cl-empty__icon{display:flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:15px;background:var(--cl-surface-2);color:var(--cl-faint);}
			.wpdmpp-cl .cl-empty__icon svg{width:25px;height:25px;}
			.wpdmpp-cl .cl-empty__title{font-size:15px;font-weight:600;color:var(--cl-text);}
			.wpdmpp-cl .cl-empty__hint{font-size:13px;color:var(--cl-muted);max-width:380px;}

			/* recalc progress */
			.wpdmpp-cl__recalc{display:none;align-items:center;gap:14px;margin:0 0 16px;padding:14px 18px;background:var(--cl-surface);border:1px solid var(--cl-border);border-radius:var(--cl-radius);box-shadow:var(--cl-shadow-sm);}
			.wpdmpp-cl__recalc.is-active{display:flex;}
			.wpdmpp-cl__recalc-spin{flex:0 0 auto;color:var(--cl-primary);display:inline-flex;}
			.wpdmpp-cl__recalc-spin svg{width:20px;height:20px;}
			.wpdmpp-cl__recalc-body{flex:1 1 auto;min-width:0;}
			.wpdmpp-cl__recalc-label{font-size:13px;font-weight:600;color:var(--cl-text);margin-bottom:7px;display:flex;justify-content:space-between;gap:10px;}
			.wpdmpp-cl__recalc-label .cl-pct{color:var(--cl-muted);font-variant-numeric:tabular-nums;}
			.wpdmpp-cl__recalc-track{height:7px;border-radius:999px;background:var(--cl-border-soft);overflow:hidden;}
			.wpdmpp-cl__recalc-bar{height:100%;width:0;border-radius:999px;background:linear-gradient(90deg,var(--cl-primary),#7c3aed);transition:width .35s ease;}

			/* pagination (wpdm_paginate_links → ul.pagination > li > a.page-numbers) */
			.wpdmpp-cl .pagination{display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin:18px 0 4px;padding:0;list-style:none;justify-content:center;}
			.wpdmpp-cl .pagination li{margin:0;list-style:none;}
			.wpdmpp-cl .pagination .page-numbers{
				display:inline-flex;align-items:center;justify-content:center;
				min-width:34px;height:34px;padding:0 10px;
				border:1px solid var(--cl-border);border-radius:9px;
				background:var(--cl-surface);color:var(--cl-text);
				font-size:13px;font-weight:600;text-decoration:none;
				transition:background .12s ease,border-color .12s ease,color .12s ease;
			}
			.wpdmpp-cl .pagination .page-numbers:hover{background:var(--cl-surface-2);border-color:var(--cl-faint);text-decoration:none;}
			.wpdmpp-cl .pagination .page-numbers.current{background:var(--cl-primary);border-color:var(--cl-primary);color:#fff;}
			.wpdmpp-cl .pagination .page-numbers.dots{border-color:transparent;background:transparent;}

			/* ======================================================================
			   Mobile — swap the table for Vue-rendered cards.
			   ====================================================================== */
			#wpdmpp-customers-mobile{display:none;}
			.wpcm-list{display:flex;flex-direction:column;gap:12px;}
			.wpcm-card{background:var(--cl-surface,#fff);border:1px solid var(--cl-border,#e7eaef);border-radius:14px;padding:15px 16px;box-shadow:0 1px 2px rgba(15,23,42,.05);}
			.wpcm-head{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
			.wpcm-av{flex:0 0 auto;width:42px;height:42px;border-radius:50%;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;font-weight:700;color:#fff;}
			.wpcm-av img{width:100%;height:100%;border-radius:50%;display:block;}
			.wpcm-name{font-weight:700;font-size:15px;color:var(--cl-text,#0f172a);}
			.wpcm-name a{color:inherit;text-decoration:none;}
			.wpcm-email{font-size:12.5px;color:var(--cl-muted,#64748b);word-break:break-all;}
			.wpcm-meta{display:flex;flex-direction:column;gap:9px;padding-top:11px;border-top:1px solid var(--cl-border-soft,#eef1f5);}
			.wpcm-row{display:flex;align-items:center;justify-content:space-between;gap:14px;font-size:13px;}
			.wpcm-k{color:var(--cl-muted,#64748b);font-weight:600;}
			.wpcm-v{text-align:right;color:var(--cl-text,#0f172a);font-variant-numeric:tabular-nums;}
			.wpcm-v.spent{color:#15803d;font-weight:700;}
			.wpcm-view{display:block;text-align:center;margin-top:14px;padding:10px 16px;border-radius:9px;background:var(--cl-primary,#4f46e5);color:#fff !important;font-weight:600;text-decoration:none;}
			.wpcm-empty{padding:34px;text-align:center;color:var(--cl-muted,#64748b);}

			@media screen and (max-width:782px){
				.wpdmpp-cl__field,.wpdmpp-cl__field--action{flex:1 1 100%;}
				.wpdmpp-cl__btn{width:100%;justify-content:center;}
				.wpdmpp-cl table.cl-table{min-width:0;}
				.wpdmpp-cl__customers-responsive.is-vue-ready .wpdmpp-cl__tablewrap{display:none;}
				.wpdmpp-cl__customers-responsive.is-vue-ready #wpdmpp-customers-mobile{display:block;}
				.wpdmpp-cl__customers-responsive:not(.is-vue-ready) .cl-table thead{display:none;}
				.wpdmpp-cl__customers-responsive:not(.is-vue-ready) .cl-table tbody,
				.wpdmpp-cl__customers-responsive:not(.is-vue-ready) .cl-table tr,
				.wpdmpp-cl__customers-responsive:not(.is-vue-ready) .cl-table td{display:block;width:100% !important;box-sizing:border-box;}
				.wpdmpp-cl__customers-responsive:not(.is-vue-ready) .cl-table tr{margin:0 0 14px;padding:6px 14px;border:1px solid var(--cl-border,#e7eaef);border-radius:10px;background:var(--cl-surface,#fff);}
				.wpdmpp-cl__customers-responsive:not(.is-vue-ready) .cl-table td{display:flex;align-items:center;justify-content:space-between;gap:12px;text-align:right;padding:9px 0;border-bottom:1px solid var(--cl-border-soft,#eef1f5);}
				.wpdmpp-cl__customers-responsive:not(.is-vue-ready) .cl-table td:last-child{border-bottom:0;}
				.wpdmpp-cl__customers-responsive:not(.is-vue-ready) .cl-table td::before{content:attr(data-label);font-weight:600;color:var(--cl-muted,#64748b);text-align:left;margin-right:auto;}
			}
			@media (prefers-reduced-motion:reduce){
				.wpdmpp-cl *{transition:none !important;}
				.wpdmpp-cl .wpdmpp-spin{animation:none !important;}
			}
			.wpdmpp-cl .wpdmpp-spin{animation:wpdmpp-cl-spin .8s linear infinite;}
			@keyframes wpdmpp-cl-spin{to{transform:rotate(360deg);}}
			</style>

			<?php if ( ! $has_filter && $kpi ) { ?>
				<div class="wpdmpp-cl__kpis">
					<div class="wpdmpp-cl__kpi wpdmpp-cl__kpi--indigo">
						<div class="wpdmpp-cl__kpi-top"><span class="wpdmpp-cl__kpi-icon"><?php echo Icons::get( 'users', 19 ); ?></span><span class="wpdmpp-cl__kpi-label"><?php esc_html_e( 'Customers', 'wpdm-premium-packages' ); ?></span></div>
						<span class="wpdmpp-cl__kpi-value"><?php echo esc_html( number_format_i18n( $kpi['paying'] ) ); ?></span>
						<span class="wpdmpp-cl__kpi-meta"><span class="wpdmpp-cl__kpi-dot"></span><b><?php echo esc_html( number_format_i18n( $kpi['repeat'] ) ); ?></b> <?php esc_html_e( 'returning', 'wpdm-premium-packages' ); ?></span>
					</div>
					<div class="wpdmpp-cl__kpi wpdmpp-cl__kpi--sky">
						<div class="wpdmpp-cl__kpi-top"><span class="wpdmpp-cl__kpi-icon"><?php echo Icons::get( 'user-plus', 19 ); ?></span><span class="wpdmpp-cl__kpi-label"><?php esc_html_e( 'New This Month', 'wpdm-premium-packages' ); ?></span></div>
						<span class="wpdmpp-cl__kpi-value"><?php echo esc_html( number_format_i18n( $kpi['new_month'] ) ); ?></span>
						<span class="wpdmpp-cl__kpi-meta">
							<?php if ( $kpi['growth'] > 0 ) { ?>
								<span class="wpdmpp-cl__kpi-trend wpdmpp-cl__kpi-trend--up"><?php echo Icons::get( 'trending-up', 14 ); ?> +<?php echo esc_html( $kpi['growth'] ); ?>%</span>
							<?php } elseif ( $kpi['growth'] < 0 ) { ?>
								<span class="wpdmpp-cl__kpi-trend wpdmpp-cl__kpi-trend--down"><?php echo Icons::get( 'trending-down', 14 ); ?> <?php echo esc_html( $kpi['growth'] ); ?>%</span>
							<?php } else { ?>
								<span class="wpdmpp-cl__kpi-trend wpdmpp-cl__kpi-trend--flat"><?php echo esc_html( $kpi['growth'] ); ?>%</span>
							<?php } ?>
							<?php esc_html_e( 'vs last month', 'wpdm-premium-packages' ); ?>
						</span>
					</div>
					<div class="wpdmpp-cl__kpi wpdmpp-cl__kpi--success">
						<div class="wpdmpp-cl__kpi-top"><span class="wpdmpp-cl__kpi-icon"><?php echo Icons::get( 'dollar-sign', 19 ); ?></span><span class="wpdmpp-cl__kpi-label"><?php esc_html_e( 'Lifetime Revenue', 'wpdm-premium-packages' ); ?></span></div>
						<span class="wpdmpp-cl__kpi-value"><?php echo wp_kses_post( wpdmpp_price_format( $kpi['revenue'], true, true ) ); ?></span>
						<span class="wpdmpp-cl__kpi-meta"><span class="wpdmpp-cl__kpi-dot"></span><?php esc_html_e( 'from completed orders', 'wpdm-premium-packages' ); ?></span>
					</div>
					<div class="wpdmpp-cl__kpi wpdmpp-cl__kpi--teal">
						<div class="wpdmpp-cl__kpi-top"><span class="wpdmpp-cl__kpi-icon"><?php echo Icons::get( 'shopping-bag', 19 ); ?></span><span class="wpdmpp-cl__kpi-label"><?php esc_html_e( 'Avg Order Value', 'wpdm-premium-packages' ); ?></span></div>
						<span class="wpdmpp-cl__kpi-value"><?php echo wp_kses_post( wpdmpp_price_format( $kpi['aov'], true, true ) ); ?></span>
						<span class="wpdmpp-cl__kpi-meta"><span class="wpdmpp-cl__kpi-dot"></span><?php esc_html_e( 'per completed order', 'wpdm-premium-packages' ); ?></span>
					</div>
					<div class="wpdmpp-cl__kpi wpdmpp-cl__kpi--violet">
						<div class="wpdmpp-cl__kpi-top"><span class="wpdmpp-cl__kpi-icon"><?php echo Icons::get( 'star', 19 ); ?></span><span class="wpdmpp-cl__kpi-label"><?php esc_html_e( 'Avg Customer Value', 'wpdm-premium-packages' ); ?></span></div>
						<span class="wpdmpp-cl__kpi-value"><?php echo wp_kses_post( wpdmpp_price_format( $kpi['ltv'], true, true ) ); ?></span>
						<span class="wpdmpp-cl__kpi-meta"><span class="wpdmpp-cl__kpi-dot"></span><?php esc_html_e( 'lifetime per customer', 'wpdm-premium-packages' ); ?></span>
					</div>
					<div class="wpdmpp-cl__kpi wpdmpp-cl__kpi--rose">
						<div class="wpdmpp-cl__kpi-top"><span class="wpdmpp-cl__kpi-icon"><?php echo Icons::get( 'sync', 19 ); ?></span><span class="wpdmpp-cl__kpi-label"><?php esc_html_e( 'Repeat Rate', 'wpdm-premium-packages' ); ?></span></div>
						<span class="wpdmpp-cl__kpi-value"><?php echo esc_html( $kpi['repeat_rate'] ); ?>%</span>
						<span class="wpdmpp-cl__kpi-meta"><span class="wpdmpp-cl__kpi-dot"></span><b><?php echo esc_html( number_format_i18n( $kpi['repeat'] ) ); ?></b> <?php esc_html_e( 'of', 'wpdm-premium-packages' ); ?> <?php echo esc_html( number_format_i18n( $kpi['paying'] ) ); ?></span>
					</div>
				</div>
			<?php } ?>

			<div class="wpdmpp-cl__recalc" id="wpdmpp-cl-recalc-progress">
				<span class="wpdmpp-cl__recalc-spin"><?php echo Icons::spinner( 20 ); ?></span>
				<div class="wpdmpp-cl__recalc-body">
					<div class="wpdmpp-cl__recalc-label" aria-live="polite"><span><?php esc_html_e( 'Recalculating customer values…', 'wpdm-premium-packages' ); ?></span><span class="cl-pct">0%</span></div>
					<div class="wpdmpp-cl__recalc-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="wpdmpp-cl__recalc-bar"></div></div>
				</div>
			</div>

			<?php if ( $has_filter ) { ?>
				<div class="wpdmpp-cl__chips">
					<?php if ( $search !== '' ) { ?>
						<span class="wpdmpp-cl__chip">
							<?php echo Icons::get( 'search', 12 ); ?>
							<?php echo esc_html( $search ); ?>
							<a href="<?php echo esc_url( remove_query_arg( array( 'search', 'paged' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Clear search', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get( 'close', 12 ); ?></a>
						</span>
					<?php } ?>
					<?php if ( $product_id > 0 ) { $pname = get_the_title( $product_id ); ?>
						<span class="wpdmpp-cl__chip">
							<?php echo Icons::get( 'shopping-bag', 12 ); ?>
							<?php echo esc_html( $pname !== '' ? $pname : ( '#' . $product_id ) ); ?>
							<a href="<?php echo esc_url( remove_query_arg( array( 'product', 'paged' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Clear product filter', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get( 'close', 12 ); ?></a>
						</span>
					<?php } ?>
				</div>
			<?php } ?>

			<div class="wpdmpp-cl__main">
				<form method="get" action="" class="wpdmpp-cl__panel" role="search">
					<input type="hidden" name="post_type" value="wpdmpro">
					<input type="hidden" name="page" value="customers">
					<?php if ( $product_id > 0 ) { ?><input type="hidden" name="product" value="<?php echo esc_attr( $product_id ); ?>"><?php } ?>
					<?php if ( isset( $sort_map[ $orderby_input ] ) ) { ?><input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby_input ); ?>"><input type="hidden" name="order" value="<?php echo esc_attr( strtolower( $order_dir ) ); ?>"><?php } ?>
					<div class="wpdmpp-cl__panel-body">
						<div class="wpdmpp-cl__toolbar">
							<div class="wpdmpp-cl__field">
								<label class="wpdmpp-cl__label" for="cl-search"><?php esc_html_e( 'Find a customer', 'wpdm-premium-packages' ); ?></label>
								<div class="wpdmpp-cl__search">
									<?php echo Icons::get( 'search', 16 ); ?>
									<input type="search" id="cl-search" name="search" value="<?php echo esc_attr( $search ); ?>" class="form-control" placeholder="<?php echo esc_attr__( 'ID, name, username or email…', 'wpdm-premium-packages' ); ?>">
								</div>
							</div>
							<div class="wpdmpp-cl__field wpdmpp-cl__field--action">
								<label class="wpdmpp-cl__label">&nbsp;</label>
								<button type="submit" class="btn btn-secondary"><?php echo Icons::get( 'search', 15 ); ?> <?php esc_html_e( 'Search', 'wpdm-premium-packages' ); ?></button>
							</div>
							<?php if ( $search !== '' ) { ?>
								<div class="wpdmpp-cl__field wpdmpp-cl__field--action">
									<label class="wpdmpp-cl__label">&nbsp;</label>
									<a href="<?php echo esc_url( remove_query_arg( array( 'search', 'paged' ) ) ); ?>" class="wpdmpp-cl__btn wpdmpp-cl__btn--ghost"><?php echo Icons::get( 'close', 15 ); ?> <?php esc_html_e( 'Clear', 'wpdm-premium-packages' ); ?></a>
								</div>
							<?php } ?>
						</div>
					</div>
					<div class="wpdmpp-cl__resultbar">
						<span class="cl-count"><?php echo esc_html( sprintf( _n( '%s customer', '%s customers', $total_customers, 'wpdm-premium-packages' ), number_format_i18n( $total_customers ) ) ); ?></span>
						<?php if ( ! $has_filter && $kpi ) { ?>
							<span class="cl-total"><?php esc_html_e( 'Lifetime Revenue:', 'wpdm-premium-packages' ); ?> <b><?php echo wp_kses_post( wpdmpp_price_format( $kpi['revenue'], true, true ) ); ?></b></span>
						<?php } ?>
					</div>
				</form>

				<div class="wpdmpp-cl__customers-responsive">
					<div class="wpdmpp-cl__panel wpdmpp-cl__tablewrap">
						<table class="cl-table">
							<thead>
								<tr>
									<th scope="col"<?php echo $aria_sort( 'name' ); ?>><?php echo $sort_link( 'name', __( 'Customer', 'wpdm-premium-packages' ) ); ?></th>
									<th scope="col"<?php echo $aria_sort( 'registered' ); ?>><?php echo $sort_link( 'registered', __( 'Member Since', 'wpdm-premium-packages' ) ); ?></th>
									<th scope="col" class="cl-num"<?php echo $aria_sort( 'orders' ); ?>><?php echo $sort_link( 'orders', __( 'Orders', 'wpdm-premium-packages' ), true ); ?></th>
									<th scope="col" class="cl-num"<?php echo $aria_sort( 'spent' ); ?>><?php echo $sort_link( 'spent', __( 'Total Spent', 'wpdm-premium-packages' ), true ); ?></th>
									<th scope="col"<?php echo $aria_sort( 'last' ); ?>><?php echo $sort_link( 'last', __( 'Last Order', 'wpdm-premium-packages' ), true ); ?></th>
									<th scope="col" class="cl-num" style="width:130px"><?php esc_html_e( 'Actions', 'wpdm-premium-packages' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php
							$avatar_tones  = array( '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#ef4444' );
							$mobile_rows   = array();
							$date_fmt      = get_option( 'date_format' );
							$time_fmt      = get_option( 'time_format' );

							foreach ( $all_customers as $customer ) {
								$uid          = (int) $customer->uid;
								$exists       = ! empty( $customer->user_id );
								$display_name = $exists ? $customer->display_name : '';
								$email        = $exists ? $customer->user_email : '';
								$order_count  = (int) $customer->order_count;
								$total_spent  = (float) $customer->total_spent;
								$last_order   = (int) $customer->last_order;
								$profile_url  = $base_url . '&view=profile&id=' . $uid;

								$initial = '';
								if ( $exists ) {
									$initial = strtoupper( mb_substr( $display_name !== '' ? $display_name : $email, 0, 1 ) );
								}
								$tone = $avatar_tones[ $uid % count( $avatar_tones ) ];

								$mobile_rows[] = array(
									'name'        => $exists ? $display_name : __( 'User deleted', 'wpdm-premium-packages' ),
									'email'       => $email,
									'initial'     => $initial,
									'tone'        => $tone,
									'avatar'      => $exists ? get_avatar_url( $email, array( 'size' => 84 ) ) : '',
									'profile_url' => $profile_url,
									'orders'      => number_format_i18n( $order_count ),
									'spent'       => wpdmpp_price_format( $total_spent, true, true ),
									'member'      => $exists ? wp_date( $date_fmt, strtotime( $customer->user_registered ) ) : '—',
									'last'        => $last_order ? wp_date( $date_fmt, $last_order ) : '—',
									'exists'      => $exists,
								);
								?>
								<tr>
									<td data-label="<?php esc_attr_e( 'Customer', 'wpdm-premium-packages' ); ?>">
										<div class="wpdmpp-cl__cust">
											<span class="wpdmpp-cl__avatar"<?php echo $exists ? '' : ' style="background:' . esc_attr( $tone ) . '"'; ?>>
												<?php
												if ( $exists ) {
													echo get_avatar( $email, 40 );
												} else {
													echo '<span class="wpdmpp-cl__avatar--ph">?</span>';
												}
												?>
											</span>
											<span class="wpdmpp-cl__cust-main">
												<span class="wpdmpp-cl__cust-name">
													<?php if ( $exists ) { ?>
														<a href="<?php echo esc_url( $profile_url ); ?>"><?php echo esc_html( $display_name ); ?></a>
													<?php } else { ?>
														<span class="wpdmpp-cl__deleted"><?php esc_html_e( 'User deleted', 'wpdm-premium-packages' ); ?></span>
													<?php } ?>
													<span class="wpdmpp-cl__id">#<?php echo esc_html( $uid ); ?></span>
												</span>
												<?php if ( $email !== '' ) { ?>
													<span class="wpdmpp-cl__cust-email"><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></span>
												<?php } ?>
											</span>
										</div>
									</td>
									<td data-label="<?php esc_attr_e( 'Member Since', 'wpdm-premium-packages' ); ?>">
										<?php if ( $exists ) { ?>
											<span class="cl-date__stack"><span class="cl-date__d"><?php echo esc_html( wp_date( $date_fmt, strtotime( $customer->user_registered ) ) ); ?></span><span class="cl-date__t"><?php echo esc_html( wp_date( $time_fmt, strtotime( $customer->user_registered ) ) ); ?></span></span>
										<?php } else { ?>
											<span class="cl-muted">&mdash;</span>
										<?php } ?>
									</td>
									<td class="cl-num" data-label="<?php esc_attr_e( 'Orders', 'wpdm-premium-packages' ); ?>">
										<a class="cl-orders-pill" href="edit.php?post_type=wpdmpro&page=orders&customer=<?php echo esc_attr( $uid ); ?>" title="<?php esc_attr_e( 'View this customer\'s orders', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get( 'shopping-bag', 13 ); ?> <?php echo esc_html( number_format_i18n( $order_count ) ); ?></a>
									</td>
									<td class="cl-num" data-label="<?php esc_attr_e( 'Total Spent', 'wpdm-premium-packages' ); ?>">
										<span class="cl-spent<?php echo $total_spent > 0 ? '' : ' cl-spent--zero'; ?>"><?php echo wp_kses_post( wpdmpp_price_format( $total_spent, true, true ) ); ?></span>
									</td>
									<td data-label="<?php esc_attr_e( 'Last Order', 'wpdm-premium-packages' ); ?>">
										<?php if ( $last_order ) { ?>
											<span class="cl-date__stack"><span class="cl-date__d"><?php echo esc_html( wp_date( $date_fmt, $last_order ) ); ?></span><span class="cl-date__t"><?php echo esc_html( wp_date( $time_fmt, $last_order ) ); ?></span></span>
										<?php } else { ?>
											<span class="cl-muted">&mdash;</span>
										<?php } ?>
									</td>
									<td class="cl-num" data-label="<?php esc_attr_e( 'Actions', 'wpdm-premium-packages' ); ?>">
										<span class="wpdmpp-cl__actions">
											<?php if ( $exists ) { ?>
												<a class="wpdmpp-cl__act wpdmpp-cl__act--primary" href="<?php echo esc_url( $profile_url ); ?>" title="<?php esc_attr_e( 'View profile', 'wpdm-premium-packages' ); ?>" aria-label="<?php esc_attr_e( 'View profile', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get( 'user-circle', 15 ); ?></a>
												<a class="wpdmpp-cl__act" href="user-edit.php?user_id=<?php echo esc_attr( $uid ); ?>" title="<?php esc_attr_e( 'Edit user', 'wpdm-premium-packages' ); ?>" aria-label="<?php esc_attr_e( 'Edit user', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get( 'pencil', 15 ); ?></a>
											<?php } else { ?>
												<span class="cl-muted">&mdash;</span>
											<?php } ?>
										</span>
									</td>
								</tr>
							<?php } ?>
							<?php if ( empty( $all_customers ) ) { ?>
								<tr class="cl-empty">
									<td colspan="6">
										<div class="cl-empty__inner">
											<div class="cl-empty__icon"><?php echo Icons::get( 'users', 25 ); ?></div>
											<div class="cl-empty__title"><?php esc_html_e( 'No customers found', 'wpdm-premium-packages' ); ?></div>
											<div class="cl-empty__hint">
												<?php
												echo $has_filter
													? esc_html__( 'No customers match the current filters. Try clearing the search.', 'wpdm-premium-packages' )
													: esc_html__( 'Customers appear here once their first order is completed.', 'wpdm-premium-packages' );
												?>
											</div>
										</div>
									</td>
								</tr>
							<?php } ?>
							</tbody>
						</table>
					</div>
					<div id="wpdmpp-customers-mobile"></div>
				</div><!-- /.wpdmpp-cl__customers-responsive -->

				<script>
					window.wpdmppCustomersData = <?php echo wp_json_encode( array(
						'items' => $mobile_rows,
						'l10n'  => array(
							'member' => __( 'Member since', 'wpdm-premium-packages' ),
							'orders' => __( 'Orders', 'wpdm-premium-packages' ),
							'spent'  => __( 'Total spent', 'wpdm-premium-packages' ),
							'last'   => __( 'Last order', 'wpdm-premium-packages' ),
							'view'   => __( 'View profile', 'wpdm-premium-packages' ),
							'empty'  => __( 'No customers found.', 'wpdm-premium-packages' ),
						),
					) ); ?>;
				</script>
				<script>
					(function () {
						var cfg = window.wpdmppCustomersData || { items: [], l10n: {} };
						if (typeof Vue === 'undefined' || !document.getElementById('wpdmpp-customers-mobile')) return;
						// r.spent is trusted server markup from wpdmpp_price_format() (currency entity),
						// not user input — hence the single v-html binding below is safe.
						Vue.createApp({
							data: function () { return { rows: cfg.items || [], L: cfg.l10n || {} }; },
							template:
								'<div class="wpcm-list">' +
								'  <p v-if="!rows.length" class="wpcm-empty">{{ L.empty }}</p>' +
								'  <div class="wpcm-card" v-for="r in rows" :key="r.profile_url">' +
								'    <div class="wpcm-head">' +
								'      <span class="wpcm-av" :style="{background: r.tone}"><img v-if="r.avatar && r.exists" :src="r.avatar" alt=""><template v-else>{{ r.initial || \'?\' }}</template></span>' +
								'      <div>' +
								'        <div class="wpcm-name"><a v-if="r.exists" :href="r.profile_url">{{ r.name }}</a><template v-else>{{ r.name }}</template></div>' +
								'        <div class="wpcm-email" v-if="r.email">{{ r.email }}</div>' +
								'      </div>' +
								'    </div>' +
								'    <div class="wpcm-meta">' +
								'      <div class="wpcm-row"><span class="wpcm-k">{{ L.member }}</span><span class="wpcm-v">{{ r.member }}</span></div>' +
								'      <div class="wpcm-row"><span class="wpcm-k">{{ L.orders }}</span><span class="wpcm-v">{{ r.orders }}</span></div>' +
								'      <div class="wpcm-row"><span class="wpcm-k">{{ L.spent }}</span><span class="wpcm-v spent" v-html="r.spent"></span></div>' +
								'      <div class="wpcm-row"><span class="wpcm-k">{{ L.last }}</span><span class="wpcm-v">{{ r.last }}</span></div>' +
								'    </div>' +
								'    <a v-if="r.exists" class="wpcm-view" :href="r.profile_url">{{ L.view }}</a>' +
								'  </div>' +
								'</div>'
						}).mount('#wpdmpp-customers-mobile');
						var wrap = document.querySelector('.wpdmpp-cl__customers-responsive');
						if (wrap) wrap.classList.add('is-vue-ready');
					})();
				</script>

				<?php
				echo wpdm_paginate_links(
					$total_customers,
					$limit,
					$page,
					'paged',
					array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '' )
				);
				?>
			</div>
		</div>
	</div>
</div>

<script>
	(function ($) {
		var $btn = $('#wpdmpp-cl-recalc');
		if (!$btn.length) return;

		var nonce = $btn.attr('data-nonce');
		var $progress = $('#wpdmpp-cl-recalc-progress');
		var $bar = $progress.find('.wpdmpp-cl__recalc-bar');
		var $pct = $progress.find('.cl-pct');

		var $track = $progress.find('.wpdmpp-cl__recalc-track');

		function setPct(p) {
			p = Math.max(0, Math.min(100, Math.round(p)));
			$bar.css('width', p + '%');
			$track.attr('aria-valuenow', p);
			$pct.text(p + '%');
		}

		function run(page) {
			$.post(ajaxurl, { action: 'wpdmpp_recalculateCustomerValue', __rcvnonce: nonce, cp: page })
				.done(function (r) {
					r = r || {};
					setPct(r.progress || 0);
					if (r['continue']) {
						run(r.nextpage);
					} else {
						setPct(100);
						setTimeout(function () { window.location.reload(); }, 700);
					}
				})
				.fail(function () {
					$progress.find('.wpdmpp-cl__recalc-label > span').first()
						.text(<?php echo wp_json_encode( __( 'Recalculation failed. Please try again.', 'wpdm-premium-packages' ) ); ?>);
					$btn.prop('disabled', false);
				});
		}

		$btn.on('click', function (e) {
			e.preventDefault();
			if ($btn.prop('disabled')) return;
			$btn.prop('disabled', true);
			$progress.addClass('is-active');
			setPct(0);
			run(1);
		});
	})(jQuery);
</script>
