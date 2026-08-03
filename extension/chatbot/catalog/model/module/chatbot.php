<?php
namespace Opencart\Catalog\Model\Extension\Chatbot\Module;
/**
 * Class Chatbot
 *
 * @package Opencart\Catalog\Model\Extension\Chatbot\Module
 */
class Chatbot extends \Opencart\System\Engine\Model {
	/**
	 * Build the shared LIKE match conditions for product search.
	 *
	 * @param string $like Escaped SQL LIKE value, e.g. '%keyword%'
	 * @param int    $language_id
	 *
	 * @return string
	 */
	private function buildProductSearchMatch(string $like, int $language_id): string {
		return "pd.name LIKE " . $like . "
			OR pd.description LIKE " . $like . "
			OR pd.tag LIKE " . $like . "
			OR pd.meta_title LIKE " . $like . "
			OR pd.meta_description LIKE " . $like . "
			OR pd.meta_keyword LIKE " . $like . "
			OR p.model LIKE " . $like . "
			OR p.sku LIKE " . $like . "
			OR p.upc LIKE " . $like . "
			OR p.ean LIKE " . $like . "
			OR p.jan LIKE " . $like . "
			OR p.isbn LIKE " . $like . "
			OR p.mpn LIKE " . $like . "
			OR p.location LIKE " . $like . "
			OR m.name LIKE " . $like . "
			OR EXISTS (
				SELECT 1 FROM `" . DB_PREFIX . "product_code` pc
				WHERE pc.product_id = p.product_id AND pc.value LIKE " . $like . "
			)
			OR EXISTS (
				SELECT 1 FROM `" . DB_PREFIX . "product_to_category` p2c
				LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (p2c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
				WHERE p2c.product_id = p.product_id AND cd.name LIKE " . $like . "
			)";
	}

	/**
	 * Search all matching products in the store (no result limit).
	 *
	 * @param string $keyword
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function searchProducts(string $keyword): array {
		$keyword = trim($keyword);

		if ($keyword === '') {
			return [];
		}

		$language_id = (int)$this->config->get('config_language_id');
		$escaped = $this->db->escape($keyword);
		$like = "'%" . $escaped . "%'";
		$customer_group_id = (int)$this->config->get('config_customer_group_id');

		$special = "(SELECT (CASE WHEN `ps`.`type` = 'P' THEN (`p`.`price` - (`p`.`price` * (`ps`.`price` / 100))) WHEN `ps`.`type` = 'S' THEN (`p`.`price` - `ps`.`price`) ELSE `ps`.`price` END) FROM `" . DB_PREFIX . "product_discount` `ps` WHERE `ps`.`product_id` = `p`.`product_id` AND `ps`.`customer_group_id` = '" . $customer_group_id . "' AND `ps`.`quantity` = '1' AND `ps`.`special` = '1' AND ((`ps`.`date_start` = '0000-00-00' OR `ps`.`date_start` < NOW()) AND (`ps`.`date_end` = '0000-00-00' OR `ps`.`date_end` > NOW())) ORDER BY `ps`.`priority` ASC, `ps`.`price` ASC LIMIT 1) AS `special`";

		$sql = "SELECT p.product_id, pd.name, p.image, p.price, p.tax_class_id, m.name AS manufacturer, " . $special . "
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
			LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
			LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (p.manufacturer_id = m.manufacturer_id)
			WHERE pd.language_id = '" . $language_id . "'
				AND p.status = '1'
				AND p.date_available <= NOW()
				AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
				AND (" . $this->buildProductSearchMatch($like, $language_id) . ")
			GROUP BY p.product_id
			ORDER BY pd.name ASC";

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Count products matching keyword across the store.
	 *
	 * @param string $keyword
	 *
	 * @return int
	 */
	public function getTotalSearchProducts(string $keyword): int {
		$keyword = trim($keyword);

		if ($keyword === '') {
			return 0;
		}

		$language_id = (int)$this->config->get('config_language_id');
		$escaped = $this->db->escape($keyword);
		$like = "'%" . $escaped . "%'";

		$query = $this->db->query("SELECT COUNT(DISTINCT p.product_id) AS total
			FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
			LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id)
			LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (p.manufacturer_id = m.manufacturer_id)
			WHERE pd.language_id = '" . $language_id . "'
				AND p.status = '1'
				AND p.date_available <= NOW()
				AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
				AND (" . $this->buildProductSearchMatch($like, $language_id) . ")");

		return (int)$query->row['total'];
	}

	/**
	 * Look up an order by id + email within the current store.
	 *
	 * @param int    $order_id
	 * @param string $email
	 *
	 * @return array<string, mixed>
	 */
	public function getOrder(int $order_id, string $email): array {
		$query = $this->db->query("SELECT o.order_id, o.firstname, o.lastname, o.email, o.total, o.currency_code, o.currency_value, o.date_added, o.order_status_id, os.name AS status
			FROM `" . DB_PREFIX . "order` o
			LEFT JOIN `" . DB_PREFIX . "order_status` os ON (o.order_status_id = os.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "')
			WHERE o.order_id = '" . (int)$order_id . "'
				AND LCASE(o.email) = '" . $this->db->escape(oc_strtolower($email)) . "'
				AND o.store_id = '" . (int)$this->config->get('config_store_id') . "'
			LIMIT 1");

		if (!$query->num_rows || !$query->row['order_status_id']) {
			return [];
		}

		return $query->row;
	}

	/**
	 * Save a single transcript line.
	 *
	 * @param string $session_id
	 * @param string $sender
	 * @param string $message
	 *
	 * @return void
	 */
	public function saveTranscript(string $session_id, string $sender, string $message): void {
		$this->ensureTable();

		$this->db->query("INSERT INTO `" . DB_PREFIX . "chatbot_transcript` SET
			`session_id` = '" . $this->db->escape(substr($session_id, 0, 64)) . "',
			`sender` = '" . $this->db->escape(substr($sender, 0, 16)) . "',
			`message` = '" . $this->db->escape($message) . "',
			`date_added` = NOW()");
	}

	/**
	 * Create the transcript table if it does not exist yet.
	 *
	 * @return void
	 */
	public function ensureTable(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "chatbot_transcript` (
			`transcript_id` INT(11) NOT NULL AUTO_INCREMENT,
			`session_id` VARCHAR(64) NOT NULL,
			`sender` VARCHAR(16) NOT NULL,
			`message` TEXT NOT NULL,
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`transcript_id`),
			KEY `session_id` (`session_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
	}

	/**
	 * Call OpenAI chat completions as a fallback. Returns reply text or empty string on failure.
	 *
	 * @param string $message
	 * @param string $context
	 * @param string $api_key
	 * @param string $model
	 *
	 * @return string
	 */
	public function callAi(string $message, string $context, string $api_key, string $model): string {
		if (!$api_key) {
			return '';
		}

		$payload = [
			'model'    => $model ?: 'gpt-4o-mini',
			'messages' => [
				['role' => 'system', 'content' => $context],
				['role' => 'user', 'content' => $message]
			],
			'temperature' => 0.3,
			'max_tokens'  => 300
		];

		$ch = curl_init('https://api.openai.com/v1/chat/completions');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $api_key
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);

		$response = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($status !== 200 || !$response) {
			return '';
		}

		$data = json_decode($response, true);

		return trim($data['choices'][0]['message']['content'] ?? '');
	}
}
