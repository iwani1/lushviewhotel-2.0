#!/usr/bin/env python3
"""
Build the turnkey Lush View Hotel QloApps 1.7.0 -> 1.8.0 Production Upgrade Kit:
  1. releases/lvh-db-upgrade-1.7.0-to-1.8.0.sql
       Idempotent, zero-PII MariaDB 10.4+/11.x migration script combining:
         - 001 password column widening (varchar(32) -> varchar(60))
         - 003 payment amount widening (decimal(10,2) -> decimal(20,6))
         - All 27 new 1.8.0 Core & HotelReservationSystem tables (utf8mb3_general_ci)
         - All 23 new 1.8.0 Core & HotelReservationSystem columns (ADD COLUMN IF NOT EXISTS)
         - Canonical 1.8.0 seed rows (business_source, source, htl_booking_status,
           htl_property_type, room_type_selling_object, hooks, admin tabs & SuperAdmin access)
         - Lossless migration of live 1.7.0 htl_features -> 1.8.0 htl_amenity
           (keeping legacy htl_features* and htl_bed_type* tables untouched)
         - Cleanup of DB-only orphan cashpayment + removed 1.7.0 modules
         - Deactivation of 52 unused US state taxes + CVE-2025-67325 mitigation
         - Lush View Hotel brand sync + QLO_VERSION_DB = 1.8.0.0 + verification SELECT
  2. releases/lvh-1.8.0-overlay.zip
       Clean overlay archive to extract onto official QloApps v1.8.0 containing ONLY:
         - admin-dev/themes/default/template/search_form.tpl (reflected XSS fix)
         - localization/gh.xml (Ghana GHS localization pack)
         - modules/lvhadminclean/ (v2.0.1)
         - modules/lvhauditlog/, lvhpayments/, lvhhousekeeping/, lvhfrontdesk/,
           lvhconference/, lvhreports/
"""

import os
import xml.etree.ElementTree as ET
import zipfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))


def sql_str(val: str) -> str:
    if val is None:
        return "NULL"
    return "'" + val.replace("\\", "\\\\").replace("'", "\\'") + "'"


def build_sql() -> str:
    lines = []
    w = lines.append

    w("-- =============================================================================")
    w("-- Lush View Hotel — QloApps 1.7.0.0 -> 1.8.0.0 Complete Database Upgrade")
    w("-- Target engine : MariaDB 10.4+ / 11.4.x (table prefix: qlo_)")
    w("-- Safety rules  :")
    w("--   1. NEVER run against live `lushview_qloapp` directly.")
    w("--      Import a fresh backup into a NEW database (e.g. `lushview_qlo18`)")
    w("--      and run this script on the NEW database copy.")
    w("--   2. Additive & idempotent: safe to re-run; preserves all legacy tables")
    w("--      (`qlo_htl_features*`, `qlo_htl_bed_type*`) and all 1,934+ bookings.")
    w("--   3. Defers the 5 tables & 8 columns owned by uninstalled 1.8.0 modules")
    w("--      (`qlocmconnector`, `qlogooglehotelconnector`, `qlocrontaskmanager`)")
    w("--      so their unguarded `ALTER TABLE ... ADD COLUMN` calls succeed cleanly.")
    w("-- =============================================================================")
    w("")
    w("SET NAMES utf8mb4;")
    w("SET @old_sql_mode = @@SESSION.sql_mode;")
    w("SET SESSION sql_mode = '';")
    w("")

    # 1. Migrations 001 + 003
    w("-- -----------------------------------------------------------------------------")
    w("-- PART 1: Widen password columns (bcrypt 60 chars) & payment amounts (20,6)")
    w("-- -----------------------------------------------------------------------------")
    w("ALTER TABLE `qlo_employee` MODIFY `passwd` varchar(60) NOT NULL;")
    w("ALTER TABLE `qlo_customer` MODIFY `passwd` varchar(60) NOT NULL;")
    w("ALTER TABLE `qlo_referrer` MODIFY `passwd` varchar(60) DEFAULT NULL;")
    w("ALTER TABLE `qlo_order_payment` MODIFY `amount` decimal(20,6) NOT NULL;")
    w("ALTER TABLE `qlo_order_payment_detail` MODIFY `amount` decimal(20,6) NOT NULL;")
    w("")

    # 2. 27 new 1.8.0 Core (13) + HRS (14) tables using utf8mb3_general_ci to match live
    w("-- -----------------------------------------------------------------------------")
    w("-- PART 2: Create all 27 new QloApps 1.8.0 Core (13) & HRS (14) tables")
    w("--         Explicit utf8mb3_general_ci matches existing 300 tables (0 collation mix)")
    w("-- -----------------------------------------------------------------------------")
    ddl_27 = """
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
""".strip()
    w(ddl_27)
    w("")

    # 3. 23 Missing Columns
    w("-- -----------------------------------------------------------------------------")
    w("-- PART 3: Add all 23 missing QloApps 1.8.0 Core (16) & HRS (7) columns")
    w("--         Uses MariaDB `ADD COLUMN IF NOT EXISTS` for full idempotency")
    w("-- -----------------------------------------------------------------------------")
    alters_23 = [
        "ALTER TABLE `qlo_access` ADD COLUMN IF NOT EXISTS `kpi` int(11) NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_cart_rule` ADD COLUMN IF NOT EXISTS `hotel_restriction` tinyint(1) unsigned NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_customer` ADD COLUMN IF NOT EXISTS `id_country` int(10) NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_feature` ADD COLUMN IF NOT EXISTS `logo_type` varchar(10) NOT NULL DEFAULT 'image';",
        "ALTER TABLE `qlo_feature` ADD COLUMN IF NOT EXISTS `logo` varchar(255) NOT NULL DEFAULT '';",
        "ALTER TABLE `qlo_feature` ADD COLUMN IF NOT EXISTS `is_featured` tinyint(1) unsigned NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_order_detail` ADD COLUMN IF NOT EXISTS `id_tourism_tax_rule_group` int(11) unsigned DEFAULT 0;",
        "ALTER TABLE `qlo_order_payment_detail` ADD COLUMN IF NOT EXISTS `receipt_number` int(10) NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_order_return` ADD COLUMN IF NOT EXISTS `event_type` tinyint(1) unsigned NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_order_slip` ADD COLUMN IF NOT EXISTS `remark` text DEFAULT NULL;",
        "ALTER TABLE `qlo_orders` ADD COLUMN IF NOT EXISTS `id_source` int(10) unsigned DEFAULT NULL;",
        "ALTER TABLE `qlo_product` ADD COLUMN IF NOT EXISTS `id_tourism_tax_rules_group` int(11) unsigned NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_product` ADD COLUMN IF NOT EXISTS `id_selling_object` int(10) unsigned DEFAULT NULL;",
        "ALTER TABLE `qlo_product_shop` ADD COLUMN IF NOT EXISTS `id_tourism_tax_rules_group` int(11) unsigned NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_tax` ADD COLUMN IF NOT EXISTS `is_tourism_tax` tinyint(1) NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_tax_rules_group` ADD COLUMN IF NOT EXISTS `is_tourism_tax_rule_group` tinyint(1) NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_htl_booking_detail` ADD COLUMN IF NOT EXISTS `selling_object_name` varchar(255) DEFAULT NULL;",
        "ALTER TABLE `qlo_htl_booking_detail` ADD COLUMN IF NOT EXISTS `selling_object_plural_name` varchar(255) DEFAULT NULL;",
        "ALTER TABLE `qlo_htl_booking_detail` ADD COLUMN IF NOT EXISTS `property_type_name` varchar(255) DEFAULT NULL;",
        "ALTER TABLE `qlo_htl_branch_info` ADD COLUMN IF NOT EXISTS `id_property_type` int(10) unsigned NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_htl_branch_info` ADD COLUMN IF NOT EXISTS `tourism_tax_collection_type` tinyint(1) unsigned NOT NULL DEFAULT 0;",
        "ALTER TABLE `qlo_htl_image` ADD COLUMN IF NOT EXISTS `id_htl_image_category` int(10) unsigned DEFAULT NULL;",
        "ALTER TABLE `qlo_htl_room_type` ADD COLUMN IF NOT EXISTS `id_selling_object` int(10) unsigned DEFAULT NULL;",
    ]
    assert len(alters_23) == 23
    for stmt in alters_23:
        w(stmt)
    w("")

    # 4. Seed canonical 1.8.0 vocabulary rows
    w("-- -----------------------------------------------------------------------------")
    w("-- PART 4: Seed canonical QloApps 1.8.0 vocabulary tables & migrate Amenities")
    w("-- -----------------------------------------------------------------------------")

    # 4a. business_source + business_source_lang
    bs_items = [
        (1, "DIRECT", 0, 1, 1, "Direct"),
        (2, "OTA", 1, 1, 1, "OTA"),
        (3, "CORPORATE", 2, 1, 1, "Corporate"),
        (4, "TRAVEL_AGENT", 3, 1, 1, "Travel Agent"),
        (5, "GROUP", 4, 1, 1, "Group"),
        (6, "API", 5, 1, 1, "API"),
    ]
    for bid, code, pos, unrem, act, label in bs_items:
        w(f"INSERT IGNORE INTO `qlo_business_source` (`id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES ({bid}, {sql_str(code)}, {pos}, {unrem}, {act}, 0, NOW(), NOW());")
        w(f"INSERT IGNORE INTO `qlo_business_source_lang` (`id_business_source`, `id_lang`, `name`) SELECT {bid}, `id_lang`, {sql_str(label)} FROM `qlo_lang`;")
    w("")

    # 4b. source + source_lang
    src_items = [
        (1, 1, "DIRECT_WEBSITE", 0, 1, 1, "Direct Website"),
        (2, 1, "WALKIN", 1, 1, 1, "Walk-in"),
        (3, 1, "PHONE", 2, 0, 1, "Phone"),
        (4, 1, "EMAIL", 3, 0, 1, "Email"),
        (5, 1, "SOCIAL_MEDIA", 4, 0, 1, "Social Media"),
        (6, 1, "WHATSAPP", 5, 0, 1, "WhatsApp"),
        (7, 2, "BOOKING_COM", 0, 0, 1, "Booking.com"),
        (8, 2, "EXPEDIA", 1, 0, 1, "Expedia"),
        (9, 2, "GOMMT", 2, 0, 1, "GoMMT (GoIbibo/MakeMyTrip)"),
        (10, 2, "AGODA", 3, 0, 1, "Agoda"),
        (11, 2, "AIRBNB", 4, 0, 1, "Airbnb"),
        (12, 2, "TRIP_COM", 5, 0, 1, "Trip.com (Ctrip)"),
        (13, 2, "BAKUUN", 6, 0, 1, "Bakuun"),
        (14, 2, "DESPEGAR", 7, 0, 1, "Despegar"),
        (15, 2, "YATRA", 8, 0, 1, "Yatra"),
        (16, 2, "HOSTELWORLD", 9, 0, 1, "Hostelworld"),
        (17, 2, "HYPERGUEST", 10, 0, 1, "HyperGuest"),
        (18, 6, "API_ORDER", 0, 1, 1, "API Order"),
    ]
    for sid, bsid, code, pos, unrem, act, label in src_items:
        w(f"INSERT IGNORE INTO `qlo_source` (`id_source`, `id_business_source`, `code`, `position`, `unremovable`, `active`, `deleted`, `date_add`, `date_upd`) VALUES ({sid}, {bsid}, {sql_str(code)}, {pos}, {unrem}, {act}, 0, NOW(), NOW());")
        w(f"INSERT IGNORE INTO `qlo_source_lang` (`id_source`, `id_lang`, `name`) SELECT {sid}, `id_lang`, {sql_str(label)} FROM `qlo_lang`;")
    w("")

    # 4c. htl_booking_status + htl_booking_status_lang (fixed IDs 1..5)
    statuses = [
        (1, "#4169E1", 0, "Assigned"),
        (2, "#17A2B8", 0, "Checked-in"),
        (3, "#108510", 0, "Checked-out"),
        (4, "#FF8C00", 0, "No-show"),
        (5, "#DC143C", 1, "Cancelled"),
    ]
    for st_id, color, term, label in statuses:
        w(f"INSERT IGNORE INTO `qlo_htl_booking_status` (`id_booking_status`, `color`, `is_terminal`) VALUES ({st_id}, {sql_str(color)}, {term});")
        w(f"INSERT IGNORE INTO `qlo_htl_booking_status_lang` (`id_booking_status`, `id_lang`, `name`) SELECT {st_id}, `id_lang`, {sql_str(label)} FROM `qlo_lang`;")
    w("")

    # 4d. htl_property_type + htl_property_type_lang (1..16)
    prop_types = [
        (1, "Hotel", "Hotels"),
        (2, "Motel", "Motels"),
        (3, "Resort", "Resorts"),
        (4, "Hostel", "Hostels"),
        (5, "Guest House", "Guest Houses"),
        (6, "Bed & Breakfast", "Bed & Breakfasts"),
        (7, "Lodge", "Lodges"),
        (8, "Apartment", "Apartments"),
        (9, "Vacation Rental", "Vacation Rentals"),
        (10, "Homestay", "Homestays"),
        (11, "Co-Living", "Co-Livings"),
        (12, "Camping", "Campings"),
        (13, "Glamping", "Glampings"),
        (14, "Farm Stay", "Farm Stays"),
        (15, "Houseboat", "Houseboats"),
        (16, "Boat Stay", "Boat Stays"),
    ]
    for pid, sing, plur in prop_types:
        w(f"INSERT IGNORE INTO `qlo_htl_property_type` (`id_htl_property_type`, `active`, `date_add`, `date_upd`) VALUES ({pid}, 1, NOW(), NOW());")
        w(f"INSERT IGNORE INTO `qlo_htl_property_type_lang` (`id_htl_property_type`, `id_lang`, `name`, `plural_name`) SELECT {pid}, `id_lang`, {sql_str(sing)}, {sql_str(plur)} FROM `qlo_lang`;")
    w("")

    # 4e. room_type_selling_object + room_type_selling_object_lang (1..29)
    selling_objs = [
        (1, "Room", "Rooms"),
        (2, "Bed", "Beds"),
        (3, "Apartment", "Apartments"),
        (4, "Villa", "Villas"),
        (5, "Cottage", "Cottages"),
        (6, "Bungalow", "Bungalows"),
        (7, "House", "Houses"),
        (8, "Chalet", "Chalets"),
        (9, "Cabin", "Cabins"),
        (10, "Studio", "Studios"),
        (11, "Condo", "Condos"),
        (12, "Penthouse", "Penthouses"),
        (13, "Tent", "Tents"),
        (14, "Camping Pitch", "Camping Pitches"),
        (15, "Caravan Site", "Caravan Sites"),
        (16, "RV Site", "RV Sites"),
        (17, "Pod", "Pods"),
        (18, "Capsule", "Capsules"),
        (19, "Treehouse", "Treehouses"),
        (20, "Hut", "Huts"),
        (21, "Yurt", "Yurts"),
        (22, "Igloo", "Igloos"),
        (23, "Houseboat", "Houseboats"),
        (24, "Boat Cabin", "Boat Cabins"),
        (25, "Overwater Bungalow", "Overwater Bungalows"),
        (26, "Heritage Room", "Heritage Rooms"),
        (27, "Holiday Home", "Holiday Homes"),
        (28, "Farm Stay Unit", "Farm Stay Units"),
        (29, "Cave Room", "Cave Rooms"),
        (30, "Container Stay", "Container Stays"),
        (31, "Train Cabin", "Train Cabins"),
        (32, "Motorhome", "Motorhomes"),
    ]
    for oid, sing, plur in selling_objs:
        w(f"INSERT IGNORE INTO `qlo_room_type_selling_object` (`id_room_type_selling_object`, `active`, `date_add`, `date_upd`) VALUES ({oid}, 1, NOW(), NOW());")
        w(f"INSERT IGNORE INTO `qlo_room_type_selling_object_lang` (`id_room_type_selling_object`, `id_lang`, `name`, `plural_name`) SELECT {oid}, `id_lang`, {sql_str(sing)}, {sql_str(plur)} FROM `qlo_lang`;")
    w("")

    # 4f. Migrate 1.7.0 Hotel Features -> 1.8.0 Hotel Amenities (legacy tables preserved intact)
    w("-- Migrate live 1.7.0 `qlo_htl_features*` into 1.8.0 `qlo_htl_amenity*` (non-destructive)")
    w("INSERT IGNORE INTO `qlo_htl_amenity` (`id_amenity`, `id_parent`, `position`, `active`, `logo_type`, `logo`, `date_add`, `date_upd`)")
    w("SELECT `id`, `parent_feature_id`, `position`, `active`, 'icon', '', `date_add`, `date_upd` FROM `qlo_htl_features`;")
    w("INSERT IGNORE INTO `qlo_htl_amenity_lang` (`id_amenity`, `id_lang`, `name`)")
    w("SELECT `id`, `id_lang`, `name` FROM `qlo_htl_features_lang`;")
    w("INSERT INTO `qlo_htl_branch_amenity` (`id_hotel`, `amenity_id`, `is_featured`, `date_add`, `date_upd`)")
    w("SELECT bf.`id_hotel`, CAST(bf.`feature_id` AS UNSIGNED), 0, bf.`date_add`, bf.`date_upd`")
    w("FROM `qlo_htl_branch_features` bf")
    w("WHERE CAST(bf.`feature_id` AS UNSIGNED) > 0")
    w("  AND NOT EXISTS (")
    w("      SELECT 1 FROM `qlo_htl_branch_amenity` ba")
    w("      WHERE ba.`id_hotel` = bf.`id_hotel` AND ba.`amenity_id` = CAST(bf.`feature_id` AS UNSIGNED)")
    w("  );")
    w("")

    # 4g. Safe column backfills for existing hotel data
    w("-- Backfill sensible defaults on existing hotel rows")
    w("UPDATE `qlo_access` SET `kpi` = 1 WHERE `id_profile` = 1;")
    w("UPDATE `qlo_htl_branch_info` SET `id_property_type` = 1 WHERE `id_property_type` = 0;")
    w("UPDATE `qlo_htl_room_type` SET `id_selling_object` = 1 WHERE `id_selling_object` IS NULL;")
    w("UPDATE `qlo_product` SET `id_selling_object` = 1 WHERE `id_selling_object` IS NULL AND `id_product` IN (SELECT `id_product` FROM `qlo_htl_room_type`);")
    w("UPDATE `qlo_order_payment_detail` SET `receipt_number` = `id_order_payment_detail` WHERE `receipt_number` = 0;")
    w("INSERT IGNORE INTO `qlo_htl_settings_link` (`id_settings_link`, `icon`, `link`, `new_window`, `position`, `unremovable`, `active`, `date_add`, `date_upd`)")
    w("VALUES (5, 'icon-picture', 'index.php?controller=AdminHotelHeaderImage', 0, 6, 0, 1, NOW(), NOW());")
    w("INSERT IGNORE INTO `qlo_htl_settings_link_lang` (`id_settings_link`, `id_lang`, `name`, `hint`)")
    w("SELECT 5, `id_lang`, 'Landing Page Header Media', 'Configure and manage header images or videos displayed on the home page.' FROM `qlo_lang`;")
    w("")

    # 5. Hooks & Admin Tabs new in 1.8.0
    w("-- -----------------------------------------------------------------------------")
    w("-- PART 5: Register new QloApps 1.8.0 Hooks, Admin Tabs & SuperAdmin Permissions")
    w("-- -----------------------------------------------------------------------------")
    hook_tree = ET.parse(os.path.join(ROOT, "install-dev", "data", "xml", "hook.xml"))
    for h in hook_tree.findall(".//hook"):
        hname = h.findtext("name") or h.attrib.get("id") or ""
        htitle = h.findtext("title") or hname
        hdesc = h.findtext("description") or ""
        hpos = int(h.attrib.get("position", "1") or 1)
        hlive = int(h.attrib.get("live_edit", "0") or 0)
        if hname:
            w(f"INSERT IGNORE INTO `qlo_hook` (`name`, `title`, `description`, `position`, `live_edit`) VALUES ({sql_str(hname)}, {sql_str(htitle)}, {sql_str(hdesc)}, {hpos}, {hlive});")
    w("")

    # New 1.8.0 Core & HRS Admin Tabs
    new_tabs = [
        # (class_name, parent_class_or_minus1, module_or_None, label)
        ("AdminRoomTypeSellingObjects", "AdminCatalog", None, "Room Type Selling Objects"),
        ("AdminPaymentReceipts", "AdminParentOrders", None, "Payment Receipts"),
        ("AdminSources", "AdminParentOrders", None, "Sources"),
        ("AdminGuestRegistration", "AdminParentOrders", None, "Guest Registration"),
        ("AdminRoomStatuses", "AdminParentOrders", "hotelreservationsystem", "Room Statuses"),
        ("AdminHotelPropertyTypes", "AdminHotelReservationSystemManagement", "hotelreservationsystem", "Manage Property Types"),
        ("AdminHotelAmenities", "AdminHotelReservationSystemManagement", "hotelreservationsystem", "Manage Amenities"),
        ("AdminHotelHeaderImage", "-1", "hotelreservationsystem", "Header Image Configuration"),
        ("AdminBookingDocument", "-1", "hotelreservationsystem", "Booking Documents"),
        ("AdminHotelImageCategory", "-1", "hotelreservationsystem", "Hotel Image Category"),
    ]
    for cls_name, parent_cls, mod_name, label in new_tabs:
        mod_sql = sql_str(mod_name) if mod_name else "NULL"
        if parent_cls == "-1":
            parent_expr = "-1"
        else:
            parent_expr = f"COALESCE((SELECT t2.`id_tab` FROM (SELECT `id_tab`, `class_name` FROM `qlo_tab`) t2 WHERE t2.`class_name` = {sql_str(parent_cls)} LIMIT 1), 0)"
        w(
            f"INSERT INTO `qlo_tab` (`id_parent`, `class_name`, `module`, `position`, `active`, `hide_host_mode`) "
            f"SELECT {parent_expr}, {sql_str(cls_name)}, {mod_sql}, 10, 1, 0 FROM DUAL "
            f"WHERE NOT EXISTS (SELECT 1 FROM `qlo_tab` WHERE `class_name` = {sql_str(cls_name)});"
        )
        w(
            f"INSERT IGNORE INTO `qlo_tab_lang` (`id_tab`, `id_lang`, `name`) "
            f"SELECT t.`id_tab`, l.`id_lang`, {sql_str(label)} FROM `qlo_tab` t CROSS JOIN `qlo_lang` l "
            f"WHERE t.`class_name` = {sql_str(cls_name)};"
        )
        w(
            f"INSERT IGNORE INTO `qlo_access` (`id_profile`, `id_tab`, `view`, `add`, `edit`, `delete`, `kpi`) "
            f"SELECT 1, t.`id_tab`, 1, 1, 1, 1, 1 FROM `qlo_tab` t "
            f"WHERE t.`class_name` = {sql_str(cls_name)};"
        )
    # Hide obsolete 1.7.0 AdminHotelFeatures menu item (superseded by AdminHotelAmenities)
    w("UPDATE `qlo_tab` SET `active` = 0 WHERE `class_name` = 'AdminHotelFeatures';")
    w("")

    # 6. Live Hygiene, Orphan Cleanup & 1.8.0 Config Sync
    w("-- -----------------------------------------------------------------------------")
    w("-- PART 6: Orphan Module Cleanup, Security Hardening & Lush View Hotel Config")
    w("-- -----------------------------------------------------------------------------")
    w("-- Remove DB-only orphan `cashpayment` (0 orders used it) & removed 1.7.0 modules")
    w("DELETE FROM `qlo_hook_module` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));")
    w("DELETE FROM `qlo_hook_module_exceptions` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));")
    w("DELETE FROM `qlo_module_shop` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));")
    w("DELETE FROM `qlo_module_group` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));")
    w("DELETE FROM `qlo_module_country` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));")
    w("DELETE FROM `qlo_module_currency` WHERE `id_module` IN (SELECT `id_module` FROM (SELECT `id_module`, `name` FROM `qlo_module`) m WHERE m.`name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector'));")
    w("UPDATE `qlo_tab` SET `active` = 0 WHERE `module` IN ('qloautoupgrade', 'qlochannelmanagerconnector');")
    w("DELETE FROM `qlo_module` WHERE `name` IN ('cashpayment', 'qloautoupgrade', 'qlochannelmanagerconnector');")
    w("")
    w("-- Keep `qlohotelreview` disabled by default (0 live reviews; CVE-2025-67325 defense-in-depth)")
    w("UPDATE `qlo_module` SET `active` = 0 WHERE `name` = 'qlohotelreview';")
    w("")
    w("-- Deactivate the 52 unused US state taxes (rooms remain flat all-inclusive GHS: id_tax_rules_group = 0)")
    w("UPDATE `qlo_tax` SET `active` = 0 WHERE `id_tax` BETWEEN 1 AND 52;")
    w("")
    w("-- Seed new 1.8.0 Header Slider config defaults (only if missing)")
    cfg_defaults = [
        ("QLO_HEADER_MEDIA_TYPE", "1"),
        ("QLO_HEADER_SLIDER_NAV_TYPE", "2"),
        ("QLO_HEADER_SLIDER_AUTO_PLAY", "1"),
        ("QLO_HEADER_SLIDER_INTERVAL", "5000"),
        ("QLO_HEADER_SLIDER_ANIM_TYPE", "1"),
        ("QLO_HEADER_CONTENT_ALIGN", "2"),
        ("QLO_HEADER_VIDEO_SOURCE_TYPE", ""),
        ("QLO_HEADER_VIDEO_NAME", ""),
    ]
    for k, v in cfg_defaults:
        w(
            f"INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) "
            f"SELECT NULL, NULL, {sql_str(k)}, {sql_str(v)}, NOW(), NOW() FROM DUAL "
            f"WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = {sql_str(k)});"
        )
    w("")
    w("-- Sync Lush View Hotel branding & record QloApps 1.8.0.0 DB version")
    w("UPDATE `qlo_configuration` SET `value` = 'Lush View Hotel', `date_upd` = NOW() WHERE `name` IN ('PS_SHOP_NAME', 'WK_HTL_CHAIN_NAME');")
    w("UPDATE `qlo_configuration` SET `value` = 'Lush View Hotel', `date_upd` = NOW() WHERE `name` = 'WK_TITLE_HEADER_BLOCK' AND (`value` IS NULL OR `value` IN ('', 'Hotel Prime', 'The Hotel Prime'));")
    w("UPDATE `qlo_configuration` SET `value` = '1.8.0.0', `date_upd` = NOW() WHERE `name` = 'QLO_VERSION_DB';")
    w("INSERT INTO `qlo_configuration` (`id_shop_group`, `id_shop`, `name`, `value`, `date_add`, `date_upd`) SELECT NULL, NULL, 'QLO_VERSION_DB', '1.8.0.0', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `qlo_configuration` WHERE `name` = 'QLO_VERSION_DB');")
    w("")
    w("SET SESSION sql_mode = @old_sql_mode;")
    w("")
    w("-- -----------------------------------------------------------------------------")
    w("-- PART 7: Built-in Post-Upgrade Verification Summary")
    w("-- -----------------------------------------------------------------------------")
    w("SELECT")
    w("  (SELECT COUNT(*) FROM `qlo_orders`) AS `bookings_preserved`,")
    w("  (SELECT COUNT(*) FROM `qlo_customer`) AS `guests_preserved`,")
    w("  (SELECT COUNT(*) FROM `qlo_order_payment`) AS `payments_preserved`,")
    w("  (SELECT COUNT(*) FROM `qlo_employee`) AS `staff_preserved`,")
    w("  (SELECT COUNT(*) FROM `qlo_htl_room_information`) AS `rooms_preserved`,")
    w("  (SELECT COUNT(*) FROM `qlo_htl_amenity`) AS `amenities_migrated`,")
    w("  (SELECT `value` FROM `qlo_configuration` WHERE `name` = 'QLO_VERSION_DB' LIMIT 1) AS `qlo_version_db`,")
    w("  (SELECT COUNT(*) FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_TYPE` = 'BASE TABLE') AS `total_tables`;")
    w("")

    return "\n".join(lines)


def build_overlay_zip(out_zip: str) -> int:
    include_files = [
        "admin-dev/themes/default/template/search_form.tpl",
        "localization/gh.xml",
    ]
    include_dirs = [
        "modules/lvhadminclean",
        "modules/lvhauditlog",
        "modules/lvhpayments",
        "modules/lvhhousekeeping",
        "modules/lvhfrontdesk",
        "modules/lvhconference",
        "modules/lvhreports",
    ]
    count = 0
    with zipfile.ZipFile(out_zip, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for rel in include_files:
            full = os.path.join(ROOT, rel)
            zf.write(full, rel)
            count += 1
        for d in include_dirs:
            full_dir = os.path.join(ROOT, d)
            for dirpath, dirnames, filenames in os.walk(full_dir):
                dirnames.sort()
                for fn in sorted(filenames):
                    if fn.startswith("."):
                        continue
                    fp = os.path.join(dirpath, fn)
                    arc = os.path.relpath(fp, ROOT)
                    zf.write(fp, arc)
                    count += 1
    return count


def main() -> None:
    sql_text = build_sql()
    os.makedirs(os.path.join(ROOT, "releases"), exist_ok=True)
    mig_path = os.path.join(ROOT, "dev", "migrations", "004_live_1_7_0_to_1_8_0_standalone.sql")
    rel_sql = os.path.join(ROOT, "releases", "lvh-db-upgrade-1.7.0-to-1.8.0.sql")
    rel_zip = os.path.join(ROOT, "releases", "lvh-1.8.0-overlay.zip")

    for p in (mig_path, rel_sql):
        with open(p, "w", encoding="utf-8") as f:
            f.write(sql_text)

    n_files = build_overlay_zip(rel_zip)
    print(f"Wrote {rel_sql} ({len(sql_text):,} bytes)")
    print(f"Wrote {rel_zip} ({os.path.getsize(rel_zip):,} bytes, {n_files} files)")


if __name__ == "__main__":
    main()
