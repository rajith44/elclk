<?php
namespace Opencart\Admin\Controller\Extension\Chatbot\Module;
/**
 * Class Chatbot
 *
 * @package Opencart\Admin\Controller\Extension\Chatbot\Module
 */
class Chatbot extends \Opencart\System\Engine\Controller {
	private string $event_code = 'chatbot';

	/**
	 * Index - settings form
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/chatbot/module/chatbot');

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
			'href' => $this->url->link('extension/chatbot/module/chatbot', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/chatbot/module/chatbot.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');
		$data['clear'] = $this->url->link('extension/chatbot/module/chatbot.clear', 'user_token=' . $this->session->data['user_token']);

		// Load saved settings with sensible defaults
		$defaults = [
			'module_chatbot_status'           => 0,
			'module_chatbot_title'            => 'Chat with us',
			'module_chatbot_greeting'         => 'Hi! How can we help you today?',
			'module_chatbot_placeholder'      => 'Type your message...',
			'module_chatbot_product_search'   => 1,
			'module_chatbot_order_status'     => 1,
			'module_chatbot_handoff_label'    => 'Chat with a human',
			'module_chatbot_handoff_url'      => '',
			'module_chatbot_faq'              => "shipping,delivery,deliver = We deliver island-wide within 2-5 working days.\nreturn,refund = You can return items within 7 days of delivery. Please contact support to start a return.\npayment,pay,card = We accept Visa, Mastercard and cash on delivery.",
			'module_chatbot_ai_status'        => 0,
			'module_chatbot_ai_key'           => '',
			'module_chatbot_ai_model'         => 'gpt-4o-mini',
			'module_chatbot_save_transcripts' => 1
		];

		foreach ($defaults as $key => $default) {
			$short = substr($key, strlen('module_chatbot_'));

			if ($this->config->has($key)) {
				$data[$short] = $this->config->get($key);
			} else {
				$data[$short] = $default;
			}
		}

		// Recent transcripts
		$this->load->model('extension/chatbot/module/chatbot');

		$data['transcripts'] = $this->model_extension_chatbot_module_chatbot->getTranscripts(0, 50);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/chatbot/module/chatbot', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/chatbot/module/chatbot');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/chatbot/module/chatbot')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$post = $this->request->post;

			$settings = [
				'module_chatbot_status'           => isset($post['status']) ? (int)$post['status'] : 0,
				'module_chatbot_title'            => $post['title'] ?? '',
				'module_chatbot_greeting'         => $post['greeting'] ?? '',
				'module_chatbot_placeholder'      => $post['placeholder'] ?? '',
				'module_chatbot_product_search'   => isset($post['product_search']) ? (int)$post['product_search'] : 0,
				'module_chatbot_order_status'     => isset($post['order_status']) ? (int)$post['order_status'] : 0,
				'module_chatbot_handoff_label'    => $post['handoff_label'] ?? '',
				'module_chatbot_handoff_url'      => $post['handoff_url'] ?? '',
				'module_chatbot_faq'              => $post['faq'] ?? '',
				'module_chatbot_ai_status'        => isset($post['ai_status']) ? (int)$post['ai_status'] : 0,
				'module_chatbot_ai_key'           => $post['ai_key'] ?? '',
				'module_chatbot_ai_model'         => $post['ai_model'] ?? 'gpt-4o-mini',
				'module_chatbot_save_transcripts' => isset($post['save_transcripts']) ? (int)$post['save_transcripts'] : 0
			];

			$this->model_setting_setting->editSetting('module_chatbot', $settings);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Clear transcripts
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->load->language('extension/chatbot/module/chatbot');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/chatbot/module/chatbot')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('extension/chatbot/module/chatbot');

			$this->model_extension_chatbot_module_chatbot->clearTranscripts();

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install - create table and register footer event
	 *
	 * @return void
	 */
	public function install(): void {
		$this->load->model('extension/chatbot/module/chatbot');
		$this->model_extension_chatbot_module_chatbot->install();

		// Register event to inject the widget into the footer of every page
		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode($this->event_code);

		$this->model_setting_event->addEvent([
			'code'        => $this->event_code,
			'description' => 'Store Chatbot widget injection',
			'trigger'     => 'catalog/view/common/footer/after',
			'action'      => 'extension/chatbot/module/chatbot.footer',
			'status'      => 1,
			'sort_order'  => 0
		]);
	}

	/**
	 * Uninstall - remove event (transcripts table is kept)
	 *
	 * @return void
	 */
	public function uninstall(): void {
		if (!$this->user->hasPermission('modify', 'extension/chatbot/module/chatbot')) {
			return;
		}

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode($this->event_code);
	}
}
