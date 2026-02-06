<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $payment_methods;
$payment_methods = apply_filters('payment_method', $payment_methods);
$xpayment_methods = $payment_methods;
$payment_methods = count(get_wpdmpp_option('pmorders', array())) == count($payment_methods) ? get_wpdmpp_option('pmorders') : $payment_methods;
$new_pgs = array_diff($xpayment_methods, $payment_methods);
if(is_array($new_pgs) && count($new_pgs) > 0){
    foreach ($new_pgs as $new_pg){
        $payment_methods[] = $new_pg;
    }
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

.wpdmpp-pm-item__body {
    padding: 20px;
}

/* Fix inner form styles */
.wpdmpp-pm-item__body .form-group {
    margin-bottom: 15px;
}

.wpdmpp-pm-item__body label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
    display: block;
}

.wpdmpp-pm-item__body .form-control {
    border-radius: 6px;
    border-color: #e2e8f0;
}

.wpdmpp-pm-item__body .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            foreach ($payment_methods as $payment_method) {
                $payment_gateway_class = 'WPDMPP\Libs\PaymentMethods\\'.$payment_method;

                if (class_exists($payment_gateway_class)) {
                    $has_methods = true;
                    $obj = new $payment_gateway_class();
                    $name = isset($obj->GatewayName) ? $obj->GatewayName : $payment_method;
                    $is_active = get_wpdmpp_option("{$payment_method}/enabled", 0, 'int') !== 0;
                    ?>
                    <div class="wpdmpp-pm-item" data-pm="<?php echo esc_attr($payment_method); ?>">
                        <div class="wpdmpp-pm-item__header">
                            <div class="wpdmpp-pm-item__drag" title="<?php esc_attr_e('Drag to reorder', 'wpdm-premium-packages'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" />
                                </svg>
                            </div>
                            <div class="wpdmpp-pm-item__info">
                                <h4 class="wpdmpp-pm-item__name">
                                    <?php echo esc_html(ucwords($name)); ?>
                                    <span class="wpdmpp-pm-item__id"><?php echo esc_html($payment_method); ?></span>
                                </h4>
                            </div>
                            <div class="wpdmpp-pm-item__status <?php echo $is_active ? 'wpdmpp-pm-item__status--active' : 'wpdmpp-pm-item__status--inactive'; ?>" id="pmstatus_<?php echo esc_attr($payment_method); ?>">
                                <?php if ($is_active): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <?php _e("Active", "wpdm-premium-packages"); ?>
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <?php _e("Inactive", "wpdm-premium-packages"); ?>
                                <?php endif; ?>
                            </div>
                            <div class="wpdmpp-pm-item__toggle">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        <div class="wpdmpp-pm-item__content">
                            <div class="wpdmpp-pm-item__body">
                                <?php echo \WPDMPP\Libs\Payment::GateWaySettings($payment_gateway_class); ?>
                            </div>
                        </div>
                        <input type="hidden" name="_wpdmpp_settings[pmorders][]" value="<?php echo esc_attr($payment_method); ?>">
                    </div>
                    <?php
                }
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
                    $symbol = isset($settings['currency']) ? \WPDMPP\Libs\Currencies::getCurrency($settings['currency'])['symbol'] : '$';
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
                    <?php \WPDMPP\Libs\Currencies::CurrencyListHTML(array('name'=>'_wpdmpp_settings[currency]', 'selected'=> (isset($settings['currency'])?$settings['currency']:''), 'class' => 'wpdmpp-currecy-dropdown')); ?>
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
    jQuery(function($) {
        // Initialize sortable with drag handle
        $('#wpdmpp-payment-methods').sortable({
            handle: '.wpdmpp-pm-item__drag',
            placeholder: 'wpdmpp-pm-item ui-sortable-placeholder',
            tolerance: 'pointer',
            cursor: 'grabbing',
            opacity: 0.9,
            start: function(e, ui) {
                ui.placeholder.height(ui.item.outerHeight());
            }
        });

        // Accordion toggle - click on header (excluding drag handle)
        $(document).on('click', '.wpdmpp-pm-item__header', function(e) {
            // Ignore if clicking on drag handle
            if ($(e.target).closest('.wpdmpp-pm-item__drag').length) {
                return;
            }

            var $item = $(this).closest('.wpdmpp-pm-item');
            var $content = $item.find('.wpdmpp-pm-item__content');

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

        // Tooltips
        $('.ttip').tooltip();

        // Select2 for currency dropdowns
        jQuery('.wpdmpp-currecy-dropdown').select2({width:'300px'});

        // Currency position toggle
        $('.wpdmpp-currency-position__option input').on('change', function() {
            $('.wpdmpp-currency-position__option').removeClass('is-selected');
            $(this).closest('.wpdmpp-currency-position__option').addClass('is-selected');
            updateCurrencyPreview();
        });

        // Currency preview update
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

        // Bind update events
        $('select[name="_wpdmpp_settings[currency]"]').on('change', updateCurrencyPreview);
        $('input[name="_wpdmpp_settings[thousand_separator]"]').on('input', updateCurrencyPreview);
        $('input[name="_wpdmpp_settings[decimal_separator]"]').on('input', updateCurrencyPreview);
        $('input[name="_wpdmpp_settings[decimal_points]"]').on('input', updateCurrencyPreview);
    });
</script>
