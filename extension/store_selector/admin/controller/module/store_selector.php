<?php
namespace Opencart\Admin\Controller\Extension\StoreSelector\Module;
/**
 * Class StoreSelector
 *
 * @package Opencart\Admin\Controller\Extension\StoreSelector\Module
 */
class StoreSelector extends \Opencart\System\Engine\Controller {
	private string $event_code = 'store_selector';

	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/store_selector/module/store_selector');

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
			'href' => $this->url->link('extension/store_selector/module/store_selector', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/store_selector/module/store_selector.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$defaults = [
			'module_store_selector_status'          => 0,
			'module_store_selector_sl_url'          => HTTP_CATALOG,
			'module_store_selector_au_url'          => '',
			'module_store_selector_sl_name'         => 'Sri Lanka',
			'module_store_selector_au_name'         => 'Australia',
			'module_store_selector_sl_description'  => 'Island-wide delivery across Sri Lanka',
			'module_store_selector_au_description'  => 'Delivery across Australia',
			'module_store_selector_geo_redirect'    => 1,
			'module_store_selector_first_visit_mode' => 'geo',
			'module_store_selector_cookie_days'     => 30,
			'module_store_selector_cookie_domain'   => ''
		];

		foreach ($defaults as $key => $default) {
			$short = substr($key, strlen('module_store_selector_'));

			if ($this->config->has($key)) {
				$data[$short] = $this->config->get($key);
			} else {
				$data[$short] = $default;
			}
		}

		if (!in_array($data['first_visit_mode'], ['ask', 'geo'], true)) {
			$data['first_visit_mode'] = $data['geo_redirect'] ? 'geo' : 'ask';
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/store_selector/module/store_selector', $data));
	}

	/**
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/store_selector/module/store_selector');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/store_selector/module/store_selector')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$post = $this->request->post;

		if (empty($post['sl_url'])) {
			$json['error'] = $this->language->get('error_sl_url');
		} elseif (empty($post['au_url'])) {
			$json['error'] = $this->language->get('error_au_url');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$first_visit_mode = ($post['first_visit_mode'] ?? '') === 'ask' ? 'ask' : 'geo';

			$settings = [
				'module_store_selector_status'          => isset($post['status']) ? (int)$post['status'] : 0,
				'module_store_selector_sl_url'          => rtrim((string)$post['sl_url'], '/') . '/',
				'module_store_selector_au_url'          => rtrim((string)$post['au_url'], '/') . '/',
				'module_store_selector_sl_name'         => (string)($post['sl_name'] ?? ''),
				'module_store_selector_au_name'         => (string)($post['au_name'] ?? ''),
				'module_store_selector_sl_description'  => (string)($post['sl_description'] ?? ''),
				'module_store_selector_au_description'  => (string)($post['au_description'] ?? ''),
				'module_store_selector_first_visit_mode' => $first_visit_mode,
				'module_store_selector_geo_redirect'    => $first_visit_mode === 'geo' ? 1 : 0,
				'module_store_selector_cookie_days'     => max(1, (int)($post['cookie_days'] ?? 30)),
				'module_store_selector_cookie_domain'   => (string)($post['cookie_domain'] ?? '')
			];

			$this->model_setting_setting->editSetting('module_store_selector', $settings);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return void
	 */
	public function install(): void {
		$this->load->model('extension/store_selector/module/store_selector');
		$this->model_extension_store_selector_module_store_selector->install();

		$this->load->model('user/user_group');

		$route = 'extension/store_selector/module/store_selector';

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $route);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $route);

		$this->load->model('setting/startup');
		$this->load->model('setting/event');

		$this->model_setting_startup->deleteStartupByCode($this->event_code);

		$this->model_setting_startup->addStartup([
			'code'        => $this->event_code,
			'description' => 'Store region selector redirect',
			'action'      => 'catalog/extension/store_selector/startup/store_selector',
			'status'      => 1,
			'sort_order'  => 2
		]);

		$this->model_setting_event->deleteEventByCode($this->event_code);

		$this->model_setting_event->addEvent([
			'code'        => $this->event_code,
			'description' => 'Store region selector location switcher',
			'trigger'     => 'catalog/view/common/footer/after',
			'action'      => 'extension/store_selector/module/store_selector.header',
			'status'      => 1,
			'sort_order'  => 0
		]);
	}

	/**
	 * @return void
	 */
	public function uninstall(): void {
		if (!$this->user->hasPermission('modify', 'extension/store_selector/module/store_selector')) {
			return;
		}

		$this->load->model('setting/startup');
		$this->load->model('setting/event');

		$this->model_setting_startup->deleteStartupByCode($this->event_code);
		$this->model_setting_event->deleteEventByCode($this->event_code);
	}
}
