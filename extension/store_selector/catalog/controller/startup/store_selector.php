<?php
namespace Opencart\Catalog\Controller\Extension\StoreSelector\Startup;
/**
 * Class StoreSelector
 *
 * @package Opencart\Catalog\Controller\Extension\StoreSelector\Startup
 */
class StoreSelector extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		if (!$this->config->get('module_store_selector_status')) {
			return;
		}

		$this->load->model('extension/store_selector/module/store_selector');

		$model = $this->model_extension_store_selector_module_store_selector;

		$route = $this->request->get['route'] ?? $this->config->get('action_default');

		$ignore = [
			'extension/store_selector/store/gateway',
			'extension/store_selector/store/select',
			'common/maintenance',
			'error/not_found'
		];

		if (str_starts_with($route, 'api/') || in_array($route, $ignore, true)) {
			return;
		}

		if (!empty($this->request->get['change_location'])) {
			$model->clearRegionCookie();
			unset($this->session->data['store_selector_country']);

			$this->redirectToGateway();

			return;
		}

		$selected = $model->getSelectedRegion();
		$host_region = $model->detectRegionByHost();

		if ($selected !== '') {
			if ($host_region !== '' && $host_region !== $selected) {
				$target = $model->getRegionUrl($selected);

				if ($target !== '' && $model->normalizeHost($target) !== $model->getCurrentHost()) {
					$this->response->redirect($target);
				}
			}

			return;
		}

		// Always ask: show store picker, never auto-detect IP or assign by domain.
		if (!$model->usesGeoRedirect()) {
			$this->redirectToGateway();

			return;
		}

		// Geo mode: detect IP and redirect to matching store when possible.
		$country = $model->detectCountryCode();
		$geo_region = $model->countryToRegion($country);

		if ($geo_region !== '') {
			$model->setRegionCookie($geo_region);

			$target = $model->getRegionUrl($geo_region);

			if ($target !== '' && $model->normalizeHost($target) !== $model->getCurrentHost()) {
				$this->response->redirect($target);
			}

			return;
		}

		$sl_host = $model->normalizeHost($model->getRegionUrl(\Opencart\Catalog\Model\Extension\StoreSelector\Module\StoreSelector::REGION_LK));

		if ($model->getCurrentHost() === $sl_host) {
			$this->redirectToGateway();

			return;
		}

		if ($host_region !== '') {
			$model->setRegionCookie($host_region);

			return;
		}

		$this->redirectToGateway();
	}

	/**
	 * @return void
	 */
	private function redirectToGateway(): void {
		$this->response->redirect($this->url->link('extension/store_selector/store/gateway', 'language=' . $this->config->get('config_language')));
	}
}
