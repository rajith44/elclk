<?php
namespace Opencart\Catalog\Controller\Extension\InstagramFeed\Module;
/**
 * Class InstagramFeed
 */
class InstagramFeed extends \Opencart\System\Engine\Controller {
	private string $event_code = 'instagram_feed';

	/**
	 * Inject feed on the Contact Us page.
	 *
	 * @param string $route
	 * @param array  $data
	 * @param string $output
	 *
	 * @return void
	 */
	public function contactPage(string &$route, array &$data, string &$output): void {
		if (!$this->config->get('module_instagram_feed_status') || !$this->config->get('module_instagram_feed_auto_contact')) {
			return;
		}

		$feed = $this->index();

		if ($feed === '') {
			return;
		}

		$markup = '<div class="container">' . $feed . '</div>';

		if (strpos($output, 'id="cs-contact-modal"') !== false) {
			$output = preg_replace('/(<div class="cs-modal" id="cs-contact-modal")/', $markup . '$1', $output, 1);
		} elseif (strpos($output, '</body>') !== false) {
			$output = str_replace('</body>', $markup . '</body>', $output);
		} else {
			$output .= $markup;
		}
	}

	/**
	 * @param array<string, mixed> $setting
	 *
	 * @return string
	 */
	public function index(array $setting = []): string {
		if (!$this->config->get('module_instagram_feed_status')) {
			return '';
		}

		if (isset($setting['status']) && !(int)$setting['status']) {
			return '';
		}

		$this->load->language('extension/instagram_feed/module/instagram_feed');
		$this->load->model('extension/instagram_feed/module/instagram_feed');

		$limit = (int)$this->config->get('module_instagram_feed_limit');

		if ($limit < 1) {
			$limit = 6;
		}

		$result = $this->model_extension_instagram_feed_module_instagram_feed->getPosts($limit);

		if (!$result['posts']) {
			return '';
		}

		$username = trim((string)$this->config->get('module_instagram_feed_username'));
		$profile_url = $username !== '' ? 'https://www.instagram.com/' . rawurlencode($username) . '/' : 'https://www.instagram.com/';

		$data['title'] = $this->config->get('module_instagram_feed_title') ?: 'Instagram';
		$data['username'] = $username;
		$data['profile_url'] = $profile_url;
		$data['posts'] = $result['posts'];
		$data['columns'] = (int)$this->config->get('module_instagram_feed_columns') ?: 3;
		$data['show_caption'] = (int)$this->config->get('module_instagram_feed_show_caption');
		$data['show_stats'] = (int)$this->config->get('module_instagram_feed_show_stats');
		$data['show_follow'] = (int)$this->config->get('module_instagram_feed_show_follow');
		$data['text_follow'] = $username !== '' ? sprintf($this->language->get('text_follow'), $username) : $this->language->get('text_view');
		$data['text_view'] = $this->language->get('text_view');

		if (!empty($this->request->server['HTTPS'])) {
			$data['base'] = $this->config->get('config_ssl') ?: $this->config->get('config_url');
		} else {
			$data['base'] = $this->config->get('config_url');
		}

		return $this->load->view('extension/instagram_feed/module/instagram_feed', $data);
	}
}
