-- POS Sync Log Table
CREATE TABLE IF NOT EXISTS `oc_pos_sync_log` (
  `sync_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `sync_date` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'running',
  `total_items` int(11) NOT NULL DEFAULT '0',
  `updated_products` int(11) NOT NULL DEFAULT '0',
  `updated_options` int(11) NOT NULL DEFAULT '0',
  `skipped_items` int(11) NOT NULL DEFAULT '0',
  `error_count` int(11) NOT NULL DEFAULT '0',
  `error_message` text DEFAULT NULL,
  `execution_time` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`sync_log_id`),
  KEY `sync_date` (`sync_date`),
  KEY `status` (`status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- POS Sync Log Details Table
CREATE TABLE IF NOT EXISTS `oc_pos_sync_log_detail` (
  `log_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `sync_log_id` int(11) NOT NULL,
  `sku` varchar(64) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `message` text DEFAULT NULL,
  `old_quantity` int(11) DEFAULT NULL,
  `new_quantity` int(11) DEFAULT NULL,
  `old_price` decimal(15,4) DEFAULT NULL,
  `new_price` decimal(15,4) DEFAULT NULL,
  `log_date` datetime NOT NULL,
  PRIMARY KEY (`log_detail_id`),
  KEY `sync_log_id` (`sync_log_id`),
  KEY `sku` (`sku`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

