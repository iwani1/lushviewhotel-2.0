<?php
/**
 * Lush View Hotel - Staff Action & Security Audit Trail Admin Controller.
 *
 * Read-only append-only viewer + CSV exporter for Supervisors, Accountants,
 * Managers, IT Team, and SuperAdmin.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhStaffAudit.php';

class AdminLvhAuditLogController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Staff Audit Trail');
    }

    /**
     * Handle CSV export before rendering HTML.
     */
    public function postProcess()
    {
        parent::postProcess();

        if (Tools::isSubmit('exportLvhAuditCsv')) {
            $filters = $this->readFilters();
            $rows = LvhStaffAudit::getEntries($filters, 2000);
            $this->streamCsv($rows);
        }
    }

    /**
     * Render the filterable audit trail table.
     */
    public function initContent()
    {
        parent::initContent();

        $filters = $this->readFilters();
        $entries = LvhStaffAudit::getEntries($filters, 300);

        $eventCodes = Db::getInstance()->executeS(
            'SELECT DISTINCT `event_code` FROM `' . _DB_PREFIX_ . 'lvh_audit_log` ORDER BY `event_code`'
        );
        $employees = Db::getInstance()->executeS(
            'SELECT DISTINCT `id_employee`, `employee_name`, `role_name`
             FROM `' . _DB_PREFIX_ . 'lvh_audit_log`
             WHERE `id_employee` > 0
             ORDER BY `employee_name`'
        );

        $totalCount = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'lvh_audit_log`'
        );
        $todayCount = (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "lvh_audit_log` WHERE `date_add` >= '" . pSQL(date('Y-m-d')) . " 00:00:00'"
        );
        $warnCount = (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "lvh_audit_log` WHERE `severity` IN ('warning', 'critical')"
        );

        $this->context->smarty->assign(array(
            'lvh_entries' => $entries,
            'lvh_filters' => $filters,
            'lvh_event_codes' => is_array($eventCodes) ? $eventCodes : array(),
            'lvh_employees' => is_array($employees) ? $employees : array(),
            'lvh_stats' => array(
                'total' => $totalCount,
                'today' => $todayCount,
                'warnings' => $warnCount,
            ),
            'lvh_controller_url' => $this->context->link->getAdminLink('AdminLvhAuditLog'),
        ));

        $tpl = _PS_MODULE_DIR_ . 'lvhauditlog/views/templates/admin/audit-log.tpl';
        $this->content .= $this->context->smarty->fetch($tpl);
        $this->context->smarty->assign('content', $this->content);
    }

    /**
     * Collect request filter inputs safely.
     *
     * @return array
     */
    protected function readFilters()
    {
        return array(
            'date_from' => trim((string) Tools::getValue('date_from', date('Y-m-01'))),
            'date_to' => trim((string) Tools::getValue('date_to', date('Y-m-d'))),
            'event_code' => trim((string) Tools::getValue('event_code', '')),
            'id_employee' => (int) Tools::getValue('id_employee', 0),
            'severity' => trim((string) Tools::getValue('severity', '')),
            'q' => trim((string) Tools::getValue('q', '')),
        );
    }

    /**
     * Stream filtered audit rows as UTF-8 CSV and exit.
     *
     * @param array $rows
     */
    protected function streamCsv($rows)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $filename = 'lushview-staff-audit-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, array('ID', 'Timestamp', 'Severity', 'Event', 'Employee', 'Role', 'Object Type', 'Object ID', 'Summary', 'Details', 'IP'));

        foreach ($rows as $r) {
            fputcsv($out, array(
                $r['id_audit_log'],
                $r['date_add'],
                $r['severity'],
                $r['event_code'],
                $r['employee_name'],
                $r['role_name'],
                $r['object_type'],
                $r['object_id'],
                $r['summary'],
                $r['details_json'],
                $r['ip_addr'],
            ));
        }

        fclose($out);
        exit;
    }
}
