<?php
namespace Opencart\Admin\Controller\Extension\ContactStores\Module;
/**
 * Class ContactStores
 */
class ContactStores extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/contact_stores/module/contact_stores');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/contact_stores/module/contact_stores', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/contact_stores/module/contact_stores.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$defaults = [
			'module_contact_stores_status' => 0,
			'module_contact_stores_intro'  => '',
			'module_contact_stores_sl_name' => 'ELC Sri Lanka',
			'module_contact_stores_sl_address' => "411A, Kotte Road\nPitakotte\nSri Lanka",
			'module_contact_stores_sl_telephone' => '077 270 5397',
			'module_contact_stores_sl_email' => 'malindarajith@gmail.com',
			'module_contact_stores_sl_open' => "Mon - Sat: 9:00 AM - 6:00 PM\nSun: Closed",
			'module_contact_stores_sl_geocode' => '6.8828511,79.9015717',
			'module_contact_stores_sl_map_embed' => '',
			'module_contact_stores_sl_image' => '',
			'module_contact_stores_au_name' => 'ELC Australia',
			'module_contact_stores_au_address' => "Sydney, NSW\nAustralia",
			'module_contact_stores_au_telephone' => '',
			'module_contact_stores_au_email' => '',
			'module_contact_stores_au_open' => "Mon - Fri: 9:00 AM - 5:00 PM",
			'module_contact_stores_au_geocode' => '-33.8688,151.2093',
			'module_contact_stores_au_map_embed' => '',
			'module_contact_stores_au_image' => '',
		];

		foreach ($defaults as $key => $default) {
			$short = substr($key, strlen('module_contact_stores_'));

			if ($this->config->has($key)) {
				$data[$short] = $this->config->get($key);
			} else {
				$data[$short] = $default;
			}
		}

		$data['page_route'] = 'extension/contact_stores/contact';
		$data['help_page_link'] = $this->language->get('help_page_link');
		$data['entry_image'] = $this->language->get('entry_image');
		$data['help_geocode'] = $this->language->get('help_geocode');
		$data['help_map_embed'] = $this->language->get('help_map_embed');
		$data['help_image'] = $this->language->get('help_image');
		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('tool/image');

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));

		foreach (['sl', 'au'] as $code) {
			$image = (string)($data[$code . '_image'] ?? '');

			if ($image && is_file(DIR_IMAGE . html_entity_decode($image, ENT_QUOTES, 'UTF-8'))) {
				$data[$code . '_thumb'] = $this->model_tool_image->resize($image, $this->config->get('config_image_default_width'), $this->config->get('config_image_default_height'));
			} else {
				$data[$code . '_thumb'] = $data['placeholder'];
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/contact_stores/module/contact_stores', $data));
	}

	/**
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/contact_stores/module/contact_stores');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/contact_stores/module/contact_stores')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$post = $this->request->post;

			$this->model_setting_setting->editSetting('module_contact_stores', [
				'module_contact_stores_status'        => isset($post['status']) ? (int)$post['status'] : 0,
				'module_contact_stores_intro'         => trim((string)($post['intro'] ?? '')),
				'module_contact_stores_sl_name'     => trim((string)($post['sl_name'] ?? '')),
				'module_contact_stores_sl_address'  => trim((string)($post['sl_address'] ?? '')),
				'module_contact_stores_sl_telephone'=> trim((string)($post['sl_telephone'] ?? '')),
				'module_contact_stores_sl_email'    => trim((string)($post['sl_email'] ?? '')),
				'module_contact_stores_sl_open'     => trim((string)($post['sl_open'] ?? '')),
				'module_contact_stores_sl_geocode'  => trim((string)($post['sl_geocode'] ?? '')),
				'module_contact_stores_sl_map_embed'=> trim((string)($post['sl_map_embed'] ?? '')),
				'module_contact_stores_sl_image'    => trim((string)($post['sl_image'] ?? '')),
				'module_contact_stores_au_name'     => trim((string)($post['au_name'] ?? '')),
				'module_contact_stores_au_address'  => trim((string)($post['au_address'] ?? '')),
				'module_contact_stores_au_telephone'=> trim((string)($post['au_telephone'] ?? '')),
				'module_contact_stores_au_email'    => trim((string)($post['au_email'] ?? '')),
				'module_contact_stores_au_open'     => trim((string)($post['au_open'] ?? '')),
				'module_contact_stores_au_geocode'  => trim((string)($post['au_geocode'] ?? '')),
				'module_contact_stores_au_map_embed'=> trim((string)($post['au_map_embed'] ?? '')),
				'module_contact_stores_au_image'    => trim((string)($post['au_image'] ?? '')),
			]);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return void
	 */
	public function install(): void {
		$this->load->model('user/user_group');

		$route = 'extension/contact_stores/module/contact_stores';

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $route);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $route);
	}
}
