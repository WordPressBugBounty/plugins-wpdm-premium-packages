<?php
/**
 * Find guest order form template
 *
 * Uses WPDM core controls (.form-control, .btn, .alert) so the lookup form
 * stays consistent with the billing modal and the wider WPDM design system.
 *
 * This template can be overridden by copying it to yourtheme/download-manager/partials/guest-order-search-form.php.
 *
 * @version     3.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$wpdmpp_go_email = \WPDM\__\Session::get('order_email');
$wpdmpp_go_order = \WPDM\__\Session::get('guest_order');
?>
<div id="gonotice" class="wpdmpp-go__notice"></div>

<form method="post" id="goform" class="wpdmpp-go__access" novalidate>
    <input type="hidden" name="__wpdmpp_go_nonce" value="<?php echo wp_create_nonce(NONCE_KEY); ?>" />

    <div class="wpdmpp-go__access-head">
        <span class="wpdmpp-go__access-icon"><?php echo \WPDMPP\UI\Icons::get('shopping-bag', 24); ?></span>
        <div>
            <h2 class="wpdmpp-go__access-title"><?php _e('Guest Order Access', 'wpdm-premium-packages'); ?></h2>
            <p class="wpdmpp-go__access-sub"><?php _e('Enter the email and order ID from your receipt to view downloads and your invoice — no account needed.', 'wpdm-premium-packages'); ?></p>
        </div>
    </div>

    <div class="wpdmpp-go__fields">
        <div class="wpdmpp-go__field">
            <label for="goemail"><?php _e('Order Email', 'wpdm-premium-packages'); ?></label>
            <input type="email" required id="goemail" name="__wpdmpp_go[email]" class="form-control" autocomplete="email"
                   placeholder="<?php esc_attr_e('you@example.com', 'wpdm-premium-packages'); ?>"
                   value="<?php echo esc_attr($wpdmpp_go_email); ?>">
        </div>
        <div class="wpdmpp-go__field">
            <label for="goorder"><?php _e('Order ID', 'wpdm-premium-packages'); ?></label>
            <input type="text" required id="goorder" name="__wpdmpp_go[order]" class="form-control"
                   placeholder="<?php esc_attr_e('e.g. WPDMPP65A4B3C2', 'wpdm-premium-packages'); ?>"
                   value="<?php echo esc_attr($wpdmpp_go_order); ?>">
        </div>
    </div>

    <div class="wpdmpp-go__access-foot">
        <button type="submit" class="btn btn-primary" id="goproceed">
            <?php echo \WPDMPP\UI\Icons::get('search', 16); ?>
            <span><?php _e('Find My Order', 'wpdm-premium-packages'); ?></span>
        </button>
    </div>
</form>

<script>
    jQuery(function($){
        var goerrors = [];
        goerrors['nosess'] = "<?php echo esc_js(__('Session was expired. Please try again','wpdm-premium-packages')); ?>";
        goerrors['noordr'] = "<?php echo esc_js(__('Order not found. Please re-check your email and order ID.','wpdm-premium-packages')); ?>";
        goerrors['nogues'] = "<?php echo esc_js(__('This order is linked to an account. Please log in with that account to access it.','wpdm-premium-packages')); ?>";
        goerrors['ratelimit'] = "<?php echo esc_js(__('Too many attempts. Please try again in {minutes} minute(s).','wpdm-premium-packages')); ?>";

        function gonotice(type, icon, msg){
            $('#gonotice').html('<div class="alert alert-' + type + '">' + icon + ' <span>' + msg + '</span></div>');
        }

        var iconClock = "<?php echo addslashes(\WPDMPP\UI\Icons::get('clock', 16)); ?>";
        var iconAlert = "<?php echo addslashes(\WPDMPP\UI\Icons::get('times-circle', 16)); ?>";

        $('#goform').submit(function(){
            var $btn = $('#goproceed');
            var gop = $btn.html();
            $btn.prop('disabled', true).html('<?php echo addslashes(\WPDMPP\UI\Icons::spinner(16)); ?> <span><?php echo esc_js(__('Searching…','wpdm-premium-packages')); ?></span>');
            $('#gonotice').empty();
            $(this).ajaxSubmit({
                success: function(res){
                    if(res.match(/nosess/))        gonotice('danger', iconAlert, goerrors['nosess']);
                    else if(res.match(/noordr/))   gonotice('danger', iconAlert, goerrors['noordr']);
                    else if(res.match(/nogues/))   gonotice('warning', iconAlert, goerrors['nogues']);
                    else if(res.match(/^ratelimit:/)) {
                        var seconds = parseInt(res.split(':')[1]) || 60;
                        var minutes = Math.ceil(seconds / 60);
                        gonotice('warning', iconClock, goerrors['ratelimit'].replace('{minutes}', minutes));
                    }
                    else if(res.match(/success/)) {
                        location.href = '<?php echo esc_url( wpdmpp_guest_order_page() ); ?>';
                        return;
                    }
                    $btn.prop('disabled', false).html(gop);
                }
            });
            return false;
        });
    });
</script>
