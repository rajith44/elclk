<?php
namespace Opencart\Catalog\Controller\Checkout;
/**
 * Class ShippingMethod
 *
 * @package Opencart\Catalog\Controller\Checkout
 */
class ShippingMethod extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		try {
			// Ensure language is set from request or use default
			$language_code = $this->config->get('config_language');
			if (isset($this->request->get['language']) && !empty(trim($this->request->get['language']))) {
				$language_code = trim($this->request->get['language']);
			}
			
			$this->load->language('checkout/shipping_method');

			if (isset($this->session->data['shipping_method'])) {
				$data['shipping_method'] = $this->session->data['shipping_method']['name'];
				$data['code'] = $this->session->data['shipping_method']['code'];
			} else {
				$data['shipping_method'] = '';
				$data['code'] = '';
			}

			$data['language'] = $language_code;

			$output = $this->load->view('checkout/shipping_method', $data);
			
			// Ensure we always return something
			if (empty($output)) {
				return '<!-- Shipping method section -->';
			}
			
			return $output;
		} catch (\Exception $e) {
			// Return minimal HTML on error
			return '<!-- Error loading shipping methods: ' . htmlspecialchars($e->getMessage()) . ' -->';
		}
	}

	/**
	 * Quote
	 *
	 * @return void
	 */
	public function quote(): void {
		$this->load->language('checkout/shipping_method');

		$json = [];

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Validate if customer data is set
			// if (!isset($this->session->data['customer'])) {
			// 	$json['error'] = $this->language->get('error_customer');
			// }

			// Validate if payment address is set if required in settings - BYPASSED for auto-load
			// if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
			// 	$json['error'] = $this->language->get('error_payment_address');
			// }

			// Validate if shipping not required - BYPASSED to allow loading methods without full address validation
			// if ($this->cart->hasShipping() && !isset($this->session->data['shipping_address']['address_id'])) {
			// 	$json['error'] = $this->language->get('error_shipping_address');
			// }
		}

		if (!$json) {
			// Shipping method
			$this->load->model('checkout/shipping_method');
			$this->load->model('localisation/country');
			$this->load->model('localisation/zone');

			// Create default shipping address if not set
			if (!isset($this->session->data['shipping_address']) || empty($this->session->data['shipping_address'])) {
				// Use store default country and zone
				$default_country_id = (int)$this->config->get('config_country_id');
				$default_zone_id = (int)$this->config->get('config_zone_id');
				
				// Get country info
				$country_info = $this->model_localisation_country->getCountry($default_country_id);
				
				// Get zone info
				$zone_info = $this->model_localisation_zone->getZone($default_zone_id);
				
				// Create default shipping address
				$this->session->data['shipping_address'] = [
					'firstname'      => '',
					'lastname'       => '',
					'company'        => '',
					'address_1'       => '',
					'address_2'       => '',
					'city'           => '',
					'postcode'       => '',
					'country_id'     => $default_country_id,
					'zone_id'        => $default_zone_id,
					'country'        => $country_info ? $country_info['name'] : '',
					'iso_code_2'     => $country_info ? $country_info['iso_code_2'] : '',
					'iso_code_3'     => $country_info ? $country_info['iso_code_3'] : '',
					'address_format' => '',
					'zone'           => $zone_info ? $zone_info['name'] : '',
					'zone_code'      => $zone_info ? $zone_info['code'] : '',
					'custom_field'   => []
				];
			}

			$shipping_methods = $this->model_checkout_shipping_method->getMethods($this->session->data['shipping_address']);

			if ($shipping_methods) {
				$json['shipping_methods'] = $this->session->data['shipping_methods'] = $shipping_methods;
			} else {
				$json['error'] = sprintf($this->language->get('error_no_shipping'), $this->url->link('information/contact', 'language=' . $this->config->get('config_language')));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('checkout/shipping_method');

		$json = [];

		// Validate cart has products and has stock.
		if (!$this->cart->hasProducts() || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout')) || !$this->cart->hasMinimum()) {
			$json['redirect'] = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
		}

		if (!$json) {
			// Validate if customer is logged in or customer session data is not set
			// if (!isset($this->session->data['customer'])) {
			// 	$json['error'] = $this->language->get('error_customer');
			// }

			// Validate if payment address is set if required in settings - BYPASSED for auto-load
			// if ($this->config->get('config_checkout_payment_address') && !isset($this->session->data['payment_address'])) {
			// 	$json['error'] = $this->language->get('error_payment_address');
			// }

			// Validate if shipping not required - BYPASSED to allow saving without full address validation
			// if ($this->cart->hasShipping() && !isset($this->session->data['shipping_address']['address_id'])) {
			// 	$json['error'] = $this->language->get('error_shipping_address');
			// }

			if (isset($this->request->post['shipping_method'])) {
				$shipping = explode('.', $this->request->post['shipping_method']);

				if (!isset($shipping[0]) || !isset($shipping[1]) || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
					$json['error'] = $this->language->get('error_shipping_method');
				}
			} else {
				$json['error'] = $this->language->get('error_shipping_method');
			}
		}

		if (!$json) {
			$this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];

			$json['success'] = $this->language->get('text_success');

			// Clear payment methods
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
