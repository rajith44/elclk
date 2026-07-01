<?php
namespace Opencart\Catalog\Controller\Extension\StoreSelector\Store;
/**
 * Class Gateway
 *
 * @package Opencart\Catalog\Controller\Extension\StoreSelector\Store
 */
class Gateway extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		if (!$this->config->get('module_store_selector_status')) {
			$this->response->redirect($this->url->link('common/home', 'language=' . $this->config->get('config_language')));

			return;
		}

		$this->load->language('extension/store_selector/store/gateway');
		$this->load->model('extension/store_selector/module/store_selector');

		$model = $this->model_extension_store_selector_module_store_selector;

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setDescription($this->language->get('meta_description'));

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_subtitle'] = $this->language->get('text_subtitle');
		$data['text_continue'] = $this->language->get('text_continue');
		$data['text_detecting'] = $model->usesGeoRedirect()
			? $this->language->get('text_detecting')
			: $this->language->get('text_detecting_ask');
		$data['store_name'] = $this->config->get('config_name');
		$data['logo'] = $this->config->get('config_logo') ? $this->config->get('config_url') . 'image/' . $this->config->get('config_logo') : '';

		$data['regions'] = array_values($model->getRegions());
		$data['select'] = $this->url->link('extension/store_selector/store/select', 'language=' . $this->config->get('config_language'));

		if (!empty($this->request->server['HTTPS'])) {
			$data['base'] = $this->config->get('config_ssl') ?: $this->config->get('config_url');
		} else {
			$data['base'] = $this->config->get('config_url');
		}

		$this->response->setOutput($this->load->view('extension/store_selector/store/gateway', $data));
	}
}
