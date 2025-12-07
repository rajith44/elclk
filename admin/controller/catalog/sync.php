<?php
namespace Opencart\Admin\Controller\Catalog;

class Sync extends \Opencart\System\Engine\Controller {
	/**
	 * Check Install
	 *
	 * @return void
	 */
	private function checkInstall(): void {
		$sync_log_table = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "pos_sync_log'");
		$sync_log_detail_table = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "pos_sync_log_detail'");

		if (!$sync_log_table->num_rows || !$sync_log_detail_table->num_rows) {
			$this->install();
		}
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		$sql_file = __DIR__ . '/../../../install_sync.sql';

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
		$this->checkInstall();

		$this->load->language('catalog/sync');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/sync', 'user_token=' . $this->session->data['user_token'])
		];

		$this->load->model('catalog/sync');

		// Get sync logs
		$data['sync_logs'] = [];
		$results = $this->model_catalog_sync->getSyncLogs(['limit' => 50]);

		foreach ($results as $result) {
			$data['sync_logs'][] = [
				'sync_log_id'     => $result['sync_log_id'],
				'sync_date'       => date($this->language->get('date_format_short') . ' ' . $this->language->get('time_format'), strtotime($result['sync_date'])),
				'status'          => $result['status'],
				'total_items'     => $result['total_items'],
				'updated_products' => $result['updated_products'],
				'updated_options' => $result['updated_options'],
				'skipped_items'   => $result['skipped_items'],
				'error_count'     => $result['error_count'],
				'execution_time'  => $result['execution_time'],
				'view'            => $this->url->link('catalog/sync.view', 'user_token=' . $this->session->data['user_token'] . '&sync_log_id=' . $result['sync_log_id'])
			];
		}

		$data['user_token'] = $this->session->data['user_token'];
		$data['sync'] = $this->url->link('catalog/sync.sync', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/sync', $data));
	}

	/**
	 * View Log Details
	 *
	 * @return void
	 */
	public function view(): void {
		$this->load->language('catalog/sync');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/sync', 'user_token=' . $this->session->data['user_token'])
		];

		$this->load->model('catalog/sync');

		if (isset($this->request->get['sync_log_id'])) {
			$sync_log = $this->model_catalog_sync->getSyncLog($this->request->get['sync_log_id']);

			if ($sync_log) {
				$data['sync_log'] = $sync_log;
				$data['sync_log']['sync_date'] = date($this->language->get('date_format_short') . ' ' . $this->language->get('time_format'), strtotime($sync_log['sync_date']));

				// Get log details
				$data['log_details'] = [];
				$details = $this->model_catalog_sync->getSyncLogDetails($this->request->get['sync_log_id']);

				foreach ($details as $detail) {
					$data['log_details'][] = [
						'sku'          => $detail['sku'],
						'product_name' => $detail['product_name'],
						'action'       => $detail['action'],
						'message'      => $detail['message'],
						'old_quantity' => $detail['old_quantity'],
						'new_quantity' => $detail['new_quantity'],
						'old_price'    => $detail['old_price'] ? $this->currency->format($detail['old_price'], $this->config->get('config_currency')) : '',
						'new_price'    => $detail['new_price'] ? $this->currency->format($detail['new_price'], $this->config->get('config_currency')) : '',
						'log_date'     => date($this->language->get('date_format_short') . ' ' . $this->language->get('time_format'), strtotime($detail['log_date']))
					];
				}
			} else {
				$data['error_warning'] = $this->language->get('error_log_not_found');
			}
		} else {
			$data['error_warning'] = $this->language->get('error_log_not_found');
		}

		$data['back'] = $this->url->link('catalog/sync', 'user_token=' . $this->session->data['user_token']);
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/sync_view', $data));
	}

	/**
	 * Sync
	 *
	 * @return void
	 */
	public function sync(): void {
		$this->load->language('catalog/sync');

		$json = [];

		if (!$this->user->hasPermission('modify', 'catalog/sync')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($json['error'])) {
			$this->load->model('catalog/sync');
			$this->load->model('catalog/product');

			$start_time = microtime(true);

			// Create sync log
			$sync_log_id = $this->model_catalog_sync->addSyncLog([
				'status' => 'running',
				'total_items' => 0
			]);

			$updated_products = 0;
			$updated_options = 0;
			$skipped_items = 0;
			$error_count = 0;
			$errors = [];

			try {
				// POS API call
				$curl = curl_init();

				curl_setopt_array($curl, array(
					CURLOPT_URL => 'https://api-printacake.posbill.net/api/app/stock-list',
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => '',
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => 'GET',
					CURLOPT_HTTPHEADER => array(
						'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkdlbmVyYXRlZCBUb2tlbiIsImlhdCI6MTY5ODUyNzIwMCwiZXhwIjoxNzk4NTI3MjAwfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c'
					),
				));

				$response = curl_exec($curl);
				$curl_error = curl_error($curl);
				curl_close($curl);

				if ($curl_error) {
					throw new \Exception('CURL Error: ' . $curl_error);
				}

				$results_array = json_decode($response, true);

				if (!isset($results_array['data']) || !is_array($results_array['data'])) {
					throw new \Exception('Invalid API response');
				}

				$total_items = count($results_array['data']);

				// Update total items
				$this->model_catalog_sync->updateSyncLog($sync_log_id, ['total_items' => $total_items]);

				foreach ($results_array['data'] as $item) {
					$name = ltrim($item['name'] ?? '');
					$sku = $item['barcode'] ?? '';
					$category = ltrim($item['category'] ?? '');
					$sub_category = ltrim($item['subCategory'] ?? '');
					$unit = $item['unit'] ?? '';
					$stock = (int)($item['stock'] ?? 0);
					$price = isset($item['price']) ? (float)$item['price'] : 0.0;
					$status = $item['status'] ?? '';

					if (empty($sku)) {
						$skipped_items++;
						$this->model_catalog_sync->addSyncLogDetail($sync_log_id, [
							'sku' => $sku,
							'product_name' => $name,
							'action' => 'skipped',
							'message' => 'SKU is empty'
						]);
						continue;
					}

					if ($status != 'Active') {
						$skipped_items++;
						$this->model_catalog_sync->addSyncLogDetail($sync_log_id, [
							'sku' => $sku,
							'product_name' => $name,
							'action' => 'skipped',
							'message' => 'Product status is not Active'
						]);
						continue;
					}

					// Check if product exists by SKU
					$product = $this->model_catalog_sync->getProductBySKU($sku);

					if ($product && !empty($product['product_id'])) {
						$product_id = $product['product_id'];
						$web_qty = (int)$product['quantity'];
						$product_price = (float)$product['price'];
						$product_name = $product['name'];

						$old_price = $product_price;
						$old_quantity = $web_qty;

						// Update price if different
						if ($price > 0 && abs($product_price - $price) > 0.01) {
							$this->model_catalog_sync->updateProductPrice($product_id, $price);
						}

						// Update quantity if different
						if ($web_qty != $stock) {
							$this->model_catalog_sync->updateProductQuantity($product_id, $stock);
						}

						$updated_products++;

						$this->model_catalog_sync->addSyncLogDetail($sync_log_id, [
							'sku' => $sku,
							'product_name' => $product_name,
							'action' => 'updated',
							'message' => 'Product updated',
							'old_quantity' => $old_quantity,
							'new_quantity' => $stock,
							'old_price' => $old_price,
							'new_price' => $price
						]);
					} else {
						// Check if SKU exists in product options
						$product_option = $this->model_catalog_sync->getProductOptionBySKU($sku);

						if ($product_option && !empty($product_option['product_option_value_id'])) {
							$old_qty = (int)$product_option['quantity'];
							$old_price_opt = (float)$product_option['price'];

							$this->model_catalog_sync->updateProductOptionQuantity($product_option['product_option_value_id'], $stock, $price);

							$updated_options++;

							$this->model_catalog_sync->addSyncLogDetail($sync_log_id, [
								'sku' => $sku,
								'product_name' => $product_option['product_name'] ?? '',
								'action' => 'updated_option',
								'message' => 'Product option updated',
								'old_quantity' => $old_qty,
								'new_quantity' => $stock,
								'old_price' => $old_price_opt,
								'new_price' => $price
							]);
						} else {
							$skipped_items++;
							$this->model_catalog_sync->addSyncLogDetail($sync_log_id, [
								'sku' => $sku,
								'product_name' => $name,
								'action' => 'skipped',
								'message' => 'Product not found in database'
							]);
						}
					}
				}

				$end_time = microtime(true);
				$execution_time = round($end_time - $start_time, 2);

				// Update sync log
				$this->model_catalog_sync->updateSyncLog($sync_log_id, [
					'status' => 'completed',
					'updated_products' => $updated_products,
					'updated_options' => $updated_options,
					'skipped_items' => $skipped_items,
					'error_count' => $error_count,
					'execution_time' => $execution_time
				]);

				$json['success'] = sprintf($this->language->get('text_sync_success'), $updated_products, $updated_options, $skipped_items);
				$json['redirect'] = $this->url->link('catalog/sync', 'user_token=' . $this->session->data['user_token'], true);

			} catch (\Exception $e) {
				$error_count++;
				$errors[] = $e->getMessage();

				$end_time = microtime(true);
				$execution_time = round($end_time - $start_time, 2);

				$this->model_catalog_sync->updateSyncLog($sync_log_id, [
					'status' => 'error',
					'error_count' => $error_count,
					'error_message' => implode('; ', $errors),
					'execution_time' => $execution_time
				]);

				$json['error'] = $this->language->get('error_sync') . ': ' . $e->getMessage();
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}

