<?php

class PostFinanceCheckoutCheckoutConfirmationContentControl extends PostFinanceCheckoutCheckoutConfirmationContentControl_parent
{
	public function proceed()
	{
		$choosenPaymentMethod = xtc_db_prepare_input($this->v_data_array['POST']['payment']) ?? '';
		if (strpos($choosenPaymentMethod, 'postfinancecheckout') === false) {
			return parent::proceed();
		}
		
		$this->v_data_array['POST']['payment'] = 'postfinancecheckout';
		parent::proceed();
		$_SESSION['choosen_payment_method'] = $choosenPaymentMethod;
	}
}
