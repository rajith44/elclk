<?php
namespace Opencart\Admin\Controller\Extension\Payhere\Payment;

class Payhere extends \Opencart\System\Engine\Controller {
	public function index(): void {
		$this->load->language('extension/payhere/payment/payhere');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payhere/payment/payhere', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/payhere/payment/payhere.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$data['text_payhere'] = $this->language->get('text_payhere');

		$fields = [
			'payment_payhere_merchant_id',
			'payment_payhere_secret',
			'payment_payhere_status',
			'payment_payhere_onsite_checkout',
			'payment_payhere_test',
			'payment_payhere_total',
			'payment_payhere_order_status_id',
			'payment_payhere_pending_status_id',
			'payment_payhere_canceled_status_id',
			'payment_payhere_failed_status_id',
			'payment_payhere_chargeback_status_id',
			'payment_payhere_geo_zone_id',
			'payment_payhere_sort_order'
		];

		foreach ($fields as $field) {
			$data[$field] = $this->config->get($field);
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payhere/payment/payhere', $data));
	}

	public function save(): void {
		$this->load->language('extension/payhere/payment/payhere');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/payhere/payment/payhere')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['payment_payhere_merchant_id']) || !trim($this->request->post['payment_payhere_merchant_id'])) {
			$json['error']['merchant_id'] = $this->language->get('error_merchant_id');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_payhere', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		$this->load->model('extension/payhere/payment/payhere');

		$this->model_extension_payhere_payment_payhere->install();
	}

	public function uninstall(): void {
		$this->load->model('extension/payhere/payment/payhere');

		if (method_exists($this->model_extension_payhere_payment_payhere, 'uninstall')) {
			$this->model_extension_payhere_payment_payhere->uninstall();
		}
	}
}

