<?php
namespace Opencart\Admin\Controller\Extension\StockNotify\Module;
/**
 * Class StockNotify
 *
 * @package Opencart\Admin\Controller\Extension\StockNotify\Module
 */
class StockNotify extends \Opencart\System\Engine\Controller {
	private string $event_code = 'stock_notify';

	/**
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/stock_notify/module/stock_notify');

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
			'href' => $this->url->link('extension/stock_notify/module/stock_notify', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/stock_notify/module/stock_notify.save', 'user_token=' . $this->session->data['user_token']);
		$data['delete'] = $this->url->link('extension/stock_notify/module/stock_notify.delete', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$defaults = [
			'module_stock_notify_status'         => 0,
			'module_stock_notify_button_text'    => 'Notify Me When Available',
			'module_stock_notify_require_name'   => 0,
			'module_stock_notify_show_phone'     => 0,
			'module_stock_notify_email_subject'  => '{product_name} is back in stock!',
			'module_stock_notify_email_body'     => "Hi {customer_name},\n\nGood news! {product_name} is back in stock at {store_name}.\n\nShop now: {product_url}\n\nThank you!"
		];

		foreach ($defaults as $key => $default) {
			$short = substr($key, strlen('module_stock_notify_'));

			if ($this->config->has($key)) {
				$data[$short] = $this->config->get($key);
			} else {
				$data[$short] = $default;
			}
		}

		$this->load->model('extension/stock_notify/module/stock_notify');

		$data['subscriptions'] = $this->model_extension_stock_notify_module_stock_notify->getSubscriptions(['limit' => 100]);
		$data['text_pending'] = $this->language->get('text_pending');
		$data['text_notified'] = $this->language->get('text_notified');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_confirm_delete'] = $this->language->get('text_confirm_delete');

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/stock_notify/module/stock_notify', $data));
	}

	/**
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/stock_notify/module/stock_notify');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/stock_notify/module/stock_notify')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$post = $this->request->post;

			$this->model_setting_setting->editSetting('module_stock_notify', [
				'module_stock_notify_status'        => isset($post['status']) ? (int)$post['status'] : 0,
				'module_stock_notify_button_text'   => (string)($post['button_text'] ?? ''),
				'module_stock_notify_require_name'  => isset($post['require_name']) ? (int)$post['require_name'] : 0,
				'module_stock_notify_show_phone'    => isset($post['show_phone']) ? (int)$post['show_phone'] : 0,
				'module_stock_notify_email_subject' => (string)($post['email_subject'] ?? ''),
				'module_stock_notify_email_body'    => (string)($post['email_body'] ?? '')
			]);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return void
	 */
	public function delete(): void {
		$this->load->language('extension/stock_notify/module/stock_notify');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/stock_notify/module/stock_notify')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('extension/stock_notify/module/stock_notify');

			foreach ($this->request->post['selected'] ?? [] as $stock_notify_id) {
				$this->model_extension_stock_notify_module_stock_notify->deleteSubscription((int)$stock_notify_id);
			}

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * @return void
	 */
	public function install(): void {
		$this->load->model('extension/stock_notify/module/stock_notify');
		$this->model_extension_stock_notify_module_stock_notify->install();

		$this->load->model('user/user_group');

		$route = 'extension/stock_notify/module/stock_notify';

		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $route);
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $route);

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode($this->event_code);

		$this->model_setting_event->addEvent([
			'code'        => $this->event_code,
			'description' => 'Back in stock notify widget',
			'trigger'     => 'catalog/view/common/footer/after',
			'action'      => 'extension/stock_notify/module/stock_notify.footer',
			'status'      => 1,
			'sort_order'  => 0
		]);

		$this->model_setting_event->addEvent([
			'code'        => $this->event_code,
			'description' => 'Back in stock notify product grid icon',
			'trigger'     => 'catalog/view/journal3/products/after',
			'action'      => 'extension/stock_notify/event/products.productsAfter',
			'status'      => 1,
			'sort_order'  => 0
		]);

		$this->model_setting_event->addEvent([
			'code'        => $this->event_code,
			'description' => 'Back in stock notify related/side products icon',
			'trigger'     => 'catalog/view/journal3/side_products/after',
			'action'      => 'extension/stock_notify/event/products.sideProductsAfter',
			'status'      => 1,
			'sort_order'  => 0
		]);

		$this->model_setting_event->addEvent([
			'code'        => $this->event_code,
			'description' => 'Back in stock notify on product save',
			'trigger'     => 'admin/model/catalog/product/editProduct/before',
			'action'      => 'extension/stock_notify/event/product.before',
			'status'      => 1,
			'sort_order'  => 0
		]);

		$this->model_setting_event->addEvent([
			'code'        => $this->event_code,
			'description' => 'Back in stock notify on product save',
			'trigger'     => 'admin/model/catalog/product/editProduct/after',
			'action'      => 'extension/stock_notify/event/product.after',
			'status'      => 1,
			'sort_order'  => 0
		]);
	}

	/**
	 * @return void
	 */
	public function uninstall(): void {
		if (!$this->user->hasPermission('modify', 'extension/stock_notify/module/stock_notify')) {
			return;
		}

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEventByCode($this->event_code);
	}
}
