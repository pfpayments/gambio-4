<?php

class PostFinanceCheckout_payment extends PostFinanceCheckout_payment_parent
{
	public function __construct($module = '')
	{
		$payment = $_SESSION['payment'];
		parent::__construct($module);
		if (strpos(strtolower($payment), 'postfinancecheckout') !== false) {
			$_SESSION['payment'] = $payment;
		}
	}
}
