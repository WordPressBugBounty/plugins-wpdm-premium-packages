<?php

namespace WPDMPP\Libs;

class SellerDashboard {

	function __construct(){
		add_filter( 'wpdm_frontend', array( $this, 'tabs' ) );
	}

	function tabs($tabs){
		//$first['adb'] = array('label'=>'Dashboard','shortcode' => '[wpdmpp_seller_dashboard]');
		$tabs['sales'] = array('label'=>'Sales','shortcode' => '[wpdmpp_earnings]');
		$tabs['withdraws'] = array('label'=>'Withdraws','shortcode' => '[wpdmpp_withdraws]');

		return array('seller-dashboard' => array('label'=>'Dashboard','shortcode' => '[wpdmpp_seller_dashboard]'))+$tabs;
	}
}

new SellerDashboard();