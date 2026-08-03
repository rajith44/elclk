<?php
/**
 * One-time installer for Contact Stores page.
 * Run: C:\xampp\php\php.exe extension/contact_stores/install.php
 */
require dirname(__DIR__, 2) . '/config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

if ($db->connect_error) {
	die('DB connection failed: ' . $db->connect_error . PHP_EOL);
}

$prefix = DB_PREFIX;

$db->query("INSERT IGNORE INTO `{$prefix}extension` SET `extension` = 'contact_stores', `type` = 'module', `code` = 'contact_stores'");

$settings = [
	'module_contact_stores_status'         => '1',
	'module_contact_stores_intro'          => 'We have dedicated teams in Sri Lanka and Australia. Choose your region below to view store details or send us a message.',
	'module_contact_stores_sl_name'        => 'ELC Sri Lanka',
	'module_contact_stores_sl_address'     => "411A, Kotte Road\nPitakotte\nSri Lanka",
	'module_contact_stores_sl_telephone'   => '077 270 5397',
	'module_contact_stores_sl_email'       => 'malindarajith@gmail.com',
	'module_contact_stores_sl_open'        => "Mon - Sat: 9:00 AM - 6:00 PM\nSun: Closed",
	'module_contact_stores_sl_geocode'     => '6.8828511,79.9015717',
	'module_contact_stores_sl_map_embed'   => '',
	'module_contact_stores_sl_image'       => '',
	'module_contact_stores_au_name'        => 'ELC Australia',
	'module_contact_stores_au_address'     => "Sydney, NSW\nAustralia",
	'module_contact_stores_au_telephone'   => '',
	'module_contact_stores_au_email'       => '',
	'module_contact_stores_au_open'        => "Mon - Fri: 9:00 AM - 5:00 PM",
	'module_contact_stores_au_geocode'     => '-33.8688,151.2093',
	'module_contact_stores_au_map_embed'   => '',
	'module_contact_stores_au_image'       => '',
];

foreach ($settings as $key => $value) {
	$db->query("DELETE FROM `{$prefix}setting` WHERE `store_id` = 0 AND `code` = 'module_contact_stores' AND `key` = '" . $db->real_escape_string($key) . "'");
	$db->query("INSERT INTO `{$prefix}setting` SET `store_id` = 0, `code` = 'module_contact_stores', `key` = '" . $db->real_escape_string($key) . "', `value` = '" . $db->real_escape_string($value) . "', `serialized` = 0");
}

$permission_route = 'extension/contact_stores/module/contact_stores';
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

$seo_query = "SELECT seo_url_id FROM `{$prefix}seo_url` WHERE `store_id` = 0 AND `language_id` = 1 AND `key` = 'route' AND `value` = 'extension/contact_stores/contact' LIMIT 1";
$seo = $db->query($seo_query);

if ($seo && $seo->num_rows === 0) {
	$db->query("INSERT INTO `{$prefix}seo_url` SET
		`store_id` = 0,
		`language_id` = 1,
		`key` = 'route',
		`value` = 'extension/contact_stores/contact',
		`keyword` = 'contact-us',
		`sort_order` = 0");
}

echo "Contact Stores installed successfully.\n";
echo "Page URL: index.php?route=extension/contact_stores/contact\n";
echo "SEO URL: /contact-us (if SEO URLs are enabled)\n";
echo "Admin: Extensions > Modules > Contact Stores Page\n";
