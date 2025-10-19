-- Newsletter Module Installation SQL
-- This script creates the required database tables for the Newsletter Manager module

-- Create newsletter table
CREATE TABLE IF NOT EXISTS `oc_newsletter` (
  `newsletter_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL COMMENT 'Internal newsletter name',
  `subject` varchar(255) NOT NULL COMMENT 'Email subject line',
  `message` text NOT NULL COMMENT 'Email message body',
  `to_type` varchar(20) NOT NULL DEFAULT 'newsletter' COMMENT 'Recipient type: newsletter, customer_all, customer_group',
  `store_id` int(11) NOT NULL DEFAULT '0' COMMENT 'Store ID for email sender',
  `customer_group_id` int(11) NOT NULL DEFAULT '0' COMMENT 'Customer group ID if to_type is customer_group',
  `status` varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'Status: draft, ready, sending, sent',
  `date_added` datetime NOT NULL COMMENT 'Date created',
  `date_sent` datetime DEFAULT NULL COMMENT 'Date sent',
  PRIMARY KEY (`newsletter_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Newsletter campaigns';

-- Create newsletter_product table
CREATE TABLE IF NOT EXISTS `oc_newsletter_product` (
  `newsletter_id` int(11) NOT NULL COMMENT 'Newsletter ID',
  `product_id` int(11) NOT NULL COMMENT 'Product ID to include in newsletter',
  PRIMARY KEY (`newsletter_id`, `product_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Products included in newsletters';

