<?php
namespace Opencart\Catalog\Controller\Extension\Chatbot\Module;
/**
 * Class Chatbot
 *
 * @package Opencart\Catalog\Controller\Extension\Chatbot\Module
 */
class Chatbot extends \Opencart\System\Engine\Controller {
	/**
	 * Footer event handler - injects the widget into every page.
	 *
	 * Triggered by: catalog/view/common/footer/after
	 *
	 * @param string $route
	 * @param array  $data
	 * @param string $output
	 *
	 * @return void
	 */
	public function footer(string &$route, array &$data, string &$output): void {
		if (!$this->config->get('module_chatbot_status')) {
			return;
		}

		$widget = $this->index();

		if ($widget === '') {
			return;
		}

		if (strpos($output, '</body>') !== false) {
			$output = str_replace('</body>', $widget . '</body>', $output);
		} else {
			$output .= $widget;
		}
	}

	/**
	 * Render the chat widget markup.
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/chatbot/module/chatbot');

		$data['title'] = $this->config->get('module_chatbot_title') ?: $this->language->get('text_title');
		$data['greeting'] = $this->config->get('module_chatbot_greeting') ?: $this->language->get('text_greeting');
		$data['placeholder'] = $this->config->get('module_chatbot_placeholder') ?: $this->language->get('text_placeholder');

		$data['product_search'] = (int)$this->config->get('module_chatbot_product_search');
		$data['order_status'] = (int)$this->config->get('module_chatbot_order_status');
		$data['handoff_label'] = $this->config->get('module_chatbot_handoff_url') ? $this->config->get('module_chatbot_handoff_label') : '';
		$data['handoff_url'] = $this->config->get('module_chatbot_handoff_url');

		$data['text_send'] = $this->language->get('text_send');
		$data['text_quick_products'] = $this->language->get('text_quick_products');
		$data['text_quick_order'] = $this->language->get('text_quick_order');
		$data['text_quick_faq'] = $this->language->get('text_quick_faq');

		$data['send'] = $this->url->link('extension/chatbot/module/chatbot.send', 'language=' . $this->config->get('config_language'));

		// Base URL for static assets
		if (!empty($this->request->server['HTTPS'])) {
			$data['base'] = $this->config->get('config_ssl') ?: $this->config->get('config_url');
		} else {
			$data['base'] = $this->config->get('config_url');
		}

		return $this->load->view('extension/chatbot/module/chatbot', $data);
	}

	/**
	 * AJAX endpoint - process a user message and return bot reply.
	 *
	 * @return void
	 */
	public function send(): void {
		$this->load->language('extension/chatbot/module/chatbot');
		$this->load->model('extension/chatbot/module/chatbot');

		$json = ['messages' => []];

		if (!$this->config->get('module_chatbot_status')) {
			$this->respond(['messages' => [['type' => 'text', 'text' => $this->language->get('text_error')]]]);

			return;
		}

		$message = trim((string)($this->request->post['message'] ?? ''));
		$session_id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($this->request->post['session_id'] ?? ''));

		if ($session_id === '') {
			$session_id = substr(md5(uniqid('', true)), 0, 32);
		}

		if ($message === '') {
			$this->respond($json);

			return;
		}

		$save = (int)$this->config->get('module_chatbot_save_transcripts');

		if ($save) {
			$this->model_extension_chatbot_module_chatbot->saveTranscript($session_id, 'user', $message);
		}

		$handled = false;

		// Quick-reply intents from the buttons
		if ($message === '__products') {
			$json['messages'][] = ['type' => 'text', 'text' => $this->language->get('text_products_prompt')];
			$handled = true;
		} elseif ($message === '__order') {
			$json['messages'][] = ['type' => 'text', 'text' => $this->language->get('text_order_prompt')];
			$handled = true;
		} elseif ($message === '__faq') {
			$json['messages'][] = ['type' => 'text', 'text' => $this->language->get('text_faq_intro')];

			foreach ($this->getFaqRules() as $rule) {
				$json['messages'][] = ['type' => 'text', 'text' => $rule['answer']];
			}

			$handled = true;
		}

		// Order status lookup (number + email present)
		if (!$handled && $this->config->get('module_chatbot_order_status')) {
			if (preg_match('/(\d{1,10})/', $message, $num) && preg_match('/([^\s@]+@[^\s@]+\.[^\s@]+)/', $message, $mail)) {
				$order = $this->model_extension_chatbot_module_chatbot->getOrder((int)$num[1], $mail[1]);

				if ($order) {
					$total = $this->currency->format($order['total'], $order['currency_code'], $order['currency_value']);

					$json['messages'][] = [
						'type' => 'text',
						'text' => sprintf($this->language->get('text_order_found'), $order['order_id'], $order['firstname'], $order['status'], $total, date('d M Y', strtotime($order['date_added'])))
					];
				} else {
					$json['messages'][] = ['type' => 'text', 'text' => $this->language->get('text_order_none')];
				}

				$handled = true;
			}
		}

		// FAQ keyword matching
		if (!$handled) {
			$answer = $this->matchFaq($message);

			if ($answer !== '') {
				$json['messages'][] = ['type' => 'text', 'text' => $answer];
				$handled = true;
			}
		}

		// Product search
		if (!$handled && $this->config->get('module_chatbot_product_search')) {
			try {
				$products = $this->buildProductCards($message);

				if ($products) {
					$json['messages'][] = [
						'type' => 'text',
						'text' => sprintf($this->language->get('text_products_found'), count($products))
					];
					$json['messages'][] = ['type' => 'products', 'items' => $products];
				} else {
					$json['messages'][] = ['type' => 'text', 'text' => $this->language->get('text_products_none')];
				}

				$handled = true;
			} catch (\Throwable $e) {
				$this->log->write('Chatbot product search error: ' . $e->getMessage());
				$json['messages'][] = ['type' => 'text', 'text' => $this->language->get('text_error')];
				$handled = true;
			}
		}

		// AI fallback
		if (!$handled && $this->config->get('module_chatbot_ai_status') && $this->config->get('module_chatbot_ai_key')) {
			$reply = $this->model_extension_chatbot_module_chatbot->callAi($message, $this->buildAiContext(), (string)$this->config->get('module_chatbot_ai_key'), (string)$this->config->get('module_chatbot_ai_model'));

			if ($reply !== '') {
				$json['messages'][] = ['type' => 'text', 'text' => $reply];
				$handled = true;
			}
		}

		// Generic fallback + handoff
		if (!$handled) {
			$json['messages'][] = ['type' => 'text', 'text' => $this->language->get('text_fallback')];

			if ($this->config->get('module_chatbot_handoff_url')) {
				$json['handoff'] = [
					'label' => $this->config->get('module_chatbot_handoff_label'),
					'url'   => $this->config->get('module_chatbot_handoff_url')
				];
			}
		}

		// Save bot replies
		if ($save) {
			foreach ($json['messages'] as $msg) {
				if ($msg['type'] === 'text') {
					$this->model_extension_chatbot_module_chatbot->saveTranscript($session_id, 'bot', $msg['text']);
				}
			}
		}

		$json['session_id'] = $session_id;

		$this->respond($json);
	}

	/**
	 * Build formatted product cards for a keyword.
	 *
	 * @param string $keyword
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function buildProductCards(string $keyword): array {
		$this->load->model('tool/image');

		$results = $this->model_extension_chatbot_module_chatbot->searchProducts($keyword);

		$cards = [];

		foreach ($results as $result) {
			if ($result['image']) {
				$thumb = $this->model_tool_image->resize(html_entity_decode($result['image'], ENT_QUOTES, 'UTF-8'), 80, 80);
			} else {
				$thumb = $this->model_tool_image->resize('placeholder.png', 80, 80);
			}

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				if ((float)$result['special']) {
					$price = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				}
			} else {
				$price = '';
			}

			$cards[] = [
				'name'         => $result['name'],
				'thumb'        => $thumb,
				'price'        => $price,
				'manufacturer' => $result['manufacturer'] ?? '',
				'href'         => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $result['product_id'], true)
			];
		}

		return $cards;
	}

	/**
	 * Parse FAQ rules from settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function getFaqRules(): array {
		$raw = (string)$this->config->get('module_chatbot_faq');

		$rules = [];

		foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
			$line = trim($line);

			if ($line === '' || strpos($line, '=') === false) {
				continue;
			}

			[$keywords, $answer] = explode('=', $line, 2);

			$keyword_list = array_filter(array_map('trim', explode(',', oc_strtolower($keywords))));

			if (!$keyword_list) {
				continue;
			}

			$rules[] = [
				'keywords' => $keyword_list,
				'answer'   => trim($answer)
			];
		}

		return $rules;
	}

	/**
	 * Match a message against FAQ keywords.
	 *
	 * @param string $message
	 *
	 * @return string
	 */
	private function matchFaq(string $message): string {
		$haystack = oc_strtolower($message);

		foreach ($this->getFaqRules() as $rule) {
			foreach ($rule['keywords'] as $keyword) {
				if ($keyword !== '' && strpos($haystack, $keyword) !== false) {
					return $rule['answer'];
				}
			}
		}

		return '';
	}

	/**
	 * Build store context for the AI fallback.
	 *
	 * @return string
	 */
	private function buildAiContext(): string {
		$store = $this->config->get('config_name');

		$faq_text = '';

		foreach ($this->getFaqRules() as $rule) {
			$faq_text .= '- ' . $rule['answer'] . "\n";
		}

		$context = "You are a helpful customer support assistant for the online store \"" . $store . "\". ";
		$context .= "Answer briefly and politely. Only answer questions about this store, its products, orders, shipping and policies. ";
		$context .= "If you do not know, advise the customer to contact support.";

		if ($faq_text !== '') {
			$context .= "\n\nStore information:\n" . $faq_text;
		}

		return $context;
	}

	/**
	 * Output JSON.
	 *
	 * @param array<string, mixed> $json
	 *
	 * @return void
	 */
	private function respond(array $json): void {
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
