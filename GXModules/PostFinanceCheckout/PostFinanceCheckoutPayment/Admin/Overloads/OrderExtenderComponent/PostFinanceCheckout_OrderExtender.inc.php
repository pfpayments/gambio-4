<?php declare(strict_types=1);

use PostFinanceCheckout\Sdk\Model\TransactionState;
use GXModules\PostFinanceCheckout\PostFinanceCheckoutPayment\Shop\Classes\Model\PostFinanceCheckoutTransactionModel;
use GXModules\PostFinanceCheckout\PostFinanceCheckoutPayment\Shop\Classes\Model\PostFinanceCheckoutRefundModel;

class PostFinanceCheckout_OrderExtender extends PostFinanceCheckout_OrderExtender_parent
{
	public function proceed()
	{
		require(DIR_FS_CATALOG . DIR_WS_CLASSES . 'xtcPrice.php');
		$xtPrice = new xtcPrice(DEFAULT_CURRENCY, $_SESSION['customers_status']['customers_status_id']);

		$contentView = MainFactory::create('ContentView');
		$contentView->set_template_dir(DIR_FS_DOCUMENT_ROOT);
		$contentView->set_content_template('GXModules/PostFinanceCheckout/PostFinanceCheckoutPayment/Admin/Templates/postfinancecheckout_transaction_panel.html');
		$contentView->set_flat_assigns(true);
		$contentView->set_caching_enabled(false);

		$transactionModel = new PostFinanceCheckoutTransactionModel();
		$orderId = (int)$_GET['oID'];

		$transaction = $transactionModel->getByOrderId($orderId);

		$transactionData = $transaction->getData();
		$transactionInfo = $transactionData ? \json_decode($transactionData, true) : [];
		$transactionState = $transaction->getState();
		$contentView->set_content_data('orderId', $orderId);

		$refunds = PostFinanceCheckoutRefundModel::getRefunds($orderId);
		$totalRefundsAmount = PostFinanceCheckoutRefundModel::getTotalRefundsAmount($refunds);

		$contentView->set_content_data('refunds', $refunds);
		$contentView->set_content_data('xtPrice', $xtPrice);
		$contentView->set_content_data('totalSumOfRefunds', $totalRefundsAmount);
		$contentView->set_content_data('totalOrderAmount', round($transactionInfo['info']['total'], 2));
		$amountToBeRefunded = round(floatval($transactionInfo['info']['total']) - $totalRefundsAmount, 2);
		$contentView->set_content_data('amountToBeRefunded', number_format($amountToBeRefunded, 2));
		$contentView->set_content_data('transactionState', $transactionState);
		$contentView->set_content_data('authorizedState', TransactionState::AUTHORIZED);
		$contentView->set_content_data('fulfillState', TransactionState::FULFILL);

		$showRefundsForm = $transactionState !== PostFinanceCheckoutTransactionModel::TRANSACTION_STATE_REFUNDED && $amountToBeRefunded > 0;
		$contentView->set_content_data('showRefundsForm', $showRefundsForm);

		$showButtonsAfterFullfill = $transactionState !== TransactionState::FULFILL
			&& $transactionState !== PostFinanceCheckoutTransactionModel::TRANSACTION_STATE_REFUNDED
			&& $transactionState !== PostFinanceCheckoutTransactionModel::TRANSACTION_STATE_PARTIALLY_REFUNDED
			&& $transactionState !== PostFinanceCheckoutTransactionModel::TRANSACTION_STATE_PAID;
		$contentView->set_content_data('showButtonsAfterFullfill', $showButtonsAfterFullfill);

		$showRefundNowButton = $transactionState !== TransactionState::FULFILL
			&& $transactionState !== PostFinanceCheckoutTransactionModel::TRANSACTION_STATE_REFUNDED
			&& $transactionState !== PostFinanceCheckoutTransactionModel::TRANSACTION_STATE_PARTIALLY_REFUNDED ;
		$contentView->set_content_data('showRefundNowButton', $showRefundNowButton);

		$languageTextManager = MainFactory::create_object(LanguageTextManager::class, array(), true);
		$this->v_output_buffer['below_product_data_heading'] = 'PostFinanceCheckout ' . $languageTextManager->get_text('transaction_panel', 'postfinancecheckout');
		$this->v_output_buffer['below_product_data'] = $contentView->get_html();

		$this->addContent();
		parent::proceed();
	}
}
