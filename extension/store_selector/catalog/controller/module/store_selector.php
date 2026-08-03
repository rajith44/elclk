<?php
namespace Opencart\Catalog\Controller\Extension\StoreSelector\Module;
/**
 * Class StoreSelector
 *
 * @package Opencart\Catalog\Controller\Extension\StoreSelector\Module
 */
class StoreSelector extends \Opencart\System\Engine\Controller {
	/**
	 * @param string $route
	 * @param array  $data
	 * @param string $output
	 *
	 * @return void
	 */
	public function header(string &$route, array &$data, string &$output): void {
		if (!$this->config->get('module_store_selector_status')) {
			return;
		}

		$route_current = $this->request->get['route'] ?? $this->config->get('action_default');

		if ($route_current === 'extension/store_selector/store/gateway') {
			return;
		}

		$widget = $this->index();

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
	public function index(): string {
		$this->load->language('extension/store_selector/module/store_selector');
		$this->load->model('extension/store_selector/module/store_selector');

		$model = $this->model_extension_store_selector_module_store_selector;

		$regions = $model->getRegions();
		$current = $model->getSelectedRegion() ?: $model->detectRegionByHost();

		if ($current === '' || !isset($regions[$current])) {
			$current = \Opencart\Catalog\Model\Extension\StoreSelector\Module\StoreSelector::REGION_LK;
		}

		$data['text_location'] = $this->language->get('text_location');
		$data['text_change'] = $this->language->get('text_change');
		$data['text_select_store'] = $this->language->get('text_select_store');
		$data['current'] = $regions[$current];
		$data['regions'] = array_values($regions);
		$data['select'] = $this->url->link('extension/store_selector/store/select', 'language=' . $this->config->get('config_language'));
		$data['gateway'] = $this->url->link('extension/store_selector/store/gateway', 'language=' . $this->config->get('config_language') . '&change_location=1');

		if (!empty($this->request->server['HTTPS'])) {
			$data['base'] = $this->config->get('config_ssl') ?: $this->config->get('config_url');
		} else {
			$data['base'] = $this->config->get('config_url');
		}

		return $this->load->view('extension/store_selector/module/store_selector', $data);
	}
}
