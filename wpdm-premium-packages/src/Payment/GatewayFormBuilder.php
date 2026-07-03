<?php
/**
 * Gateway Form Builder Trait
 *
 * Provides fluent, type-safe methods for rendering payment gateway settings forms.
 * All output is properly escaped for security.
 *
 * @package WPDMPP\Payment
 * @since 7.0.0
 */

namespace WPDMPP\Payment;

defined('ABSPATH') || exit;

/**
 * Form Builder Trait for Payment Gateways
 */
trait GatewayFormBuilder
{
    /**
     * Get the settings key prefix for this gateway
     * Uses the gateway ID (e.g., 'PayPal', 'Cash')
     *
     * @return string
     */
    abstract public function getSettingsKey(): string;

    /**
     * Start a form wrapper
     *
     * @return string HTML
     */
    protected function formStart(): string
    {
        return '<div class="wpdmpp-gateway-settings-form">';
    }

    /**
     * End form wrapper
     *
     * @return string HTML
     */
    protected function formEnd(): string
    {
        return '</div>';
    }

    /**
     * Render the common Enable/Status and Title fields for a gateway
     *
     * This renders the standard fields that appear at the top of every gateway's settings.
     *
     * @return string HTML
     */
    public function renderCommonFields(): string
    {
        return $this->enableField() . $this->titleField();
    }

    /**
     * Render the Enable/Status radio button group
     *
     * @return string HTML
     */
    protected function enableField(): string
    {
        $name = $this->fieldName('enabled');
        $id = $this->fieldId('enabled');
        $value = (int) $this->getFormOption('enabled', 0);

        // Icons for each status option
        $icons = [
            0 => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>',
            1 => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>',
            2 => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>',
        ];

        $options = [
            0 => __('Disabled', 'wpdm-premium-packages'),
            1 => __('Enabled', 'wpdm-premium-packages'),
            2 => __('Admin Only', 'wpdm-premium-packages'),
        ];

        $radioHtml = '<div class="wpdmpp-status-radio-group">';
        foreach ($options as $optValue => $optLabel) {
            $checked = checked($value, $optValue, false);
            $activeClass = ($value === $optValue) ? ' active' : '';
            $radioHtml .= sprintf(
                '<label class="wpdmpp-status-option%s">
                    <input type="radio" name="%s" value="%d" %s />
                    <span class="wpdmpp-status-label">%s %s</span>
                </label>',
                $activeClass,
                esc_attr($name),
                $optValue,
                $checked,
                $icons[$optValue],
                esc_html($optLabel)
            );
        }
        $radioHtml .= '</div>';

        return $this->fieldWrapper(
            __('Status', 'wpdm-premium-packages'),
            $radioHtml
        );
    }

    /**
     * Render the gateway Title field
     *
     * @return string HTML
     */
    protected function titleField(): string
    {
        $name = $this->fieldName('title');
        $id = $this->fieldId('title');
        // Get custom title, fallback to gateway's default title
        $defaultTitle = method_exists($this, 'getTitle') ? $this->getTitle() : '';
        $value = $this->getFormOption('title', $defaultTitle);

        return $this->fieldWrapper(
            __('Title', 'wpdm-premium-packages'),
            sprintf(
                '<input type="text" name="%s" id="%s" value="%s" class="form-control" />',
                esc_attr($name),
                esc_attr($id),
                esc_attr($value)
            ),
            __('This is the title displayed to customers during checkout.', 'wpdm-premium-packages')
        );
    }

    /**
     * Render a text input field
     *
     * @param string $key Setting key
     * @param string $label Field label
     * @param string $placeholder Placeholder text
     * @param string $description Help text
     * @return string HTML
     */
    protected function textField(
        string $key,
        string $label,
        string $placeholder = '',
        string $description = ''
    ): string {
        $name = $this->fieldName($key);
        $id = $this->fieldId($key);
        $value = $this->getOption($key);

        return $this->fieldWrapper(
            $label,
            sprintf(
                '<input type="text" name="%s" id="%s" value="%s" class="form-control" placeholder="%s" />',
                esc_attr($name),
                esc_attr($id),
                esc_attr($value),
                esc_attr($placeholder)
            ),
            $description
        );
    }

    /**
     * Render an email input field
     *
     * Uses type="text" instead of type="email" to avoid browser validation
     * issues when field is inside collapsed accordion panels.
     *
     * @param string $key Setting key
     * @param string $label Field label
     * @param string $placeholder Placeholder text
     * @param string $description Help text
     * @return string HTML
     */
    protected function emailField(
        string $key,
        string $label,
        string $placeholder = '',
        string $description = ''
    ): string {
        $name = $this->fieldName($key);
        $id = $this->fieldId($key);
        $value = $this->getOption($key);

        return $this->fieldWrapper(
            $label,
            sprintf(
                '<input type="text" name="%s" id="%s" value="%s" class="form-control" placeholder="%s" />',
                esc_attr($name),
                esc_attr($id),
                esc_attr($value),
                esc_attr($placeholder)
            ),
            $description
        );
    }

    /**
     * Render a URL input field
     *
     * @param string $key Setting key
     * @param string $label Field label
     * @param string $placeholder Placeholder text
     * @param string $description Help text
     * @return string HTML
     */
    protected function urlField(
        string $key,
        string $label,
        string $placeholder = '',
        string $description = ''
    ): string {
        $name = $this->fieldName($key);
        $id = $this->fieldId($key);
        $value = $this->getOption($key);

        return $this->fieldWrapper(
            $label,
            sprintf(
                '<input type="url" name="%s" id="%s" value="%s" class="form-control" placeholder="%s" />',
                esc_attr($name),
                esc_attr($id),
                esc_attr($value),
                esc_attr($placeholder)
            ),
            $description
        );
    }

    /**
     * Render a password input field
     *
     * @param string $key Setting key
     * @param string $label Field label
     * @param string $description Help text
     * @return string HTML
     */
    protected function passwordField(
        string $key,
        string $label,
        string $description = ''
    ): string {
        $name = $this->fieldName($key);
        $id = $this->fieldId($key);
        $value = $this->getOption($key);

        return $this->fieldWrapper(
            $label,
            sprintf(
                '<input type="password" name="%s" id="%s" value="%s" class="form-control" />',
                esc_attr($name),
                esc_attr($id),
                esc_attr($value)
            ),
            $description
        );
    }

    /**
     * Render a number input field
     *
     * @param string $key Setting key
     * @param string $label Field label
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @param string $description Help text
     * @return string HTML
     */
    protected function numberField(
        string $key,
        string $label,
        int $min = 0,
        int $max = 999999,
        string $description = ''
    ): string {
        $name = $this->fieldName($key);
        $id = $this->fieldId($key);
        $value = $this->getOption($key, 0);

        return $this->fieldWrapper(
            $label,
            sprintf(
                '<input type="number" name="%s" id="%s" value="%s" class="form-control" min="%d" max="%d" />',
                esc_attr($name),
                esc_attr($id),
                esc_attr($value),
                $min,
                $max
            ),
            $description
        );
    }

    /**
     * Render a select dropdown field
     *
     * @param string $key Setting key
     * @param string $label Field label
     * @param array $options Associative array of value => label
     * @param string $description Help text
     * @return string HTML
     */
    protected function selectField(
        string $key,
        string $label,
        array $options,
        string $description = ''
    ): string {
        $name = $this->fieldName($key);
        $id = $this->fieldId($key);
        $selected = $this->getOption($key);

        $optionsHtml = '';
        foreach ($options as $value => $optionLabel) {
            $optionsHtml .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($selected, $value, false),
                esc_html($optionLabel)
            );
        }

        return $this->fieldWrapper(
            $label,
            sprintf(
                '<select name="%s" id="%s" class="form-control">%s</select>',
                esc_attr($name),
                esc_attr($id),
                $optionsHtml
            ),
            $description
        );
    }

    /**
     * Render a textarea field
     *
     * @param string $key Setting key
     * @param string $label Field label
     * @param int $rows Number of rows
     * @param string $placeholder Placeholder text
     * @param string $description Help text
     * @return string HTML
     */
    protected function textareaField(
        string $key,
        string $label,
        int $rows = 4,
        string $placeholder = '',
        string $description = ''
    ): string {
        $name = $this->fieldName($key);
        $id = $this->fieldId($key);
        $value = $this->getOption($key);

        return $this->fieldWrapper(
            $label,
            sprintf(
                '<textarea name="%s" id="%s" class="form-control" rows="%d" placeholder="%s">%s</textarea>',
                esc_attr($name),
                esc_attr($id),
                $rows,
                esc_attr($placeholder),
                esc_textarea($value)
            ),
            $description
        );
    }

    /**
     * Render a checkbox field
     *
     * @param string $key Setting key
     * @param string $label Checkbox label
     * @param string $description Help text
     * @return string HTML
     */
    protected function checkboxField(
        string $key,
        string $label,
        string $description = ''
    ): string {
        $name = $this->fieldName($key);
        $id = $this->fieldId($key);
        $checked = $this->getOption($key, 0);

        $html = '<div class="wpdmpp-field wpdmpp-field--toggle">';
        $html .= '<label class="wpdmpp-toggle">';
        $html .= sprintf(
            '<input type="hidden" name="%s" value="0" /><input type="checkbox" name="%s" id="%s" value="1" %s />',
            esc_attr($name),
            esc_attr($name),
            esc_attr($id),
            checked($checked, 1, false)
        );
        $html .= '<span class="wpdmpp-toggle__switch"></span>';
	    $html .= '<div>';
        $html .= '<span class="wpdmpp-toggle__text">' . esc_html($label) . '</span>';
        if ($description) {
            $html .= '<div class="wpdmpp-field__help">' . esc_html($description) . '</div>';
        }
	    $html .= '</div>';
	    $html .= '</label>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a readonly text field (for display only, e.g., webhook URLs)
     *
     * @param string $label Field label
     * @param string $value Value to display
     * @param string $description Help text
     * @return string HTML
     */
    protected function readonlyField(
        string $label,
        string $value,
        string $description = ''
    ): string {
        return $this->fieldWrapper(
            $label,
            sprintf(
                '<div class="wpdmpp-readonly-wrap"><input type="text" readonly value="%s" class="form-control wpdmpp-readonly" onclick="WPDM.copyTxt(this.value)" /><span class="wpdmpp-readonly__hint"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg> %s</span></div>',
                esc_attr($value),
                esc_html__('Click to copy', 'wpdm-premium-packages')
            ),
            $description
        );
    }

    /**
     * Render a section heading
     *
     * @param string $text Heading text
     * @return string HTML
     */
    protected function heading(string $text): string
    {
        return '<div class="wpdmpp-section-heading">' . esc_html($text) . '</div>';
    }

    /**
     * Render a horizontal rule (separator)
     *
     * @return string HTML
     */
    protected function separator(): string
    {
        return '<div class="wpdmpp-separator"></div>';
    }

    /**
     * Render a notice/info block (allows HTML)
     *
     * @param string $html Content (HTML allowed)
     * @return string HTML
     */
    protected function notice(string $html): string
    {
        return '<div class="wpdmpp-notice">' . wp_kses_post($html) . '</div>';
    }

    /**
     * Render an alert box
     *
     * @param string $text Alert text
     * @param string $type Alert type (warning, danger, info, success)
     * @return string HTML
     */
    protected function alert(string $text, string $type = 'warning'): string
    {
        $type = in_array($type, ['warning', 'danger', 'info', 'success'], true) ? $type : 'warning';
        return sprintf(
            '<div class="wpdmpp-alert wpdmpp-alert--%s">%s</div>',
            esc_attr($type),
            wp_kses_post($text)
        );
    }

    /**
     * Render a panel with content
     *
     * @param string $content Panel content (HTML)
     * @return string HTML
     */
    protected function panel(string $content): string
    {
        return '<div style="padding: 20px"><div class="panel panel-default" style="margin: 0"><div class="panel-body">' . $content . '</div></div></div>';
    }

    /**
     * Render raw HTML content
     *
     * @param string $html HTML content
     * @return string HTML
     */
    protected function html(string $html): string
    {
        return $html;
    }

    /**
     * Build a field wrapper with label and description
     *
     * @param string $label Field label
     * @param string $field Field HTML
     * @param string $description Help text
     * @return string HTML
     */
    protected function fieldWrapper(string $label, string $field, string $description = ''): string
    {
        $html = '<div class="wpdmpp-field">';
        $html .= '<label class="wpdmpp-field__label">' . esc_html($label) . '</label>';
        $html .= $field;
        if ($description) {
            $html .= '<div class="wpdmpp-field__help">' . esc_html($description) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Get the full field name for form submission
     *
     * @param string $key Setting key
     * @return string Field name attribute value
     */
    protected function fieldName(string $key): string
    {
        return '_wpdmpp_settings[' . $this->getSettingsKey() . '][' . $key . ']';
    }

    /**
     * Get the field ID for HTML
     *
     * @param string $key Setting key
     * @return string Field ID attribute value
     */
    protected function fieldId(string $key): string
    {
        return strtolower($this->getSettingsKey()) . '_' . $key;
    }

    /**
     * Get an option value for this gateway
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Option value
     */
    protected function getFormOption(string $key, $default = '')
    {
        return get_wpdmpp_option($this->getSettingsKey() . '/' . $key, $default);
    }
}
