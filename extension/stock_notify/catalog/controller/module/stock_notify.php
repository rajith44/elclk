<?php
namespace Opencart\Catalog\Controller\Extension\StockNotify\Module;
/**
 * Class StockNotify
 *
 * @package Opencart\Catalog\Controller\Extension\StockNotify\Module
 */
class StockNotify extends \Opencart\System\Engine\Controller {
	/**
	 * @param string $route
	 * @param array  $data
	 * @param string $output
	 *
	 * @return void
	 */
	public function footer(string &$route, array &$data, string &$output): void {
		if (!$this->config->get('module_stock_notify_status')) {
			return;
		}

		$widget = $this->renderWidget();

		if ($widget === '') {
			return;
		}

		if (strpos($output, '</body>') !== false) {
			$output = str_replace('</body>', $widget . '</body>', $output);
		} else {
			$output .= $widget;
		}
	}

	/**
	 * @return string
	 */
	public function renderWidget(): string {
		if (!$this->config->get('module_stock_notify_status')) {
			return '';
		}

		$data = $this->getSharedData();
		$data['show_product_button'] = false;
		$data['product_id'] = 0;
		$data['product_name'] = '';

		$route_current = $this->request->get['route'] ?? '';

		if ($route_current === 'product/product' && !empty($this->request->get['product_id'])) {
			$this->load->model('catalog/product');
			$this->load->model('extension/stock_notify/module/stock_notify');

			$product_id = (int)$this->request->get['product_id'];
			$product = $this->model_catalog_product->getProduct($product_id);

			if ($product && $this->model_extension_stock_notify_module_stock_notify->isOutOfStock($product)) {
				$data['show_product_button'] = true;
				$data['product_id'] = $product_id;
				$data['product_name'] = $product['name'];
			}
		}

		return $this->load->view('extension/stock_notify/module/stock_notify', $data);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getSharedData(): array {
		$this->load->language('extension/stock_notify/module/stock_notify');

		$data['button_notify'] = $this->config->get('module_stock_notify_button_text') ?: $this->language->get('button_notify');
		$data['text_title'] = $this->language->get('text_title');
		$data['text_intro'] = $this->language->get('text_intro');
		$data['text_intro_logged'] = $this->language->get('text_intro_logged');
		$data['text_optional'] = $this->language->get('text_optional');
		$data['text_notify_tooltip'] = $this->language->get('text_notify_tooltip');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_email'] = $this->language->get('entry_email');
		$data['entry_phone'] = $this->language->get('entry_phone');
		$data['button_submit'] = $this->language->get('button_submit');
		$data['button_close'] = $this->language->get('button_close');
		$data['require_name'] = (int)$this->config->get('module_stock_notify_require_name');
		$data['show_phone'] = (int)$this->config->get('module_stock_notify_show_phone');

		$data['name'] = '';
		$data['email'] = '';
		$data['telephone'] = '';
		$data['is_logged'] = false;

		if ($this->customer->isLogged()) {
			$this->load->model('account/customer');

			$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

			if ($customer_info) {
				$data['is_logged'] = true;
				$data['name'] = trim(($customer_info['firstname'] ?? '') . ' ' . ($customer_info['lastname'] ?? ''));
				$data['email'] = (string)($customer_info['email'] ?? '');
				$data['telephone'] = (string)($customer_info['telephone'] ?? '');
			} else {
				$data['is_logged'] = true;
				$data['name'] = trim($this->customer->getFirstName() . ' ' . $this->customer->getLastName());
				$data['email'] = $this->customer->getEmail();
				$data['telephone'] = $this->customer->getTelephone();
			}
		}

		$data['customer_json'] = json_encode([
			'logged'    => $data['is_logged'],
			'name'      => $data['name'],
			'email'     => $data['email'],
			'telephone' => $data['telephone']
		], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

		$data['labels_json'] = json_encode([
			'title'         => $data['text_title'],
			'intro'         => $data['text_intro'],
			'introLogged'   => $data['text_intro_logged'],
			'notifyTooltip' => $data['text_notify_tooltip'],
			'submit'        => $data['button_submit'],
			'close'         => $data['button_close']
		], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

		$data['subscribe'] = $this->url->link('extension/stock_notify/subscribe/subscribe', 'language=' . $this->config->get('config_language'));

		if (!empty($this->request->server['HTTPS'])) {
			$data['base'] = $this->config->get('config_ssl') ?: $this->config->get('config_url');
		} else {
			$data['base'] = $this->config->get('config_url');
		}

		return $data;
	}
}
