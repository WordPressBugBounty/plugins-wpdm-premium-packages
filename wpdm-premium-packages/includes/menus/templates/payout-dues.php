<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
global $current_user;
$current_user = wp_get_current_user();
$sql = "select * from {$wpdb->prefix}ahm_withdraws where status<>1 order by date desc";
$payouts = $wpdb->get_results($sql);
//($payout->amount-($payout->amount*($comission[$userrole]/100)))
?>
<div class="panel panel-default">
<table cellspacing="0" class="table table-striped">
    <thead>
    <tr>
        <th><?php _e("Name","wpdm-premium-packages");?></th>
        <th><?php _e("Payment Account","wpdm-premium-packages");?></th>
        <th><?php _e("Amount","wpdm-premium-packages");?></th>
        <th><?php _e("Status","wpdm-premium-packages");?></th>
        <th style="width: 100px;"><?php _e("Action","wpdm-premium-packages");?></th>
    </tr>
    </thead>
    <tbody>
    <?php

    foreach($payouts as $payout){
        $userrole = get_userdata($payout->uid)->roles[0];

        $pstatus = "";

        if( $payout->status == 0 ) { $pstatus = "Pending"; $pstatusa = "Paid"; }

	    $payment_account = WPDMPP()->withdraws->getPaymentAccount($payout);
	    $payout_method = $payment_account['method'];
	    $currency_sign = wpdmpp_currency_sign();
        echo "<tr><td><a href='user-edit.php?user_id={$payout->uid}' >".get_userdata($payout->uid)->display_name."</a></td><td>{$payment_account['name']} [ {$payment_account['account']} ]</td><td >{$currency_sign}{$payout->amount}</td><td >{$pstatus}</td><td >".$payout_method->payoutLink($payout, $payment_account)."</td></tr>";
    }
    ?>
    </tbody>
</table>
</div>
