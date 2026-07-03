<?php
/**
 * Abstract Dashboard Widget
 *
 * Base class for dashboard widgets with common functionality.
 *
 * @package WPDMPP\Admin\Dashboard
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Dashboard;

use WPDM\__\Session;

defined('ABSPATH') || exit;

abstract class AbstractWidget implements WidgetInterface
{
    /**
     * Widget ID
     *
     * @var string
     */
    protected string $id;

    /**
     * Widget title
     *
     * @var string
     */
    protected string $title;

    /**
     * Cache duration in seconds (default 30 minutes)
     *
     * @var int
     */
    protected int $cacheDuration = 1800;

    /**
     * Required capability to view widget
     *
     * @var string
     */
    protected string $capability = WPDMPP_ADMIN_CAP;

    /**
     * Get the widget ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the widget title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the AJAX action name
     *
     * @return string
     */
    public function getAjaxAction(): string
    {
        return 'wpdmpp_widget_' . $this->id;
    }

    /**
     * Get the cache key
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        return 'wpdmpp_widget_' . $this->id . '_' . wp_date('Y-m-d-H');
    }

    /**
     * Get the cache duration
     *
     * @return int
     */
    public function getCacheDuration(): int
    {
        return $this->cacheDuration;
    }

    /**
     * Check if user can view widget
     *
     * @return bool
     */
    public function userCanView(): bool
    {
        return current_user_can($this->capability);
    }

    /**
     * Get cached data or compute and cache it
     *
     * @return array|null
     */
    protected function getCachedData(): ?array
    {
        $cacheKey = $this->getCacheKey();
        $cached = Session::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        return null;
    }

    /**
     * Store data in cache
     *
     * @param array $data
     * @return void
     */
    protected function setCachedData(array $data): void
    {
        Session::set($this->getCacheKey(), $data, $this->cacheDuration);
    }

    /**
     * Clear widget cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        Session::clear($this->getCacheKey());
    }

    /**
     * Get placeholder callback for lazy loading
     *
     * @return callable
     */
    public function getPlaceholderCallback(): callable
    {
        return [$this, 'renderPlaceholder'];
    }

    /**
     * Render the loading placeholder
     *
     * @return void
     */
    public function renderPlaceholder(): void
    {
        $containerId = 'wpdmpp-widget-' . esc_attr($this->id);
        $ajaxAction = $this->getAjaxAction();
        ?>
        <style>
            .wpdmpp-widget-loading {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 40px 20px;
                color: #64748b;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .wpdmpp-widget-loading-spinner {
                width: 32px;
                height: 32px;
                border: 3px solid #e2e8f0;
                border-top-color: #6366f1;
                border-radius: 50%;
                animation: wpdmpp-spin 0.8s linear infinite;
                margin-bottom: 12px;
            }
            .wpdmpp-widget-loading-text {
                font-size: 13px;
                color: #94a3b8;
            }
            @keyframes wpdmpp-spin {
                to { transform: rotate(360deg); }
            }
        </style>
        <div id="<?php echo $containerId; ?>">
            <div class="wpdmpp-widget-loading">
                <div class="wpdmpp-widget-loading-spinner"></div>
                <div class="wpdmpp-widget-loading-text"><?php esc_html_e('Loading...', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <script>
            jQuery(function($) {
                $('#<?php echo $containerId; ?>').load(ajaxurl, {
                    action: '<?php echo esc_js($ajaxAction); ?>'
                });
            });
        </script>
        <?php
    }

    /**
     * Handle AJAX request for widget content
     *
     * @return void
     */
    public function handleAjax(): void
    {
        if (!$this->userCanView()) {
            wp_die(__('Permission denied', 'wpdm-premium-packages'));
        }

        $this->render();
        wp_die();
    }

    /**
     * Register the widget with WordPress (legacy method - use registerDashboardWidget instead)
     *
     * @return void
     * @deprecated Use DashboardService::register() which handles AJAX separately
     */
    public function register(): void
    {
        // Register WordPress dashboard widget
        wp_add_dashboard_widget(
            'wpdmpp_' . $this->id,
            $this->title,
            $this->getPlaceholderCallback()
        );

        // Register AJAX handler
        add_action('wp_ajax_' . $this->getAjaxAction(), [$this, 'handleAjax']);
    }

    /**
     * Register only the WordPress dashboard widget (without AJAX handler)
     *
     * This is called by DashboardService::registerDashboardWidgets() on wp_dashboard_setup.
     * AJAX handlers are registered separately by DashboardService::registerAjaxHandlers().
     *
     * @return void
     */
    public function registerDashboardWidget(): void
    {
        wp_add_dashboard_widget(
            'wpdmpp_' . $this->id,
            $this->title,
            $this->getPlaceholderCallback()
        );
    }

    /**
     * Format currency amount
     *
     * @param float $amount
     * @return string
     */
    protected function formatCurrency(float $amount): string
    {
        return wpdmpp_currency_sign() . number_format($amount, 2, '.', ',');
    }

    /**
     * Get human-readable time difference
     *
     * @param int $timestamp
     * @return string
     */
    protected function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return __('Just now', 'wpdm-premium-packages');
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return sprintf(_n('%d min ago', '%d mins ago', $mins, 'wpdm-premium-packages'), $mins);
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'wpdm-premium-packages'), $hours);
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return sprintf(_n('%d day ago', '%d days ago', $days, 'wpdm-premium-packages'), $days);
        } else {
            return wp_date(get_option('date_format'), $timestamp);
        }
    }

    /**
     * Batch fetch posts data to avoid N+1 queries
     *
     * @param array $postIds
     * @return array Keyed by post ID with title and thumbnail
     */
    protected function batchFetchPosts(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'wpdmpro',
            'post__in' => array_unique($postIds),
            'posts_per_page' => count($postIds),
            'post_status' => 'any',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
        ]);

        $data = [];
        foreach ($posts as $post) {
            $data[$post->ID] = [
                'title' => $post->post_title,
                'thumbnail' => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
            ];
        }

        return $data;
    }

    /**
     * Get the template path for this widget
     *
     * @return string
     */
    protected function getTemplatePath(): string
    {
        // Convert underscores to hyphens (e.g., sales_overview → sales-overview)
        $templateName = str_replace('_', '-', $this->id);

        // New path in src/Admin/Dashboard/views/
        $newPath = __DIR__ . '/views/' . $templateName . '.php';
        if (file_exists($newPath)) {
            return $newPath;
        }

        // Fallback to old path for backward compatibility
        return WPDMPP_TPL_DIR . 'dashboard-widgets/' . $templateName . '.php';
    }

    /**
     * Render widget content (to be implemented by subclasses)
     *
     * @return void
     */
    abstract public function render(): void;

    /**
     * Get widget data (to be implemented by subclasses)
     *
     * @return array
     */
    abstract public function getData(): array;
}
