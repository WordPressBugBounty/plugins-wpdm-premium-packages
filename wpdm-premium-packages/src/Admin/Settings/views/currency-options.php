<?php
/**
 * Currency and exchange rate settings.
 *
 * @package WPDMPP\Admin\Settings
 * @since   7.2.0
 */

use WPDMPP\Core\CurrencyService;
use WPDMPP\Currency\ExchangeRateService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings   = get_option( '_wpdmpp_settings' );
$settings['currency_position'] = $settings['currency_position'] ?? 'before';

// Symbol for the currently selected currency, used by the preview and by the
// symbol-position examples. getCurrency() returns null for a code it does not
// know, so the fallback is not decorative - indexing null would be fatal.
$wpdmpp_cur_data   = isset( $settings['currency'] )
	? CurrencyService::getInstance()->getCurrency( $settings['currency'] )
	: null;
$wpdmpp_cur_symbol = is_array( $wpdmpp_cur_data ) && ! empty( $wpdmpp_cur_data['symbol'] )
	? $wpdmpp_cur_data['symbol']
	: '$';
$currencies = CurrencyService::getInstance()->getCurrencies();
$rateSvc    = ExchangeRateService::getInstance();
$health     = $rateSvc->getHealth();
$providers  = $rateSvc->getProviders();

$baseCurrency  = wpdmpp_base_currency_code();
$activeProvider = (string) ( $settings['rate_provider'] ?? \WPDMPP\Currency\Providers\FrankfurterProvider::ID );
$maxAge        = $rateSvc->getMaxAgeHours();
$refreshEvery  = $rateSvc->getRefreshIntervalHours();
$lastError     = get_option( '__wpdmpp_rates_last_error', '' );
$legacyForeign = method_exists( '\WPDMPP_INSTALLER', 'countLegacyForeignOrders' )
	? \WPDMPP_INSTALLER::countLegacyForeignOrders()
	: 0;
?>

<style>
	.wpdmpp-cur-health { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
	.wpdmpp-cur-health__item { flex: 1 1 170px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; }
	.wpdmpp-cur-health__label { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #64748b; font-weight: 600; }
	.wpdmpp-cur-health__value { font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 4px; word-break: break-word; }
	.wpdmpp-cur-health__item--warn { background: #fffbeb; border-color: #fcd34d; }
	.wpdmpp-cur-health__item--warn .wpdmpp-cur-health__value { color: #b45309; }
	.wpdmpp-cur-rates { width: 100%; border-collapse: collapse; margin-top: 8px; }
	.wpdmpp-cur-rates th { text-align: left; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; padding: 8px 10px; }
	.wpdmpp-cur-rates td { padding: 9px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
	.wpdmpp-cur-rates td.num { text-align: right; font-variant-numeric: tabular-nums; }
	.wpdmpp-cur-empty { padding: 22px; text-align: center; color: #94a3b8; font-size: 13px; }
</style>

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
                    $symbol = $wpdmpp_cur_symbol;
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
                            <span class="wpdmpp-currency-position__example js-position-example" data-position="before"><?php echo esc_html( $wpdmpp_cur_symbol . '99' ); ?></span>
                            <span class="wpdmpp-currency-position__text"><?php _e('Before', 'wpdm-premium-packages'); ?></span>
                        </label>
                        <label class="wpdmpp-currency-position__option <?php echo $settings['currency_position'] === 'after' ? 'is-selected' : ''; ?>">
                            <input type="radio" name="_wpdmpp_settings[currency_position]" value="after" <?php checked($settings['currency_position'], 'after'); ?>>
                            <span class="wpdmpp-currency-position__example js-position-example" data-position="after"><?php echo esc_html( '99' . $wpdmpp_cur_symbol ); ?></span>
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

/* Multi-currency card icon */
.wpdmpp-pm-card__icon--multicurrency {
    background: #dbeafe;
}

.wpdmpp-pm-card__icon--multicurrency svg {
    color: #2563eb;
}

/* Exchange rate card icon */
.wpdmpp-pm-card__icon--rates {
    background: #d1fae5;
}

.wpdmpp-pm-card__icon--rates svg {
    color: #059669;
}

/* Card body, matching the padding the payment cards use */
.wpdmpp-pm-card__body {
    padding: 20px;
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
jQuery(function ($) {
    // Select2 on the currency dropdown, which moved here from the payment tab
    // along with the card it belongs to.
    var $currencyDropdown = $('.wpdmpp-currecy-dropdown');
    if ($currencyDropdown.length && !$currencyDropdown.hasClass('select2-hidden-accessible') && $.fn.select2) {
        $currencyDropdown.select2({ width: '300px' });
    }

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

                // The position examples illustrate the choice, so they have to show
                // the symbol actually selected rather than a hard-coded dollar.
                $('.js-position-example').each(function () {
                    var $el = $(this);
                    $el.text($el.data('position') === 'before' ? symbol + '99' : '99' + symbol);
                });
            }

                // Currency position toggle
                $(document).off('change.wpdmpp-currency').on('change.wpdmpp-currency', '.wpdmpp-currency-position__option input', function() {
                    $('.wpdmpp-currency-position__option').removeClass('is-selected');
                    $(this).closest('.wpdmpp-currency-position__option').addClass('is-selected');
                    updateCurrencyPreview();
                });

                // Bind currency preview update events with namespacing
                $(document).off('change.wpdmpp-preview').on('change.wpdmpp-preview', 'select[name="_wpdmpp_settings[currency]"]', updateCurrencyPreview);
                $(document).off('input.wpdmpp-preview').on('input.wpdmpp-preview', 'input[name="_wpdmpp_settings[thousand_separator]"], input[name="_wpdmpp_settings[decimal_separator]"], input[name="_wpdmpp_settings[decimal_points]"]', updateCurrencyPreview);

    updateCurrencyPreview();
});
</script>

<div class="wpdmpp-pm-section">
    <div class="wpdmpp-pm-card">
        <div class="wpdmpp-pm-card__header">
            <div class="wpdmpp-pm-card__icon wpdmpp-pm-card__icon--multicurrency">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-pm-card__title"><?php esc_html_e( 'Multi-Currency', 'wpdm-premium-packages' ); ?></h3>
                <div class="wpdmpp-pm-card__subtitle"><?php esc_html_e( 'Choose the reporting currency and which currencies shoppers can pay in', 'wpdm-premium-packages' ); ?></div>
            </div>
        </div>

        <div class="wpdmpp-pm-card__body">

		<div class="row">
			<div class="col-md-6 form-group">
				<label><?php esc_html_e( 'Reporting currency', 'wpdm-premium-packages' ); ?></label>
				<select name="_wpdmpp_settings[base_currency]" class="form-control">
					<option value=""><?php esc_html_e( 'Same as store currency', 'wpdm-premium-packages' ); ?></option>
					<?php foreach ( $currencies as $code => $c ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( ( $settings['base_currency'] ?? '' ), $code ); ?>>
							<?php echo esc_html( $code . ' — ' . ( $c['name'] ?? $code ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="help-block"><?php esc_html_e( 'Every sales report is converted to this currency so totals across currencies can be added. Changing it does not alter what customers were charged.', 'wpdm-premium-packages' ); ?></span>
			</div>
		</div>

		<hr/>

		<div class="row">
			<div class="col-md-12 form-group">
				<label>
					<input type="hidden" name="_wpdmpp_settings[multicurrency_enabled]" value="0"/>
					<input type="checkbox" name="_wpdmpp_settings[multicurrency_enabled]" value="1"
						<?php checked( (int) ( $settings['multicurrency_enabled'] ?? 0 ), 1 ); ?> />
					<?php esc_html_e( 'Let shoppers choose their currency', 'wpdm-premium-packages' ); ?>
				</label>
				<span class="help-block">
					<?php esc_html_e( 'Prices are shown and charged in the currency the shopper picks. Switching is disabled automatically whenever rates are stale, so a price is never quoted from an out-of-date rate.', 'wpdm-premium-packages' ); ?>
				</span>
			</div>
		</div>

		<div class="row">
			<div class="col-md-12 form-group">
				<label><?php esc_html_e( 'Currencies offered', 'wpdm-premium-packages' ); ?></label>
				<?php
				$enabledCurrencies = get_wpdmpp_option( 'enabled_currencies', [] );
				$enabledCurrencies = is_array( $enabledCurrencies ) ? array_map( 'strtoupper', $enabledCurrencies ) : [];
				$gatewaySupported  = \WPDMPP\Currency\PresentmentService::getInstance()->getGatewaySupportedCurrencies();
				?>
				<select name="_wpdmpp_settings[enabled_currencies][]" class="form-control" multiple size="8">
					<?php foreach ( $currencies as $code => $c ) : ?>
						<?php
						if ( $code === $baseCurrency ) {
							continue;
						}
						$unsupported = ! empty( $gatewaySupported ) && ! in_array( $code, $gatewaySupported, true );
						?>
						<option value="<?php echo esc_attr( $code ); ?>"
							<?php selected( in_array( $code, $enabledCurrencies, true ) ); ?>
							<?php disabled( $unsupported ); ?>>
							<?php
							echo esc_html( $code . ' — ' . ( $c['name'] ?? $code ) );
							if ( $unsupported ) {
								echo esc_html__( '  (no enabled gateway accepts this)', 'wpdm-premium-packages' );
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="help-block">
					<?php esc_html_e( 'The store currency is always offered. A currency only appears to shoppers once it also has a usable exchange rate; currencies no enabled payment gateway accepts cannot be selected, because checkout would fail at the final step.', 'wpdm-premium-packages' ); ?>
				</span>
				<?php
				// Anything enabled but not yet offered is almost always a missing rate.
				// Saying so here saves working it out from an empty dropdown.
				$wpdmpp_offered  = \WPDMPP\Currency\PresentmentService::getInstance()->getAvailable();
				$wpdmpp_pending  = array_diff( $enabledCurrencies, $wpdmpp_offered );
				if ( ! empty( $wpdmpp_pending ) ) :
				?>
					<div class="alert alert-warning" style="margin-top:10px">
						<?php
						printf(
							/* translators: %s: comma separated currency codes */
							esc_html__( 'Enabled but not yet shown to shoppers: %s. Refresh the rates below to fetch them.', 'wpdm-premium-packages' ),
							esc_html( implode( ', ', $wpdmpp_pending ) )
						);
						?>
					</div>
				<?php endif; ?>
			</div>
		</div>


		<hr/>

		<div class="row">
			<div class="col-md-6 form-group">
				<label>
					<input type="hidden" name="_wpdmpp_settings[currency_float_enabled]" value="0"/>
					<input type="checkbox" name="_wpdmpp_settings[currency_float_enabled]" value="1"
						<?php checked( (int) get_wpdmpp_option( 'currency_float_enabled', 0, 'int' ), 1 ); ?> />
					<?php esc_html_e( 'Show a floating currency panel on every page', 'wpdm-premium-packages' ); ?>
				</label>
				<span class="help-block">
					<?php esc_html_e( 'A small pinned control so shoppers can switch currency anywhere, not only on the cart. Hidden automatically when there is only one currency to choose from.', 'wpdm-premium-packages' ); ?>
				</span>
			</div>

			<div class="col-md-6 form-group">
				<label><?php esc_html_e( 'Panel position', 'wpdm-premium-packages' ); ?></label>
				<?php $wpdmpp_float_pos = get_wpdmpp_option( 'currency_float_position', 'middle-left' ); ?>
				<select name="_wpdmpp_settings[currency_float_position]" class="form-control">
					<?php
					foreach ( [
						'bottom-left'  => __( 'Bottom left', 'wpdm-premium-packages' ),
						'bottom-right' => __( 'Bottom right', 'wpdm-premium-packages' ),
						'middle-left'  => __( 'Middle left', 'wpdm-premium-packages' ),
						'middle-right' => __( 'Middle right', 'wpdm-premium-packages' ),
						'top-right'    => __( 'Top right', 'wpdm-premium-packages' ),
					] as $wpdmpp_pos_key => $wpdmpp_pos_label ) :
						?>
						<option value="<?php echo esc_attr( $wpdmpp_pos_key ); ?>" <?php selected( $wpdmpp_float_pos, $wpdmpp_pos_key ); ?>>
							<?php echo esc_html( $wpdmpp_pos_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="help-block">
					<?php esc_html_e( 'Other floating elements claim corners too: the File Cart tab sits middle right, and a download-limit or chat button often takes a bottom corner.', 'wpdm-premium-packages' ); ?>
				</span>
			</div>
		</div>

		<div class="row">
			<div class="col-md-4 form-group">
				<label><?php esc_html_e( 'Distance from the edge', 'wpdm-premium-packages' ); ?></label>
				<div class="input-group">
					<input type="number" min="0" max="400" class="form-control"
					       name="_wpdmpp_settings[currency_float_offset]"
					       value="<?php echo esc_attr( get_wpdmpp_option( 'currency_float_offset', 20, 'int' ) ); ?>"/>
					<span class="input-group-addon">px</span>
				</div>
				<span class="help-block">
					<?php esc_html_e( 'Increase this to clear another floating button already in that corner.', 'wpdm-premium-packages' ); ?>
				</span>
			</div>
		</div>

	</div>
    </div>
</div>

<div class="wpdmpp-pm-section">
    <div class="wpdmpp-pm-card">
        <div class="wpdmpp-pm-card__header">
            <div class="wpdmpp-pm-card__icon wpdmpp-pm-card__icon--rates">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-pm-card__title"><?php esc_html_e( 'Exchange Rates', 'wpdm-premium-packages' ); ?></h3>
                <div class="wpdmpp-pm-card__subtitle"><?php esc_html_e( 'Where rates come from and how fresh they are', 'wpdm-premium-packages' ); ?></div>
            </div>
        </div>

        <div class="wpdmpp-pm-card__body">

		<div class="wpdmpp-cur-health">
			<div class="wpdmpp-cur-health__item">
				<div class="wpdmpp-cur-health__label"><?php esc_html_e( 'Reporting in', 'wpdm-premium-packages' ); ?></div>
				<div class="wpdmpp-cur-health__value"><?php echo esc_html( $baseCurrency ); ?></div>
			</div>
			<div class="wpdmpp-cur-health__item<?php echo $health['stale'] ? ' wpdmpp-cur-health__item--warn' : ''; ?>">
				<div class="wpdmpp-cur-health__label"><?php esc_html_e( 'Rates updated', 'wpdm-premium-packages' ); ?></div>
				<div class="wpdmpp-cur-health__value"><?php echo esc_html( $health['age_human'] ); ?></div>
			</div>
			<div class="wpdmpp-cur-health__item">
				<div class="wpdmpp-cur-health__label"><?php esc_html_e( 'Pairs stored', 'wpdm-premium-packages' ); ?></div>
				<div class="wpdmpp-cur-health__value"><?php echo (int) $health['pairs']; ?></div>
			</div>
			<div class="wpdmpp-cur-health__item">
				<div class="wpdmpp-cur-health__label"><?php esc_html_e( 'Source', 'wpdm-premium-packages' ); ?></div>
				<div class="wpdmpp-cur-health__value"><?php echo esc_html( $health['provider'] !== '' ? $health['provider'] : '—' ); ?></div>
			</div>
		</div>

		<?php if ( $lastError !== '' ) : ?>
			<div class="alert alert-warning">
				<strong><?php esc_html_e( 'Last refresh failed:', 'wpdm-premium-packages' ); ?></strong>
				<?php echo esc_html( $lastError ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $legacyForeign > 0 ) : ?>
			<div class="alert alert-warning">
				<?php
				printf(
					/* translators: 1: order count, 2: reporting currency */
					esc_html( _n(
						'%1$d order was taken in a currency other than %2$s before rates were recorded. It is counted at face value in reports until a rate is entered for its currency below.',
						'%1$d orders were taken in a currency other than %2$s before rates were recorded. They are counted at face value in reports until rates are entered for their currencies below.',
						$legacyForeign,
						'wpdm-premium-packages'
					) ),
					(int) $legacyForeign,
					esc_html( $baseCurrency )
				);
				?>
			</div>
		<?php endif; ?>

		<div class="row">
			<div class="col-md-6 form-group">
				<label><?php esc_html_e( 'Rate source', 'wpdm-premium-packages' ); ?></label>
				<select name="_wpdmpp_settings[rate_provider]" class="form-control">
					<?php foreach ( $providers as $id => $provider ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $activeProvider, $id ); ?>>
							<?php echo esc_html( $provider->getTitle() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3 form-group">
				<label><?php esc_html_e( 'API key', 'wpdm-premium-packages' ); ?></label>
				<input type="password" class="form-control" name="_wpdmpp_settings[rate_provider_key]"
				       value="<?php echo esc_attr( $settings['rate_provider_key'] ?? '' ); ?>"
				       autocomplete="off" />
			</div>
			<div class="col-md-3 form-group">
				<label><?php esc_html_e( 'Refresh rates every', 'wpdm-premium-packages' ); ?></label>
				<div class="input-group">
					<input type="number"
					       min="<?php echo esc_attr( \WPDMPP\Currency\ExchangeRateService::MIN_REFRESH_INTERVAL_HOURS ); ?>"
					       max="<?php echo esc_attr( \WPDMPP\Currency\ExchangeRateService::MAX_REFRESH_INTERVAL_HOURS ); ?>"
					       class="form-control" name="_wpdmpp_settings[rate_refresh_interval_hours]"
					       value="<?php echo esc_attr( $refreshEvery ); ?>" />
					<span class="input-group-addon"><?php esc_html_e( 'hours', 'wpdm-premium-packages' ); ?></span>
				</div>
				<span class="help-block">
					<?php esc_html_e( 'Most providers publish once a day, so refreshing more often than that returns the same figures. Prices are shown from the stored rate, never fetched while somebody is browsing.', 'wpdm-premium-packages' ); ?>
				</span>
			</div>
			<div class="col-md-3 form-group">
				<label><?php esc_html_e( 'Treat rates as stale after', 'wpdm-premium-packages' ); ?></label>
				<div class="input-group">
					<input type="number" min="1" class="form-control" name="_wpdmpp_settings[rate_max_age_hours]"
					       value="<?php echo esc_attr( $maxAge ); ?>" />
					<span class="input-group-addon"><?php esc_html_e( 'hours', 'wpdm-premium-packages' ); ?></span>
				</div>
			</div>
		</div>

		<p>
			<button type="button" class="btn btn-secondary" id="wpdmpp-refresh-rates">
				<?php esc_html_e( 'Refresh rates now', 'wpdm-premium-packages' ); ?>
			</button>
			<span id="wpdmpp-refresh-rates-msg" style="margin-left:10px;color:#64748b;"></span>
		</p>

		<h4><?php esc_html_e( 'Current rates', 'wpdm-premium-packages' ); ?></h4>
		<?php $latest = $rateSvc->getLatestRates(); ?>
		<?php if ( empty( $latest ) ) : ?>
			<div class="wpdmpp-cur-empty">
				<?php esc_html_e( 'No rates stored yet. Refresh from a provider to fetch them.', 'wpdm-premium-packages' ); ?>
			</div>
		<?php else : ?>
			<div style="overflow-x:auto;">
				<table class="wpdmpp-cur-rates">
					<thead>
					<tr>
						<th><?php esc_html_e( 'Pair', 'wpdm-premium-packages' ); ?></th>
						<th style="text-align:right"><?php esc_html_e( 'Rate', 'wpdm-premium-packages' ); ?></th>
						<th><?php esc_html_e( 'Source', 'wpdm-premium-packages' ); ?></th>
						<th><?php esc_html_e( 'Fetched', 'wpdm-premium-packages' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $latest as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->base_currency . ' → ' . $row->quote_currency ); ?></td>
							<td class="num"><?php echo esc_html( rtrim( rtrim( number_format( (float) $row->rate, 6, '.', '' ), '0' ), '.' ) ); ?></td>
							<td><?php echo esc_html( $row->provider !== '' ? $row->provider : '—' ); ?></td>
							<td><?php echo esc_html( human_time_diff( (int) $row->fetched_at, time() ) . ' ' . __( 'ago', 'wpdm-premium-packages' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	</div>
    </div>
</div>

<script>
	jQuery(function ($) {
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'wpdmpp_rates' ) ); ?>;

		$('#wpdmpp-refresh-rates').on('click', function () {
			var $btn = $(this), $msg = $('#wpdmpp-refresh-rates-msg');
			$btn.prop('disabled', true);
			$msg.text(<?php echo wp_json_encode( __( 'Refreshing…', 'wpdm-premium-packages' ) ); ?>);

			$.post(ajaxurl, { action: 'wpdmpp_refresh_rates', _wpnonce: nonce }, function (res) {
				$msg.text(res && res.data ? res.data.message : '');
				if (res && res.success) { setTimeout(function () { location.reload(); }, 700); }
			}).fail(function () {
				$msg.text(<?php echo wp_json_encode( __( 'The request failed.', 'wpdm-premium-packages' ) ); ?>);
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

	});
</script>
