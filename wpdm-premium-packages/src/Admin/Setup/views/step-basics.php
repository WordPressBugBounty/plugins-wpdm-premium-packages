<?php
/**
 * Setup Wizard - Basics step.
 *
 * @package WPDMPP\Admin\Setup
 *
 * @var array $settings Stored plugin settings.
 */

defined('ABSPATH') || exit;

/**
 * Render one toggle-switch option row.
 *
 * @param array  $settings
 * @param string $name  Field name inside _wpdmpp_settings[...]
 * @param bool   $on    Whether the stored value is enabled.
 * @param string $title
 * @param string $desc
 */
$row = function ($on, $name, $title, $desc) {
    $checked = $on ? ' checked' : '';
    $active  = $on ? ' is-on' : '';
    ?>
    <label class="wz-opt<?php echo $active; ?>">
        <span class="wz-opt__body">
            <span class="wz-opt__t"><?php echo esc_html($title); ?></span>
            <span class="wz-opt__d"><?php echo esc_html($desc); ?></span>
        </span>
        <span class="wz-switch">
            <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1"<?php echo $checked; ?>>
            <span class="wz-switch__track"></span>
        </span>
    </label>
    <?php
};
?>
<div class="wz-panel">
    <p class="wz-eyebrow"><?php esc_html_e('Step 1 of 4 · Welcome', 'wpdm-premium-packages'); ?></p>
    <h1 class="wz-title"><?php esc_html_e("Let's set up your store", 'wpdm-premium-packages'); ?></h1>
    <p class="wz-sub"><?php esc_html_e('A few quick choices shape how customers buy and download. You can change any of these later in settings.', 'wpdm-premium-packages'); ?></p>

    <form id="wzform" method="post">
        <?php wp_nonce_field('wpdmpp-setup'); ?>
        <div class="wz-opts">
            <?php
            $row(isset($settings['billing_address']) && $settings['billing_address'] == 1, '_wpdmpp_settings[billing_address]', __('Ask for billing address at checkout', 'wpdm-premium-packages'), __('Collect name, address, email and phone before purchase.', 'wpdm-premium-packages'));
            $row(isset($settings['guest_checkout']) && $settings['guest_checkout'] == 1, '_wpdmpp_settings[guest_checkout]', __('Enable guest checkout', 'wpdm-premium-packages'), __('Let people buy without an account — just name and email.', 'wpdm-premium-packages'));
            $row(isset($settings['guest_download']) && $settings['guest_download'] == 1, '_wpdmpp_settings[guest_download]', __('Enable guest download', 'wpdm-premium-packages'), __('Guests can re-download using their order ID and email.', 'wpdm-premium-packages'));
            $row(isset($settings['wpdmpp_after_addtocart_redirect']) && $settings['wpdmpp_after_addtocart_redirect'] == 1, '_wpdmpp_settings[wpdmpp_after_addtocart_redirect]', __('Redirect to cart after adding a product', 'wpdm-premium-packages'), __('Send shoppers to the cart, or keep them browsing.', 'wpdm-premium-packages'));
            $row(isset($settings['tax']['enable']) && $settings['tax']['enable'] == 1, '_wpdmpp_settings[tax][enable]', __('Enable tax calculation', 'wpdm-premium-packages'), __('Turn on taxes now; add country / state rates later.', 'wpdm-premium-packages'));
            ?>
        </div>
    </form>
</div>
