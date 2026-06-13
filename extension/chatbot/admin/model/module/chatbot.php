<?php
namespace Opencart\Admin\Model\Extension\Chatbot\Module;
/**
 * Class Chatbot
 *
 * @package Opencart\Admin\Model\Extension\Chatbot\Module
 */
class Chatbot extends \Opencart\System\Engine\Model {
	/**
	 * Install - create the transcript table
	 *
	 * @return void
	 */
	public function install(): void {
		$this->ensureTable();
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
	 * Get transcripts
	 *
	 * @param int $start
	 * @param int $limit
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getTranscripts(int $start = 0, int $limit = 50): array {
		$this->ensureTable();

		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 50;
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "chatbot_transcript` ORDER BY `transcript_id` DESC LIMIT " . (int)$start . ", " . (int)$limit);

		return $query->rows;
	}

	/**
	 * Clear all transcripts
	 *
	 * @return void
	 */
	public function clearTranscripts(): void {
		$this->ensureTable();

		$this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "chatbot_transcript`");
	}
}
