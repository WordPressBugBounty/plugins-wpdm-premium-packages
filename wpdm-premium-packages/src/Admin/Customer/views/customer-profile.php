<?php
/**
 * Customers — single customer profile shell.
 *
 * Sidebar identity card + key metrics + tab navigation, with the active tab's
 * content rendered into the main column. Scoped under .wpdmpp-cp.
 *
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Shit happens!' );
}

use WPDMPP\UI\Icons;

global $wpdb;

$uid      = (int) wpdm_query_var( 'id', 'int' );
$customer = get_user_by( 'id', $uid );
$exists   = is_object( $customer );

// Orders + lifetime value on the same basis the customers list uses
// (completed + expired purchases, plus renewal revenue) so this page and the
// list row always agree.
$total_spent = 0.0;
$order_count = 0;
if ( $exists ) {
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT
			COUNT(CASE WHEN order_status IN ('Completed','Expired') THEN 1 END) AS order_count,
			COALESCE(SUM(CASE WHEN order_status IN ('Completed','Expired') THEN total ELSE 0 END), 0) AS order_total
		FROM {$wpdb->prefix}ahm_orders WHERE uid = %d",
		$uid
	) );
	$renew_total = (float) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(r.total), 0) FROM {$wpdb->prefix}ahm_orders o
		 INNER JOIN {$wpdb->prefix}ahm_order_renews r ON o.order_id = r.order_id
		 WHERE o.uid = %d",
		$uid
	) );
	$order_count = (int) $row->order_count;
	$total_spent = (float) $row->order_total + $renew_total;
}
$last_login = $exists ? (int) get_user_meta( $uid, '__wpdm_last_login_time', true ) : 0;
$base_url   = 'edit.php?post_type=wpdmpro&page=customers';

?>
<div class="w3eden">
	<?php
	$menus = array(
		array( 'link' => $base_url, 'name' => __( 'All Customers', 'wpdm-premium-packages' ), 'active' => false ),
		array( 'link' => '#', 'name' => __( 'Customer Profile', 'wpdm-premium-packages' ), 'active' => true ),
	);

	WPDM()->admin->pageHeader( esc_attr__( 'Customers', 'wpdm-premium-packages' ), 'user-graduate fas color-purple', $menus );
	?>

	<div class="wpdm-admin-page-content">
		<div class="wpdmpp-cp">
			<style>
			/* ==========================================================================
			   Customer profile — shares the .wpdmpp-cl token system. Scoped to .wpdmpp-cp.
			   ========================================================================== */
			.wpdmpp-cp{
				--cp-surface:var(--color-bg-card,#fff);
				--cp-surface-2:#f8fafc;
				--cp-text:var(--color-text,#0f172a);
				--cp-muted:var(--color-muted,#64748b);
				--cp-faint:#94a3b8;
				--cp-border:var(--color-border,#e7eaef);
				--cp-border-soft:#eef1f5;
				--cp-primary:var(--color-primary,#4f46e5);
				--cp-primary-rgb:var(--color-primary-rgb,79,70,229);
				--cp-radius:14px;
				--cp-shadow-sm:0 1px 2px rgba(15,23,42,.05);
				--cp-font:var(--wpdm-font,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Inter,Helvetica,Arial,sans-serif);
				font-family:var(--cp-font);color:var(--cp-text);font-size:14px;line-height:1.5;
			}
			.wpdmpp-cp *{box-sizing:border-box;}
			.wpdmpp-cp a{color:var(--cp-primary);text-decoration:none;}
			.wpdmpp-cp a:hover{text-decoration:underline;}

			.wpdmpp-cp__layout{display:grid;grid-template-columns:300px minmax(0,1fr);gap:20px;align-items:start;}
			.wpdmpp-cp__card{background:var(--cp-surface);border:1px solid var(--cp-border);border-radius:var(--cp-radius);box-shadow:var(--cp-shadow-sm);overflow:hidden;}

			/* identity */
			.wpdmpp-cp__identity{padding:26px 22px 20px;text-align:center;border-bottom:1px solid var(--cp-border-soft);}
			.wpdmpp-cp__avatar{width:88px;height:88px;border-radius:50%;overflow:hidden;margin:0 auto 14px;background:var(--cp-surface-2);display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 4px #fff,0 0 0 5px var(--cp-border);}
			.wpdmpp-cp__avatar img{width:100%;height:100%;display:block;border-radius:50%;}
			.wpdmpp-cp__avatar--ph{font-weight:700;font-size:34px;color:#fff;}
			.wpdmpp-cp__name{font-size:18px;font-weight:700;letter-spacing:-.01em;color:var(--cp-text);margin:0 0 4px;display:flex;align-items:center;justify-content:center;gap:8px;}
			.wpdmpp-cp__name a{color:var(--cp-faint);display:inline-flex;}
			.wpdmpp-cp__name a:hover{color:var(--cp-primary);}
			.wpdmpp-cp__name a svg{width:15px;height:15px;}
			.wpdmpp-cp__email{font-size:13px;color:var(--cp-muted);word-break:break-word;}
			.wpdmpp-cp__email a{color:var(--cp-muted);}
			.wpdmpp-cp__badge{display:inline-flex;align-items:center;gap:6px;margin-top:12px;padding:4px 12px;border-radius:999px;font-size:11.5px;font-weight:600;background:rgba(var(--cp-primary-rgb),.08);color:var(--cp-primary);}
			.wpdmpp-cp__badge svg{width:13px;height:13px;}

			/* metrics */
			.wpdmpp-cp__metrics{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--cp-border-soft);border-bottom:1px solid var(--cp-border-soft);}
			.wpdmpp-cp__metric{background:var(--cp-surface);padding:16px 14px;text-align:center;}
			.wpdmpp-cp__metric-label{font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--cp-muted);margin-bottom:5px;}
			.wpdmpp-cp__metric-value{font-size:19px;font-weight:700;letter-spacing:-.02em;font-variant-numeric:tabular-nums;}
			.wpdmpp-cp__metric-value.is-spent{color:#15803d;}

			/* nav */
			.wpdmpp-cp__nav{padding:10px;}
			.wpdmpp-cp__nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:13.5px;font-weight:600;color:var(--cp-text);transition:background .12s ease,color .12s ease;}
			.wpdmpp-cp__nav-item:hover{background:var(--cp-surface-2);text-decoration:none;}
			.wpdmpp-cp__nav-item svg{width:16px;height:16px;color:var(--cp-faint);flex:0 0 auto;}
			.wpdmpp-cp__nav-item.is-active{background:rgba(var(--cp-primary-rgb),.08);color:var(--cp-primary);}
			.wpdmpp-cp__nav-item.is-active svg{color:var(--cp-primary);}

			/* info list */
			.wpdmpp-cp__info{padding:6px 18px 16px;}
			.wpdmpp-cp__info-row{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-top:1px solid var(--cp-border-soft);}
			.wpdmpp-cp__info-row:first-child{border-top:0;}
			.wpdmpp-cp__info-ico{width:30px;height:30px;border-radius:8px;flex:0 0 auto;background:var(--cp-surface-2);color:var(--cp-faint);display:inline-flex;align-items:center;justify-content:center;}
			.wpdmpp-cp__info-ico svg{width:15px;height:15px;}
			.wpdmpp-cp__info-k{font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--cp-muted);}
			.wpdmpp-cp__info-v{font-size:13px;color:var(--cp-text);word-break:break-word;}

			.wpdmpp-cp__deleted{display:flex;flex-direction:column;align-items:center;gap:10px;padding:50px 24px;text-align:center;color:var(--cp-muted);}
			.wpdmpp-cp__deleted-ico{width:54px;height:54px;border-radius:15px;background:var(--cp-surface-2);color:var(--cp-faint);display:flex;align-items:center;justify-content:center;}

			/* shared content surfaces (used by tab content, e.g. customer-purchases.php) */
			.wpdmpp-cp__section{background:var(--cp-surface);border:1px solid var(--cp-border);border-radius:var(--cp-radius);box-shadow:var(--cp-shadow-sm);margin-bottom:18px;overflow:hidden;}
			/* tabbed content (Invoices / Purchased Items). Divider drawn as an inset
			   shadow (not a border) so the active 2px underline never overflows the
			   horizontally-scrolling tab strip and clips into a double-line seam. */
			.wpdmpp-cp__tabs{display:flex;align-items:stretch;gap:2px;padding:0 10px;box-shadow:inset 0 -1px 0 var(--cp-border-soft);background:var(--cp-surface);overflow-x:auto;overflow-y:hidden;}
			.wpdmpp-cp__tab{display:inline-flex;align-items:center;gap:8px;padding:15px 12px 13px;border:0;background:none;cursor:pointer;font-family:inherit;font-size:13px;font-weight:600;line-height:1;color:var(--cp-muted);white-space:nowrap;border-bottom:2px solid transparent;transition:color .12s ease,border-color .12s ease;}
			.wpdmpp-cp__tab svg{width:16px;height:16px;color:var(--cp-faint);transition:color .12s ease;}
			.wpdmpp-cp__tab:hover{color:var(--cp-text);}
			.wpdmpp-cp__tab:hover svg{color:var(--cp-muted);}
			.wpdmpp-cp__tab.is-active{color:var(--cp-primary);border-bottom-color:var(--cp-primary);}
			.wpdmpp-cp__tab.is-active svg{color:var(--cp-primary);}
			.wpdmpp-cp__tab:focus-visible{outline:2px solid var(--cp-primary);outline-offset:-3px;border-radius:6px;}
			.wpdmpp-cp__tab-count{font-size:11px;font-weight:700;color:#475569;background:var(--cp-surface-2);border:1px solid var(--cp-border-soft);padding:1px 8px;border-radius:999px;font-variant-numeric:tabular-nums;}
			.wpdmpp-cp__tab.is-active .wpdmpp-cp__tab-count{color:var(--cp-primary);background:rgba(var(--cp-primary-rgb),.08);border-color:rgba(var(--cp-primary-rgb),.18);}
			.wpdmpp-cp__tabpanel{outline:none;}
			.wpdmpp-cp__tabpanel[hidden]{display:none;}
			.wpdmpp-cp__tablewrap{width:100%;overflow-x:auto;}
			.wpdmpp-cp table.cp-table{width:100%;border-collapse:separate;border-spacing:0;margin:0;background:var(--cp-surface);}
			.wpdmpp-cp table.cp-table thead th{background:var(--cp-surface-2);padding:10px 18px;text-align:left;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--cp-muted);white-space:nowrap;border-bottom:1px solid var(--cp-border);}
			.wpdmpp-cp table.cp-table thead th.cp-num{text-align:right;}
			.wpdmpp-cp table.cp-table tbody td{padding:12px 18px;vertical-align:middle;border-bottom:1px solid var(--cp-border-soft);font-size:13.5px;color:var(--cp-text);}
			.wpdmpp-cp table.cp-table tbody td.cp-num{text-align:right;font-variant-numeric:tabular-nums;}
			.wpdmpp-cp table.cp-table tbody tr:last-child td{border-bottom:0;}
			.wpdmpp-cp table.cp-table tbody tr:hover td{background:rgba(var(--cp-primary-rgb),.035);}
			.wpdmpp-cp .cp-id{display:inline-flex;align-items:center;gap:8px;font-weight:600;}
			.wpdmpp-cp .cp-id svg{flex:0 0 auto;}
			.wpdmpp-cp .cp-type{display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;background:#eef2ff;color:#4338ca;}
			.wpdmpp-cp .cp-type--renew{background:#ecfdf5;color:#047857;}
			.wpdmpp-cp .cp-amount{font-weight:700;color:#15803d;white-space:nowrap;font-variant-numeric:tabular-nums;}
			.wpdmpp-cp .cp-act{color:var(--cp-muted);font-weight:600;}
			.wpdmpp-cp .cp-actions{display:inline-flex;align-items:center;gap:6px;justify-content:flex-end;}
			.wpdmpp-cp__act{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;flex:0 0 auto;background:var(--cp-surface);border:1px solid var(--cp-border);color:var(--cp-muted);transition:background .12s ease,border-color .12s ease,color .12s ease;}
			.wpdmpp-cp__act svg{width:15px;height:15px;}
			.wpdmpp-cp__act:hover{background:rgba(var(--cp-primary-rgb),.08);border-color:rgba(var(--cp-primary-rgb),.32);color:var(--cp-primary);text-decoration:none;}
			.wpdmpp-cp .cp-empty{padding:34px 18px;text-align:center;color:var(--cp-faint);font-size:13px;}

			@media (max-width:980px){
				.wpdmpp-cp__layout{grid-template-columns:1fr;}
			}
			@media (prefers-reduced-motion:reduce){
				.wpdmpp-cp *{transition:none !important;}
			}
			</style>

			<div class="wpdmpp-cp__layout">
				<aside class="wpdmpp-cp__aside">
					<div class="wpdmpp-cp__card">
						<?php if ( $exists ) { ?>
							<div class="wpdmpp-cp__identity">
								<div class="wpdmpp-cp__avatar"><?php echo get_avatar( $customer->user_email, 88 ); ?></div>
								<h2 class="wpdmpp-cp__name">
									<?php echo esc_html( $customer->display_name ); ?>
									<a href="user-edit.php?user_id=<?php echo esc_attr( $uid ); ?>" title="<?php esc_attr_e( 'Edit user', 'wpdm-premium-packages' ); ?>" aria-label="<?php esc_attr_e( 'Edit user', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get( 'pencil', 15 ); ?></a>
								</h2>
								<div class="wpdmpp-cp__email"><a href="mailto:<?php echo esc_attr( $customer->user_email ); ?>"><?php echo esc_html( $customer->user_email ); ?></a></div>
								<span class="wpdmpp-cp__badge"><?php echo Icons::get( 'user-circle', 13 ); ?> <?php esc_html_e( 'Customer', 'wpdm-premium-packages' ); ?></span>
							</div>

							<div class="wpdmpp-cp__metrics">
								<div class="wpdmpp-cp__metric">
									<div class="wpdmpp-cp__metric-label"><?php esc_html_e( 'Total Spent', 'wpdm-premium-packages' ); ?></div>
									<div class="wpdmpp-cp__metric-value is-spent"><?php echo wp_kses_post( wpdmpp_price_format( $total_spent, true, true ) ); ?></div>
								</div>
								<div class="wpdmpp-cp__metric">
									<div class="wpdmpp-cp__metric-label"><?php esc_html_e( 'Orders', 'wpdm-premium-packages' ); ?></div>
									<div class="wpdmpp-cp__metric-value"><?php echo esc_html( number_format_i18n( $order_count ) ); ?></div>
								</div>
							</div>


							<?php do_action( 'wpdm_customer_profile_admin_sidebar_top', $customer ); ?>

							<div class="wpdmpp-cp__info">
								<div class="wpdmpp-cp__info-row">
									<span class="wpdmpp-cp__info-ico"><?php echo Icons::get( 'calendar', 15 ); ?></span>
									<span>
										<span class="wpdmpp-cp__info-k"><?php esc_html_e( 'Member Since', 'wpdm-premium-packages' ); ?></span><br>
										<span class="wpdmpp-cp__info-v"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $customer->user_registered ) ) ); ?></span>
									</span>
								</div>
								<?php if ( $last_login ) { ?>
									<div class="wpdmpp-cp__info-row">
										<span class="wpdmpp-cp__info-ico"><?php echo Icons::get( 'clock', 15 ); ?></span>
										<span>
											<span class="wpdmpp-cp__info-k"><?php esc_html_e( 'Last Login', 'wpdm-premium-packages' ); ?></span><br>
											<span class="wpdmpp-cp__info-v"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_login ) ); ?></span>
										</span>
									</div>
								<?php } ?>
								<div class="wpdmpp-cp__info-row">
									<span class="wpdmpp-cp__info-ico"><?php echo Icons::get( 'mail', 15 ); ?></span>
									<span>
										<span class="wpdmpp-cp__info-k"><?php esc_html_e( 'Email', 'wpdm-premium-packages' ); ?></span><br>
										<span class="wpdmpp-cp__info-v"><?php echo esc_html( $customer->user_email ); ?></span>
									</span>
								</div>
							</div>

							<?php do_action( 'wpdm_customer_profile_admin_sidebar_bottom', $customer ); ?>
						<?php } else { ?>
							<div class="wpdmpp-cp__deleted">
								<div class="wpdmpp-cp__deleted-ico"><?php echo Icons::get( 'user-circle', 26 ); ?></div>
								<div><strong><?php esc_html_e( 'User deleted / not found', 'wpdm-premium-packages' ); ?></strong></div>
								<div><a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Back to all customers', 'wpdm-premium-packages' ); ?></a></div>
							</div>
						<?php } ?>
					</div>
				</aside>

				<main class="wpdmpp-cp__content" id="wpdmdd-profile-content">
					<?php
					if ( $exists && isset( $tabs[ $tab ]['callback'] ) ) {
						call_user_func( $tabs[ $tab ]['callback'] );
					} else {
						?>
						<div class="wpdmpp-cp__section">
							<div class="cp-empty" style="padding:60px 24px;">
								<?php esc_html_e( 'This profile is unavailable because the user account no longer exists.', 'wpdm-premium-packages' ); ?>
							</div>
						</div>
						<?php
					}
					?>
				</main>
			</div>
		</div>
	</div>


</div>
