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
?>

<div class="row">
    <div class="col-md-3 center">
        <div class="card">
            <div class="card-header"><?php _e( "Sales:", "wpdm-premium-packages" ); ?></div>
            <div class="card-body lead"><?php echo wpdmpp_price_format( $total_sales, true, true ); ?></div>
        </div>
    </div>
    <div class="col-md-3 center" title="After <?php echo $commission ?>% Site Commission Deducted">
        <div class="card">
            <div class="card-header"><?php _e( "Earning:", "wpdm-premium-packages" ); ?></div>
            <div class="card-body lead"><?php echo wpdmpp_price_format( $total_earning, true, true ); ?></div>
        </div>
    </div>
    <div class="col-md-3 center">
        <div class="card">
            <div class="card-header"><?php _e( "Withdrawn:", "wpdm-premium-packages" ); ?></div>
            <div class="card-body lead" id="wa"><?php echo wpdmpp_price_format( $total_withdraws, true, true ); ?></div>
        </div>
    </div>
    <div class="col-md-3 center">
        <div class="card">
            <div class="card-header"><?php _e( "Pending:", "wpdm-premium-packages" ); ?></div>
            <div class="card-body lead"><?php echo wpdmpp_price_format( $pending_balance, true, true ); ?></div>
        </div>
    </div>
    <div class="col-md-12 center">
        <div class="card mt-4 mb-4">
            <div class="card-header"><?php _e( "Balance:", "wpdm-premium-packages" ); ?></div>
            <div class="card-body lead">
                <span id="mb"><?php echo wpdmpp_price_format( $matured_balance, true, true ); ?></span>
                <div class="pull-right">
                    <button data-toggle="modal" <?php if ( $matured_balance <= 0 ){ ?>disabled="disabled" <?php } ?>
                            data-target="#wdmodal" type="button"
                            class="btn btn-info"><?= __( 'Withdraw Funds', WPDMPP_TEXT_DOMAIN ) ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="wdmodal" tabindex="-1" role="dialog" aria-labelledby="modelTitleId" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document" style="width: 350px">
        <div class="modal-content">
                <form id="wreqform" action="" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __( 'Withdrawal Request', WPDMPP_TEXT_DOMAIN ) ?></h5>
                    </div>
                    <div class="modal-body">
	                    <?php
	                    $form = 0;
	                    $billing_info = maybe_unserialize(get_user_meta(get_current_user_id(), 'user_billing_shipping', true));
	                    $billing_info = wpdm_valueof($billing_info, "billing");
	                    $user_accounts = get_user_meta( get_current_user_id(), '__wpdmpp_payment_account', true );
	                    $active_pom = get_option("wpdmpp_active_pom", []);
	                    if(!is_array($active_pom)) $active_pom = [];
	                    $updatepoi = WPDM()->authorDashboard->url(['adb_page' => 'withdraws']);

	                    if ( !is_array($billing_info) ||
                             wpdm_valueof($billing_info,'first_name') == '' ||
	                         wpdm_valueof($billing_info,'last_name') == '' ||
	                         wpdm_valueof($billing_info, 'address_1') . wpdm_valueof($billing_info, 'address_2') == '' ||
	                         wpdm_valueof($billing_info,'postcode') == '' ||
	                         wpdm_valueof($billing_info,'state') . wpdm_valueof($billing_info,'city') == ''
	                    ) {

		                    $updatebilling = wpdm_user_dashboard_url( array( 'udb_page' => 'edit-profile' ) );
		                    \WPDM\__\Messages::warning( "Critical billing info is missing. Please update your billing info to generate invoice properly.<br style='margin-bottom: 10px;display: block'/><a class='btn btn-warning'  href='$updatebilling'>Update Billing Info</a>", 0 );
	                    } else if(!$user_accounts) {
		                    \WPDM\__\Messages::warning( "Critical payout info is missing. Please update your payout info to withdraw your fund.<br style='margin-bottom: 10px;display: block'/><a class='btn btn-warning' href='$updatepoi'>Update Payout Info</a>", 0 );
	                    } else { $form = 1;
	                    ?>
                        <input type="hidden" name="withdraw" value="1">

                        <div class="form-group">
                            <strong class="d-block mb-2"><?= __( 'Payment Option', WPDMPP_TEXT_DOMAIN ) ?></strong>
                            <div class="list-group">
								<?php foreach ( WPDMPP()->withdraws->getPayoutMethods() as $method ) {
                                    if($method['active']) {
                                    ?>
                                    <label class="list-group-item  d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto" style="line-height: 1.2">
                                        <strong class="fw-bold"><?= $method['name'] ?></strong><br/>
                                        <small class="text-muted">Min. Amount: <?= wpdmpp_price_format($method['min']) ?></small>
                                        </div>
                                        <div>
                                            <?php if(wpdm_valueof($user_accounts, $method['id']) !== '') { ?>
                                            <input required="required" class="form-control pom"
                                                   data-min="<?= $method['min'] ?>" type="radio"
                                                   name="payout_method"
                                                   value="<?= $method['id'] ?>">
                                            <?php } else { ?>
                                                <a href="<?= $updatepoi ?>" class="ttip btn btn-info btn-sm mt-1" title="<?= sprintf(__('You need to add your %s account before send withdrawal request using %s', WPDMPP_TEXT_DOMAIN), $method['name'], $method['name']) ?>"><?= __('Configure', WPDMPP_TEXT_DOMAIN) ?></a>
                                            <?php } ?>
                                        </div>
                                    </label>
								<?php }} ?>

                            </div>
                        </div>
                        <div class="form-group">
                            <strong class="d-block mb-2"><?= __( 'Amount', WPDMPP_TEXT_DOMAIN ) ?></strong>
                            <input type="number" name="withdraw_amount" id="withdraw_amount"
                                   required="required"
                                   value="<?php echo floor( $matured_balance ); ?>"
                                   min="10"
                                   max="<?php echo floor( $matured_balance ); ?>" class="form-control" id="wamt">

                        </div>

	                    <?php } ?>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('Close', WPDMPP_TEXT_DOMAIN)?></button>
                        <?php if($form === 1) { ?>
                        <button type="submit" class="btn btn-primary"><?= __('Send Request', WPDMPP_TEXT_DOMAIN)?></button>
                        <?php } ?>
                    </div>
                </form>

        </div>

    </div>
</div>
<table class="table table-striped panel" id="earnings">
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
        var cs = '<?php echo wpdmpp_currency_sign(); ?>', mb = <?php echo number_format( $matured_balance, 2 ); ?>,
            wd = <?php echo number_format( $total_withdraws, 2 ); ?>;

        $('body').on('click', '.pom', function (e) {
            let wam = $('#withdraw_amount');
            let min = $(this).data('min');
            wam.attr('min', min);
            if (wam.val() < min) wam.val(min);

        });

        $('#wreqform').submit(function () {
            WPDM.blockUI('#wreqform');
            $(this).ajaxSubmit({
                success: function (res) {
                    WPDM.unblockUI('#wreqform');
                    if (res === 'denied') {
                        alert('<?php _e( "Request denied. Matured balance is 0!", "wpdm-premium-packages" ); ?>');
                        $('#wreqb').attr('disabled', 'disabled').html("<i class='fa fa-check-circle-o'></i>");
                    } else {
                        $('#wnotice .modal-title').html('Great!');
                        $('#wnotice .modal-body').html(res)
                        var wa = parseFloat($('#withdraw_amount').val());
                        var rb = mb - wa;
                        mb = rb;
                        wd += wa;
                        $('#mb').html(cs + rb.toFixed(2));
                        $('#wa').html(cs + wd.toFixed(2));
                        if(res.success) {
                            WPDM.notify(res.msg, 'success', 'top-center', 6000);
                            $('#wdmodal').modal('hide');
                        }
                        else
                            WPDM.notify(res.msg, 'danger', 'top-center', 6000);
                    }
                }
            });
            return false;
        });
    });
</script>
