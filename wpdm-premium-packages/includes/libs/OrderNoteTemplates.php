<?php

namespace WPDMPP;

use WPDM\__\__;

class OrderNoteTemplates {

	function __construct() {

		add_action('wp_ajax_wpdm_save_order_note_template', [$this, 'saveOrderNoteTemplate']);
		add_action('wp_ajax_wpdm_edit_order_note_template', [$this, 'editOrderNoteTemplate']);
		add_action('wp_ajax_wpdm_delete_order_note_template', [$this, 'deleteOrderNoteTemplate']);
		add_action('wp_ajax_wpdm_get_order_note_templates', [$this, 'orderNoteTemplates']);

	}

	function orderNoteTemplates()
	{
		__::isAuthentic('__ontgnonce', WPDM_PRI_NONCE, WPDM_ADMIN_CAP);
		wp_send_json($this->getOrderNoteTemplates());
	}

	function getOrderNoteTemplates()
	{
		$templates = get_option("__wpdmpp_order_note_templates");
		if($templates) $templates = json_decode($templates, true);
		if(!is_array($templates)) $templates = [];
		foreach ($templates as $id => &$template) {
			if(is_object($template)) {
				$template->id = $id;
				$template->content = stripslashes($template->content);
			}
		}
		return $templates;
	}

	function saveOrderNoteTemplate(){
		if(wp_verify_nonce(wpdm_query_var('__ontxnonce'), NONCE_KEY) && current_user_can(WPDM_ADMIN_CAP)){
			$templates = $this->getOrderNoteTemplates();
			if(wpdm_query_var('id') !== '') {
				foreach ( $templates as $id => $_template ) {
					if ( $_template->id === wpdm_query_var( 'id' ) ) {
						unset( $templates[ $id ] );
					}
				}
			}
			$id = strtolower(preg_replace("/([^A-Za-z0-9]+)/", "_", wpdm_query_var('ont/name', 'txt')));
			$templates[$id] = ['id' => $id, 'name' => wpdm_query_var('ont/name', 'txt'), 'content' => stripslashes(wpdm_query_var('ont/template', 'kses'))];
			update_option('__wpdmpp_order_note_templates', json_encode($templates), false);
			wp_send_json($templates);
		}
	}

	function editOrderNoteTemplate(){
		if(current_user_can(WPDM_ADMIN_CAP)){
			$templates = $this->getOrderNoteTemplates();
			wp_send_json($templates[wpdm_query_var('id', 'txt')]);
		}
	}

	function deleteOrderNoteTemplate(){
		if(current_user_can(WPDM_ADMIN_CAP)){
			$templates = $this->getOrderNoteTemplates();
			unset($templates[wpdm_query_var('id', 'txt')]);
			update_option('__wpdmpp_order_note_templates', json_encode($templates), false);
			wp_send_json($templates);
		}
	}

}

new OrderNoteTemplates();