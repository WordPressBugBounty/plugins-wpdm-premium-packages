<?php
/**
 * User: shahnuralam
 * Date: 5/6/17
 * Time: 7:58 PM
 */
namespace WPDMPP\Libs;

use WPDM\__\Session;


if (!defined('ABSPATH')) {
    exit;
}

class DashboardWidgets
{

    function __construct(){
        add_action('wp_dashboard_setup', array($this, 'addDashboardWidget'));
        add_action('wp_ajax_loadSalesOverview', array($this, 'loadSalesOverview'));
        add_action('wp_ajax_loadLatestOrders', array($this, 'loadLatestOrders'));
        add_action('wp_ajax_loadRecentSales', array($this, 'loadRecentSales'));
        add_action('wp_ajax_loadTopSales', array($this, 'loadTopSales'));
    }

    /**
     * Render loading placeholder with modern spinner
     */
    function renderPlaceholder($containerId, $action) {
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
        <div id="<?php echo esc_attr($containerId); ?>">
            <div class="wpdmpp-widget-loading">
                <div class="wpdmpp-widget-loading-spinner"></div>
                <div class="wpdmpp-widget-loading-text"><?php _e('Loading...', 'wpdm-premium-packages'); ?></div>
            </div>
        </div>
        <script>
            jQuery(function ($) {
                $('#<?php echo esc_js($containerId); ?>').load(ajaxurl, {action: '<?php echo esc_js($action); ?>'});
            });
        </script>
        <?php
    }

    function salesOverview() {
        $this->renderPlaceholder('wpdmpp-sales-overview', 'loadSalesOverview');
    }

    function loadSalesOverview() {
        $data = Session::get( 'sales_overview_html' );
        if($data){
            echo $data;
            die();
        }

        ob_start();
        include WPDMPP_TPL_DIR . 'dashboard-widgets/sales-overview.php';
        $data = ob_get_clean();
        Session::set( 'sales_overview_html' , $data );
        echo $data;
        die();
    }

    function latestOrders() {
        $this->renderPlaceholder('wpdmpp-latestOrders', 'loadLatestOrders');
    }

    function loadLatestOrders() {
        $data = Session::get( 'latest_orders_html' );
        if($data){
            echo $data;
            die();
        }
        ob_start();
        include WPDMPP_TPL_DIR . 'dashboard-widgets/latest-orders.php';
        $data = ob_get_clean();
        Session::set( 'latest_orders_html' , $data );
        echo $data;
        die();
    }

    function recentSales() {
        $this->renderPlaceholder('wpdmpp-recentSales', 'loadRecentSales');
    }

    function loadRecentSales() {
        $data = Session::get( 'recent_sales_html' );
        if($data){
            echo $data;
            die();
        }
        ob_start();
        include WPDMPP_TPL_DIR . 'dashboard-widgets/recent-sales.php';
        $data = ob_get_clean();
        Session::set( 'recent_sales_html' , $data );
        echo $data;
        die();
    }

    function topSales() {
        $this->renderPlaceholder('wpdmpp-topSales', 'loadTopSales');
    }

    function loadTopSales() {
        /*$data = Session::get('top_sales_html');
        if($data){
            echo $data;
            die();
        }*/
        ob_start();
        include WPDMPP_TPL_DIR . 'dashboard-widgets/top-sales.php';
        $data = ob_get_clean();
        Session::set( 'top_sales_html' , $data );
        echo $data;
        die();
    }


    function addDashboardWidget() {
        if(current_user_can(WPDM_ADMIN_CAP)) {
            wp_add_dashboard_widget('wpdmpp_sales_overview', __('Sales Overview', 'wpdm-premium-packages'), array($this, 'salesOverview'));
            wp_add_dashboard_widget('wpdmpp_lastest_orders', __('Latest Orders', 'wpdm-premium-packages'), array($this, 'latestOrders'));
            wp_add_dashboard_widget('wpdmpp_lastest_sales', __('Recently Sold Items', 'wpdm-premium-packages'), array($this, 'recentSales'));
            wp_add_dashboard_widget('wpdmpp_top_sales', __('Top Selling Items ( Last 90 Days )', 'wpdm-premium-packages'), array($this, 'topSales'));
        }
    }

}

new DashboardWidgets();
