<?php
/**
 * User: shahnuralam
 * Date: 2/6/18
 * Time: 1:11 PM
 */

namespace WPDMPP\Libs;

use WPDM\__\__;use WPDM\__\Session;
use WPDM\__\Template;
use WPDMPP\Product;

if (!defined('ABSPATH')) die();

class ShortCodes{

    function __construct()
    {
        add_shortcode( "wpdmpp_seller_dashboard",   array( $this, 'sellerDashboard') );
        add_shortcode( 'wpdmpp_earnings',           array( $this, 'earnings' ) );
        add_shortcode( 'wpdmpp_purchases',          array( $this, 'userPurchases' ) );
        add_shortcode( 'wpdmpp_guest_orders',       array( $this, 'guestOrders' ) );
        add_shortcode( 'wpdmpp_buynow' ,            array( $this, 'buyNowHTML' ) );
        add_shortcode( 'wpdmpp_withdraws' ,      [ new Withdraws(), 'requests' ] );
        add_shortcode( "wpdmpp_cart",       [ new Cart(), 'render' ] ); // function is in includes/libs/cart.php
        add_shortcode( "wpdm-pp-cart",      [ new Cart(), 'render' ] ); // function is in includes/libs/cart.php
        add_shortcode( "wpdmpp_pay_link",      [ $this, 'payLink' ] );

    }

    function sellerDashboard(){
        ob_start();
        wp_register_script("wpdmpp-seller-dashboard", WPDMPP_BASE_URL.'/assets/js/Chart.js');
        include WPDM()->template->locate("wpdm-pp-seller-dashboard.php", WPDMPP_TPL_DIR);
        return ob_get_clean();
    }

    /**
     * Function for earnings using shortcode
     */
    function earnings()
    {
        ob_start();
        include WPDM()->template->locate("wpdm-pp-earnings.php", WPDMPP_TPL_DIR);
        return ob_get_clean();
    }

    /**
     * [wpdmpp_purchases] shortcode - Lists all purchases/orders made by current user
     *
     * @return string
     */
    function userPurchases()
    {
        global $current_user;

        $current_user = wp_get_current_user();
        $dashboard          = true;
        $wpdmpp_settings    = get_option('_wpdmpp_settings');

        ob_start();
        ?>
        <div class="w3eden">
        <?php
        if( ! is_user_logged_in() ) {

            // Show login/registration form. This is a Download Manager core template
            echo WPDM()->user->login->form();

            // If guest order is enabled then show guest order page link
            if( Session::get( 'last_order' ) && isset($wpdmpp_settings['guest_download']) && $wpdmpp_settings['guest_download'] == 1){
                include_once Template::locate("partials/guest_order_page_link.php", WPDMPP_TPL_DIR);
            }
        }else{

            // List all orders made by the user
            $order = new Order();
            $myorders = $order->GetOrders($current_user->ID);

            include_once wpdm_tpl_path('wpdm-pp-purchases.php', WPDMPP_TPL_DIR);
        }
        echo '</div>';

        $purchase_orders_html = ob_get_clean();

        return $purchase_orders_html;
    }

    /**
     * [wpdm-pp-guest-orders] shortcode
     *
     * @return string
     */

    function guestOrders(){
        ob_start();
        global $post;

        if( get_wpdmpp_option('guest_download') != 1 )
            return 'Enable guest download from Premium Packages settings';

        if(is_object($post) && get_the_permalink() == wpdmpp_guest_order_page() && !Session::get('guest_order_init'))
            Session::set('guest_order_init', uniqid());

        include  wpdm_tpl_path('wpdm-pp-guest-orders.php', WPDMPP_TPL_DIR);
        return ob_get_clean();
    }

    function buyNowHTML($params = array()){
        ob_start();
        if((int)__::valueof($params, 'id') <= 0) {
            _e('Product ID is missing!', 'wpdm-premium-packages');
            return ob_get_clean();
        }
        $product_id = (int)__::valueof($params, 'id');
        $license = __::valueof($params, 'license',[], 'safetxt');
        $product = new Product($product_id);
        $price = $product->getLicensePrice($license);
        $params = array('title' => __('Buy Now', WPDMPP_TEXT_DOMAIN));
        echo "<div class='__wpdmpp_buy_now_zone_{$product_id}'>";
        include  wpdm_tpl_path('add-to-cart/buy-now.php', WPDMPP_TPL_DIR, WPDMPP_TPL_FALLBACK);
        echo "</div>";
        return ob_get_clean();
    }

    function payLink($params = [])
    {
        if(!(double)__::valueof($params, 'price')) return '';
        ob_start();
        $args = [ 'addtocart' =>  'dynamic', 'price' => (double)__::valueof($params, 'price')];
        $args['recurring'] = __::valueof($params, 'recurring', 0, 'int');
        ?>
        <span class="w3eden wpdmpp-pay-link"><a href="<?= add_query_arg($args, home_url('/')); ?>" class="<?= __::valueof($params, 'cssclass', 'wpdm-pay-now-link', 'username'); ?>"><?= __::valueof($params, 'label', __('Pay Now', 'download-manager'), 'safetxt') ?></a></span>
        <?php
        return ob_get_clean();
    }



}


