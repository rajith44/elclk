<?php
namespace Opencart\Catalog\Model\Extension\StockNotify\Module;
/**
 * Class StockNotify
 *
 * @package Opencart\Catalog\Model\Extension\StockNotify\Module
 */
class StockNotify extends \Opencart\System\Engine\Model {
	public const STATUS_PENDING = 0;
	public const STATUS_NOTIFIED = 1;

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
	 * @param array<string, mixed> $product
	 *
	 * @return bool
	 */
	public function isOutOfStock(array $product): bool {
		return (int)($product['quantity'] ?? 0) <= 0;
	}

	/**
	 * @param int $product_id
	 *
	 * @return int
	 */
	public function getProductQuantity(int $product_id): int {
		$query = $this->db->query("SELECT `quantity` FROM `" . DB_PREFIX . "product` WHERE `product_id` = '" . (int)$product_id . "'");

		return (int)($query->row['quantity'] ?? 0);
	}

	/**
	 * @param array<string, mixed> $data
	 *
	 * @return int
	 */
	public function addSubscription(array $data): int {
		$existing = $this->getPendingSubscription(
			(int)$data['product_id'],
			(string)$data['email'],
			(int)$data['store_id']
		);

		if ($existing) {
			return (int)$existing['stock_notify_id'];
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "stock_notify` SET
			`product_id` = '" . (int)$data['product_id'] . "',
			`store_id` = '" . (int)$data['store_id'] . "',
			`customer_id` = '" . (int)($data['customer_id'] ?? 0) . "',
			`language_id` = '" . (int)($data['language_id'] ?? 0) . "',
			`name` = '" . $this->db->escape((string)($data['name'] ?? '')) . "',
			`email` = '" . $this->db->escape((string)$data['email']) . "',
			`telephone` = '" . $this->db->escape((string)($data['telephone'] ?? '')) . "',
			`status` = '" . self::STATUS_PENDING . "',
			`date_added` = NOW()");

		return (int)$this->db->getLastId();
	}

	/**
	 * @param int    $product_id
	 * @param string $email
	 * @param int    $store_id
	 *
	 * @return array<string, mixed>
	 */
	public function getPendingSubscription(int $product_id, string $email, int $store_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "stock_notify`
			WHERE `product_id` = '" . (int)$product_id . "'
			AND `email` = '" . $this->db->escape($email) . "'
			AND `store_id` = '" . (int)$store_id . "'
			AND `status` = '" . self::STATUS_PENDING . "'
			LIMIT 1");

		return $query->row;
	}

	/**
	 * @param int $product_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPendingByProduct(int $product_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "stock_notify`
			WHERE `product_id` = '" . (int)$product_id . "'
			AND `status` = '" . self::STATUS_PENDING . "'
			ORDER BY `date_added` ASC");

		return $query->rows;
	}

	/**
	 * @param int $stock_notify_id
	 *
	 * @return void
	 */
	public function markNotified(int $stock_notify_id): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "stock_notify` SET
			`status` = '" . self::STATUS_NOTIFIED . "',
			`date_notified` = NOW()
			WHERE `stock_notify_id` = '" . (int)$stock_notify_id . "'");
	}

	/**
	 * @param int $product_id
	 *
	 * @return int
	 */
	public function notifyProductSubscribers(int $product_id): int {
		if (!$this->config->get('module_stock_notify_status')) {
			return 0;
		}

		if ($this->getProductQuantity($product_id) <= 0) {
			return 0;
		}

		$subscriptions = $this->getPendingByProduct($product_id);

		if (!$subscriptions) {
			return 0;
		}

		$sent = 0;

		foreach ($subscriptions as $subscription) {
			if ($this->sendNotification($subscription)) {
				$this->markNotified((int)$subscription['stock_notify_id']);
				$sent++;
			}
		}

		return $sent;
	}

	/**
	 * @param array<string, mixed> $subscription
	 *
	 * @return bool
	 */
	public function sendNotification(array $subscription): bool {
		if (!$this->config->get('config_mail_engine')) {
			return false;
		}

		$product_id = (int)$subscription['product_id'];
		$language_id = (int)$subscription['language_id'];

		if ($language_id < 1) {
			$language_id = (int)$this->config->get('config_language_id');
		}

		$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "product_description`
			WHERE `product_id` = '" . $product_id . "'
			AND `language_id` = '" . $language_id . "'
			LIMIT 1");

		if (!$query->num_rows) {
			$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "product_description`
				WHERE `product_id` = '" . $product_id . "'
				LIMIT 1");
		}

		if (!$query->num_rows) {
			return false;
		}

		$product_name = $query->row['name'];
		$store_id = (int)$subscription['store_id'];

		$this->load->model('setting/store');

		$store_url = $this->config->get('config_url');

		if ($store_id) {
			$store = $this->model_setting_store->getStore($store_id);

			if ($store) {
				$store_url = $store['url'];
			}
		}

		$product_url = rtrim($store_url, '/') . '/index.php?route=product/product&product_id=' . $product_id;

		$customer_name = trim((string)$subscription['name']);

		if ($customer_name === '') {
			$customer_name = 'Customer';
		}

		$subject = (string)$this->config->get('module_stock_notify_email_subject');
		$message = (string)$this->config->get('module_stock_notify_email_body');

		if ($subject === '') {
			$subject = '{product_name} is back in stock';
		}

		if ($message === '') {
			$message = "Hi {customer_name},\n\nGood news! {product_name} is back in stock.\n\nShop now: {product_url}\n\nThank you,\n{store_name}";
		}

		$replace = [
			'{customer_name}' => $customer_name,
			'{product_name}'  => $product_name,
			'{product_url}'   => $product_url,
			'{store_name}'    => (string)$this->config->get('config_name')
		];

		$subject = str_replace(array_keys($replace), array_values($replace), $subject);
		$message = str_replace(array_keys($replace), array_values($replace), $message);

		$mail_option = [
			'parameter'     => $this->config->get('config_mail_parameter'),
			'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
			'smtp_username' => $this->config->get('config_mail_smtp_username'),
			'smtp_password' => html_entity_decode((string)$this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
			'smtp_port'     => $this->config->get('config_mail_smtp_port'),
			'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
		];

		try {
			$mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
			$mail->setTo($subscription['email']);
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender(html_entity_decode((string)$this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
			$mail->setText($message);
			$mail->send();

			return true;
		} catch (\Exception $e) {
			$this->log->write('Stock Notify mail error: ' . $e->getMessage());

			return false;
		}
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

		if (!empty($filters['filter_email'])) {
			$sql .= " AND sn.`email` LIKE '" . $this->db->escape('%' . $filters['filter_email'] . '%') . "'";
		}

		if (!empty($filters['filter_product'])) {
			$sql .= " AND pd.`name` LIKE '" . $this->db->escape('%' . $filters['filter_product'] . '%') . "'";
		}

		$sql .= " ORDER BY sn.`date_added` DESC";

		if (isset($filters['start']) || isset($filters['limit'])) {
			$start = max(0, (int)($filters['start'] ?? 0));
			$limit = max(1, (int)($filters['limit'] ?? 20));

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * @param array<string, mixed> $filters
	 *
	 * @return int
	 */
	public function getTotalSubscriptions(array $filters = []): int {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "stock_notify` sn
			LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (sn.`product_id` = pd.`product_id` AND pd.`language_id` = '" . (int)$this->config->get('config_language_id') . "')
			WHERE 1 = 1";

		if (isset($filters['filter_status']) && $filters['filter_status'] !== '') {
			$sql .= " AND sn.`status` = '" . (int)$filters['filter_status'] . "'";
		}

		if (!empty($filters['filter_email'])) {
			$sql .= " AND sn.`email` LIKE '" . $this->db->escape('%' . $filters['filter_email'] . '%') . "'";
		}

		if (!empty($filters['filter_product'])) {
			$sql .= " AND pd.`name` LIKE '" . $this->db->escape('%' . $filters['filter_product'] . '%') . "'";
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * @param int $stock_notify_id
	 *
	 * @return void
	 */
	public function deleteSubscription(int $stock_notify_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "stock_notify` WHERE `stock_notify_id` = '" . (int)$stock_notify_id . "'");
	}

	/**
	 * @param string $html
	 * @param string $button
	 *
	 * @return string
	 */
	public function injectAfterCompare(string $html, string $button): string {
		if ($button === '' || strpos($html, 'sn-card-notify') !== false) {
			return $html;
		}

		if (preg_match('#(<a class="btn btn-compare"[^>]*>.*?</a>)#s', $html, $match)) {
			return str_replace($match[1], $match[1] . $button, $html);
		}

		if (preg_match('#(<div class="wish-group">.*?)(</div>)#s', $html, $match)) {
			return str_replace($match[0], $match[1] . $button . $match[2], $html);
		}

		if (preg_match('#(<div class="button-group">.*?)(</div>\s*(?:</div>\s*){0,2}$)#s', $html, $match)) {
			return str_replace($match[0], $match[1] . $button . $match[2], $html);
		}

		return $html;
	}

	/**
	 * @param string   $html
	 * @param callable $button_renderer
	 *
	 * @return string
	 */
	public function injectCardButtonsHtml(string $html, callable $button_renderer): string {
		if (strpos($html, 'out-of-stock') === false) {
			return $html;
		}

		$offset = 0;

		while (($pos = strpos($html, 'btn-compare', $offset)) !== false) {
			$layout_pos = strrpos(substr($html, 0, $pos), 'product-layout');

			if ($layout_pos === false) {
				$offset = $pos + 12;
				continue;
			}

			$layout_tag = substr($html, $layout_pos, min(300, $pos - $layout_pos + 50));

			if (strpos($layout_tag, 'out-of-stock') === false) {
				$offset = $pos + 12;
				continue;
			}

			$compare_start = strrpos(substr($html, 0, $pos), '<a class="btn btn-compare"');

			if ($compare_start === false) {
				$offset = $pos + 12;
				continue;
			}

			$compare_end = strpos($html, '</a>', $pos);

			if ($compare_end === false) {
				$offset = $pos + 12;
				continue;
			}

			$compare_end += 4;
			$compare_html = substr($html, $compare_start, $compare_end - $compare_start);
			$check_area = substr($html, $compare_start, min(200, strlen($html) - $compare_start));

			if (strpos($check_area, 'sn-card-notify') !== false) {
				$offset = $compare_end;
				continue;
			}

			$product_id = 0;

			if (preg_match("/compare\\.add\\(['\"]?(\\d+)['\"]?\\)/", $compare_html, $id_match)) {
				$product_id = (int)$id_match[1];
			}

			if ($product_id < 1) {
				$offset = $compare_end;
				continue;
			}

			$next_layout = strpos($html, 'class="product-layout', $layout_pos + 15);
			$block_html = substr($html, $layout_pos, ($next_layout ?: strlen($html)) - $layout_pos);
			$product_name = '';

			if (preg_match('#class="name"><a[^>]*>(.*?)</a>#s', $block_html, $name_match)) {
				$product_name = trim(html_entity_decode(strip_tags($name_match[1]), ENT_QUOTES, 'UTF-8'));
			}

			$button = (string)$button_renderer($product_id, $product_name);

			if ($button === '') {
				$offset = $compare_end;
				continue;
			}

			$html = substr_replace($html, $compare_html . $button, $compare_start, $compare_end - $compare_start);
			$offset = $compare_start + strlen($compare_html . $button);
		}

		return $html;
	}
}
