<?php
namespace Opencart\Admin\Model\Extension\StockNotify\Module;
/**
 * Class StockNotify
 *
 * @package Opencart\Admin\Model\Extension\StockNotify\Module
 */
class StockNotify extends \Opencart\System\Engine\Model {
	/**
	 * @return void
	 */
	public function install(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "stock_notify` (
			`stock_notify_id` INT(11) NOT NULL AUTO_INCREMENT,
			`product_id` INT(11) NOT NULL,
			`store_id` INT(11) NOT NULL DEFAULT 0,
			`customer_id` INT(11) NOT NULL DEFAULT 0,
			`language_id` INT(11) NOT NULL DEFAULT 0,
			`name` VARCHAR(64) NOT NULL DEFAULT '',
			`email` VARCHAR(96) NOT NULL,
			`telephone` VARCHAR(32) NOT NULL DEFAULT '',
			`status` TINYINT(1) NOT NULL DEFAULT 0,
			`date_added` DATETIME NOT NULL,
			`date_notified` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`stock_notify_id`),
			KEY `product_id` (`product_id`),
			KEY `email` (`email`),
			KEY `status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
	}

	/**
	 * @param array<string, mixed> $filters
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getSubscriptions(array $filters = []): array {
		$sql = "SELECT sn.*, pd.`name` AS product_name FROM `" . DB_PREFIX . "stock_notify` sn
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (sn.`product_id` = pd.`product_id` AND pd.`language_id` = '" . (int)$this->config->get('config_language_id') . "')
			WHERE 1 = 1";

		if (isset($filters['filter_status']) && $filters['filter_status'] !== '') {
			$sql .= " AND sn.`status` = '" . (int)$filters['filter_status'] . "'";
		}

		$sql .= " ORDER BY sn.`date_added` DESC";

		if (isset($filters['limit'])) {
			$start = max(0, (int)($filters['start'] ?? 0));
			$limit = max(1, (int)$filters['limit']);

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * @param int $stock_notify_id
	 *
	 * @return void
	 */
	public function deleteSubscription(int $stock_notify_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "stock_notify` WHERE `stock_notify_id` = '" . (int)$stock_notify_id . "'");
	}
}
