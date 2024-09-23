<?php

namespace WPDMPP\Libs\PayoutMethods;


class PayPal {
	function __construct()
	{

	}


	/**
	 * @param $request
	 * @param $account
	 *
	 * @return string
	 */
	function payoutLink($request, $account)
	{

		$payform = '
				<form action="https://www.paypal.com/cgi-bin/webscr" method="post" id="payPalForm" name="payPalForm" >
				    <input type="hidden" name="item_number" value="PAYOUT'.$request->id.'">
				    <input type="hidden" name="cmd" value="_xclick">
				    <input type="hidden" name="item_name" value="Payout from '.get_bloginfo('name').'">
				    <input type="hidden" name="no_note" value="1">
				    <input type="hidden" name="business" value="'.$account['account'].'">
				    <input type="hidden" name="currency_code" value="'.wpdmpp_currency_code().'">
				    <input type="hidden" name="return" value="'.home_url().'/">
				    <input type="hidden" name="notify_url" value="'.home_url().'/?action=withdraw_paypal_notification">
				    <input name="amount" type="hidden" id="amount" value="'.$request->amount.'">
				    <input type="hidden" name="custom" value="'.$request->id.'" >
				    <button name="sub" class="btn btn-info btn-sm" type="submit" id="sub">Pay Now</button>
				    </form>';
		return $payform;
	}
}