<?php
if(!defined('ABSPATH')) die('Dream more!');
?>
<form method="post" class="p-0 m-0" id="wpdmpp_poform">
    <?php echo wp_nonce_field(WPDM_PUB_NONCE, '__supanonce'); ?>
    <input type="hidden" name="action" value="wpdmpp_user_payment_options" />
<div class="card mb-3">
    <div class="card-header bg-white"><?php echo __('Payment Accounts', WPDMPP_TEXT_DOMAIN) ?></div>
    <div class="card-body p-0">
        <table class="table table-striped m-0">
            <thead>
            <tr>
                <th style="width: 50px"></th>
                <th><?php _e('Payment Method', WPDMPP_TEXT_DOMAIN); ?></th>
                <th><?= __('Payment Account', WPDMPP_TEXT_DOMAIN) ?></th>
            </tr>
            </thead>

            <tbody>
		    <?php
            foreach ($methods as $method) {
                if($method['active']) {
                ?>
                <tr>
                    <td><img src="<?= $method['icon'] ?>" width="48px" /> </td>
                    <td style="line-height: 1.2">
                        <strong><?php echo $method['name'] ?></strong><br/>
                        <small class="text-muted">Min. Amount: <?= wpdmpp_price_format($method['min']) ?></small>
                    </td>
                    <td><input class="form-control" id="pa<?= $method['id'] ?>" type="text" name="account[<?= $method['id'] ?>]" value="<?= wpdm_valueof($accounts, $method['id'])  ?>" /></td>
                </tr>
		    <?php }} ?>
            </tbody>
        </table>
    </div>
    
    <div class="card-footer text-right bg-white">
        <button class="btn btn-success"><?= __('Save Changes', WPDMPP_TEXT_DOMAIN) ?></button>
    </div>
</div>
</form>

<div class="card">
	<div class="card-header bg-white"><?php echo __('Withdraw Requests', WPDMPP_TEXT_DOMAIN) ?></div>
	<div class="card-body p-0">
        <table class="table table-striped m-0">
            <thead>
            <tr>
                <th>Ref. No.</th>
                <th>Request Date</th>
                <th>Amount</th>
                <th style="width: 100px">Status</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($requests as $request) { ?>
                <tr>
                    <td>#<?php echo $request->id ?></td>
                    <td><?php echo wp_date(get_option('date_format'), $request->date) ?></td>
                    <td><?php echo wpdmpp_price_format($request->amount) ?></td>
                    <td><?php echo $request->status ? 'Paid' : 'Pending' ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
	</div>
</div>


<script>
    jQuery($ => {

        $('#wpdmpp_poform').submit(e => {
            e.preventDefault();
            WPDM.blockUI('#wpdmpp_poform');
            $('#wpdmpp_poform').ajaxSubmit({
                url: wpdm_url.ajax,
                success: res => {
                    WPDM.unblockUI('#wpdmpp_poform');
                    WPDM.notify(res.message, res.type, 'top-center', 7000);
                }
            })
        })

    });
</script>