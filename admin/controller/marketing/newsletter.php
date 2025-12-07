<?php
namespace Opencart\Admin\Controller\Marketing;

class Newsletter extends \Opencart\System\Engine\Controller {
	/**
	 * Check Install
	 *
	 * @return void
	 */
	private function checkInstall(): void {
		// Check if tables exist
		$subscriber_table = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "newsletter_subscriber'");
		$campaign_table = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "newsletter_campaign'");

		if (!$subscriber_table->num_rows || !$campaign_table->num_rows) {
			$this->install();
		} else {
			// Check if new columns exist, if not add them
			$columns = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "newsletter_campaign` LIKE 'send_to_type'");
			if (!$columns->num_rows) {
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "newsletter_campaign` ADD COLUMN `send_to_type` varchar(50) NOT NULL DEFAULT 'subscribers' AFTER `name`");
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "newsletter_campaign` ADD COLUMN `customer_group_ids` text DEFAULT NULL AFTER `send_to_type`");
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "newsletter_campaign` ADD COLUMN `custom_emails` text DEFAULT NULL AFTER `customer_group_ids`");
			}
		}
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		// Read and execute SQL file
		$sql_file = DIR_EXTENSION . 'newsletter/install.sql';

		if (file_exists($sql_file)) {
			$lines = file($sql_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

			$sql = '';

			foreach ($lines as $line) {
				if ($line && (substr($line, 0, 2) != '--') && (substr($line, 0, 1) != '#')) {
					$sql .= $line;

					if (preg_match('/;\s*$/', $line)) {
						$sql = str_replace("`oc_", "`" . DB_PREFIX, $sql);
						$this->db->query($sql);
						$sql = '';
					}
				}
			}
		}
	}

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		// Auto-install database tables if they don't exist
		$this->checkInstall();

		$this->load->language('marketing/newsletter');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->addScript('view/javascript/ckeditor/ckeditor.js');
		$this->document->addScript('view/javascript/ckeditor/adapters/jquery.js');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketing/newsletter', 'user_token=' . $this->session->data['user_token'])
		];

		$data['user_token'] = $this->session->data['user_token'];

		// Load model
		$this->load->model('marketing/newsletter');

		// Get campaigns
		$data['campaigns'] = [];

		$results = $this->model_marketing_newsletter->getCampaigns();

		foreach ($results as $result) {
			$data['campaigns'][] = [
				'campaign_id' => $result['campaign_id'],
				'name'        => $result['name'],
				'subject'     => $result['subject'],
				'status'      => $result['status'],
				'total_sent'  => $result['total_sent'],
				'date_created' => date($this->language->get('date_format_short'), strtotime($result['date_created'])),
				'date_sent'   => $result['date_sent'] ? date($this->language->get('date_format_short'), strtotime($result['date_sent'])) : '',
				'edit'        => $this->url->link('marketing/newsletter.form', 'user_token=' . $this->session->data['user_token'] . '&campaign_id=' . $result['campaign_id']),
				'delete'      => $this->url->link('marketing/newsletter.delete', 'user_token=' . $this->session->data['user_token'] . '&campaign_id=' . $result['campaign_id'])
			];
		}

		// Get subscribers
		$data['subscribers'] = [];

		$subscriber_results = $this->model_marketing_newsletter->getSubscribers();

		foreach ($subscriber_results as $subscriber) {
			$data['subscribers'][] = [
				'subscriber_id' => $subscriber['subscriber_id'],
				'email'         => $subscriber['email'],
				'name'          => $subscriber['name'],
				'status'        => $subscriber['status'],
				'date_added'    => date($this->language->get('date_format_short'), strtotime($subscriber['date_added'])),
				'delete'        => $this->url->link('marketing/newsletter.deleteSubscriber', 'user_token=' . $this->session->data['user_token'] . '&subscriber_id=' . $subscriber['subscriber_id'])
			];
		}

		$data['add'] = $this->url->link('marketing/newsletter.form', 'user_token=' . $this->session->data['user_token']);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketing/newsletter', $data));
	}

	/**
	 * Form
	 *
	 * @return void
	 */
	public function form(): void {
		$this->load->language('marketing/newsletter');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->addScript('view/javascript/ckeditor/ckeditor.js');
		$this->document->addScript('view/javascript/ckeditor/adapters/jquery.js');

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketing/newsletter', 'user_token=' . $this->session->data['user_token'])
		];

		$this->load->model('marketing/newsletter');
		$this->load->model('customer/customer_group');

		$data['campaign'] = [];

		if (isset($this->request->get['campaign_id'])) {
			$campaign_info = $this->model_marketing_newsletter->getCampaign($this->request->get['campaign_id']);

			if ($campaign_info) {
				$data['campaign'] = $campaign_info;
				$data['campaign']['product_ids'] = $campaign_info['product_ids'] ? json_decode($campaign_info['product_ids'], true) : [];
				$data['campaign']['customer_group_ids'] = $campaign_info['customer_group_ids'] ? json_decode($campaign_info['customer_group_ids'], true) : [];
			}
		}

		// Get customer groups
		$data['customer_groups'] = [];
		$customer_groups = $this->model_customer_customer_group->getCustomerGroups();
		foreach ($customer_groups as $customer_group) {
			$data['customer_groups'][] = [
				'customer_group_id' => $customer_group['customer_group_id'],
				'name'               => $customer_group['name']
			];
		}

		// Get products with promotions
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$data['products'] = [];

		$filter_data = [
			'sort'  => 'pd.name',
			'order' => 'ASC',
			'start' => 0,
			'limit' => 1000
		];

		$products = $this->model_catalog_product->getProducts($filter_data);

		foreach ($products as $product) {
			// Check for special price or discount
			$has_promotion = false;
			$original_price = $product['price'];
			$promotion_price = null;

			// Check for special price (special = 1, quantity = 1)
			if (!empty($product['special']) && $product['special'] < $product['price']) {
				$has_promotion = true;
				$promotion_price = $product['special'];
			} elseif (!empty($product['discount']) && $product['discount'] < $product['price']) {
				$has_promotion = true;
				$promotion_price = $product['discount'];
			}

			// Show all products (not just those with promotions)
			$data['products'][] = [
				'product_id'      => $product['product_id'],
				'name'            => $product['name'],
				'model'           => $product['model'],
				'price'           => $this->currency->format($product['price'], $this->config->get('config_currency')),
				'original_price'   => $original_price,
				'promotion_price' => $promotion_price ? $this->currency->format($promotion_price, $this->config->get('config_currency')) : null,
				'has_promotion'   => $has_promotion,
				'image'           => $this->model_tool_image->resize($product['image'] ?: 'no_image.png', 100, 100)
			];
		}

		$data['user_token'] = $this->session->data['user_token'];
		
		// Build save URL with campaign_id if editing
		$save_params = 'user_token=' . $this->session->data['user_token'];
		if (isset($this->request->get['campaign_id'])) {
			$save_params .= '&campaign_id=' . (int)$this->request->get['campaign_id'];
		}
		$data['save'] = $this->url->link('marketing/newsletter.save', $save_params);
		$data['back'] = $this->url->link('marketing/newsletter', 'user_token=' . $this->session->data['user_token']);

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketing/newsletter_form', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function save(): void {
		$this->load->language('marketing/newsletter');

		$json = [];

		if (!$this->user->hasPermission('modify', 'marketing/newsletter')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->post['name']) || !$this->request->post['name']) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!isset($this->request->post['subject']) || !$this->request->post['subject']) {
			$json['error']['subject'] = $this->language->get('error_subject');
		}

		if (!isset($this->request->post['message']) || !$this->request->post['message']) {
			$json['error']['message'] = $this->language->get('error_message');
		}

		if (!isset($json['error'])) {
			$this->load->model('marketing/newsletter');

			$product_ids = isset($this->request->post['product_ids']) ? $this->request->post['product_ids'] : [];
			$customer_group_ids = isset($this->request->post['customer_group_ids']) ? $this->request->post['customer_group_ids'] : [];
			$send_to_type = isset($this->request->post['send_to_type']) ? $this->request->post['send_to_type'] : 'subscribers';
			$custom_emails = isset($this->request->post['custom_emails']) ? $this->request->post['custom_emails'] : '';

			$data = [
				'name'               => $this->request->post['name'],
				'send_to_type'      => $send_to_type,
				'customer_group_ids' => json_encode($customer_group_ids),
				'custom_emails'      => $custom_emails,
				'subject'            => $this->request->post['subject'],
				'message'            => $this->request->post['message'],
				'product_ids'        => json_encode($product_ids),
				'status'             => isset($this->request->post['status']) ? (int)$this->request->post['status'] : 0
			];

			// Check for campaign_id in both GET and POST
			$campaign_id = isset($this->request->get['campaign_id']) ? (int)$this->request->get['campaign_id'] : (isset($this->request->post['campaign_id']) ? (int)$this->request->post['campaign_id'] : 0);
			
			if ($campaign_id) {
				$this->model_marketing_newsletter->editCampaign($campaign_id, $data);
			} else {
				$this->model_marketing_newsletter->addCampaign($data);
			}

			$json['success'] = $this->language->get('text_success');
			$json['redirect'] = $this->url->link('marketing/newsletter', 'user_token=' . $this->session->data['user_token'], true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Send
	 *
	 * @return void
	 */
	public function send(): void {
		$this->load->language('marketing/newsletter');

		$json = [];

		if (!$this->user->hasPermission('modify', 'marketing/newsletter')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->get['campaign_id'])) {
			$json['error'] = $this->language->get('error_campaign');
		}

		if (!$json) {
			$this->load->model('marketing/newsletter');

			$campaign = $this->model_marketing_newsletter->getCampaign($this->request->get['campaign_id']);

			if (!$campaign) {
				$json['error'] = $this->language->get('error_campaign');
			} else {
				// Get recipients based on send_to_type
				$recipients = [];
				$send_to_type = $campaign['send_to_type'] ?? 'subscribers';

				if ($send_to_type == 'all_customers') {
					// Get all customers
					$this->load->model('customer/customer');
					$customers = $this->model_customer_customer->getCustomers(['filter_status' => 1]);
					foreach ($customers as $customer) {
						$recipients[] = [
							'email' => $customer['email'],
							'name'  => $customer['name'] ?? ''
						];
					}
				} elseif ($send_to_type == 'customer_groups') {
					// Get customers from selected customer groups
					$customer_group_ids = $campaign['customer_group_ids'] ? json_decode($campaign['customer_group_ids'], true) : [];
					if (!empty($customer_group_ids)) {
						$this->load->model('customer/customer');
						foreach ($customer_group_ids as $customer_group_id) {
							$customers = $this->model_customer_customer->getCustomers([
								'filter_customer_group_id' => $customer_group_id,
								'filter_status' => 1
							]);
							foreach ($customers as $customer) {
								$recipients[] = [
									'email' => $customer['email'],
									'name'  => $customer['name'] ?? ''
								];
							}
						}
					}
				} elseif ($send_to_type == 'custom') {
					// Get custom emails
					$custom_emails = $campaign['custom_emails'] ?? '';
					if ($custom_emails) {
						$emails = array_map('trim', explode(',', $custom_emails));
						foreach ($emails as $email) {
							if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
								$recipients[] = [
									'email' => $email,
									'name'  => ''
								];
							}
						}
					}
				} else {
					// Default: Newsletter Subscribers
					$subscribers = $this->model_marketing_newsletter->getSubscribers(['status' => 1]);
					foreach ($subscribers as $subscriber) {
						$recipients[] = [
							'email' => $subscriber['email'],
							'name'  => $subscriber['name'] ?? ''
						];
					}
				}

				// Remove duplicates
				$unique_recipients = [];
				$seen_emails = [];
				foreach ($recipients as $recipient) {
					if (!in_array($recipient['email'], $seen_emails)) {
						$unique_recipients[] = $recipient;
						$seen_emails[] = $recipient['email'];
					}
				}

				if (empty($unique_recipients)) {
					$json['error'] = $this->language->get('error_no_subscribers');
				} else {
					// Get products
					$product_ids = $campaign['product_ids'] ? json_decode($campaign['product_ids'], true) : [];

					$this->load->model('catalog/product');
					$this->load->model('tool/image');

					$products = [];

					foreach ($product_ids as $product_id) {
						$product_info = $this->model_catalog_product->getProduct($product_id);

						if ($product_info) {
							// Get special/discount price
							$original_price = $product_info['price'];
							$promotion_price = null;
							$has_promotion = false;

							// Check for special price (special = 1, quantity = 1)
							$special_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity = '1' AND special = '1' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

							if ($special_query->num_rows) {
								$discount_row = $special_query->row;
								if ($discount_row['type'] == 'F') {
									$promotion_price = $discount_row['price'];
								} elseif ($discount_row['type'] == 'P') {
									$promotion_price = $original_price - ($original_price * ($discount_row['price'] / 100));
								} elseif ($discount_row['type'] == 'S') {
									$promotion_price = $original_price - $discount_row['price'];
								}
								$has_promotion = true;
							} else {
								// Check for quantity-based discount
								$discount_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity = '1' AND special = '0' AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

								if ($discount_query->num_rows) {
									$discount_row = $discount_query->row;
									if ($discount_row['type'] == 'F') {
										$promotion_price = $discount_row['price'];
									} elseif ($discount_row['type'] == 'P') {
										$promotion_price = $original_price - ($original_price * ($discount_row['price'] / 100));
									} elseif ($discount_row['type'] == 'S') {
										$promotion_price = $original_price - $discount_row['price'];
									}
									$has_promotion = true;
								}
							}

							// Generate catalog URL (not admin URL)
							$catalog_url = HTTP_CATALOG . 'index.php?route=product/product&product_id=' . $product_info['product_id'];
							
							// Get resized image URL (absolute URL for email)
							$image_path = $this->model_tool_image->resize($product_info['image'] ?: 'no_image.png', 300, 300);
							// Convert relative URL to absolute
							if (strpos($image_path, 'http') !== 0) {
								$image_url = HTTP_CATALOG . ltrim($image_path, '/');
							} else {
								$image_url = $image_path;
							}
							
							$products[] = [
								'product_id'      => $product_info['product_id'],
								'name'            => $product_info['name'],
								'description'     => strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8')),
								'price'           => $this->currency->format($original_price, $this->config->get('config_currency')),
								'promotion_price' => $promotion_price ? $this->currency->format($promotion_price, $this->config->get('config_currency')) : null,
								'has_promotion'   => $has_promotion,
								'image'           => $image_url,
								'href'            => $catalog_url
							];
						}
					}

					// Send emails
					$total_sent = 0;
					$total_failed = 0;
					
				foreach ($unique_recipients as $recipient) {
					// Skip if email is empty
					if (empty($recipient['email']) || !filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
						$total_failed++;
						continue;
					}
					
					if ($this->config->get('config_mail_engine')) {
						try {
							// Suppress warnings/errors during mail sending to prevent JSON parse errors
							$error_level = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
							
							$mail_option = [
								'parameter'     => $this->config->get('config_mail_parameter'),
								'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
								'smtp_username' => $this->config->get('config_mail_smtp_username'),
								'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
								'smtp_port'     => $this->config->get('config_mail_smtp_port'),
								'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
							];

							$mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);

							$mail->setTo($recipient['email']);
							$mail->setFrom($this->config->get('config_email'));
							$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
							$mail->setSubject($campaign['subject']);

							$unsubscribe_code = '';
							if ($send_to_type == 'subscribers') {
								$subscriber_info = $this->model_marketing_newsletter->getSubscriberByEmail($recipient['email']);
								if ($subscriber_info) {
									$unsubscribe_code = HTTP_CATALOG . 'index.php?route=marketing/newsletter/unsubscribe&email=' . urlencode($recipient['email']) . '&code=' . md5($recipient['email'] . $subscriber_info['subscriber_id']);
								}
							}

							$data = [
								'store_name'     => $this->config->get('config_name'),
								'store_url'      => $this->config->get('config_url'),
								'message'        => html_entity_decode($campaign['message'], ENT_QUOTES, 'UTF-8'),
								'products'       => $products,
								'unsubscribe'    => $unsubscribe_code
							];

							$mail->setHtml($this->load->view('marketing/newsletter_email', $data));

							if ($mail->send()) {
								$total_sent++;
							} else {
								$total_failed++;
							}
						} catch (\Exception $e) {
							// Log error but continue with other recipients
							error_log('Newsletter send error: ' . $e->getMessage());
							$total_failed++;
						} finally {
							// Restore error reporting
							if (isset($error_level)) {
								error_reporting($error_level);
							}
						}
					} else {
						$total_failed++;
					}
				}

					// Update campaign
					$this->model_marketing_newsletter->updateCampaignStats($this->request->get['campaign_id'], $total_sent, $total_failed);

					$json['success'] = sprintf($this->language->get('text_sent'), $total_sent, $total_failed);
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Delete
	 *
	 * @return void
	 */
	public function delete(): void {
		$this->load->language('marketing/newsletter');

		$json = [];

		if (!$this->user->hasPermission('modify', 'marketing/newsletter')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json && isset($this->request->get['campaign_id'])) {
			$this->load->model('marketing/newsletter');
			$this->model_marketing_newsletter->deleteCampaign($this->request->get['campaign_id']);
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Delete Subscriber
	 *
	 * @return void
	 */
	public function deleteSubscriber(): void {
		$this->load->language('marketing/newsletter');

		$json = [];

		if (!$this->user->hasPermission('modify', 'marketing/newsletter')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json && isset($this->request->get['subscriber_id'])) {
			$this->load->model('marketing/newsletter');
			$this->model_marketing_newsletter->deleteSubscriber($this->request->get['subscriber_id']);
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}

