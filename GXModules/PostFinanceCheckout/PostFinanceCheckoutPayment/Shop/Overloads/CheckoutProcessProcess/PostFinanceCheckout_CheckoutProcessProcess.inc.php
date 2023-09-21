<?php

use GXModules\PostFinanceCheckoutPayment\Library\{Core\Settings\Options\Integration,
  Core\Settings\Struct\Settings,
  Helper\PostFinanceCheckoutHelper,
  Core\Service\PaymentService
};
use PostFinanceCheckout\Sdk\Model\{AddressCreate, LineItemCreate, LineItemType, Transaction, TransactionCreate};

use GXModules\PostFinanceCheckout\PostFinanceCheckoutPayment\Shop\Classes\Model\PostFinanceCheckoutTransactionModel;
use PostFinanceCheckout\Sdk\Model\TransactionPending;

class PostFinanceCheckout_CheckoutProcessProcess extends PostFinanceCheckout_CheckoutProcessProcess_parent
{
	/**
	 * The proceed method is the main method of the class and performs the complete checkout process.
	 *
	 * @return bool
	 */
	public function proceed()
	{
		if (strpos($_SESSION['payment'] ?? '', 'postfinancecheckout') === false) {
			parent::proceed();
			return true;
		}
		
		$_SESSION['gambio_hub_selection'] = $_SESSION['payment'];
		if ($this->check_redirect()) {
			return true;
		}
		
		$this->_initOrderData();
		
		// check if tmp order id exists
		if (!isset($_SESSION['tmp_oID']) || !is_int($_SESSION['tmp_oID'])) {
			$this->save_order();
			
			$this->save_module_data();
			$this->coo_order_total->apply_credit();
			$this->process_products();
			$this->save_tracking_data();
			
			// redirect to payment service
			if ($this->tmp_order) {
				$this->coo_payment->payment_action();
			}
		}
		
		if ($this->tmp_order === false) {
			$settings = new Settings();
			if (!$settings->isConfirmationEmailSendEnabled()) {
				parent::send_order_mail();
			}
			
			$this->coo_payment->after_process();
			
			$this->set_redirect_url(xtc_href_link("shop.php", 'do=PostFinanceCheckoutPayment/PaymentPage', 'SSL'));
			return true;
		}
		
		return true;
	}
	
	/**
	 * The save_order method stores the order and sets the orderId
	 */
	public function save_order()
	{
		if (strpos($_SESSION['payment'] ?? '', 'postfinancecheckout') === false) {
			return parent::save_order();
		}
		
		$settings = new Settings();
		$integration = $settings->getIntegration();
		
		$orderId = $this->createOrder();
		$createdTransaction = $this->createRemoteTransaction($orderId, $settings, $integration);
		
		$createdTransactionId = $createdTransaction->getId();
		
		$this->confirmTransaction($createdTransaction);
		
		$transactionModel = new PostFinanceCheckoutTransactionModel();
		$transactionModel->create($settings, $createdTransactionId, $orderId, (array)$this->coo_order);
		
		$_SESSION['integration'] = $integration;
		$_SESSION['transactionID'] = $createdTransactionId;
		$this->_setOrderId($orderId);
		
		if ($integration == Integration::PAYMENT_PAGE) {
			$redirectUrl = $settings->getApiClient()->getTransactionPaymentPageService()
			  ->paymentPageUrl($settings->getSpaceId(), $createdTransactionId);
			
			xtc_redirect($redirectUrl);
			return;
		}
		$_SESSION['javascriptUrl'] = $this->getTransactionJavaScriptUrl($createdTransactionId);
		$_SESSION['possiblePaymentMethod'] = $this->getTransactionPaymentMethod($settings, $createdTransactionId);
		$_SESSION['orderTotal'] = $this->coo_order_total->output_array();
	}
	
	/**
	 * @param Transaction $transaction
	 * @throws \PostFinanceCheckout\Sdk\ApiException
	 * @throws \PostFinanceCheckout\Sdk\Http\ConnectionException
	 * @throws \PostFinanceCheckout\Sdk\VersioningException
	 */
	private function confirmTransaction(Transaction $transaction): void
	{
		$pendingTransaction = new TransactionPending();
		$pendingTransaction->setId($transaction->getId());
		$pendingTransaction->setVersion($transaction->getVersion());
		
		$settings = new Settings();
		$settings->getApiClient()->getTransactionService()
		  ->confirm($settings->getSpaceId(), $pendingTransaction);
	}
	
	/**
	 * @return string
	 */
	private function createOrder(): string
	{
		return $this->orderWriteService->createNewCustomerOrder($this->_getCustomerId(),
		  $this->_getCustomerStatusInformation(),
		  $this->_getCustomerNumber(),
		  $this->_getCustomerEmail(),
		  $this->_getCustomerTelephone(),
		  $this->_getCustomerVatId(),
		  $this->_getCustomerDefaultAddress(),
		  $this->_getBillingAddress(),
		  $this->_getDeliveryAddress(),
		  $this->_getOrderItemCollection(),
		  $this->_getOrderTotalCollection(),
		  $this->_getOrderShippingType(),
		  $this->_getOrderPaymentType(),
		  $this->_getCurrencyCode(),
		  $this->_getLanguageCode(),
		  $this->_getOrderTotalWeight(),
		  $this->_getComment(),
		  $this->_getOrderStatusId(),
		  $this->_getOrderAddonValuesCollection());
	}
	
	/**
	 * @param string $orderId
	 * @param Settings $settings
	 * @param string $integration
	 * @return Transaction
	 * @throws \PostFinanceCheckout\Sdk\ApiException
	 * @throws \PostFinanceCheckout\Sdk\Http\ConnectionException
	 * @throws \PostFinanceCheckout\Sdk\VersioningException
	 */
	private function createRemoteTransaction(string $orderId, Settings $settings, string $integration): Transaction
	{
		
		$order = (array)$this->coo_order;
		$lineItems = [];
		foreach ($order['products'] as $product) {
			$lineItem = new LineItemCreate();
			$lineItem->setName($product['name']);
			$lineItem->setUniqueId($product['id']);
			$lineItem->setSku($product['id']);
			$lineItem->setQuantity($product['qty']);
			$lineItem->setAmountIncludingTax(floatval((string)$product['final_price']));
			$lineItem->setType(LineItemType::PRODUCT);
			$lineItems[] = $lineItem;
		}
		
		$shippingCost = floatval((string)$order['info']['shipping_cost']);
		if ($shippingCost > 0) {
			$lineItem = new LineItemCreate();
			$lineItem->setName('Shipping: ' . $order['info']['shipping_method']);
			$lineItem->setUniqueId('shipping-' . $order['info']['shipping_class']);
			$lineItem->setSku('shipping-' . $order['info']['shipping_class']);
			$lineItem->setQuantity(1);
			$lineItem->setAmountIncludingTax($shippingCost);
			$lineItem->setType(LineItemType::SHIPPING);
			$lineItems[] = $lineItem;
		}
		
		$billingAddress = $this->getBillingAddress($order);
		$shippingAddress = $this->getShippingAddress($order);
		
		$transactionPayload = new TransactionCreate();
		$transactionPayload->setCurrency($order['info']['currency']);
		$transactionPayload->setLineItems($lineItems);
		$transactionPayload->setBillingAddress($billingAddress);
		$transactionPayload->setShippingAddress($shippingAddress);
		$transactionPayload->setMerchantReference($orderId);
		$transactionPayload->setMetaData([
		  'orderId' => $orderId,
		  'spaceId' => $settings->getSpaceId(),
		]);
		$transactionPayload->setSpaceViewId($settings->getSpaceViewId());
		$transactionPayload->setAutoConfirmationEnabled(getenv('POSTFINANCECHECKOUT_AUTOCONFIRMATION_ENABLED') ?: false);
		
		if ($integration === Integration::PAYMENT_PAGE) {
			$paymentMethodConfigurationId = $this->getPaymentMethodConfigurationId();
			if ($paymentMethodConfigurationId) {
				$transactionPayload->setAllowedPaymentMethodConfigurations([$paymentMethodConfigurationId]);
			}
		}
		
		$transactionPayload->setSuccessUrl(xtc_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
		$transactionPayload->setFailedUrl(xtc_href_link(FILENAME_CHECKOUT_PAYMENT . '?payment_error', '', 'SSL'));
		$createdTransaction = $settings->getApiClient()->getTransactionService()->create($settings->getSpaceId(), $transactionPayload);
		
		return $createdTransaction;
	}
	
	private function getPaymentMethodConfigurationId()
	{
		$paymentMethodConfigurationId = null;
		$paymentService = new PaymentService(MainFactory::create('PostFinanceCheckoutStorage'));
		$paymentMethodConfigurations = $paymentService->getPaymentMethodConfigurations();
		foreach ($paymentMethodConfigurations as $paymentMethodConfiguration) {
			$slug = 'postfinancecheckout_' . trim(strtolower(PostFinanceCheckoutHelper::slugify($paymentMethodConfiguration->getName())));
			if ($_SESSION['choosen_payment_method'] === $slug) {
				$paymentMethodConfigurationId = $paymentMethodConfiguration->getId();
				break;
			}
		}
		
		return $paymentMethodConfigurationId;
	}
	
	/**
	 * @param Settings $settings
	 * @param string $transactionId
	 * @return array
	 * @throws \PostFinanceCheckout\Sdk\ApiException
	 * @throws \PostFinanceCheckout\Sdk\Http\ConnectionException
	 * @throws \PostFinanceCheckout\Sdk\VersioningException
	 */
	private function getTransactionPaymentMethod(Settings $settings, string $transactionId): array
	{
		$possiblePaymentMethods = $settings->getApiClient()
		  ->getTransactionService()
		  ->fetchPaymentMethods(
			$settings->getSpaceId(),
			$transactionId,
			$settings->getIntegration()
		  );
		
		$chosenPaymentMethod = $_SESSION['choosen_payment_method'];
		
		return array_filter($possiblePaymentMethods, function ($possiblePaymentMethod) use ($chosenPaymentMethod) {
			$slug = 'postfinancecheckout_' . trim(strtolower(PostFinanceCheckoutHelper::slugify($possiblePaymentMethod->getName())));
			return $slug === $chosenPaymentMethod;
		}) ?? [];
	}
	
	private function getBillingAddress($order): AddressCreate
	{
		$billingAddress = new AddressCreate();
		$billingAddress->setStreet($order['billing']['street_address']);
		$billingAddress->setCity($order['billing']['city']);
		$billingAddress->setCountry($order['billing']['country']['iso_code_2']);
		$billingAddress->setEmailAddress($order['customer']['email_address']);
		$billingAddress->setFamilyName($order['billing']['lastname']);
		$billingAddress->setGivenName($order['billing']['firstname']);
		$billingAddress->setPostCode($order['billing']['postcode']);
		$billingAddress->setPostalState($order['billing']['state']);
		$billingAddress->setOrganizationName($order['billing']['company']);
		$billingAddress->setPhoneNumber($order['customer']['telephone']);
		$billingAddress->setSalutation($order['customer']['gender'] === 'm' ? 'Mr' : 'Ms');
		
		return $billingAddress;
	}
	
	private function getShippingAddress($order): AddressCreate
	{
		$shippingAddress = new AddressCreate();
		$shippingAddress->setStreet($order['delivery']['street_address']);
		$shippingAddress->setCity($order['delivery']['city']);
		$shippingAddress->setCountry($order['delivery']['country']['iso_code_2']);
		$shippingAddress->setEmailAddress($order['customer']['email_address']);
		$shippingAddress->setFamilyName($order['delivery']['lastname']);
		$shippingAddress->setGivenName($order['delivery']['firstname']);
		$shippingAddress->setPostCode($order['delivery']['postcode']);
		$shippingAddress->setPostalState($order['delivery']['state']);
		$shippingAddress->setOrganizationName($order['delivery']['company']);
		$shippingAddress->setPhoneNumber($order['customer']['telephone']);
		$shippingAddress->setSalutation($order['customer']['gender'] === 'm' ? 'Mr' : 'Ms');
		
		return $shippingAddress;
	}
	
	/**
	 * @param int $transactionId
	 * @return string
	 * @throws \PostFinanceCheckout\Sdk\ApiException
	 * @throws \PostFinanceCheckout\Sdk\Http\ConnectionException
	 * @throws \PostFinanceCheckout\Sdk\VersioningException
	 */
	private function getTransactionJavaScriptUrl(int $transactionId): string
	{
		$settings = new Settings();
		
		return $settings->getApiClient()->getTransactionIframeService()
		  ->javascriptUrl($settings->getSpaceId(), $transactionId);
	}
}
