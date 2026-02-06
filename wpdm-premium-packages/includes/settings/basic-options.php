<?php

use WPDM\__\__;
use WPDM\__\Crypt;
use WPDM\Form\Form;

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$countries = $wpdb->get_results("select * from {$wpdb->prefix}ahm_country order by country_name");
?>

<style>
/* Basic Options Styles */
.wpdmpp-settings-section {
    margin-top: 20px;
}

.wpdmpp-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 20px;
}

.wpdmpp-card__header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.wpdmpp-card__icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.wpdmpp-card__icon svg {
    width: 20px;
    height: 20px;
}

.wpdmpp-card__icon--blue { background: #dbeafe; }
.wpdmpp-card__icon--blue svg { color: #2563eb; }
.wpdmpp-card__icon--green { background: #dcfce7; }
.wpdmpp-card__icon--green svg { color: #16a34a; }
.wpdmpp-card__icon--purple { background: #f3e8ff; }
.wpdmpp-card__icon--purple svg { color: #9333ea; }
.wpdmpp-card__icon--amber { background: #fef3c7; }
.wpdmpp-card__icon--amber svg { color: #d97706; }
.wpdmpp-card__icon--rose { background: #ffe4e6; }
.wpdmpp-card__icon--rose svg { color: #e11d48; }
.wpdmpp-card__icon--cyan { background: #cffafe; }
.wpdmpp-card__icon--cyan svg { color: #0891b2; }
.wpdmpp-card__icon--indigo { background: #e0e7ff; }
.wpdmpp-card__icon--indigo svg { color: #4f46e5; }
.wpdmpp-card__icon--slate { background: #e2e8f0; }
.wpdmpp-card__icon--slate svg { color: #475569; }

.wpdmpp-card__title {
    color: #1e293b;
    font-size: 15px;
    font-weight: 600;
    margin: 0;
}

.wpdmpp-card__subtitle {
    color: #64748b;
    font-size: 12px;
    margin-top: 2px;
}

.wpdmpp-card__body {
    padding: 20px;
}

.wpdmpp-card__footer {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 16px 20px;
}

/* Form Groups */
.wpdmpp-form-group {
    margin-bottom: 20px;
}

.wpdmpp-form-group:last-child {
    margin-bottom: 0;
}

.wpdmpp-form-group__label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.wpdmpp-form-group__note {
    font-size: 12px;
    color: #64748b;
    margin-top: 6px;
}

/* Toggle Switch */
.wpdmpp-switch {
    position: relative;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}

.wpdmpp-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.wpdmpp-switch__slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: 0.2s;
    border-radius: 24px;
}

.wpdmpp-switch__slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.2s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.wpdmpp-switch input:checked + .wpdmpp-switch__slider {
    background-color: #10b981;
}

.wpdmpp-switch input:checked + .wpdmpp-switch__slider:before {
    transform: translateX(20px);
}

/* Toggle Option Row */
.wpdmpp-toggle-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    background: #f8fafc;
    border-radius: 8px;
    margin-bottom: 10px;
}

.wpdmpp-toggle-option:last-child {
    margin-bottom: 0;
}

.wpdmpp-toggle-option__info {
    flex: 1;
    min-width: 0;
    padding-right: 16px;
}

.wpdmpp-toggle-option__label {
    font-size: 14px !important;
    font-weight: 500;
    color: #1e293b;
    margin: 0;
}

.wpdmpp-toggle-option__desc {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}

/* Country List */
.wpdmpp-country-list {
    overflow: hidden;
}

.wpdmpp-country-list__toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.wpdmpp-country-list__search {
    flex: 1;
    position: relative;
}

.wpdmpp-country-list__search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

.wpdmpp-country-list__search-icon svg {
    width: 16px;
    height: 16px;
}

.wpdmpp-country-list__search input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 13px;
    background: #ffffff;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.wpdmpp-country-list__search input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.wpdmpp-country-list__search input::placeholder {
    color: #94a3b8;
}

.wpdmpp-country-list__select-all {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
}

.wpdmpp-country-list__select-all:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.wpdmpp-country-list__select-all input {
    width: 14px;
    height: 14px;
    accent-color: #6366f1;
}

.wpdmpp-country-list__stats {
    font-size: 11px;
    color: #64748b;
    padding: 0 12px;
    white-space: nowrap;
}

.wpdmpp-country-list__items {
    max-height: 280px;
    overflow-y: auto;
    padding: 8px;
}

.wpdmpp-country-list__item {
    padding: 8px 12px;
    border-radius: 6px;
    transition: background 0.15s ease;
}

.wpdmpp-country-list__item:hover {
    background: #f1f5f9;
}

.wpdmpp-country-list__item.is-hidden {
    display: none;
}

.wpdmpp-country-list__item label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 13px;
    margin: 0;
    font-weight: 400 !important;
    transition: color 0.15s ease;
}

/* Unselected countries - muted color */
.wpdmpp-country-list__item label {
    color: #94a3b8;
}

/* Selected countries - darker color */
.wpdmpp-country-list__item.is-selected label {
    color: #1e293b;
    font-weight: 500;
}

.wpdmpp-country-list__item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #6366f1;
    flex-shrink: 0;
}

.wpdmpp-country-list__empty {
    padding: 24px;
    text-align: center;
    color: #64748b;
    font-size: 13px;
    display: none;
}

.wpdmpp-country-list__empty.is-visible {
    display: block;
}

/* License Table */
.wpdmpp-license-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.wpdmpp-license-table thead th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 1px solid #e2e8f0;
}

.wpdmpp-license-table tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
    color: #374151;
    vertical-align: middle;
}

.wpdmpp-license-table tbody tr:last-child td {
    border-bottom: none;
}

/* Delete Button */
.wpdmpp-btn-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: #fef2f2;
    border: none;
    border-radius: 6px;
    color: #dc2626;
    cursor: pointer;
    transition: all 0.15s ease;
}

.wpdmpp-btn-delete:hover {
    background: #fee2e2;
}

/* Add Button */
.wpdmpp-btn-add {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #475569;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}

.wpdmpp-btn-add:hover {
    background: #e2e8f0;
    border-color: #cbd5e1;
    color: #1e293b;
}

.wpdmpp-btn-add svg {
    width: 16px;
    height: 16px;
}

/* Button Settings Row */
.wpdmpp-btn-settings-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    .wpdmpp-btn-settings-row {
        grid-template-columns: 1fr;
    }
}

/* Button Style Selector */
.wpdmpp-btn-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.wpdmpp-btn-selector__item {
    position: relative;
}

.wpdmpp-btn-selector__item input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.wpdmpp-btn-selector__color {
    display: block;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 2px solid transparent;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.wpdmpp-btn-selector__color:hover {
    transform: scale(1.1);
}

.wpdmpp-btn-selector__color--primary { background: #3b82f6; }
.wpdmpp-btn-selector__color--secondary { background: #6b7280; }
.wpdmpp-btn-selector__color--info { background: #17a2b8; }
.wpdmpp-btn-selector__color--success { background: #22c55e; }
.wpdmpp-btn-selector__color--warning { background: #f59e0b; }
.wpdmpp-btn-selector__color--danger { background: #ef4444; }

/* Selected state */
.wpdmpp-btn-selector__item input:checked + .wpdmpp-btn-selector__color {
    border-color: #1e293b;
    box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #1e293b;
}

/* Button Preview */
.wpdmpp-btn-preview {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    padding: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    min-height: 80px;
}

.wpdmpp-btn-preview__label {
    position: absolute;
    top: -10px;
    right: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 10px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #ffffff;
    padding: 4px 10px;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    z-index: 2;
}

.wpdmpp-btn-preview__label svg {
    width: 12px;
    height: 12px;
    color: #94a3b8;
}

.wpdmpp-btn-preview__mockup {
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.wpdmpp-btn-preview__mockup .btn {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
}

/* Invoice Settings - Image Upload */
.wpdmpp-invoice-images {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 768px) {
    .wpdmpp-invoice-images {
        grid-template-columns: 1fr;
    }
}

.wpdmpp-image-upload {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
}

.wpdmpp-image-upload__label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 12px;
}

.wpdmpp-image-upload__preview {
    background: #ffffff;
    border: 2px dashed #e2e8f0;
    border-radius: 8px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 12px;
}

.wpdmpp-image-upload__preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.wpdmpp-image-upload__placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
}

.wpdmpp-image-upload__placeholder svg {
    width: 32px;
    height: 32px;
}

.wpdmpp-image-upload__placeholder span {
    font-size: 12px;
}

.wpdmpp-image-upload__actions {
    display: flex;
    gap: 8px;
}

.wpdmpp-image-upload__btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}

.wpdmpp-image-upload__btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.wpdmpp-image-upload__btn svg {
    width: 14px;
    height: 14px;
}

.wpdmpp-image-upload__btn--remove {
    flex: 0 0 36px;
    padding: 8px;
    color: #dc2626;
    background: #fef2f2;
    border-color: #fecaca;
}

.wpdmpp-image-upload__btn--remove:hover {
    background: #fee2e2;
    border-color: #fca5a5;
}

/* Invoice Section Headers */
.wpdmpp-invoice-section {
    margin-bottom: 0;
}

.wpdmpp-invoice-section__header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
}

.wpdmpp-invoice-section__header svg {
    width: 18px;
    height: 18px;
    color: #94a3b8;
}

/* Cron URL Box */
.wpdmpp-cron-box {
    background: #f8fafc;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
}

.wpdmpp-cron-box:last-child {
    margin-bottom: 0;
}

.wpdmpp-cron-box__label {
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 8px;
}

.wpdmpp-cron-box__input {
    display: flex;
    gap: 8px;
}

.wpdmpp-cron-box__input input {
    flex: 1;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    padding: 8px 12px;
    font-size: 12px;
    font-family: monospace;
    background: #ffffff;
}

.wpdmpp-cron-box__copy {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}

.wpdmpp-cron-box__copy:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.wpdmpp-cron-box__note {
    font-size: 11px;
    color: #64748b;
    margin-top: 8px;
}

/* Section Divider */
.wpdmpp-divider {
    height: 1px;
    background: #e2e8f0;
    margin: 20px 0;
}

/* Radio Group */
.wpdmpp-radio-group {
    display: flex;
    gap: 16px;
}

.wpdmpp-radio-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #374151;
    cursor: pointer;
}

.wpdmpp-radio-group input[type="radio"] {
    width: 16px;
    height: 16px;
    accent-color: #6366f1;
}
</style>

<div class="wpdmpp-settings-section">
    <input type="hidden" name="action" value="wpdmpp_save_settings">

    <!-- Base Country Card -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--blue">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('Base Country', 'wpdm-premium-packages'); ?></h3>
                <div class="wpdmpp-card__subtitle"><?php _e('Set your primary business location', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <div class="wpdmpp-card__body">
            <select class="form-control chosen" name="_wpdmpp_settings[base_country]" style="width: 100%; max-width: 400px;">
                <option value=""><?php _e('-- Select Country --', 'wpdm-premium-packages'); ?></option>
                <?php foreach ($countries as $country): ?>
                    <option value="<?php echo esc_attr($country->country_code); ?>" <?php selected(isset($settings['base_country']) ? $settings['base_country'] : '', $country->country_code); ?>>
                        <?php echo esc_html(ucwords(strtolower($country->country_name))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Allowed Countries Card -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--green">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('Allowed Countries', 'wpdm-premium-packages'); ?></h3>
                <div class="wpdmpp-card__subtitle"><?php _e('Select countries where customers can purchase', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <div class="wpdmpp-card__body" style="padding: 0;">
            <div class="wpdmpp-country-list">
                <div class="wpdmpp-country-list__toolbar">
                    <div class="wpdmpp-country-list__search">
                        <span class="wpdmpp-country-list__search-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input type="text" id="country-search" placeholder="<?php esc_attr_e('Search countries...', 'wpdm-premium-packages'); ?>">
                    </div>
                    <label class="wpdmpp-country-list__select-all">
                        <input type="checkbox" id="allowed_cn">
                        <?php _e('Select All', 'wpdm-premium-packages'); ?>
                    </label>
                    <span class="wpdmpp-country-list__stats" id="country-stats">
                        <?php
                        $selected_count = isset($settings['allow_country']) ? count($settings['allow_country']) : 0;
                        $total_count = count($countries);
                        echo sprintf(__('%d of %d selected', 'wpdm-premium-packages'), $selected_count, $total_count);
                        ?>
                    </span>
                </div>
                <div class="wpdmpp-country-list__items" id="country-list">
                    <?php foreach ($countries as $country):
                        $is_selected = false;
                        if (isset($settings['allow_country'])) {
                            $is_selected = in_array($country->country_code, $settings['allow_country']);
                        }
                        $country_name = ucwords(strtolower($country->country_name));
                    ?>
                    <div class="wpdmpp-country-list__item <?php echo $is_selected ? 'is-selected' : ''; ?>" data-name="<?php echo esc_attr(strtolower($country_name)); ?>">
                        <label>
                            <input type="checkbox" class="ccb" name="_wpdmpp_settings[allow_country][]" value="<?php echo esc_attr($country->country_code); ?>" <?php checked($is_selected); ?>>
                            <?php echo esc_html($country_name); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    <div class="wpdmpp-country-list__empty" id="country-empty">
                        <?php _e('No countries found matching your search', 'wpdm-premium-packages'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Frontend Settings Card -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--purple">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('Frontend Settings', 'wpdm-premium-packages'); ?></h3>
                <div class="wpdmpp-card__subtitle"><?php _e('Configure checkout and user-facing options', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <div class="wpdmpp-card__body">
            <!-- Toggle Options -->
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Billing Address', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Ask for billing address on checkout page', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[billing_address]" value="1" <?php checked(isset($settings['billing_address']) && $settings['billing_address'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('MasterKey Authorization', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Authorize MasterKey to download premium packages', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[authorize_masterkey]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[authorize_masterkey]" value="1" <?php checked(isset($settings['authorize_masterkey']) && $settings['authorize_masterkey'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Guest Checkout', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Allow customers to checkout without creating an account', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[guest_checkout]" value="1" <?php checked(isset($settings['guest_checkout']) && $settings['guest_checkout'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Guest Download', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Allow guests to download purchased items without login', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[guest_download]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[guest_download]" value="1" <?php checked(isset($settings['guest_download']) && $settings['guest_download'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Disable Multi-file Download', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Disable multi-file download for purchased items', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[disable_multi_file_download]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[disable_multi_file_download]" value="1" <?php checked(isset($settings['disable_multi_file_download']) && $settings['disable_multi_file_download'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-divider"></div>

            <!-- Page Settings -->
            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Cart Page', 'wpdm-premium-packages'); ?></label>
                <?php
                $args = array(
                    'show_option_none' => __('None Selected', 'wpdm-premium-packages'),
                    'name' => '_wpdmpp_settings[page_id]',
                    'class' => 'form-control',
                    'selected' => isset($settings['page_id']) ? $settings['page_id'] : ''
                );
                wp_dropdown_pages($args);
                ?>
            </div>

            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Checkout Layout', 'wpdm-premium-packages'); ?></label>
                <div class="wpdmpp-radio-group">
                    <label>
                        <input type="radio" name="_wpdmpp_settings[checkout_page_style]" value="" <?php checked(get_wpdmpp_option('checkout_page_style'), ''); ?>>
                        <?php _e('Single Column', 'wpdm-premium-packages'); ?>
                    </label>
                    <label>
                        <input type="radio" name="_wpdmpp_settings[checkout_page_style]" value="-2col" <?php checked(get_wpdmpp_option('checkout_page_style'), '-2col'); ?>>
                        <?php _e('2 Columns Extended', 'wpdm-premium-packages'); ?>
                    </label>
                </div>
            </div>

            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Orders Page', 'wpdm-premium-packages'); ?></label>
                <?php
                $args = array(
                    'show_option_none' => __('None Selected', 'wpdm-premium-packages'),
                    'name' => '_wpdmpp_settings[orders_page_id]',
                    'class' => 'form-control',
                    'selected' => isset($settings['orders_page_id']) ? $settings['orders_page_id'] : ''
                );
                wp_dropdown_pages($args);
                ?>
            </div>

            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Guest Order Page', 'wpdm-premium-packages'); ?></label>
                <?php
                $args = array(
                    'show_option_none' => __('None Selected', 'wpdm-premium-packages'),
                    'name' => '_wpdmpp_settings[guest_order_page_id]',
                    'class' => 'form-control',
                    'selected' => isset($settings['guest_order_page_id']) ? $settings['guest_order_page_id'] : ''
                );
                wp_dropdown_pages($args);
                ?>
            </div>

            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Continue Shopping URL', 'wpdm-premium-packages'); ?></label>
                <input type="text" class="form-control" name="_wpdmpp_settings[continue_shopping_url]" value="<?php echo esc_attr(isset($settings['continue_shopping_url']) ? $settings['continue_shopping_url'] : ''); ?>" placeholder="https://">
            </div>
        </div>
    </div>

    <!-- Purchase Settings Card -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--amber">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('Purchase Settings', 'wpdm-premium-packages'); ?></h3>
                <div class="wpdmpp-card__subtitle"><?php _e('Configure purchase behavior and order options', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <div class="wpdmpp-card__body">
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Disable Role-based Discount', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Disable user role based discount on purchases', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[no_role_discount]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[no_role_discount]" value="1" <?php checked(isset($settings['no_role_discount']) && $settings['no_role_discount'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Disable Product Coupon Field', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Hide product specific coupon input field', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[no_product_coupon]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[no_product_coupon]" value="1" <?php checked(isset($settings['no_product_coupon']) && $settings['no_product_coupon'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Show Buy Now Option', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Display a "Buy Now" button for instant checkout', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[show_buynow]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[show_buynow]" value="1" <?php checked(isset($settings['show_buynow']) && $settings['show_buynow'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Redirect to Cart', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Redirect to shopping cart after adding a product', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" name="_wpdmpp_settings[wpdmpp_after_addtocart_redirect]" value="1" <?php checked(isset($settings['wpdmpp_after_addtocart_redirect']) && $settings['wpdmpp_after_addtocart_redirect'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Add To Cart Fallback', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Show "Add To Cart" button as customer download link fallback', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[cdl_fallback]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[cdl_fallback]" value="1" <?php checked(isset($settings['cdl_fallback']) && $settings['cdl_fallback'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Keep License Key Valid', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Keep license key valid for expired orders', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[license_key_validity]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[license_key_validity]" value="1" <?php checked(isset($settings['license_key_validity']) && $settings['license_key_validity'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Order Expiry Alert', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Send order expiration alert to customer', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[order_expiry_alert]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[order_expiry_alert]" value="1" <?php checked(isset($settings['order_expiry_alert']) && $settings['order_expiry_alert'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Auto Renew Orders', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Automatically renew orders on expiration', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[auto_renew]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[auto_renew]" value="1" <?php checked(isset($settings['auto_renew']) && $settings['auto_renew'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Disable Manual Renewal', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc">
                        <?php _e('Disable manual renewal after', 'wpdm-premium-packages'); ?>
                        <input type="number" class="form-control" style="display: inline-block; width: 60px; padding: 2px 6px; height: 24px; font-size: 12px; margin: 0 4px;" name="_wpdmpp_settings[disable_manual_renewal_period]" value="<?php echo esc_attr(get_wpdmpp_option('disable_manual_renewal_period', 90)); ?>">
                        <?php _e('days of expiration', 'wpdm-premium-packages'); ?>
                    </p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[disable_manual_renew]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[disable_manual_renew]" value="1" <?php checked(isset($settings['disable_manual_renew']) && $settings['disable_manual_renew'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Disable Order Notes', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Hide order notes field on checkout', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[disable_order_notes]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[disable_order_notes]" value="1" <?php checked(isset($settings['disable_order_notes']) && $settings['disable_order_notes'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Audio Preview', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Allow users to play MP3 files before purchase', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[audio_preview]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[audio_preview]" value="1" <?php checked(isset($settings['audio_preview']) && $settings['audio_preview'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <div class="wpdmpp-divider"></div>

            <!-- Order Settings -->
            <div class="wpdmpp-form-group">
                <label class="wpdmpp-form-group__label"><?php _e('Order Validity Period', 'wpdm-premium-packages'); ?></label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="number" class="form-control" style="width: 120px;" name="_wpdmpp_settings[order_validity_period]" value="<?php echo esc_attr(isset($settings['order_validity_period']) ? $settings['order_validity_period'] : 365); ?>">
                    <span style="color: #64748b; font-size: 13px;"><?php _e('Days', 'wpdm-premium-packages'); ?></span>
                </div>
            </div>

            <div style="display: flex; gap: 20px;">
                <div class="wpdmpp-form-group" style="width: 60%;">
                    <label class="wpdmpp-form-group__label"><?php _e('Order Title', 'wpdm-premium-packages'); ?></label>
                    <input type="text" class="form-control" name="_wpdmpp_settings[order_title]" value="<?php echo esc_attr(isset($settings['order_title']) && $settings['order_title'] != '' ? $settings['order_title'] : get_option('blogname') . ' Order# {{ORDER_ID}}'); ?>">
                    <p class="wpdmpp-form-group__note"><?php echo sprintf(__('%s = Product Name, %s = Order ID', 'wpdm-premium-packages'), '<code>{{PRODUCT_NAME}}</code>', '<code>{{ORDER_ID}}</code>'); ?></p>
                </div>

                <div class="wpdmpp-form-group" style="width: 40%;">
                    <label class="wpdmpp-form-group__label"><?php _e('Order ID Prefix', 'wpdm-premium-packages'); ?></label>
                    <input type="text" class="form-control" name="_wpdmpp_settings[order_id_prefix]" value="<?php echo esc_attr(isset($settings['order_id_prefix']) && $settings['order_id_prefix'] != '' ? $settings['order_id_prefix'] : strtoupper(substr(str_replace(" ", "", get_option('blogname')), 0, 4))); ?>">
                </div>
            </div>

        </div>
    </div>

    <!-- Abandoned Order Recovery Card -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--rose">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('Abandoned Order Recovery', 'wpdm-premium-packages'); ?></h3>
                <div class="wpdmpp-card__subtitle"><?php _e('Recover incomplete orders with automated emails', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <div class="wpdmpp-card__body">
            <?php
            $acr_fields['acre_count'] = array(
                    //'grid_class' => 'col-md-4',
                'label' => __("Number of emails to send", "wpdm-premium-packages") . ":",
                'type' => 'number',
                'attrs' => array('name' => '_wpdmpp_settings[acre_count]', 'placeholder' => '0', 'min' => 0, 'max' => 5, 'value' => wpdm_valueof($settings, 'acre_count'), 'class' => 'form-control', 'style' => 'width: 100px'),
                'note' => __('0 = do not send email, max value 5', 'wpdm-premium-packages')
            );
            $acr_fields['acre_interval'] = array(
                    //'grid_class' => 'col-md-4',
                'label' => __("Email sending interval (days)", "wpdm-premium-packages") . ":",
                'type' => 'text',
                'attrs' => array('name' => '_wpdmpp_settings[acre_interval]', 'id' => 'acre_interval', 'placeholder' => '3', 'value' => wpdm_valueof($settings, 'acre_interval'), 'class' => 'form-control', 'style' => 'width: 200px'),
                'note' => __('Use comma for different intervals: 1,3,7 = 1st email after 1 day, 2nd after 3 days, 3rd after 7 days', 'wpdm-premium-packages')
            );
            $acr_fields['delete_incomplete_order'] = array(
                    //'grid_class' => 'col-md-4',
                'label' => __("Delete incomplete orders after (days)", "wpdm-premium-packages") . ":",
                'type' => 'number',
                'attrs' => array('name' => '_wpdmpp_settings[delete_incomplete_order]', 'placeholder' => '30', 'min' => 0, 'max' => 364, 'value' => wpdm_valueof($settings, 'delete_incomplete_order'), 'class' => 'form-control', 'style' => 'width: 100px'),
                'note' => __('Set 0 to disable auto-delete. Value should be greater than sum of email intervals.', 'wpdm-premium-packages')
            );
            //$acr_fields = [['cols' => $acr_fields]];
            $acr_fields = apply_filters("wpdmpp_settings_acre_form_fields", $acr_fields);
            $form = new Form($acr_fields, ['name' => '_wpdmpp_settings_form', 'id' => '_wpdmpp_settings_form', 'method' => 'POST', 'action' => '', 'submit_button' => [], 'noForm' => true]);
            echo $form->render();
            ?>

            <div class="wpdmpp-divider"></div>

            <div class="wpdmpp-cron-box">
                <div class="wpdmpp-cron-box__label"><?php _e('Cron URL for abandoned order collection', 'wpdm-premium-packages'); ?></div>
                <div class="wpdmpp-cron-box__input">
                    <input type="text" readonly value="<?php echo esc_attr(home_url('?acre=1&acrq_key=' . WPDM()->cronJob->cronKey())); ?>">
                    <button type="button" class="wpdmpp-cron-box__copy" onclick="WPDM.copyTxt('<?php echo esc_js(home_url('?acre=1&acrq_key=' . WPDM()->cronJob->cronKey())); ?>')" title="<?php esc_attr_e('Click to copy'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
                <p class="wpdmpp-cron-box__note"><?php _e('Setup cron from your hosting panel to execute once a day', 'wpdm-premium-packages'); ?></p>
            </div>

            <div class="wpdmpp-cron-box">
                <div class="wpdmpp-cron-box__label"><?php _e('Cron URL for order recovery email', 'wpdm-premium-packages'); ?></div>
                <div class="wpdmpp-cron-box__input">
                    <input type="text" readonly value="<?php echo esc_attr(home_url('?acre=1&acre_key=' . WPDM()->cronJob->cronKey())); ?>">
                    <button type="button" class="wpdmpp-cron-box__copy" onclick="WPDM.copyTxt('<?php echo esc_js(home_url('?acre=1&acre_key=' . WPDM()->cronJob->cronKey())); ?>')" title="<?php esc_attr_e('Click to copy'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
                <p class="wpdmpp-cron-box__note"><?php _e('Setup cron from your hosting panel to execute once a day', 'wpdm-premium-packages'); ?></p>
            </div>
        </div>
    </div>

    <!-- License Settings Card -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--cyan">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('License Settings', 'wpdm-premium-packages'); ?></h3>
                <div class="wpdmpp-card__subtitle"><?php _e('Configure license types and usage limits', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <div class="wpdmpp-card__body" style="padding: 0;">
            <table class="wpdmpp-license-table">
                <thead>
                    <tr>
                        <th style="width: 120px;"><?php _e('ID', 'wpdm-premium-packages'); ?></th>
                        <th><?php _e('License Name', 'wpdm-premium-packages'); ?></th>
                        <th><?php _e('Description', 'wpdm-premium-packages'); ?></th>
                        <th style="width: 100px;"><?php _e('Limit', 'wpdm-premium-packages'); ?></th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody id="licenses">
                    <?php
                    $pre_licenses = wpdmpp_get_licenses();
                    $pre_licenses = maybe_unserialize($pre_licenses);
                    foreach ($pre_licenses as $licid => $lic):
                    ?>
                    <tr id="tr_<?php echo esc_attr($licid); ?>">
                        <td><input type="text" class="form-control" disabled value="<?php echo esc_attr($licid); ?>"></td>
                        <td><input type="text" class="form-control" name="_wpdmpp_settings[licenses][<?php echo esc_attr($licid); ?>][name]" value="<?php echo esc_attr($lic['name']); ?>"></td>
                        <td><textarea class="form-control" rows="1" name="_wpdmpp_settings[licenses][<?php echo esc_attr($licid); ?>][description]"><?php echo esc_textarea(isset($lic['description']) ? $lic['description'] : ''); ?></textarea></td>
                        <td><input type="number" class="form-control" name="_wpdmpp_settings[licenses][<?php echo esc_attr($licid); ?>][use]" value="<?php echo esc_attr($lic['use']); ?>"></td>
                        <td><button type="button" data-rowid="#tr_<?php echo esc_attr($licid); ?>" class="wpdmpp-btn-delete del-lic"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="wpdmpp-card__footer" style="text-align: right;">
            <button type="button" id="addlicenses" class="wpdmpp-btn-add">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <?php _e('Add New License', 'wpdm-premium-packages'); ?>
            </button>
        </div>
    </div>

    <!-- Button Settings Card -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--indigo">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('Button Settings', 'wpdm-premium-packages'); ?></h3>
                <div class="wpdmpp-card__subtitle"><?php _e('Customize Add to Cart and Checkout buttons', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <div class="wpdmpp-card__body">
            <?php
            $btn_colors = ['primary', 'secondary', 'info', 'success', 'warning', 'danger'];
            $current_a2c = isset($settings['a2cbtn_color']) ? $settings['a2cbtn_color'] : 'btn-primary';
            $current_a2c_label = isset($settings['a2cbtn_label']) ? $settings['a2cbtn_label'] : 'Add To Cart';
            ?>
            <!-- Add to Cart Button -->
            <div class="wpdmpp-btn-settings-row">
                <div class="wpdmpp-form-group" style="margin-bottom: 0;">
                    <label class="wpdmpp-form-group__label"><?php _e('Add to Cart Button Label', 'wpdm-premium-packages'); ?></label>
                    <input type="text" class="form-control" id="a2c_label" name="_wpdmpp_settings[a2cbtn_label]" value="<?php echo esc_attr($current_a2c_label); ?>">
                </div>
                <div class="wpdmpp-form-group" style="margin-bottom: 0;">
                    <label class="wpdmpp-form-group__label"><?php _e('Button Color', 'wpdm-premium-packages'); ?></label>
                    <div class="wpdmpp-btn-selector">
                        <?php foreach ($btn_colors as $color): ?>
                        <div class="wpdmpp-btn-selector__item">
                            <input type="radio" name="_wpdmpp_settings[a2cbtn_color]" id="a2c_<?php echo esc_attr($color); ?>" value="btn-<?php echo esc_attr($color); ?>" <?php checked($current_a2c, 'btn-' . $color); ?> data-preview="a2c">
                            <label for="a2c_<?php echo esc_attr($color); ?>" class="wpdmpp-btn-selector__color wpdmpp-btn-selector__color--<?php echo esc_attr($color); ?>" title="<?php echo esc_attr(ucfirst($color)); ?>"></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="wpdmpp-btn-preview">
                <span class="wpdmpp-btn-preview__label">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <?php _e('Preview', 'wpdm-premium-packages'); ?>
                </span>
                <div class="wpdmpp-btn-preview__mockup">
                    <span id="a2c_preview" class="btn <?php echo esc_attr($current_a2c); ?>"><?php echo esc_html($current_a2c_label); ?></span>
                </div>
            </div>

            <div class="wpdmpp-divider"></div>

            <?php
            $current_co = __::valueof($settings, 'cobtn_color') ?: 'btn-success';
            $current_co_label = isset($settings['cobtn_label']) ? $settings['cobtn_label'] : 'Complete Payment';
            ?>
            <!-- Checkout Button -->
            <div class="wpdmpp-btn-settings-row">
                <div class="wpdmpp-form-group" style="margin-bottom: 0;">
                    <label class="wpdmpp-form-group__label"><?php _e('Checkout Button Label', 'wpdm-premium-packages'); ?></label>
                    <input type="text" class="form-control" id="co_label" name="_wpdmpp_settings[cobtn_label]" value="<?php echo esc_attr($current_co_label); ?>">
                </div>
                <div class="wpdmpp-form-group" style="margin-bottom: 0;">
                    <label class="wpdmpp-form-group__label"><?php _e('Button Color', 'wpdm-premium-packages'); ?></label>
                    <div class="wpdmpp-btn-selector">
                        <?php foreach ($btn_colors as $color): ?>
                        <div class="wpdmpp-btn-selector__item">
                            <input type="radio" name="_wpdmpp_settings[cobtn_color]" id="co_<?php echo esc_attr($color); ?>" value="btn-<?php echo esc_attr($color); ?>" <?php checked($current_co, 'btn-' . $color); ?> data-preview="co">
                            <label for="co_<?php echo esc_attr($color); ?>" class="wpdmpp-btn-selector__color wpdmpp-btn-selector__color--<?php echo esc_attr($color); ?>" title="<?php echo esc_attr(ucfirst($color)); ?>"></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="wpdmpp-btn-preview">
                <span class="wpdmpp-btn-preview__label">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <?php _e('Preview', 'wpdm-premium-packages'); ?>
                </span>
                <div class="wpdmpp-btn-preview__mockup">
                    <span id="co_preview" class="btn <?php echo esc_attr($current_co); ?>"><?php echo esc_html($current_co_label); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Card -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--slate">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('Invoice Settings', 'wpdm-premium-packages'); ?></h3>
                <div class="wpdmpp-card__subtitle"><?php _e('Customize invoice appearance and content', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <div class="wpdmpp-card__body">
            <!-- Logo & Signature Row -->
            <div class="wpdmpp-invoice-images">
                <!-- Invoice Logo -->
                <div class="wpdmpp-image-upload">
                    <label class="wpdmpp-image-upload__label"><?php _e('Invoice Logo', 'wpdm-premium-packages'); ?></label>
                    <div class="wpdmpp-image-upload__preview" id="invoice-logo-preview">
                        <?php if (!empty($settings['invoice_logo'])): ?>
                            <img src="<?php echo esc_url($settings['invoice_logo']); ?>" alt="Logo">
                        <?php else: ?>
                            <div class="wpdmpp-image-upload__placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span><?php _e('No logo uploaded', 'wpdm-premium-packages'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="wpdmpp-image-upload__actions">
                        <input type="hidden" name="_wpdmpp_settings[invoice_logo]" id="invoice-logo" value="<?php echo esc_attr($settings['invoice_logo'] ?? ''); ?>">
                        <button type="button" class="wpdmpp-image-upload__btn wpdmpp-media-upload" data-target="invoice-logo" data-preview="invoice-logo-preview">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <?php _e('Upload', 'wpdm-premium-packages'); ?>
                        </button>
                        <button type="button" class="wpdmpp-image-upload__btn wpdmpp-image-upload__btn--remove" data-target="invoice-logo" data-preview="invoice-logo-preview">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Signature Image -->
                <div class="wpdmpp-image-upload">
                    <label class="wpdmpp-image-upload__label"><?php _e('Signature Image', 'wpdm-premium-packages'); ?></label>
                    <div class="wpdmpp-image-upload__preview" id="signature-preview">
                        <?php if (!empty($settings['signature'])): ?>
                            <img src="<?php echo esc_url($settings['signature']); ?>" alt="Signature">
                        <?php else: ?>
                            <div class="wpdmpp-image-upload__placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                <span><?php _e('No signature uploaded', 'wpdm-premium-packages'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="wpdmpp-image-upload__actions">
                        <input type="hidden" name="_wpdmpp_settings[signature]" id="signature" value="<?php echo esc_attr($settings['signature'] ?? ''); ?>">
                        <button type="button" class="wpdmpp-image-upload__btn wpdmpp-media-upload" data-target="signature" data-preview="signature-preview">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <?php _e('Upload', 'wpdm-premium-packages'); ?>
                        </button>
                        <button type="button" class="wpdmpp-image-upload__btn wpdmpp-image-upload__btn--remove" data-target="signature" data-preview="signature-preview">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="wpdmpp-divider"></div>

            <!-- Company Info Section -->
            <div class="wpdmpp-invoice-section">
                <div class="wpdmpp-invoice-section__header">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span><?php _e('Company Information', 'wpdm-premium-packages'); ?></span>
                </div>
                <div class="wpdmpp-form-group">
                    <label class="wpdmpp-form-group__label"><?php _e('Company Address', 'wpdm-premium-packages'); ?></label>
                    <textarea class="form-control" rows="3" name="_wpdmpp_settings[invoice_company_address]" placeholder="<?php esc_attr_e('Enter your company name and address...', 'wpdm-premium-packages'); ?>"><?php echo esc_textarea($settings['invoice_company_address'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="wpdmpp-divider"></div>

            <!-- Footer Content Section -->
            <div class="wpdmpp-invoice-section">
                <div class="wpdmpp-invoice-section__header">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                    <span><?php _e('Footer Content', 'wpdm-premium-packages'); ?></span>
                </div>
                <div class="wpdmpp-form-group">
                    <label class="wpdmpp-form-group__label"><?php _e('Thanks Note', 'wpdm-premium-packages'); ?></label>
                    <input type="text" class="form-control" name="_wpdmpp_settings[invoice_thanks]" value="<?php echo esc_attr(get_wpdmpp_option('invoice_thanks')); ?>" placeholder="<?php esc_attr_e('Thank you for your business!', 'wpdm-premium-packages'); ?>">
                </div>

                <div class="wpdmpp-form-group">
                    <label class="wpdmpp-form-group__label"><?php _e('Terms & Conditions', 'wpdm-premium-packages'); ?></label>
                    <textarea class="form-control" rows="2" name="_wpdmpp_settings[invoice_terms_acceptance]" placeholder="<?php esc_attr_e('By accepting this invoice, you agree to our terms...', 'wpdm-premium-packages'); ?>"><?php echo esc_textarea($settings['invoice_terms_acceptance'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Miscellaneous Card -->
    <div class="wpdmpp-card">
        <div class="wpdmpp-card__header">
            <div class="wpdmpp-card__icon wpdmpp-card__icon--slate">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-card__title"><?php _e('Miscellaneous', 'wpdm-premium-packages'); ?></h3>
                <div class="wpdmpp-card__subtitle"><?php _e('Additional settings and options', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <div class="wpdmpp-card__body">
            <div class="wpdmpp-toggle-option">
                <div class="wpdmpp-toggle-option__info">
                    <h4 class="wpdmpp-toggle-option__label"><?php _e('Disable Frontend CSS', 'wpdm-premium-packages'); ?></h4>
                    <p class="wpdmpp-toggle-option__desc"><?php _e('Disable plugin CSS from loading on frontend pages', 'wpdm-premium-packages'); ?></p>
                </div>
                <label class="wpdmpp-switch">
                    <input type="hidden" name="_wpdmpp_settings[disable_fron_end_css]" value="0">
                    <input type="checkbox" name="_wpdmpp_settings[disable_fron_end_css]" value="1" <?php checked(isset($settings['disable_fron_end_css']) && $settings['disable_fron_end_css'] == 1); ?>>
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>
        </div>
    </div>

    <?php do_action("wpdmpp_basic_options"); ?>
</div>

<script>
jQuery(function ($) {
    // Button Preview - Update on label change
    $('#a2c_label').on('input', function() {
        $('#a2c_preview').text($(this).val() || 'Add To Cart');
    });
    $('#co_label').on('input', function() {
        $('#co_preview').text($(this).val() || 'Complete Payment');
    });

    // Button Preview - Update on color change
    $('input[data-preview="a2c"]').on('change', function() {
        var btnClass = $(this).val();
        $('#a2c_preview').removeClass('btn-primary btn-secondary btn-info btn-success btn-warning btn-danger').addClass(btnClass);
    });
    $('input[data-preview="co"]').on('change', function() {
        var btnClass = $(this).val();
        $('#co_preview').removeClass('btn-primary btn-secondary btn-info btn-success btn-warning btn-danger').addClass(btnClass);
    });

    // Invoice Image Upload with Preview
    $('.wpdmpp-media-upload').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var targetId = $btn.data('target');
        var previewId = $btn.data('preview');

        // Create WordPress media frame
        var mediaFrame = wp.media({
            title: 'Select Image',
            button: { text: 'Use This Image' },
            multiple: false
        });

        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var imageUrl = attachment.url;

            // Set the hidden input value
            $('#' + targetId).val(imageUrl);

            // Update the preview
            $('#' + previewId).html('<img src="' + imageUrl + '" alt="Preview">');
        });

        mediaFrame.open();
    });

    // Invoice Image Remove
    $('.wpdmpp-image-upload__btn--remove').on('click', function() {
        var targetId = $(this).data('target');
        var previewId = $(this).data('preview');

        // Clear the hidden input
        $('#' + targetId).val('');

        // Show placeholder in preview
        var placeholderIcon = targetId === 'invoice-logo'
            ? '<path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />'
            : '<path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />';
        var placeholderText = targetId === 'invoice-logo' ? 'No logo uploaded' : 'No signature uploaded';

        $('#' + previewId).html(
            '<div class="wpdmpp-image-upload__placeholder">' +
            '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">' + placeholderIcon + '</svg>' +
            '<span>' + placeholderText + '</span>' +
            '</div>'
        );
    });

    // Country List - Search Filter
    var $countrySearch = $('#country-search');
    var $countryItems = $('.wpdmpp-country-list__item[data-name]');
    var $countryEmpty = $('#country-empty');
    var $countryStats = $('#country-stats');
    var totalCountries = $countryItems.length;

    $countrySearch.on('input', function() {
        var searchTerm = $(this).val().toLowerCase().trim();
        var visibleCount = 0;

        $countryItems.each(function() {
            var $item = $(this);
            var name = $item.data('name');

            if (name && name.indexOf(searchTerm) !== -1) {
                $item.removeClass('is-hidden');
                visibleCount++;
            } else if (name) {
                $item.addClass('is-hidden');
            }
        });

        // Show/hide empty state
        if (visibleCount === 0) {
            $countryEmpty.addClass('is-visible');
        } else {
            $countryEmpty.removeClass('is-visible');
        }
    });

    // Country List - Update selected count
    function updateCountryStats() {
        var selectedCount = $('.wpdmpp-country-list__item[data-name] .ccb:checked').length;
        $countryStats.text(selectedCount + ' of ' + totalCountries + ' selected');

        // Update Select All checkbox state
        var $selectAll = $('#allowed_cn');
        if (selectedCount === 0) {
            $selectAll.prop('checked', false).prop('indeterminate', false);
        } else if (selectedCount === totalCountries) {
            $selectAll.prop('checked', true).prop('indeterminate', false);
        } else {
            $selectAll.prop('checked', false).prop('indeterminate', true);
        }
    }

    // Country List - Toggle is-selected class
    $('.wpdmpp-country-list__item .ccb').on('change', function() {
        var $item = $(this).closest('.wpdmpp-country-list__item');
        if ($(this).is(':checked')) {
            $item.addClass('is-selected');
        } else {
            $item.removeClass('is-selected');
        }
        updateCountryStats();
    });

    // Country List - Select All
    $('#allowed_cn').on('change', function() {
        var isChecked = $(this).is(':checked');
        // If search is active, only toggle visible items; otherwise toggle all
        var $targetItems = $countrySearch.val().trim() !== ''
            ? $('.wpdmpp-country-list__item[data-name]:not(.is-hidden) .ccb')
            : $('.wpdmpp-country-list__item[data-name] .ccb');

        $targetItems.prop('checked', isChecked).each(function() {
            var $item = $(this).closest('.wpdmpp-country-list__item');
            if (isChecked) {
                $item.addClass('is-selected');
            } else {
                $item.removeClass('is-selected');
            }
        });
        updateCountryStats();
    });

    // Initialize stats on page load
    updateCountryStats();
});
</script>
