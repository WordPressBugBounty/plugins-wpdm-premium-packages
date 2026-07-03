<?php
/**
 * Edit Billing Info dialog for the guest orders page.
 *
 * Rendered with the WPDM modal library (WPDM.dialog / WPDMDialog from
 * download-manager/assets/modal/wpdm-modal.js). The form lives in an inert
 * <script type="text/template"> block and is injected into the dialog body on
 * open, so there are no duplicate element IDs and no stale event bindings.
 *
 * This template can be overridden by copying it to yourtheme/download-manager/partials/guest-order-billing-info.php.
 *
 * @version     2.0.0
 */

use WPDM\__\Crypt;
use WPDM\__\Session;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (! Session::get('guest_order')) {
    return;
}

$billing = $sbilling = array(
    'first_name'  => '',
    'last_name'   => '',
    'company'     => '',
    'address_1'   => '',
    'address_2'   => '',
    'city'        => '',
    'postcode'    => '',
    'country'     => '',
    'state'       => '',
    'email'       => '',
    'order_email' => '',
    'phone'       => '',
    'taxid'       => '',
);
$sbilling = is_object($order) && method_exists($order, 'getBillingInfo') ? (array) $order->getBillingInfo() : array();
$billing  = shortcode_atts($billing, $sbilling);

$allowed_countries = get_wpdmpp_option('allow_country');
$all_countries     = wpdmpp_countries();
$tax_class         = wpdmpp_tax_active() ? 'calculate-tax' : '';
?>
<style>
    .wpdm-dialog .control-label { font-size: 10pt; margin-bottom: 4px !important; display:block; }
    /* overflow-x:hidden contains the Bootstrap .row negative margins (-15px) so
       the dialog body doesn't get a horizontal scrollbar. */
    .wpdmpp-billing-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }
    .wpdmpp-billing-fields {
        max-height: calc(100vh - 200px) !important;
        overflow-y: auto;
    }
</style>

<script type="text/template" id="wpdmpp-billing-tpl">
    <form id="billing-info-form" method="post">
        <input type="hidden" name="oid" value="<?php echo Crypt::encrypt( Session::get('guest_order')); ?>" />
        <div class="wpdmpp-billing-fields p-3">
            <!-- name -->
            <div class="form-group">
                <div class="controls row">
                    <div class="col-md-6">
                        <label class="control-label"><?php echo __("First Name", "wpdm-premium-packages"); ?> <span class="required" title="<?php esc_attr_e('Required', 'wpdm-premium-packages'); ?>">*</span></label>
                        <input id="f-name" value="<?php echo esc_attr($billing['first_name']); ?>" name="billing[first_name]" required="required" type="text" placeholder="<?php esc_attr_e("First Name", "wpdm-premium-packages"); ?>" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="control-label"><?php echo __("Last Name", "wpdm-premium-packages"); ?> <span class="required" title="<?php esc_attr_e('Required', 'wpdm-premium-packages'); ?>">*</span></label>
                        <input id="l-name" value="<?php echo esc_attr($billing['last_name']); ?>" name="billing[last_name]" type="text" required="required" placeholder="<?php esc_attr_e("Last Name", "wpdm-premium-packages"); ?>" class="form-control">
                    </div>
                </div>
            </div>
            <!-- company -->
            <div class="form-group">
                <label class="control-label"><?php echo __("Company Name", "wpdm-premium-packages"); ?></label>
                <input value="<?php echo esc_attr($billing['company']); ?>" name="billing[company]" type="text" placeholder="<?php esc_attr_e("Company (optional)", "wpdm-premium-packages"); ?>" class="form-control">
            </div>
            <!-- address 1 -->
            <div class="form-group">
                <label class="control-label"><?php echo __("Address Line 1", "wpdm-premium-packages"); ?> <span class="required" title="<?php esc_attr_e('Required', 'wpdm-premium-packages'); ?>">*</span></label>
                <input id="address-line1" name="billing[address_1]" value="<?php echo esc_attr($billing['address_1']); ?>" type="text" required="required" placeholder="<?php esc_attr_e("address line 1", "wpdm-premium-packages"); ?>" class="form-control">
            </div>
            <!-- address 2 -->
            <div class="form-group">
                <label class="control-label"><?php echo __("Address Line 2", "wpdm-premium-packages"); ?></label>
                <input id="address-line2" name="billing[address_2]" value="<?php echo esc_attr($billing['address_2']); ?>" type="text" placeholder="<?php esc_attr_e("address line 2", "wpdm-premium-packages"); ?>" class="form-control">
            </div>
            <div class="form-group">
                <div class="row">
                    <!-- postcode -->
                    <div class="col-md-6">
                        <label class="control-label"><?php echo __("Postcode/Zip", "wpdm-premium-packages"); ?> <span class="required" title="<?php esc_attr_e('Required', 'wpdm-premium-packages'); ?>">*</span></label>
                        <input id="postal-code" name="billing[postcode]" value="<?php echo esc_attr($billing['postcode']); ?>" type="text" required="required" placeholder="<?php esc_attr_e("Postcode/Zip", "wpdm-premium-packages"); ?>" class="form-control">
                    </div>
                    <!-- city -->
                    <div class="col-md-6">
                        <label class="control-label"><?php echo __("Town/City", "wpdm-premium-packages"); ?> <span class="required" title="<?php esc_attr_e('Required', 'wpdm-premium-packages'); ?>">*</span></label>
                        <input id="city" value="<?php echo esc_attr($billing['city']); ?>" name="billing[city]" type="text" required="required" placeholder="<?php esc_attr_e("Town/City", "wpdm-premium-packages"); ?>" class="form-control">
                    </div>
                    <!-- region -->
                    <div class="col-md-6">
                        <label class="control-label"><?php echo __("State / Province", "wpdm-premium-packages"); ?> <span class="required" title="<?php esc_attr_e('Required', 'wpdm-premium-packages'); ?>">*</span></label>
                        <select id="region" name="billing[state]" class="custom-select wpdm-custom-select form-control <?php echo esc_attr($tax_class); ?>"></select>
                        <input id="region-txt" style="display:none;" name="billing[state]" value="<?php echo esc_attr($billing['state']); ?>" type="text" placeholder="<?php esc_attr_e("state / province / region", "wpdm-premium-packages"); ?>" class="form-control <?php echo esc_attr($tax_class); ?>">
                    </div>
                    <!-- country -->
                    <div class="col-md-6">
                        <label class="control-label"><?php echo __("Country", "wpdm-premium-packages"); ?> <span class="required" title="<?php esc_attr_e('Required', 'wpdm-premium-packages'); ?>">*</span></label>
                        <select id="country" name="billing[country]" required="required" class="form-control custom-select wpdm-custom-select" data-live-search="true">
                            <option value=""><?php echo __("--Select Country--", "wpdm-premium-packages"); ?></option>
                            <?php foreach ((array) $allowed_countries as $country_code) {
                                $selected = ($billing['country'] == $country_code) ? ' selected="selected"' : '';
                                ?>
                                <option value="<?php echo esc_attr($country_code); ?>"<?php echo $selected; ?>><?php echo esc_html($all_countries[$country_code] ?? $country_code); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
            <!-- phone / tax id -->
            <div class="form-group">
                <div class="row">
                    <div class="col-md-6">
                        <label class="control-label" for="billing_phone"><?php _e("Phone", "wpdm-premium-packages"); ?></label>
                        <input type="text" value="<?php echo esc_attr($billing['phone']); ?>" id="billing_phone" name="billing[phone]" class="input-text form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="control-label" for="billing_tin"><?php _e("Tax ID #", "wpdm-premium-packages"); ?></label>
                        <input type="text" value="<?php echo esc_attr($billing['taxid']); ?>" id="billing_tin" name="billing[taxid]" class="input-text form-control">
                    </div>
                </div>
            </div>
            <!-- email -->
            <div class="form-group">
                <label class="control-label"><?php echo __("Email Address", "wpdm-premium-packages"); ?> <span class="required" title="<?php esc_attr_e('Required', 'wpdm-premium-packages'); ?>">*</span></label>
                <input type="email" value="<?php echo esc_attr($billing['order_email']); ?>" required="required" class="form-control" name="billing[order_email]" id="email_m" placeholder="<?php esc_attr_e("Email Address", "wpdm-premium-packages"); ?>">
            </div>
        </div>

        <div class="wpdmpp-billing-actions">
            <button type="button" class="btn btn-secondary" id="billing-cancel-btn"><?php _e("Close", "wpdm-premium-packages"); ?></button>
            <button type="submit" class="btn btn-primary"><?php _e("Save Changes", "wpdm-premium-packages"); ?></button>
        </div>
    </form>
</script>

<script>
    jQuery(function ($) {

        // Open the Edit Billing dialog using the WPDM modal library.
        window.wpdmppOpenBillingDialog = function () {
            if (typeof WPDM === 'undefined' || !WPDM.dialog) {
                return;
            }
            WPDM.dialog.show({
                id: 'wpdmpp-billing-dialog',
                title: '<?php echo esc_js(__("Edit Billing Info", "wpdm-premium-packages")); ?>',
                icon: false,
                size: 'xl',
                backdrop: 'static',
                content: document.getElementById('wpdmpp-billing-tpl').innerHTML
            });
            // show() appends the dialog synchronously; populate the state
            // dropdown for the saved country now that #country exists.
            if (typeof populateStates === 'function') {
                populateStates($('#wpdmpp-billing-dialog #country').val());
            }
        };

        function closeBillingDialog() {
            $('#wpdmpp-billing-dialog .wpdm-dialog__close').trigger('click');
        }

        // Cancel button inside the form closes the dialog (delegated — the form
        // is created dynamically inside the dialog body).
        $('body').on('click', '#billing-cancel-btn', function () {
            closeBillingDialog();
            return false;
        });

        // Save (delegated submit).
        $('body').on('submit', '#billing-info-form', function () {
            var $form = $(this);
            WPDM.blockUI('#billing-info-form');
            $form.ajaxSubmit({
                url: '<?php echo admin_url('admin-ajax.php?action=update_guest_billing'); ?>',
                success: function (res) {
                    WPDM.unblockUI('#billing-info-form');
                    WPDM.notify(`<?php echo \WPDMPP\UI\Icons::get('check-double', 16); ?> ${res}`, 'success', 'top-center', 7000);
                    closeBillingDialog();
                }
            });
            return false;
        });
    });
</script>
