<?php
/**
 * Lush View Hotel - Immutable Staff Action & Security Audit Storage / API.
 *
 * Provides `LvhStaffAudit::record(...)` so core hooks and sibling LVH modules
 * (lvhpayments, lvhfrontdesk, lvhhousekeeping, lvhconference, lvhreports)
 * write to one append-only staff accountability trail.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhStaffAudit
{
    /**
     * Create the append-only audit log table idempotently.
     *
     * @return bool
     */
    public static function createTables()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'lvh_audit_log` (
            `id_audit_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event_code` VARCHAR(64) NOT NULL,
            `severity` VARCHAR(16) NOT NULL DEFAULT \'info\',
            `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
            `employee_name` VARCHAR(128) NOT NULL DEFAULT \'\',
            `role_name` VARCHAR(64) NOT NULL DEFAULT \'\',
            `object_type` VARCHAR(48) NOT NULL DEFAULT \'\',
            `object_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `summary` VARCHAR(255) NOT NULL DEFAULT \'\',
            `details_json` TEXT NULL,
            `ip_addr` VARCHAR(45) NOT NULL DEFAULT \'\',
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_audit_log`),
            KEY `idx_event_code` (`event_code`),
            KEY `idx_employee` (`id_employee`),
            KEY `idx_object` (`object_type`, `object_id`),
            KEY `idx_date_add` (`date_add`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return (bool) Db::getInstance()->execute($sql);
    }

    /**
     * Record an append-only staff/system audit event. Safe to call from any
     * module; silently no-ops if the table has not been installed yet.
     *
     * @param string $eventCode
     * @param string $objectType
     * @param int    $objectId
     * @param string $summary
     * @param array  $details
     * @param string $severity   info|notice|warning|critical
     * @return bool
     */
    public static function record($eventCode, $objectType, $objectId, $summary, $details = array(), $severity = 'info')
    {
        try {
            $ctx = Context::getContext();
            $idEmployee = 0;
            $empName = 'System / Front-Office';
            $roleName = 'Guest / System';

            if (isset($ctx->employee) && Validate::isLoadedObject($ctx->employee)) {
                $idEmployee = (int) $ctx->employee->id;
                $empName = trim($ctx->employee->firstname . ' ' . $ctx->employee->lastname);
                $idLang = (isset($ctx->language) && (int) $ctx->language->id > 0)
                    ? (int) $ctx->language->id
                    : (int) Configuration::get('PS_LANG_DEFAULT');
                $profileRow = Profile::getProfile((int) $ctx->employee->id_profile, $idLang);
                if (is_array($profileRow) && !empty($profileRow['name'])) {
                    $roleName = (string) $profileRow['name'];
                } else {
                    $roleName = 'Profile #' . (int) $ctx->employee->id_profile;
                }
            }

            $ip = (string) Tools::getRemoteAddr();
            $json = !empty($details) ? json_encode($details) : null;

            return (bool) Db::getInstance()->insert('lvh_audit_log', array(
                'event_code' => pSQL( Tools::substr((string) $eventCode, 0, 64) ),
                'severity' => pSQL( Tools::substr((string) $severity, 0, 16) ),
                'id_employee' => (int) $idEmployee,
                'employee_name' => pSQL( Tools::substr($empName, 0, 128) ),
                'role_name' => pSQL( Tools::substr($roleName, 0, 64) ),
                'object_type' => pSQL( Tools::substr((string) $objectType, 0, 48) ),
                'object_id' => (int) $objectId,
                'summary' => pSQL( Tools::substr((string) $summary, 0, 255) ),
                'details_json' => $json !== null ? pSQL($json, true) : null,
                'ip_addr' => pSQL( Tools::substr($ip, 0, 45) ),
                'date_add' => date('Y-m-d H:i:s'),
            ), true);
        } catch (Exception $e) {
            return false;
        } catch (Error $e) {
            return false;
        }
    }

    /**
     * Query audit log entries with optional filters.
     *
     * @param array $filters
     * @param int   $limit
     * @return array
     */
    public static function getEntries($filters = array(), $limit = 250)
    {
        $where = array('1=1');

        if (!empty($filters['date_from']) && Validate::isDate($filters['date_from'])) {
            $where[] = "`date_add` >= '" . pSQL($filters['date_from']) . " 00:00:00'";
        }
        if (!empty($filters['date_to']) && Validate::isDate($filters['date_to'])) {
            $where[] = "`date_add` <= '" . pSQL($filters['date_to']) . " 23:59:59'";
        }
        if (!empty($filters['event_code'])) {
            $where[] = "`event_code` = '" . pSQL($filters['event_code']) . "'";
        }
        if (!empty($filters['id_employee']) && (int) $filters['id_employee'] > 0) {
            $where[] = '`id_employee` = ' . (int) $filters['id_employee'];
        }
        if (!empty($filters['severity'])) {
            $where[] = "`severity` = '" . pSQL($filters['severity']) . "'";
        }
        if (!empty($filters['q'])) {
            $q = pSQL($filters['q']);
            $where[] = "(`summary` LIKE '%" . $q . "%' OR `employee_name` LIKE '%" . $q . "%' OR `object_type` LIKE '%" . $q . "%')";
        }

        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'lvh_audit_log`
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY `id_audit_log` DESC
                LIMIT ' . max(1, min(2000, (int) $limit));

        $rows = Db::getInstance()->executeS($sql);
        return is_array($rows) ? $rows : array();
    }

    /**
     * Ensure the shared parent menu tab `AdminLvhOperations` ("Lush View Ops")
     * exists and return its id_tab.
     *
     * @return int
     */
    public static function ensureParentOperationsTab()
    {
        $existing = (int) Tab::getIdFromClassName('AdminLvhOperations');
        if ($existing > 0) {
            return $existing;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminLvhOperations';
        $tab->id_parent = 0;
        $tab->module = '';
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Lush View Ops';
        }
        $tab->add();

        $tabId = (int) $tab->id;
        if ($tabId > 0) {
            self::grantRoleAccess($tabId, array('reception', 'supervisor', 'accountant', 'manager', 'it'), 1, 0, 0, 0);
        }

        return $tabId;
    }

    /**
     * Grant tab permissions to Lush View Hotel staff roles matched by profile
     * name substring (e.g. 'reception' matches 'Receptionists').
     *
     * @param int   $idTab
     * @param array $roleKeywords
     * @param int   $view
     * @param int   $add
     * @param int   $edit
     * @param int   $delete
     */
    public static function grantRoleAccess($idTab, $roleKeywords, $view = 1, $add = 0, $edit = 0, $delete = 0)
    {
        if ((int) $idTab <= 0 || empty($roleKeywords)) {
            return;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT id_profile, LOWER(name) AS lname FROM `' . _DB_PREFIX_ . 'profile_lang`'
        );
        if (!is_array($rows)) {
            return;
        }

        $matchedProfiles = array();
        foreach ($rows as $r) {
            $pid = (int) $r['id_profile'];
            if ($pid <= 1) {
                continue;
            }
            foreach ($roleKeywords as $kw) {
                if (strpos((string) $r['lname'], strtolower($kw)) !== false) {
                    $matchedProfiles[$pid] = $pid;
                    break;
                }
            }
        }

        if (empty($matchedProfiles)) {
            return;
        }

        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'access`
             SET `view` = ' . (int) $view . ',
                 `add` = ' . (int) $add . ',
                 `edit` = ' . (int) $edit . ',
                 `delete` = ' . (int) $delete . '
             WHERE `id_tab` = ' . (int) $idTab . '
               AND `id_profile` IN (' . implode(',', array_map('intval', $matchedProfiles)) . ')'
        );
    }

    /**
     * Remove `AdminLvhOperations` parent tab only if no child tabs remain.
     */
    public static function cleanupParentOperationsTabIfEmpty()
    {
        $parentId = (int) Tab::getIdFromClassName('AdminLvhOperations');
        if ($parentId <= 0) {
            return;
        }
        $children = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'tab` WHERE `id_parent` = ' . $parentId
        );
        if ($children === 0) {
            $parentTab = new Tab($parentId);
            if (Validate::isLoadedObject($parentTab)) {
                $parentTab->delete();
            }
        }
    }
}
