<?php
namespace Opencart\Catalog\Model\Marketing;

class Newsletter extends \Opencart\System\Engine\Model {
	/**
	 * Add Subscriber
	 *
	 * @param array $data
	 * @return int
	 */
	public function addSubscriber(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "newsletter_subscriber` SET 
			`email` = '" . $this->db->escape($data['email']) . "',
			`name` = '" . $this->db->escape($data['name'] ?? '') . "',
			`status` = '" . (int)($data['status'] ?? 1) . "',
			`date_added` = NOW(),
			`date_modified` = NOW()
			ON DUPLICATE KEY UPDATE 
			`name` = '" . $this->db->escape($data['name'] ?? '') . "',
			`status` = '" . (int)($data['status'] ?? 1) . "',
			`date_modified` = NOW()");

		return $this->db->getLastId();
	}

	/**
	 * Get Subscriber by Email
	 *
	 * @param string $email
	 * @return array
	 */
	public function getSubscriberByEmail(string $email): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "newsletter_subscriber` WHERE `email` = '" . $this->db->escape($email) . "'");

		return $query->row;
	}

	/**
	 * Delete Subscriber
	 *
	 * @param int $subscriber_id
	 * @return void
	 */
	public function deleteSubscriber(int $subscriber_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "newsletter_subscriber` WHERE `subscriber_id` = '" . (int)$subscriber_id . "'");
	}
}

