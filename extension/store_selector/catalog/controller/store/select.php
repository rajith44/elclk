<?php
namespace Opencart\Catalog\Controller\Extension\StoreSelector\Store;
/**
 * Class Select
 *
 * @package Opencart\Catalog\Controller\Extension\StoreSelector\Store
 */
class Select extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/store_selector/store/gateway');
		$this->load->model('extension/store_selector/module/store_selector');

		$model = $this->model_extension_store_selector_module_store_selector;

		$json = [];

		$region = (string)($this->request->post['region'] ?? $this->request->get['region'] ?? '');

		$regions = $model->getRegions();

		if (!isset($regions[$region])) {
			$json['error'] = $this->language->get('error_region');
		}

		if (!$json) {
			$model->setRegionCookie($region);

			$redirect = $model->getRegionUrl($region);

			if ($redirect === '') {
				$redirect = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
			}

			$json['redirect'] = $redirect;
			$json['region'] = $region;
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
