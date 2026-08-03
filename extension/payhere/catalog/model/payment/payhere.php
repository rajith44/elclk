<?php
namespace Opencart\Catalog\Model\Extension\Payhere\Payment;

class Payhere extends \Opencart\System\Engine\Model {
	public function getMethods(array $address = []): array {
		$this->load->language('extension/payhere/payment/payhere');

		$status = (bool)$this->config->get('payment_payhere_status');

		if ($status) {
			$minimum_total = (float)$this->config->get('payment_payhere_total');

			if ($minimum_total > 0 && $minimum_total > $this->cart->getSubTotal()) {
				$status = false;
			}
		}

		if ($status && $this->config->get('payment_payhere_geo_zone_id')) {
			$this->load->model('localisation/geo_zone');

			$geo_zone = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('payment_payhere_geo_zone_id'), (int)($address['country_id'] ?? 0), (int)($address['zone_id'] ?? 0));

			$status = (bool)$geo_zone;
		}

		$method_data = [];

		if ($status) {
			$option_data['payhere'] = [
				'code' => 'payhere.payhere',
				'name' => $this->language->get('text_title')
			];

			$method_data = [
				'code'       => 'payhere',
				'name'       => $this->language->get('text_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_payhere_sort_order')
			];
		}

		return $method_data;
	}
}

