<?php
namespace Opencart\Catalog\Model\Extension\NextDayTrishawDeliveryRate\Shipping;

/**
 * Class NextDayTrishawDeliveryRate
 *
 * Frontend quote provider for Next Day Trishaw Delivery Rate.
 */
class NextDayTrishawDeliveryRate extends \Opencart\System\Engine\Model {
	private const TABLE_NAME = DB_PREFIX . 'next_day_trishaw_rates';

	/**
	 * Return the shipping quote for the provided address.
	 *
	 * @param array<string, mixed> $address
	 *
	 * @return array<string, mixed>
	 */
	public function getQuote(array $address): array {
		$this->load->language('extension/next_day_trishaw_delivery_rate/shipping/next_day_trishaw_delivery_rate');

		if (!$this->config->get('shipping_next_day_trishaw_delivery_rate_status')) {
			return [];
		}

		$method_title = $this->config->get('shipping_next_day_trishaw_delivery_rate_title') ?: $this->language->get('text_title');
		$sort_order = (int)$this->config->get('shipping_next_day_trishaw_delivery_rate_sort_order');
		$tax_class_id = (int)$this->config->get('shipping_next_day_trishaw_delivery_rate_tax_class_id');

		$province = $this->resolveProvince($address);
		$city = $this->resolveCity($address);

		if (!$city) {
			return [];
		}

		$rate_row = null;

		if ($province) {
			$rate_row = $this->getCityRate($province, $city);

			if (!$rate_row) {
				$rate_row = $this->getFallbackProvinceRate($province);
			}
		}

		if (!$rate_row) {
			$rate_row = $this->getCityRateByCityName($city);
		}

		if (!$rate_row) {
			$rate_row = $this->getDefaultRate();
		}

		if (!$rate_row || !(int)$rate_row['status']) {
			return [];
		}

		$default_weight = (float)$this->config->get('shipping_next_day_trishaw_delivery_rate_default_weight');
		$additional_weight_step = (float)$this->config->get('shipping_next_day_trishaw_delivery_rate_additional_weight');

		if ($default_weight <= 0) {
			$default_weight = 1;
		}

		if ($additional_weight_step <= 0) {
			$additional_weight_step = 1;
		}

		$cart_weight = (float)$this->cart->getWeight();

		if ($cart_weight <= 0) {
			$cart_weight = $default_weight;
		}

		$cost = (float)$rate_row['city_rate'] + (float)$rate_row['primary_weight_rate'];

		if ($cart_weight > $default_weight) {
			$extra_weight = $cart_weight - $default_weight;
			$increments = (int)ceil($extra_weight / $additional_weight_step);
			$cost += $increments * (float)$rate_row['additional_weight_rate'];
		}

		$cost = max(0, $cost);

		$quote_data = [];

		$quote_data['next_day_trishaw_delivery_rate'] = [
			'code' => 'next_day_trishaw_delivery_rate.next_day_trishaw_delivery_rate',
			'title' => sprintf($this->language->get('text_quote_title'), $method_title, $rate_row['city']),
			'cost' => $cost,
			'tax_class_id' => $tax_class_id,
			'text' => $this->currency->format($this->tax->calculate($cost, $tax_class_id, $this->config->get('config_tax')), $this->session->data['currency'])
		];

		return [
			'code' => 'next_day_trishaw_delivery_rate',
			'title' => $method_title,
			'quote' => $quote_data,
			'sort_order' => $sort_order,
			'error' => false
		];
	}

	/**
	 * Retrieve the exact city rate for province/city.
	 *
	 * @param string $province
	 * @param string $city
	 *
	 * @return array<string, mixed>|null
	 */
	private function getCityRate(string $province, string $city): ?array {
		$province_key = $this->db->escape($this->normalizeKey($province));
		$city_key = $this->db->escape($this->normalizeKey($city));

		$query = $this->db->query("SELECT * FROM `" . self::TABLE_NAME . "` WHERE LCASE(`province`) = '" . $province_key . "' AND LCASE(`city`) = '" . $city_key . "' LIMIT 1");

		return $query->row ?: null;
	}

	/**
	 * Fallback to the first active city in the province.
	 *
	 * @param string $province
	 *
	 * @return array<string, mixed>|null
	 */
	private function getFallbackProvinceRate(string $province): ?array {
		$province_key = $this->db->escape($this->normalizeKey($province));

		$query = $this->db->query("SELECT * FROM `" . self::TABLE_NAME . "` WHERE LCASE(`province`) = '" . $province_key . "' AND `status` = '1' ORDER BY `city` ASC LIMIT 1");

		return $query->row ?: null;
	}

	/**
	 * Attempt to find an active rate by city name regardless of province.
	 *
	 * @param string $city
	 *
	 * @return array<string, mixed>|null
	 */
	private function getCityRateByCityName(string $city): ?array {
		$city_key = $this->db->escape($this->normalizeKey($city));

		$query = $this->db->query("SELECT * FROM `" . self::TABLE_NAME . "` WHERE LCASE(`city`) = '" . $city_key . "' AND `status` = '1' ORDER BY `province` ASC LIMIT 1");

		return $query->row ?: null;
	}

	/**
	 * Return the first active rate as a last resort.
	 *
	 * @return array<string, mixed>|null
	 */
	private function getDefaultRate(): ?array {
		$query = $this->db->query("SELECT * FROM `" . self::TABLE_NAME . "` WHERE `status` = '1' ORDER BY `province` ASC, `city` ASC LIMIT 1");

		return $query->row ?: null;
	}

	/**
	 * Resolve province name from shipping address with light normalisation.
	 *
	 * @param array<string, mixed> $address
	 *
	 * @return string
	 */
	private function resolveProvince(array $address): string {
		$province = '';

		if (!empty($address['zone_id'])) {
			$this->load->model('localisation/zone');

			$zone_info = $this->model_localisation_zone->getZone((int)$address['zone_id']);

			if ($zone_info) {
				$province = $zone_info['name'];
			}
		}

		if (!$province && !empty($address['zone'])) {
			$province = $address['zone'];
		}

		$province = trim((string)$province);

		if ($province) {
			$province = preg_replace('/\s+district$/i', '', $province);
		}

		return $province;
	}

	/**
	 * Resolve the city value from the shipping address.
	 *
	 * @param array<string, mixed> $address
	 *
	 * @return string
	 */
	private function resolveCity(array $address): string {
		return trim((string)($address['city'] ?? ''));
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

