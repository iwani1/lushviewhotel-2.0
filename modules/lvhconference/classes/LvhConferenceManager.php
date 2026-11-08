<?php
/**
 * Lush View Hotel - Shared Conference Hall Hourly Scheduler & Overlap Guard.
 *
 * Consolidates Lush View Hotel's 4 conference tariffs (1h GHS 1,000 / 2h GHS 1,300 /
 * 3h GHS 1,600 / Full-Day 24h GHS 3,000 / Custom) onto ONE shared physical
 * Conference Hall timeline with automatic turnaround buffers and overlap blocking.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhConferenceManager
{
    /**
     * Audited Lush View Hotel Conference Hall tariff packages.
     *
     * @return array
     */
    public static function getPackages()
    {
        return array(
            '1_HOUR' => array('code' => '1_HOUR', 'label' => '1 Hour Session (GHS 1,000)', 'minutes' => 60, 'price' => 1000.00),
            '2_HOURS' => array('code' => '2_HOURS', 'label' => '2 Hours Session (GHS 1,300)', 'minutes' => 120, 'price' => 1300.00),
            '3_HOURS' => array('code' => '3_HOURS', 'label' => '3 Hours Session (GHS 1,600)', 'minutes' => 180, 'price' => 1600.00),
            'FULL_DAY' => array('code' => 'FULL_DAY', 'label' => 'Full Day / 24h Access (GHS 3,000)', 'minutes' => 600, 'price' => 3000.00),
            'CUSTOM' => array('code' => 'CUSTOM', 'label' => 'Custom Corporate Duration / Rate', 'minutes' => 240, 'price' => 1600.00),
        );
    }

    /**
     * Create `qlo_lvh_conference_booking` table and import any legacy `CON-*` bookings.
     *
     * @return bool
     */
    public static function createTables()
    {
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;

        $sql = "CREATE TABLE IF NOT EXISTS `{$p}lvh_conference_booking` (
            `id_conf_booking` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `booking_ref` VARCHAR(32) NOT NULL,
            `event_title` VARCHAR(160) NOT NULL,
            `organization` VARCHAR(160) NOT NULL DEFAULT '',
            `contact_name` VARCHAR(128) NOT NULL DEFAULT '',
            `contact_phone` VARCHAR(32) NOT NULL DEFAULT '',
            `contact_email` VARCHAR(128) NOT NULL DEFAULT '',
            `package_code` VARCHAR(24) NOT NULL DEFAULT '1_HOUR',
            `start_datetime` DATETIME NOT NULL,
            `end_datetime` DATETIME NOT NULL,
            `buffer_minutes` INT(11) UNSIGNED NOT NULL DEFAULT 30,
            `effective_end_datetime` DATETIME NOT NULL,
            `attendees` INT(11) UNSIGNED NOT NULL DEFAULT 20,
            `seating_layout` VARCHAR(32) NOT NULL DEFAULT 'Boardroom',
            `equipment_catering` TEXT,
            `total_amount` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `paid_amount` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `payment_method` VARCHAR(64) NOT NULL DEFAULT 'Cash (GHS)',
            `transaction_id` VARCHAR(128) NOT NULL DEFAULT '',
            `status` VARCHAR(20) NOT NULL DEFAULT 'confirmed',
            `legacy_booking_detail_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `created_by_name` VARCHAR(128) NOT NULL DEFAULT '',
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_conf_booking`),
            KEY `idx_lvh_conf_window` (`start_datetime`, `effective_end_datetime`),
            KEY `idx_lvh_conf_status` (`status`),
            KEY `idx_lvh_conf_legacy` (`legacy_booking_detail_id`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        self::syncLegacyConferenceOrders();
        return true;
    }

    /**
     * Check whether `[startDt, effectiveEndDt)` overlaps with any existing
     * `confirmed` or `tentative` Conference Hall booking.
     *
     * @param string $startDt        YYYY-MM-DD HH:MM:SS
     * @param string $effectiveEndDt YYYY-MM-DD HH:MM:SS (includes cleanup buffer)
     * @param int    $excludeId      Optional `id_conf_booking` when editing
     * @return array|null            Conflicting row or null if Hall is free
     */
    public static function findConflict($startDt, $effectiveEndDt, $excludeId = 0)
    {
        $p = _DB_PREFIX_;
        $whereExclude = (int) $excludeId > 0 ? ' AND `id_conf_booking` != ' . (int) $excludeId : '';

        $row = Db::getInstance()->getRow(
            "SELECT * FROM `{$p}lvh_conference_booking`
             WHERE `status` IN ('confirmed', 'tentative')
               {$whereExclude}
               AND `start_datetime` < '" . pSQL($effectiveEndDt) . "'
               AND `effective_end_datetime` > '" . pSQL($startDt) . "'
             ORDER BY `start_datetime` ASC"
        );

        return $row ? $row : null;
    }

    /**
     * Import historical QloApps `CON-1..CON-4` room bookings into the unified
     * Conference Hall schedule idempotently (`legacy_booking_detail_id`).
     *
     * @return int Number of newly imported legacy conference bookings
     */
    public static function syncLegacyConferenceOrders()
    {
        $p = _DB_PREFIX_;
        $hasHbd = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'htl_booking_detail') . "'");
        if (empty($hasHbd)) {
            return 0;
        }

        $rows = Db::getInstance()->executeS(
            "SELECT hbd.`id` AS `id_bd`, hbd.`id_order`, hbd.`date_from`, hbd.`date_to`,
                    hbd.`total_price_tax_incl`, hbd.`id_status`,
                    hr.`room_num`,
                    o.`reference`, o.`payment`,
                    CONCAT(c.`firstname`, ' ', c.`lastname`) AS `guest_name`, c.`email`,
                    COALESCE((SELECT SUM(op.`amount`) FROM `{$p}order_payment` op WHERE op.`order_reference` = o.`reference`), 0) AS `paid_amt`
             FROM `{$p}htl_booking_detail` hbd
             INNER JOIN `{$p}htl_room_information` hr ON (hr.`id` = hbd.`id_room`)
             LEFT JOIN `{$p}orders` o ON (o.`id_order` = hbd.`id_order`)
             LEFT JOIN `{$p}customer` c ON (c.`id_customer` = hbd.`id_customer`)
             WHERE hr.`room_num` LIKE 'CON-%'
               AND hbd.`is_refunded` = 0
               AND hbd.`id_status` NOT IN (4, 5)
             ORDER BY hbd.`date_from` ASC"
        );

        if (!is_array($rows)) {
            return 0;
        }

        $imported = 0;
        foreach ($rows as $r) {
            $idBd = (int) $r['id_bd'];
            $exists = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . $p . 'lvh_conference_booking` WHERE `legacy_booking_detail_id` = ' . $idBd
            );
            if ($exists > 0) {
                continue;
            }

            $rm = strtoupper((string) $r['room_num']);
            $pkg = 'FULL_DAY';
            $mins = 600;
            if ($rm === 'CON-4') {
                $pkg = '1_HOUR';
                $mins = 60;
            } elseif ($rm === 'CON-3') {
                $pkg = '2_HOURS';
                $mins = 120;
            } elseif ($rm === 'CON-2') {
                $pkg = '3_HOURS';
                $mins = 180;
            }

            $dayStr = substr((string) $r['date_from'], 0, 10);
            $startDt = $dayStr . ' 09:00:00';
            $endTs = strtotime($startDt) + ($mins * 60);
            $endDt = date('Y-m-d H:i:s', $endTs);

            Db::getInstance()->insert('lvh_conference_booking', array(
                'booking_ref' => pSQL(!empty($r['reference']) ? $r['reference'] : ('CONF-LEG-' . $idBd)),
                'event_title' => pSQL('Legacy ' . $rm . ' Conference Booking'),
                'organization' => pSQL((string) $r['guest_name']),
                'contact_name' => pSQL((string) $r['guest_name']),
                'contact_phone' => '',
                'contact_email' => pSQL((string) $r['email']),
                'package_code' => pSQL($pkg),
                'start_datetime' => pSQL($startDt),
                'end_datetime' => pSQL($endDt),
                'buffer_minutes' => 0,
                'effective_end_datetime' => pSQL($endDt),
                'attendees' => 20,
                'seating_layout' => 'Boardroom',
                'equipment_catering' => pSQL('Imported from legacy QloApps room ' . $rm . ' (Order #' . (int) $r['id_order'] . ')'),
                'total_amount' => (float) $r['total_price_tax_incl'],
                'paid_amount' => min((float) $r['total_price_tax_incl'], (float) $r['paid_amt']),
                'payment_method' => pSQL(!empty($r['payment']) ? (string) $r['payment'] : 'Cash (GHS)'),
                'transaction_id' => '',
                'status' => ((int) $r['id_status'] === 3) ? 'completed' : 'confirmed',
                'legacy_booking_detail_id' => $idBd,
                'created_by_name' => 'Legacy Sync',
                'date_add' => date('Y-m-d H:i:s'),
            ));
            $imported++;
        }

        return $imported;
    }
}
