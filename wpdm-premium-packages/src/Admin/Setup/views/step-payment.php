<?php
/**
 * Setup Wizard - Payment step.
 *
 * Collects PayPal Smart Buttons REST credentials (Client ID / Secret) plus the
 * offline gateways and currency.
 *
 * @package WPDMPP\Admin\Setup
 *
 * @var array $settings Stored plugin settings.
 */

defined('ABSPATH') || exit;

$pp         = isset($settings['PayPal']) && is_array($settings['PayPal']) ? $settings['PayPal'] : array();
$pp_enabled = isset($pp['enabled']) && $pp['enabled'] == 1;
$pp_mode    = isset($pp['Paypal_mode']) && $pp['Paypal_mode'] === 'sandbox' ? 'sandbox' : 'production';
$pp_title   = isset($pp['title']) && $pp['title'] !== '' ? $pp['title'] : 'PayPal';
if ($pp_mode === 'sandbox') {
    $pp_client_id     = isset($pp['client_id_sandbox']) ? $pp['client_id_sandbox'] : '';
    $pp_client_secret = isset($pp['client_secret_sandbox']) ? $pp['client_secret_sandbox'] : '';
} else {
    $pp_client_id     = isset($pp['client_id']) ? $pp['client_id'] : '';
    $pp_client_secret = isset($pp['client_secret']) ? $pp['client_secret'] : '';
}

/**
 * Render a simple offline gateway card (enable toggle + title field).
 */
$offline_card = function ($key, $enabled, $title_val, $default_title, $name, $desc, $icon) {
    ?>
    <div class="wz-gw<?php echo $enabled ? ' is-on' : ''; ?>" data-gw>
        <div class="wz-gw__head">
            <span class="wz-gw__logo"><?php echo $icon; ?></span>
            <span class="wz-gw__meta">
                <span class="wz-gw__name"><?php echo esc_html($name); ?></span>
                <span class="wz-gw__desc"><?php echo esc_html($desc); ?></span>
            </span>
            <span class="wz-switch">
                <input type="checkbox" data-gw-toggle name="_wpdmpp_settings[<?php echo esc_attr($key); ?>][enabled]" value="1"<?php echo $enabled ? ' checked' : ''; ?>>
                <span class="wz-switch__track"></span>
            </span>
        </div>
        <div class="wz-gw__body">
            <div class="wz-field">
                <label class="wz-label"><?php esc_html_e('Button title', 'wpdm-premium-packages'); ?></label>
                <input class="wz-input" type="text" name="_wpdmpp_settings[<?php echo esc_attr($key); ?>][title]" value="<?php echo esc_attr($title_val !== '' ? $title_val : $default_title); ?>">
            </div>
        </div>
    </div>
    <?php
};

$icon_flask  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.31"/><path d="M14 9.3V2"/><path d="M8.5 2h7"/><path d="M14 9.3a6.5 6.5 0 1 1-4 0"/><path d="M5.58 16.5h12.85"/></svg>';
$icon_cheque = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h16"/><path d="M4 6h16v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M8 14h.01"/><path d="M12 14h4"/></svg>';
$icon_cash   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>';
?>
<div class="wz-panel">
    <p class="wz-eyebrow"><?php esc_html_e('Step 3 of 4 · Payment', 'wpdm-premium-packages'); ?></p>
    <h1 class="wz-title"><?php esc_html_e("How you'll get paid", 'wpdm-premium-packages'); ?></h1>
    <p class="wz-sub"><?php esc_html_e('Turn on the methods you want at launch. Stripe, Razorpay and more are available as add-ons later.', 'wpdm-premium-packages'); ?></p>

    <form id="wzform" method="post">
        <?php wp_nonce_field('wpdmpp-setup'); ?>

        <div class="wz-gw<?php echo $pp_enabled ? ' is-on' : ''; ?>" data-gw>
            <div class="wz-gw__head">
                <span class="wz-gw__logo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg></span>
                <span class="wz-gw__meta">
                    <span class="wz-gw__name"><?php esc_html_e('PayPal', 'wpdm-premium-packages'); ?></span>
                    <span class="wz-gw__desc"><?php esc_html_e('Cards, PayPal balance & Pay Later via Smart Buttons.', 'wpdm-premium-packages'); ?></span>
                </span>
                <span class="wz-switch">
                    <input type="checkbox" data-gw-toggle name="_wpdmpp_settings[PayPal][enabled]" value="1"<?php echo $pp_enabled ? ' checked' : ''; ?>>
                    <span class="wz-switch__track"></span>
                </span>
            </div>
            <div class="wz-gw__body">
                <div class="wz-grid2">
                    <div class="wz-field">
                        <label class="wz-label"><?php esc_html_e('Environment', 'wpdm-premium-packages'); ?></label>
                        <select class="wz-select" name="_wpdmpp_settings[PayPal][Paypal_mode]">
                            <option value="production" <?php selected($pp_mode, 'production'); ?>><?php esc_html_e('Live', 'wpdm-premium-packages'); ?></option>
                            <option value="sandbox" <?php selected($pp_mode, 'sandbox'); ?>><?php esc_html_e('Sandbox (Test)', 'wpdm-premium-packages'); ?></option>
                        </select>
                    </div>
                    <div class="wz-field">
                        <label class="wz-label"><?php esc_html_e('Button title', 'wpdm-premium-packages'); ?></label>
                        <input class="wz-input" type="text" name="_wpdmpp_settings[PayPal][title]" value="<?php echo esc_attr($pp_title); ?>">
                    </div>
                </div>
                <div class="wz-field">
                    <label class="wz-label"><?php esc_html_e('Client ID', 'wpdm-premium-packages'); ?></label>
                    <input class="wz-input" type="text" name="_wpdmpp_settings[PayPal][client_id]" value="<?php echo esc_attr($pp_client_id); ?>" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e('Paste your PayPal REST app Client ID', 'wpdm-premium-packages'); ?>">
                </div>
                <div class="wz-field">
                    <label class="wz-label"><?php esc_html_e('Client Secret', 'wpdm-premium-packages'); ?></label>
                    <input class="wz-input" type="password" name="_wpdmpp_settings[PayPal][client_secret]" value="<?php echo esc_attr($pp_client_secret); ?>" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e('Paste your PayPal REST app Client Secret', 'wpdm-premium-packages'); ?>">
                    <p class="wz-hint"><?php esc_html_e('Create a REST app in the', 'wpdm-premium-packages'); ?> <a href="https://developer.paypal.com/dashboard/applications/live" target="_blank" rel="noopener"><?php esc_html_e('PayPal Developer Dashboard', 'wpdm-premium-packages'); ?></a> <?php esc_html_e('to get these. Credentials are saved for the selected environment.', 'wpdm-premium-packages'); ?></p>
                </div>
            </div>
        </div>

        <?php
        $offline_card('TestPay', isset($settings['TestPay']['enabled']) && $settings['TestPay']['enabled'] == 1, isset($settings['TestPay']['title']) ? $settings['TestPay']['title'] : '', 'Test Pay', 'TestPay', __('Simulate orders — for testing only.', 'wpdm-premium-packages'), $icon_flask);
        $offline_card('Cheque', isset($settings['Cheque']['enabled']) && $settings['Cheque']['enabled'] == 1, isset($settings['Cheque']['title']) ? $settings['Cheque']['title'] : '', 'Pay with Cheque', 'Cheque', __('Collect payment offline.', 'wpdm-premium-packages'), $icon_cheque);
        $offline_card('Cash', isset($settings['Cash']['enabled']) && $settings['Cash']['enabled'] == 1, isset($settings['Cash']['title']) ? $settings['Cash']['title'] : '', 'Pay with Cash', 'Cash', __('Collect payment offline.', 'wpdm-premium-packages'), $icon_cash);
        ?>

        <p class="wz-sectlabel"><?php esc_html_e('Currency', 'wpdm-premium-packages'); ?></p>
        <div class="wz-grid2">
            <div class="wz-field" style="margin:0">
                <label class="wz-label"><?php esc_html_e('Store currency', 'wpdm-premium-packages'); ?></label>
                <?php echo \WPDMPP\Core\CurrencyService::getInstance()->getCurrencyDropdown(
                    isset($settings['currency']) ? $settings['currency'] : '',
                    '_wpdmpp_settings[currency]'
                ); ?>
            </div>
            <div class="wz-field" style="margin:0">
                <label class="wz-label"><?php esc_html_e('Sign position', 'wpdm-premium-packages');
                    $currency_position = isset($settings['currency_position']) ? $settings['currency_position'] : 'before';
                ?></label>
                <select class="wz-select" name="_wpdmpp_settings[currency_position]">
                    <option value="before" <?php selected($currency_position, 'before'); ?>><?php esc_html_e('Before - $99', 'wpdm-premium-packages'); ?></option>
                    <option value="after" <?php selected($currency_position, 'after'); ?>><?php esc_html_e('After - 99$', 'wpdm-premium-packages'); ?></option>
                </select>
            </div>
        </div>
    </form>
</div>
