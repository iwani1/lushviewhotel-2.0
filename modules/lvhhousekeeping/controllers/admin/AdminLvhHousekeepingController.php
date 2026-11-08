<?php
/**
 * Lush View Hotel - Housekeeping & Room Readiness Board Controller.
 *
 * Interactive First & Second Floor board for the 34 physical guest rooms,
 * supporting 1-tap Clean / Dirty / Cleaning / Inspecting / Out-of-Order transitions,
 * attendant assignment, maintenance notes, and printable housekeeping worksheet.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhHousekeepingStatus.php';
if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class AdminLvhHousekeepingController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Housekeeping & Room Readiness');
    }

    /**
     * Handle single-room status updates and bulk mark-clean actions.
     */
    public function postProcess()
    {
        parent::postProcess();

        $staffName = isset($this->context->employee)
            ? trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname)
            : 'Staff';

        if (Tools::isSubmit('submitUpdateRoomHk')) {
            $idRoom = (int) Tools::getValue('id_room');
            $newStatus = trim((string) Tools::getValue('hk_status', 'clean'));
            $attendant = trim((string) Tools::getValue('assigned_attendant', ''));
            $note = trim((string) Tools::getValue('maintenance_note', ''));
            $oooUntil = trim((string) Tools::getValue('ooo_until', ''));

            if (LvhHousekeepingStatus::setRoomStatus($idRoom, $newStatus, $attendant, $note, $staffName, $oooUntil)) {
                if (class_exists('LvhStaffAudit')) {
                    LvhStaffAudit::record(
                        'housekeeping.status_changed',
                        'Room',
                        $idRoom,
                        sprintf('Room #%d housekeeping set to "%s"%s', $idRoom, $newStatus, $note ? ' (' . $note . ')' : ''),
                        array('hk_status' => $newStatus, 'attendant' => $attendant, 'note' => $note),
                        $newStatus === 'ooo' ? 'warning' : 'info'
                    );
                }
                $this->confirmations[] = $this->l('Room housekeeping status updated.');
            } else {
                $this->errors[] = $this->l('Invalid room or housekeeping status.');
            }
        }
    }

    /**
     * Render the 34-room floor readiness board and recent turnover log.
     */
    public function initContent()
    {
        parent::initContent();

        $rooms = LvhHousekeepingStatus::getBoardRooms((int) $this->context->language->id);
        $defs = LvhHousekeepingStatus::getStatusDefinitions();
        $filterStatus = trim((string) Tools::getValue('filter_status', ''));

        $counts = array(
            'all' => count($rooms),
            'clean' => 0,
            'dirty' => 0,
            'cleaning' => 0,
            'inspecting' => 0,
            'ooo' => 0,
        );

        $floors = array();
        foreach ($rooms as $r) {
            $st = isset($defs[$r['hk_status']]) ? $r['hk_status'] : 'clean';
            $counts[$st]++;
            if ($filterStatus !== '' && $filterStatus !== $st) {
                continue;
            }
            $r['status_meta'] = $defs[$st];
            $fl = !empty($r['floor']) ? (string) $r['floor'] . ' Floor' : 'Main Floor';
            if (!isset($floors[$fl])) {
                $floors[$fl] = array();
            }
            $floors[$fl][] = $r;
        }

        $logs = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'lvh_housekeeping_log`
             ORDER BY `id_hk_log` DESC LIMIT 25'
        );

        $this->context->smarty->assign(array(
            'lvh_floors' => $floors,
            'lvh_defs' => $defs,
            'lvh_counts' => $counts,
            'lvh_filter_status' => $filterStatus,
            'lvh_logs' => is_array($logs) ? $logs : array(),
            'lvh_controller_url' => $this->context->link->getAdminLink('AdminLvhHousekeeping'),
        ));

        $tpl = _PS_MODULE_DIR_ . 'lvhhousekeeping/views/templates/admin/housekeeping-board.tpl';
        $this->content .= $this->context->smarty->fetch($tpl);
        $this->context->smarty->assign('content', $this->content);
    }
}
