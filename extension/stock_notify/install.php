<?php
/**
 * One-time installer for Back In Stock Notify extension.
 * Run: php extension/stock_notify/install.php
 */
require dirname(__DIR__, 2) . '/config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

if ($db->connect_error) {
	die('DB connection failed: ' . $db->connect_error . PHP_EOL);
}

$prefix = DB_PREFIX;

$db->query("CREATE TABLE IF NOT EXISTS `{$prefix}stock_notify` (
	`stock_notify_id` INT(11) NOT NULL AUTO_INCREMENT,
	`product_id` INT(11) NOT NULL,
	`store_id` INT(11) NOT NULL DEFAULT 0,
	`customer_id` INT(11) NOT NULL DEFAULT 0,
	`language_id` INT(11) NOT NULL DEFAULT 0,
	`name` VARCHAR(64) NOT NULL DEFAULT '',
	`email` VARCHAR(96) NOT NULL,
	`telephone` VARCHAR(32) NOT NULL DEFAULT '',
	`status` TINYINT(1) NOT NULL DEFAULT 0,
	`date_added` DATETIME NOT NULL,
	`date_notified` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`stock_notify_id`),
	KEY `product_id` (`product_id`),
	KEY `email` (`email`),
	KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$db->query("INSERT IGNORE INTO `{$prefix}extension` SET `extension` = 'stock_notify', `type` = 'module', `code` = 'stock_notify'");

$settings = [
	'module_stock_notify_status'        => '1',
	'module_stock_notify_button_text'   => 'Notify Me When Available',
	'module_stock_notify_require_name'  => '0',
	'module_stock_notify_show_phone'    => '0',
	'module_stock_notify_email_subject' => '{product_name} is back in stock!',
	'module_stock_notify_email_body'    => "Hi {customer_name},\n\nGood news! {product_name} is back in stock at {store_name}.\n\nShop now: {product_url}\n\nThank you!"
];

foreach ($settings as $key => $value) {
	$db->query("DELETE FROM `{$prefix}setting` WHERE `store_id` = 0 AND `code` = 'module_stock_notify' AND `key` = '" . $db->real_escape_string($key) . "'");
	$db->query("INSERT INTO `{$prefix}setting` SET `store_id` = 0, `code` = 'module_stock_notify', `key` = '" . $db->real_escape_string($key) . "', `value` = '" . $db->real_escape_string($value) . "', `serialized` = 0");
}

$event_code = 'stock_notify';

$db->query("DELETE FROM `{$prefix}event` WHERE `code` = '" . $db->real_escape_string($event_code) . "'");

$events = [
	[
		'description' => 'Back in stock notify widget',
		'trigger'     => 'catalog/view/common/footer/after',
		'action'      => 'extension/stock_notify/module/stock_notify.footer'
	],
	[
		'description' => 'Back in stock notify product grid icon',
		'trigger'     => 'catalog/view/journal3/products/after',
		'action'      => 'extension/stock_notify/event/products.productsAfter'
	],
	[
		'description' => 'Back in stock notify related/side products icon',
		'trigger'     => 'catalog/view/journal3/side_products/after',
		'action'      => 'extension/stock_notify/event/products.sideProductsAfter'
	],
	[
		'description' => 'Back in stock notify on product save (before)',
		'trigger'     => 'admin/model/catalog/product/editProduct/before',
		'action'      => 'extension/stock_notify/event/product.before'
	],
	[
		'description' => 'Back in stock notify on product save (after)',
		'trigger'     => 'admin/model/catalog/product/editProduct/after',
		'action'      => 'extension/stock_notify/event/product.after'
	]
];

foreach ($events as $event) {
	$db->query("INSERT INTO `{$prefix}event` SET
		`code` = '" . $db->real_escape_string($event_code) . "',
		`description` = '" . $db->real_escape_string($event['description']) . "',
		`trigger` = '" . $db->real_escape_string($event['trigger']) . "',
		`action` = '" . $db->real_escape_string($event['action']) . "',
		`status` = 1,
		`sort_order` = 0");
}

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

echo "Back In Stock Notify installed successfully.\n";
echo "Configure at Admin > Extensions > Modules > Back In Stock Notify\n";
