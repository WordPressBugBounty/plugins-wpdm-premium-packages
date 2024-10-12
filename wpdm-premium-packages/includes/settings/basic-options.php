<?php

use WPDM\__\__;
use WPDM\__\Crypt;
use WPDM\Form\Form;

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wpdmpp-settings-fields">
    <input type="hidden" name="action" value="wpdmpp_save_settings">
    <?php
    global $wpdb;
    $countries = $wpdb->get_results("select * from {$wpdb->prefix}ahm_country order by country_name");
    ?>
    <div class="panel panel-default">
        <div class="panel-heading"><?php _e('Base Country', 'wpdm-premium-packages'); ?></div>
        <div class="panel-body">
            <select class="chosen" name="_wpdmpp_settings[base_country]">
                <option><?php _e('--Select Country--', 'wpdm-premium-packages'); ?></option>
                <?php
                foreach ($countries as $country) {
                    $country->country_name = strtolower($country->country_name);
                    ?>
                    <option value="<?php echo $country->country_code; ?>" <?php selected(isset($settings['base_country']) ? $settings['base_country'] : '', $country->country_code ); ?> >
                        <?php echo ucwords($country->country_name); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
        </div>
    </div>
    <div class="panel panel-default">
        <div class="panel-heading"><?php _e("Allowed Countries", "wpdm-premium-packages"); ?></div>
        <div class="panel-body">
            <ul id="listbox" style="height: 200px;overflow: auto;">
                <li>
                    <label for="allowed_cn"><input type="checkbox" name="allowed_cn_all" id="allowed_cn"/> <?php _e('Select All/None','wpdm-premium-packages'); ?> </label>
                </li>
                <?php
                foreach ($countries as $country) {
                    $country->country_name = strtolower($country->country_name);
                    ?>
                    <li>
                        <label><input <?php
                            $select = '';
                            if (isset($settings['allow_country'])) {
                                foreach ($settings['allow_country'] as $ac) {
                                    if ($ac == $country->country_code) {
                                        $select = 'checked="checked"';
                                        break;
                                    } else
                                        $select = '';
                                }
                            }
                            echo $select;
                            ?> type="checkbox" class="ccb" name="_wpdmpp_settings[allow_country][]"
                               value="<?php echo $country->country_code; ?>"><?php echo " " . ucwords($country->country_name); ?>
                        </label>
                    </li>
                    <?php
                }
                ?>
            </ul>
        </div>
    </div>
    <div class="panel panel-default">
        <div class="panel-heading"><?php _e("Frontend Settings", "wpdm-premium-packages"); ?></div>
        <div class="panel-body">
            <label>
                <input type="checkbox" name="_wpdmpp_settings[billing_address]" <?php if (isset($settings['billing_address']) && $settings['billing_address'] == 1) echo 'checked=checked' ?>
                       value="1"> <?php _e("Ask for billing address on checkout page", "wpdm-premium-packages"); ?>
            </label><br/>
            <label>
                <input type="hidden" name="_wpdmpp_settings[authorize_masterkey]" value="0"/>
                <input type="checkbox" name="_wpdmpp_settings[authorize_masterkey]" <?php if (isset($settings['authorize_masterkey']) && $settings['authorize_masterkey'] == 1) echo 'checked=checked' ?>
                       value="1"> <?php _e("Authorize MasterKey to download premium packages", "wpdm-premium-packages"); ?>
            </label><br/>
            <label>
                <input type="checkbox" name="_wpdmpp_settings[guest_checkout]" <?php if (isset($settings['guest_checkout']) && $settings['guest_checkout'] == 1) echo 'checked=checked' ?>
                          value="1"> <?php _e("Enable guest checkout", "wpdm-premium-packages"); ?>
            </label><br/>
            <input type="hidden" name="_wpdmpp_settings[guest_download]" value="0">
            <label>
                <input type="checkbox" name="_wpdmpp_settings[guest_download]" <?php if (isset($settings['guest_download']) && $settings['guest_download'] == 1) echo 'checked=checked' ?>
                          value="1"> <?php _e("Enable guest download", "wpdm-premium-packages"); ?>
            </label><br/>
            <label><input type="hidden" name="_wpdmpp_settings[disable_multi_file_download]" value="0">
                <input type="checkbox" name="_wpdmpp_settings[disable_multi_file_download]" <?php if (isset($settings['guest_download']) && $settings['disable_multi_file_download'] == 1) echo 'checked=checked' ?>
                       value="1"> <?php _e("Disable multi-file download for purchased items", "wpdm-premium-packages"); ?>
            </label>

            <hr/>

            <label><?php _e("Cart Page :", "wpdm-premium-packages"); ?></label><br>
            <?php
            if ($settings['page_id'])
                $args = array(
                    'show_option_none' => __('None Selected','wpdm-premium-packages'),
                    'name' => '_wpdmpp_settings[page_id]',
                    'selected' => $settings['page_id']
                );
            else
                $args = array(
                    'show_option_none' => __('None Selected','wpdm-premium-packages'),
                    'name' => '_wpdmpp_settings[page_id]'
                );
            wp_dropdown_pages($args);
            ?>
            <div class="form-group">
                <br/>
                <label><?php _e("Checkout Layout:", "wpdm-premium-packages"); ?></label><br>
                <div style="display: flex">
                    <label class="mr-3"><input type="radio" <?php checked(get_wpdmpp_option('checkout_page_style'), '') ?> name="_wpdmpp_settings[checkout_page_style]" value=""> <?php _e("Single Column", "wpdm-premium-packages"); ?></label>
                    <label><input type="radio" name="_wpdmpp_settings[checkout_page_style]" <?php checked(get_wpdmpp_option('checkout_page_style'), '-2col') ?> value="-2col"> <?php _e("2 Columns Extended", "wpdm-premium-packages"); ?></label>
                </div>
            </div>
            <hr/>

            <label><?php _e("Orders Page :", "wpdm-premium-packages"); ?></label><br>
            <?php
            if (isset($settings['orders_page_id']))
                $args = array(
                    'name' => '_wpdmpp_settings[orders_page_id]',
                    'show_option_none' => __('None Selected','wpdm-premium-packages'),
                    'selected' => $settings['orders_page_id']
                );
            else
                $args = array(
                    'show_option_none' => __('None Selected','wpdm-premium-packages'),
                    'name' => '_wpdmpp_settings[orders_page_id]'
                );
            wp_dropdown_pages($args);
            ?>
            <hr/>

            <label><?php _e("Guest Order Page :", "wpdm-premium-packages"); ?></label><br>
            <?php
            if (isset($settings['guest_order_page_id']))
                $args = array(
                    'name' => '_wpdmpp_settings[guest_order_page_id]',
                    'show_option_none' => __('None Selected','wpdm-premium-packages'),
                    'selected' => $settings['guest_order_page_id']
                );
            else
                $args = array(
                    'show_option_none' => __('None Selected','wpdm-premium-packages'),
                    'name' => '_wpdmpp_settings[guest_order_page_id]'
                );
            wp_dropdown_pages($args);
            ?>
            <hr/>

            <label><?php _e("Continue Shopping URL:", "wpdm-premium-packages"); ?></label><br/>
            <input type="text" class="form-control" name="_wpdmpp_settings[continue_shopping_url]" size="50" id="continue_shopping_url" value="<?php echo $settings['continue_shopping_url'] ?>"/>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><?php _e("Purchase Settings", "wpdm-premium-packages"); ?></div>
        <div class="panel-body">
            <label>
                <input  name="_wpdmpp_settings[no_role_discount]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[no_role_discount]" <?php if (isset($settings['no_role_discount']) && $settings['no_role_discount'] == 1) echo 'checked=checked' ?> value="1">
                <?php echo __("Disable user role based discount", "wpdm-premium-packages"); ?>
            </label><br/>
            <label>
                <input  name="_wpdmpp_settings[no_product_coupon]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[no_product_coupon]" <?php if (isset($settings['no_product_coupon']) && $settings['no_product_coupon'] == 1) echo 'checked=checked' ?> value="1">
                <?php echo __("Disable product specific coupon field", "wpdm-premium-packages"); ?>
            </label><br/>
            <label>
                <input  name="_wpdmpp_settings[show_buynow]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[show_buynow]" <?php if (isset($settings['show_buynow']) && $settings['show_buynow'] == 1) echo 'checked=checked' ?>
                       value="1"> <?php echo __("Show <strong>Buy Now</strong> option", "wpdm-premium-packages"); ?>
            </label><br/>
            <label>
                <input type="checkbox" name="_wpdmpp_settings[wpdmpp_after_addtocart_redirect]" id="wpdmpp_after_addtocart_redirect"
                       value="1" <?php if ( isset($settings['wpdmpp_after_addtocart_redirect']) &&  $settings['wpdmpp_after_addtocart_redirect'] == 1 ) echo "checked='checked'"; ?>>
                <?php _e("Redirect to shopping cart after a product is added to the cart", "wpdm-premium-packages"); ?>
            </label><br/>
            <label>
                <input  name="_wpdmpp_settings[cdl_fallback]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[cdl_fallback]" <?php if (isset($settings['cdl_fallback']) && $settings['cdl_fallback'] == 1) echo 'checked=checked' ?>
                          value="1"> <?php echo __("Show 'Add To Cart' button as customer download link fallback", "wpdm-premium-packages"); ?>
            </label><br/>
            <label>
                <input  name="_wpdmpp_settings[license_key_validity]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[license_key_validity]" <?php if (isset($settings['license_key_validity']) && $settings['license_key_validity'] == 1) echo 'checked=checked' ?>
                          value="1"> <?php echo __("Keep license key valid for expired orders", "wpdm-premium-packages"); ?>
            </label><br/>
            <label>
                <input  name="_wpdmpp_settings[order_expiry_alert]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[order_expiry_alert]" <?php if (isset($settings['order_expiry_alert']) && $settings['order_expiry_alert'] == 1) echo 'checked=checked' ?>
                          value="1"> <?php echo __("Send order expiration alert to customer", "wpdm-premium-packages"); ?>
            </label>
            <br/>
            <label>
                <input  name="_wpdmpp_settings[auto_renew]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[auto_renew]" <?php if (isset($settings['auto_renew']) && $settings['auto_renew'] == 1) echo 'checked=checked' ?>
                       value="1"> <?php echo __("Auto renew order on expiration", "wpdm-premium-packages"); ?>
            </label><br/>
            <label>
                <input  name="_wpdmpp_settings[disable_manual_renew]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[disable_manual_renew]" <?php if (isset($settings['disable_manual_renew']) && $settings['disable_manual_renew'] == 1) echo 'checked=checked' ?>
                       value="1"> <?php echo sprintf(__("Disable manual renewal of orders after %s days of expiration", "wpdm-premium-packages"), "<input type=number class='form-control input-sm' value='".get_wpdmpp_option('disable_manual_renewal_period', 90)."' style='display: inline-block;width: 60px;padding: 0 4px;line-height: 18px !important;height: 18px;text-align: center;font-weight: bold;' name='_wpdmpp_settings[disable_manual_renewal_period]' />"); ?>
            </label><br/>
            <label>
                <input  name="_wpdmpp_settings[disable_order_notes]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[disable_order_notes]" <?php if (isset($settings['disable_order_notes']) && $settings['disable_order_notes'] == 1) echo 'checked=checked' ?>
                       value="1"> <?php echo __("Disable order notes", "wpdm-premium-packages"); ?>
            </label>
            <br/>
            <label>
                <input  name="_wpdmpp_settings[audio_preview]" type="hidden" value="0">
                <input type="checkbox" name="_wpdmpp_settings[audio_preview]" <?php if (isset($settings['audio_preview']) && $settings['audio_preview'] == 1) echo 'checked=checked' ?>
                       value="1"> <?php echo __("Allow users to play mp3 files before purchase", "wpdm-premium-packages"); ?>
            </label>
            <br/><br/>

            <div class="form-group">
                <label><?php _e("Order validity period:", "wpdm-premium-packages"); ?></label><br>
                <div class="input-group">
                    <input type="number" class="form-control" value="<?php echo (isset($settings['order_validity_period'])) ? $settings['order_validity_period'] : 365; ?>"
                           name="_wpdmpp_settings[order_validity_period]"/>
                    <span class="input-group-addon"><?php _e('Days','wpdm-premium-packages'); ?></span>
                </div>

            </div>

            <div class="form-group">
                <label><?php _e("Order Title:", "wpdm-premium-packages"); ?></label><br>

                    <input type="text" class="form-control" value="<?php echo (isset($settings['order_title']) && $settings['order_title'] != '') ? $settings['order_title'] : get_option('blogname'). ' Order# {{ORDER_ID}}'; ?>"
                           name="_wpdmpp_settings[order_title]"/>
                    <em class="note"><?php echo sprintf(__('%s = Product Name, %s = Order ID','wpdm-premium-packages'), '{{PRODUCT_NAME}}','{{ORDER_ID}}'); ?></em>
            </div>
            <div class="form-group">
                <label><?php _e("Order ID Prefix:", "wpdm-premium-packages"); ?></label><br>

                <input type="text" class="form-control" value="<?php echo (isset($settings['order_id_prefix']) && $settings['order_id_prefix'] != '') ? $settings['order_id_prefix'] : strtoupper(substr(str_replace(" ", "", get_option('blogname')), 0, 4)); ?>"
                       name="_wpdmpp_settings[order_id_prefix]"/>

            </div>



        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><?php _e("Abandoned Order Recovery", "wpdm-premium-packages"); ?></div>
        <div class="panel-body">
	        <?php
	        const COLON = ":";
	        $acr_fields['acre_count'] = array(
		        'label' => __("Numbers of emails to send", "wpdm-premium-packages").COLON,
		        'type' => 'number',
		        'attrs' => array('name' => '_wpdmpp_settings[acre_count]', 'placeholder' => '0', 'min' => 0, 'max' => 5, 'value' => wpdm_valueof($settings, 'acre_count')),
                'note' => __('0 = do not send email, max value 5', WPDMPP_TEXT_DOMAIN)
	        );
	        $acr_fields['acre_interval'] = array(
		        'label' => __("Email sending interval", "wpdm-premium-packages").COLON,
		        'type' => 'text',
		        'attrs' => array('name' => '_wpdmpp_settings[acre_interval]', 'id' => 'acre_interval', 'placeholder' => 3, 'min' => 1, 'max' => 99, 'value' => wpdm_valueof($settings, 'acre_interval')),
		        'note' => __('min value 1, max value 99, you also can set different intervals, ex: <code>1,3,7</code> = 1 email after 1 day, 2nd email after 3 days , 3rd email after 7 days', WPDMPP_TEXT_DOMAIN)
	        );

	        $acr_fields['delete_incomplete_order'] = array(
		        'label' => __("Delete incomplete order automatically after", "wpdm-premium-packages").COLON,
		        'type' => 'number',
		        'attrs' => array('name' => '_wpdmpp_settings[delete_incomplete_order]', 'id' => 'acre_interval', 'placeholder' => 3, 'min' => 0, 'max' => 364, 'value' => wpdm_valueof($settings, 'delete_incomplete_order')),
		        'note' => __('Set 0 if you do not want to delete, when you want to delete, the value should be greater than sum of email intervals', WPDMPP_TEXT_DOMAIN)
	        );

	        $acr_fields = apply_filters("wpdmpp_settings_acre_form_fields", $acr_fields);
	        $form = new Form($acr_fields, ['name' => '_wpdmpp_settings_form', 'id' => '_wpdmpp_settings_form', 'method' => 'POST', 'action' => '', 'submit_button' => [], 'noForm' => true]);
	        echo $form->render();
	        ?>
        </div>
        <div class="panel-footer bg-light">
            <?= __('Cron URL for abandoned order collection', WPDMPP_TEXT_DOMAIN) ?>:
            <div class="input-group">
                <input type="text" readonly="readonly" class="form-control" value="<?= home_url('?acre=1&acrq_key='.WPDM_CRON_KEY); ?>">
                <span class="input-group-btn"><button onclick="WPDM.copyTxt('<?= home_url('?acre=1&acrq_key='.WPDM_CRON_KEY); ?>')" type="button" class="btn btn-secondary ttip" title="<?= esc_html__('Click to copy') ?>"><i class="fa fa-copy"></i></button></span>
            </div>
            <em class="note"><?= __('Setup cron from your hosting panel to execute once a day', WPDMPP_TEXT_DOMAIN) ?></em>
        </div>
        <div class="panel-footer bg-light">
		    <?= __('Cron URL for order recovery email', WPDMPP_TEXT_DOMAIN) ?>:
            <div class="input-group">
                <input type="text" readonly="readonly" class="form-control" value="<?= home_url('?acre=1&acre_key='.WPDM_CRON_KEY); ?>">
                <span class="input-group-btn"><button onclick="WPDM.copyTxt('<?= home_url('?acre=1&acre_key='.WPDM_CRON_KEY); ?>')" type="button" class="btn btn-secondary ttip" title="<?= esc_html__('Click to copy') ?>"><i class="fa fa-copy"></i></button></span>
            </div>
            <em class="note"><?= __('Setup cron from your hosting panel to execute once a day', WPDMPP_TEXT_DOMAIN) ?></em>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><?php _e("License Settings", "wpdm-premium-packages"); ?></div>
        <div class="panel-body-ex">
            <table class="table table-striped" style="margin-bottom: 0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>License Name</th>
                    <th>License Description</th>
                    <th style="width: 90px"><abbr class="ttip" title="Usage Limit">Limit</abbr></th>
                    <th><i class="fa fa-cogs"></i></th>
                </tr>
                </thead>
                <tbody id="licenses">
            <?php
            $pre_licenses = wpdmpp_get_licenses();
            $pre_licenses = maybe_unserialize($pre_licenses);
            foreach ($pre_licenses as $licid => $lic){ ?>

                <tr id="tr_<?php echo $licid; ?>">
                    <td><input type="text" class="form-control" disabled="disabled" value="<?php echo $licid; ?>"></td>
                    <td><input type="text" class="form-control" name="_wpdmpp_settings[licenses][<?php echo $licid; ?>][name]" value="<?php echo esc_attr($lic['name']); ?>"></td>
                    <td><textarea class="form-control" name="_wpdmpp_settings[licenses][<?php echo $licid; ?>][description]"><?php echo isset($lic['description'])?esc_attr($lic['description']):''; ?></textarea></td>
                    <td><input type="number" class="form-control" name="_wpdmpp_settings[licenses][<?php echo $licid; ?>][use]" value="<?php echo esc_attr($lic['use']); ?>"></td>
                    <td><button type="button" data-rowid="#tr_<?php echo $licid; ?>" class="btn btn-danger del-lic"><i class="fas fa-trash-alt"></i></button></td>
                </tr>


            <?php } ?>
                </tbody>

                </table>

        </div>
        <div class="panel-footer text-right">
            <button type="button" id="addlicenses" class="btn btn-secondary btn-sm"><i class="fa fa-plus-circle"></i> <?php _e('Add New License', 'wpdm-premium-packages'); ?></button>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><?php _e("Add to Cart & Checkout Buttons", "wpdm-premium-packages"); ?></div>
        <div class="panel-body">
            <div class="form-group">
                <label for="invoice-logo"><?php _e('Add to Cart Button Label','wpdm-premium-packages'); ?></label>
                <input type="text" name="_wpdmpp_settings[a2cbtn_label]" class="form-control" value="<?php echo isset($settings['a2cbtn_label']) ? esc_attr($settings['a2cbtn_label']) : 'Add To Cart'; ?>"/>
            </div>
            <div class="form-group">
                <label><?php _e('Add To Cart Button Style', 'wpdm-premium-packages'); ?>:</label><br/>
                <div class="btn-group btn-group-sm">
                    <label class="btn btn-primary <?php echo __::valueof($settings, 'a2cbtn_color') === 'primary' ? 'active' : ''; ?>"><input <?php checked('btn-primary', (isset($settings['a2cbtn_color'])?$settings['a2cbtn_color']:'')); ?> type="radio" value="btn-primary" name="_wpdmpp_settings[a2cbtn_color]"> Primary</label>
                    <label class="btn btn-secondary <?php echo __::valueof($settings, 'a2cbtn_color') === 'secondary'?'active':''; ?>"><input <?php checked('btn-secondary', (isset($settings['a2cbtn_color'])?$settings['a2cbtn_color']:'')); ?> type="radio" value="btn-secondary" name="_wpdmpp_settings[a2cbtn_color]"> Secondary</label>
                    <label class="btn btn-info <?php echo __::valueof($settings, 'a2cbtn_color') === 'info'?'active':''; ?>"><input <?php checked('btn-info', (isset($settings['a2cbtn_color'])?$settings['a2cbtn_color']:'')); ?> type="radio" value="btn-info" name="_wpdmpp_settings[a2cbtn_color]"> Info</label>
                    <label class="btn btn-success <?php echo __::valueof($settings, 'a2cbtn_color') === 'success'?'active':''; ?>"><input <?php checked('btn-success', (isset($settings['a2cbtn_color'])?$settings['a2cbtn_color']:'')); ?> type="radio" value="btn-success" name="_wpdmpp_settings[a2cbtn_color]"> Success</label>
                    <label class="btn btn-warning <?php echo __::valueof($settings, 'a2cbtn_color') === 'warning'?'active':''; ?>"><input <?php checked('btn-warning', (isset($settings['a2cbtn_color'])?$settings['a2cbtn_color']:'')); ?> type="radio" value="btn-warning" name="_wpdmpp_settings[a2cbtn_color]"> Warning</label>
                    <label class="btn btn-danger <?php echo __::valueof($settings, 'a2cbtn_color') === 'danger'?'active':''; ?>"><input <?php checked('btn-danger', (isset($settings['a2cbtn_color'])?$settings['a2cbtn_color']:'')); ?> type="radio" value="btn-danger" name="_wpdmpp_settings[a2cbtn_color]"> Danger</label>
                </div><br/>
                <em class="note"><?php _e('You can change colors from User Interface settings page'); ?></em>

            </div>

            <div class="form-group">
                <label for="invoice-logo"><?php _e('Checkout Button Label','wpdm-premium-packages'); ?></label>
                <input type="text" name="_wpdmpp_settings[cobtn_label]" class="form-control" value="<?php echo isset($settings['cobtn_label']) ? esc_attr($settings['cobtn_label']) : htmlspecialchars('Complete Payment'); ?>"/>
            </div>
            <div class="form-group">
                <label><?php _e('Checkout Button Style', 'wpdm-premium-packages'); ?>:</label><br/>
                <div class="btn-group btn-group-sm">
                    <label class="btn btn-primary <?php echo (__::valueof($settings, 'cobtn_color') === 'primary')?'active':''; ?>"><input <?php checked('btn-primary', __::valueof($settings, 'cobtn_color')); ?> type="radio" value="btn-primary" name="_wpdmpp_settings[cobtn_color]"> Primary</label>
                    <label class="btn btn-secondary <?php echo (__::valueof($settings, 'cobtn_color') === 'secondary')?'active':''; ?>"><input <?php checked('btn-secondary', __::valueof($settings, 'cobtn_color')); ?> type="radio" value="btn-secondary" name="_wpdmpp_settings[cobtn_color]"> Secondary</label>
                    <label class="btn btn-info <?php echo (__::valueof($settings, 'cobtn_color') === 'info')?'active':''; ?>"><input <?php checked('btn-info', __::valueof($settings, 'cobtn_color')); ?> type="radio" value="btn-info" name="_wpdmpp_settings[cobtn_color]"> Info</label>
                    <label class="btn btn-success <?php echo (__::valueof($settings, 'cobtn_color') === 'success')?'active':''; ?>"><input <?php checked('btn-success', __::valueof($settings, 'cobtn_color')); ?> type="radio" value="btn-success" name="_wpdmpp_settings[cobtn_color]"> Success</label>
                    <label class="btn btn-warning <?php echo (__::valueof($settings, 'cobtn_color') === 'warning')?'active':''; ?>"><input <?php checked('btn-warning', __::valueof($settings, 'cobtn_color')); ?> type="radio" value="btn-warning" name="_wpdmpp_settings[cobtn_color]"> Warning</label>
                    <label class="btn btn-danger <?php echo (__::valueof($settings, 'cobtn_color') === 'danger')?'active':''; ?>"><input <?php checked('btn-danger', __::valueof($settings, 'cobtn_color')); ?> type="radio" value="btn-danger" name="_wpdmpp_settings[cobtn_color]"> Danger</label>
                </div><br/>
                <em class="note"><?php _e('You can change colors from User Interface settings page'); ?></em>

            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><?php _e('Invoice', 'wpdm-premium-packages'); ?></div>
        <div class="panel-body">
            <div class="form-group">
                <label for="invoice-logo"><?php _e('Invoice Logo URL','wpdm-premium-packages'); ?></label>
                <div class="input-group">
                    <input type="text" name="_wpdmpp_settings[invoice_logo]" id="invoice-logo" class="form-control" value="<?php echo isset($settings['invoice_logo']) ? $settings['invoice_logo'] : ''; ?>"/>
                    <span class="input-group-btn">
                        <button class="btn btn-default btn-media-upload" type="button" rel="#invoice-logo"><i class="far fa-image"></i></button>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label for="company-address"><?php _e('Company Address', 'wpdm-premium-packages'); ?></label>
                <textarea class="form-control" name="_wpdmpp_settings[invoice_company_address]" id="company-address"><?php echo isset($settings['invoice_company_address']) ? $settings['invoice_company_address'] : ''; ?></textarea>
            </div>
            <div class="form-group">
                <label for="company-address"><?php _e('Thanks Note', 'wpdm-premium-packages'); ?></label>
                <input type="text" class="form-control" name="_wpdmpp_settings[invoice_thanks]" id="invoice_thanks" value="<?php echo get_wpdmpp_option('invoice_thanks'); ?>" />
            </div>
            <div class="form-group">
                <label for="company-address"><?php _e('Terms Acceptance Note', 'wpdm-premium-packages'); ?></label>
                <textarea class="form-control" name="_wpdmpp_settings[invoice_terms_acceptance]" id="terms_acceptance"><?php echo isset($settings['invoice_terms_acceptance']) ? $settings['invoice_terms_acceptance'] : ''; ?></textarea>
            </div>
            <div class="form-group">
                <label for="invoice-logo"><?php _e('Signature','wpdm-premium-packages'); ?></label>
                <div class="input-group">
                    <input type="text" name="_wpdmpp_settings[signature]" id="signature" class="form-control" value="<?php echo isset($settings['signature']) ? $settings['signature'] : ''; ?>"/>
                    <span class="input-group-btn">
                        <button class="btn btn-default btn-media-upload" type="button" rel="#signature"><i class="far fa-image"></i></button>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><?php _e('Miscellaneous', 'wpdm-premium-packages'); ?></div>
        <div class="panel-body">
            <label>
            <input type="hidden" name="_wpdmpp_settings[disable_fron_end_css]"  value="0" />
            <input type="checkbox" name="_wpdmpp_settings[disable_fron_end_css]" id="disable_fron_end_css"
                   value="1" <?php if (isset($settings['disable_fron_end_css']) && $settings['disable_fron_end_css'] == 1) echo "checked='checked'"; ?>> <?php _e("Disable plugin CSS from front-end", "wpdm-premium-packages"); ?>
            </label>
        </div>
    </div>

    <?php do_action("wpdmpp_basic_options"); ?>
</div>

<style>
    .w3eden input[type="radio"], .w3eden input[type="checkbox"] {
        line-height: normal;
        margin: -2px 0 0;
    }
    .panel-body label{
        font-weight: 400 !important;
    }
    .wpdmpp-settings-fields{
        margin-top: 20px;
    }
    .btn-group.btn-group-sm .btn {
        font-size: 11px;
    }
</style>
<script>
    jQuery(function ($) {
        $('.__wpdm_a2c_button_color, .__wpdm_a2c_button_size').on('change', function () {
            $('#__wpdm_a2c_button').attr('class', 'btn '+ $('.__wpdm_a2c_button_color:checked').val() + ' ' + $('.__wpdm_a2c_button_size:checked').val());
        });

        $('#__wpdm_a2c_button_br').on('change', function () {
            $('#__wpdm_a2c_button').css('border-radius', $(this).val()+'px');
        });

        $('#__wpdm_a2c_button_label').on('keyup', function () {
            $('#__wpdm_a2c_button').html($(this).val());
        });
    });
</script>
