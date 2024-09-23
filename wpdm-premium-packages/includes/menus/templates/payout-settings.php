<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_pom = get_option("wpdmpp_active_pom", []);
if(!is_array($active_pom)) $active_pom = [];

?>
<form id="posettings" method="post">
    <input type="hidden" name="action" value="wpdmpp_payout_settings"/>
    <input type="hidden" name="__wpdmpp_payout" value="<?= wp_create_nonce( WPDM_PRI_NONCE ); ?>"/>
    <div class="panel panel-default">
        <div class="panel-heading"><?php _e( "Commissions", "wpdm-premium-packages" ); ?></div>
        <table class="table table-striped">
            <tr>
                <th align="left"><?php _e( "Role", "wpdm-premium-packages" ); ?></th>
                <th align="left" style="width: 130px"><?php _e( "Commission (%)", "wpdm-premium-packages" ); ?></th>
            </tr>
            <tr>
                <td><?php _e( "Default", "wpdm-premium-packages" ); ?> </td>
                <td><input class="form-control input-sm" style="width: 80px" type="number" size="8"
                           name="comission[default]"
                           value="<?php echo isset( $comission['default'] ) ? $comission['default'] : ''; ?>"></td>
            </tr>
			<?php
			global $wp_roles;
			$roles = array_reverse( $wp_roles->role_names );
			foreach ( $roles as $role => $name ) {
				if ( isset( $currentAccess ) ) {
					$sel = ( in_array( $role, $currentAccess ) ) ? 'checked' : '';
				} ?>
                <tr>
                    <td><?php echo $name; ?> (<?php echo $role; ?>)</td>
                    <td><input type="number" class="form-control input-sm" style="width: 80px" size="8"
                               name="comission[<?php echo $role; ?>]"
                               value="<?php echo ( is_array( $comission ) && isset( $comission[ $role ] ) ) ? (double) $comission[ $role ] : ''; ?>">
                    </td>
                </tr>
			<?php } ?>
            <tr>
                <td colspan="2"><input type="submit" class="btn btn-primary" name="psub" value="<?=__('Save Changes', WPDMPP_TEXT_DOMAIN)?>"></td>
            </tr>
        </table>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><?php _e( "Payout Methods", "wpdm-premium-packages" ); ?></div>

            <table class="table table-striped">
                <thead>
                <tr>
                    <th style="width: 50px;"><?= __('Active', WPDMPP_TEXT_DOMAIN)?></th>
                    <th><?= __( 'Payout Method', WPDMPP_TEXT_DOMAIN ) ?></th>
                    <th><?= __( 'Min Amount', WPDMPP_TEXT_DOMAIN ) ?></th>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( WPDMPP()->withdraws->getPayoutMethods() as $method ) { ?>
                    <tr>
                        <td><input <?php checked(true, in_array($method['id'], $active_pom)) ?> type="checkbox" name="active_pom[]" value="<?= $method['id'] ?>"></td>
                        <td><?= $method['name'] ?></td>
                        <td style="width: 150px">
                            <div class="input-group">
                                <div class="input-group-addon"><?= wpdmpp_currency_sign() ?></div>
                                <input class="form-control input-sm" style="width: 80px;display: inline" type="number"
                                       name="payout_min_amount[<?= $method['id'] ?>]" value="<?php echo (int)wpdm_valueof($payout_min_amount, $method['id']); ?>">
                            </div>
                        </td>
                    </tr>
				<?php }
				?>
                </tbody>
            </table>
        <div class="panel-footer">
            <input type="submit" class="btn btn-primary" name="psub" value="<?=__('Save Changes', WPDMPP_TEXT_DOMAIN)?>">
        </div>
    </div>
    <div class="panel panel-default">
        <div class="panel-heading"><?php _e( "Payout Duration", "wpdm-premium-packages" ); ?></div>
        <div class="panel-body">

			<?php _e( "Duration of payout to mature :", "wpdm-premium-packages" ); ?>
            <input min="0" class="form-control input-sm" style="width: 80px;display: inline" type="number"
                   name="payout_duration"
                   value="<?php echo $payout_duration; ?>"> <?php _e( "Days", "wpdm-premium-packages" ); ?>
            <br/><br/><input type="submit" class="btn btn-primary" name="psub" value="<?=__('Save Changes', WPDMPP_TEXT_DOMAIN)?>">

        </div>
    </div>
</form>

<script>
    jQuery($ => {
        $('#posettings').submit(e => {
            e.preventDefault();
            WPDM.blockUI('#posettings');
            $('#posettings').ajaxSubmit({
                url: ajaxurl,
                success: res => {
                    WPDM.notify(res.msg, 'success', 'top-center', 7000);
                    WPDM.unblockUI('#posettings');
                }
            });

        });
    });
</script>