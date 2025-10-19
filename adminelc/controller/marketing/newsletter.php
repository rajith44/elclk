<?php
namespace Opencart\Admin\Controller\Marketing;
/**
 * Class Newsletter
 *
 * @package Opencart\Admin\Controller\Marketing
 */
class Newsletter extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('marketing/newsletter');

		$this->document->setTitle($this->language->get('heading_title'));

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketing/newsletter', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['add'] = $this->url->link('marketing/newsletter.form', 'user_token=' . $this->session->data['user_token'] . $url);
		$data['delete'] = $this->url->link('marketing/newsletter.delete', 'user_token=' . $this->session->data['user_token']);

		$data['list'] = $this->getList();

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('marketing/newsletter_list', $data));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
		$this->load->language('marketing/newsletter');

		$this->response->setOutput($this->getList());
	}

	/**
	 * Get List
	 *
	 * @return string
	 */
	protected function getList(): string {

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'date_added';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'DESC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['action'] = $this->url->link('marketing/newsletter.list', 'user_token=' . $this->session->data['user_token'] . $url);

		$data['newsletters'] = [];

		$filter_data = [
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_pagination_admin'),
			'limit' => $this->config->get('config_pagination_admin')
		];

		$this->load->model('marketing/newsletter');

		$newsletter_total = $this->model_marketing_newsletter->getTotalNewsletters();

		$results = $this->model_marketing_newsletter->getNewsletters($filter_data);

		foreach ($results as $result) {
			$data['newsletters'][] = [
				'newsletter_id' => $result['newsletter_id'],
				'name'          => $result['name'],
				'subject'       => $result['subject'],
				'status'        => $result['status'],
				'date_added'    => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'edit'          => $this->url->link('marketing/newsletter.form', 'user_token=' . $this->session->data['user_token'] . '&newsletter_id=' . $result['newsletter_id'] . $url),
				'send'          => $this->url->link('marketing/newsletter.send', 'user_token=' . $this->session->data['user_token'] . '&newsletter_id=' . $result['newsletter_id'] . $url)
			];
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		$data['sort_name'] = $this->url->link('marketing/newsletter.list', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url);
		$data['sort_subject'] = $this->url->link('marketing/newsletter.list', 'user_token=' . $this->session->data['user_token'] . '&sort=subject' . $url);
		$data['sort_date_added'] = $this->url->link('marketing/newsletter.list', 'user_token=' . $this->session->data['user_token'] . '&sort=date_added' . $url);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $newsletter_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('marketing/newsletter.list', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($newsletter_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($newsletter_total - $this->config->get('config_pagination_admin'))) ? $newsletter_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $newsletter_total, ceil($newsletter_total / $this->config->get('config_pagination_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		return $this->load->view('marketing/newsletter_list_content', $data);
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

		$data['text_form'] = !isset($this->request->get['newsletter_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('marketing/newsletter', 'user_token=' . $this->session->data['user_token'] . $url)
		];

		$data['save'] = $this->url->link('marketing/newsletter.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketing/newsletter', 'user_token=' . $this->session->data['user_token'] . $url);

		if (isset($this->request->get['newsletter_id'])) {
			$this->load->model('marketing/newsletter');

			$newsletter_info = $this->model_marketing_newsletter->getNewsletter($this->request->get['newsletter_id']);
		}

		if (isset($this->request->get['newsletter_id'])) {
			$data['newsletter_id'] = (int)$this->request->get['newsletter_id'];
		} else {
			$data['newsletter_id'] = 0;
		}

		if (!empty($newsletter_info)) {
			$data['name'] = $newsletter_info['name'];
		} else {
			$data['name'] = '';
		}

		if (!empty($newsletter_info)) {
			$data['subject'] = $newsletter_info['subject'];
		} else {
			$data['subject'] = '';
		}

		if (!empty($newsletter_info)) {
			$data['message'] = $newsletter_info['message'];
		} else {
			$data['message'] = '';
		}

		if (!empty($newsletter_info)) {
			$data['to'] = $newsletter_info['to_type'];
		} else {
			$data['to'] = 'newsletter';
		}

		if (!empty($newsletter_info)) {
			$data['store_id'] = $newsletter_info['store_id'];
		} else {
			$data['store_id'] = 0;
		}

		if (!empty($newsletter_info)) {
			$data['customer_group_id'] = $newsletter_info['customer_group_id'];
		} else {
			$data['customer_group_id'] = 0;
		}

		if (!empty($newsletter_info)) {
			$data['status'] = $newsletter_info['status'];
		} else {
			$data['status'] = 1;
		}

		// Get newsletter products
		$data['newsletter_products'] = [];

		if (!empty($newsletter_info)) {
			$this->load->model('marketing/newsletter');
			
			$newsletter_products = $this->model_marketing_newsletter->getNewsletterProducts($this->request->get['newsletter_id']);

			foreach ($newsletter_products as $product) {
				$data['newsletter_products'][] = [
					'product_id' => $product['product_id'],
					'name'       => $product['name']
				];
			}
		}

		// Store
		$this->load->model('setting/store');

		$data['stores'] = $this->model_setting_store->getStores();

		// Customer Group
		$this->load->model('customer/customer_group');

		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

		$data['user_token'] = $this->session->data['user_token'];

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

		if (!isset($this->request->post['name']) || (oc_strlen($this->request->post['name']) < 3) || (oc_strlen($this->request->post['name']) > 64)) {
			$json['error']['name'] = $this->language->get('error_name');
		}

		if (!isset($this->request->post['subject']) || (oc_strlen($this->request->post['subject']) < 3) || (oc_strlen($this->request->post['subject']) > 255)) {
			$json['error']['subject'] = $this->language->get('error_subject');
		}

		if (!isset($this->request->post['message']) || !$this->request->post['message']) {
			$json['error']['message'] = $this->language->get('error_message');
		}

		if (!$json) {
			$this->load->model('marketing/newsletter');

			if (!$this->request->post['newsletter_id']) {
				$json['newsletter_id'] = $this->model_marketing_newsletter->addNewsletter($this->request->post);
			} else {
				$this->model_marketing_newsletter->editNewsletter($this->request->post['newsletter_id'], $this->request->post);

				$json['newsletter_id'] = $this->request->post['newsletter_id'];
			}

			$json['success'] = $this->language->get('text_success');
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

		if (isset($this->request->post['selected'])) {
			$selected = $this->request->post['selected'];
		} else {
			$selected = [];
		}

		if (!$this->user->hasPermission('modify', 'marketing/newsletter')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('marketing/newsletter');

			foreach ($selected as $newsletter_id) {
				$this->model_marketing_newsletter->deleteNewsletter($newsletter_id);
			}

			$json['success'] = $this->language->get('text_success');
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
			$json['error']['warning'] = $this->language->get('error_permission');
		}

		if (isset($this->request->get['newsletter_id'])) {
			$newsletter_id = (int)$this->request->get['newsletter_id'];
		} else {
			$newsletter_id = 0;
		}

		if (!$json) {
			$this->load->model('marketing/newsletter');

			$newsletter_info = $this->model_marketing_newsletter->getNewsletter($newsletter_id);

			if ($newsletter_info) {
				// Store
				$this->load->model('setting/store');

				// Setting
				$this->load->model('setting/setting');

				// Customer
				$this->load->model('customer/customer');

				$store_info = $this->model_setting_store->getStore($newsletter_info['store_id']);

				if ($store_info) {
					$store_name = $store_info['name'];
				} else {
					$store_name = $this->config->get('config_name');
				}

				$setting = $this->model_setting_setting->getSetting('config', $newsletter_info['store_id']);

				$store_email = $setting['config_email'] ?? $this->config->get('config_email');

				if (isset($this->request->get['page'])) {
					$page = (int)$this->request->get['page'];
				} else {
					$page = 1;
				}

				$limit = 10;

				$email_total = 0;

				$emails = [];

				switch ($newsletter_info['to_type']) {
					case 'newsletter':
						$customer_data = [
							'filter_newsletter' => 1,
							'start'             => ($page - 1) * $limit,
							'limit'             => $limit
						];

						$email_total = $this->model_customer_customer->getTotalCustomers($customer_data);

						$results = $this->model_customer_customer->getCustomers($customer_data);

						foreach ($results as $result) {
							$emails[] = $result['email'];
						}
						break;
					case 'journal3_newsletter':
						// Get emails from Journal3 newsletter table
						$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "journal3_newsletter`");
						$email_total = (int)$query->row['total'];

						$query = $this->db->query("SELECT email FROM `" . DB_PREFIX . "journal3_newsletter` ORDER BY email ASC LIMIT " . (int)(($page - 1) * $limit) . "," . (int)$limit);

						foreach ($query->rows as $result) {
							if (!empty($result['email'])) {
								$emails[] = $result['email'];
							}
						}
						break;
					case 'all_subscribers':
						// Get emails from both customer table (newsletter=1) and Journal3 newsletter table
						$sql = "SELECT email FROM (
							(SELECT email FROM `" . DB_PREFIX . "customer` WHERE newsletter = 1)
							UNION
							(SELECT email FROM `" . DB_PREFIX . "journal3_newsletter`)
						) AS combined ORDER BY email ASC";

						// Get total count
						$count_query = $this->db->query("SELECT COUNT(*) AS total FROM (
							(SELECT email FROM `" . DB_PREFIX . "customer` WHERE newsletter = 1)
							UNION
							(SELECT email FROM `" . DB_PREFIX . "journal3_newsletter`)
						) AS combined");
						$email_total = (int)$count_query->row['total'];

						// Get paginated results
						$query = $this->db->query($sql . " LIMIT " . (int)(($page - 1) * $limit) . "," . (int)$limit);

						foreach ($query->rows as $result) {
							if (!empty($result['email'])) {
								$emails[] = $result['email'];
							}
						}
						break;
					case 'customer_all':
						$customer_data = [
							'start' => ($page - 1) * $limit,
							'limit' => $limit
						];

						$email_total = $this->model_customer_customer->getTotalCustomers($customer_data);

						$results = $this->model_customer_customer->getCustomers($customer_data);

						foreach ($results as $result) {
							$emails[] = $result['email'];
						}
						break;
					case 'customer_group':
						$customer_data = [
							'filter_customer_group_id' => $newsletter_info['customer_group_id'],
							'start'                    => ($page - 1) * $limit,
							'limit'                    => $limit
						];

						$email_total = $this->model_customer_customer->getTotalCustomers($customer_data);

						$results = $this->model_customer_customer->getCustomers($customer_data);

						foreach ($results as $result) {
							$emails[] = $result['email'];
						}
						break;
				}


				if ($emails) {
					$start = ($page - 1) * $limit;
					$end = $start > ($email_total - $limit) ? $email_total : ($start + $limit);

					if ($end < $email_total) {
						$json['text'] = sprintf($this->language->get('text_sent'), $start ?: 1, $end, $email_total);

						$json['next'] = $this->url->link('marketing/newsletter.send', 'user_token=' . $this->session->data['user_token'] . '&newsletter_id=' . $newsletter_id . '&page=' . ($page + 1), true);
					} else {
						$json['success'] = $this->language->get('text_success_sent');

						$json['next'] = '';

						// Update newsletter status to sent
						$this->model_marketing_newsletter->updateStatus($newsletter_id, 'sent');
					}

					// Build the email with products
					$message = $this->buildEmailTemplate($newsletter_info);

					if ($this->config->get('config_mail_engine')) {
						$mail_option = [
							'parameter'     => $this->config->get('config_mail_parameter'),
							'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
							'smtp_username' => $this->config->get('config_mail_smtp_username'),
							'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
							'smtp_port'     => $this->config->get('config_mail_smtp_port'),
							'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
						];

						$mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);

						foreach ($emails as $email) {
							if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
								$mail->setTo(trim($email));
								$mail->setFrom($store_email);
								$mail->setSender(html_entity_decode($store_name, ENT_QUOTES, 'UTF-8'));
								$mail->setSubject(html_entity_decode($newsletter_info['subject'], ENT_QUOTES, 'UTF-8'));
								$mail->setHtml($message);
								$mail->send();
							}
						}
					}
				} else {
					$json['error']['warning'] = $this->language->get('error_email');
				}
			} else {
				$json['error']['warning'] = $this->language->get('error_newsletter');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Build Email Template
	 *
	 * @param array $newsletter_info
	 *
	 * @return string
	 */
	protected function buildEmailTemplate(array $newsletter_info): string {
		$this->load->model('marketing/newsletter');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		// Get newsletter products
		$products = $this->model_marketing_newsletter->getNewsletterProducts($newsletter_info['newsletter_id']);

		$products_html = '';

		if ($products) {
			$products_html = '<table cellpadding="0" cellspacing="0" width="100%" style="margin: 20px 0;">';
			
			foreach ($products as $product_data) {
				$product = $this->model_catalog_product->getProduct($product_data['product_id']);

				if ($product) {
					if ($product['image']) {
						$image = $this->model_tool_image->resize($product['image'], 200, 200);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', 200, 200);
					}

					// Make image URL absolute
					if (strpos($image, 'http') === false) {
						$image = HTTP_CATALOG . $image;
					}

					$price = $this->currency->format($product['price'], $this->config->get('config_currency'));

					// if ($product['special']) {
					// 	$special = $this->currency->format($product['special'], $this->config->get('config_currency'));
					// } else {
						$special = false;
					// }

					$product_url = HTTP_CATALOG . 'index.php?route=product/product&product_id=' . $product['product_id'];

					$products_html .= '<tr style="border-bottom: 1px solid #eeeeee;">';
					$products_html .= '<td style="padding: 20px; width: 200px;"><img src="' . $image . '" alt="' . htmlspecialchars($product['name']) . '" style="width: 180px; height: auto; border-radius: 8px;" /></td>';
					$products_html .= '<td style="padding: 20px; vertical-align: top;">';
					$products_html .= '<h3 style="margin: 0 0 10px 0; font-size: 18px; font-weight: 600;"><a href="' . $product_url . '" style="color: #333333; text-decoration: none;">' . $product['name'] . '</a></h3>';
					
					if ($product['description']) {
						$description = strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8'));
						$description = mb_substr($description, 0, 150) . '...';
						$products_html .= '<p style="margin: 0 0 15px 0; color: #666666; font-size: 14px; line-height: 1.5;">' . $description . '</p>';
					}
					
					$products_html .= '<div style="margin-bottom: 15px;">';
					if ($special) {
						$products_html .= '<span style="font-size: 20px; font-weight: 700; color: #e74c3c; margin-right: 10px;">' . $special . '</span>';
						$products_html .= '<span style="font-size: 16px; color: #999999; text-decoration: line-through;">' . $price . '</span>';
					} else {
						$products_html .= '<span style="font-size: 20px; font-weight: 700; color: #27ae60;">' . $price . '</span>';
					}
					$products_html .= '</div>';
					
					$products_html .= '<a href="' . $product_url . '" style="display: inline-block; padding: 12px 30px; background-color: #3498db; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 14px;">View Product</a>';
					$products_html .= '</td>';
					$products_html .= '</tr>';
				}
			}
			
			$products_html .= '</table>';
		}

		// Build complete email
		$message  = '<!DOCTYPE html>' . "\n";
		$message .= '<html dir="ltr" lang="en">' . "\n";
		$message .= '<head>' . "\n";
		$message .= '<meta charset="UTF-8">' . "\n";
		$message .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
		$message .= '<title>' . htmlspecialchars($newsletter_info['subject']) . '</title>' . "\n";
		$message .= '</head>' . "\n";
		$message .= '<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">' . "\n";
		$message .= '<table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f4; padding: 20px 0;">' . "\n";
		$message .= '<tr><td align="center">' . "\n";
		$message .= '<table cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">' . "\n";
		$message .= '<tr><td style="padding: 40px 30px; background: linear-gradient(147deg, #FADADD 0%, #fc6c85 74%);">' . "\n";
		$message .= '<h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; text-align: center;">' . htmlspecialchars($newsletter_info['subject']) . '</h1>' . "\n";
		$message .= '</td></tr>' . "\n";
		$message .= '<tr><td style="padding: 30px;">' . "\n";
		$message .= html_entity_decode($newsletter_info['message'], ENT_QUOTES, 'UTF-8') . "\n";
		
		if ($products_html) {
			$message .= '<div style="margin: 30px 0;">' . "\n";
			$message .= '<h2 style="margin: 0 0 20px 0; color: #333333; font-size: 24px; font-weight: 600; border-bottom: 2px solid #667eea; padding-bottom: 10px;">Featured Products</h2>' . "\n";
			$message .= $products_html . "\n";
			$message .= '</div>' . "\n";
		}
		
		$message .= '</td></tr>' . "\n";
		$message .= '<tr><td style="padding: 20px 30px; background-color: #f8f9fa; text-align: center; border-top: 1px solid #eeeeee;">' . "\n";
		$message .= '<p style="margin: 0; color: #999999; font-size: 12px;">You are receiving this email because you subscribed to our newsletter.</p>' . "\n";
		$message .= '<p style="margin: 10px 0 0 0; color: #999999; font-size: 12px;">&copy; ' . date('Y') . ' ' . htmlspecialchars($this->config->get('config_name')) . '. All rights reserved.</p>' . "\n";
		$message .= '</td></tr>' . "\n";
		$message .= '</table>' . "\n";
		$message .= '</td></tr>' . "\n";
		$message .= '</table>' . "\n";
		$message .= '</body>' . "\n";
		$message .= '</html>' . "\n";

		return $message;
	}
}

