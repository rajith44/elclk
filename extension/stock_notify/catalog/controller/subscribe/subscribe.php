<?php
namespace Opencart\Catalog\Controller\Extension\StockNotify\Subscribe;
/**
 * Class Subscribe
 *
 * @package Opencart\Catalog\Controller\Extension\StockNotify\Subscribe
 */
class Subscribe extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/stock_notify/module/stock_notify');

		$json = [];

		if (!$this->config->get('module_stock_notify_status')) {
			$json['error'] = $this->language->get('error_disabled');
		}

		$product_id = (int)($this->request->post['product_id'] ?? 0);
		$email = trim((string)($this->request->post['email'] ?? ''));
		$name = trim((string)($this->request->post['name'] ?? ''));
		$telephone = trim((string)($this->request->post['telephone'] ?? ''));

		if (!$json && $product_id < 1) {
			$json['error'] = $this->language->get('error_product');
		}

		if (!$json && (!filter_var($email, FILTER_VALIDATE_EMAIL) || oc_strlen($email) > 96)) {
			$json['error'] = $this->language->get('error_email');
		}

		if (!$json && $this->config->get('module_stock_notify_require_name') && $name === '') {
			$json['error'] = $this->language->get('error_name');
		}

		if (!$json) {
			$this->load->model('catalog/product');
			$this->load->model('extension/stock_notify/module/stock_notify');

			$product = $this->model_catalog_product->getProduct($product_id);

			if (!$product) {
				$json['error'] = $this->language->get('error_product');
			} elseif (!$this->model_extension_stock_notify_module_stock_notify->isOutOfStock($product)) {
				$json['error'] = $this->language->get('error_in_stock');
			}
		}

		if (!$json) {
			$customer_id = 0;

			if ($this->customer->isLogged()) {
				$customer_id = (int)$this->customer->getId();

				if ($name === '') {
					$name = trim($this->customer->getFirstName() . ' ' . $this->customer->getLastName());
				}

				if ($email === '') {
					$email = (string)$this->customer->getEmail();
				}
			}

			$this->model_extension_stock_notify_module_stock_notify->addSubscription([
				'product_id'   => $product_id,
				'store_id'     => (int)$this->config->get('config_store_id'),
				'customer_id'  => $customer_id,
				'language_id'  => (int)$this->config->get('config_language_id'),
				'name'         => $name,
				'email'        => $email,
				'telephone'    => $telephone
			]);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
