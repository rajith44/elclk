<?php
namespace Opencart\Admin\Controller\Tool;
/**
 * Class NewsletterInstall
 *
 * @package Opencart\Admin\Controller\Tool
 */
class NewsletterInstall extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('tool/newsletter_install');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('tool/newsletter_install', 'user_token=' . $this->session->data['user_token'])
		];

		$data['install'] = $this->url->link('tool/newsletter_install.install', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/newsletter_install', $data));
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		$this->load->language('tool/newsletter_install');

		$json = [];

		if (!$this->user->hasPermission('modify', 'tool/newsletter_install')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			try {
				// Create newsletter table
				$this->db->query("
					CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "newsletter` (
						`newsletter_id` int(11) NOT NULL AUTO_INCREMENT,
						`name` varchar(64) NOT NULL,
						`subject` varchar(255) NOT NULL,
						`message` text NOT NULL,
						`to_type` varchar(20) NOT NULL DEFAULT 'newsletter',
						`store_id` int(11) NOT NULL DEFAULT '0',
						`customer_group_id` int(11) NOT NULL DEFAULT '0',
						`status` varchar(20) NOT NULL DEFAULT 'draft',
						`date_added` datetime NOT NULL,
						`date_sent` datetime DEFAULT NULL,
						PRIMARY KEY (`newsletter_id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
				");

				// Create newsletter_product table
				$this->db->query("
					CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "newsletter_product` (
						`newsletter_id` int(11) NOT NULL,
						`product_id` int(11) NOT NULL,
						PRIMARY KEY (`newsletter_id`, `product_id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
				");

				$json['success'] = $this->language->get('text_success');
			} catch (\Exception $e) {
				$json['error'] = 'Database Error: ' . $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}

