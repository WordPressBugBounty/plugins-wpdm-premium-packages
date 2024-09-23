<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$sql = "select * from {$wpdb->prefix}ahm_withdraws order by date desc";
$payouts = $wpdb->get_results($sql);
?>
<form action="" method="post">
    <div class="panel panel-default">
        <div class="panel-heading">
            <select name="payout_status" class="form-control wpdm-custom-select" style="display: inline-block;width: 200px">
                <option value="-1"><?php _e('Payout Status:','wpdm-premium-packages'); ?></option>
                <option value="0"><?php _e('Pending','wpdm-premium-packages'); ?></option>
                <option value="1"><?php _e('Completed','wpdm-premium-packages'); ?></option>
                <option value="2"><?php _e('Cancel','wpdm-premium-packages'); ?></option>
            </select>
            <button type="submit" name="pschange" class="btn btn-info"><?php _e('Apply', 'wpdm-premium-packages');  ?></button>
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
	        echo "<tr><td><a href='user-edit.php?user_id={$payout->uid}' >".get_userdata($payout->uid)->display_name."</a></td><td>{$payment_account['name']} [ {$payout->payment_account} ]</td><td >{$currency_sign}{$payout->amount}</td><td ><button type='button' class='pull-right btn btn-xs btn-primary btn-payout-status ttip' title='Change Status' data-status='{$sta}' data-id='{$payout->id}'><i class='fas fa-sync'></i></button><span id='pstatus-{$payout->id}'>" . __($st, "wpdm-premium-packages") . "</span></td></tr>";
        }
        ?>

        </tbody>
    </table>
    </div>
</form>

<script>
    jQuery(function ($) {
        $('.btn-payout-status').on('click', function () {

            var $this = $(this);
            var $pst = $('#pstatus-'+$this.data('id'));
            var _id = $(this).data('id');
            $this.html("<i class='fas fa-sync fa-spin'></i>");

            WPDM.confirm('<?= __('Payout Request Status!', WPDM_TEXT_DOMAIN); ?>', '<?= __('Changing payout status! Are you sure?', WPDM_TEXT_DOMAIN); ?>', [
                {
                    label: 'Yes, Confirm!',
                    class: 'btn btn-danger',
                    callback: function () {
                        let $mod = $(this);
                        $mod.find('.modal-body').html("<i class='fas fa-sun fa-spin'></i> Processing...");
                        $.post(ajaxurl, {action: 'wpdmpp_change_payout_status', id: _id, __psnonce: '<?php echo wp_create_nonce(NONCE_KEY); ?>'}, function (res) {
                            $this.html("<i class='fas fa-sync'></i>");
                            $pst.html(res.status);
                            $mod.modal('hide');
                            $this.html("<i class='fas fa-sync'></i>");
                        });
                    }
                },
                {
                    label: 'No, Later',
                    class: 'btn btn-info',
                    callback: function () {
                        $(this).modal('hide');
                        $this.html("<i class='fas fa-sync'></i>");
                    }
                }
            ]);
        });
    });
</script>
