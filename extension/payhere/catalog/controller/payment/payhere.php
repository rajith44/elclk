<?php
namespace Opencart\Catalog\Controller\Extension\Payhere\Payment;

class Payhere extends \Opencart\System\Engine\Controller {
	private const MSG_AUTH_SUCCESS = 'AUTHORIZATION_SUCCESS';
	private const MSG_AUTH_FAILED = 'AUTHORIZATION_FAILED';
	private const MSG_REC_INST_SUCCESS = 'RECURRING_INSTALLMENT_SUCCESS';
	private const MSG_REC_INST_FAILED = 'RECURRING_INSTALLMENT_FAILED';
	private const MSG_REC_COMPLETE = 'RECURRING_COMPLETE';
	private const MSG_REC_STOPPED = 'RECURRING_STOPPED';

	public function index(): string {
		$this->load->language('extension/payhere/payment/payhere');

		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['language'] = $this->config->get('config_language');

		return $this->load->view('extension/payhere/payment/payhere', $data);
	}

	public function confirm(): void {
		$this->load->language('extension/payhere/payment/payhere');

		$json = [];

		if (!isset($this->session->data['order_id'])) {
			$json['error'] = $this->language->get('error_order');
		}

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] !== 'payhere.payhere') {
			$json['error'] = $this->language->get('error_payment_method');
		}

		if (!$json) {
			$json['redirect'] = $this->url->link('extension/payhere/payment/payhere|pay', 'language=' . $this->config->get('config_language'), true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function pay(): void {
		if (!isset($this->session->data['order_id'])) {
			$this->response->redirect($this->url->link('checkout/cart', 'language=' . $this->config->get('config_language')));

			return;
		}

		$this->load->language('extension/payhere/payment/payhere');
		$this->load->model('checkout/order');

		$order_id = (int)$this->session->data['order_id'];
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			$this->response->redirect($this->url->link('checkout/cart', 'language=' . $this->config->get('config_language')));

			return;
		}

		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['button_back'] = $this->language->get('button_back');
		$data['text_testmode'] = $this->language->get('text_testmode');
		$data['text_recurring_exception_trial'] = $this->language->get('text_recurring_exception_trial');
		$data['text_recurring_exception_unsupported_freq'] = $this->language->get('text_recurring_exception_unsupported_freq');
		$data['text_recurring_exception_unmatching_rec'] = $this->language->get('text_recurring_exception_unmatching_rec');

		$data['has_recurring_products'] = false;
		$data['has_recurring_exceptions'] = false;
		$data['has_recurring_exception_trial'] = false;
		$data['has_recurring_exception_unsupported_freq'] = false;
		$data['has_recurring_exception_unmatching_rec'] = false;
		$data['recurring_startup'] = 0;

		$data['testmode'] = (bool)$this->config->get('payment_payhere_test');

		if ($data['testmode']) {
			$data['action'] = 'https://sandbox.payhere.lk/pay/checkout';
			$data['sandbox'] = true;
		} else {
			$data['action'] = 'https://www.payhere.lk/pay/checkout';
			$data['sandbox'] = false;
		}

		$data['merchant_id'] = $this->config->get('payment_payhere_merchant_id');
		$data['is_onsite_checkout_enabled'] = (bool)$this->config->get('payment_payhere_onsite_checkout');
		$data['platform'] = 'opencart';
		$data['description'] = $this->config->get('config_name');
		$data['transaction_id'] = $order_id;
		$data['order_id'] = $order_id;
		$data['return_url'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
		$data['cancel_url'] = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'), true);
		$data['status_url'] = $this->url->link('extension/payhere/payment/payhere|callback', '', true);
		$data['language'] = $this->config->get('config_language');
		$data['logo'] = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');

		$data['email'] = $order_info['email'];
		$data['firstname'] = $order_info['payment_firstname'];
		$data['lastname'] = $order_info['payment_lastname'];
		$data['address'] = trim($order_info['payment_address_1'] . ($order_info['payment_address_2'] ? ', ' . $order_info['payment_address_2'] : ''));
		$data['address1'] = $order_info['payment_address_1'];
		$data['address2'] = $order_info['payment_address_2'];
		$data['phone'] = $order_info['telephone'];
		$data['phone_number'] = $order_info['telephone'];
		$data['postal_code'] = $order_info['payment_postcode'];
		$data['city'] = $order_info['payment_city'];
		$data['state'] = $order_info['payment_zone'];
		$data['country'] = $order_info['payment_iso_code_3'];

		$data['amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		$data['currency'] = $order_info['currency_code'];

		$product_names = [];
		$data['products'] = [];

		$cart_products = $this->cart->getProducts();

		foreach ($cart_products as $product) {
			$product_names[] = $product['name'];

			$data['products'][] = [
				'name'     => htmlspecialchars($product['name']),
				'model'    => htmlspecialchars($product['model']),
				'price'    => $this->currency->format($product['price'], $order_info['currency_code'], $order_info['currency_value'], false),
				'quantity' => $product['quantity'],
				'weight'   => $product['weight']
			];
		}

		$data['items_text'] = implode(', ', $product_names);

		if ($this->cart->hasSubscription()) {
			$data['has_recurring_products'] = true;
			$data['amount'] = 0;
			$data['recurring_recurrence'] = null;
			$data['recurring_duration'] = null;
			$data['recurring_startup'] = 0;

			foreach ($cart_products as $product) {
				if (empty($product['subscription'])) {
					continue;
				}

				$subscription = $product['subscription'];
				$has_trial = !empty($subscription['trial_status']) && (int)$subscription['trial_status'] === 1 && (int)$subscription['trial_duration'] > 0;

				if ($has_trial) {
					$data['has_recurring_exceptions'] = true;
					$data['has_recurring_exception_trial'] = true;
					continue;
				}

				$frequency = strtolower($subscription['frequency']);

				if (!in_array($frequency, $this->getCompatibleRecurringFrequencies(), true)) {
					$data['has_recurring_exceptions'] = true;
					$data['has_recurring_exception_unsupported_freq'] = true;
					continue;
				}

				$recurrence_term = $this->getRecurrenceTerm($subscription);
				$duration_term = $this->getDurationTerm($subscription);

				if ($data['recurring_recurrence'] === null) {
					$data['recurring_recurrence'] = $recurrence_term;
				} elseif ($data['recurring_recurrence'] !== $recurrence_term) {
					$data['has_recurring_exceptions'] = true;
					$data['has_recurring_exception_unmatching_rec'] = true;
					continue;
				}

				if ($data['recurring_duration'] === null) {
					$data['recurring_duration'] = $duration_term;
				} elseif ($data['recurring_duration'] !== $duration_term) {
					$data['has_recurring_exceptions'] = true;
					$data['has_recurring_exception_unmatching_rec'] = true;
					continue;
				}

				$data['amount'] += $this->currency->format(($subscription['price'] * $product['quantity']), $data['currency'], $order_info['currency_value'], false);
				$data['recurring_startup'] += $this->currency->format($product['total'], $data['currency'], $order_info['currency_value'], false);
			}

			$order_subscription_ids = [];
			$order_subscriptions = $this->model_checkout_order->getSubscriptions($order_id);

			foreach ($order_subscriptions as $order_subscription) {
				if (!empty($order_subscription['order_subscription_id'])) {
					$order_subscription_ids[] = (int)$order_subscription['order_subscription_id'];
				}
			}

			$data['custom_1'] = implode(',', $order_subscription_ids);
		} else {
			$data['custom_1'] = '';
		}

		$data['discount_amount_cart'] = 0;
		$total_adjustment = $this->currency->format($order_info['total'] - $this->cart->getSubTotal(), $order_info['currency_code'], $order_info['currency_value'], false);

		if ($total_adjustment < 0) {
			$data['discount_amount_cart'] -= $total_adjustment;
		}

		$payhere_secret = strtoupper(md5($this->config->get('payment_payhere_secret')));
		$payhere_amount = number_format($data['amount'] + $data['recurring_startup'], 2, '.', '');
		$data['hash'] = strtoupper(md5($data['merchant_id'] . $data['order_id'] . $payhere_amount . $data['currency'] . $payhere_secret));

		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payhere/payment/payhere_pay', $data));
	}

	public function callback(): void {
		$this->log->write('PayHere Payment Gateway Callback Reached');

		$is_subscription = !empty($this->request->post['subscription_id']);

		$order_id = (int)($this->request->post['order_id'] ?? 0);

		$this->load->model('checkout/order');

		$status_code = $this->request->post['status_code'] ?? '';
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			$this->log->write('PayHere Callback Error: Unable to find order info for order_id ' . $order_id);
			return;
		}

		$verified = true;

		$secret = $this->config->get('payment_payhere_secret');

		if ($secret) {
			$hash = $this->request->post['merchant_id'] ?? '';
			$hash .= $this->request->post['order_id'] ?? '';
			$hash .= $this->request->post['payhere_amount'] ?? '';
			$hash .= $this->request->post['payhere_currency'] ?? '';
			$hash .= $status_code;
			$hash .= strtoupper(md5($secret));

			$md5hash = strtoupper(md5($hash));
			$md5sig = $this->request->post['md5sig'] ?? '';

			if ($is_subscription) {
				if ($md5hash !== $md5sig || strcasecmp($this->request->post['merchant_id'] ?? '', $this->config->get('payment_payhere_merchant_id')) !== 0) {
					$verified = false;
				}
			} else {
				$order_total = (float)$this->currency->format((float)$order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
				if ($md5hash !== $md5sig || strcasecmp($this->request->post['merchant_id'] ?? '', $this->config->get('payment_payhere_merchant_id')) !== 0 || (float)($this->request->post['payhere_amount'] ?? 0) !== $order_total) {
					$verified = false;
				}
			}
		}

		if (!$verified) {
			$this->log->write('PayHere callback failed signature validation for order_id ' . $order_id);
			return;
		}

		$comment = '';

		if ($is_subscription && isset($this->request->post['message_type'])) {
			$subscription_id = $this->request->post['subscription_id'];
			$message_type = $this->request->post['message_type'];
			$amount = $this->request->post['payhere_amount'] ?? '0.00';
			$currency = $this->request->post['payhere_currency'] ?? $order_info['currency_code'];

			if ($message_type === self::MSG_AUTH_SUCCESS) {
				$comment = 'PayHere subscription ' . $subscription_id . ' authorized successfully.';
			} elseif ($message_type === self::MSG_AUTH_FAILED) {
				$comment = 'PayHere subscription ' . $subscription_id . ' authorization failed.';
			} elseif ($message_type === self::MSG_REC_INST_SUCCESS) {
				$comment = 'PayHere subscription ' . $subscription_id . ' installment of ' . $currency . ' ' . $amount . ' was successful.';
			} elseif ($message_type === self::MSG_REC_INST_FAILED) {
				$comment = 'PayHere subscription ' . $subscription_id . ' installment of ' . $currency . ' ' . $amount . ' failed.';
			} elseif ($message_type === self::MSG_REC_COMPLETE) {
				$comment = 'PayHere subscription ' . $subscription_id . ' completed.';
			} elseif ($message_type === self::MSG_REC_STOPPED) {
				$comment = 'PayHere subscription ' . $subscription_id . ' stopped.';
			}
		} elseif (!$is_subscription && $status_code !== '2') {
			$status_labels = [
				'-3' => 'charged back',
				'-2' => 'failed',
				'-1' => 'canceled',
				'0'  => 'pending',
				'2'  => 'success'
			];

			$status_text = $status_labels[$status_code] ?? 'unknown';
			$status_message = $this->request->post['status_message'] ?? '';
			$method = $this->request->post['method'] ?? '';
			$payment_id = $this->request->post['payment_id'] ?? '';

			if ($method === 'TEST') {
				$comment = sprintf(
					'Test payment %s due to "%s". Method = %s, Payment ID = %s',
					$status_text,
					$status_message,
					$method,
					$payment_id
				);
			} else {
				$card_holder_name = $this->request->post['card_holder_name'] ?? '';
				$card_no = $this->request->post['card_no'] ?? '';
				$card_expiry = $this->request->post['card_expiry'] ?? '';

				$comment = sprintf(
					'Payment %s due to "%s". Method = %s, Payment ID = %s, Card Holder = %s, Card No = %s, Card Expiry = %s',
					$status_text,
					$status_message,
					$method,
					$payment_id,
					$card_holder_name,
					$card_no,
					$card_expiry
				);
			}
		}

		switch ($status_code) {
			case '2':
				$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_payhere_order_status_id'), $comment, true);
				break;
			case '0':
				$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_payhere_pending_status_id'), $comment, true);
				break;
			case '-1':
				$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_payhere_canceled_status_id'), $comment, true);
				break;
			case '-2':
				$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_payhere_failed_status_id'), $comment, true);
				break;
			case '-3':
				$this->model_checkout_order->addHistory($order_id, (int)$this->config->get('payment_payhere_chargeback_status_id'), $comment, true);
				break;
		}
	}

	private function getCompatibleRecurringFrequencies(): array {
		return ['week', 'month', 'year'];
	}

	private function getRecurrenceTerm(array $subscription): string {
		$cycle = (int)$subscription['cycle'];
		$frequency = ucfirst(strtolower($subscription['frequency']));

		return $cycle . ' ' . $frequency;
	}

	private function getDurationTerm(array $subscription): string {
		$cycle = (int)$subscription['cycle'];
		$duration = (int)$subscription['duration'];
		$frequency = ucfirst(strtolower($subscription['frequency']));

		if ($duration <= 0) {
			return 'Forever';
		}

		return ($cycle * $duration) . ' ' . $frequency;
	}
}

