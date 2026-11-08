<?php
/**
 * Lush View Hotel - Housekeeping & Room Readiness Data Helper.
 *
 * Tracks Clean / Dirty / Cleaning / Inspecting / Out-of-Order state across
 * Lush View Hotel's 34 physical guest rooms (First & Second floors, excluding
 * Conference Hall pseudo-rooms `CON-*`), syncs Out-of-Order rooms with QloApps'
 * native `htl_room_information.id_status` so website searches never sell a broken
 * room, and auto-flips rooms to Dirty on guest check-out.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhHousekeepingStatus
{
    public static function getStatusDefinitions()
    {
        return array(
            'clean' => array('label' => 'Clean / Ready', 'badge' => 'success', 'color' => '#15803d', 'bg' => '#dcfce7'),
            'dirty' => array('label' => 'Dirty (Needs Turnover)', 'badge' => 'danger', 'color' => '#b91c1c', 'bg' => '#fee2e2'),
            'cleaning' => array('label' => 'Cleaning in Progress', 'badge' => 'warning', 'color' => '#b45309', 'bg' => '#fef3c7'),
            'inspecting' => array('label' => 'Supervisor Inspection', 'badge' => 'info', 'color' => '#1d4ed8', 'bg' => '#dbeafe'),
            'ooo' => array('label' => 'Out of Order / Maint.', 'badge' => 'default', 'color' => '#374151', 'bg' => '#e5e7eb'),
        );
    }

    /**
     * Create `qlo_lvh_room_housekeeping` and `qlo_lvh_housekeeping_log` tables,
     * and seed any missing physical guest rooms as `clean` (or `ooo` if inactive).
     *
     * @return bool
     */
    public static function createTables()
    {
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;

        $sql1 = "CREATE TABLE IF NOT EXISTS `{$p}lvh_room_housekeeping` (
            `id_room` INT(11) UNSIGNED NOT NULL,
            `hk_status` VARCHAR(20) NOT NULL DEFAULT 'clean',
            `assigned_attendant` VARCHAR(128) NOT NULL DEFAULT '',
            `maintenance_note` VARCHAR(255) NOT NULL DEFAULT '',
            `ooo_until` DATE DEFAULT NULL,
            `updated_by_name` VARCHAR(128) NOT NULL DEFAULT '',
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_room`),
            KEY `idx_lvh_hk_status` (`hk_status`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql2 = "CREATE TABLE IF NOT EXISTS `{$p}lvh_housekeeping_log` (
            `id_hk_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_room` INT(11) UNSIGNED NOT NULL,
            `room_num` VARCHAR(64) NOT NULL DEFAULT '',
            `old_status` VARCHAR(20) NOT NULL DEFAULT '',
            `new_status` VARCHAR(20) NOT NULL DEFAULT '',
            `note` VARCHAR(255) NOT NULL DEFAULT '',
            `staff_name` VARCHAR(128) NOT NULL DEFAULT '',
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_hk_log`),
            KEY `idx_lvh_hklog_room` (`id_room`),
            KEY `idx_lvh_hklog_date` (`date_add`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        if (!Db::getInstance()->execute($sql1) || !Db::getInstance()->execute($sql2)) {
            return false;
        }

        self::seedPhysicalRooms();
        return true;
    }

    /**
     * Seed all physical guest rooms (`room_num NOT LIKE 'CON-%'`) into `lvh_room_housekeeping`.
     */
    public static function seedPhysicalRooms()
    {
        $p = _DB_PREFIX_;
        $hasRoomTable = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'htl_room_information') . "'");
        if (empty($hasRoomTable)) {
            return;
        }

        $rooms = Db::getInstance()->executeS(
            "SELECT `id`, `id_status`, `comment`
             FROM `{$p}htl_room_information`
             WHERE `room_num` NOT LIKE 'CON-%'"
        );
        if (!is_array($rooms)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($rooms as $rm) {
            $idRoom = (int) $rm['id'];
            $exists = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . $p . 'lvh_room_housekeeping` WHERE `id_room` = ' . $idRoom
            );
            if ($exists === 0) {
                $initStatus = ((int) $rm['id_status'] === 2) ? 'ooo' : 'clean';
                Db::getInstance()->insert('lvh_room_housekeeping', array(
                    'id_room' => $idRoom,
                    'hk_status' => pSQL($initStatus),
                    'assigned_attendant' => '',
                    'maintenance_note' => pSQL(Tools::substr((string) $rm['comment'], 0, 255)),
                    'ooo_until' => null,
                    'updated_by_name' => 'Initial Seed',
                    'date_upd' => pSQL($now),
                ));
            }
        }
    }

    /**
     * Update a room's housekeeping status, log the transition, and sync native
     * `htl_room_information.id_status` when entering or leaving Out-of-Order (`ooo`).
     *
     * @param int         $idRoom
     * @param string      $newStatus
     * @param string      $attendant
     * @param string      $note
     * @param string      $staffName
     * @param string|null $oooUntil
     * @return bool
     */
    public static function setRoomStatus($idRoom, $newStatus, $attendant = '', $note = '', $staffName = 'System', $oooUntil = null)
    {
        $idRoom = (int) $idRoom;
        $defs = self::getStatusDefinitions();
        if ($idRoom <= 0 || !isset($defs[$newStatus])) {
            return false;
        }

        $p = _DB_PREFIX_;
        $roomRow = Db::getInstance()->getRow(
            'SELECT `id`, `room_num`, `id_status` FROM `' . $p . 'htl_room_information` WHERE `id` = ' . $idRoom
        );
        if (!$roomRow || stripos((string) $roomRow['room_num'], 'CON-') === 0) {
            return false;
        }

        $cur = Db::getInstance()->getRow(
            'SELECT * FROM `' . $p . 'lvh_room_housekeeping` WHERE `id_room` = ' . $idRoom
        );
        $oldStatus = $cur ? (string) $cur['hk_status'] : 'clean';
        $now = date('Y-m-d H:i:s');

        $validOooDate = ($oooUntil && preg_match('/^\d{4}-\d{2}-\d{2}$/', $oooUntil)) ? $oooUntil : null;

        if ($cur) {
            Db::getInstance()->update(
                'lvh_room_housekeeping',
                array(
                    'hk_status' => pSQL($newStatus),
                    'assigned_attendant' => pSQL(Tools::substr($attendant, 0, 128)),
                    'maintenance_note' => pSQL(Tools::substr($note, 0, 255)),
                    'ooo_until' => $validOooDate,
                    'updated_by_name' => pSQL(Tools::substr($staffName, 0, 128)),
                    'date_upd' => pSQL($now),
                ),
                '`id_room` = ' . $idRoom
            );
        } else {
            Db::getInstance()->insert('lvh_room_housekeeping', array(
                'id_room' => $idRoom,
                'hk_status' => pSQL($newStatus),
                'assigned_attendant' => pSQL(Tools::substr($attendant, 0, 128)),
                'maintenance_note' => pSQL(Tools::substr($note, 0, 255)),
                'ooo_until' => $validOooDate,
                'updated_by_name' => pSQL(Tools::substr($staffName, 0, 128)),
                'date_upd' => pSQL($now),
            ));
        }

        // Sync Out-of-Order (`ooo`) with QloApps native `htl_room_information.id_status` (2 = Inactive, 1 = Active)
        $targetNativeStatus = ($newStatus === 'ooo') ? 2 : 1;
        if ((int) $roomRow['id_status'] !== $targetNativeStatus) {
            Db::getInstance()->update(
                'htl_room_information',
                array(
                    'id_status' => $targetNativeStatus,
                    'comment' => pSQL(Tools::substr($note, 0, 255)),
                    'date_upd' => pSQL($now),
                ),
                '`id` = ' . $idRoom
            );
        }

        Db::getInstance()->insert('lvh_housekeeping_log', array(
            'id_room' => $idRoom,
            'room_num' => pSQL((string) $roomRow['room_num']),
            'old_status' => pSQL($oldStatus),
            'new_status' => pSQL($newStatus),
            'note' => pSQL(Tools::substr($note, 0, 255)),
            'staff_name' => pSQL(Tools::substr($staffName, 0, 128)),
            'date_add' => pSQL($now),
        ));

        return true;
    }

    /**
     * Fetch all 34 physical guest rooms with floor, room type, current occupancy
     * today, and live housekeeping status.
     *
     * @param int $idLang
     * @return array
     */
    public static function getBoardRooms($idLang)
    {
        self::seedPhysicalRooms();
        $p = _DB_PREFIX_;
        $today = date('Y-m-d');
        $idLang = (int) $idLang;

        $sql = "SELECT hr.`id` AS `id_room`, hr.`room_num`, hr.`floor`, hr.`id_product`, hr.`id_status` AS `native_status`,
                       COALESCE(pl.`name`, 'Room') AS `room_type`,
                       COALESCE(hk.`hk_status`, 'clean') AS `hk_status`,
                       COALESCE(hk.`assigned_attendant`, '') AS `assigned_attendant`,
                       COALESCE(hk.`maintenance_note`, '') AS `maintenance_note`,
                       hk.`ooo_until`,
                       COALESCE(hk.`updated_by_name`, '') AS `updated_by_name`,
                       hk.`date_upd`,
                       (SELECT hbd.`id_status`
                        FROM `{$p}htl_booking_detail` hbd
                        WHERE hbd.`id_room` = hr.`id`
                          AND hbd.`is_refunded` = 0 AND hbd.`is_back_order` = 0
                          AND hbd.`id_status` IN (1, 2)
                          AND hbd.`date_from` <= '{$today} 23:59:59'
                          AND hbd.`date_to` > '{$today} 00:00:00'
                        ORDER BY hbd.`id_status` DESC, hbd.`id` DESC
                        LIMIT 1) AS `occ_status`,
                       (SELECT CONCAT(c.`firstname`, ' ', c.`lastname`)
                        FROM `{$p}htl_booking_detail` hbd2
                        INNER JOIN `{$p}customer` c ON (c.`id_customer` = hbd2.`id_customer`)
                        WHERE hbd2.`id_room` = hr.`id`
                          AND hbd2.`is_refunded` = 0
                          AND hbd2.`id_status` IN (1, 2)
                          AND hbd2.`date_from` <= '{$today} 23:59:59'
                          AND hbd2.`date_to` > '{$today} 00:00:00'
                        ORDER BY hbd2.`id_status` DESC, hbd2.`id` DESC
                        LIMIT 1) AS `guest_name`
                FROM `{$p}htl_room_information` hr
                LEFT JOIN `{$p}product_lang` pl ON (pl.`id_product` = hr.`id_product` AND pl.`id_lang` = {$idLang})
                LEFT JOIN `{$p}lvh_room_housekeeping` hk ON (hk.`id_room` = hr.`id`)
                WHERE hr.`room_num` NOT LIKE 'CON-%'
                ORDER BY hr.`floor` ASC, hr.`room_num` ASC";

        $rows = Db::getInstance()->executeS($sql);
        return is_array($rows) ? $rows : array();
    }
}
