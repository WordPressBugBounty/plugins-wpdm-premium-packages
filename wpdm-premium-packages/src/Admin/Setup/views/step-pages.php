<?php
/**
 * Setup Wizard - Pages step.
 *
 * Ensures the required cart/purchases/guest-orders pages exist and lets the
 * user assign them.
 *
 * @package WPDMPP\Admin\Setup
 *
 * @var array $settings Stored plugin settings.
 */

defined('ABSPATH') || exit;

global $wpdb;

if (!$cart_page_id = $wpdb->get_var("select id from {$wpdb->prefix}posts where post_type='page' AND post_content like '%[wpdmpp_cart]%'")) {
    $cart_page_id = wp_insert_post(array('post_title' => 'Cart', 'post_content' => '[wpdmpp_cart]', 'post_type' => 'page', 'post_status' => 'publish'));
}
if (!$orders_page_id = $wpdb->get_var("select id from {$wpdb->prefix}posts where post_type='page' AND post_content like '%[wpdmpp_purchases]%'")) {
    $orders_page_id = wp_insert_post(array('post_title' => 'Purchases', 'post_content' => '[wpdmpp_purchases]', 'post_type' => 'page', 'post_status' => 'publish'));
}
if (!$guest_orders_page_id = $wpdb->get_var("select id from {$wpdb->prefix}posts where post_type='page' AND post_content like '%[wpdmpp_guest_orders]%'")) {
    $guest_orders_page_id = wp_insert_post(array('post_title' => 'Guest Orders', 'post_content' => '[wpdmpp_guest_orders]', 'post_type' => 'page', 'post_status' => 'publish'));
}
?>
<div class="wz-panel">
    <p class="wz-eyebrow"><?php esc_html_e('Step 2 of 4 · Pages', 'wpdm-premium-packages'); ?></p>
    <h1 class="wz-title"><?php esc_html_e('Your store pages', 'wpdm-premium-packages'); ?></h1>
    <p class="wz-sub"><?php esc_html_e('These pages hold your cart, purchases and guest downloads. We created them for you — reassign them if you already have your own.', 'wpdm-premium-packages'); ?></p>

    <div class="wz-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        <span><?php esc_html_e('Cart, Purchases and Guest Orders pages were created automatically.', 'wpdm-premium-packages'); ?></span>
    </div>

    <form id="wzform" method="post">
        <?php wp_nonce_field('wpdmpp-setup'); ?>

        <div class="wz-field">
            <label class="wz-label"><?php esc_html_e('Cart page', 'wpdm-premium-packages'); ?></label>
            <?php wp_dropdown_pages(array(
                'show_option_none' => __('None Selected', 'wpdm-premium-packages'),
                'name'             => '_wpdmpp_settings[page_id]',
                'selected'         => isset($settings['page_id']) && $settings['page_id'] != "" ? $settings['page_id'] : $cart_page_id,
            )); ?>
            <p class="wz-hint"><?php esc_html_e('Shows cart items, tax, payment options and billing. Must contain the', 'wpdm-premium-packages'); ?> <code>[wpdmpp_cart]</code> <?php esc_html_e('shortcode.', 'wpdm-premium-packages'); ?></p>
        </div>

        <div class="wz-field">
            <label class="wz-label"><?php esc_html_e('Orders / purchases page', 'wpdm-premium-packages'); ?></label>
            <?php wp_dropdown_pages(array(
                'name'             => '_wpdmpp_settings[orders_page_id]',
                'show_option_none' => __('None Selected', 'wpdm-premium-packages'),
                'selected'         => isset($settings['orders_page_id']) && $settings['orders_page_id'] != "" ? $settings['orders_page_id'] : $orders_page_id,
            )); ?>
            <p class="wz-hint"><?php esc_html_e("Lists a logged-in customer's purchases. Must contain", 'wpdm-premium-packages'); ?> <code>[wpdmpp_purchases]</code>.</p>
        </div>

        <?php if (isset($settings['guest_download'])) : ?>
            <div class="wz-field">
                <label class="wz-label"><?php esc_html_e('Guest order page', 'wpdm-premium-packages'); ?></label>
                <?php wp_dropdown_pages(array(
                    'name'             => '_wpdmpp_settings[guest_order_page_id]',
                    'show_option_none' => __('None Selected', 'wpdm-premium-packages'),
                    'selected'         => isset($settings['guest_order_page_id']) && $settings['guest_order_page_id'] != "" ? $settings['guest_order_page_id'] : $guest_orders_page_id,
                )); ?>
                <p class="wz-hint"><?php esc_html_e('Where guests download products and invoices. Must contain', 'wpdm-premium-packages'); ?> <code>[wpdmpp_guest_orders]</code>.</p>
            </div>
        <?php endif; ?>

        <div class="wz-field">
            <label class="wz-label"><?php esc_html_e('Continue shopping URL', 'wpdm-premium-packages'); ?></label>
            <input class="wz-input" type="text" name="_wpdmpp_settings[continue_shopping_url]" value="<?php echo isset($settings['continue_shopping_url']) && $settings['continue_shopping_url'] != '' ? esc_attr($settings['continue_shopping_url']) : esc_attr(home_url()); ?>">
            <p class="wz-hint"><?php esc_html_e('The "Continue shopping" button on the cart links here.', 'wpdm-premium-packages'); ?></p>
        </div>
    </form>
</div>
