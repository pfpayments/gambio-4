<?php declare(strict_types=1);

if (file_exists(dirname(__DIR__) . '/../vendor/autoload.php')) {
	require_once dirname(__DIR__) . '/../vendor/autoload.php';
}

use GXModules\PostFinanceCheckoutPayment\Library\Core\{Api\WebHooks\Service\WebhooksService, Service\PaymentService};

/**
 * Class PostFinanceCheckoutModuleCenterModuleController
 */
class PostFinanceCheckoutModuleCenterModuleController extends AbstractModuleCenterModuleController
{
	/**
	 * @var PostFinanceCheckoutStorage $configuration
	 */
	protected $configuration;

	protected function _init(): void
	{
		$this->pageTitle = 'PostFinanceCheckout ' . $this->languageTextManager->get_text('payment', 'postfinancecheckout');
		$this->configuration = MainFactory::create('PostFinanceCheckoutStorage');
	}

	/**
	 * @return AdminLayoutHttpControllerResponse
	 * @throws Exception
	 */
	public function actionDefault(): AdminLayoutHttpControllerResponse
	{
		$title = new NonEmptyStringType('PostFinanceCheckout ' . $this->languageTextManager->get_text('payment', 'postfinancecheckout'));
		$template = $this->getTemplateFile('postfinancecheckout/PostFinanceCheckoutPayment/Admin/Html/postfinancecheckout_configuration.html');
		$integrations = [
			[
				'id' => 'iframe',
				'name' => $this->languageTextManager->get_text('integration.iframe', 'postfinancecheckout')
			],
			[
				'id' => 'payment_page',
				'name' => $this->languageTextManager->get_text('integration.payment_page', 'postfinancecheckout')
			],
		];

		$data = MainFactory::create('KeyValueCollection',
			[
				'pageToken' => $_SESSION['coo_page_token']->generate_token(),
				'configuration' => $this->configuration->get_all(),
				'integrations' => $integrations,
				'translate_section' => 'postfinancecheckout',
				'action_save_configuration' => xtc_href_link('admin.php', 'do=PostFinanceCheckoutModuleCenterModule/SaveConfiguration'),
			]);

		return MainFactory::create('AdminLayoutHttpControllerResponse', $title, $template, $data);
	}

	/**
	 * @return RedirectHttpControllerResponse
	 * @throws Exception
	 */
	public function actionSaveConfiguration(): RedirectHttpControllerResponse
	{
		$this->_validatePageToken();

		$newConfiguration = $this->_getPostData('configuration');
		$oldConfiguration = $this->configuration->get_all();
		foreach ($newConfiguration as $key => $value) {
			try {
				$this->configuration->set($key, $value);
			} catch (Exception $e) {
				$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_saving_configuration', 'postfinancecheckout'), 'error');
			}
		}

		// sync payment methods
		try {
			$paymentService = new PaymentService(MainFactory::create('PostFinanceCheckoutStorage'));
			$paymentService->syncPaymentMethods();
		} catch (\Exception $e) {
			// Revert configuration, because current is incorrect
			foreach ($oldConfiguration as $key => $value) {
				$this->configuration->set($key, $value);
			}

			$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_saving_configuration', 'postfinancecheckout'), 'error');
			$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_sync_payment_methods_please_check_credentials', 'postfinancecheckout'), 'error');
			return MainFactory::create(
				'RedirectHttpControllerResponse',
				xtc_href_link('admin.php', 'do=PostFinanceCheckoutModuleCenterModule')
			);
		}


		$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('configuration_saved', 'postfinancecheckout'), 'info');

		// register webhooks
		if (!empty($newConfiguration['user_id']) && !empty($newConfiguration['space_id']) && !empty($newConfiguration['application_key'])) {
			$this->registerWebHooks();
		} else {
			$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_saving_webhooks_invalid_data_provided', 'postfinancecheckout'), 'error');
		}

		return MainFactory::create(
			'RedirectHttpControllerResponse',
			xtc_href_link('admin.php', 'do=PostFinanceCheckoutModuleCenterModule')
		);
	}

	/**
	 * @return string
	 */
	private function registerWebHooks(): string
	{
		try {
			$webHooksService = new WebHooksService(MainFactory::create('PostFinanceCheckoutStorage'));
			$result = $webHooksService->install();
		} catch (\Exception $e) {
			$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('error_saving_webhooks_reached_limit_of_webhooks', 'postfinancecheckout'), 'error');
			return '';
		}

		$GLOBALS['messageStack']->add_session($this->languageTextManager->get_text('portal_details_saved', 'postfinancecheckout'), 'info');

		return json_encode($result);
	}
}
