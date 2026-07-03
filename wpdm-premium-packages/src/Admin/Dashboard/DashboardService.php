<?php
/**
 * Dashboard Service
 *
 * Orchestrates dashboard widget registration and management.
 *
 * @package WPDMPP\Admin\Dashboard
 * @since 7.0.0
 */

namespace WPDMPP\Admin\Dashboard;

use WPDMPP\Admin\Dashboard\Widgets\SalesOverviewWidget;
use WPDMPP\Admin\Dashboard\Widgets\LatestOrdersWidget;
use WPDMPP\Admin\Dashboard\Widgets\RecentSalesWidget;
use WPDMPP\Admin\Dashboard\Widgets\TopSalesWidget;

defined('ABSPATH') || exit;

class DashboardService
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Registered widgets
     *
     * @var WidgetInterface[]
     */
    private array $widgets = [];

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        // Initialize default widgets
        $this->registerDefaultWidgets();
    }

    /**
     * Register default widgets
     *
     * @return void
     */
    private function registerDefaultWidgets(): void
    {
        $this->addWidget(new SalesOverviewWidget());
        $this->addWidget(new LatestOrdersWidget());
        $this->addWidget(new RecentSalesWidget());
        $this->addWidget(new TopSalesWidget());

        // Allow extensions to add custom widgets
        do_action('wpdmpp_register_dashboard_widgets', $this);
    }

    /**
     * Add a widget
     *
     * @param WidgetInterface $widget
     * @return self
     */
    public function addWidget(WidgetInterface $widget): self
    {
        $this->widgets[$widget->getId()] = $widget;
        return $this;
    }

    /**
     * Remove a widget
     *
     * @param string $widgetId
     * @return self
     */
    public function removeWidget(string $widgetId): self
    {
        unset($this->widgets[$widgetId]);
        return $this;
    }

    /**
     * Get a widget by ID
     *
     * @param string $widgetId
     * @return WidgetInterface|null
     */
    public function getWidget(string $widgetId): ?WidgetInterface
    {
        return $this->widgets[$widgetId] ?? null;
    }

    /**
     * Get all registered widgets
     *
     * @return WidgetInterface[]
     */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    /**
     * Register all widgets with WordPress
     *
     * @return void
     */
    public function register(): void
    {
        // Register AJAX handlers immediately (needed for admin-ajax.php requests)
        $this->registerAjaxHandlers();

        // Register dashboard widgets on wp_dashboard_setup (only fires on dashboard page)
        add_action('wp_dashboard_setup', [$this, 'registerDashboardWidgets']);
    }

    /**
     * Register AJAX handlers for all widgets
     *
     * This must be called early (not on wp_dashboard_setup) so handlers
     * are available during admin-ajax.php requests.
     *
     * @return void
     */
    public function registerAjaxHandlers(): void
    {
        foreach ($this->widgets as $widget) {
            $ajaxAction = $widget->getAjaxAction();
            add_action('wp_ajax_' . $ajaxAction, [$widget, 'handleAjax']);
        }
    }

    /**
     * Register widgets with WordPress dashboard
     *
     * @return void
     */
    public function registerDashboardWidgets(): void
    {
        foreach ($this->widgets as $widget) {
            if ($widget->userCanView()) {
                $widget->registerDashboardWidget();
            }
        }
    }

    /**
     * Get widget data for API response
     *
     * @param string $widgetId
     * @return array|null
     */
    public function getWidgetData(string $widgetId): ?array
    {
        $widget = $this->getWidget($widgetId);
        if ($widget && $widget->userCanView()) {
            return $widget->getData();
        }
        return null;
    }

    /**
     * Get all widgets data for API response
     *
     * @return array
     */
    public function getAllWidgetsData(): array
    {
        $data = [];
        foreach ($this->widgets as $id => $widget) {
            if ($widget->userCanView()) {
                $data[$id] = [
                    'id' => $widget->getId(),
                    'title' => $widget->getTitle(),
                    'data' => $widget->getData(),
                ];
            }
        }
        return $data;
    }

    /**
     * Clear cache for a specific widget
     *
     * @param string $widgetId
     * @return bool
     */
    public function clearWidgetCache(string $widgetId): bool
    {
        $widget = $this->getWidget($widgetId);
        if ($widget) {
            $widget->clearCache();
            return true;
        }
        return false;
    }

    /**
     * Clear all widget caches
     *
     * @return void
     */
    public function clearAllCaches(): void
    {
        foreach ($this->widgets as $widget) {
            $widget->clearCache();
        }
    }

    /**
     * Get sales overview data (convenience method)
     *
     * @return array
     */
    public function getSalesOverview(): array
    {
        $widget = $this->getWidget('sales_overview');
        return $widget ? $widget->getData() : [];
    }

    /**
     * Get latest orders data (convenience method)
     *
     * @return array
     */
    public function getLatestOrders(): array
    {
        $widget = $this->getWidget('latest_orders');
        return $widget ? $widget->getData() : [];
    }

    /**
     * Get recent sales data (convenience method)
     *
     * @return array
     */
    public function getRecentSales(): array
    {
        $widget = $this->getWidget('recent_sales');
        return $widget ? $widget->getData() : [];
    }

    /**
     * Get top sales data (convenience method)
     *
     * @return array
     */
    public function getTopSales(): array
    {
        $widget = $this->getWidget('top_sales');
        return $widget ? $widget->getData() : [];
    }

    /**
     * Get dashboard summary for API
     *
     * @return array
     */
    public function getDashboardSummary(): array
    {
        $salesOverview = $this->getSalesOverview();
        $latestOrders = $this->getLatestOrders();

        return [
            'sales' => [
                'today' => $salesOverview['today'] ?? 0,
                'this_week' => $salesOverview['this_week'] ?? 0,
                'this_month' => $salesOverview['this_month'] ?? 0,
                'total' => $salesOverview['total'] ?? 0,
            ],
            'currency' => $salesOverview['currency'] ?? wpdmpp_currency_sign(),
            'recent_orders_count' => count($latestOrders['orders'] ?? []),
            'last_updated' => current_time('mysql'),
        ];
    }
}
