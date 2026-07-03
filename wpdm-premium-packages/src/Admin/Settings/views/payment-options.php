<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $payment_methods;
$payment_methods = apply_filters('payment_method', $payment_methods);
$saved_order = get_wpdmpp_option('pmorders', []);
if (!empty($saved_order) && is_array($saved_order)) {
    // Build sorted list from saved order, keeping only currently registered methods
    $sorted = [];
    foreach ($saved_order as $pm) {
        if (in_array($pm, $payment_methods, true)) {
            $sorted[] = $pm;
        }
    }
    // Append any new methods not in saved order
    foreach ($payment_methods as $pm) {
        if (!in_array($pm, $sorted, true)) {
            $sorted[] = $pm;
        }
    }
    $payment_methods = $sorted;
}
$settings['currency_position'] = isset($settings['currency_position']) ? $settings['currency_position'] : 'before';
?>

<style>
/* Payment Methods Accordion */
.wpdmpp-pm-section {
    margin-top: 20px;
}

.wpdmpp-pm-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.wpdmpp-pm-card__header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.wpdmpp-pm-card__icon {
    width: 40px;
    height: 40px;
    background: #e0e7ff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wpdmpp-pm-card__icon svg {
    width: 20px;
    height: 20px;
    color: #4f46e5;
}

.wpdmpp-pm-card__title {
    color: #1e293b;
    font-size: 15px;
    font-weight: 600;
    margin: 0;
}

.wpdmpp-pm-card__subtitle {
    color: #64748b;
    font-size: 12px;
    margin-top: 2px;
}

/* Accordion List */
.wpdmpp-pm-list {
    padding: 16px;
}

.wpdmpp-pm-item {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 10px;
    transition: all 0.2s ease;
    overflow: hidden;
}

.wpdmpp-pm-item:last-child {
    margin-bottom: 0;
}

.wpdmpp-pm-item:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.wpdmpp-pm-item.ui-sortable-helper {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    transform: rotate(1deg);
}

.wpdmpp-pm-item.ui-sortable-placeholder {
    visibility: visible !important;
    background: #f1f5f9;
    border: 2px dashed #94a3b8;
}

.wpdmpp-pm-item__header {
    display: flex;
    align-items: center;
    padding: 14px 16px;
    cursor: pointer;
    user-select: none;
    gap: 12px;
    transition: background 0.15s ease;
}

.wpdmpp-pm-item__header:hover {
    background: #f8fafc;
}

.wpdmpp-pm-item__drag {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    cursor: grab;
    border-radius: 6px;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.wpdmpp-pm-item__drag:hover {
    background: #e2e8f0;
    color: #64748b;
}

.wpdmpp-pm-item__drag:active {
    cursor: grabbing;
}

.wpdmpp-pm-item__drag svg {
    width: 18px;
    height: 18px;
}

.wpdmpp-pm-item__info {
    flex: 1;
    min-width: 0;
}

.wpdmpp-pm-item__name {
    font-size: 14px !important;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.wpdmpp-pm-item__id {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 400;
    font-family: monospace;
}

.wpdmpp-pm-item__status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    flex-shrink: 0;
}

.wpdmpp-pm-item__status--active {
    background: #dcfce7;
    color: #15803d;
}

.wpdmpp-pm-item__status--inactive {
    background: #fef2f2;
    color: #dc2626;
}

.wpdmpp-pm-item__status--admin {
    background: #fef3c7;
    color: #b45309;
}

.wpdmpp-pm-item__status svg {
    width: 12px;
    height: 12px;
}

.wpdmpp-pm-item__toggle {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    border-radius: 6px;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.wpdmpp-pm-item__toggle svg {
    width: 18px;
    height: 18px;
    transition: transform 0.25s ease;
}

.wpdmpp-pm-item.is-expanded .wpdmpp-pm-item__toggle svg {
    transform: rotate(180deg);
}

.wpdmpp-pm-item__content {
    display: none;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.wpdmpp-pm-item.is-expanded .wpdmpp-pm-item__content {
    display: block;
}

/* ── Gateway Settings Form ── */

/* Fields */
.wpdmpp-pm-item__body .wpdmpp-field {
    margin-bottom: 0;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
}

.wpdmpp-pm-item__body .wpdmpp-field:last-child {
    border-bottom: none;
}

.select2.select2-container {
    min-width: 200px;
}

/* Field labels */
.wpdmpp-field__label {
    display: block !important;
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

/* Inputs */
.wpdmpp-pm-item__body .form-control {
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 14px;
    color: #1e293b;
    background: #fff;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.wpdmpp-pm-item__body .form-control:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.08);
    outline: none;
}

.wpdmpp-pm-item__body select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 20px;
    padding-right: 36px;
}

/* Help text */
.wpdmpp-field__help {
    font-weight: 400;
    font-size: 12px;
    color: #94a3b8;
    line-height: 1.5;
}

/* Section headings */
.wpdmpp-section-heading {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin: 0;
    padding: 12px 20px;
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    border-top: 1px solid #e2e8f0;
}

/* Separators */
.wpdmpp-separator {
    height: 0;
    border-top: 1px dashed #e2e8f0;
    margin: 0;
}

/* Notices */
.wpdmpp-notice {
    font-size: 13px;
    color: #475569;
    line-height: 1.6;
    padding: 12px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}

.wpdmpp-notice a {
    color: #4f46e5;
    text-decoration: none;
    font-weight: 500;
}

.wpdmpp-notice a:hover {
    color: #4338ca;
    text-decoration: underline;
}

.wpdmpp-notice strong {
    color: #334155;
    font-weight: 600;
}

/* Alerts */
.wpdmpp-alert {
    font-size: 13px;
    line-height: 1.6;
    padding: 14px 20px;
    margin: 0;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.wpdmpp-alert::before {
    flex-shrink: 0;
    font-size: 16px;
    line-height: 1.3;
}

.wpdmpp-alert--warning {
    background: #fffbeb;
    border-left: 3px solid #f59e0b;
    color: #92400e;
}

.wpdmpp-alert--warning::before {
    content: "\26A0";
}

.wpdmpp-alert--danger {
    background: #fef2f2;
    border-left: 3px solid #ef4444;
    color: #991b1b;
}

.wpdmpp-alert--danger::before {
    content: "\2716";
}

.wpdmpp-alert--info {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
    color: #1e40af;
}

.wpdmpp-alert--info::before {
    content: "\2139";
}

.wpdmpp-alert--success {
    background: #f0fdf4;
    border-left: 3px solid #22c55e;
    color: #166534;
}

.wpdmpp-alert--success::before {
    content: "\2714";
}

/* Readonly / Copy fields */
.wpdmpp-readonly-wrap {
    position: relative;
}

.wpdmpp-readonly.form-control {
    background: #f1f5f9;
    color: #475569;
    font-family: 'SF Mono', SFMono-Regular, ui-monospace, Consolas, monospace;
    font-size: 13px;
    cursor: pointer;
    border-style: dashed;
    border-color: #cbd5e1;
    padding-right: 120px;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.wpdmpp-readonly.form-control:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
}

.wpdmpp-readonly.form-control:focus {
    box-shadow: none;
    border-color: #94a3b8;
}

.wpdmpp-readonly__hint {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 11px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
    pointer-events: none;
}

/* Toggle switch */
.wpdmpp-field--toggle {
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
}

.wpdmpp-toggle {
    align-items: center;
    gap: 12px;
    cursor: pointer;
    margin: 0 !important;
    -webkit-user-select: none;
    user-select: none;
    display: flex !important;
}

.wpdmpp-toggle input[type="checkbox"] {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0,0,0,0) !important;
    border: 0 !important;
    display: block !important;
    -webkit-appearance: none !important;
    appearance: none !important;
}

.wpdmpp-toggle input[type="checkbox"]::before,
.wpdmpp-toggle input[type="checkbox"]::after {
    display: none !important;
}

.wpdmpp-toggle__switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
    background: #cbd5e1;
    border-radius: 11px;
    flex-shrink: 0;
    transition: background 0.2s ease;
}

.wpdmpp-toggle__switch::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 16px;
    height: 16px;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    transition: transform 0.2s ease;
}

.wpdmpp-toggle input[type="checkbox"]:checked + .wpdmpp-toggle__switch {
    background: #6366f1;
}

.wpdmpp-toggle input[type="checkbox"]:checked + .wpdmpp-toggle__switch::after {
    transform: translateX(18px);
}

.wpdmpp-toggle__text {
    font-size: 14px;
    font-weight: 500;
    color: #1e293b;
}

/* Form wrapper padding reset */
.wpdmpp-pm-item__body {
    padding: 0;
}

.wpdmpp-gateway-settings-form {
    /* no extra padding — fields handle their own */
}

/* Empty state */
.wpdmpp-pm-empty {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

.wpdmpp-pm-empty svg {
    width: 48px;
    height: 48px;
    color: #cbd5e1;
    margin-bottom: 12px;
}

/* Status Radio Button Group */
.wpdmpp-status-radio-group {
    display: inline-flex;
    background: #f1f5f9;
    border-radius: 10px;
    padding: 5px;
    gap: 5px;
    border: 1px solid #e2e8f0;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
}

.wpdmpp-status-option {
    position: relative;
    cursor: pointer;
    margin: 0 !important;
}

.wpdmpp-status-option input[type="radio"] {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
}

.wpdmpp-status-label {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    background: transparent;
    border: none;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.wpdmpp-status-label svg {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    opacity: 0.6;
    transition: opacity 0.2s ease;
}

.wpdmpp-status-option:hover .wpdmpp-status-label {
    background: rgba(255, 255, 255, 0.7);
    color: #475569;
}

.wpdmpp-status-option:hover .wpdmpp-status-label svg {
    opacity: 0.8;
}

.wpdmpp-status-option.active .wpdmpp-status-label,
.wpdmpp-status-option input[type="radio"]:checked + .wpdmpp-status-label {
    background: #ffffff;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
}

.wpdmpp-status-option input[type="radio"]:checked + .wpdmpp-status-label svg {
    opacity: 1;
}

/* Color variants for different statuses */
.wpdmpp-status-option input[value="0"]:checked + .wpdmpp-status-label {
    color: #dc2626;
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    box-shadow: 0 1px 3px rgba(220, 38, 38, 0.15), 0 1px 2px rgba(220, 38, 38, 0.1);
}

.wpdmpp-status-option input[value="1"]:checked + .wpdmpp-status-label {
    color: #15803d;
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    box-shadow: 0 1px 3px rgba(21, 128, 61, 0.15), 0 1px 2px rgba(21, 128, 61, 0.1);
}

.wpdmpp-status-option input[value="2"]:checked + .wpdmpp-status-label {
    color: #b45309;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    box-shadow: 0 1px 3px rgba(217, 119, 6, 0.15), 0 1px 2px rgba(217, 119, 6, 0.1);
}
</style>

<div class="wpdmpp-pm-section">
    <div class="wpdmpp-pm-card">
        <div class="wpdmpp-pm-card__header">
            <div class="wpdmpp-pm-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-pm-card__title"><?php _e("Payment Methods", "wpdm-premium-packages"); ?></h3>
                <div class="wpdmpp-pm-card__subtitle"><?php _e("Drag to reorder, click to configure", "wpdm-premium-packages"); ?></div>
            </div>
        </div>

        <div class="wpdmpp-pm-list" id="wpdmpp-payment-methods">
            <?php
            $has_methods = false;
            $paymentService = \WPDMPP\Payment\PaymentService::instance();

            foreach ($payment_methods as $payment_method) {
                $gateway = $paymentService->getGateway(strtolower($payment_method));

                if (!$gateway) {
                    continue;
                }

                $has_methods = true;
                $settings_key = $gateway->getSettingsKey();
                $name = $gateway->getTitle();
                $enabled_value = get_wpdmpp_option("{$settings_key}/enabled", 0, 'int');
                $is_active = $enabled_value !== 0;
                $settings_html = $gateway->renderCommonFields() . $gateway->renderSettings();
                ?>
                <div class="wpdmpp-pm-item" data-pm="<?php echo esc_attr($settings_key); ?>">
                    <div class="wpdmpp-pm-item__header">
                        <div class="wpdmpp-pm-item__drag" title="<?php esc_attr_e('Drag to reorder', 'wpdm-premium-packages'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" />
                            </svg>
                        </div>
                        <div class="wpdmpp-pm-item__info">
                            <h4 class="wpdmpp-pm-item__name">
                                <?php echo esc_html(ucwords($name)); ?>
                                <span class="wpdmpp-pm-item__id"><?php echo esc_html($settings_key); ?></span>
                            </h4>
                        </div>
                        <?php
                        $status_class = 'wpdmpp-pm-item__status--inactive';
                        $status_icon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>';
                        $status_label = __("Inactive", "wpdm-premium-packages");
                        if ($enabled_value === 1) {
                            $status_class = 'wpdmpp-pm-item__status--active';
                            $status_icon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>';
                            $status_label = __("Active", "wpdm-premium-packages");
                        } elseif ($enabled_value === 2) {
                            $status_class = 'wpdmpp-pm-item__status--admin';
                            $status_icon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>';
                            $status_label = __("Admin Only", "wpdm-premium-packages");
                        }
                        ?>
                        <div class="wpdmpp-pm-item__status <?php echo $status_class; ?>" id="pmstatus_<?php echo esc_attr($settings_key); ?>">
                            <?php echo $status_icon; ?>
                            <?php echo esc_html($status_label); ?>
                        </div>
                        <div class="wpdmpp-pm-item__toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                    <div class="wpdmpp-pm-item__content">
                        <div class="wpdmpp-pm-item__body">
                            <?php echo $settings_html; ?>
                        </div>
                    </div>
                    <input type="hidden" name="_wpdmpp_settings[pmorders][]" value="<?php echo esc_attr($settings_key); ?>">
                </div>
                <?php
            }

            if (!$has_methods): ?>
                <div class="wpdmpp-pm-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <p><?php _e("No payment methods available", "wpdm-premium-packages"); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Currency Configuration Card -->
<div class="wpdmpp-pm-section">
    <div class="wpdmpp-pm-card wpdmpp-currency-card">
        <div class="wpdmpp-pm-card__header">
            <div class="wpdmpp-pm-card__icon wpdmpp-pm-card__icon--currency">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-pm-card__title"><?php _e("Currency Configuration", "wpdm-premium-packages"); ?></h3>
                <div class="wpdmpp-pm-card__subtitle"><?php _e("Set your store currency and display format", "wpdm-premium-packages"); ?></div>
            </div>
        </div>

        <div class="wpdmpp-currency-body">
            <!-- Currency Preview -->
            <div class="wpdmpp-currency-preview">
                <div class="wpdmpp-currency-preview__label"><?php _e("Preview", "wpdm-premium-packages"); ?></div>
                <div class="wpdmpp-currency-preview__value" id="currency-preview">
                    <?php
                    $symbol = isset($settings['currency']) ? \WPDMPP\Core\CurrencyService::getInstance()->getCurrency($settings['currency'])['symbol'] : '$';
                    $thousand = isset($settings['thousand_separator']) ? $settings['thousand_separator'] : ',';
                    $decimal = isset($settings['decimal_separator']) ? $settings['decimal_separator'] : '.';
                    $decimals = isset($settings['decimal_points']) ? $settings['decimal_points'] : '2';
                    $position = isset($settings['currency_position']) ? $settings['currency_position'] : 'before';
                    $sample = number_format(1234.56, (int)$decimals, $decimal, $thousand);
                    echo $position === 'before' ? $symbol . $sample : $sample . $symbol;
                    ?>
                </div>
            </div>

            <!-- Currency -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <?php _e('Currency', 'wpdm-premium-packages'); ?>
                </div>
                <div class="panel-body">
                    <?php echo \WPDMPP\Core\CurrencyService::getInstance()->getCurrencyDropdown(
                        isset($settings['currency']) ? $settings['currency'] : '',
                        '_wpdmpp_settings[currency]',
                        '',
                        'form-control wpdmpp-currecy-dropdown'
                    ); ?>
                </div>
            </div>
            <!-- Currency Fields Grid -->
            <div class="wpdmpp-currency-grid">
                <!-- Currency Position -->
                <div class="wpdmpp-currency-field wpdmpp-currency-field--full">
                    <label class="wpdmpp-currency-field__label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <?php _e('Symbol Position', 'wpdm-premium-packages'); ?>
                    </label>
                    <div class="wpdmpp-currency-position">
                        <label class="wpdmpp-currency-position__option <?php echo $settings['currency_position'] === 'before' ? 'is-selected' : ''; ?>">
                            <input type="radio" name="_wpdmpp_settings[currency_position]" value="before" <?php checked($settings['currency_position'], 'before'); ?>>
                            <span class="wpdmpp-currency-position__example">$99</span>
                            <span class="wpdmpp-currency-position__text"><?php _e('Before', 'wpdm-premium-packages'); ?></span>
                        </label>
                        <label class="wpdmpp-currency-position__option <?php echo $settings['currency_position'] === 'after' ? 'is-selected' : ''; ?>">
                            <input type="radio" name="_wpdmpp_settings[currency_position]" value="after" <?php checked($settings['currency_position'], 'after'); ?>>
                            <span class="wpdmpp-currency-position__example">99$</span>
                            <span class="wpdmpp-currency-position__text"><?php _e('After', 'wpdm-premium-packages'); ?></span>
                        </label>
                    </div>
                </div>

                <!-- Separators Row -->
                <div class="wpdmpp-currency-field">
                    <label class="wpdmpp-currency-field__label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                        <?php _e('Thousand Separator', 'wpdm-premium-packages'); ?>
                    </label>
                    <input class="form-control wpdmpp-currency-input" type="text" name="_wpdmpp_settings[thousand_separator]" value="<?php echo esc_attr(isset($settings['thousand_separator']) ? $settings['thousand_separator'] : ','); ?>" placeholder="," />
                    <span class="wpdmpp-currency-field__hint">1,000,000</span>
                </div>

                <div class="wpdmpp-currency-field">
                    <label class="wpdmpp-currency-field__label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <?php _e('Decimal Separator', 'wpdm-premium-packages'); ?>
                    </label>
                    <input class="form-control wpdmpp-currency-input" type="text" name="_wpdmpp_settings[decimal_separator]" value="<?php echo esc_attr(isset($settings['decimal_separator']) ? $settings['decimal_separator'] : '.'); ?>" placeholder="." />
                    <span class="wpdmpp-currency-field__hint">99.99</span>
                </div>

                <div class="wpdmpp-currency-field">
                    <label class="wpdmpp-currency-field__label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                        <?php _e('Decimal Places', 'wpdm-premium-packages'); ?>
                    </label>
                    <input class="form-control wpdmpp-currency-input" type="number" min="0" max="4" name="_wpdmpp_settings[decimal_points]" value="<?php echo esc_attr(isset($settings['decimal_points']) ? $settings['decimal_points'] : '2'); ?>" />
                    <span class="wpdmpp-currency-field__hint"><?php _e('0-4 digits', 'wpdm-premium-packages'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Currency Card Icon */
.wpdmpp-pm-card__icon--currency {
    background: #fef3c7;
}

.wpdmpp-pm-card__icon--currency svg {
    color: #d97706;
}

/* Currency Body */
.wpdmpp-currency-body {
    padding: 24px;
}

/* Currency Preview */
.wpdmpp-currency-preview {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    margin-bottom: 24px;
}

.wpdmpp-currency-preview__label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #92400e;
    margin-bottom: 8px;
}

.wpdmpp-currency-preview__value {
    font-size: 32px;
    font-weight: 700;
    color: #78350f;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Currency Grid */
.wpdmpp-currency-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.wpdmpp-currency-field--full {
    grid-column: 1 / -1;
}

.wpdmpp-currency-field__label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.wpdmpp-currency-field__label svg {
    width: 16px;
    height: 16px;
    color: #9ca3af;
}

.wpdmpp-currency-input {
    width: 100%;
    max-width: 120px;
    text-align: center;
    font-size: 15px;
    font-weight: 500;
}

.wpdmpp-currency-field__hint {
    display: block;
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
}

/* Currency Position Toggle */
.wpdmpp-currency-position {
    display: flex;
    gap: 12px;
}

.wpdmpp-currency-position__option {
    flex: 1;
    max-width: 180px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 16px 20px;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.wpdmpp-currency-position__option:hover {
    border-color: #cbd5e1;
    background: #f1f5f9;
}

.wpdmpp-currency-position__option.is-selected {
    border-color: #d97706;
    background: #fffbeb;
}

.wpdmpp-currency-position__option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.wpdmpp-currency-position__example {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
}

.wpdmpp-currency-position__text {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
}

.wpdmpp-currency-position__option.is-selected .wpdmpp-currency-position__example {
    color: #d97706;
}

.wpdmpp-currency-position__option.is-selected .wpdmpp-currency-position__text {
    color: #92400e;
}

/* Responsive */
@media (max-width: 768px) {
    .wpdmpp-currency-grid {
        grid-template-columns: 1fr;
    }

    .wpdmpp-currency-position {
        flex-direction: column;
    }

    .wpdmpp-currency-position__option {
        max-width: none;
    }

    .wpdmpp-currency-input {
        max-width: none;
    }
}
</style>
<script>
    (function($) {
        // Payment options initialization function - can be called on load and after AJAX
        function initPaymentOptions() {
            var $paymentMethods = $('#wpdmpp-payment-methods');
            var $currencyDropdown = $('.wpdmpp-currecy-dropdown');

            // Initialize sortable with drag handle (only if not already initialized)
            if ($paymentMethods.length && !$paymentMethods.hasClass('ui-sortable')) {
                $paymentMethods.sortable({
                    handle: '.wpdmpp-pm-item__drag',
                    placeholder: 'wpdmpp-pm-item ui-sortable-placeholder',
                    tolerance: 'pointer',
                    cursor: 'grabbing',
                    opacity: 0.9,
                    start: function(e, ui) {
                        ui.placeholder.height(ui.item.outerHeight());
                    }
                });
            }

            // Tooltips
            $('.ttip').tooltip();

            // Select2 for currency dropdowns (only if not already initialized)
            if ($currencyDropdown.length && !$currencyDropdown.hasClass('select2-hidden-accessible')) {
                $currencyDropdown.select2({width: '300px'});
            }
        }

        // Currency preview update function
        function updateCurrencyPreview() {
            var $currencySelect = $('select[name="_wpdmpp_settings[currency]"]');
            var symbol = $currencySelect.find('option:selected').text().match(/\(([^)]+)\)/);
            symbol = symbol ? symbol[1] : '$';

            var thousand = $('input[name="_wpdmpp_settings[thousand_separator]"]').val() || ',';
            var decimal = $('input[name="_wpdmpp_settings[decimal_separator]"]').val() || '.';
            var decimals = parseInt($('input[name="_wpdmpp_settings[decimal_points]"]').val()) || 2;
            var position = $('input[name="_wpdmpp_settings[currency_position]"]:checked').val() || 'before';

            // Format sample number
            var num = 1234.56;
            var parts = num.toFixed(decimals).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousand);
            var formatted = parts.length > 1 ? parts[0] + decimal + parts[1] : parts[0];

            var preview = position === 'before' ? symbol + formatted : formatted + symbol;
            $('#currency-preview').text(preview);
        }

        // Initialize on document ready
        $(function() {
            initPaymentOptions();

            // Accordion toggle - click on header (excluding drag handle)
            // Using event delegation so it works after AJAX loads
            $(document).off('click.wpdmpp-pm').on('click.wpdmpp-pm', '.wpdmpp-pm-item__header', function(e) {
                // Ignore if clicking on drag handle
                if ($(e.target).closest('.wpdmpp-pm-item__drag').length) {
                    return;
                }

                var $item = $(this).closest('.wpdmpp-pm-item');

                // Close other items (accordion behavior)
                $('.wpdmpp-pm-item').not($item).removeClass('is-expanded');

                // Toggle current item
                $item.toggleClass('is-expanded');

                // Smooth scroll into view if expanding
                if ($item.hasClass('is-expanded')) {
                    setTimeout(function() {
                        var itemTop = $item.offset().top - 100;
                        if ($(window).scrollTop() > itemTop) {
                            $('html, body').animate({ scrollTop: itemTop }, 200);
                        }
                    }, 100);
                }
            });

            // Currency position toggle
            $(document).off('change.wpdmpp-currency').on('change.wpdmpp-currency', '.wpdmpp-currency-position__option input', function() {
                $('.wpdmpp-currency-position__option').removeClass('is-selected');
                $(this).closest('.wpdmpp-currency-position__option').addClass('is-selected');
                updateCurrencyPreview();
            });

            // Payment method status radio change - update header badge
            $(document).off('change.wpdmpp-status').on('change.wpdmpp-status', '.wpdmpp-status-radio-group input[type="radio"]', function() {
                var $item = $(this).closest('.wpdmpp-pm-item');
                var $statusBadge = $item.find('.wpdmpp-pm-item__status');
                var value = parseInt($(this).val());

                // Update active state on radio options
                $(this).closest('.wpdmpp-status-radio-group').find('.wpdmpp-status-option').removeClass('active');
                $(this).closest('.wpdmpp-status-option').addClass('active');

                // Remove all status classes
                $statusBadge.removeClass('wpdmpp-pm-item__status--active wpdmpp-pm-item__status--inactive wpdmpp-pm-item__status--admin');

                // Update the status badge in the header
                if (value === 1) {
                    $statusBadge
                        .addClass('wpdmpp-pm-item__status--active')
                        .html('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>' + '<?php _e("Active", "wpdm-premium-packages"); ?>');
                } else if (value === 2) {
                    $statusBadge
                        .addClass('wpdmpp-pm-item__status--admin')
                        .html('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>' + '<?php _e("Admin Only", "wpdm-premium-packages"); ?>');
                } else {
                    $statusBadge
                        .addClass('wpdmpp-pm-item__status--inactive')
                        .html('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>' + '<?php _e("Inactive", "wpdm-premium-packages"); ?>');
                }
            });

            // Bind currency preview update events with namespacing
            $(document).off('change.wpdmpp-preview').on('change.wpdmpp-preview', 'select[name="_wpdmpp_settings[currency]"]', updateCurrencyPreview);
            $(document).off('input.wpdmpp-preview').on('input.wpdmpp-preview', 'input[name="_wpdmpp_settings[thousand_separator]"], input[name="_wpdmpp_settings[decimal_separator]"], input[name="_wpdmpp_settings[decimal_points]"]', updateCurrencyPreview);
        });

        // Re-initialize when tab becomes visible (for AJAX-loaded content)
        $(document).on('shown.bs.tab click', 'a[data-pptab="pppayment"], a[href="#pppayment"]', function() {
            setTimeout(initPaymentOptions, 100);
        });

        // Also listen for custom WPDM settings tab events
        $(document).on('wpdm_settings_tab_shown', function(e, tabId) {
            if (tabId === 'pppayment' || tabId === 'ppsettings') {
                setTimeout(initPaymentOptions, 100);
            }
        });

        // Expose init function globally for manual re-initialization
        window.wpdmppInitPaymentOptions = initPaymentOptions;

    })(jQuery);
</script>
