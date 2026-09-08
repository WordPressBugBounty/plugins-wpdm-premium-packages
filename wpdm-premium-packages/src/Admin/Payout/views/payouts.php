<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Verify nonce and capability for all payout operations
if ( isset($_POST['psub']) && wp_verify_nonce( wpdm_query_var('_wpdmpp_payout_nonce'), 'wpdmpp_payout_settings' ) && current_user_can( WPDM_ADMIN_CAP ) ) {
    update_option("wpdmpp_payout_duration", absint( $_POST['payout_duration']) );
}

if ( isset($_POST['csub']) && wp_verify_nonce( wpdm_query_var('_wpdmpp_payout_nonce'), 'wpdmpp_payout_settings' ) && current_user_can( WPDM_ADMIN_CAP ) ) {
    $comission_data = isset($_POST['comission']) && is_array($_POST['comission']) ? $_POST['comission'] : array();
    $comission_data = array_map('floatval', $comission_data);
    update_option("wpdmpp_user_comission", $comission_data);
}

$payout_min_amount = get_option("wpdmpp_payout_min_amount", ['paypal' => 10, 'payoneer' => 50]);
$payout_duration = (int)get_option("wpdmpp_payout_duration", 0);
$comission = get_option("wpdmpp_user_comission");
?>


<!-- Empty-state styling, shared by the payout tabs below. -->
<style>
    .w3eden .wpdmpp-po-empty td { padding: 0; border: 0; background: transparent; }
    .w3eden .wpdmpp-po-empty__inner { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 54px 20px; text-align: center; }
    .w3eden .wpdmpp-po-empty__icon { display: flex; align-items: center; justify-content: center; width: 52px; height: 52px; border-radius: 14px; background: #f1f5f9; color: #94a3b8; }
    .w3eden .wpdmpp-po-empty__icon svg { width: 24px; height: 24px; }
    .w3eden .wpdmpp-po-empty__title { font-size: 15px; font-weight: 600; color: #1e293b; }
    .w3eden .wpdmpp-po-empty__hint { font-size: 13px; color: #94a3b8; max-width: 420px; }
</style>
<div class="w3eden payout-entries">
    <?php
    $menus = [
        ['link' => "#all_payouts", "name" => __("All Payouts", "wpdm-premium-packages"), "active" => true, 'attrs' => ['data-toggle' => 'tab']],
        ['link' => "#dues", "name" => __("Dues", "wpdm-premium-packages"), "active" => false, 'attrs' => ['data-toggle' => 'tab']],
        ['link' => "#payout_settings", "name" => __("Payout Settings", "wpdm-premium-packages"), "active" => false, 'attrs' => ['data-toggle' => 'tab']],
    ];

    WPDM()->admin->pageHeader(esc_attr__( "Payouts", "wpdm-premium-packages" ), 'credit-card fas color-purple', $menus);
    ?>

    <div class="wpdm-admin-page-content">

        <div class="tab-content panel-body-np">
            <div class="tab-pane active" id="all_payouts">
                <?php include_once("payout-all.php"); ?>
            </div>
            <div class="tab-pane" id="dues">
                <?php include_once("payout-dues.php"); ?>
            </div>
            <div class="tab-pane" id="payout_settings">
                <?php include_once("payout-settings.php"); ?>
            </div>
        </div>

</div>
</div>
<style>div.notice{ display: none; }</style>
