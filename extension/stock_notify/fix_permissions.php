<?php
/**
 * Fix admin permissions for Back In Stock Notify.
 * Run: php extension/stock_notify/fix_permissions.php
 */
require dirname(__DIR__, 2) . '/config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

if ($db->connect_error) {
	die('DB connection failed: ' . $db->connect_error . PHP_EOL);
}

$prefix = DB_PREFIX;
$permission_route = 'extension/stock_notify/module/stock_notify';
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

echo "Permissions updated for {$permission_route}\n";
