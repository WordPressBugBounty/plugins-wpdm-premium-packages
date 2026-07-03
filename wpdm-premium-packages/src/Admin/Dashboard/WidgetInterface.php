<?php
/**
 * Dashboard Widget Interface
 *
 * Contract for dashboard widgets to implement.
 *
 * @package WPDMPP\Admin\Dashboard
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Dashboard;

defined('ABSPATH') || exit;

interface WidgetInterface
{
    /**
     * Get the widget ID (used for registration and AJAX action)
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get the widget title
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Get the callback for rendering the widget placeholder
     *
     * This renders the loading state with spinner
     *
     * @return callable
     */
    public function getPlaceholderCallback(): callable;

    /**
     * Render the full widget content (called via AJAX)
     *
     * @return void
     */
    public function render(): void;

    /**
     * Get the widget data (for API/programmatic access)
     *
     * @return array
     */
    public function getData(): array;

    /**
     * Get the AJAX action name
     *
     * @return string
     */
    public function getAjaxAction(): string;

    /**
     * Get the cache key for this widget
     *
     * @return string
     */
    public function getCacheKey(): string;

    /**
     * Get the cache duration in seconds
     *
     * @return int
     */
    public function getCacheDuration(): int;

    /**
     * Check if the current user can view this widget
     *
     * @return bool
     */
    public function userCanView(): bool;

    /**
     * Register the widget with WordPress (legacy - registers both widget and AJAX)
     *
     * @return void
     * @deprecated Use DashboardService::register() which handles AJAX separately
     */
    public function register(): void;

    /**
     * Register only the WordPress dashboard widget (without AJAX handler)
     *
     * Called by DashboardService::registerDashboardWidgets() on wp_dashboard_setup.
     * AJAX handlers are registered separately by DashboardService::registerAjaxHandlers().
     *
     * @return void
     */
    public function registerDashboardWidget(): void;

    /**
     * Handle AJAX request for widget content
     *
     * @return void
     */
    public function handleAjax(): void;

    /**
     * Clear widget cache
     *
     * @return void
     */
    public function clearCache(): void;
}
