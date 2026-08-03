<?php
namespace Opencart\Admin\Model\Extension\InstagramFeed\Module;
/**
 * Class InstagramFeed
 */
class InstagramFeed extends \Opencart\System\Engine\Model {
	/**
	 * @return void
	 */
	public function clearCache(): void {
		$this->cache->delete('instagram_feed');
	}

	/**
	 * @param string $user_id
	 * @param string $token
	 * @param int    $limit
	 *
	 * @return array{posts: array<int, array<string, mixed>>, error: string}
	 */
	public function testConnection(string $user_id, string $token, int $limit = 3): array {
		if ($user_id === '' || $token === '') {
			return ['posts' => [], 'error' => 'missing_credentials'];
		}

		$fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count';
		$url = 'https://graph.facebook.com/v21.0/' . rawurlencode($user_id) . '/media'
			. '?fields=' . rawurlencode($fields)
			. '&limit=' . max(1, min(12, $limit))
			. '&access_token=' . rawurlencode($token);

		$response = $this->request($url);

		if (!empty($response['error']['message'])) {
			return ['posts' => [], 'error' => (string)$response['error']['message']];
		}

		return ['posts' => $response['data'] ?? [], 'error' => ''];
	}

	/**
	 * @param string $url
	 *
	 * @return array<string, mixed>
	 */
	private function request(string $url): array {
		if (!function_exists('curl_init')) {
			$context = stream_context_create([
				'http' => [
					'timeout' => 15,
					'ignore_errors' => true,
				],
				'ssl' => [
					'verify_peer' => true,
					'verify_peer_name' => true,
				],
			]);

			$response = @file_get_contents($url, false, $context);

			if ($response === false) {
				return ['error' => ['message' => 'Could not connect to Instagram API.']];
			}

			$data = json_decode($response, true);

			return is_array($data) ? $data : ['error' => ['message' => 'Invalid API response.']];
		}

		$ch = curl_init($url);

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_SSL_VERIFYPEER => true,
		]);

		$response = curl_exec($ch);
		$error = curl_error($ch);

		curl_close($ch);

		if ($response === false) {
			return ['error' => ['message' => $error ?: 'Could not connect to Instagram API.']];
		}

		$data = json_decode($response, true);

		return is_array($data) ? $data : ['error' => ['message' => 'Invalid API response.']];
	}
}
