<?php
namespace Opencart\Admin\Controller\Extension\NextDayTrishawDeliveryRate\Shipping;

/**
 * Class NextDayTrishawDeliveryRate
 *
 * Admin controller for configuring the Next Day Trishaw Delivery Rate shipping method.
 */
class NextDayTrishawDeliveryRate extends \Opencart\System\Engine\Controller {
	private const EXTENSION_CODE = 'shipping_next_day_trishaw_delivery_rate';

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addScript('https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js');
		$this->document->addStyle('https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');

		$this->load->model('setting/setting');
		$this->load->model('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');
		$this->load->model('localisation/tax_class');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate.save', 'user_token=' . $this->session->data['user_token']);
		$data['get_cities'] = $this->url->link('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate.ajaxGetCities', 'user_token=' . $this->session->data['user_token']);
		$data['save_city'] = $this->url->link('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate.ajaxSaveCityRate', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

		$data['user_token'] = $this->session->data['user_token'];
		$data['error_invalid_rate'] = $this->language->get('error_invalid_rate');

		$config_keys = [
			'shipping_next_day_trishaw_delivery_rate_title',
			'shipping_next_day_trishaw_delivery_rate_default_weight',
			'shipping_next_day_trishaw_delivery_rate_additional_weight',
			'shipping_next_day_trishaw_delivery_rate_tax_class_id',
			'shipping_next_day_trishaw_delivery_rate_sort_order',
			'shipping_next_day_trishaw_delivery_rate_status'
		];

		foreach ($config_keys as $key) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} else {
				$data[$key] = $this->config->get($key);
			}
		}

		if (!$data['shipping_next_day_trishaw_delivery_rate_default_weight']) {
			$data['shipping_next_day_trishaw_delivery_rate_default_weight'] = 1;
		}

		if (!$data['shipping_next_day_trishaw_delivery_rate_additional_weight']) {
			$data['shipping_next_day_trishaw_delivery_rate_additional_weight'] = 1;
		}

		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

		$provinces = $this->model_extension_next_day_trishaw_delivery_rate_shipping_next_day_trishaw_delivery_rate->getProvinces();
		$data['provinces'] = $provinces;
		$data['default_province'] = $provinces[0] ?? '';

		$initial_cities = [];

		if ($data['default_province']) {
			$initial_cities = $this->model_extension_next_day_trishaw_delivery_rate_shipping_next_day_trishaw_delivery_rate->getCitiesByProvince($data['default_province']);
		}

		$data['initial_cities'] = json_encode($initial_cities);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate', $data));
	}

	/**
	 * Persist general settings submitted by the admin.
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('shipping_next_day_trishaw_delivery_rate', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX handler to retrieve cities for a given province.
	 *
	 * @return void
	 */
	public function ajaxGetCities(): void {
		$this->load->language('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');

		$json = [];

		if (!$this->user->hasPermission('access', 'extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$province = $this->request->post['province'] ?? '';

		if (!$province) {
			$json['error'] = $this->language->get('error_missing_province');
		}

		if (!$json) {
			$this->load->model('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');

			$json['cities'] = $this->model_extension_next_day_trishaw_delivery_rate_shipping_next_day_trishaw_delivery_rate->getCitiesByProvince($province);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX handler to save a city rate row.
	 *
	 * @return void
	 */
	public function ajaxSaveCityRate(): void {
		$this->load->language('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$province = trim($this->request->post['province'] ?? '');
		$city = trim($this->request->post['city'] ?? '');
		$city_rate = $this->request->post['city_rate'] ?? '';
		$primary_weight_rate = $this->request->post['primary_weight_rate'] ?? '';
		$additional_weight_rate = $this->request->post['additional_weight_rate'] ?? '';
		$status = isset($this->request->post['status']) ? (int)$this->request->post['status'] : 0;

		if (!$province || !$city) {
			$json['error'] = $this->language->get('error_missing_fields');
		}

		if (!is_numeric($city_rate) || !is_numeric($primary_weight_rate) || !is_numeric($additional_weight_rate)) {
			$json['error'] = $this->language->get('error_invalid_rate');
		}

		if (!$json) {
			$this->load->model('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');

			$this->model_extension_next_day_trishaw_delivery_rate_shipping_next_day_trishaw_delivery_rate->saveCityRate([
				'province' => $province,
				'city' => $city,
				'city_rate' => (float)$city_rate,
				'primary_weight_rate' => (float)$primary_weight_rate,
				'additional_weight_rate' => (float)$additional_weight_rate,
				'status' => $status ? 1 : 0
			]);

			$json['success'] = $this->language->get('text_city_saved');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install hook.
	 *
	 * @return void
	 */
	public function install(): void {
		$this->load->model('user/user_group');

		if ($this->user && $this->user->getGroupId()) {
			$this->model_user_user_group->addPermission(
				(int)$this->user->getGroupId(),
				'access',
				'extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate'
			);

			$this->model_user_user_group->addPermission(
				(int)$this->user->getGroupId(),
				'modify',
				'extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate'
			);
		}

		$this->load->model('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');
		$this->model_extension_next_day_trishaw_delivery_rate_shipping_next_day_trishaw_delivery_rate->install();
	}

	/**
	 * Uninstall hook.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->load->model('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');
		$this->model_extension_next_day_trishaw_delivery_rate_shipping_next_day_trishaw_delivery_rate->uninstall();
	}
}

