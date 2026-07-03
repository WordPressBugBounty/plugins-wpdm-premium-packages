<?php
/**
 * Customers — profile "Profile" tab content (invoices + purchased items).
 *
 * Rendered inside the .wpdmpp-cp profile shell, so it reuses the shell's
 * shared surfaces (.wpdmpp-cp__section / .cp-table).
 *
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Shit happens!' );
}

use WPDMPP\UI\Icons;

global $wpdb;

$uid          = (int) wpdm_query_var( 'id', 'int' );
$orderService = \WPDMPP\Order\OrderService::instance();
$orders       = $orderService->getRawUserOrders( $uid, true );
$purchased    = $orderService->getPurchasedItems( $uid );

$orders_url = 'edit.php?post_type=wpdmpro&page=orders&task=vieworder&id=';

// Batch every renewal for this customer in one query, then group by order_id —
// avoids an N+1 query inside the invoices loop below.
$renews_by_order = array();
$order_ids       = ! empty( $orders ) ? wp_list_pluck( $orders, 'order_id' ) : array();
if ( ! empty( $order_ids ) ) {
	$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%s' ) );
	$renew_rows   = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}ahm_order_renews WHERE order_id IN ($placeholders) ORDER BY date ASC",
		$order_ids
	) );
	foreach ( $renew_rows as $renew_row ) {
		$renews_by_order[ $renew_row->order_id ][] = $renew_row;
	}
}

?>
<div class="wpdmpp-cp__tabcontent">

	<div class="wpdmpp-cp__section">
		<div class="wpdmpp-cp__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Customer activity', 'wpdm-premium-packages' ); ?>">
			<button type="button" class="wpdmpp-cp__tab is-active" id="cp-tab-invoices" role="tab" aria-controls="cp-panel-invoices" aria-selected="true" tabindex="0">
				<?php echo Icons::get( 'file-text', 16 ); ?>
				<?php esc_html_e( 'Invoices', 'wpdm-premium-packages' ); ?>
				<span class="wpdmpp-cp__tab-count"><?php echo esc_html( number_format_i18n( count( $orders ) ) ); ?></span>
			</button>
			<button type="button" class="wpdmpp-cp__tab" id="cp-tab-items" role="tab" aria-controls="cp-panel-items" aria-selected="false">
				<?php echo Icons::get( 'shopping-bag', 16 ); ?>
				<?php esc_html_e( 'Purchased Items', 'wpdm-premium-packages' ); ?>
				<span class="wpdmpp-cp__tab-count"><?php echo esc_html( number_format_i18n( count( $purchased ) ) ); ?></span>
			</button>
		</div>

		<div class="wpdmpp-cp__tabpanel" id="cp-panel-invoices" role="tabpanel" aria-labelledby="cp-tab-invoices">
			<div class="wpdmpp-cp__tablewrap">
				<table class="cp-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Order ID', 'wpdm-premium-packages' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date', 'wpdm-premium-packages' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'wpdm-premium-packages' ); ?></th>
							<th scope="col" class="cp-num"><?php esc_html_e( 'Amount', 'wpdm-premium-packages' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					if ( ! empty( $orders ) ) {
						foreach ( $orders as $order ) {
							$renews = isset( $renews_by_order[ $order->order_id ] ) ? $renews_by_order[ $order->order_id ] : array();
							?>
							<tr>
								<td>
									<span class="cp-id"><?php echo Icons::get( 'shopping-bag', 15, 'text-primary' ); ?>
										<a target="_blank" rel="noopener" href="<?php echo esc_url( $orders_url . rawurlencode( $order->order_id ) ); ?>"><?php echo esc_html( $order->order_id ); ?></a>
									</span>
								</td>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ), $order->date ) ); ?></td>
								<td><span class="cp-type"><?php echo Icons::get( 'check-circle', 12 ); ?> <?php esc_html_e( 'Purchase', 'wpdm-premium-packages' ); ?></span></td>
								<td class="cp-num"><span class="cp-amount"><?php echo wp_kses_post( wpdmpp_price_format( $order->total, true, true ) ); ?></span></td>
							</tr>
							<?php foreach ( $renews as $renew ) { ?>
								<tr>
									<td>
										<span class="cp-id"><?php echo Icons::get( 'redo', 15, 'text-success' ); ?>
											<span class="cp-act"><?php echo esc_html( $renew->order_id ); ?></span>
										</span>
									</td>
									<td><?php echo esc_html( wp_date( get_option( 'date_format' ), $renew->date ) ); ?></td>
									<td><span class="cp-type cp-type--renew"><?php echo Icons::get( 'redo', 12 ); ?> <?php esc_html_e( 'Renew', 'wpdm-premium-packages' ); ?></span></td>
									<td class="cp-num"><span class="cp-amount"><?php echo wp_kses_post( wpdmpp_price_format( $renew->total, true, true ) ); ?></span></td>
								</tr>
							<?php } ?>
							<?php
						}
					} else {
						?>
						<tr><td colspan="4"><div class="cp-empty"><?php esc_html_e( 'No invoices yet.', 'wpdm-premium-packages' ); ?></div></td></tr>
						<?php
					}
					?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="wpdmpp-cp__tabpanel" id="cp-panel-items" role="tabpanel" aria-labelledby="cp-tab-items">
			<div class="wpdmpp-cp__tablewrap">
				<table class="cp-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name', 'wpdm-premium-packages' ); ?></th>
							<th scope="col" class="cp-num"><?php esc_html_e( 'Price', 'wpdm-premium-packages' ); ?></th>
							<th scope="col" class="cp-num" style="width:130px"><?php esc_html_e( 'Actions', 'wpdm-premium-packages' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					// OrderService::getPurchasedItems() returns OrderItem::toArray() arrays:
					// keys are order_id / product_id / product_name / price.
					if ( ! empty( $purchased ) ) {
						foreach ( $purchased as $item ) {
							$order_id   = wpdm_valueof( $item, 'order_id' );
							$product_id = (int) wpdm_valueof( $item, 'product_id' );
							$price      = wpdm_valueof( $item, 'price', array( 'default' => 0 ) );
							?>
							<tr>
								<td>
									<span class="cp-id"><?php echo Icons::get( 'file-text', 15, 'text-primary' ); ?>
										<a target="_blank" rel="noopener" href="<?php echo esc_url( $orders_url . rawurlencode( $order_id ) ); ?>"><?php echo esc_html( wpdm_valueof( $item, 'product_name' ) ); ?></a>
									</span>
								</td>
								<td class="cp-num"><span class="cp-amount"><?php echo wp_kses_post( wpdmpp_price_format( (float) $price, true, true ) ); ?></span></td>
								<td class="cp-num">
									<span class="cp-actions">
										<a class="wpdmpp-cp__act" href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'View package', 'wpdm-premium-packages' ); ?>" aria-label="<?php esc_attr_e( 'View package', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get( 'eye', 15 ); ?></a>
										<a class="wpdmpp-cp__act" href="post.php?action=edit&post=<?php echo esc_attr( $product_id ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Edit package', 'wpdm-premium-packages' ); ?>" aria-label="<?php esc_attr_e( 'Edit package', 'wpdm-premium-packages' ); ?>"><?php echo Icons::get( 'pencil', 15 ); ?></a>
									</span>
								</td>
							</tr>
							<?php
						}
					} else {
						?>
						<tr><td colspan="3"><div class="cp-empty"><?php esc_html_e( 'No purchased items yet.', 'wpdm-premium-packages' ); ?></div></td></tr>
						<?php
					}
					?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

</div>

<script>
	(function () {
		var tablist = document.querySelector('.wpdmpp-cp__tabs[role="tablist"]');
		if (!tablist) return;
		var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));
		if (tabs.length < 2) return;

		// Progressive enhancement: panels render visible and the inactive tab stays
		// natively focusable server-side, so all content is reachable even if this
		// script fails. JS applies the roving tabindex and hides inactive panels.
		tabs.forEach(function (t) {
			var selected = t.getAttribute('aria-selected') === 'true';
			t.setAttribute('tabindex', selected ? '0' : '-1');
			var panel = document.getElementById(t.getAttribute('aria-controls'));
			if (panel) { panel.hidden = !selected; }
		});

		function activate(tab, setFocus) {
			tabs.forEach(function (t) {
				var selected = t === tab;
				t.classList.toggle('is-active', selected);
				t.setAttribute('aria-selected', selected ? 'true' : 'false');
				t.setAttribute('tabindex', selected ? '0' : '-1');
				var panel = document.getElementById(t.getAttribute('aria-controls'));
				if (panel) { panel.hidden = !selected; }
			});
			if (setFocus) { tab.focus(); }
		}

		tablist.addEventListener('click', function (e) {
			var tab = e.target.closest('[role="tab"]');
			if (tab) { activate(tab, false); }
		});

		tablist.addEventListener('keydown', function (e) {
			var i = tabs.indexOf(document.activeElement);
			if (i < 0) { return; }
			var n = null;
			// Horizontal tablist: APG specifies Left/Right (+ Home/End) only.
			if (e.key === 'ArrowRight') { n = (i + 1) % tabs.length; }
			else if (e.key === 'ArrowLeft') { n = (i - 1 + tabs.length) % tabs.length; }
			else if (e.key === 'Home') { n = 0; }
			else if (e.key === 'End') { n = tabs.length - 1; }
			if (n !== null) { e.preventDefault(); activate(tabs[n], true); }
		});
	})();
</script>
