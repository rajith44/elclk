<?php
namespace Opencart\Admin\Model\Extension\NextDayTrishawDeliveryRate\Shipping;

/**
 * Class NextDayTrishawDeliveryRate
 *
 * Handles persistence for the Next Day Trishaw Delivery Rate shipping module.
 */
class NextDayTrishawDeliveryRate extends \Opencart\System\Engine\Model {
	private const TABLE_NAME = DB_PREFIX . 'next_day_trishaw_rates';

	/**
	 * Return the canonical list of provinces (districts) used by the module.
	 *
	 * @return array<int, string>
	 */
	public function getProvinces(): array {
		return [
			'Ampara',
			'Anuradhapura',
			'Badulla',
			'Batticaloa',
			'Colombo',
			'Galle',
			'Gampaha',
			'Hambantota',
			'Jaffna',
			'Kalutara',
			'Kandy',
			'Kegalle',
			'Kilinochchi',
			'Kurunegala',
			'Mannar',
			'Matale',
			'Matara',
			'Monaragala',
			'Mullaitivu',
			'Nuwara Eliya',
			'Polonnaruwa',
			'Puttalam',
			'Ratnapura',
			'Trincomalee',
			'Vavuniya'
		];
	}

	/**
	 * Fetch city rate rows for a given province.
	 *
	 * @param string $province
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getCitiesByProvince(string $province): array {
		$query = $this->db->query("SELECT * FROM `" . self::TABLE_NAME . "` WHERE `province` = '" . $this->db->escape($province) . "' ORDER BY `city` ASC");

		return $query->rows;
	}

	/**
	 * Insert or update a city rate record.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return void
	 */
	public function saveCityRate(array $data): void {
		$province = trim((string)$data['province']);
		$city = trim((string)$data['city']);
		$city_rate = number_format((float)$data['city_rate'], 2, '.', '');
		$primary_weight_rate = number_format((float)$data['primary_weight_rate'], 2, '.', '');
		$additional_weight_rate = number_format((float)$data['additional_weight_rate'], 2, '.', '');
		$status = (int)$data['status'] ? 1 : 0;

		$province_key = $this->db->escape($this->normalizeKey($province));
		$city_key = $this->db->escape($this->normalizeKey($city));

		$exists = $this->db->query("SELECT `id` FROM `" . self::TABLE_NAME . "` WHERE LCASE(`province`) = '" . $province_key . "' AND LCASE(`city`) = '" . $city_key . "'");

		if ($exists->num_rows) {
			$id = (int)$exists->row['id'];
			$this->db->query("UPDATE `" . self::TABLE_NAME . "` SET `province` = '" . $this->db->escape($province) . "', `city` = '" . $this->db->escape($city) . "', `city_rate` = '" . $city_rate . "', `primary_weight_rate` = '" . $primary_weight_rate . "', `additional_weight_rate` = '" . $additional_weight_rate . "', `status` = '" . $status . "' WHERE `id` = '" . $id . "'");
		} else {
			$this->db->query("INSERT INTO `" . self::TABLE_NAME . "` SET `province` = '" . $this->db->escape($province) . "', `city` = '" . $this->db->escape($city) . "', `city_rate` = '" . $city_rate . "', `primary_weight_rate` = '" . $primary_weight_rate . "', `additional_weight_rate` = '" . $additional_weight_rate . "', `status` = '" . $status . "'");
		}
	}

	/**
	 * Run install tasks (create table and seed default data).
	 *
	 * @return void
	 */
	public function install(): void {
		$this->createTable();
		$this->seedDefaults();
	}

	/**
	 * Drop the custom data table during uninstall.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->db->query("DROP TABLE IF EXISTS `" . self::TABLE_NAME . "`");
	}

	/**
	 * Create the custom rate table if it does not exist.
	 *
	 * @return void
	 */
	private function createTable(): void {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . self::TABLE_NAME . "` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`province` VARCHAR(100) NOT NULL,
				`city` VARCHAR(100) NOT NULL,
				`city_rate` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
				`primary_weight_rate` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
				`additional_weight_rate` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
				`status` TINYINT(1) NOT NULL DEFAULT '1',
				PRIMARY KEY (`id`),
				UNIQUE KEY `province_city` (`province`, `city`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
		");
	}

	/**
	 * Seed a baseline dataset for every province.
	 *
	 * @return void
	 */
	private function seedDefaults(): void {
		$existing = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . self::TABLE_NAME . "`");

		if ((int)$existing->row['total'] > 0) {
			return;
		}

		foreach ($this->getSeedData() as $province => $cities) {
			foreach ($cities as $city) {
				$this->db->query("INSERT INTO `" . self::TABLE_NAME . "` SET `province` = '" . $this->db->escape($province) . "', `city` = '" . $this->db->escape($city['city']) . "', `city_rate` = '" . (float)$city['city_rate'] . "', `primary_weight_rate` = '" . (float)$city['primary_weight_rate'] . "', `additional_weight_rate` = '" . (float)$city['additional_weight_rate'] . "', `status` = '" . (int)$city['status'] . "'");
			}
		}
	}

	/**
	 * Default dataset used during installation.
	 *
	 * @return array<string, array<int, array<string, float|int|string>>>
	 */
	private function getSeedData(): array {
		return [
			'Ampara' => [
				[
					'city' => 'Ampara Town',
					'city_rate' => 350.00,
					'primary_weight_rate' => 250.00,
					'additional_weight_rate' => 150.00,
					'status' => 1
				],
				[
					'city' => 'Kalmunai',
					'city_rate' => 360.00,
					'primary_weight_rate' => 260.00,
					'additional_weight_rate' => 150.00,
					'status' => 1
				]
			],
			'Anuradhapura' => [
				[
					'city' => 'Anuradhapura Town',
					'city_rate' => 340.00,
					'primary_weight_rate' => 240.00,
					'additional_weight_rate' => 140.00,
					'status' => 1
				],
				[
					'city' => 'Medawachchiya',
					'city_rate' => 330.00,
					'primary_weight_rate' => 230.00,
					'additional_weight_rate' => 140.00,
					'status' => 1
				]
			],
			'Badulla' => [
				[
					'city' => 'Badulla Town',
					'city_rate' => 360.00,
					'primary_weight_rate' => 260.00,
					'additional_weight_rate' => 150.00,
					'status' => 1
				],
				[
					'city' => 'Bandarawela',
					'city_rate' => 365.00,
					'primary_weight_rate' => 265.00,
					'additional_weight_rate' => 155.00,
					'status' => 1
				]
			],
			'Batticaloa' => [
				[
					'city' => 'Batticaloa Town',
					'city_rate' => 355.00,
					'primary_weight_rate' => 255.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				],
				[
					'city' => 'Eravur',
					'city_rate' => 345.00,
					'primary_weight_rate' => 245.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				]
			],
			'Colombo' => [
				[
					'city' => 'Colombo 01',
					'city_rate' => 300.00,
					'primary_weight_rate' => 200.00,
					'additional_weight_rate' => 120.00,
					'status' => 1
				],
				[
					'city' => 'Dehiwala',
					'city_rate' => 305.00,
					'primary_weight_rate' => 205.00,
					'additional_weight_rate' => 120.00,
					'status' => 1
				]
			],
			'Galle' => [
				[
					'city' => 'Galle Fort',
					'city_rate' => 320.00,
					'primary_weight_rate' => 210.00,
					'additional_weight_rate' => 130.00,
					'status' => 1
				],
				[
					'city' => 'Hikkaduwa',
					'city_rate' => 325.00,
					'primary_weight_rate' => 215.00,
					'additional_weight_rate' => 135.00,
					'status' => 1
				]
			],
			'Gampaha' => [
				[
					'city' => 'Gampaha Town',
					'city_rate' => 310.00,
					'primary_weight_rate' => 210.00,
					'additional_weight_rate' => 125.00,
					'status' => 1
				],
				[
					'city' => 'Negombo',
					'city_rate' => 315.00,
					'primary_weight_rate' => 215.00,
					'additional_weight_rate' => 125.00,
					'status' => 1
				]
			],
			'Hambantota' => [
				[
					'city' => 'Hambantota Town',
					'city_rate' => 340.00,
					'primary_weight_rate' => 240.00,
					'additional_weight_rate' => 140.00,
					'status' => 1
				],
				[
					'city' => 'Tangalle',
					'city_rate' => 345.00,
					'primary_weight_rate' => 245.00,
					'additional_weight_rate' => 140.00,
					'status' => 1
				]
			],
			'Jaffna' => [
				[
					'city' => 'Jaffna Town',
					'city_rate' => 370.00,
					'primary_weight_rate' => 270.00,
					'additional_weight_rate' => 160.00,
					'status' => 1
				],
				[
					'city' => 'Chavakachcheri',
					'city_rate' => 365.00,
					'primary_weight_rate' => 265.00,
					'additional_weight_rate' => 160.00,
					'status' => 1
				]
			],
			'Kalutara' => [
				[
					'city' => 'Kalutara Town',
					'city_rate' => 315.00,
					'primary_weight_rate' => 215.00,
					'additional_weight_rate' => 125.00,
					'status' => 1
				],
				[
					'city' => 'Beruwala',
					'city_rate' => 320.00,
					'primary_weight_rate' => 220.00,
					'additional_weight_rate' => 130.00,
					'status' => 1
				]
			],
			'Kandy' => [
				[
					'city' => 'Kandy City',
					'city_rate' => 335.00,
					'primary_weight_rate' => 235.00,
					'additional_weight_rate' => 135.00,
					'status' => 1
				],
				[
					'city' => 'Peradeniya',
					'city_rate' => 330.00,
					'primary_weight_rate' => 230.00,
					'additional_weight_rate' => 135.00,
					'status' => 1
				]
			],
			'Kegalle' => [
				[
					'city' => 'Kegalle Town',
					'city_rate' => 325.00,
					'primary_weight_rate' => 225.00,
					'additional_weight_rate' => 130.00,
					'status' => 1
				],
				[
					'city' => 'Mawanella',
					'city_rate' => 320.00,
					'primary_weight_rate' => 220.00,
					'additional_weight_rate' => 130.00,
					'status' => 1
				]
			],
			'Kilinochchi' => [
				[
					'city' => 'Kilinochchi Town',
					'city_rate' => 365.00,
					'primary_weight_rate' => 265.00,
					'additional_weight_rate' => 155.00,
					'status' => 1
				],
				[
					'city' => 'Pallai',
					'city_rate' => 360.00,
					'primary_weight_rate' => 260.00,
					'additional_weight_rate' => 155.00,
					'status' => 1
				]
			],
			'Kurunegala' => [
				[
					'city' => 'Kurunegala Town',
					'city_rate' => 320.00,
					'primary_weight_rate' => 220.00,
					'additional_weight_rate' => 130.00,
					'status' => 1
				],
				[
					'city' => 'Kuliyapitiya',
					'city_rate' => 325.00,
					'primary_weight_rate' => 225.00,
					'additional_weight_rate' => 130.00,
					'status' => 1
				]
			],
			'Mannar' => [
				[
					'city' => 'Mannar Town',
					'city_rate' => 355.00,
					'primary_weight_rate' => 255.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				],
				[
					'city' => 'Pesalai',
					'city_rate' => 350.00,
					'primary_weight_rate' => 250.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				]
			],
			'Matale' => [
				[
					'city' => 'Matale Town',
					'city_rate' => 330.00,
					'primary_weight_rate' => 230.00,
					'additional_weight_rate' => 135.00,
					'status' => 1
				],
				[
					'city' => 'Dambulla',
					'city_rate' => 335.00,
					'primary_weight_rate' => 235.00,
					'additional_weight_rate' => 135.00,
					'status' => 1
				]
			],
			'Matara' => [
				[
					'city' => 'Matara Town',
					'city_rate' => 330.00,
					'primary_weight_rate' => 230.00,
					'additional_weight_rate' => 135.00,
					'status' => 1
				],
				[
					'city' => 'Weligama',
					'city_rate' => 335.00,
					'primary_weight_rate' => 235.00,
					'additional_weight_rate' => 135.00,
					'status' => 1
				]
			],
			'Monaragala' => [
				[
					'city' => 'Monaragala Town',
					'city_rate' => 345.00,
					'primary_weight_rate' => 245.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				],
				[
					'city' => 'Wellawaya',
					'city_rate' => 340.00,
					'primary_weight_rate' => 240.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				]
			],
			'Mullaitivu' => [
				[
					'city' => 'Mullaitivu Town',
					'city_rate' => 360.00,
					'primary_weight_rate' => 260.00,
					'additional_weight_rate' => 150.00,
					'status' => 1
				],
				[
					'city' => 'Puthukkudiyiruppu',
					'city_rate' => 355.00,
					'primary_weight_rate' => 255.00,
					'additional_weight_rate' => 150.00,
					'status' => 1
				]
			],
			'Nuwara Eliya' => [
				[
					'city' => 'Nuwara Eliya Town',
					'city_rate' => 340.00,
					'primary_weight_rate' => 240.00,
					'additional_weight_rate' => 140.00,
					'status' => 1
				],
				[
					'city' => 'Hatton',
					'city_rate' => 335.00,
					'primary_weight_rate' => 235.00,
					'additional_weight_rate' => 140.00,
					'status' => 1
				]
			],
			'Polonnaruwa' => [
				[
					'city' => 'Polonnaruwa Town',
					'city_rate' => 335.00,
					'primary_weight_rate' => 235.00,
					'additional_weight_rate' => 140.00,
					'status' => 1
				],
				[
					'city' => 'Kaduruwela',
					'city_rate' => 330.00,
					'primary_weight_rate' => 230.00,
					'additional_weight_rate' => 140.00,
					'status' => 1
				]
			],
			'Puttalam' => [
				[
					'city' => 'Puttalam Town',
					'city_rate' => 320.00,
					'primary_weight_rate' => 220.00,
					'additional_weight_rate' => 130.00,
					'status' => 1
				],
				[
					'city' => 'Chilaw',
					'city_rate' => 325.00,
					'primary_weight_rate' => 225.00,
					'additional_weight_rate' => 130.00,
					'status' => 1
				]
			],
			'Ratnapura' => [
				[
					'city' => 'Ratnapura Town',
					'city_rate' => 325.00,
					'primary_weight_rate' => 225.00,
					'additional_weight_rate' => 135.00,
					'status' => 1
				],
				[
					'city' => 'Embilipitiya',
					'city_rate' => 330.00,
					'primary_weight_rate' => 230.00,
					'additional_weight_rate' => 135.00,
					'status' => 1
				]
			],
			'Trincomalee' => [
				[
					'city' => 'Trincomalee Town',
					'city_rate' => 350.00,
					'primary_weight_rate' => 250.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				],
				[
					'city' => 'Kinniya',
					'city_rate' => 345.00,
					'primary_weight_rate' => 245.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				]
			],
			'Vavuniya' => [
				[
					'city' => 'Vavuniya Town',
					'city_rate' => 350.00,
					'primary_weight_rate' => 250.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				],
				[
					'city' => 'Nedunkeni',
					'city_rate' => 345.00,
					'primary_weight_rate' => 245.00,
					'additional_weight_rate' => 145.00,
					'status' => 1
				]
			]
		];
	}

	/**
	 * Normalise a string for case-insensitive comparison.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	private function normalizeKey(string $value): string {
		return strtolower(trim($value));
	}
}

