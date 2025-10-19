<?php
namespace Opencart\Admin\Model\Marketing;
/**
 * Class Newsletter
 *
 * @package Opencart\Admin\Model\Marketing
 */
class Newsletter extends \Opencart\System\Engine\Model {
	/**
	 * Add Newsletter
	 *
	 * @param array $data
	 *
	 * @return int
	 */
	public function addNewsletter(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "newsletter` SET 
			`name` = '" . $this->db->escape($data['name']) . "', 
			`subject` = '" . $this->db->escape($data['subject']) . "', 
			`message` = '" . $this->db->escape($data['message']) . "', 
			`to_type` = '" . $this->db->escape($data['to']) . "', 
			`store_id` = '" . (int)$data['store_id'] . "', 
			`customer_group_id` = '" . (int)$data['customer_group_id'] . "', 
			`status` = '" . $this->db->escape($data['status']) . "', 
			`date_added` = NOW()");

		$newsletter_id = $this->db->getLastId();

		// Add products to newsletter
		if (isset($data['product']) && is_array($data['product'])) {
			foreach ($data['product'] as $product_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "newsletter_product` SET 
					`newsletter_id` = '" . (int)$newsletter_id . "', 
					`product_id` = '" . (int)$product_id . "'");
			}
		}

		return $newsletter_id;
	}

	/**
	 * Edit Newsletter
	 *
	 * @param int   $newsletter_id
	 * @param array $data
	 *
	 * @return void
	 */
	public function editNewsletter(int $newsletter_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "newsletter` SET 
			`name` = '" . $this->db->escape($data['name']) . "', 
			`subject` = '" . $this->db->escape($data['subject']) . "', 
			`message` = '" . $this->db->escape($data['message']) . "', 
			`to_type` = '" . $this->db->escape($data['to']) . "', 
			`store_id` = '" . (int)$data['store_id'] . "', 
			`customer_group_id` = '" . (int)$data['customer_group_id'] . "', 
			`status` = '" . $this->db->escape($data['status']) . "' 
			WHERE `newsletter_id` = '" . (int)$newsletter_id . "'");

		// Delete old products
		$this->db->query("DELETE FROM `" . DB_PREFIX . "newsletter_product` WHERE `newsletter_id` = '" . (int)$newsletter_id . "'");

		// Add products to newsletter
		if (isset($data['product']) && is_array($data['product'])) {
			foreach ($data['product'] as $product_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "newsletter_product` SET 
					`newsletter_id` = '" . (int)$newsletter_id . "', 
					`product_id` = '" . (int)$product_id . "'");
			}
		}
	}

	/**
	 * Delete Newsletter
	 *
	 * @param int $newsletter_id
	 *
	 * @return void
	 */
	public function deleteNewsletter(int $newsletter_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "newsletter` WHERE `newsletter_id` = '" . (int)$newsletter_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "newsletter_product` WHERE `newsletter_id` = '" . (int)$newsletter_id . "'");
	}

	/**
	 * Get Newsletter
	 *
	 * @param int $newsletter_id
	 *
	 * @return array
	 */
	public function getNewsletter(int $newsletter_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "newsletter` WHERE `newsletter_id` = '" . (int)$newsletter_id . "'");

		return $query->row;
	}

	/**
	 * Get Newsletters
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function getNewsletters(array $data = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "newsletter`";

		$sort_data = [
			'name',
			'subject',
			'date_added',
			'status'
		];

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY date_added";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Get Newsletter Products
	 *
	 * @param int $newsletter_id
	 *
	 * @return array
	 */
	public function getNewsletterProducts(int $newsletter_id): array {
		$query = $this->db->query("SELECT np.*, pd.name FROM `" . DB_PREFIX . "newsletter_product` np 
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (np.product_id = pd.product_id) 
			WHERE np.newsletter_id = '" . (int)$newsletter_id . "' 
			AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->rows;
	}

	/**
	 * Get Total Newsletters
	 *
	 * @return int
	 */
	public function getTotalNewsletters(): int {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "newsletter`");

		return (int)$query->row['total'];
	}

	/**
	 * Update Status
	 *
	 * @param int    $newsletter_id
	 * @param string $status
	 *
	 * @return void
	 */
	public function updateStatus(int $newsletter_id, string $status): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "newsletter` SET 
			`status` = '" . $this->db->escape($status) . "', 
			`date_sent` = NOW() 
			WHERE `newsletter_id` = '" . (int)$newsletter_id . "'");
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
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

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "newsletter_product` (
				`newsletter_id` int(11) NOT NULL,
				`product_id` int(11) NOT NULL,
				PRIMARY KEY (`newsletter_id`, `product_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
		");
	}
}

