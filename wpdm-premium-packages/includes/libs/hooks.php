<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action('init', 'wpdmpp_load_payment_methods');
add_action('init', 'wpdmpp_remove_cart_item');
add_action('init', 'wpdmpp_get_purchased_items');

add_action('wp_loaded', 'wpdmpp_load_saved_cart');


add_action('wp', 'wpdmpp_download_order_note_attachment');

add_filter("wpdm_email_template_tags", "wpdmpp_email_template_tags");
add_filter("wpdm_email_templates", "wpdmpp_email_templates");

if (is_admin()) {
    add_action('admin_head', 'wpdmpp_head');
    add_action('admin_footer', 'wpdmpp_admin_footer');
    add_action('wp_ajax_assign_user_2order', 'wpdmpp_assign_user_2order');
    add_action('wp_ajax_RecalculateSales', 'wpdmpp_recalculate_sales');
    add_action('publish_post', 'wpdmpp_notify_product_accepted');
    add_action('wp_ajax_wpdmpp_export_product_customers', 'wpdmpp_export_product_customers');
}

if (!is_admin()) {
    //add to cart using form submit
    add_action('init', 'wpdmpp_add_to_cart');
    //add to cart from url call
    add_action('init', 'wpdmpp_add_to_cart_ucb');

    add_action('init', 'wpdmpp_withdraw_request');

    add_action('wp_head', 'wpdmpp_head');

    add_action('init', 'wpdmpp_update_cart');
    add_action('init', 'wpdmpp_delete_product');
}

add_action('wpdm_onstart_download', 'wpdmpp_validate_download');


add_action("wp_ajax_nopriv_update_guest_billing", "wpdmpp_update_guest_billing");
add_action("wp_ajax_wpdmpp_delete_frontend_order", "wpdmpp_delete_frontend_order");

// Mini Cart Admin Settings
add_action("wpdmpp_basic_options", "wpdmpp_mini_cart_settings_panel");
add_action("wpdmpp_after_save_settings", "wpdmpp_save_mini_cart_settings");

/**
 * Save Mini Cart Settings
 * Hooked to wpdmpp_after_save_settings to save Mini Cart settings separately
 */
function wpdmpp_save_mini_cart_settings() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if mini cart settings are being saved
    if (isset($_POST['_wpdmpp_mini_cart_settings'])) {
        $settings_json = wp_unslash($_POST['_wpdmpp_mini_cart_settings']);
        $settings = json_decode($settings_json, true);

        if (is_array($settings)) {
            // Sanitize settings
            $sanitized = [
                'enabled' => !empty($settings['enabled']),
                'auto_inject' => !empty($settings['auto_inject']),
                'display_style' => sanitize_text_field($settings['display_style'] ?? 'dropdown'),
                'position' => sanitize_text_field($settings['position'] ?? 'top-right'),
                'show_item_count' => !empty($settings['show_item_count']),
                'show_subtotal' => !empty($settings['show_subtotal']),
                'show_thumbnails' => !empty($settings['show_thumbnails']),
                'auto_open_on_add' => !empty($settings['auto_open_on_add']),
                'auto_close_delay' => absint($settings['auto_close_delay'] ?? 3000),
                'mobile_full_screen' => !empty($settings['mobile_full_screen']),
                'mobile_breakpoint' => absint($settings['mobile_breakpoint'] ?? 768),
                'primary_color' => sanitize_hex_color($settings['primary_color'] ?? '#6366f1'),
                'button_text_color' => sanitize_hex_color($settings['button_text_color'] ?? '#ffffff'),
                'badge_color' => sanitize_hex_color($settings['badge_color'] ?? '#ef4444'),
            ];

            update_option('wpdmpp_mini_cart_settings', $sanitized);
        }
    }
}

/**
 * Export product customers to CSV
 */
function wpdmpp_export_product_customers() {
    // Verify nonce
    if (!wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'wpdmpp_export_customers')) {
        wp_die(__('Security check failed', 'wpdm-premium-packages'));
    }

    // Check admin capability
    if (!current_user_can('manage_options')) {
        wp_die(__('Permission denied', 'wpdm-premium-packages'));
    }

    $pid = intval($_REQUEST['pid'] ?? 0);
    if (!$pid) {
        wp_die(__('Invalid product ID', 'wpdm-premium-packages'));
    }

    global $wpdb;
    $orders_table = $wpdb->prefix . 'ahm_orders';
    $items_table = $wpdb->prefix . 'ahm_order_items';
    $users_table = $wpdb->users;

    // Get all orders for this product with user info
    $orders = $wpdb->get_results($wpdb->prepare("
        SELECT
            o.order_id,
            o.uid,
            o.title as order_title,
            o.billing_info,
            o.payment_status,
            o.payment_method,
            o.coupon_code,
            o.date as order_timestamp,
            o.total as order_total,
            oi.price,
            oi.quantity,
            oi.license as license_info,
            (oi.price * oi.quantity) as item_total,
            u.display_name as user_name,
            u.user_email
        FROM {$orders_table} o
        INNER JOIN {$items_table} oi ON oi.oid = o.order_id
        LEFT JOIN {$users_table} u ON o.uid = u.ID
        WHERE oi.pid = %d
        ORDER BY o.date DESC
    ", $pid));

    // Get product title
    $product_title = get_the_title($pid);
    $product_slug = sanitize_title($product_title);

    // Set headers for CSV download
    $filename = 'customers-' . $product_slug . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Write CSV header
    fputcsv($output, [
        __('Order ID', 'wpdm-premium-packages'),
        __('Order Date', 'wpdm-premium-packages'),
        __('Customer Name', 'wpdm-premium-packages'),
        __('Customer Email', 'wpdm-premium-packages'),
        __('Phone', 'wpdm-premium-packages'),
        __('Address', 'wpdm-premium-packages'),
        __('Product', 'wpdm-premium-packages'),
        __('License Type', 'wpdm-premium-packages'),
        __('Price', 'wpdm-premium-packages'),
        __('Quantity', 'wpdm-premium-packages'),
        __('Total', 'wpdm-premium-packages'),
        __('Coupon', 'wpdm-premium-packages'),
        __('Payment Method', 'wpdm-premium-packages'),
        __('Payment Status', 'wpdm-premium-packages')
    ]);

    $currency = wpdmpp_currency_sign();

    // Write data rows
    foreach ($orders as $order) {
        $order_date = $order->order_timestamp ? date('Y-m-d H:i:s', $order->order_timestamp) : '';

        // Parse billing info (may be serialized)
        $billing = [];
        if (!empty($order->billing_info)) {
            $billing = maybe_unserialize($order->billing_info);
            if (!is_array($billing)) {
                $billing = [];
            }
        }

        // Get customer name - prefer billing info, then order title, then user name
        $customer_name = '';
        if (!empty($billing['first_name']) || !empty($billing['last_name'])) {
            $customer_name = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        } elseif (!empty($order->order_title)) {
            $customer_name = $order->order_title;
        } elseif (!empty($order->user_name)) {
            $customer_name = $order->user_name;
        }

        // Get customer email - prefer billing info, then user email
        $customer_email = '';
        if (!empty($billing['email'])) {
            $customer_email = $billing['email'];
        } elseif (!empty($order->user_email)) {
            $customer_email = $order->user_email;
        }

        // Get phone and address from billing
        $phone = $billing['phone'] ?? '';
        $address_parts = array_filter([
            $billing['address'] ?? '',
            $billing['address_2'] ?? '',
            $billing['city'] ?? '',
            $billing['state'] ?? '',
            $billing['zip'] ?? '',
            $billing['country'] ?? ''
        ]);
        $address = implode(', ', $address_parts);

        // Parse license info
        $license_type = 'Standard';
        if (!empty($order->license_info)) {
            $license_data = maybe_unserialize($order->license_info);
            if (is_array($license_data) && isset($license_data['name'])) {
                $license_type = $license_data['name'];
            } elseif (is_string($license_data)) {
                $license_type = $license_data;
            }
        }

        fputcsv($output, [
            $order->order_id,
            $order_date,
            $customer_name,
            $customer_email,
            $phone,
            $address,
            $product_title,
            $license_type,
            $currency . number_format((float)$order->price, 2),
            $order->quantity,
            $currency . number_format((float)$order->item_total, 2),
            $order->coupon_code ?: '',
            $order->payment_method ?: '',
            $order->payment_status
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Mini Cart Settings Panel
 * Renders the admin settings UI for the Mini Cart feature
 */
function wpdmpp_mini_cart_settings_panel() {
    // Include MiniCartAPI to get default settings
    if (!class_exists('WPDMPP\Libs\MiniCartAPI')) {
        return;
    }

    $settings = get_option('wpdmpp_mini_cart_settings', \WPDMPP\Libs\MiniCartAPI::getDefaultSettings());
    ?>

    <!-- Mini Cart Settings -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--indigo">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('Mini Cart', 'wpdm-premium-packages'); ?></h3>
                <p class="wpdmpp-card__subtitle"><?php _e('Configure the mini cart widget for your store', 'wpdm-premium-packages'); ?></p>
            </div>
        </div>
        <div class="wpdmpp-card__body">
            <!-- Enable Mini Cart -->
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <div class="wpdmpp-toggle-option__label"><?php _e('Enable Mini Cart', 'wpdm-premium-packages'); ?></div>
                    <div class="wpdmpp-toggle-option__desc"><?php _e('Show a mini cart widget on your site', 'wpdm-premium-packages'); ?></div>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[mini_cart][enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <!-- Auto Inject -->
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <div class="wpdmpp-toggle-option__label"><?php _e('Auto Inject in Footer', 'wpdm-premium-packages'); ?></div>
                    <div class="wpdmpp-toggle-option__desc"><?php _e('Automatically add the mini cart to your site footer', 'wpdm-premium-packages'); ?></div>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[mini_cart][auto_inject]" value="1" <?php checked(!empty($settings['auto_inject'])); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-divider"></div>

            <!-- Display Style -->
            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Display Style', 'wpdm-premium-packages'); ?></label>
                <select name="_wpdmpp_settings[mini_cart][display_style]" class="form-control" style="max-width: 300px;">
                    <option value="dropdown" <?php selected($settings['display_style'] ?? 'dropdown', 'dropdown'); ?>><?php _e('Dropdown', 'wpdm-premium-packages'); ?></option>
                    <option value="slide_panel" <?php selected($settings['display_style'] ?? '', 'slide_panel'); ?>><?php _e('Slide Panel', 'wpdm-premium-packages'); ?></option>
                    <option value="floating" <?php selected($settings['display_style'] ?? '', 'floating'); ?>><?php _e('Floating Button', 'wpdm-premium-packages'); ?></option>
                </select>
                <p class="wpdmpp-form-group__note"><?php _e('Choose how the mini cart should be displayed', 'wpdm-premium-packages'); ?></p>
            </div>

            <!-- Position -->
            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Position', 'wpdm-premium-packages'); ?></label>
                <select name="_wpdmpp_settings[mini_cart][position]" class="form-control" style="max-width: 300px;">
                    <option value="top-right" <?php selected($settings['position'] ?? 'top-right', 'top-right'); ?>><?php _e('Top Right', 'wpdm-premium-packages'); ?></option>
                    <option value="top-left" <?php selected($settings['position'] ?? '', 'top-left'); ?>><?php _e('Top Left', 'wpdm-premium-packages'); ?></option>
                    <option value="bottom-right" <?php selected($settings['position'] ?? '', 'bottom-right'); ?>><?php _e('Bottom Right', 'wpdm-premium-packages'); ?></option>
                    <option value="bottom-left" <?php selected($settings['position'] ?? '', 'bottom-left'); ?>><?php _e('Bottom Left', 'wpdm-premium-packages'); ?></option>
                </select>
                <p class="wpdmpp-form-group__note"><?php _e('Position for floating button and slide panel', 'wpdm-premium-packages'); ?></p>
            </div>

            <div class="wpdmpp-divider"></div>

            <h4 style="margin: 0 0 16px; font-size: 14px; font-weight: 600; color: #374151;"><?php _e('Display Options', 'wpdm-premium-packages'); ?></h4>

            <!-- Show Item Count -->
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <div class="wpdmpp-toggle-option__label"><?php _e('Show Item Count', 'wpdm-premium-packages'); ?></div>
                    <div class="wpdmpp-toggle-option__desc"><?php _e('Display the number of items in the cart', 'wpdm-premium-packages'); ?></div>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[mini_cart][show_item_count]" value="1" <?php checked($settings['show_item_count'] ?? true); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <!-- Show Subtotal -->
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <div class="wpdmpp-toggle-option__label"><?php _e('Show Subtotal', 'wpdm-premium-packages'); ?></div>
                    <div class="wpdmpp-toggle-option__desc"><?php _e('Display the cart subtotal amount', 'wpdm-premium-packages'); ?></div>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[mini_cart][show_subtotal]" value="1" <?php checked($settings['show_subtotal'] ?? true); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <!-- Show Thumbnails -->
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <div class="wpdmpp-toggle-option__label"><?php _e('Show Product Thumbnails', 'wpdm-premium-packages'); ?></div>
                    <div class="wpdmpp-toggle-option__desc"><?php _e('Display product images in the cart', 'wpdm-premium-packages'); ?></div>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[mini_cart][show_thumbnails]" value="1" <?php checked($settings['show_thumbnails'] ?? true); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-divider"></div>

            <h4 style="margin: 0 0 16px; font-size: 14px; font-weight: 600; color: #374151;"><?php _e('Behavior', 'wpdm-premium-packages'); ?></h4>

            <!-- Auto Open on Add -->
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <div class="wpdmpp-toggle-option__label"><?php _e('Auto Open on Add to Cart', 'wpdm-premium-packages'); ?></div>
                    <div class="wpdmpp-toggle-option__desc"><?php _e('Automatically open the mini cart when an item is added', 'wpdm-premium-packages'); ?></div>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[mini_cart][auto_open_on_add]" value="1" <?php checked($settings['auto_open_on_add'] ?? true); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <!-- Auto Close Delay -->
            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Auto Close Delay (ms)', 'wpdm-premium-packages'); ?></label>
                <input type="number" name="_wpdmpp_settings[mini_cart][auto_close_delay]" class="form-control" style="max-width: 150px;" value="<?php echo esc_attr($settings['auto_close_delay'] ?? 3000); ?>" min="0" step="500">
                <p class="wpdmpp-form-group__note"><?php _e('Time in milliseconds before auto-closing. Set to 0 to disable', 'wpdm-premium-packages'); ?></p>
            </div>

            <div class="wpdmpp-divider"></div>

            <h4 style="margin: 0 0 16px; font-size: 14px; font-weight: 600; color: #374151;"><?php _e('Mobile Settings', 'wpdm-premium-packages'); ?></h4>

            <!-- Mobile Full Screen -->
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <div class="wpdmpp-toggle-option__label"><?php _e('Mobile Full Screen', 'wpdm-premium-packages'); ?></div>
                    <div class="wpdmpp-toggle-option__desc"><?php _e('Use full screen overlay on mobile devices', 'wpdm-premium-packages'); ?></div>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[mini_cart][mobile_full_screen]" value="1" <?php checked($settings['mobile_full_screen'] ?? true); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <!-- Mobile Breakpoint -->
            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Mobile Breakpoint (px)', 'wpdm-premium-packages'); ?></label>
                <input type="number" name="_wpdmpp_settings[mini_cart][mobile_breakpoint]" class="form-control" style="max-width: 150px;" value="<?php echo esc_attr($settings['mobile_breakpoint'] ?? 768); ?>" min="320" max="1024">
                <p class="wpdmpp-form-group__note"><?php _e('Screen width in pixels at which mobile mode activates', 'wpdm-premium-packages'); ?></p>
            </div>

            <div class="wpdmpp-divider"></div>

            <h4 style="margin: 0 0 16px; font-size: 14px; font-weight: 600; color: #374151;"><?php _e('Colors', 'wpdm-premium-packages'); ?></h4>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <!-- Primary Color -->
                <div class="wpdmpp-form-group">
                    <label class="wpdmpp-form-group__label"><?php _e('Primary Color', 'wpdm-premium-packages'); ?></label>
                    <input type="color" name="_wpdmpp_settings[mini_cart][primary_color]" value="<?php echo esc_attr($settings['primary_color'] ?? '#6366f1'); ?>" style="width: 60px; height: 36px; padding: 2px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer;">
                </div>

                <!-- Button Text Color -->
                <div class="wpdmpp-form-group">
                    <label class="wpdmpp-form-group__label"><?php _e('Button Text Color', 'wpdm-premium-packages'); ?></label>
                    <input type="color" name="_wpdmpp_settings[mini_cart][button_text_color]" value="<?php echo esc_attr($settings['button_text_color'] ?? '#ffffff'); ?>" style="width: 60px; height: 36px; padding: 2px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer;">
                </div>

                <!-- Badge Color -->
                <div class="wpdmpp-form-group">
                    <label class="wpdmpp-form-group__label"><?php _e('Badge Color', 'wpdm-premium-packages'); ?></label>
                    <input type="color" name="_wpdmpp_settings[mini_cart][badge_color]" value="<?php echo esc_attr($settings['badge_color'] ?? '#ef4444'); ?>" style="width: 60px; height: 36px; padding: 2px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer;">
                </div>
            </div>

            <div class="wpdmpp-divider"></div>

            <!-- Shortcode Info -->
            <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 16px;">
                <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #166534; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" />
                    </svg>
                    <?php _e('Shortcode', 'wpdm-premium-packages'); ?>
                </h4>
                <p style="margin: 0 0 12px; font-size: 13px; color: #15803d;"><?php _e('Use this shortcode to manually place the mini cart anywhere on your site:', 'wpdm-premium-packages'); ?></p>
                <code style="display: inline-block; background: #dcfce7; padding: 8px 12px; border-radius: 6px; font-size: 13px; color: #166534; font-family: monospace;">[wpdmpp_mini_cart]</code>

                <h5 style="margin: 16px 0 8px; font-size: 13px; font-weight: 600; color: #166534;"><?php _e('Shortcode Attributes', 'wpdm-premium-packages'); ?></h5>
                <ul style="margin: 0; padding: 0 0 0 20px; font-size: 12px; color: #15803d; line-height: 1.8;">
                    <li><code>style</code> - <?php _e('Display style: dropdown, slide_panel, or floating', 'wpdm-premium-packages'); ?></li>
                    <li><code>position</code> - <?php _e('Position: top-right, top-left, bottom-right, or bottom-left', 'wpdm-premium-packages'); ?></li>
                    <li><code>class</code> - <?php _e('Additional CSS classes', 'wpdm-premium-packages'); ?></li>
                </ul>
                <p style="margin: 12px 0 0; font-size: 12px; color: #15803d;">
                    <?php _e('Example:', 'wpdm-premium-packages'); ?> <code style="background: #dcfce7; padding: 2px 6px; border-radius: 4px;">[wpdmpp_mini_cart style="floating" position="bottom-right"]</code>
                </p>
            </div>

            <!-- PHP Usage Info -->
            <div style="background: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px; padding: 16px; margin-top: 16px;">
                <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #1e40af; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <?php _e('PHP Usage', 'wpdm-premium-packages'); ?>
                </h4>
                <p style="margin: 0 0 12px; font-size: 13px; color: #1d4ed8;"><?php _e('Use these PHP functions in your theme templates:', 'wpdm-premium-packages'); ?></p>

                <h5 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #1e40af;"><?php _e('Display Mini Cart', 'wpdm-premium-packages'); ?></h5>
<pre style="background: #dbeafe; padding: 12px; border-radius: 6px; font-size: 12px; color: #1e40af; font-family: monospace; margin: 0 0 16px; overflow-x: auto; white-space: pre-wrap;">
// Basic usage (uses settings from admin)
echo \WPDMPP\Libs\MiniCart::render();

// With custom options
echo \WPDMPP\Libs\MiniCart::render([
    'display_style' => 'floating',  // dropdown, slide_panel, floating
    'position' => 'bottom-right',   // top-right, top-left, bottom-right, bottom-left
    'show_count' => 'yes',          // yes or no
    'show_total' => 'yes',          // yes or no
    'icon' => 'cart',               // cart, bag, or basket
    'class' => 'my-custom-class',   // Additional CSS classes
]);
</pre>

                <h5 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #1e40af;"><?php _e('Check if Mini Cart is Enabled', 'wpdm-premium-packages'); ?></h5>
<pre style="background: #dbeafe; padding: 12px; border-radius: 6px; font-size: 12px; color: #1e40af; font-family: monospace; margin: 0 0 16px; overflow-x: auto; white-space: pre-wrap;">
if (\WPDMPP\Libs\MiniCart::isEnabled()) {
    // Mini cart is enabled
}
</pre>

                <h5 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #1e40af;"><?php _e('Get Mini Cart Settings', 'wpdm-premium-packages'); ?></h5>
<pre style="background: #dbeafe; padding: 12px; border-radius: 6px; font-size: 12px; color: #1e40af; font-family: monospace; margin: 0; overflow-x: auto; white-space: pre-wrap;">
$settings = \WPDMPP\Libs\MiniCart::getSettings();
</pre>
            </div>

            <!-- Nav Menu Integration Info -->
            <div style="background: #fff7ed; border: 1px solid #fdba74; border-radius: 8px; padding: 16px; margin-top: 16px;">
                <h4 style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #c2410c; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <?php _e('Nav Menu Integration', 'wpdm-premium-packages'); ?>
                </h4>
                <p style="margin: 0 0 12px; font-size: 13px; color: #ea580c;"><?php _e('Add the mini cart to any WordPress navigation menu item without editing theme files:', 'wpdm-premium-packages'); ?></p>

                <h5 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #c2410c;"><?php _e('Steps:', 'wpdm-premium-packages'); ?></h5>
                <ol style="margin: 0 0 16px; padding: 0 0 0 20px; font-size: 13px; color: #ea580c; line-height: 1.8;">
                    <li><?php _e('Go to <strong>Appearance → Menus</strong> in WordPress admin', 'wpdm-premium-packages'); ?></li>
                    <li><?php _e('Add a <strong>Custom Link</strong> to your menu (URL: #, Link Text: Cart)', 'wpdm-premium-packages'); ?></li>
                    <li><?php _e('Expand the menu item and click "Screen Options" at top-right if you don\'t see CSS Classes field', 'wpdm-premium-packages'); ?></li>
                    <li><?php _e('Check "CSS Classes" in Screen Options to enable the field', 'wpdm-premium-packages'); ?></li>
                    <li><?php _e('In the <strong>CSS Classes</strong> field, enter: <code style="background: #fed7aa; padding: 2px 6px; border-radius: 4px; font-family: monospace;">wpdmpp-minicart</code>', 'wpdm-premium-packages'); ?></li>
                    <li><?php _e('Save Menu', 'wpdm-premium-packages'); ?></li>
                </ol>

                <div style="background: #ffedd5; border-radius: 6px; padding: 12px; margin-top: 12px;">
                    <p style="margin: 0; font-size: 12px; color: #c2410c;">
                        <strong><?php _e('Note:', 'wpdm-premium-packages'); ?></strong>
                        <?php _e('The mini cart will automatically replace the menu item link with a cart icon, item count, and total. Clicking it opens a dropdown panel with cart items, subtotal, and checkout buttons.', 'wpdm-premium-packages'); ?>
                    </p>
                </div>

                <div style="background: #ffedd5; border-radius: 6px; padding: 12px; margin-top: 8px;">
                    <p style="margin: 0; font-size: 12px; color: #c2410c;">
                        <strong><?php _e('Mobile:', 'wpdm-premium-packages'); ?></strong>
                        <?php _e('On mobile devices, the cart panel slides up from the bottom of the screen for easier interaction.', 'wpdm-premium-packages'); ?>
                    </p>
                </div>
            </div>

        </div>
    </div>

    <script>
    jQuery(function($) {
        // Handle settings save for mini cart
        $('form').on('submit', function() {
            // Get all mini cart settings
            var miniCartSettings = {};
            $('input[name^="_wpdmpp_settings[mini_cart]"], select[name^="_wpdmpp_settings[mini_cart]"]').each(function() {
                var name = $(this).attr('name').replace('_wpdmpp_settings[mini_cart][', '').replace(']', '');
                var type = $(this).attr('type');

                if (type === 'checkbox') {
                    miniCartSettings[name] = $(this).is(':checked') ? 1 : 0;
                } else {
                    miniCartSettings[name] = $(this).val();
                }
            });

            // Store in hidden field for processing
            if ($('#wpdmpp_mini_cart_settings').length === 0) {
                $(this).append('<input type="hidden" id="wpdmpp_mini_cart_settings" name="_wpdmpp_mini_cart_settings" />');
            }
            $('#wpdmpp_mini_cart_settings').val(JSON.stringify(miniCartSettings));
        });
    });
    </script>
    <?php
}
