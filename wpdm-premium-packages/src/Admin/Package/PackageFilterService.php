<?php
/**
 * Package Filter Service
 *
 * Adds a Premium/Free filter to the package list table in admin.
 *
 * @package WPDMPP\Admin\Package
 * @since 7.0.8
 */

namespace WPDMPP\Admin\Package;

defined('ABSPATH') || exit;

class PackageFilterService
{
    /**
     * Query var carrying the selected filter
     */
    public const QUERY_VAR = 'wpdmpp_price';

    /**
     * Filter values
     */
    public const SHOW_PREMIUM = 'premium';
    public const SHOW_FREE = 'free';

    /**
     * Singleton instance
     *
     * @var PackageFilterService|null
     */
    private static ?PackageFilterService $instance = null;

    /**
     * Whether the service has been registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Get singleton instance
     *
     * @return PackageFilterService
     */
    public static function getInstance(): PackageFilterService
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

        add_action('restrict_manage_posts', [$this, 'renderDropdown']);
        add_action('pre_get_posts', [$this, 'applyFilter']);
    }

    /**
     * Render the Premium/Free dropdown next to the existing filters
     */
    public function renderDropdown(): void
    {
        global $typenow;

        if ($typenow !== 'wpdmpro' || !current_user_can(WPDM_ADMIN_CAP)) {
            return;
        }

        $current = $this->currentFilter();

        $options = [
            '' => __('All packages', 'wpdm-premium-packages'),
            self::SHOW_PREMIUM => __('Premium (price > 0)', 'wpdm-premium-packages'),
            self::SHOW_FREE => __('Free (no price)', 'wpdm-premium-packages'),
        ];

        echo '<select name="' . esc_attr(self::QUERY_VAR) . '" id="' . esc_attr(self::QUERY_VAR) . '" class="postform">';
        foreach ($options as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($current, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    /**
     * Restrict the list query to premium or free packages
     *
     * @param \WP_Query $query
     */
    public function applyFilter($query): void
    {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'edit.php' || !$query instanceof \WP_Query || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'wpdmpro') {
            return;
        }

        $filter = $this->currentFilter();
        if ($filter === '') {
            return;
        }

        $clauses = $filter === self::SHOW_PREMIUM ? $this->premiumClauses() : $this->freeClauses();

        // Merge rather than overwrite — other add-ons may already have filtered.
        $existing = $query->get('meta_query');
        if (!empty($existing) && is_array($existing)) {
            $clauses = [
                'relation' => 'AND',
                $existing,
                $clauses,
            ];
        }

        $query->set('meta_query', $clauses);
    }

    /**
     * Meta clauses matching packages that cost something
     *
     * Mirrors wpdmpp_effective_price(): a package is paid when either the base
     * price or an active sale price is above zero. Per-license pricing is not
     * considered, because the front end does not treat it as a price either —
     * a package with no base price shows a Download button, not Add to Cart.
     *
     * @return array
     */
    private function premiumClauses(): array
    {
        return [
            'relation' => 'OR',
            [
                'key' => '__wpdm_base_price',
                'value' => 0,
                'compare' => '>',
                'type' => 'DECIMAL(10,2)',
            ],
            [
                'key' => '__wpdm_sales_price',
                'value' => 0,
                'compare' => '>',
                'type' => 'DECIMAL(10,2)',
            ],
        ];
    }

    /**
     * Meta clauses matching packages that cost nothing
     *
     * The meta may be absent entirely (never priced), an empty string, or a
     * zero value, so each key needs a NOT EXISTS arm alongside the comparison.
     *
     * @return array
     */
    private function freeClauses(): array
    {
        return [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key' => '__wpdm_base_price',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '__wpdm_base_price',
                    'value' => 0,
                    'compare' => '<=',
                    'type' => 'DECIMAL(10,2)',
                ],
            ],
            [
                'relation' => 'OR',
                [
                    'key' => '__wpdm_sales_price',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '__wpdm_sales_price',
                    'value' => 0,
                    'compare' => '<=',
                    'type' => 'DECIMAL(10,2)',
                ],
            ],
        ];
    }

    /**
     * Currently selected filter value
     *
     * @return string '', 'premium' or 'free'
     */
    private function currentFilter(): string
    {
        $value = isset($_GET[self::QUERY_VAR]) ? sanitize_key(wp_unslash($_GET[self::QUERY_VAR])) : '';

        return in_array($value, [self::SHOW_PREMIUM, self::SHOW_FREE], true) ? $value : '';
    }
}
