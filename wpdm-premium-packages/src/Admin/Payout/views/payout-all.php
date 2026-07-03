<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$sql = "select * from {$wpdb->prefix}ahm_withdraws order by date desc";
$payouts = $wpdb->get_results($sql);
?>
<form action="" method="post">
    <div class="panel panel-default">
        <div class="panel-heading">
            <select name="payout_status" id="payout-status-filter" class="form-control wpdm-custom-select" style="display: inline-block;width: 200px">
                <option value="-1"><?php _e('All Statuses','wpdm-premium-packages'); ?></option>
                <option value="0"><?php _e('Pending','wpdm-premium-packages'); ?></option>
                <option value="1"><?php _e('Completed','wpdm-premium-packages'); ?></option>
            </select>

        </div>
        <table cellspacing="0" class="table table-striped">
        <thead>
        <tr>
            <th><?php echo __("Name", "wpdm-premium-packages"); ?></th>
            <th><?php _e("Payment Account","wpdm-premium-packages");?></th>
            <th><?php echo __("Amount", "wpdm-premium-packages"); ?></th>
            <th style="width: 150px"><?php echo __("Status", "wpdm-premium-packages"); ?></th>
        </tr>
        </thead>
        <tfoot>
        <tr>
            <th><?php echo __("Name", "wpdm-premium-packages"); ?></th>
            <th><?php _e("Payment Account","wpdm-premium-packages");?></th>
            <th><?php echo __("Amount", "wpdm-premium-packages"); ?></th>
            <th style="width: 150px"><?php echo __("Status", "wpdm-premium-packages"); ?></th>
        </tr>
        </tfoot>
        <tbody>
        <?php
        foreach ($payouts as $payout) {
            $sta = 'Completed';
            if ($payout->status == 0) $st = "Pending"; else if ($payout->status == 1) $st = "Completed";
            if($st == 'Completed') $sta = 'Pending';
	        $payment_account = WPDMPP()->withdraws->getPaymentAccount($payout);
	        $payout_method = wpdm_valueof($payment_account, 'method', 'paypal');
	        $currency_sign = wpdmpp_currency_sign();
            $acc = wpdm_valueof($payment_account, 'account');
	        $sync_icon = \WPDMPP\UI\Icons::get('sync', 16);
	        echo "<tr class='payout-row' data-status='" . (int) $payout->status . "'><td><a href='user-edit.php?user_id={$payout->uid}' >".get_userdata($payout->uid)->display_name."</a></td><td>{$payment_account['name']} [ {$payout->payment_account} ]</td><td >{$currency_sign}{$payout->amount}</td><td ><button type='button' class='pull-right btn btn-xs btn-primary btn-payout-status ttip' title='Change Status' data-status='{$sta}' data-id='{$payout->id}'>{$sync_icon}</button><span id='pstatus-{$payout->id}'>" . __($st, "wpdm-premium-packages") . "</span></td></tr>";
        }
        ?>

        </tbody>
    </table>
    </div>
</form>

<script>
    jQuery(function ($) {

        // Status filter: show only payout rows matching the selected status
        // (-1 = all). Rows carry data-status="0|1" (Pending|Completed).
        function filterPayouts() {
            var val = $('#payout-status-filter').val();
            $('.payout-row').each(function () {
                var $row = $(this);
                $row.toggle(val === '-1' || $row.attr('data-status') === val);
            });
        }
        $('#apply-payout-filter').on('click', filterPayouts);
        $('#payout-status-filter').on('change', filterPayouts);

        $('.btn-payout-status').on('click', function () {

            var $this = $(this);
            var $pst = $('#pstatus-'+$this.data('id'));
            var _id = $(this).data('id');
            $this.html('<?php echo \WPDMPP\UI\Icons::spinner(16); ?>');

            WPDM.confirm('<?= __('Payout Request Status!', WPDM_TEXT_DOMAIN); ?>', '<?= __('Changing payout status! Are you sure?', WPDM_TEXT_DOMAIN); ?>', [
                {
                    label: 'Yes, Confirm!',
                    class: 'btn btn-danger',
                    callback: function () {
                        let $mod = $(this);
                        $mod.find('.modal-body').html('<?php echo \WPDMPP\UI\Icons::spinner(16); ?> Processing...');
                        $.post(ajaxurl, {action: 'wpdmpp_change_payout_status', id: _id, __psnonce: '<?php echo wp_create_nonce(NONCE_KEY); ?>'}, function (res) {
                            $this.html('<?php echo \WPDMPP\UI\Icons::get('sync', 16); ?>');
                            $pst.html(res.status);
                            // Keep the row's data-status in sync so the active filter
                            // stays correct after a toggle.
                            $this.closest('.payout-row').attr('data-status', res.status === 'Completed' ? '1' : '0');
                            filterPayouts();
                            $mod.modal('hide');
                        });
                    }
                },
                {
                    label: 'No, Later',
                    class: 'btn btn-info',
                    callback: function () {
                        $(this).modal('hide');
                        $this.html('<?php echo \WPDMPP\UI\Icons::get('sync', 16); ?>');
                    }
                }
            ]);
        });
    });
</script>
