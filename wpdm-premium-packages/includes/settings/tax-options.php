<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$us_states = array(
    'AL' => "Alabama",
    'AK' => "Alaska",
    'AZ' => "Arizona",
    'AR' => "Arkansas",
    'CA' => "California",
    'CO' => "Colorado",
    'CT' => "Connecticut",
    'DE' => "Delaware",
    'DC' => "District Of Columbia",
    'FL' => "Florida",
    'GA' => "Georgia",
    'HI' => "Hawaii",
    'ID' => "Idaho",
    'IL' => "Illinois",
    'IN' => "Indiana",
    'IA' => "Iowa",
    'KS' => "Kansas",
    'KY' => "Kentucky",
    'LA' => "Louisiana",
    'ME' => "Maine",
    'MD' => "Maryland",
    'MA' => "Massachusetts",
    'MI' => "Michigan",
    'MN' => "Minnesota",
    'MS' => "Mississippi",
    'MO' => "Missouri",
    'MT' => "Montana",
    'NE' => "Nebraska",
    'NV' => "Nevada",
    'NH' => "New Hampshire",
    'NJ' => "New Jersey",
    'NM' => "New Mexico",
    'NY' => "New York",
    'NC' => "North Carolina",
    'ND' => "North Dakota",
    'OH' => "Ohio",
    'OK' => "Oklahoma",
    'OR' => "Oregon",
    'PA' => "Pennsylvania",
    'RI' => "Rhode Island",
    'SC' => "South Carolina",
    'SD' => "South Dakota",
    'TN' => "Tennessee",
    'TX' => "Texas",
    'UT' => "Utah",
    'VT' => "Vermont",
    'VA' => "Virginia",
    'WA' => "Washington",
    'WV' => "West Virginia",
    'WI' => "Wisconsin",
    'WY' => "Wyoming");

$ca_states = array(
    "BC" => "British Columbia",
    "ON" => "Ontario",
    "NL" => "Newfoundland and Labrador",
    "NS" => "Nova Scotia",
    "PE" => "Prince Edward Island",
    "NB" => "New Brunswick",
    "QC" => "Quebec",
    "MB" => "Manitoba",
    "SK" => "Saskatchewan",
    "AB" => "Alberta",
    "NT" => "Northwest Territories",
    "NU" => "Nunavut",
    "YT" => "Yukon Territory");

$dataurl = WPDMPP_BASE_DIR.'assets/js/data/countries.json';
$data = file_get_contents($dataurl);
$data = json_decode($data);
$taxcountries = [];
foreach($data as $index => $taxcountry){
    $taxcountries[$taxcountry->code] = $taxcountry;
}

$tax_enabled = isset($settings['tax']['enable']) && $settings['tax']['enable'] == 1;
$tax_rates = isset($settings['tax']['tax_rate']) ? $settings['tax']['tax_rate'] : [];
?>

<style>
/* Tax Options Card */
.wpdmpp-tax-section {
    margin-top: 20px;
}

.wpdmpp-tax-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.wpdmpp-tax-card__header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.wpdmpp-tax-card__icon {
    width: 40px;
    height: 40px;
    background: #fef3c7;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wpdmpp-tax-card__icon svg {
    width: 20px;
    height: 20px;
    color: #d97706;
}

.wpdmpp-tax-card__title {
    color: #1e293b;
    font-size: 15px;
    font-weight: 600;
    margin: 0;
}

.wpdmpp-tax-card__subtitle {
    color: #64748b;
    font-size: 12px;
    margin-top: 2px;
}

.wpdmpp-tax-card__body {
    padding: 20px;
}

/* Tax Enable Toggle */
.wpdmpp-tax-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: #f8fafc;
    border-radius: 10px;
    margin-bottom: 24px;
}

.wpdmpp-tax-toggle__info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.wpdmpp-tax-toggle__icon {
    width: 36px;
    height: 36px;
    background: #e0f2fe;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wpdmpp-tax-toggle__icon svg {
    width: 18px;
    height: 18px;
    color: #0284c7;
}

.wpdmpp-tax-toggle__label {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.wpdmpp-tax-toggle__desc {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}

/* Custom Toggle Switch */
.wpdmpp-switch {
    position: relative;
    width: 48px;
    height: 26px;
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
    border-radius: 26px;
}

.wpdmpp-switch__slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
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
    transform: translateX(22px);
}

/* Tax Rates Section */
.wpdmpp-tax-rates {
    margin-top: 0;
}

.wpdmpp-tax-rates__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.wpdmpp-tax-rates__title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.wpdmpp-tax-rates__count {
    background: #e2e8f0;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
}

.wpdmpp-tax-rates__add {
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

.wpdmpp-tax-rates__add:hover {
    background: #e2e8f0;
    border-color: #cbd5e1;
    color: #1e293b;
}

.wpdmpp-tax-rates__add svg {
    width: 16px;
    height: 16px;
}

/* Tax Rates Table */
.wpdmpp-tax-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.wpdmpp-tax-table thead th {
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

.wpdmpp-tax-table thead th:last-child {
    text-align: center;
    width: 80px;
}

.wpdmpp-tax-table tbody tr {
    transition: background 0.15s ease;
}

.wpdmpp-tax-table tbody tr:hover {
    background: #f8fafc;
}

.wpdmpp-tax-table tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
    color: #374151;
    vertical-align: middle;
}

.wpdmpp-tax-table tbody tr:last-child td {
    border-bottom: none;
}

.wpdmpp-tax-table tbody td:last-child {
    text-align: center;
}

.wpdmpp-tax-table .taxcountry,
.wpdmpp-tax-table .taxstate {
    min-width: 180px;
}

.wpdmpp-tax-table input[type="text"][size="4"] {
    width: 80px;
    text-align: center;
}

/* Delete Button */
.wpdmpp-tax-delete {
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

.wpdmpp-tax-delete:hover {
    background: #fee2e2;
}

.wpdmpp-tax-delete svg {
    width: 16px;
    height: 16px;
}

/* Empty State */
.wpdmpp-tax-empty {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

.wpdmpp-tax-empty svg {
    width: 48px;
    height: 48px;
    color: #cbd5e1;
    margin-bottom: 12px;
}

.wpdmpp-tax-empty p {
    margin: 0 0 16px 0;
    font-size: 14px;
}

/* Fix Select2 width */
.wpdmpp-tax-table .select2-container {
    min-width: 180px !important;
}

#intr_rate .chosen-disabled {
    display: none;
}
</style>

<div class="wpdmpp-tax-section">
    <div class="wpdmpp-tax-card">
        <div class="wpdmpp-tax-card__header">
            <div class="wpdmpp-tax-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                </svg>
            </div>
            <div>
                <h3 class="wpdmpp-tax-card__title"><?php _e("Tax Configuration", "wpdm-premium-packages"); ?></h3>
                <div class="wpdmpp-tax-card__subtitle"><?php _e("Configure tax rates by country and state", "wpdm-premium-packages"); ?></div>
            </div>
        </div>

        <div class="wpdmpp-tax-card__body">
            <!-- Tax Enable Toggle -->
            <div class="wpdmpp-tax-toggle">
                <div class="wpdmpp-tax-toggle__info">
                    <div class="wpdmpp-tax-toggle__icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="wpdmpp-tax-toggle__label"><?php _e("Tax Calculation", "wpdm-premium-packages"); ?></h4>
                        <p class="wpdmpp-tax-toggle__desc"><?php _e("Enable automatic tax calculation on orders", "wpdm-premium-packages"); ?></p>
                    </div>
                </div>
                <label class="wpdmpp-switch">
                    <input type="checkbox" value="1" <?php checked($tax_enabled); ?> id="tax_calculation" name="_wpdmpp_settings[tax][enable]">
                    <span class="wpdmpp-switch__slider"></span>
                </label>
            </div>

            <!-- Tax Rates Section -->
            <div class="wpdmpp-tax-rates">
                <div class="wpdmpp-tax-rates__header">
                    <div class="wpdmpp-tax-rates__title">
                        <?php _e("Tax Rates", "wpdm-premium-packages"); ?>
                        <span class="wpdmpp-tax-rates__count"><?php echo count($tax_rates); ?></span>
                    </div>
                    <button type="button" class="wpdmpp-tax-rates__add" id="add_tax_rate">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <?php _e('Add Tax Rate', 'wpdm-premium-packages'); ?>
                    </button>
                </div>

                <?php if (!empty($tax_rates)): ?>
                <table class="wpdmpp-tax-table">
                    <thead>
                        <tr>
                            <th><?php _e("Country", "wpdm-premium-packages"); ?></th>
                            <th><?php _e("State / Region", "wpdm-premium-packages"); ?></th>
                            <th><?php _e("Rate (%)", "wpdm-premium-packages"); ?></th>
                            <th><?php _e("Action", "wpdm-premium-packages"); ?></th>
                        </tr>
                    </thead>
                    <tbody id="intr_rate">
                    <?php foreach ($tax_rates as $key => $rate):
                        $country_code = $rate['country'];
                        $has_states = isset($taxcountries[$country_code]->filename);
                        $states = [];

                        if ($has_states) {
                            $states_url = WPDMPP_BASE_DIR.'assets/js/data/countries/'.$taxcountries[$country_code]->filename.'.json';
                            $states_data = json_decode(file_get_contents($states_url));
                            foreach ($states_data as $state) {
                                $state->code = str_replace($country_code.'-', '', $state->code);
                                $states[$state->code] = $state->name;
                            }
                        }
                    ?>
                        <tr id="r_<?php echo esc_attr($key); ?>">
                            <td>
                                <select class="taxcountry form-control" rel="<?php echo esc_attr($key); ?>" name="_wpdmpp_settings[tax][tax_rate][<?php echo esc_attr($key); ?>][country]">
                                    <?php foreach ($taxcountries as $country): ?>
                                        <option <?php selected($rate['country'], $country->code); ?> value="<?php echo esc_attr($country->code); ?>"><?php echo esc_html(ucwords($country->name)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td id="cahngestates_<?php echo esc_attr($key); ?>">
                                <select class="taxstate form-control" name="_wpdmpp_settings[tax][tax_rate][<?php echo esc_attr($key); ?>][state]" <?php echo $has_states ? '' : 'disabled="disabled" style="display:none"'; ?>>
                                    <option <?php selected($rate['state'], 'ALL-STATES'); ?> value="ALL-STATES"><?php _e('All States', 'wpdm-premium-packages'); ?></option>
                                    <?php foreach ($states as $s_code => $s_name): ?>
                                        <option <?php selected($rate['state'], $s_code); ?> value="<?php echo esc_attr($s_code); ?>"><?php echo esc_html(ucwords($s_name)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input style="width:180px;<?php echo $has_states ? 'display:none;' : ''; ?>" <?php echo $has_states ? 'disabled' : ''; ?> class="form-control taxstate-text" type="text" name="_wpdmpp_settings[tax][tax_rate][<?php echo esc_attr($key); ?>][state]" value="<?php echo $has_states ? '' : esc_attr($rate['state']); ?>" />
                            </td>
                            <td>
                                <input class="form-control" type="text" size="4" name="_wpdmpp_settings[tax][tax_rate][<?php echo esc_attr($key); ?>][rate]" value="<?php echo esc_attr($rate['rate']); ?>">
                            </td>
                            <td>
                                <button type="button" class="wpdmpp-tax-delete del_rate" rel="<?php echo esc_attr($key); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="wpdmpp-tax-empty" id="tax-empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </svg>
                    <p><?php _e("No tax rates configured yet", "wpdm-premium-packages"); ?></p>
                </div>
                <table class="wpdmpp-tax-table" style="display: none;">
                    <thead>
                        <tr>
                            <th><?php _e("Country", "wpdm-premium-packages"); ?></th>
                            <th><?php _e("State / Region", "wpdm-premium-packages"); ?></th>
                            <th><?php _e("Rate (%)", "wpdm-premium-packages"); ?></th>
                            <th><?php _e("Action", "wpdm-premium-packages"); ?></th>
                        </tr>
                    </thead>
                    <tbody id="intr_rate"></tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
jQuery(function($) {
    // Delete tax rate
    $('body').on('click', '.del_rate', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $row = $('#r_' + $btn.attr('rel'));

        if (confirm("<?php _e('Are you sure to delete?', 'wpdm-premium-packages'); ?>")) {
            $row.fadeOut(200, function() {
                $(this).remove();
                updateTaxCount();
            });
        }
        return false;
    });

    // Add new tax rate
    $('#add_tax_rate').on('click', function() {
        var tmy = new Date().getTime();

        // Show table if empty state is visible
        $('#tax-empty-state').hide();
        $('.wpdmpp-tax-table').show();

        var newRow = '<tr id="r_' + tmy + '">' +
            '<td>' +
                '<select class="form-control taxcountry" rel="' + tmy + '" name="_wpdmpp_settings[tax][tax_rate][' + tmy + '][country]"></select>' +
            '</td>' +
            '<td id="states_' + tmy + '">' +
                '<select class="form-control taxstate" name="_wpdmpp_settings[tax][tax_rate][' + tmy + '][state]"></select>' +
                '<input style="display:none; width:180px;" class="form-control taxstate-text" type="text" name="_wpdmpp_settings[tax][tax_rate][' + tmy + '][state]" value="">' +
            '</td>' +
            '<td>' +
                '<input type="text" size="4" class="form-control" name="_wpdmpp_settings[tax][tax_rate][' + tmy + '][rate]" value="" placeholder="0">' +
            '</td>' +
            '<td>' +
                '<button type="button" class="wpdmpp-tax-delete del_rate" rel="' + tmy + '">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />' +
                    '</svg>' +
                '</button>' +
            '</td>' +
        '</tr>';

        $('#intr_rate').append(newRow);
        populateCountryStateAdmin(tmy);
        updateTaxCount();
    });

    // Update tax count badge
    function updateTaxCount() {
        var count = $('#intr_rate tr').length;
        $('.wpdmpp-tax-rates__count').text(count);
    }
});
</script>

    