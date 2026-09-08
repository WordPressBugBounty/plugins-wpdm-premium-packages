<?php
/**
 * Sidebar widget wrapper for the currency switcher.
 *
 * @package WPDMPP\Currency
 * @since   7.2.0
 */

namespace WPDMPP\Currency;

defined('ABSPATH') || exit;

class CurrencySwitcherWidget extends \WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'wpdmpp_currency_switcher',
            __('WPDM Currency Switcher', 'wpdm-premium-packages'),
            ['description' => __('Lets shoppers choose the currency they pay in.', 'wpdm-premium-packages')]
        );
    }

    /**
     * @param array $args
     * @param array $instance
     *
     * @return void
     */
    public function widget($args, $instance)
    {
        $switcher = CurrencySwitcher::getInstance()->render([
            'label' => $instance['label'] ?? __('Currency', 'wpdm-premium-packages'),
        ]);

        // Nothing to choose between: emit no wrapper either, so an empty widget
        // box does not appear in the sidebar.
        if ($switcher === '') {
            return;
        }

        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . esc_html($instance['title']) . $args['after_title'];
        }

        echo $switcher;
        echo $args['after_widget'];
    }

    /**
     * @param array $instance
     *
     * @return void
     */
    public function form($instance)
    {
        $title = $instance['title'] ?? '';
        $label = $instance['label'] ?? __('Currency', 'wpdm-premium-packages');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'wpdm-premium-packages'); ?>
            </label>
            <input class="widefat" type="text"
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   value="<?php echo esc_attr($title); ?>"/>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('label')); ?>">
                <?php esc_html_e('Field label:', 'wpdm-premium-packages'); ?>
            </label>
            <input class="widefat" type="text"
                   id="<?php echo esc_attr($this->get_field_id('label')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('label')); ?>"
                   value="<?php echo esc_attr($label); ?>"/>
        </p>
        <?php
    }

    /**
     * @param array $new
     * @param array $old
     *
     * @return array
     */
    public function update($new, $old)
    {
        return [
            'title' => sanitize_text_field($new['title'] ?? ''),
            'label' => sanitize_text_field($new['label'] ?? ''),
        ];
    }
}
