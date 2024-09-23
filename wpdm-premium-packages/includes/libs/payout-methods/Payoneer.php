<?php

namespace WPDMPP\Libs\PayoutMethods;

use WPDMPP\Libs\Withdraws;

class Payoneer {


	/**
	 * @param $request Withdraws
	 *
	 * @return void
	 */
	function payoutLink($request, $account)
	{

		return '<a href="https://myaccount.payoneer.com/ma/pay/makeapayment" class="btn btn-info btn-sm" type="submit" id="sub">Pay Now</a>';
	}
}