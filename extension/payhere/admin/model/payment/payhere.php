<?php
namespace Opencart\Admin\Model\Extension\Payhere\Payment;

class Payhere extends \Opencart\System\Engine\Model {
	public function install(): void {
		$this->load->model('setting/setting');

		$defaults = [
			'payment_payhere_order_status_id'      => (int)$this->config->get('config_order_status_id'),
			'payment_payhere_pending_status_id'    => (int)$this->config->get('config_order_status_id'),
			'payment_payhere_canceled_status_id'   => (int)$this->config->get('config_order_status_id'),
			'payment_payhere_failed_status_id'     => (int)$this->config->get('config_order_status_id'),
			'payment_payhere_chargeback_status_id' => (int)$this->config->get('config_order_status_id'),
			'payment_payhere_status'               => 0,
			'payment_payhere_sort_order'           => 0,
			'payment_payhere_test'                 => 1,
			'payment_payhere_onsite_checkout'      => 0,
			'payment_payhere_total'                => 0
		];

		$this->model_setting_setting->editSetting('payment_payhere', $defaults);
	}

	public function uninstall(): void {
		$this->load->model('setting/setting');

		$this->model_setting_setting->deleteSetting('payment_payhere');
	}
}

