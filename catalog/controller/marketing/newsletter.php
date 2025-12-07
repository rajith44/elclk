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

			if ($subscriber && md5($email . $subscriber['subscriber_id']) == $code) {
				$this->model_marketing_newsletter->deleteSubscriber($subscriber['subscriber_id']);
				$data['success'] = $this->language->get('text_unsubscribe_success');
			} else {
				$data['error'] = $this->language->get('error_unsubscribe');
			}
		} else {
			$data['error'] = $this->language->get('error_unsubscribe');
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['continue'] = $this->url->link('common/home');

		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketing/newsletter_unsubscribe', $data));
	}
}

