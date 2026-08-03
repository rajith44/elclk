<?php
namespace Opencart\Catalog\Model\Extension\ContactStores;
/**
 * Class Contact
 */
class Contact extends \Opencart\System\Engine\Model {
	/**
	 * @return array<string, array<string, string>>
	 */
	public function getStores(): array {
		return [
			'sl' => $this->buildStore('sl'),
			'au' => $this->buildStore('au'),
		];
	}

	/**
	 * @param string $code
	 *
	 * @return array<string, string>
	 */
	public function getStore(string $code): array {
		$stores = $this->getStores();

		return $stores[$code] ?? [];
	}

	/**
	 * @param string $code
	 *
	 * @return array<string, string>
	 */
	private function buildStore(string $code): array {
		$prefix = 'module_contact_stores_' . $code . '_';

		$name = trim((string)$this->config->get($prefix . 'name'));
		$address = trim((string)$this->config->get($prefix . 'address'));
		$telephone = trim((string)$this->config->get($prefix . 'telephone'));
		$email = trim((string)$this->config->get($prefix . 'email'));
		$open = trim((string)$this->config->get($prefix . 'open'));
		$geocode = trim((string)$this->config->get($prefix . 'geocode'));
		$map_embed = trim((string)$this->config->get($prefix . 'map_embed'));
		$image = trim((string)$this->config->get($prefix . 'image'));

		if ($map_embed === '' && $geocode !== '') {
			$map_embed = 'https://maps.google.com/maps?q=' . rawurlencode($geocode) . '&z=15&output=embed';
		}

		$maps_link = $geocode !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($geocode) : '';
		[$latitude, $longitude] = $this->parseGeocode($geocode);

		return [
			'code'        => $code,
			'name'        => $name,
			'address'     => $address,
			'address_html'=> nl2br($address),
			'telephone'   => $telephone,
			'email'       => $email,
			'open'        => $open,
			'open_html'   => nl2br($open),
			'geocode'     => $geocode,
			'latitude'    => $latitude,
			'longitude'   => $longitude,
			'image'       => $image,
			'map_embed'   => $map_embed,
			'maps_link'   => $maps_link,
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getMapMarkers(): array {
		$markers = [];

		foreach ($this->getStores() as $store) {
			if ($store['latitude'] === null || $store['longitude'] === null || $store['name'] === '') {
				continue;
			}

			$markers[] = [
				'code'      => $store['code'],
				'name'      => $store['name'],
				'address'   => $store['address'],
				'latitude'  => $store['latitude'],
				'longitude' => $store['longitude'],
				'maps_link' => $store['maps_link'],
			];
		}

		return $markers;
	}

	/**
	 * @param string $geocode
	 *
	 * @return array{0: ?float, 1: ?float}
	 */
	private function parseGeocode(string $geocode): array {
		if ($geocode === '' || !str_contains($geocode, ',')) {
			return [null, null];
		}

		$parts = array_map('trim', explode(',', $geocode, 2));

		if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
			return [null, null];
		}

		return [(float)$parts[0], (float)$parts[1]];
	}

	/**
	 * @param string $store_code
	 * @param array<string, string> $data
	 *
	 * @return bool
	 */
	public function sendEnquiry(string $store_code, array $data): bool {
		$store = $this->getStore($store_code);

		if (!$store || $store['email'] === '') {
			return false;
		}

		if (!$this->config->get('config_mail_engine')) {
			return false;
		}

		$mail_option = [
			'parameter'     => $this->config->get('config_mail_parameter'),
			'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
			'smtp_username' => $this->config->get('config_mail_smtp_username'),
			'smtp_password' => html_entity_decode((string)$this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
			'smtp_port'     => $this->config->get('config_mail_smtp_port'),
			'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout'),
		];

		$body = "Sending From: {$data['sending_from']}\n";
		$body .= "Store: {$store['name']}\n";
		$body .= "Name: {$data['name']}\n";
		$body .= "Email: {$data['email']}\n";

		if ($data['telephone'] !== '') {
			$body .= "Phone: {$data['telephone']}\n";
		}

		$body .= "\nMessage:\n{$data['enquiry']}\n";

		$mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'), $mail_option);
		$mail->setTo($store['email']);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setReplyTo($data['email']);
		$mail->setSender(html_entity_decode($data['name'], ENT_QUOTES, 'UTF-8'));
		$mail->setSubject(html_entity_decode($data['subject'], ENT_QUOTES, 'UTF-8'));
		$mail->setText($body);
		$mail->send();

		return true;
	}
}
