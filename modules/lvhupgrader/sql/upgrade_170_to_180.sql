-- =============================================================================
-- Lush View Hotel — QloApps 1.7.0.0 -> 1.8.0.0 Complete Database Upgrade
-- Target engine : MariaDB 10.4+ / 11.4.x (table prefix: qlo_)
-- Safety rules  :
--   1. NEVER run against live `lushview_qloapp` directly.
--      Import a fresh backup into a NEW database (e.g. `lushview_qlo18`)
--      and run this script on the NEW database copy.
--   2. Additive & idempotent: safe to re-run; preserves all legacy tables
--      (`qlo_htl_features*`, `qlo_htl_bed_type*`) and all 1,934+ bookings.
--   3. Defers the 5 tables & 8 columns owned by uninstalled 1.8.0 modules
--      (`qlocmconnector`, `qlogooglehotelconnector`, `qlocrontaskmanager`)
--      so their unguarded `ALTER TABLE ... ADD COLUMN` calls succeed cleanly.
-- =============================================================================

SET NAMES utf8mb4;
SET @old_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = '';

-- -----------------------------------------------------------------------------
-- PART 1: Widen password columns (bcrypt 60 chars) & payment amounts (20,6)
-- -----------------------------------------------------------------------------
ALTER TABLE `qlo_employee` MODIFY `passwd` varchar(60) NOT NULL;
ALTER TABLE `qlo_customer` MODIFY `passwd` varchar(60) NOT NULL;
ALTER TABLE `qlo_referrer` MODIFY `passwd` varchar(60) DEFAULT NULL;
ALTER TABLE `qlo_order_payment` MODIFY `amount` decimal(20,6) NOT NULL;
ALTER TABLE `qlo_order_payment_detail` MODIFY `amount` decimal(20,6) NOT NULL;

-- -----------------------------------------------------------------------------
-- PART 2: Create all 27 new QloApps 1.8.0 Core (13) & HRS (14) tables
--         Explicit utf8mb3_general_ci matches existing 300 tables (0 collation mix)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qlo_business_source` (
  `id_business_source` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `unremovable` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_business_source`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_business_source_lang` (
  `id_business_source` int(10) unsigned NOT NULL,
  `id_lang` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id_business_source`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_source` (
  `id_source` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_business_source` int(10) unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `unremovable` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_source`),
  UNIQUE KEY `code` (`code`),
  KEY `id_business_source` (`id_business_source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_source_lang` (
  `id_source` int(10) unsigned NOT NULL,
  `id_lang` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id_source`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_room_type_selling_object` (
  `id_room_type_selling_object` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_room_type_selling_object`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_room_type_selling_object_lang` (
  `id_room_type_selling_object` int(10) unsigned NOT NULL,
  `id_lang` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `plural_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_room_type_selling_object`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_cart_rule_hotel` (
  `id_cart_rule` int(10) unsigned NOT NULL,
  `id_hotel` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_cart_rule`, `id_hotel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_tax_configuration` (
  `id_tax` int(11) unsigned NOT NULL,
  `tax_value` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `calculation_type` tinyint(1) NOT NULL DEFAULT 0,
  `per_night` tinyint(1) NOT NULL DEFAULT 1,
  `per_person` tinyint(1) NOT NULL DEFAULT 0,
  `has_tiered_pricing` tinyint(1) NOT NULL DEFAULT 0,
  `apply_on_child` tinyint(1) NOT NULL DEFAULT 0,
  `has_child_age_range` tinyint(1) NOT NULL DEFAULT 0,
  `child_calculation_type` tinyint(1) NOT NULL DEFAULT 0,
  `has_multiple_valid_ranges` tinyint(1) NOT NULL DEFAULT 0,
  `special_days` text DEFAULT NULL,
  PRIMARY KEY (`id_tax`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_tax_validity_range` (
  `id_tax_validity_range` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id_tax` int(11) unsigned NOT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  PRIMARY KEY (`id_tax_validity_range`),
  KEY `id_tax` (`id_tax`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_tax_price_tier` (
  `id_tax_price_tier` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id_tax` int(11) unsigned NOT NULL,
  `min_amount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `max_amount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `tax_value` decimal(20,6) NOT NULL DEFAULT 0.000000,
  PRIMARY KEY (`id_tax_price_tier`),
  KEY `id_tax` (`id_tax`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_tax_child_range` (
  `id_tax_child_range` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id_tax` int(11) unsigned NOT NULL,
  `min_age` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_age` tinyint(3) unsigned NOT NULL DEFAULT 17,
  `tax_value` decimal(20,6) NOT NULL DEFAULT 0.000000,
  PRIMARY KEY (`id_tax_child_range`),
  KEY `id_tax` (`id_tax`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_order_tax_detail` (
  `id_order_tax_detail` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id_order` int(11) unsigned NOT NULL,
  `id_order_detail` int(11) unsigned NOT NULL,
  `id_htl_booking` int(11) unsigned NOT NULL,
  `id_service_product_order_detail` int(11) unsigned NOT NULL DEFAULT 0,
  `id_tax` int(11) unsigned NOT NULL,
  `unit_amount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `total_amount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_order_tax_detail`),
  KEY `id_order` (`id_order`),
  KEY `id_order_detail` (`id_order_detail`),
  KEY `id_htl_booking` (`id_htl_booking`),
  KEY `id_service_product_order_detail` (`id_service_product_order_detail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_order_tax_exemption` (
  `id_order_tax_exemption` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id_htl_booking` int(11) unsigned NOT NULL DEFAULT 0,
  `id_service_product_order_detail` int(11) unsigned NOT NULL DEFAULT 0,
  `id_order` int(11) unsigned NOT NULL,
  `id_employee` int(11) unsigned NOT NULL,
  `note` text DEFAULT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_order_tax_exemption`),
  KEY `id_htl_booking` (`id_htl_booking`),
  KEY `id_service_product_order_detail` (`id_service_product_order_detail`),
  KEY `id_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_image_category` (
  `id_htl_image_category` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_htl_image_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_image_category_lang` (
  `id_htl_image_category` int(10) unsigned NOT NULL,
  `id_lang` int(10) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`id_htl_image_category`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_amenity` (
  `id_amenity` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_parent` int(10) unsigned NOT NULL,
  `position` int(10) unsigned NOT NULL,
  `active` int(2) NOT NULL DEFAULT 0,
  `logo_type` varchar(10) NOT NULL DEFAULT 'icon',
  `logo` varchar(255) NOT NULL DEFAULT '',
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_amenity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_amenity_lang` (
  `id_amenity` int(10) unsigned NOT NULL,
  `id_lang` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_amenity`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_property_type` (
  `id_htl_property_type` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_htl_property_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_property_type_lang` (
  `id_htl_property_type` int(10) unsigned NOT NULL,
  `id_lang` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `plural_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_htl_property_type`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_branch_amenity` (
  `id_branch_amenity` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_hotel` int(10) unsigned NOT NULL,
  `amenity_id` int(10) unsigned NOT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_branch_amenity`),
  KEY `amenity_id` (`amenity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_room_type_amenity` (
  `id_room_type_amenity` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_product` int(10) unsigned NOT NULL,
  `amenity_id` int(10) unsigned NOT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_room_type_amenity`),
  KEY `id_product` (`id_product`),
  KEY `amenity_id` (`amenity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_booking_status` (
  `id_booking_status` int(11) NOT NULL AUTO_INCREMENT,
  `color` varchar(32) NOT NULL,
  `is_terminal` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_booking_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_booking_status_lang` (
  `id_booking_status` int(11) NOT NULL,
  `id_lang` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id_booking_status`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_booking_status_history` (
  `id_booking_status_history` int(11) NOT NULL AUTO_INCREMENT,
  `id_htl_booking` int(11) NOT NULL,
  `id_status_from` int(11) DEFAULT NULL,
  `id_status_to` int(11) NOT NULL,
  `id_employee` int(11) DEFAULT NULL,
  `id_customer` int(11) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_booking_status_history`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_header_image` (
  `id_header_image` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(512) NOT NULL,
  `description_color` varchar(7) NOT NULL DEFAULT '#ffffff',
  `description_font_size` tinyint(3) unsigned NOT NULL DEFAULT 16,
  `description_font_weight` varchar(10) NOT NULL DEFAULT '400',
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `active` tinyint(1) unsigned NOT NULL DEFAULT 1,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_header_image`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_header_image_lang` (
  `id_header_image` int(10) unsigned NOT NULL,
  `id_lang` int(11) NOT NULL,
  `title` varchar(512) NOT NULL DEFAULT '',
  `description` varchar(512) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_header_image`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `qlo_htl_connected_room` (
  `id_connected_room` int(11) NOT NULL AUTO_INCREMENT,
  `id_room` int(11) NOT NULL,
  `id_room_connected` int(11) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL,
  PRIMARY KEY (`id_connected_room`),
  UNIQUE KEY `uniq_room_connection` (`id_room`, `id_room_connected`),
  KEY `idx_id_room` (`id_room`),
  KEY `idx_id_room_connected` (`id_room_connected`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- -----------------------------------------------------------------------------
-- PART 3: Add all 23 missing QloApps 1.8.0 Core (16) & HRS (7) columns
--         Uses MariaDB `ADD COLUMN IF NOT EXISTS` for full idempotency
-- -----------------------------------------------------------------------------
ALTER TABLE `qlo_access` ADD COLUMN IF NOT EXISTS `kpi` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `qlo_cart_rule` ADD COLUMN IF NOT EXISTS `hotel_restriction` tinyint(1) unsigned NOT NULL DEFAULT 0;
ALTER TABLE `qlo_customer` ADD COLUMN IF NOT EXISTS `id_country` int(10) NOT NULL DEFAULT 0;
ALTER TABLE `qlo_feature` ADD COLUMN IF NOT EXISTS `logo_type` varchar(10) NOT NULL DEFAULT 'image';
ALTER TABLE `qlo_feature` ADD COLUMN IF NOT EXISTS `logo` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE `qlo_feature` ADD COLUMN IF NOT EXISTS `is_featured` tinyint(1) unsigned NOT NULL DEFAULT 0;
ALTER TABLE `qlo_order_detail` ADD COLUMN IF NOT EXISTS `id_tourism_tax_rule_group` int(11) unsigned DEFAULT 0;
ALTER TABLE `qlo_order_payment_detail` ADD COLUMN IF NOT EXISTS `receipt_number` int(10) NOT NULL DEFAULT 0;
ALTER TABLE `qlo_order_return` ADD COLUMN IF NOT EXISTS `event_type` tinyint(1) unsigned NOT NULL DEFAULT 0;
ALTER TABLE `qlo_order_slip` ADD COLUMN IF NOT EXISTS `remark` text DEFAULT NULL;
ALTER TABLE `qlo_orders` ADD COLUMN IF NOT EXISTS `id_source` int(10) unsigned DEFAULT NULL;
ALTER TABLE `qlo_product` ADD COLUMN IF NOT EXISTS `id_tourism_tax_rules_group` int(11) unsigned NOT NULL DEFAULT 0;
ALTER TABLE `qlo_product` ADD COLUMN IF NOT EXISTS `id_selling_object` int(10) unsigned DEFAULT NULL;
ALTER TABLE `qlo_product_shop` ADD COLUMN IF NOT EXISTS `id_tourism_tax_rules_group` int(11) unsigned NOT NULL DEFAULT 0;
ALTER TABLE `qlo_tax` ADD COLUMN IF NOT EXISTS `is_tourism_tax` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `qlo_tax_rules_group` ADD COLUMN IF NOT EXISTS `is_tourism_tax_rule_group` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `qlo_htl_booking_detail` ADD COLUMN IF NOT EXISTS `selling_object_name` varchar(255) DEFAULT NULL;
ALTER TABLE `qlo_htl_booking_detail` ADD COLUMN IF NOT EXISTS `selling_object_plural_name` varchar(255) DEFAULT NULL;
ALTER TABLE `qlo_htl_booking_detail` ADD COLUMN IF NOT EXISTS `property_type_name` varchar(255) DEFAULT NULL;
ALTER TABLE `qlo_htl_branch_info` ADD COLUMN IF NOT EXISTS `id_property_type` int(10) unsigned NOT NULL DEFAULT 0;
ALTER TABLE `qlo_htl_branch_info` ADD COLUMN IF NOT EXISTS `tourism_tax_collection_type` tinyint(1) unsigned NOT NULL DEFAULT 0;
ALTER TABLE `qlo_htl_image` ADD COLUMN IF NOT EXISTS `id_htl_image_category` int(10) unsigned DEFAULT NULL;
ALTER TABLE `qlo_htl_room_type` ADD COLUMN IF NOT EXISTS `id_selling_object` int(10) unsigned DEFAULT NULL;

-- -----------------------------------------------------------------------------
-- PART 4: Seed canonical QloApps 1.8.0 vocabulary tables & migrate Amenities
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `qlo_business_source` (`id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (1, 'DIRECT', 0, 1, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_business_source_lang` (`id_business_source`, `id_lang`, `name`) SELECT 1, `id_lang`, 'Direct' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_business_source` (`id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (2, 'OTA', 1, 1, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_business_source_lang` (`id_business_source`, `id_lang`, `name`) SELECT 2, `id_lang`, 'OTA' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_business_source` (`id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (3, 'CORPORATE', 2, 1, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_business_source_lang` (`id_business_source`, `id_lang`, `name`) SELECT 3, `id_lang`, 'Corporate' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_business_source` (`id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (4, 'TRAVEL_AGENT', 3, 1, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_business_source_lang` (`id_business_source`, `id_lang`, `name`) SELECT 4, `id_lang`, 'Travel Agent' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_business_source` (`id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (5, 'GROUP', 4, 1, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_business_source_lang` (`id_business_source`, `id_lang`, `name`) SELECT 5, `id_lang`, 'Group' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_business_source` (`id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (6, 'API', 5, 1, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_business_source_lang` (`id_business_source`, `id_lang`, `name`) SELECT 6, `id_lang`, 'API' FROM `qlo_lang`;

INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (1, 1, 'DIRECT_WEBSITE', 0, 1, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 1, `id_lang`, 'Direct Website' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (2, 1, 'WALKIN', 1, 1, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 2, `id_lang`, 'Walk-in' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (3, 1, 'PHONE', 2, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 3, `id_lang`, 'Phone' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (4, 1, 'EMAIL', 3, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 4, `id_lang`, 'Email' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (5, 1, 'SOCIAL_MEDIA', 4, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 5, `id_lang`, 'Social Media' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (6, 1, 'WHATSAPP', 5, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 6, `id_lang`, 'WhatsApp' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (7, 2, 'BOOKING_COM', 0, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 7, `id_lang`, 'Booking.com' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (8, 2, 'EXPEDIA', 1, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 8, `id_lang`, 'Expedia' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (9, 2, 'GOMMT', 2, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 9, `id_lang`, 'GoMMT (GoIbibo/MakeMyTrip)' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (10, 2, 'AGODA', 3, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 10, `id_lang`, 'Agoda' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (11, 2, 'AIRBNB', 4, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 11, `id_lang`, 'Airbnb' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (12, 2, 'TRIP_COM', 5, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 12, `id_lang`, 'Trip.com (Ctrip)' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (13, 2, 'BAKUUN', 6, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 13, `id_lang`, 'Bakuun' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (14, 2, 'DESPEGAR', 7, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 14, `id_lang`, 'Despegar' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (15, 2, 'YATRA', 8, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 15, `id_lang`, 'Yatra' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (16, 2, 'HOSTELWORLD', 9, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 16, `id_lang`, 'Hostelworld' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (17, 2, 'HYPERGUEST', 10, 0, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 17, `id_lang`, 'HyperGuest' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES (18, 6, 'API_ORDER', 0, 1, 1, 0, NOW(), NOW());
INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT 18, `id_lang`, 'API Order' FROM `qlo_lang`;

INSERT IGNORE INTO `qlo_htl_booking_status` (`id_booking_status`, `color`, `is_terminal`) VALUES (1, '#4169E1', 0);
INSERT IGNORE INTO `qlo_htl_booking_status_lang` (`id_booking_status`, `id_lang`, `name`) SELECT 1, `id_lang`, 'Assigned' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_booking_status` (`id_booking_status`, `color`, `is_terminal`) VALUES (2, '#17A2B8', 0);
INSERT IGNORE INTO `qlo_htl_booking_status_lang` (`id_booking_status`, `id_lang`, `name`) SELECT 2, `id_lang`, 'Checked-in' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_booking_status` (`id_booking_status`, `color`, `is_terminal`) VALUES (3, '#108510', 0);
INSERT IGNORE INTO `qlo_htl_booking_status_lang` (`id_booking_status`, `id_lang`, `name`) SELECT 3, `id_lang`, 'Checked-out' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_booking_status` (`id_booking_status`, `color`, `is_terminal`) VALUES (4, '#FF8C00', 0);
INSERT IGNORE INTO `qlo_htl_booking_status_lang` (`id_booking_status`, `id_lang`, `name`) SELECT 4, `id_lang`, 'No-show' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_booking_status` (`id_booking_status`, `color`, `is_terminal`) VALUES (5, '#DC143C', 1);
INSERT IGNORE INTO `qlo_htl_booking_status_lang` (`id_booking_status`, `id_lang`, `name`) SELECT 5, `id_lang`, 'Cancelled' FROM `qlo_lang`;

INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (1, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 1, `id_lang`, 'Hotel', 'Hotels' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (2, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 2, `id_lang`, 'Motel', 'Motels' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (3, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 3, `id_lang`, 'Resort', 'Resorts' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (4, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 4, `id_lang`, 'Hostel', 'Hostels' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (5, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 5, `id_lang`, 'Guest House', 'Guest Houses' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (6, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 6, `id_lang`, 'Bed & Breakfast', 'Bed & Breakfasts' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (7, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 7, `id_lang`, 'Lodge', 'Lodges' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (8, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 8, `id_lang`, 'Apartment', 'Apartments' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (9, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 9, `id_lang`, 'Vacation Rental', 'Vacation Rentals' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (10, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 10, `id_lang`, 'Homestay', 'Homestays' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (11, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 11, `id_lang`, 'Co-Living', 'Co-Livings' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (12, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 12, `id_lang`, 'Camping', 'Campings' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (13, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 13, `id_lang`, 'Glamping', 'Glampings' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (14, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 14, `id_lang`, 'Farm Stay', 'Farm Stays' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (15, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 15, `id_lang`, 'Houseboat', 'Houseboats' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES (16, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT 16, `id_lang`, 'Boat Stay', 'Boat Stays' FROM `qlo_lang`;

INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (1, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 1, `id_lang`, 'Room', 'Rooms' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (2, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 2, `id_lang`, 'Bed', 'Beds' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (3, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 3, `id_lang`, 'Apartment', 'Apartments' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (4, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 4, `id_lang`, 'Villa', 'Villas' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (5, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 5, `id_lang`, 'Cottage', 'Cottages' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (6, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 6, `id_lang`, 'Bungalow', 'Bungalows' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (7, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 7, `id_lang`, 'House', 'Houses' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (8, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 8, `id_lang`, 'Chalet', 'Chalets' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (9, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 9, `id_lang`, 'Cabin', 'Cabins' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (10, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 10, `id_lang`, 'Studio', 'Studios' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (11, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 11, `id_lang`, 'Condo', 'Condos' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (12, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 12, `id_lang`, 'Penthouse', 'Penthouses' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (13, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 13, `id_lang`, 'Tent', 'Tents' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (14, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 14, `id_lang`, 'Camping Pitch', 'Camping Pitches' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (15, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 15, `id_lang`, 'Caravan Site', 'Caravan Sites' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (16, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 16, `id_lang`, 'RV Site', 'RV Sites' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (17, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 17, `id_lang`, 'Pod', 'Pods' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (18, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 18, `id_lang`, 'Capsule', 'Capsules' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (19, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 19, `id_lang`, 'Treehouse', 'Treehouses' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (20, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 20, `id_lang`, 'Hut', 'Huts' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (21, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 21, `id_lang`, 'Yurt', 'Yurts' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (22, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 22, `id_lang`, 'Igloo', 'Igloos' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (23, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 23, `id_lang`, 'Houseboat', 'Houseboats' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (24, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 24, `id_lang`, 'Boat Cabin', 'Boat Cabins' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (25, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 25, `id_lang`, 'Overwater Bungalow', 'Overwater Bungalows' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (26, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 26, `id_lang`, 'Heritage Room', 'Heritage Rooms' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (27, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 27, `id_lang`, 'Holiday Home', 'Holiday Homes' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (28, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 28, `id_lang`, 'Farm Stay Unit', 'Farm Stay Units' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (29, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 29, `id_lang`, 'Cave Room', 'Cave Rooms' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (30, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 30, `id_lang`, 'Container Stay', 'Container Stays' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (31, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 31, `id_lang`, 'Train Cabin', 'Train Cabins' FROM `qlo_lang`;
INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES (32, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT 32, `id_lang`, 'Motorhome', 'Motorhomes' FROM `qlo_lang`;

-- Migrate live 1.7.0 `qlo_htl_features*` into 1.8.0 `qlo_htl_amenity*` (non-destructive)
INSERT IGNORE INTO `qlo_htl_amenity` (`id_amenity`, `id_parent`, `position`, `active`, `logo_type`, `logo`, `date_add`, `date_upd`)
SELECT `id`, `parent_feature_id`, `position`, `active`, 'icon', '', `date_add`, `date_upd` FROM `qlo_htl_features`;
INSERT IGNORE INTO `qlo_htl_amenity_lang` (`id_amenity`, `id_lang`, `name`)
SELECT `id`, `id_lang`, `name` FROM `qlo_htl_features_lang`;
INSERT INTO `qlo_htl_branch_amenity` (`id_hotel`, `amenity_id`, `is_featured`, `date_add`, `date_upd`)
SELECT bf.`id_hotel`, CAST(bf.`feature_id` AS UNSIGNED), 0, bf.`date_add`, bf.`date_upd`
FROM `qlo_htl_branch_features` bf
WHERE CAST(bf.`feature_id` AS UNSIGNED) > 0
  AND NOT EXISTS (
      SELECT 1 FROM `qlo_htl_branch_amenity` ba
      WHERE ba.`id_hotel` = bf.`id_hotel` AND ba.`amenity_id` = CAST(bf.`feature_id` AS UNSIGNED)
  );

-- Backfill sensible defaults on existing hotel rows
UPDATE `qlo_access` SET `kpi` = 1 WHERE `id_profile` = 1;
UPDATE `qlo_htl_branch_info` SET `id_property_type` = 1 WHERE `id_property_type` = 0;
UPDATE `qlo_htl_room_type` SET `id_selling_object` = 1 WHERE `id_selling_object` IS NULL;
UPDATE `qlo_product` SET `id_selling_object` = 1 WHERE `id_selling_object` IS NULL AND `id_product` IN (SELECT `id_product` FROM `qlo_htl_room_type`);
UPDATE `qlo_order_payment_detail` SET `receipt_number` = `id_order_payment_detail` WHERE `receipt_number` = 0;
INSERT IGNORE INTO `qlo_htl_settings_link` (`id_settings_link`, `icon`, `link`, `new_window`, `position`, `unremovable`, `active`, `date_add`, `date_upd`)
VALUES (5, 'icon-picture', 'index.php?controller=AdminHotelHeaderImage', 0, 6, 0, 1, NOW(), NOW());
INSERT IGNORE INTO `qlo_htl_settings_link_lang` (`id_settings_link`, `id_lang`, `name`, `hint`)
SELECT 5, `id_lang`, 'Landing Page Header Media', 'Configure and manage header images or videos displayed on the home page.' FROM `qlo_lang`;

-- -----------------------------------------------------------------------------
-- PART 5: Register new QloApps 1.8.0 Hooks, Admin Tabs & SuperAdmin Permissions
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayPayment', 'Payment', 'This hook displays new elements on the payment page', 1, 1);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionValidateOrder', 'New orders', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayMaintenance', 'Maintenance Page', 'This hook displays new elements on the maintenance page', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionPaymentConfirmation', 'Payment confirmation', 'This hook displays new elements after the payment is validated', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayPaymentReturn', 'Payment return', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionUpdateQuantity', 'Quantity update', 'Quantity is updated only when a customer effectively places their order', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayRightColumn', 'Right column blocks', 'This hook displays new elements in the right-hand column', 1, 1);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayLeftColumn', 'Left column blocks', 'This hook displays new elements in the left-hand column', 1, 1);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayHome', 'Homepage content', 'This hook displays new elements on the homepage', 1, 1);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('Header', 'Pages html head section', 'This hook adds additional elements in the head section of your pages (head section of html)', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionCartSave', 'Cart creation and update', 'This hook is displayed when a product is added to the cart or if the cart\'s content is modified', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionAuthentication', 'Successful customer authentication', 'This hook is displayed after a customer successfully signs in', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionProductAdd', 'Product creation', 'This hook is displayed after a product is created', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionProductUpdate', 'Product update', 'This hook is displayed after a product has been updated', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayTop', 'Top of pages', 'This hook displays additional elements at the top of your pages', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayRightColumnProduct', 'New elements on the product page (right column)', 'This hook displays new elements in the right-hand column of the product page', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionProductDelete', 'Product deletion', 'This hook is called when a product is deleted', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayFooterProduct', 'Product footer', 'This hook adds new blocks under the product\'s description', 1, 1);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayInvoice', 'Invoice', 'This hook displays new blocks on the invoice (order)', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionOrderStatusUpdate', 'Order status update - Event', 'This hook launches modules when the status of an order changes.', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAdminOrder', 'Display new elements in the Back Office, tab AdminOrder', 'This hook launches modules when the AdminOrder tab is displayed in the Back Office', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAdminOrderTabOrder', 'Display new elements in Back Office, AdminOrder, panel Order', 'This hook launches modules when the AdminOrder tab is displayed in the Back Office and extends / override Order panel tabs', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAdminOrderTabShip', 'Display new elements in Back Office, AdminOrder, panel Shipping', 'This hook launches modules when the AdminOrder tab is displayed in the Back Office and extends / override Shipping panel tabs', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAdminOrderContentOrder', 'Display new elements in Back Office, AdminOrder, panel Order', 'This hook launches modules when the AdminOrder tab is displayed in the Back Office and extends / override Order panel content', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAdminOrderContentShip', 'Display new elements in Back Office, AdminOrder, panel Shipping', 'This hook launches modules when the AdminOrder tab is displayed in the Back Office and extends / override Shipping panel content', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayFooter', 'Footer', 'This hook displays new blocks in the footer', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayPDFInvoice', 'PDF Invoice', 'This hook allows you to display additional information on PDF invoices', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayInvoiceLegalFreeText', 'PDF Invoice - Legal Free Text', 'This hook allows you to modify the legal free text on PDF invoices', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAdminCustomers', 'Display new elements in the Back Office, tab AdminCustomers', 'This hook launches modules when the AdminCustomers tab is displayed in the Back Office', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayOrderConfirmation', 'Order confirmation page', 'This hook is called within an order\'s confirmation page', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionCustomerAccountAdd', 'Successful customer account creation', 'This hook is called when a new customer creates an account successfully', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayCustomerAccount', 'Customer account displayed in Front Office', 'This hook displays new elements on the customer account page', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayCustomerIdentityForm', 'Customer identity form displayed in Front Office', 'This hook displays new elements on the form to update a customer identity', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionOrderSlipAdd', 'Order slip creation', 'This hook is called when a new credit slip is added regarding client order', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayProductTab', 'Tabs on product page', 'This hook is called on the product page\'s tab', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayProductTabContent', 'Tabs content on the product page', 'This hook is called on the product page\'s tab', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayShoppingCartFooter', 'Shopping cart footer', 'This hook displays some specific information on the shopping cart\'s page', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayCustomerAccountForm', 'Customer account creation form', 'This hook displays some information on the form to create a customer account', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAdminStatsModules', 'Stats - Modules', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAdminStatsGraphEngine', 'Graph engines', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionOrderReturn', 'Returned product', 'This hook is displayed when a customer returns a product ', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayProductButtons', 'Product page actions', 'This hook adds new action buttons on the product page', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayBackOfficeHome', 'Administration panel homepage', 'This hook is displayed on the admin panel\'s homepage', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAdminStatsGridEngine', 'Grid engines', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionWatermark', 'Watermark', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionProductCancel', 'Product cancelled', 'This hook is called when you cancel a product in an order', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayLeftColumnProduct', 'New elements on the product page (left column)', 'This hook displays new elements in the left-hand column of the product page', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionProductOutOfStock', 'Out-of-stock product', 'This hook displays new action buttons if a product is out of stock', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionProductAttributeUpdate', 'Product attribute update', 'This hook is displayed when a product\'s attribute is updated', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayCarrierList', 'Extra carrier (module mode)', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayShoppingCart', 'Shopping cart - Additional button', 'This hook displays new action buttons within the shopping cart', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionSearch', 'Search', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayBeforePayment', 'Redirect during the order process', 'This hook redirects the user to the module instead of displaying payment modules', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionCarrierUpdate', 'Carrier Update', 'This hook is called when a carrier is updated', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionOrderStatusPostUpdate', 'Post update of order status', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayCustomerAccountFormTop', 'Block above the form for create an account', 'This hook is displayed above the customer\'s account creation form', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayBackOfficeHeader', 'Administration panel header', 'This hook is displayed in the header of the admin panel', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayBackOfficeTop', 'Administration panel hover the tabs', 'This hook is displayed on the roll hover of the tabs within the admin panel', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayBackOfficeFooter', 'Administration panel footer', 'This hook is displayed within the admin panel\'s footer', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionProductAttributeDelete', 'Product attribute deletion', 'This hook is displayed when a product\'s attribute is deleted', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionCarrierProcess', 'Carrier process', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionOrderDetail', 'Order detail', 'This hook is used to set the follow-up in Smarty when an order\'s detail is called', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayBeforeCarrier', 'Before carriers list', 'This hook is displayed before the carrier list in Front Office', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayOrderDetail', 'Order detail', 'This hook is displayed within the order\'s details in Front Office', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionPaymentCCAdd', 'Payment CC added', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayProductComparison', 'Extra product comparison', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionCategoryAdd', 'Category creation', 'This hook is displayed when a category is created', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionCategoryUpdate', 'Category modification', 'This hook is displayed when a category is modified', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionCategoryDelete', 'Category deletion', 'This hook is displayed when a category is deleted', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionBeforeAuthentication', 'Before authentication', 'This hook is displayed before the customer\'s authentication', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionSubmitIdentityBefore', 'Before identity modification', 'This hook is called before customer\'s identity modification', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayCartRoomImageAfter', 'After cart room image on checkout page', 'This hook is displayed after cart room image on checkout page', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayPaymentTop', 'Top of payment page', 'This hook is displayed at the top of the payment page', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionHtaccessCreate', 'After htaccess creation', 'This hook is displayed after the htaccess creation', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionAdminMetaSave', 'After saving the configuration in AdminMeta', 'This hook is displayed after saving the configuration in AdminMeta', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAttributeGroupForm', 'Add fields to the form \'attribute group\'', 'This hook adds fields to the form \'attribute group\'', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionAttributeGroupSave', 'Saving an attribute group', 'This hook is called while saving an attributes group', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionAttributeGroupDelete', 'Deleting attribute group', 'This hook is called while deleting an attributes  group', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayFeatureForm', 'Add fields to the form \'feature\'', 'This hook adds fields to the form \'feature\'', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionFeatureSave', 'Saving attributes\' features', 'This hook is called while saving an attributes features', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionFeatureDelete', 'Deleting attributes\' features', 'This hook is called while deleting an attributes features', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionProductSave', 'Saving products', 'This hook is called while saving products', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionProductListOverride', 'Assign a products list to a category', 'This hook assigns a products list to a category', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAttributeGroupPostProcess', 'On post-process in admin attribute group', 'This hook is called on post-process in admin attribute group', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayFeaturePostProcess', 'On post-process in admin feature', 'This hook is called on post-process in admin feature', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayFeatureValueForm', 'Add fields to the form \'feature value\'', 'This hook adds fields to the form \'feature value\'', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayFeatureValuePostProcess', 'On post-process in admin feature value', 'This hook is called on post-process in admin feature value', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionFeatureValueDelete', 'Deleting attributes\' features\' values', 'This hook is called while deleting an attributes features value', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionFeatureValueSave', 'Saving an attributes features value', 'This hook is called while saving an attributes features value', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayAttributeForm', 'Add fields to the form \'attribute value\'', 'This hook adds fields to the form \'attribute value\'', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionAttributePostProcess', 'On post-process in admin feature value', 'This hook is called on post-process in admin feature value', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionAttributeDelete', 'Deleting an attributes features value', 'This hook is called while deleting an attributes features value', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionAttributeSave', 'Saving an attributes features value', 'This hook is called while saving an attributes features value', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionTaxManager', 'Tax Manager Factory', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayMyAccountBlock', 'My account block', 'This hook displays extra information within the \'my account\' block"', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionModuleInstallBefore', 'actionModuleInstallBefore', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionModuleInstallAfter', 'actionModuleInstallAfter', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayHomeTab', 'Home Page Tabs', 'This hook displays new elements on the homepage tabs', 1, 1);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayHomeTabContent', 'Home Page Tabs Content', 'This hook displays new elements on the homepage tabs content', 1, 1);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayTopColumn', 'Top column blocks', 'This hook displays new elements in the top of columns', 1, 1);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayBackOfficeCategory', 'Display new elements in the Back Office, tab AdminCategories', 'This hook launches modules when the AdminCategories tab is displayed in the Back Office', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayProductListFunctionalButtons', 'Display new elements in the Front Office, products list', 'This hook launches modules when the products list is displayed in the Front Office', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayNav', 'Navigation', '', 1, 1);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayOverrideTemplate', 'Change the default template of current controller', '', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionAdminLoginControllerSetMedia', 'Set media on admin login page header', 'This hook is called after adding media to admin login page header', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionOrderEdited', 'Order edited', 'This hook is called when an order is edited.', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionEmailAddBeforeContent', 'Add extra content before mail content', 'This hook is called just before fetching mail template', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('actionEmailAddAfterContent', 'Add extra content after mail content', 'This hook is called just after fetching mail template', 1, 0);
INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ('displayCartExtraProductActions', 'Extra buttons in shopping cart', 'This hook adds extra buttons to the product lines, in the shopping cart', 1, 0);

INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT COALESCE((SELECT t2.`id_tab` FROM (SELECT `id_tab`, `class_name` FROM `qlo_tab`) t2 WHERE t2.`class_name` = 'AdminCatalog' LIMIT 1), 0), 'AdminRoomTypeSellingObjects', NULL, 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminRoomTypeSellingObjects');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Room Type Selling Objects' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminRoomTypeSellingObjects';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminRoomTypeSellingObjects';
INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT COALESCE((SELECT t2.`id_tab` FROM (SELECT `id_tab`, `class_name` FROM `qlo_tab`) t2 WHERE t2.`class_name` = 'AdminParentOrders' LIMIT 1), 0), 'AdminPaymentReceipts', NULL, 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminPaymentReceipts');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Payment Receipts' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminPaymentReceipts';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminPaymentReceipts';
INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT COALESCE((SELECT t2.`id_tab` FROM (SELECT `id_tab`, `class_name` FROM `qlo_tab`) t2 WHERE t2.`class_name` = 'AdminParentOrders' LIMIT 1), 0), 'AdminSources', NULL, 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminSources');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Sources' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminSources';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminSources';
INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT COALESCE((SELECT t2.`id_tab` FROM (SELECT `id_tab`, `class_name` FROM `qlo_tab`) t2 WHERE t2.`class_name` = 'AdminParentOrders' LIMIT 1), 0), 'AdminGuestRegistration', NULL, 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminGuestRegistration');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Guest Registration' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminGuestRegistration';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminGuestRegistration';
INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT COALESCE((SELECT t2.`id_tab` FROM (SELECT `id_tab`, `class_name` FROM `qlo_tab`) t2 WHERE t2.`class_name` = 'AdminParentOrders' LIMIT 1), 0), 'AdminRoomStatuses', 'hotelreservationsystem', 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminRoomStatuses');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Room Statuses' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminRoomStatuses';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminRoomStatuses';
INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT COALESCE((SELECT t2.`id_tab` FROM (SELECT `id_tab`, `class_name` FROM `qlo_tab`) t2 WHERE t2.`class_name` = 'AdminHotelReservationSystemManagement' LIMIT 1), 0), 'AdminHotelPropertyTypes', 'hotelreservationsystem', 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminHotelPropertyTypes');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Manage Property Types' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminHotelPropertyTypes';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminHotelPropertyTypes';
INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT COALESCE((SELECT t2.`id_tab` FROM (SELECT `id_tab`, `class_name` FROM `qlo_tab`) t2 WHERE t2.`class_name` = 'AdminHotelReservationSystemManagement' LIMIT 1), 0), 'AdminHotelAmenities', 'hotelreservationsystem', 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminHotelAmenities');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Manage Amenities' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminHotelAmenities';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminHotelAmenities';
INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT -1, 'AdminHotelHeaderImage', 'hotelreservationsystem', 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminHotelHeaderImage');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Header Image Configuration' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminHotelHeaderImage';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminHotelHeaderImage';
INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT -1, 'AdminBookingDocument', 'hotelreservationsystem', 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminBookingDocument');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Booking Documents' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminBookingDocument';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminBookingDocument';
INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) SELECT -1, 'AdminHotelImageCategory', 'hotelreservationsystem', 10, 1, 0 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = 'AdminHotelImageCategory');
INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) SELECT t.`id_tab`, l.`id_lang`, 'Hotel Image Category' FROM `qlo_tab` t CROSS JOIN `qlo_lang` l WHERE t.`class_name` = 'AdminHotelImageCategory';
INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t WHERE t.`class_name` = 'AdminHotelImageCategory';
UPDATE `qlo_tab` SET `active` = 0 WHERE `class_name` = 'AdminHotelFeatures';

-- -----------------------------------------------------------------------------
-- PART 6: Orphan Module Cleanup, Security Hardening & Lush View Hotel Config
-- -----------------------------------------------------------------------------
-- Remove DB-only orphan `cashpayment` (0 orders used it) & removed 1.7.0 modules
DELETE FROM `qlo_hook_module` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));
DELETE FROM `qlo_hook_module_exceptions` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));
DELETE FROM `qlo_module_shop` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));
DELETE FROM `qlo_module_group` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));
DELETE FROM `qlo_module_country` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));
DELETE FROM `qlo_module_currency` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));
UPDATE `qlo_tab` SET `active` = 0 WHERE `module` IN ('qloautoupgrade', 'qlochannelmanagerconnector');
DELETE FROM `qlo_module` WHERE `name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector');

-- Keep `qlohotelreview` disabled by default (0 live reviews; CVE-2025-67325 defense-in-depth)
UPDATE `qlo_module` SET `active` = 0 WHERE `name` = 'qlohotelreview';

-- Deactivate the 52 unused US state taxes (rooms remain flat all-inclusive GHS: id_tax_rules_group = 0)
UPDATE `qlo_tax` SET `active` = 0 WHERE `id_tax` BETWEEN 1 AND 52;

-- Seed new 1.8.0 Header Slider config defaults (only if missing)
INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_HEADER_MEDIA_TYPE', '1', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_HEADER_MEDIA_TYPE');
INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_HEADER_SLIDER_NAV_TYPE', '2', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_HEADER_SLIDER_NAV_TYPE');
INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_HEADER_SLIDER_AUTO_PLAY', '1', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_HEADER_SLIDER_AUTO_PLAY');
INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_HEADER_SLIDER_INTERVAL', '5000', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_HEADER_SLIDER_INTERVAL');
INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_HEADER_SLIDER_ANIM_TYPE', '1', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_HEADER_SLIDER_ANIM_TYPE');
INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_HEADER_CONTENT_ALIGN', '2', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_HEADER_CONTENT_ALIGN');
INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_HEADER_VIDEO_SOURCE_TYPE', '', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_HEADER_VIDEO_SOURCE_TYPE');
INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_HEADER_VIDEO_NAME', '', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_HEADER_VIDEO_NAME');

-- Sync Lush View Hotel branding & record QloApps 1.8.0.0 DB version
UPDATE `qlo_configuration` SET `value` = 'Lush View Hotel', `date_upd` = NOW() WHERE `name` IN ('PS_SHOP_NAME', 'WK_HTL_CHAIN_NAME');
UPDATE `qlo_configuration` SET `value` = 'Lush View Hotel', `date_upd` = NOW() WHERE `name` = 'WK_TITLE_HEADER_BLOCK' AND (`value` IS NULL OR `value` IN ('', 'Hotel Prime', 'The Hotel Prime'));
UPDATE `qlo_configuration` SET `value` = '1.8.0.0', `date_upd` = NOW() WHERE `name` = 'QLO_VERSION_DB';
INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_VERSION_DB', '1.8.0.0', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_VERSION_DB');

SET SESSION sql_mode = @old_sql_mode;
