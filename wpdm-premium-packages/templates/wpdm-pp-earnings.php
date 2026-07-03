<?php
/**
 * Template for [wpdm-pp-earnings] shortocode. This shortcode generates the content of WPDM Author Dashboard ( [wpdm_frontend flaturl=0] ) >> Sales Tab.
 *
 * Reports sales and earning details of the author.
 *
 * This template can be overridden by copying it to yourtheme/download-manager/wpdm-pp-earnings.php.
 *
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb, $current_user;
$current_user = wp_get_current_user();
$uid          = $current_user->ID;
$sql          = "select p.*,i.*, o.date from {$wpdb->prefix}ahm_orders o,
                      {$wpdb->prefix}ahm_order_items i,
                      {$wpdb->prefix}posts p
                      where p.post_author=$uid and
                            i.oid=o.order_id and
                            i.pid=p.ID and
                            i.quantity > 0 and
                            o.payment_status='Completed' order by o.date desc";

$sales = $wpdb->get_results( $sql );

$sql = "select sum(i.price*i.quantity) from {$wpdb->prefix}ahm_orders o,
                      {$wpdb->prefix}ahm_order_items i,
                      {$wpdb->prefix}posts p
                      where p.post_author=$uid and
                            i.oid=o.order_id and
                            i.pid=p.ID and
                            i.quantity > 0 and
                            o.payment_status='Completed'";

$total_sales      = $wpdb->get_var( $sql );
$commission       = wpdmpp_site_commission();
$total_commission = $total_sales * $commission / 100;
$total_earning    = $total_sales - $total_commission;
$sql              = "select sum(amount) from {$wpdb->prefix}ahm_withdraws where uid=$uid";
$total_withdraws  = $wpdb->get_var( $sql );
$balance          = $total_earning - $total_withdraws;

//finding matured balance
$payout_duration = get_option( "wpdmpp_payout_duration" );
$dt              = $payout_duration * 24 * 60 * 60;
$sqlm            = "select sum(i.price*i.quantity) from {$wpdb->prefix}ahm_orders o,
                      {$wpdb->prefix}ahm_order_items i,
                      {$wpdb->prefix}posts p
                      where p.post_author=$uid and
                            i.oid=o.order_id and
                            i.pid=p.ID and
                            i.quantity > 0 and
                            o.payment_status='Completed'
                            and (o.date+($dt))<" . time() . "";

$tempbalance     = $wpdb->get_var( $sqlm );
$tempbalance     = $tempbalance - ( $tempbalance * $commission / 100 );
$matured_balance = $tempbalance - $total_withdraws;

//finding pending balance
$pending_balance = $balance - $matured_balance;

// Build the withdrawal request form (rendered inside the WPDM modal via JS)
$form          = 0;
$billing_info  = maybe_unserialize( get_user_meta( get_current_user_id(), 'user_billing_shipping', true ) );
$billing_info  = wpdm_valueof( $billing_info, "billing" );
$user_accounts = get_user_meta( get_current_user_id(), '__wpdmpp_payment_account', true );
$active_pom    = get_option( "wpdmpp_active_pom", [] );
if ( ! is_array( $active_pom ) ) {
	$active_pom = [];
}
$updatepoi = WPDM()->authorDashboard->url( [ 'adb_page' => 'withdraws' ] );

ob_start();
?>
<div class="wpe-modal">
	<form id="wreqform" method="post">
		<?php
		if ( ! is_array( $billing_info ) ||
		     wpdm_valueof( $billing_info, 'first_name' ) == '' ||
		     wpdm_valueof( $billing_info, 'last_name' ) == '' ||
		     wpdm_valueof( $billing_info, 'address_1' ) . wpdm_valueof( $billing_info, 'address_2' ) == '' ||
		     wpdm_valueof( $billing_info, 'postcode' ) == '' ||
		     wpdm_valueof( $billing_info, 'state' ) . wpdm_valueof( $billing_info, 'city' ) == ''
		) {
			$updatebilling = wpdm_user_dashboard_url( array( 'udb_page' => 'edit-profile' ) );
			?>
			<div class="wpe-alert">
				<?php esc_html_e( 'Critical billing info is missing. Please update your billing info to generate invoice properly.', WPDMPP_TEXT_DOMAIN ); ?>
				<a class="wpe-btn wpe-btn--block" href="<?php echo esc_url( $updatebilling ); ?>"><?php esc_html_e( 'Update Billing Info', WPDMPP_TEXT_DOMAIN ); ?></a>
			</div>
			<?php
		} else if ( ! $user_accounts ) {
			?>
			<div class="wpe-alert">
				<?php esc_html_e( 'Critical payout info is missing. Please update your payout info to withdraw your fund.', WPDMPP_TEXT_DOMAIN ); ?>
				<a class="wpe-btn wpe-btn--block" href="<?php echo esc_url( $updatepoi ); ?>"><?php esc_html_e( 'Update Payout Info', WPDMPP_TEXT_DOMAIN ); ?></a>
			</div>
			<?php
		} else {
			$form = 1;
			?>
			<input type="hidden" name="withdraw" value="1">
			<div class="wpe-field">
				<label class="wpe-field__label"><?php esc_html_e( 'Payment Option', WPDMPP_TEXT_DOMAIN ); ?></label>
				<div class="wpe-pom-list">
					<?php foreach ( WPDMPP()->withdraws->getPayoutMethods() as $method ) {
						if ( $method['active'] ) { ?>
							<label class="wpe-pom">
								<span class="wpe-pom__info">
									<span class="wpe-pom__name"><?php echo esc_html( $method['name'] ); ?></span>
									<span class="wpe-pom__min"><?php printf( esc_html__( 'Min. Amount: %s', WPDMPP_TEXT_DOMAIN ), esc_html( wpdmpp_price_format( $method['min'] ) ) ); ?></span>
								</span>
								<?php if ( wpdm_valueof( $user_accounts, $method['id'] ) !== '' ) { ?>
									<input required="required" class="wpe-pom__radio pom" data-min="<?php echo esc_attr( $method['min'] ); ?>" type="radio" name="payout_method" value="<?php echo esc_attr( $method['id'] ); ?>">
								<?php } else { ?>
									<a href="<?php echo esc_url( $updatepoi ); ?>" class="wpe-btn wpe-btn--secondary wpe-btn--sm ttip" title="<?php echo esc_attr( sprintf( __( 'You need to add your %s account before send withdrawal request using %s', WPDMPP_TEXT_DOMAIN ), $method['name'], $method['name'] ) ); ?>"><?php esc_html_e( 'Configure', WPDMPP_TEXT_DOMAIN ); ?></a>
								<?php } ?>
							</label>
						<?php }} ?>
				</div>
			</div>
			<div class="wpe-field">
				<label class="wpe-field__label" for="withdraw_amount"><?php esc_html_e( 'Amount', WPDMPP_TEXT_DOMAIN ); ?></label>
				<input type="number" name="withdraw_amount" id="withdraw_amount" required="required" value="<?php echo floor( $matured_balance ); ?>" min="10" max="<?php echo floor( $matured_balance ); ?>" class="wpe-input">
			</div>
			<?php
		}
		?>
		<div class="wpe-actions">
			<button type="button" class="wpe-btn wpe-btn--secondary" id="wd-cancel"><?php esc_html_e( 'Close', WPDMPP_TEXT_DOMAIN ); ?></button>
			<?php if ( $form === 1 ) { ?>
				<button type="submit" class="wpe-btn"><?php esc_html_e( 'Send Request', WPDMPP_TEXT_DOMAIN ); ?></button>
			<?php } ?>
		</div>
	</form>
</div>
<?php
$wd_modal_inner = trim( ob_get_clean() );
?>

<style>
    .wpe-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }
    .wpe-card {
        background: var(--color-bg-card, #fff);
        border: 1px solid var(--color-border, #e2e8f0);
        border-radius: 10px;
        overflow: hidden;
        text-align: center;
    }
    .wpe-card__label {
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--color-muted, #64748b);
        border-bottom: 1px solid var(--color-border, #e2e8f0);
    }
    .wpe-card__value { padding: 18px 14px; font-size: 22px; font-weight: 700; color: var(--color-text, #1e293b); }
    .wpe-balance {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        background: var(--color-bg-card, #fff);
        border: 1px solid var(--color-border, #e2e8f0);
        border-radius: 10px;
        padding: 18px 22px;
        margin: 20px 0;
    }
    .wpe-balance__main { text-align: left; }
    .wpe-balance__label { font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--color-muted, #64748b); }
    .wpe-balance__amount { font-size: 26px; font-weight: 700; color: var(--color-success, #10b981); margin-top: 2px; }
    /* Buttons */
    .wpe-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 9px 20px;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        line-height: 1.4;
        border: 1px solid var(--color-primary, #6366f1);
        border-radius: 8px;
        background: var(--color-primary, #6366f1);
        color: #fff;
        cursor: pointer;
        text-decoration: none !important;
        transition: background 120ms ease, border-color 120ms ease, opacity 120ms ease;
    }
    .wpe-btn:hover:not(:disabled), .wpe-btn:focus:not(:disabled) { background: var(--color-primary-active, #4f46e5); border-color: var(--color-primary-active, #4f46e5); color: #fff; }
    .wpe-btn:disabled { opacity: .5; cursor: not-allowed; }
    .wpe-btn--secondary { background: transparent; color: var(--color-text, #1e293b); border-color: var(--color-border, #e2e8f0); }
    .wpe-btn--secondary:hover:not(:disabled) { background: var(--color-bg, #f8fafc); color: var(--color-text, #1e293b); border-color: var(--color-border, #e2e8f0); }
    .wpe-btn--sm { padding: 5px 12px; font-size: 12px; }
    .wpe-btn--block { display: flex; width: 100%; margin-top: 12px; }
    /* Modal form */
    .wpe-modal { text-align: left; }
    .wpe-field { margin-bottom: 18px; }
    .wpe-field__label { display: block; font-size: 13px; font-weight: 600; color: var(--color-text, #1e293b); margin-bottom: 8px; }
    .wpe-pom-list { display: flex; flex-direction: column; gap: 8px; }
    .wpe-pom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        margin: 0;
        border: 1px solid var(--color-border, #e2e8f0);
        border-radius: 8px;
        cursor: pointer;
        transition: border-color 120ms ease;
    }
    .wpe-pom:hover { border-color: var(--color-primary, #6366f1); }
    .wpe-pom__info { display: flex; flex-direction: column; line-height: 1.3; }
    .wpe-pom__name { font-weight: 600; font-size: 14px; color: var(--color-text, #1e293b); }
    .wpe-pom__min { font-size: 12px; color: var(--color-muted, #64748b); }
    .wpe-pom__radio { width: 18px; height: 18px; accent-color: var(--color-primary, #6366f1); flex-shrink: 0; }
    .wpe-input {
        width: 100%;
        padding: 10px 14px;
        font-size: 16px;
        line-height: 1.4;
        border: 1px solid var(--color-border, #e2e8f0);
        border-radius: 8px;
        background: var(--color-bg-card, #fff);
        color: var(--color-text, #1e293b);
        outline: none;
    }
    .wpe-input:focus { border-color: var(--color-primary, #6366f1); box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb, 99, 102, 241), .18); }
    .wpe-alert {
        padding: 14px 16px;
        border-radius: 8px;
        background: rgba(var(--color-warning-rgb, 245, 158, 11), .12);
        color: var(--color-text, #1e293b);
        font-size: 14px;
        line-height: 1.5;
    }
    .wpe-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 22px;
        padding-top: 16px;
        border-top: 1px solid var(--color-border, #e2e8f0);
    }
    /* Earnings table */
    .wpe-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .wpe-table th, .wpe-table td { padding: 11px 14px; text-align: left; border-bottom: 1px solid var(--color-border, #e2e8f0); font-size: 14px; color: var(--color-text, #1e293b); }
    .wpe-table thead th { font-size: 12px; letter-spacing: .04em; text-transform: uppercase; color: var(--color-muted, #64748b); background: var(--color-bg, #f8fafc); }
    .wpe-table tbody tr:hover { background: var(--color-bg, #f8fafc); }
    .wpe-table tfoot th { font-weight: 700; color: var(--color-text, #1e293b); border-top: 2px solid var(--color-border, #e2e8f0); }
    @media (max-width: 768px) {
        .wpe-stats { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 480px) {
        .wpe-stats { grid-template-columns: 1fr; }
        .wpe-balance { flex-direction: column; align-items: stretch; text-align: center; }
        .wpe-balance__main { text-align: center; }
    }
</style>

<div class="wpe-stats">
    <div class="wpe-card">
        <div class="wpe-card__label"><?php esc_html_e( "Sales", "wpdm-premium-packages" ); ?></div>
        <div class="wpe-card__value"><?php echo wpdmpp_price_format( $total_sales, true, true ); ?></div>
    </div>
    <div class="wpe-card" title="<?php echo esc_attr( sprintf( __( 'After %s%% site commission deducted', 'wpdm-premium-packages' ), $commission ) ); ?>">
        <div class="wpe-card__label"><?php esc_html_e( "Earning", "wpdm-premium-packages" ); ?></div>
        <div class="wpe-card__value"><?php echo wpdmpp_price_format( $total_earning, true, true ); ?></div>
    </div>
    <div class="wpe-card">
        <div class="wpe-card__label"><?php esc_html_e( "Withdrawn", "wpdm-premium-packages" ); ?></div>
        <div class="wpe-card__value" id="wa"><?php echo wpdmpp_price_format( $total_withdraws, true, true ); ?></div>
    </div>
    <div class="wpe-card">
        <div class="wpe-card__label"><?php esc_html_e( "Pending", "wpdm-premium-packages" ); ?></div>
        <div class="wpe-card__value"><?php echo wpdmpp_price_format( $pending_balance, true, true ); ?></div>
    </div>
</div>

<div class="wpe-balance">
    <div class="wpe-balance__main">
        <div class="wpe-balance__label"><?php esc_html_e( "Available Balance", "wpdm-premium-packages" ); ?></div>
        <div class="wpe-balance__amount" id="mb"><?php echo wpdmpp_price_format( $matured_balance, true, true ); ?></div>
    </div>
    <button type="button" id="wd-open" <?php disabled( $matured_balance <= 0 ); ?> class="wpe-btn"><?php esc_html_e( 'Withdraw Funds', WPDMPP_TEXT_DOMAIN ); ?></button>
</div>

<table class="wpe-table" id="earnings">
    <thead>
    <tr>
        <th><?php _e( "Date", "wpdm-premium-packages" ); ?></th>
        <th><?php _e( "Item", "wpdm-premium-packages" ); ?></th>
        <th><?php _e( "Quantity", "wpdm-premium-packages" ); ?></th>
        <th><?php _e( "Price", "wpdm-premium-packages" ); ?></th>
        <th><?php _e( "Commission", "wpdm-premium-packages" ); ?></th>
        <th><?php _e( "Earning", "wpdm-premium-packages" ); ?></th>
    </tr>
    </thead>
    <tbody>
	<?php foreach ( $sales as $sale ) {
		$sale->site_commission = $sale->site_commission ? $sale->site_commission : $sale->price * $commission / 100; ?>
        <tr>
            <td><?php echo wp_date( "Y-m-d H:i", $sale->date ); ?></td>
            <td><?php echo $sale->post_title; ?></td>
            <td><?php echo $sale->quantity; ?></td>
            <td><?php echo wpdmpp_price_format( $sale->price, true, true ); ?></td>
            <td><?php echo wpdmpp_price_format( $sale->site_commission, true, true ); ?></td>
            <td><?php echo wpdmpp_price_format( $sale->price - $sale->site_commission, true, true ); ?></td>
        </tr>
	<?php } ?>
    </tbody>
    <tfoot>
    <tr>
        <th colspan="3"></th>
        <th><?php echo wpdmpp_price_format( $total_sales, true, true ); ?></th>
        <th><?php echo wpdmpp_price_format( $total_commission, true, true ); ?></th>
        <th><?php echo wpdmpp_price_format( $total_earning, true, true ); ?></th>
    </tr>
    </tfoot>
</table>

<script>
    jQuery(function ($) {
        var cs = '<?php echo esc_js( wpdmpp_currency_sign() ); ?>',
            mb = <?php echo number_format( $matured_balance, 2, '.', '' ); ?>,
            wd = <?php echo number_format( $total_withdraws, 2, '.', '' ); ?>;
        var wdModalHtml = <?php echo wp_json_encode( $wd_modal_inner ); ?>;

        // WPDM native dialog system (download-manager/assets/modal)
        var dlg = (typeof WPDM !== 'undefined' && WPDM.dialog) ? WPDM.dialog : (typeof WPDMDialog !== 'undefined' ? WPDMDialog : null);

        function closeWdDialog() {
            $('#wd-dialog .wpdm-dialog__close').trigger('click');
        }

        $('body').on('click', '#wd-open', function () {
            if (!dlg) return;
            dlg.show({
                id: 'wd-dialog',
                title: '<?php echo esc_js( __( 'Withdrawal Request', WPDMPP_TEXT_DOMAIN ) ); ?>',
                icon: false,
                size: 'md',
                content: wdModalHtml,
                backdrop: 'static'
            });
        });

        $('body').on('click', '#wd-cancel', function () {
            closeWdDialog();
        });

        $('body').on('click', '.pom', function () {
            var wam = $('#withdraw_amount');
            var min = $(this).data('min');
            wam.attr('min', min);
            if (parseFloat(wam.val()) < parseFloat(min)) wam.val(min);
        });

        $('body').on('submit', '#wreqform', function (e) {
            e.preventDefault();
            WPDM.blockUI('#wreqform');
            $.ajax({
                url: location.href,
                type: 'post',
                dataType: 'json',
                data: $(this).serialize(),
                success: function (res) {
                    WPDM.unblockUI('#wreqform');
                    if (res && res.success) {
                        var wa = parseFloat($('#withdraw_amount').val());
                        mb = mb - wa;
                        wd = wd + wa;
                        $('#mb').html(cs + mb.toFixed(2));
                        $('#wa').html(cs + wd.toFixed(2));
                        WPDM.notify(res.msg, 'success', 'top-center', 6000);
                        closeWdDialog();
                    } else {
                        WPDM.notify((res && res.msg) ? res.msg : '<?php echo esc_js( __( "Withdrawal request failed.", "wpdm-premium-packages" ) ); ?>', 'danger', 'top-center', 6000);
                    }
                },
                error: function () {
                    WPDM.unblockUI('#wreqform');
                    WPDM.notify('<?php echo esc_js( __( "Something went wrong. Please try again.", "wpdm-premium-packages" ) ); ?>', 'danger', 'top-center', 6000);
                }
            });
            return false;
        });
    });
</script>
