<?php

use GXModules\PostFinanceCheckoutPayment\Library\Core\Settings\Struct\Settings;

class PostFinanceCheckout_CheckoutPaymentContentControl extends PostFinanceCheckout_CheckoutPaymentContentControl_parent
{
    public function proceed()
    {
		$_SESSION['gm_error_message'] = $this->getErrorMessage();
		return parent::proceed();
    }

    /**
     * @return string
     * @throws \PostFinanceCheckout\Sdk\ApiException
     * @throws \PostFinanceCheckout\Sdk\Http\ConnectionException
     * @throws \PostFinanceCheckout\Sdk\VersioningException
     */
    private function getErrorMessage()
    {
        $transactionId = $_SESSION['transactionID'] ?? null;

	    if (!isset($_GET['payment_error']) || empty($transactionId)) {
	        return '';
	    }

	    $settings = new Settings();
	    $transaction = $settings->getApiClient()->getTransactionService()->read($settings->getSpaceId(), $_SESSION['transactionID']);
	    $languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
	    if (!empty($_GET['payment_error'])) {
	        return $languageTextManager->get_text($_GET['payment_error'], 'postfinancecheckout');
	    }

	    return $transaction->getUserFailureMessage();
    }
}
