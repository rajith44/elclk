<?php
namespace Opencart\Catalog\Model\Extension\StoreSelector\Module;
/**
 * Class StoreSelector
 *
 * @package Opencart\Catalog\Model\Extension\StoreSelector\Module
 */
class StoreSelector extends \Opencart\System\Engine\Model {
	public const COOKIE_NAME = 'elc_store_region';
	public const REGION_LK = 'lk';
	public const REGION_AU = 'au';

	/**
	 * @return array<string, mixed>
	 */
	public function getRegions(): array {
		return [
			self::REGION_LK => [
				'code'        => self::REGION_LK,
				'name'        => $this->config->get('module_store_selector_sl_name') ?: 'Sri Lanka',
				'url'         => rtrim((string)$this->config->get('module_store_selector_sl_url'), '/') . '/',
				'flag'        => '🇱🇰',
				'description' => $this->config->get('module_store_selector_sl_description') ?: 'Shop from our Sri Lanka store'
			],
			self::REGION_AU => [
				'code'        => self::REGION_AU,
				'name'        => $this->config->get('module_store_selector_au_name') ?: 'Australia',
				'url'         => rtrim((string)$this->config->get('module_store_selector_au_url'), '/') . '/',
				'flag'        => '🇦🇺',
				'description' => $this->config->get('module_store_selector_au_description') ?: 'Shop from our Australia store'
			]
		];
	}

	/**
	 * @param string $url
	 *
	 * @return string
	 */
	public function normalizeHost(string $url): string {
		$host = parse_url($url, PHP_URL_HOST);

		if (!$host && str_contains($url, '://') === false) {
			$host = parse_url('http://' . ltrim($url, '/'), PHP_URL_HOST);
		}

		return strtolower(str_replace('www.', '', (string)$host));
	}

	/**
	 * @return string
	 */
	public function getCurrentHost(): string {
		$host = $this->request->server['HTTP_HOST'] ?? '';

		return strtolower(str_replace('www.', '', $host));
	}

	/**
	 * @return string
	 */
	public function getSelectedRegion(): string {
		if (!empty($this->request->cookie[self::COOKIE_NAME])) {
			$region = (string)$this->request->cookie[self::COOKIE_NAME];

			if (isset($this->getRegions()[$region])) {
				return $region;
			}
		}

		return '';
	}

	/**
	 * @return string
	 */
	public function detectRegionByHost(): string {
		$host = $this->getCurrentHost();

		foreach ($this->getRegions() as $code => $region) {
			if ($this->normalizeHost($region['url']) === $host) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * @param string $region
	 *
	 * @return void
	 */
	public function setRegionCookie(string $region): void {
		$regions = $this->getRegions();

		if (!isset($regions[$region])) {
			return;
		}

		$days = (int)$this->config->get('module_store_selector_cookie_days');

		if ($days < 1) {
			$days = 30;
		}

		$option = [
			'expires'  => time() + ($days * 86400),
			'path'     => '/',
			'secure'   => !empty($this->request->server['HTTPS']),
			'httponly' => false,
			'samesite' => 'Lax'
		];

		$cookie_domain = trim((string)$this->config->get('module_store_selector_cookie_domain'));

		if ($cookie_domain !== '') {
			$option['domain'] = $cookie_domain;
		}

		setcookie(self::COOKIE_NAME, $region, $option);

		$this->request->cookie[self::COOKIE_NAME] = $region;
	}

	/**
	 * @return void
	 */
	public function clearRegionCookie(): void {
		$option = [
			'expires'  => time() - 3600,
			'path'     => '/',
			'secure'   => !empty($this->request->server['HTTPS']),
			'httponly' => false,
			'samesite' => 'Lax'
		];

		$cookie_domain = trim((string)$this->config->get('module_store_selector_cookie_domain'));

		if ($cookie_domain !== '') {
			$option['domain'] = $cookie_domain;
		}

		setcookie(self::COOKIE_NAME, '', $option);

		unset($this->request->cookie[self::COOKIE_NAME]);
	}

	/**
	 * @return string
	 */
	public function getClientIp(): string {
		$candidates = [
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR'
		];

		foreach ($candidates as $key) {
			if (empty($this->request->server[$key])) {
				continue;
			}

			$value = (string)$this->request->server[$key];

			if ($key === 'HTTP_X_FORWARDED_FOR') {
				$value = trim(explode(',', $value)[0]);
			}

			if (filter_var($value, FILTER_VALIDATE_IP)) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @param string $ip
	 *
	 * @return bool
	 */
	public function isPrivateIp(string $ip): bool {
		if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
			return true;
		}

		return !filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	/**
	 * @return string LK, AU or empty
	 */
	public function detectCountryCode(): string {
		if (isset($this->session->data['store_selector_country']) && is_string($this->session->data['store_selector_country'])) {
			return $this->session->data['store_selector_country'];
		}

		$ip = $this->getClientIp();

		if ($ip === '' || $this->isPrivateIp($ip)) {
			return '';
		}

		$cache_key = 'store_selector.geo.' . md5($ip);
		$cached = $this->cache->get($cache_key);

		// OpenCart file cache returns [] on miss, not null/false.
		if (is_string($cached)) {
			$this->session->data['store_selector_country'] = $cached;

			return $cached;
		}

		$country = $this->lookupCountryCode($ip);

		$this->cache->set($cache_key, $country, 86400);
		$this->session->data['store_selector_country'] = $country;

		return $country;
	}

	/**
	 * @param string $ip
	 *
	 * @return string
	 */
	private function lookupCountryCode(string $ip): string {
		$endpoint = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode';

		$context = stream_context_create([
			'http' => [
				'timeout' => 2,
				'header'  => "User-Agent: ELC-StoreSelector/1.0\r\n"
			]
		]);

		$response = @file_get_contents($endpoint, false, $context);

		if ($response === false) {
			return '';
		}

		$data = json_decode($response, true);

		if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
			return '';
		}

		$code = strtoupper((string)($data['countryCode'] ?? ''));

		if (in_array($code, ['LK', 'AU'], true)) {
			return $code;
		}

		return '';
	}

	/**
	 * @param string $country_code
	 *
	 * @return string
	 */
	public function countryToRegion(string $country_code): string {
		return match (strtoupper($country_code)) {
			'LK'    => self::REGION_LK,
			'AU'    => self::REGION_AU,
			default => ''
		};
	}

	/**
	 * @param string $region
	 *
	 * @return string
	 */
	public function getRegionUrl(string $region): string {
		$regions = $this->getRegions();

		return $regions[$region]['url'] ?? '';
	}

	/**
	 * @return string ask|geo
	 */
	public function getFirstVisitMode(): string {
		$mode = (string)$this->config->get('module_store_selector_first_visit_mode');

		if ($mode === 'ask' || $mode === 'geo') {
			return $mode;
		}

		return $this->config->get('module_store_selector_geo_redirect') ? 'geo' : 'ask';
	}

	/**
	 * @return bool
	 */
	public function usesGeoRedirect(): bool {
		return $this->getFirstVisitMode() === 'geo';
	}

	/**
	 * @return bool
	 */
	public function shouldShowGateway(): bool {
		if (!$this->config->get('module_store_selector_status')) {
			return false;
		}

		if (!empty($this->request->get['change_location'])) {
			return true;
		}

		return $this->getSelectedRegion() === '';
	}
}
