<?php
namespace Opencart\Catalog\Controller\Extension\ContactStores;
/**
 * Class Contact
 */
class Contact extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		if (!$this->config->get('module_contact_stores_status')) {
			$this->response->redirect($this->url->link('common/home', 'language=' . $this->config->get('config_language')));

			return;
		}

		$this->load->language('extension/contact_stores/contact/contact');
		$this->load->model('extension/contact_stores/contact');
		$this->load->model('tool/image');

		$this->document->setTitle($this->language->get('heading_title'));

		if (!empty($this->request->server['HTTPS'])) {
			$data['asset_base'] = $this->config->get('config_ssl') ?: $this->config->get('config_url');
		} else {
			$data['asset_base'] = $this->config->get('config_url');
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/contact_stores/contact', 'language=' . $this->config->get('config_language'))
		];

		$stores = $this->model_extension_contact_stores_contact->getStores();

		$data['intro'] = $this->config->get('module_contact_stores_intro') ?: $this->language->get('text_intro');
		$data['heading_title'] = $this->language->get('heading_title');
		$data['stores'] = [];

		foreach ($stores as $code => $store) {
			if ($store['name'] === '' && $store['address'] === '') {
				continue;
			}

			$image_thumb = '';

			if (!empty($store['image'])) {
				$image_thumb = $this->model_tool_image->resize($store['image'], 960, 540);
			}

			$data['stores'][$code] = $store + [
				'image_thumb' => $image_thumb,
				'sending_from' => $code === 'au' ? 'AUS' : 'SL',
				'button' => $code === 'au'
					? $this->language->get('button_contact_au')
					: $this->language->get('button_contact_sl'),
				'label' => $code === 'au'
					? $this->language->get('text_store_au')
					: $this->language->get('text_store_sl'),
				'flag' => $code === 'au' ? 'AU' : 'SL',
			];
		}

		$data['send'] = $this->url->link('extension/contact_stores/contact.send', 'language=' . $this->config->get('config_language'), true);
		$data['name'] = $this->customer->getFirstName();
		$data['email'] = $this->customer->getEmail();

		$data['text_address'] = $this->language->get('text_address');
		$data['text_telephone'] = $this->language->get('text_telephone');
		$data['text_email'] = $this->language->get('text_email');
		$data['text_open'] = $this->language->get('text_open');
		$data['text_get_directions'] = $this->language->get('text_get_directions');
		$data['text_modal_close'] = $this->language->get('text_modal_close');
		$data['text_modal_send'] = $this->language->get('text_modal_send');
		$data['text_success'] = $this->language->get('text_success');
		$data['text_captcha'] = $this->language->get('text_captcha');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_email'] = $this->language->get('entry_email');
		$data['entry_telephone'] = $this->language->get('entry_telephone');
		$data['entry_enquiry'] = $this->language->get('entry_enquiry');
		$data['map_markers'] = $this->model_extension_contact_stores_contact->getMapMarkers();
		$data['text_map_title'] = $this->language->get('text_map_title');
		$data['captcha'] = $this->loadCaptcha();
		$data['captcha_url'] = $this->url->link('extension/opencart/captcha/basic.captcha', '', true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/contact_stores/contact/contact', $data));
	}

	/**
	 * @return void
	 */
	public function send(): void {
		$this->load->language('extension/contact_stores/contact/contact');
		$this->load->model('extension/contact_stores/contact');

		$json = [];

		$store_code = (string)($this->request->post['store'] ?? '');
		$sending_from = trim((string)($this->request->post['sending_from'] ?? ''));
		$name = trim((string)($this->request->post['name'] ?? ''));
		$email = trim((string)($this->request->post['email'] ?? ''));
		$telephone = trim((string)($this->request->post['telephone'] ?? ''));
		$enquiry = trim((string)($this->request->post['enquiry'] ?? ''));

		$store = $this->model_extension_contact_stores_contact->getStore($store_code);

		if (!$store) {
			$json['error']['store'] = $this->language->get('error_store');
		}

		if ($sending_from === '') {
			$sending_from = $store_code === 'au' ? 'AUS' : 'SL';
		}

		if (!in_array($sending_from, ['SL', 'AUS'], true)) {
			$json['error']['store'] = $this->language->get('error_store');
		}

		if (!oc_validate_length($name, 2, 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!oc_validate_email($email)) {
			$json['error']['email'] = $this->language->get('error_email');
		}

		if (!oc_validate_length($enquiry, 10, 3000)) {
			$json['error']['enquiry'] = $this->language->get('error_enquiry');
		}

		if ($captcha_error = $this->validateCaptcha()) {
			$json['error']['captcha'] = $captcha_error;
		}

		if (!$json) {
			$subject = sprintf($this->language->get('email_subject'), $store['name'], $name);

			$sent = $this->model_extension_contact_stores_contact->sendEnquiry($store_code, [
				'name'         => $name,
				'email'        => $email,
				'telephone'    => $telephone,
				'enquiry'      => $enquiry,
				'subject'      => $subject,
				'sending_from' => $sending_from,
			]);

			if ($sent) {
				$json['success'] = $this->language->get('text_success');
			} else {
				$json['error']['warning'] = $this->language->get('error_send');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return string
	 */
	private function loadCaptcha(): string {
		$this->load->model('setting/extension');

		$captcha = (string)$this->config->get('config_captcha');
		$extension_info = $captcha !== '' ? $this->model_setting_extension->getExtensionByCode('captcha', $captcha) : [];

		if ($extension_info && $this->config->get('captcha_' . $captcha . '_status')) {
			return $this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code']);
		}

		return $this->load->controller('extension/opencart/captcha/basic');
	}

	/**
	 * @return string
	 */
	private function validateCaptcha(): string {
		$this->load->model('setting/extension');

		$captcha = (string)$this->config->get('config_captcha');
		$extension_info = $captcha !== '' ? $this->model_setting_extension->getExtensionByCode('captcha', $captcha) : [];

		if ($extension_info && $this->config->get('captcha_' . $captcha . '_status')) {
			return (string)$this->load->controller('extension/' . $extension_info['extension'] . '/captcha/' . $extension_info['code'] . '.validate');
		}

		return (string)$this->load->controller('extension/opencart/captcha/basic.validate');
	}
}
