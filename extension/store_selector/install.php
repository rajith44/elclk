<?php
/**
 * One-time installer for Store Region Selector extension.
 * Run: php extension/store_selector/install.php
 */
require dirname(__DIR__, 2) . '/config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

if ($db->connect_error) {
    die('DB connection failed: ' . $db->connect_error . PHP_EOL);
}

$prefix = DB_PREFIX;

// Register extension if missing
$db->query("INSERT IGNORE INTO `{$prefix}extension` SET `extension` = 'store_selector', `type` = 'module', `code` = 'store_selector'");

// Default settings
$settings = [
    'module_store_selector_status'         => '1',
    'module_store_selector_sl_url'         => HTTP_SERVER,
    'module_store_selector_au_url'         => 'http://au.elc.local/',
    'module_store_selector_sl_name'        => 'Sri Lanka',
    'module_store_selector_au_name'        => 'Australia',
    'module_store_selector_sl_description' => 'Island-wide delivery across Sri Lanka',
    'module_store_selector_au_description' => 'Delivery across Australia',
    'module_store_selector_geo_redirect'   => '1',
    'module_store_selector_first_visit_mode' => 'geo',
    'module_store_selector_cookie_days'    => '30',
    'module_store_selector_cookie_domain'  => ''
];

foreach ($settings as $key => $value) {
    $db->query("DELETE FROM `{$prefix}setting` WHERE `store_id` = 0 AND `code` = 'module_store_selector' AND `key` = '" . $db->real_escape_string($key) . "'");
    $db->query("INSERT INTO `{$prefix}setting` SET `store_id` = 0, `code` = 'module_store_selector', `key` = '" . $db->real_escape_string($key) . "', `value` = '" . $db->real_escape_string($value) . "', `serialized` = 0");
}

// Startup
$db->query("DELETE FROM `{$prefix}startup` WHERE `code` = 'store_selector'");
$db->query("INSERT INTO `{$prefix}startup` SET `code` = 'store_selector', `description` = 'Store region selector redirect', `action` = 'catalog/extension/store_selector/startup/store_selector', `status` = 1, `sort_order` = 2");

// Event
$db->query("DELETE FROM `{$prefix}event` WHERE `code` = 'store_selector'");
$db->query("INSERT INTO `{$prefix}event` SET `code` = 'store_selector', `description` = 'Store region selector location switcher', `trigger` = 'catalog/view/common/footer/after', `action` = 'extension/store_selector/module/store_selector.header', `status` = 1, `sort_order` = 0");

// Admin permissions (required to open module settings)
$permission_route = 'extension/store_selector/module/store_selector';
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

echo "Store Region Selector installed successfully.\n";
echo "Enable and configure at Admin > Extensions > Modules > Store Region Selector\n";
