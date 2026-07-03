<?php
/**
 * Modern Single-Page AJAX Checkout Template
 *
 * A streamlined, single-page checkout experience with:
 * - AJAX-powered cart updates
 * - PayPal Smart Buttons integration
 * - Guest checkout (name + email only)
 * - Modern, responsive design
 *
 * This template can be overridden by copying it to:
 * yourtheme/download-manager/checkout/checkout.php
 *
 * @package WPDMPP
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get cart data
$cart_items = wpdmpp_get_cart_data();
if (empty($cart_items)) {
    include wpdm_tpl_path('checkout-cart/cart-empty.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
    return;
}

// Get current user data
$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();

// Get user billing info
$billing = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'company'    => '',
    'country'    => '',
    'state'      => '',
    'address_1'  => '',
    'address_2'  => '',
    'city'       => '',
    'postcode'   => '',
    'phone'      => '',
];

if ($is_logged_in) {
    $saved_billing = maybe_unserialize(get_user_meta($current_user->ID, 'user_billing_shipping', true));
    $saved_billing = is_array($saved_billing) && isset($saved_billing['billing']) ? $saved_billing['billing'] : [];

    $billing['first_name'] = !empty($saved_billing['first_name']) ? $saved_billing['first_name'] : $current_user->first_name;
    $billing['last_name'] = !empty($saved_billing['last_name']) ? $saved_billing['last_name'] : $current_user->last_name;
    $billing['email'] = !empty($saved_billing['order_email']) ? $saved_billing['order_email'] : $current_user->user_email;

    // Address fields (used for tax calculation when tax is enabled)
    foreach (['company', 'country', 'state', 'address_1', 'address_2', 'city', 'postcode', 'phone'] as $_bk) {
        if (!empty($saved_billing[$_bk])) {
            $billing[$_bk] = $saved_billing[$_bk];
        }
    }

    // Fallback to display name if no first name
    if (empty($billing['first_name'])) {
        $billing['first_name'] = $current_user->display_name;
    }
}

// Get payment methods
$paymentService = \WPDMPP\Payment\PaymentService::instance();
$payment_methods = $paymentService->getPaymentMethodNames(true);
$settings = maybe_unserialize(get_option('_wpdmpp_settings', []));

// When the user is not logged in and guest checkout is disabled, the payment section
// is replaced by the login/register form (the order summary / cart still shows).
$guest_checkout = (is_array($settings) && isset($settings['guest_checkout']) && $settings['guest_checkout'] == 1) ? 1 : 0;
$login_required = !$is_logged_in && $guest_checkout == 0;

// Apply saved sort order
if (!empty($settings['pmorders']) && is_array($settings['pmorders']) && count($settings['pmorders']) >= count($payment_methods)) {
    $sorted = [];
    foreach ($settings['pmorders'] as $key) {
        if (in_array($key, $payment_methods, true)) {
            $sorted[] = $key;
        }
    }
    // Append any methods not in saved order
    foreach ($payment_methods as $m) {
        if (!in_array($m, $sorted, true)) {
            $sorted[] = $m;
        }
    }
    $payment_methods = $sorted;
}

// Check for PayPal
$has_paypal = in_array('PayPal', $payment_methods);
$paypal_client_id = '';
$paypal_mode = 'production';
if ($has_paypal) {
    $paypal_mode = get_wpdmpp_option('PayPal/Paypal_mode', 'production');
    $paypal_client_id = ($paypal_mode === 'sandbox')
        ? get_wpdmpp_option('PayPal/client_id_sandbox')
        : get_wpdmpp_option('PayPal/client_id');
}

// Privacy settings
$require_privacy = get_option('__wpdm_checkout_privacy', 0) == 1;
$privacy_label = get_option('__wpdm_checkout_privacy_label', __('I agree to the privacy policy', 'wpdm-premium-packages'));

// Color scheme
$color_scheme = get_option('__wpdm_color_scheme', 'system');
$checkout_classes = 'wpdmpp-checkout';
if ($color_scheme === 'light') {
    $checkout_classes .= ' light-mode';
} elseif ($color_scheme === 'dark') {
    $checkout_classes .= ' dark-mode';
}

// Currency
$currency = wpdmpp_currency_sign();
$currency_code = wpdmpp_currency_code();

// Cart totals
$subtotal = wpdmpp_get_cart_subtotal();
$discount = wpdmpp_get_cart_discount();
$total = wpdmpp_get_cart_total();

// Tax. When enabled, full billing address is collected so the country/state can
// drive the rate. Pre-compute the initial tax from any saved billing location so
// the summary is correct on first render (it then recalculates live via JS).
// Strict check (matches CartService::calculateTax) — tax must be explicitly enabled.
$tax_active = function_exists('get_wpdmpp_option') && (int) get_wpdmpp_option('tax/enable', 0, 'int') === 1;
$tax        = 0;
if ($tax_active && !empty($billing['country'])) {
    $tax = \WPDMPP\Cart\CartService::instance()->calculateTax($billing['country'], $billing['state']);
}
$total_with_tax = $total + $tax;

// Allowed countries for the billing dropdown (falls back to all countries).
$allowed_countries = $tax_active ? get_wpdmpp_option('allow_country') : [];
$all_countries     = function_exists('wpdmpp_countries') ? wpdmpp_countries() : [];
if (!is_array($allowed_countries) || empty($allowed_countries)) {
    $allowed_countries = array_keys($all_countries);
}

// Detect trial membership in cart
$checkout_trial_days = 0;
$checkout_full_price = 0;
foreach ($cart_items as $_cid => $_citem) {
    $_cinfo = isset($_citem['info']) ? $_citem['info'] : [];
    if (!empty($_cinfo['trial_days']) && (int) $_cinfo['trial_days'] > 0) {
        $checkout_trial_days = (int) $_cinfo['trial_days'];
        $checkout_full_price = isset($_cinfo['full_price']) ? (float) $_cinfo['full_price'] : 0;
        break;
    }
}

// Enqueue checkout assets (with cache buster for development)
$asset_version = WPDMPP_VERSION . '.4';
wp_enqueue_style('wpdmpp-checkout', WPDMPP_BASE_URL . 'assets/css/checkout.css', [], $asset_version);
wp_enqueue_script('wpdmpp-checkout', WPDMPP_BASE_URL . 'assets/js/checkout.js', ['jquery'], $asset_version, true);

// Localize script
wp_localize_script('wpdmpp-checkout', 'wpdmppCheckout', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'restUrl' => rest_url('wpdmpp/v1/checkout'),
    'cartRestUrl' => rest_url('wpdmpp/v1/cart'),
    'restNonce' => wp_create_nonce('wp_rest'),
    'cartUrl' => wpdmpp_cart_page(),
    'continueShoppingUrl' => wpdmpp_continue_shopping_url(),
    'currency' => $currency,
    'currencyCode' => $currency_code,
    'taxActive' => $tax_active,
    'dataUrl' => WPDMPP_BASE_URL . 'assets/js/data/',
    'hasPayPal' => $has_paypal,
    'paypalClientId' => $paypal_client_id,
    'paypalMode' => $paypal_mode,
    'isRecurring' => \WPDMPP\Cart\CartService::instance()->isRecurring(),
    'requirePrivacy' => $require_privacy,
    'strings' => [
        'processing' => __('Processing...', 'wpdm-premium-packages'),
        'pleaseWait' => __('Please wait...', 'wpdm-premium-packages'),
        'error' => __('An error occurred. Please try again.', 'wpdm-premium-packages'),
        'paypalError' => __('PayPal payment could not be completed.', 'wpdm-premium-packages'),
        'validationError' => __('Please fill in all required fields.', 'wpdm-premium-packages'),
        'privacyRequired' => __('Please agree to the privacy policy.', 'wpdm-premium-packages'),
        'cartUpdated' => __('Cart updated.', 'wpdm-premium-packages'),
        'itemRemoved' => __('Item removed.', 'wpdm-premium-packages'),
        'couponApplied' => __('Coupon applied!', 'wpdm-premium-packages'),
        'couponRemoved' => __('Coupon removed.', 'wpdm-premium-packages'),
        'emptyCart' => __('Your cart is empty.', 'wpdm-premium-packages'),
    ],
]);
?>
<div class="<?php echo esc_attr($checkout_classes); ?>" id="wpdmpp-checkout">
    <div class="wpdmpp-checkout__content">
        <!-- Left Column: Billing + Payment -->
        <div class="wpdmpp-checkout__main">
            <?php if (!$login_required): ?>
            <!-- Billing Section -->
            <div class="wpdmpp-checkout__section" id="billing-section">
                <div class="wpdmpp-checkout__section-header">
                    <h3 class="wpdmpp-checkout__section-title"><?php _e('Your Information', 'wpdm-premium-packages'); ?></h3>
                </div>
                <div class="wpdmpp-checkout__section-body">
                    <div class="wpdmpp-checkout__field-row">
                        <div class="wpdmpp-checkout__field">
                            <label for="checkout-first-name" class="wpdmpp-checkout__label">
                                <?php _e('First Name', 'wpdm-premium-packages'); ?> <span class="required">*</span>
                            </label>
                            <input type="text"
                                   id="checkout-first-name"
                                   name="first_name"
                                   class="wpdmpp-checkout__input"
                                   value="<?php echo esc_attr($billing['first_name']); ?>"
                                   required
                                   autocomplete="given-name">
                            <span class="wpdmpp-checkout__error" data-field="first_name"></span>
                        </div>
                        <div class="wpdmpp-checkout__field">
                            <label for="checkout-last-name" class="wpdmpp-checkout__label">
                                <?php _e('Last Name', 'wpdm-premium-packages'); ?>
                            </label>
                            <input type="text"
                                   id="checkout-last-name"
                                   name="last_name"
                                   class="wpdmpp-checkout__input"
                                   value="<?php echo esc_attr($billing['last_name']); ?>"
                                   autocomplete="family-name">
                        </div>
                    </div>
                    <div class="wpdmpp-checkout__field">
                        <label for="checkout-email" class="wpdmpp-checkout__label">
                            <?php _e('Email Address', 'wpdm-premium-packages'); ?> <span class="required">*</span>
                        </label>
                        <input type="email"
                               id="checkout-email"
                               name="email"
                               class="wpdmpp-checkout__input"
                               value="<?php echo esc_attr($billing['email']); ?>"
                               required
                               autocomplete="email">
                        <span class="wpdmpp-checkout__error" data-field="email"></span>
                        <p class="wpdmpp-checkout__hint"><?php _e('Order confirmation will be sent to this email.', 'wpdm-premium-packages'); ?></p>
                    </div>

                    <?php if ($tax_active): ?>
                    <!-- Billing Address (required for tax calculation) -->
                    <div class="wpdmpp-checkout__field">
                        <label for="checkout-country" class="wpdmpp-checkout__label">
                            <?php _e('Country', 'wpdm-premium-packages'); ?> <span class="required">*</span>
                        </label>
                        <select id="checkout-country" name="country" class="wpdmpp-checkout__input wpdmpp-checkout__select" required>
                            <option value=""><?php _e('— Select Country —', 'wpdm-premium-packages'); ?></option>
                            <?php foreach ($allowed_countries as $country_code):
                                if (!isset($all_countries[$country_code])) continue; ?>
                                <option value="<?php echo esc_attr($country_code); ?>" <?php selected($billing['country'], $country_code); ?>><?php echo esc_html(ucwords(strtolower($all_countries[$country_code]))); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="wpdmpp-checkout__error" data-field="country"></span>
                    </div>
                    <div class="wpdmpp-checkout__field-row">
                        <div class="wpdmpp-checkout__field">
                            <label for="checkout-state" class="wpdmpp-checkout__label">
                                <?php _e('State / Province', 'wpdm-premium-packages'); ?> <span class="required">*</span>
                            </label>
                            <select id="checkout-state" name="state" class="wpdmpp-checkout__input wpdmpp-checkout__select"></select>
                            <input type="text" id="checkout-state-text" name="state" class="wpdmpp-checkout__input"
                                   value="<?php echo esc_attr($billing['state']); ?>"
                                   placeholder="<?php esc_attr_e('State / Province / Region', 'wpdm-premium-packages'); ?>"
                                   style="display:none;">
                            <span class="wpdmpp-checkout__error" data-field="state"></span>
                        </div>
                        <div class="wpdmpp-checkout__field">
                            <label for="checkout-city" class="wpdmpp-checkout__label">
                                <?php _e('City / Town', 'wpdm-premium-packages'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" id="checkout-city" name="city" class="wpdmpp-checkout__input"
                                   value="<?php echo esc_attr($billing['city']); ?>" required autocomplete="address-level2">
                            <span class="wpdmpp-checkout__error" data-field="city"></span>
                        </div>
                    </div>
                    <div class="wpdmpp-checkout__field">
                        <label for="checkout-address-1" class="wpdmpp-checkout__label">
                            <?php _e('Address', 'wpdm-premium-packages'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="checkout-address-1" name="address_1" class="wpdmpp-checkout__input"
                               value="<?php echo esc_attr($billing['address_1']); ?>" required autocomplete="address-line1"
                               placeholder="<?php esc_attr_e('Address line 1', 'wpdm-premium-packages'); ?>">
                        <span class="wpdmpp-checkout__error" data-field="address_1"></span>
                    </div>
                    <div class="wpdmpp-checkout__field-row">
                        <div class="wpdmpp-checkout__field">
                            <label for="checkout-address-2" class="wpdmpp-checkout__label">
                                <?php _e('Address Line 2', 'wpdm-premium-packages'); ?>
                            </label>
                            <input type="text" id="checkout-address-2" name="address_2" class="wpdmpp-checkout__input"
                                   value="<?php echo esc_attr($billing['address_2']); ?>" autocomplete="address-line2"
                                   placeholder="<?php esc_attr_e('Apartment, suite, etc. (optional)', 'wpdm-premium-packages'); ?>">
                        </div>
                        <div class="wpdmpp-checkout__field">
                            <label for="checkout-postcode" class="wpdmpp-checkout__label">
                                <?php _e('Zip / Postal Code', 'wpdm-premium-packages'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" id="checkout-postcode" name="postcode" class="wpdmpp-checkout__input"
                                   value="<?php echo esc_attr($billing['postcode']); ?>" required autocomplete="postal-code">
                            <span class="wpdmpp-checkout__error" data-field="postcode"></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment Section (replaced by login/register form when login is required) -->
            <div class="wpdmpp-checkout__section" id="payment-section">
                <div class="wpdmpp-checkout__section-header">
                    <h3 class="wpdmpp-checkout__section-title"><?php echo $login_required ? esc_html__('Login or Register', 'wpdm-premium-packages') : esc_html__('Payment Method', 'wpdm-premium-packages'); ?></h3>
                </div>
                <div class="wpdmpp-checkout__section-body">
                    <?php if ($login_required): ?>
                        <?php include wpdm_tpl_path('checkout-cart/checkout-login-register.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK); ?>
                    <?php elseif (empty($payment_methods)): ?>
                        <div class="wpdmpp-checkout__alert wpdmpp-checkout__alert--warning">
                            <?php _e('No payment methods available.', 'wpdm-premium-packages'); ?>
                        </div>
                    <?php else: ?>
                        <div class="wpdmpp-checkout__payment-methods">
                            <?php foreach ($payment_methods as $index => $method_id):
                                $gateway = $paymentService->getGateway(strtolower($method_id));
                                if (!$gateway) continue;

                                $method_title = isset($settings[$method_id]['title']) && $settings[$method_id]['title']
                                    ? $settings[$method_id]['title']
                                    : $gateway->getTitle();
                                $method_logo = $gateway->getIcon();
                                $is_paypal = $method_id === 'PayPal';
                            ?>
                            <label class="wpdmpp-checkout__payment-method <?php echo $index === 0 ? 'wpdmpp-checkout__payment-method--selected' : ''; ?>" data-method="<?php echo esc_attr($method_id); ?>">
                                <input type="radio"
                                       name="payment_method"
                                       value="<?php echo esc_attr($method_id); ?>"
                                       <?php checked($index, 0); ?>
                                       class="wpdmpp-checkout__payment-radio">
                                <div class="wpdmpp-checkout__payment-content">
                                    <img src="<?php echo esc_url($method_logo); ?>" alt="<?php echo esc_attr($method_title); ?>" class="wpdmpp-checkout__payment-logo">
                                    <span class="wpdmpp-checkout__payment-name"><?php echo esc_html($method_title); ?></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="wpdmpp-checkout__payment-check">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <?php
                        // Render gateway-specific checkout fields
                        foreach ($payment_methods as $index => $method_id):
                            $gateway = $paymentService->getGateway(strtolower($method_id));
                            if (!$gateway || $method_id === 'PayPal') continue;

                            $fields_html = $gateway->getCheckoutFields();
                            if (empty($fields_html)) continue;
                        ?>
                        <div class="wpdmpp-checkout__gateway-fields" data-gateway="<?php echo esc_attr($method_id); ?>" <?php if ($index !== 0) echo 'style="display:none;"'; ?>>
                            <?php echo $fields_html; ?>
                        </div>
                        <?php endforeach; ?>

                        <?php if ($has_paypal && $paypal_client_id): ?>
                        <!-- PayPal Smart Buttons Container -->
                        <div id="wpdmpp-paypal-buttons" class="wpdmpp-checkout__paypal-buttons" style="display: none;">
                            <div id="paypal-button-container"></div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$login_required): ?>

            <?php if ($require_privacy): ?>
            <!-- Privacy Agreement -->
            <div class="wpdmpp-checkout__privacy">
                <label class="wpdmpp-checkout__privacy-label">
                    <input type="checkbox" name="privacy_agreed" id="checkout-privacy" value="1" class="wpdmpp-checkout__checkbox">
                    <span><?php echo esc_html($privacy_label); ?></span>
                </label>
                <?php if ($privacy_page = get_option('wp_page_for_privacy_policy')): ?>
                <a href="<?php echo esc_url(get_permalink($privacy_page)); ?>" target="_blank" class="wpdmpp-checkout__privacy-link">
                    <?php _e('Read Privacy Policy', 'wpdm-premium-packages'); ?>
                </a>
                <?php endif; ?>
                <span class="wpdmpp-checkout__error" data-field="privacy"></span>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="wpdmpp-checkout__actions" id="checkout-actions">
                <button type="button" id="checkout-submit" class="wpdmpp-checkout__submit" <?php echo empty($payment_methods) ? 'disabled' : ''; ?>>
                    <span class="wpdmpp-checkout__submit-text">
                        <?php echo esc_html(get_wpdmpp_option('cobtn_label', __('Complete Purchase', 'wpdm-premium-packages'))); ?>
                    </span>
                    <span class="wpdmpp-checkout__submit-price"><?php echo esc_html($currency . number_format($total_with_tax, 2)); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="wpdmpp-checkout__submit-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </div>
            <?php endif; /* !$login_required */ ?>

            <!-- Alerts -->
            <div id="checkout-alerts"></div>
        </div>

        <!-- Right Column: Order Summary -->
        <div class="wpdmpp-checkout__sidebar">
            <div class="wpdmpp-checkout__summary">
                <h3 class="wpdmpp-checkout__summary-title">
                    <?php _e('Order Summary', 'wpdm-premium-packages'); ?>
                </h3>

                <!-- Items -->
                <div class="wpdmpp-checkout__items">
                    <?php foreach ($cart_items as $pid => $item):
                        $is_dynamic = (isset($item['product_type']) && $item['product_type'] === 'dynamic') || wpdm_valueof($item, 'type') === 'dynamic';

                        if (!$is_dynamic) {
                            if (!$pid || get_post_type($pid) !== 'wpdmpro') continue;
                            $post = get_post($pid);
                            if (!$post) continue;
                            $item_name = $post->post_title;
                        } else {
                            $item_name = isset($item['product_name']) ? $item['product_name'] : (isset($item['name']) ? $item['name'] : __('Item', 'wpdm-premium-packages'));
                        }

                        $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                        $price = isset($item['price']) ? (float) $item['price'] : 0;
                        $prices = isset($item['prices']) ? (float) $item['prices'] : 0;
                        $unit_price = $price + $prices;
                        $line_total = $unit_price * $quantity;

                        // Get thumbnail
                        $thumbnail_url = '';
                        if (!$is_dynamic) {
                            $thumbnail_url = get_the_post_thumbnail_url($pid, 'thumbnail');
                            if (!$thumbnail_url) {
                                $icon = get_post_meta($pid, '__wpdm_icon', true);
                                $thumbnail_url = $icon ?: '';
                            }
                        }
                    ?>
                    <div class="wpdmpp-checkout__item" data-product-id="<?php echo esc_attr($pid); ?>">
                        <?php if ($thumbnail_url): ?>
                        <img src="<?php echo esc_url($thumbnail_url); ?>" alt="" class="wpdmpp-checkout__item-image">
                        <?php else: ?>
                        <div class="wpdmpp-checkout__item-placeholder">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                            </svg>
                        </div>
                        <?php endif; ?>
                        <div class="wpdmpp-checkout__item-info">
                            <span class="wpdmpp-checkout__item-name"><?php echo esc_html($item_name); ?></span>
                            <?php
                            // Check for membership trial info
                            $item_info = isset($item['info']) ? $item['info'] : [];
                            $is_trial = !empty($item_info['trial_days']) && (int) $item_info['trial_days'] > 0;
                            $trial_days = $is_trial ? (int) $item_info['trial_days'] : 0;

                            if ($is_trial): ?>
                            <span class="wpdmpp-checkout__item-trial"><?php printf(esc_html__('%d-day free trial', 'wpdm-premium-packages'), $trial_days); ?></span>
                            <?php else: ?>
                            <span class="wpdmpp-checkout__item-qty"><?php printf(__('Qty: %d', 'wpdm-premium-packages'), $quantity); ?></span>
                            <?php endif; ?>
                            <?php
                            $license_label = '';
                            if (!empty($item['license'])) {
                                $license = maybe_unserialize($item['license']);
                                if (is_array($license) && isset($license['info']['name'])) {
                                    $license_label = $license['info']['name'];
                                } elseif (is_array($license) && isset($license['id'])) {
                                    $license_label = ucfirst($license['id']);
                                } elseif (is_string($license) && !is_serialized($license)) {
                                    $license_label = ucfirst($license);
                                }
                            }
                            if ($license_label): ?>
                            <span class="wpdmpp-checkout__item-license"><?php echo esc_html($license_label); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_trial):
                            $full_price = isset($item_info['full_price']) ? (float) $item_info['full_price'] : 0;
                        ?>
                        <span class="wpdmpp-checkout__item-price wpdmpp-checkout__item-price--trial">
                            <span style="color:#10b981;font-weight:600;font-size:13px;"><?php esc_html_e('Free', 'wpdm-premium-packages'); ?></span>
                            <?php if ($full_price > 0): ?>
                            <span style="font-size:11px;color:#94a3b8;display:block;"><?php echo esc_html(sprintf(__('then %s', 'wpdm-premium-packages'), $currency . number_format($full_price, 2))); ?></span>
                            <?php endif; ?>
                        </span>
                        <?php else: ?>
                        <span class="wpdmpp-checkout__item-price"><?php echo esc_html($currency . number_format($line_total, 2)); ?></span>
                        <?php endif; ?>
                        <?php if (!$is_dynamic): ?>
                        <button type="button" class="wpdmpp-checkout__item-edit" title="<?php esc_attr_e('Edit', 'wpdm-premium-packages'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Shared Edit Popover (hidden, repositioned per item) -->
                <div class="wpdmpp-checkout__item-popover" id="checkout-item-popover" style="display:none;">
                    <div class="wpdmpp-checkout__qty-stepper">
                        <label class="wpdmpp-checkout__qty-label"><?php _e('Qty', 'wpdm-premium-packages'); ?></label>
                        <button type="button" class="wpdmpp-checkout__qty-btn" data-action="minus">&minus;</button>
                        <span class="wpdmpp-checkout__qty-value" id="popover-qty">1</span>
                        <button type="button" class="wpdmpp-checkout__qty-btn" data-action="plus">&plus;</button>
                    </div>
                    <button type="button" class="wpdmpp-checkout__popover-update" id="popover-update">
                        <?php _e('Update', 'wpdm-premium-packages'); ?>
                    </button>
                    <button type="button" class="wpdmpp-checkout__popover-remove" id="popover-remove">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        <?php _e('Remove', 'wpdm-premium-packages'); ?>
                    </button>
                </div>

                <?php
                // Coupon (hidden for trial checkouts)
                $cart_coupon = wpdmpp_get_cart_coupon();
                $applied_coupon_code = is_array($cart_coupon) && !empty($cart_coupon['code']) ? $cart_coupon['code'] : '';
                ?>
                <!-- Coupon Section -->
                <div class="wpdmpp-checkout__coupon" id="checkout-coupon" <?php if ($checkout_trial_days > 0) echo 'style="display:none;"'; ?>>
                    <?php if ($applied_coupon_code): ?>
                    <div class="wpdmpp-checkout__coupon-badge" id="coupon-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                        </svg>
                        <span id="coupon-code-text"><?php echo esc_html($applied_coupon_code); ?></span>
                        <button type="button" class="wpdmpp-checkout__coupon-remove" id="coupon-remove" title="<?php esc_attr_e('Remove coupon', 'wpdm-premium-packages'); ?>">&times;</button>
                    </div>
                    <div class="wpdmpp-checkout__coupon-form" id="coupon-form" style="display:none;">
                        <input type="text" class="wpdmpp-checkout__coupon-input" id="coupon-input" placeholder="<?php esc_attr_e('Coupon code', 'wpdm-premium-packages'); ?>">
                        <button type="button" class="wpdmpp-checkout__coupon-apply" id="coupon-apply"><?php _e('Apply', 'wpdm-premium-packages'); ?></button>
                    </div>
                    <?php else: ?>
                    <div class="wpdmpp-checkout__coupon-badge" id="coupon-badge" style="display:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                        </svg>
                        <span id="coupon-code-text"></span>
                        <button type="button" class="wpdmpp-checkout__coupon-remove" id="coupon-remove" title="<?php esc_attr_e('Remove coupon', 'wpdm-premium-packages'); ?>">&times;</button>
                    </div>
                    <div class="wpdmpp-checkout__coupon-form" id="coupon-form">
                        <input type="text" class="wpdmpp-checkout__coupon-input" id="coupon-input" placeholder="<?php esc_attr_e('Coupon code', 'wpdm-premium-packages'); ?>">
                        <button type="button" class="wpdmpp-checkout__coupon-apply" id="coupon-apply"><?php _e('Apply', 'wpdm-premium-packages'); ?></button>
                    </div>
                    <?php endif; ?>
                    <span class="wpdmpp-checkout__coupon-msg" id="coupon-msg"></span>
                </div>

                <!-- Totals -->
                <div class="wpdmpp-checkout__totals">
                    <?php if ($checkout_trial_days > 0): ?>
                    <div class="wpdmpp-checkout__total-row">
                        <span><?php _e('Due today', 'wpdm-premium-packages'); ?></span>
                        <span id="checkout-subtotal" style="color:#10b981;font-weight:600;"><?php esc_html_e('Free', 'wpdm-premium-packages'); ?></span>
                    </div>
                    <?php if ($checkout_full_price > 0): ?>
                    <div class="wpdmpp-checkout__total-row" style="font-size:13px;color:#64748b;">
                        <span><?php printf(esc_html__('After %d-day trial', 'wpdm-premium-packages'), $checkout_trial_days); ?></span>
                        <span><?php echo esc_html($currency . number_format($checkout_full_price, 2)); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="wpdmpp-checkout__total-row wpdmpp-checkout__total-row--total">
                        <span><?php _e('Total', 'wpdm-premium-packages'); ?></span>
                        <span id="checkout-total" style="color:#10b981;font-weight:700;"><?php echo esc_html($currency . '0.00'); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="wpdmpp-checkout__total-row">
                        <span><?php _e('Subtotal', 'wpdm-premium-packages'); ?></span>
                        <span id="checkout-subtotal"><?php echo esc_html($currency . number_format($subtotal, 2)); ?></span>
                    </div>
                    <div class="wpdmpp-checkout__total-row wpdmpp-checkout__total-row--discount" id="checkout-discount-row" <?php if ($discount <= 0) echo 'style="display:none;"'; ?>>
                        <span><?php _e('Discount', 'wpdm-premium-packages'); ?></span>
                        <span id="checkout-discount">-<?php echo esc_html($currency . number_format($discount, 2)); ?></span>
                    </div>
                    <?php if ($tax_active): ?>
                    <div class="wpdmpp-checkout__total-row wpdmpp-checkout__total-row--tax" id="checkout-tax-row" <?php if ($tax <= 0) echo 'style="display:none;"'; ?>>
                        <span><?php echo apply_filters('wpdmpp_checkout_tax_label', __('Tax', 'wpdm-premium-packages')); ?></span>
                        <span id="checkout-tax"><?php echo esc_html($currency . number_format($tax, 2)); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="wpdmpp-checkout__total-row wpdmpp-checkout__total-row--total">
                        <span><?php _e('Total', 'wpdm-premium-packages'); ?></span>
                        <span id="checkout-total"><?php echo esc_html($currency . number_format($total_with_tax, 2)); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Security Badge -->
                <div class="wpdmpp-checkout__security">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    <span><?php _e('Secure checkout. Your data is protected.', 'wpdm-premium-packages'); ?></span>
                </div>

                <!-- Continue Shopping -->
                <div class="wpdmpp-checkout__continue">
                    <a href="<?php echo esc_url(wpdmpp_continue_shopping_url()); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                        <?php _e('Continue Shopping', 'wpdm-premium-packages'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="wpdmpp-checkout__loading" id="checkout-loading" style="display: none;">
        <div class="wpdmpp-checkout__loading-spinner"></div>
        <span class="wpdmpp-checkout__loading-text"><?php _e('Processing...', 'wpdm-premium-packages'); ?></span>
    </div>
</div>
