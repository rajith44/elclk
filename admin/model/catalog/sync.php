<?php
namespace Opencart\Admin\Model\Catalog;

class Sync extends \Opencart\System\Engine\Model {
	/**
	 * Get Product by SKU (from product_code table)
	 *
	 * @param string $sku
	 * @return array
	 */
	public function getProductBySKU(string $sku): array {
		$query = $this->db->query("SELECT p.*, pd.name, pd.description FROM `" . DB_PREFIX . "product` p 
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
			LEFT JOIN `" . DB_PREFIX . "product_code` pc ON (p.product_id = pc.product_id)
			WHERE pc.value = '" . $this->db->escape($sku) . "' OR p.model = '" . $this->db->escape($sku) . "'
			LIMIT 1");

		return $query->row ?: [];
	}

	/**
	 * Get Product Option by SKU
	 *
	 * @param string $sku
	 * @return array
	 */
	public function getProductOptionBySKU(string $sku): array {
		// In OpenCart 4.x, product_option_value may have SKU field or it might be in product_code
		// First check if product_option_value has sku field directly
		$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product_option_value` LIKE 'sku'");
		
		if ($query->num_rows) {
			// SKU field exists in product_option_value
			$query = $this->db->query("SELECT pov.*, p.product_id, p.model, pd.name as product_name
				FROM `" . DB_PREFIX . "product_option_value` pov
				LEFT JOIN `" . DB_PREFIX . "product` p ON (pov.product_id = p.product_id)
				LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
				WHERE pov.sku = '" . $this->db->escape($sku) . "'
				LIMIT 1");
		} else {
			// SKU might be in product_code table - find products with this SKU and get their options
			// This is a fallback - we'll need to match by product first
			$query = $this->db->query("SELECT pov.*, p.product_id, p.model, pd.name as product_name
				FROM `" . DB_PREFIX . "product_code` pc
				LEFT JOIN `" . DB_PREFIX . "product` p ON (pc.product_id = p.product_id)
				LEFT JOIN `" . DB_PREFIX . "product_option_value` pov ON (p.product_id = pov.product_id)
				LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
				WHERE pc.value = '" . $this->db->escape($sku) . "'
				LIMIT 1");
		}

		return $query->row ?: [];
	}

	/**
	 * Update Product Price
	 *
	 * @param int $product_id
	 * @param float $price
	 * @return void
	 */
	public function updateProductPrice(int $product_id, float $price): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `price` = '" . (float)$price . "', `date_modified` = NOW() WHERE `product_id` = '" . (int)$product_id . "'");
	}

	/**
	 * Update Product Quantity
	 *
	 * @param int $product_id
	 * @param int $quantity
	 * @return void
	 */
	public function updateProductQuantity(int $product_id, int $quantity): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `quantity` = '" . (int)$quantity . "', `date_modified` = NOW() WHERE `product_id` = '" . (int)$product_id . "'");
	}

	/**
	 * Update Product Option Quantity
	 *
	 * @param int $product_option_value_id
	 * @param int $quantity
	 * @param float $price
	 * @return void
	 */
	public function updateProductOptionQuantity(int $product_option_value_id, int $quantity, float $price = 0): void {
		$sql = "UPDATE `" . DB_PREFIX . "product_option_value` SET `quantity` = '" . (int)$quantity . "'";
		if ($price > 0) {
			$sql .= ", `price` = '" . (float)$price . "'";
		}
		$sql .= " WHERE `product_option_value_id` = '" . (int)$product_option_value_id . "'";
		$this->db->query($sql);
	}

	/**
	 * Add Sync Log
	 *
	 * @param array $data
	 * @return int
	 */
	public function addSyncLog(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "pos_sync_log` SET 
			`sync_date` = NOW(),
			`status` = '" . $this->db->escape($data['status'] ?? 'running') . "',
			`total_items` = '" . (int)($data['total_items'] ?? 0) . "',
			`updated_products` = '" . (int)($data['updated_products'] ?? 0) . "',
			`updated_options` = '" . (int)($data['updated_options'] ?? 0) . "',
			`skipped_items` = '" . (int)($data['skipped_items'] ?? 0) . "',
			`error_count` = '" . (int)($data['error_count'] ?? 0) . "',
			`error_message` = '" . $this->db->escape($data['error_message'] ?? '') . "',
			`execution_time` = '" . (float)($data['execution_time'] ?? 0) . "'");

		return $this->db->getLastId();
	}

	/**
	 * Update Sync Log
	 *
	 * @param int $sync_log_id
	 * @param array $data
	 * @return void
	 */
	public function updateSyncLog(int $sync_log_id, array $data): void {
		$sql = "UPDATE `" . DB_PREFIX . "pos_sync_log` SET ";
		$updates = [];
		
		if (isset($data['status'])) {
			$updates[] = "`status` = '" . $this->db->escape($data['status']) . "'";
		}
		if (isset($data['total_items'])) {
			$updates[] = "`total_items` = '" . (int)$data['total_items'] . "'";
		}
		if (isset($data['updated_products'])) {
			$updates[] = "`updated_products` = '" . (int)$data['updated_products'] . "'";
		}
		if (isset($data['updated_options'])) {
			$updates[] = "`updated_options` = '" . (int)$data['updated_options'] . "'";
		}
		if (isset($data['skipped_items'])) {
			$updates[] = "`skipped_items` = '" . (int)$data['skipped_items'] . "'";
		}
		if (isset($data['error_count'])) {
			$updates[] = "`error_count` = '" . (int)$data['error_count'] . "'";
		}
		if (isset($data['error_message'])) {
			$updates[] = "`error_message` = '" . $this->db->escape($data['error_message']) . "'";
		}
		if (isset($data['execution_time'])) {
			$updates[] = "`execution_time` = '" . (float)$data['execution_time'] . "'";
		}
		
		if ($updates) {
			$sql .= implode(', ', $updates);
			$sql .= " WHERE `sync_log_id` = '" . (int)$sync_log_id . "'";
			$this->db->query($sql);
		}
	}

	/**
	 * Add Sync Log Detail
	 *
	 * @param int $sync_log_id
	 * @param array $data
	 * @return int
	 */
	public function addSyncLogDetail(int $sync_log_id, array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "pos_sync_log_detail` SET 
			`sync_log_id` = '" . (int)$sync_log_id . "',
			`sku` = '" . $this->db->escape($data['sku'] ?? '') . "',
			`product_name` = '" . $this->db->escape($data['product_name'] ?? '') . "',
			`action` = '" . $this->db->escape($data['action'] ?? '') . "',
			`message` = '" . $this->db->escape($data['message'] ?? '') . "',
			`old_quantity` = '" . (isset($data['old_quantity']) ? (int)$data['old_quantity'] : 'NULL') . "',
			`new_quantity` = '" . (isset($data['new_quantity']) ? (int)$data['new_quantity'] : 'NULL') . "',
			`old_price` = '" . (isset($data['old_price']) ? (float)$data['old_price'] : 'NULL') . "',
			`new_price` = '" . (isset($data['new_price']) ? (float)$data['new_price'] : 'NULL') . "',
			`log_date` = NOW()");

		return $this->db->getLastId();
	}

	/**
	 * Get Sync Logs
	 *
	 * @param array $data
	 * @return array
	 */
	public function getSyncLogs(array $data = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "pos_sync_log` WHERE 1";

		if (isset($data['filter_status'])) {
			$sql .= " AND `status` = '" . $this->db->escape($data['filter_status']) . "'";
		}

		$sql .= " ORDER BY `sync_date` DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			if (!isset($data['start']) || $data['start'] < 0) {
				$data['start'] = 0;
			}
			if (!isset($data['limit']) || $data['limit'] < 1) {
				$data['limit'] = 20;
			}
			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	/**
	 * Get Sync Log
	 *
	 * @param int $sync_log_id
	 * @return array
	 */
	public function getSyncLog(int $sync_log_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "pos_sync_log` WHERE `sync_log_id` = '" . (int)$sync_log_id . "'");
		return $query->row ?: [];
	}

	/**
	 * Get Sync Log Details
	 *
	 * @param int $sync_log_id
	 * @return array
	 */
	public function getSyncLogDetails(int $sync_log_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "pos_sync_log_detail` WHERE `sync_log_id` = '" . (int)$sync_log_id . "' ORDER BY `log_date` DESC");
		return $query->rows;
	}

	/**
	 * Get Total Sync Logs
	 *
	 * @param array $data
	 * @return int
	 */
	public function getTotalSyncLogs(array $data = []): int {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "pos_sync_log` WHERE 1";

		if (isset($data['filter_status'])) {
			$sql .= " AND `status` = '" . $this->db->escape($data['filter_status']) . "'";
		}

		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}
}

