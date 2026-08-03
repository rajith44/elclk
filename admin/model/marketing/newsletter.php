<?php
namespace Opencart\Admin\Model\Marketing;

class Newsletter extends \Opencart\System\Engine\Model {
	/**
	 * Add Campaign
	 *
	 * @param array $data
	 * @return int
	 */
	public function addCampaign(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "newsletter_campaign` SET 
			`name` = '" . $this->db->escape($data['name']) . "',
			`send_to_type` = '" . $this->db->escape($data['send_to_type'] ?? 'subscribers') . "',
			`customer_group_ids` = '" . $this->db->escape($data['customer_group_ids'] ?? '') . "',
			`custom_emails` = '" . $this->db->escape($data['custom_emails'] ?? '') . "',
			`subject` = '" . $this->db->escape($data['subject']) . "',
			`message` = '" . $this->db->escape($data['message']) . "',
			`product_ids` = '" . $this->db->escape($data['product_ids']) . "',
			`status` = '" . (int)$data['status'] . "',
			`date_created` = NOW()");

		return $this->db->getLastId();
	}

	/**
	 * Edit Campaign
	 *
	 * @param int $campaign_id
	 * @param array $data
	 * @return void
	 */
	public function editCampaign(int $campaign_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "newsletter_campaign` SET 
			`name` = '" . $this->db->escape($data['name']) . "',
			`send_to_type` = '" . $this->db->escape($data['send_to_type'] ?? 'subscribers') . "',
			`customer_group_ids` = '" . $this->db->escape($data['customer_group_ids'] ?? '') . "',
			`custom_emails` = '" . $this->db->escape($data['custom_emails'] ?? '') . "',
			`subject` = '" . $this->db->escape($data['subject']) . "',
			`message` = '" . $this->db->escape($data['message']) . "',
			`product_ids` = '" . $this->db->escape($data['product_ids']) . "',
			`status` = '" . (int)$data['status'] . "'
			WHERE `campaign_id` = '" . (int)$campaign_id . "'");
	}

	/**
	 * Get Campaign
	 *
	 * @param int $campaign_id
	 * @return array
	 */
	public function getCampaign(int $campaign_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "newsletter_campaign` WHERE `campaign_id` = '" . (int)$campaign_id . "'");

		return $query->row;
	}

	/**
	 * Get Campaigns
	 *
	 * @return array
	 */
	public function getCampaigns(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "newsletter_campaign` ORDER BY `date_created` DESC");

		return $query->rows;
	}

	/**
	 * Delete Campaign
	 *
	 * @param int $campaign_id
	 * @return void
	 */
	public function deleteCampaign(int $campaign_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "newsletter_campaign` WHERE `campaign_id` = '" . (int)$campaign_id . "'");
	}

	/**
	 * Update Campaign Stats
	 *
	 * @param int $campaign_id
	 * @param int $total_sent
	 * @param int $total_failed
	 * @return void
	 */
	public function updateCampaignStats(int $campaign_id, int $total_sent, int $total_failed): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "newsletter_campaign` SET 
			`total_sent` = `total_sent` + '" . (int)$total_sent . "',
			`total_failed` = `total_failed` + '" . (int)$total_failed . "',
			`date_sent` = NOW()
			WHERE `campaign_id` = '" . (int)$campaign_id . "'");
	}

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
	 * Get Subscribers
	 *
	 * @param array $data
	 * @return array
	 */
	public function getSubscribers(array $data = []): array {
		$sql = "SELECT * FROM `" . DB_PREFIX . "newsletter_subscriber` WHERE 1";

		if (isset($data['status'])) {
			$sql .= " AND `status` = '" . (int)$data['status'] . "'";
		}

		$sql .= " ORDER BY `date_added` DESC";

		$query = $this->db->query($sql);

		return $query->rows;
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
}

