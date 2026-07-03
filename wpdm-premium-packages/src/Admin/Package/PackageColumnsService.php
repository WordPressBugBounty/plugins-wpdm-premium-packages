<?php
/**
 * Package Columns Service
 *
 * Adds custom sales columns to the package list table in admin.
 *
 * @package WPDMPP\Admin\Package
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Package;

defined('ABSPATH') || exit;

class PackageColumnsService
{
    /**
     * Singleton instance
     *
     * @var PackageColumnsService|null
     */
    private static ?PackageColumnsService $instance = null;

    /**
     * Whether the service has been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Get singleton instance
     *
     * @return PackageColumnsService
     */
    public static function getInstance(): PackageColumnsService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {}

    /**
     * Register hooks
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        add_filter('manage_posts_columns', [$this, 'addColumnHeaders']);
        add_filter('manage_posts_custom_column', [$this, 'renderColumnContent'], 10, 2);
        add_filter('request', [$this, 'handleOrderBy']);
        add_filter('manage_edit-wpdmpro_sortable_columns', [$this, 'addSortableColumns']);
    }

    /**
     * Add custom column headers
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function addColumnHeaders(array $columns): array
    {
        if (get_post_type() !== 'wpdmpro' || !current_user_can(WPDM_ADMIN_CAP)) {
            return $columns;
        }

        $newColumns = [
            'wpdmuprice' => __('Price', 'wpdm-premium-packages'),
            'wpdmsaleqty' => sprintf(
                '<span title="%s" class="ttip">%s</span>',
                esc_attr__('Sales Quantity', 'wpdm-premium-packages'),
                esc_html__('Quantity', 'wpdm-premium-packages')
            ),
            'wpdmsaleamt' => sprintf(
                '<span title="%s" class="wpdm-th-icon ttip">' . \WPDMPP\UI\Icons::get('dollar-sign', 16) . '</span>',
                esc_attr__('Total Sales', 'wpdm-premium-packages')
            ),
        ];

        // Insert after position 4
        return $this->arraySpliceAssoc($columns, 4, 0, $newColumns);
    }

    /**
     * Render column content
     *
     * @param string $columnName Column identifier
     * @param int    $postId     Post ID
     */
    public function renderColumnContent(string $columnName, int $postId): void
    {
        if (get_post_type() !== 'wpdmpro' || !current_user_can(WPDM_ADMIN_CAP)) {
            return;
        }

        switch ($columnName) {
            case 'wpdmsaleqty':
                $count = (int) get_post_meta($postId, '__wpdm_sales_count', true);
                printf('<span id="sc-%d">%d</span>', $postId, $count);
                break;

            case 'wpdmsaleamt':
                $amount = (float) get_post_meta($postId, '__wpdm_sales_amount', true);
                printf(
                    '<a title="%s" class="ttip recal-sa" href="#" rel="%d">%s</a>',
                    esc_attr__('Total Sales, Click to Recalculate', 'wpdm-premium-packages'),
                    $postId,
                    esc_html(wpdmpp_price_format($amount, true, true))
                );
                break;

            case 'wpdmuprice':
                if (function_exists('wpdmpp_price_range')) {
                    echo wpdmpp_price_range($postId);
                }
                break;
        }
    }

    /**
     * Add sortable columns
     *
     * @param array $columns Existing sortable columns
     * @return array Modified sortable columns
     */
    public function addSortableColumns(array $columns): array
    {
        if (get_post_type() !== 'wpdmpro') {
            return $columns;
        }

        $columns['download_count'] = 'download_count';
        return $columns;
    }

    /**
     * Handle orderby for custom columns
     *
     * @param array $vars Query vars
     * @return array Modified query vars
     */
    public function handleOrderBy(array $vars): array
    {
        if (isset($vars['orderby']) && $vars['orderby'] === 'download_count') {
            $vars = array_merge($vars, [
                'meta_key' => '__wpdm_download_count',
                'orderby' => 'meta_value_num',
            ]);
        }

        return $vars;
    }

    /**
     * Array splice associative
     *
     * @param array $array       Source array
     * @param int   $position    Position to insert at
     * @param int   $length      Length to remove
     * @param array $replacement Values to insert
     * @return array Modified array
     */
    private function arraySpliceAssoc(array $array, int $position, int $length, array $replacement): array
    {
        $keys = array_keys($array);
        $values = array_values($array);

        $spliceKeys = array_keys($replacement);
        $spliceValues = array_values($replacement);

        array_splice($keys, $position, $length, $spliceKeys);
        array_splice($values, $position, $length, $spliceValues);

        return array_combine($keys, $values);
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
