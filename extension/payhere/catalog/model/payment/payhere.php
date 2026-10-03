<?php
namespace Opencart\Catalog\Model\Extension\Payhere\Payment;

class Payhere extends \Opencart\System\Engine\Model {
	public function getMethods(array $address = []): array {
		$this->load->language('extension/payhere/payment/payhere');

		$status = (bool)$this->config->get('payment_payhere_status');

		if ($status) {
			$minimum_total = (float)$this->config->get('payment_payhere_total');

			if ($minimum_total > 0 && $minimum_total > $this->cart->getSubTotal()) {
				$status = false;
			}
		}

		if ($status && $this->config->get('payment_payhere_geo_zone_id')) {
			$this->load->model('localisation/geo_zone');

			$geo_zone = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('payment_payhere_geo_zone_id'), (int)($address['country_id'] ?? 0), (int)($address['zone_id'] ?? 0));

			$status = (bool)$geo_zone;
		}

		$method_data = [];

		if ($status) {
			$option_data['payhere'] = [
				'code' => 'payhere.payhere',
				'name' => $this->language->get('text_title')
			];

			$method_data = [
				'code'       => 'payhere',
				'name'       => $this->language->get('text_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_payhere_sort_order')
			];
		}

		return $method_data;
	}

	/**
	 * Retrieval API base, which differs between sandbox and live.
	 *
	 * @return string
	 */
	private function getApiBase(): string {
		return $this->config->get('payment_payhere_test') ? 'https://sandbox.payhere.lk' : 'https://www.payhere.lk';
	}

	/**
	 * Exchange the App ID / App Secret for a short-lived access token.
	 *
	 * Tokens last around 10 minutes (expires_in 599) and the /merchant/v1/ endpoints
	 * are capped at 20 requests per 10 seconds, so the token is cached rather than
	 * re-fetched per lookup.
	 *
	 * @return string  empty when the credentials are missing or the exchange fails
	 */
	public function getAccessToken(): string {
		$app_id = (string)$this->config->get('payment_payhere_app_id');
		$app_secret = (string)$this->config->get('payment_payhere_app_secret');

		if (!$app_id || !$app_secret) {
			return '';
		}

		$cache_key = 'payhere.token.' . md5($app_id . $this->getApiBase());

		$cached = $this->cache->get($cache_key);

		if ($cached) {
			return (string)$cached;
		}

		$ch = curl_init($this->getApiBase() . '/merchant/v1/oauth/token');

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
			CURLOPT_HTTPHEADER     => [
				'Authorization: Basic ' . base64_encode($app_id . ':' . $app_secret),
				'Content-Type: application/x-www-form-urlencoded'
			],
			CURLOPT_TIMEOUT        => 15
		]);

		$response = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		curl_close($ch);

		if ($status !== 200 || !$response) {
			$this->log->write('PayHere token request failed (HTTP ' . $status . ')' . ($error ? ': ' . $error : ''));

			return '';
		}

		$data = json_decode($response, true);
		$token = (string)($data['access_token'] ?? '');

		if ($token) {
			// Expire our copy well before PayHere does.
			$this->cache->set($cache_key, $token, max(60, (int)($data['expires_in'] ?? 599) - 60));
		}

		return $token;
	}

	/**
	 * Look a payment up by the order id that was sent to checkout.
	 *
	 * Used to confirm and record what PayHere actually holds, rather than trusting
	 * only the callback POST. Returns [] when it cannot be retrieved — callers must
	 * treat that as "unknown", never as "not paid".
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function retrievePayment(int $order_id): array {
		$token = $this->getAccessToken();

		if (!$token) {
			return [];
		}

		$ch = curl_init($this->getApiBase() . '/merchant/v1/payment/search?order_id=' . rawurlencode((string)$order_id));

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => [
				'Authorization: Bearer ' . $token,
				'Content-Type: application/json'
			],
			CURLOPT_TIMEOUT        => 15
		]);

		$response = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		curl_close($ch);

		if ($status !== 200 || !$response) {
			$this->log->write('PayHere retrieval failed for order_id ' . $order_id . ' (HTTP ' . $status . ')' . ($error ? ': ' . $error : ''));

			return [];
		}

		$data = json_decode($response, true);

		// Top level status: 1 success, -1 no records, -2 declined.
		if ((int)($data['status'] ?? 0) !== 1 || empty($data['data'][0])) {
			$this->log->write('PayHere retrieval returned no payment for order_id ' . $order_id . ' (status ' . ($data['status'] ?? 'n/a') . ')');

			return [];
		}

		return $data['data'][0];
	}
}

