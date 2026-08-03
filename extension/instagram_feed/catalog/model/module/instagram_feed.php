<?php
namespace Opencart\Catalog\Model\Extension\InstagramFeed\Module;
/**
 * Class InstagramFeed
 */
class InstagramFeed extends \Opencart\System\Engine\Model {
	/**
	 * @param int $limit
	 *
	 * @return array{posts: array<int, array<string, mixed>>, error: string}
	 */
	public function getPosts(int $limit = 6): array {
		$user_id = trim((string)$this->config->get('module_instagram_feed_user_id'));
		$token = trim((string)$this->config->get('module_instagram_feed_access_token'));

		if ($user_id === '' || $token === '') {
			return ['posts' => [], 'error' => 'missing_credentials'];
		}

		$limit = max(1, min(12, $limit));
		$cache_key = 'instagram_feed';
		$cached = $this->cache->get($cache_key);

		if (is_array($cached) && isset($cached['posts'])) {
			return $cached;
		}

		$fields = 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count';
		$url = 'https://graph.facebook.com/v21.0/' . rawurlencode($user_id) . '/media'
			. '?fields=' . rawurlencode($fields)
			. '&limit=' . $limit
			. '&access_token=' . rawurlencode($token);

		$response = $this->request($url);

		if (!empty($response['error']['message'])) {
			return ['posts' => [], 'error' => (string)$response['error']['message']];
		}

		$posts = [];

		foreach ($response['data'] ?? [] as $item) {
			$image = '';

			if (($item['media_type'] ?? '') === 'VIDEO') {
				$image = (string)($item['thumbnail_url'] ?? '');
			} else {
				$image = (string)($item['media_url'] ?? $item['thumbnail_url'] ?? '');
			}

			if ($image === '' || empty($item['permalink'])) {
				continue;
			}

			$caption = trim((string)($item['caption'] ?? ''));
			$caption_short = $caption;

			if (oc_strlen($caption_short) > 120) {
				$caption_short = oc_substr($caption_short, 0, 117) . '...';
			}

			$posts[] = [
				'id'            => (string)($item['id'] ?? ''),
				'image'         => $image,
				'permalink'     => (string)$item['permalink'],
				'caption'       => $caption_short,
				'media_type'    => (string)($item['media_type'] ?? 'IMAGE'),
				'timestamp'     => !empty($item['timestamp']) ? date('d M Y', strtotime((string)$item['timestamp'])) : '',
				'like_count'    => (int)($item['like_count'] ?? 0),
				'comments_count'=> (int)($item['comments_count'] ?? 0),
			];
		}

		$result = ['posts' => $posts, 'error' => ''];

		$ttl = (int)$this->config->get('module_instagram_feed_cache_ttl');

		if ($ttl < 300) {
			$ttl = 300;
		}

		$this->cache->set($cache_key, $result, $ttl);

		return $result;
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
