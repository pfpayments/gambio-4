<?php

class PostFinanceCheckout_CheckoutSuccessContentControl extends PostFinanceCheckout_CheckoutSuccessContentControl_parent
{
	public function proceed()
	{
		if (strpos($_SESSION['payment'] ?? '', 'postfinancecheckout') !== false) {
			$this->reset();
		}
		
		parent::proceed();
		return true;
	}
	
	public function reset()
	{
		$_SESSION['cart']->reset(true);
		
		// unregister session variables used during checkout
		unset($_SESSION['sendto']);
		unset($_SESSION['billto']);
		unset($_SESSION['shipping']);
		unset($_SESSION['payment']);
		unset($_SESSION['comments']);
		unset($_SESSION['last_order']);
		unset($_SESSION['tmp_oID']);
		unset($_SESSION['cc']);
		unset($_SESSION['nvpReqArray']);
		unset($_SESSION['reshash']);
		$GLOBALS['last_order'] = $this->order_id;
		
		//GV Code Start
		if (isset($_SESSION['credit_covers'])) {
			unset($_SESSION['credit_covers']);
		}
		unset($_SESSION['transactionID']);
		unset($_SESSION['payment_methods_title']);
		unset($_SESSION['createdTransactionId']);
		unset($_SESSION['possiblePaymentMethod']);
		unset($_SESSION['javascriptUrl']);
		unset($_SESSION['integration']);
	}
}
