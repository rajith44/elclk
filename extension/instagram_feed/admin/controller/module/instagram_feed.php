<?php
namespace Opencart\Admin\Controller\Extension\InstagramFeed\Module;
/**
 * Class InstagramFeed
 */
class InstagramFeed extends \Opencart\System\Engine\Controller {
	private string $event_code = 'instagram_feed';

	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/instagram_feed/module/instagram_feed');

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
			'href' => $this->url->link('extension/instagram_feed/module/instagram_feed', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/instagram_feed/module/instagram_feed.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['clear_cache'] = $this->url->link('extension/instagram_feed/module/instagram_feed.clearCache', 'user_token=' . $this->session->data['user_token']);
		$data['test'] = $this->url->link('extension/instagram_feed/module/instagram_feed.test', 'user_token=' . $this->session->data['user_token']);

		$defaults = [
			'module_instagram_feed_status'        => 0,
			'module_instagram_feed_title'         => 'Follow Us on Instagram',
			'module_instagram_feed_username'      => '',
			'module_instagram_feed_user_id'        => '',
			'module_instagram_feed_access_token'  => '',
			'module_instagram_feed_limit'         => 6,
			'module_instagram_feed_columns'       => 3,
			'module_instagram_feed_show_caption'  => 1,
			'module_instagram_feed_show_stats'    => 1,
			'module_instagram_feed_show_follow'   => 1,
			'module_instagram_feed_cache_ttl'     => 3600,
			'module_instagram_feed_auto_contact'  => 1,
		];

		foreach ($defaults as $key => $default) {
			$short = substr($key, strlen('module_instagram_feed_'));

			if ($this->config->has($key)) {
				$data[$short] = $this->config->get($key);
			} else {
				$data[$short] = $default;
			}
		}

		$data['help_username'] = $this->language->get('help_username');
		$data['help_user_id'] = $this->language->get('help_user_id');
		$data['help_access_token'] = $this->language->get('help_access_token');
		$data['help_api'] = $this->language->get('help_api');
		$data['help_layout'] = $this->language->get('help_layout');
		$data['help_auto_contact'] = $this->language->get('help_auto_contact');
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/instagram_feed/module/instagram_feed', $data));
	}

	/**
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/instagram_feed/module/instagram_feed');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/instagram_feed/module/instagram_feed')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');
			$this->load->model('extension/instagram_feed/module/instagram_feed');

			$post = $this->request->post;

			$this->model_setting_setting->editSetting('module_instagram_feed', [
				'module_instagram_feed_status'        => isset($post['status']) ? (int)$post['status'] : 0,
				'module_instagram_feed_title'         => trim((string)($post['title'] ?? '')),
				'module_instagram_feed_username'      => trim((string)($post['username'] ?? '')),
				'module_instagram_feed_user_id'       => trim((string)($post['user_id'] ?? '')),
				'module_instagram_feed_access_token'  => trim((string)($post['access_token'] ?? '')),
				'module_instagram_feed_limit'         => max(1, min(12, (int)($post['limit'] ?? 6))),
				'module_instagram_feed_columns'       => in_array((int)($post['columns'] ?? 3), [2, 3, 4, 6], true) ? (int)$post['columns'] : 3,
				'module_instagram_feed_show_caption'  => isset($post['show_caption']) ? (int)$post['show_caption'] : 0,
				'module_instagram_feed_show_stats'    => isset($post['show_stats']) ? (int)$post['show_stats'] : 0,
				'module_instagram_feed_show_follow'   => isset($post['show_follow']) ? (int)$post['show_follow'] : 0,
				'module_instagram_feed_cache_ttl'     => max(300, (int)($post['cache_ttl'] ?? 3600)),
				'module_instagram_feed_auto_contact'  => isset($post['auto_contact']) ? (int)$post['auto_contact'] : 0,
			]);

			$this->model_extension_instagram_feed_module_instagram_feed->clearCache();

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return void
	 */
	public function clearCache(): void {
		$this->load->language('extension/instagram_feed/module/instagram_feed');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/instagram_feed/module/instagram_feed')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('extension/instagram_feed/module/instagram_feed');

			$this->model_extension_instagram_feed_module_instagram_feed->clearCache();

			$json['success'] = $this->language->get('text_cache_cleared');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return void
	 */
	public function test(): void {
		$this->load->language('extension/instagram_feed/module/instagram_feed');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/instagram_feed/module/instagram_feed')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$user_id = trim((string)($this->request->post['user_id'] ?? $this->config->get('module_instagram_feed_user_id')));
		$token = trim((string)($this->request->post['access_token'] ?? $this->config->get('module_instagram_feed_access_token')));

		if ($user_id === '' || $token === '') {
			$json['error'] = $this->language->get('error_credentials');
		}

		if (!$json) {
			$this->load->model('extension/instagram_feed/module/instagram_feed');

			$result = $this->model_extension_instagram_feed_module_instagram_feed->testConnection($user_id, $token, 3);

			if ($result['error'] !== '') {
				$json['error'] = sprintf($this->language->get('text_connection_fail'), $result['error']);
			} else {
				$json['success'] = sprintf($this->language->get('text_connection_ok'), count($result['posts']));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return void
	 */
	public function install(): void {
		$this->load->model('user/user_group');

		$route = 'extension/instagram_feed/module/instagram_feed';

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $route);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $route);

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode($this->event_code);

		$this->model_setting_event->addEvent([
			'code'        => $this->event_code,
			'description' => 'Instagram feed on contact page',
			'trigger'     => 'catalog/view/extension/contact_stores/contact/contact/after',
			'action'      => 'extension/instagram_feed/module/instagram_feed.contactPage',
			'status'      => 1,
			'sort_order'  => 0
		]);
	}

	/**
	 * @return void
	 */
	public function uninstall(): void {
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode($this->event_code);
	}
}
