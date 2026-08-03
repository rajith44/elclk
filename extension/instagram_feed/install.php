<?php
/**
 * One-time installer for Instagram Feed extension.
 * Run: C:\xampp\php\php.exe extension/instagram_feed/install.php
 */
require dirname(__DIR__, 2) . '/config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

if ($db->connect_error) {
	die('DB connection failed: ' . $db->connect_error . PHP_EOL);
}

$prefix = DB_PREFIX;

$db->query("INSERT IGNORE INTO `{$prefix}extension` SET `extension` = 'instagram_feed', `type` = 'module', `code` = 'instagram_feed'");

$settings = [
	'module_instagram_feed_status'        => '1',
	'module_instagram_feed_title'         => 'Follow Us on Instagram',
	'module_instagram_feed_username'      => '',
	'module_instagram_feed_user_id'       => '',
	'module_instagram_feed_access_token'  => '',
	'module_instagram_feed_limit'         => '6',
	'module_instagram_feed_columns'       => '3',
	'module_instagram_feed_show_caption'  => '1',
	'module_instagram_feed_show_stats'    => '1',
	'module_instagram_feed_show_follow'   => '1',
	'module_instagram_feed_cache_ttl'     => '3600',
	'module_instagram_feed_auto_contact'  => '1',
];

foreach ($settings as $key => $value) {
	$db->query("DELETE FROM `{$prefix}setting` WHERE `store_id` = 0 AND `code` = 'module_instagram_feed' AND `key` = '" . $db->real_escape_string($key) . "'");
	$db->query("INSERT INTO `{$prefix}setting` SET `store_id` = 0, `code` = 'module_instagram_feed', `key` = '" . $db->real_escape_string($key) . "', `value` = '" . $db->real_escape_string($value) . "', `serialized` = 0");
}

$permission_route = 'extension/instagram_feed/module/instagram_feed';
$groups = $db->query("SELECT user_group_id, permission FROM `{$prefix}user_group`");

while ($group = $groups->fetch_assoc()) {
	$data = $group['permission'] ? json_decode($group['permission'], true) : [];

	if (!is_array($data)) {
		$data = [];
	}

	foreach (['access', 'modify'] as $type) {
		if (!isset($data[$type]) || !is_array($data[$type])) {
			$data[$type] = [];
		}

		if (!in_array($permission_route, $data[$type], true)) {
			$data[$type][] = $permission_route;
		}
	}

	$db->query("UPDATE `{$prefix}user_group` SET `permission` = '" . $db->real_escape_string(json_encode($data)) . "' WHERE `user_group_id` = '" . (int)$group['user_group_id'] . "'");
}

$event_code = 'instagram_feed';

$db->query("DELETE FROM `{$prefix}event` WHERE `code` = '" . $db->real_escape_string($event_code) . "'");

$db->query("INSERT INTO `{$prefix}event` SET
	`code` = '" . $db->real_escape_string($event_code) . "',
	`description` = 'Instagram feed on contact page',
	`trigger` = 'catalog/view/extension/contact_stores/contact/contact/after',
	`action` = 'extension/instagram_feed/module/instagram_feed.contactPage',
	`status` = 1,
	`sort_order` = 0");

echo "Instagram Feed installed successfully.\n";
echo "Configure at Admin > Extensions > Modules > Instagram Feed\n";
echo "Then add the module in Design > Layouts (e.g. Home or Contact page).\n";
