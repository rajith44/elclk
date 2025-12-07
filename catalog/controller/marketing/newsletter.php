<?php
namespace Opencart\Catalog\Controller\Marketing;

class Newsletter extends \Opencart\System\Engine\Controller {
	/**
	 * Subscribe
	 *
	 * @return void
	 */
	public function subscribe(): void {
		$this->load->language('marketing/newsletter');

		$json = [];

		if (isset($this->request->post['email'])) {
			$email = $this->request->post['email'];
			$name = isset($this->request->post['name']) ? $this->request->post['name'] : '';

			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$json['error'] = $this->language->get('error_email');
			} else {
				$this->load->model('marketing/newsletter');

				$data = [
					'email'  => $email,
					'name'   => $name,
					'status' => 1
				];

				$this->model_marketing_newsletter->addSubscriber($data);

				$json['success'] = $this->language->get('text_subscribe_success');
			}
		} else {
			$json['error'] = $this->language->get('error_email');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Unsubscribe
	 *
	 * @return void
	 */
	public function unsubscribe(): void {
		$this->load->language('marketing/newsletter');

		$data = [];

		if (isset($this->request->get['email']) && isset($this->request->get['code'])) {
			$email = $this->request->get['email'];
			$code = $this->request->get['code'];

			$this->load->model('marketing/newsletter');

			$subscriber = $this->model_marketing_newsletter->getSubscriberByEmail($email);

			if ($subscriber) {
				// Verify code for existing subscriber
				if (md5($email . $subscriber['subscriber_id']) == $code) {
					// Update status to 0 (unsubscribed) instead of deleting
					$this->db->query("UPDATE `" . DB_PREFIX . "newsletter_subscriber` SET `status` = '0', `date_modified` = NOW() WHERE `subscriber_id` = '" . (int)$subscriber['subscriber_id'] . "'");
					$data['success'] = $this->language->get('text_unsubscribe_success');
				} else {
					$data['error'] = $this->language->get('error_unsubscribe');
				}
			} else {
				// New unsubscribe - verify code and create subscriber record with status=0
				if (md5($email . 'newsletter_unsubscribe') == $code) {
					// Create subscriber record with status=0 (unsubscribed)
					$this->model_marketing_newsletter->addSubscriber([
						'email'  => $email,
						'name'   => '',
						'status' => 0
					]);
					$data['success'] = $this->language->get('text_unsubscribe_success');
				} else {
					$data['error'] = $this->language->get('error_unsubscribe');
				}
			}
		} else {
			$data['error'] = $this->language->get('error_unsubscribe');
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketing/newsletter/unsubscribe', 'language=' . $this->config->get('config_language'))
		];

		$data['continue'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));

		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketing/newsletter_unsubscribe', $data));
	}
}

