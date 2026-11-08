<?php
/**
 * Lush View Hotel - Housekeeping & Room Readiness Board Module.
 *
 * Tracks Clean, Dirty, Cleaning, Inspecting, and Out-of-Order status across
 * Lush View Hotel's 34 physical guest rooms, auto-flips checked-out rooms to
 * Dirty via `actionObjectHotelBookingDetailUpdateAfter`, and blocks website
 * reservations on Out-of-Order rooms.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/LvhHousekeepingStatus.php';
if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class LvhHousekeeping extends Module
{
    public function __construct()
    {
        $this->name = 'lvhhousekeeping';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH Housekeeping & Room Readiness');
        $this->description = $this->l('34-room First & Second Floor Clean/Dirty/OOO board with automatic check-out dirty trigger.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install tables, check-out auto-dirty hook, and admin menu tab.
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()
            || !LvhHousekeepingStatus::createTables()
            || !$this->registerHook('actionObjectHotelBookingDetailUpdateAfter')
            || !$this->installTab()
        ) {
            return false;
        }
        return true;
    }

    /**
     * Remove admin menu tab on uninstall.
     *
     * @return bool
     */
    public function uninstall()
    {
        $this->uninstallTab();
        return parent::uninstall();
    }

    /**
     * Redirect Configure link to Housekeeping Board.
     *
     * @return string
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminLvhHousekeeping'));
        return '';
    }

    /**
     * Install `AdminLvhHousekeeping` under `AdminLvhOperations` for Receptionists,
     * Supervisors, Managers, and IT.
     *
     * @return bool
     */
    protected function installTab()
    {
        $parentId = class_exists('LvhStaffAudit')
            ? LvhStaffAudit::ensureParentOperationsTab()
            : (int) Tab::getIdFromClassName('AdminLvhOperations');

        if ($parentId <= 0) {
            $pTab = new Tab();
            $pTab->class_name = 'AdminLvhOperations';
            $pTab->id_parent = 0;
            $pTab->module = '';
            $pTab->active = 1;
            foreach (Language::getLanguages(false) as $lang) {
                $pTab->name[(int) $lang['id_lang']] = 'Lush View Ops';
            }
            $pTab->add();
            $parentId = (int) $pTab->id;
        }

        $existing = (int) Tab::getIdFromClassName('AdminLvhHousekeeping');
        if ($existing > 0) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminLvhHousekeeping';
        $tab->id_parent = $parentId;
        $tab->module = $this->name;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Housekeeping Board';
        }

        if (!$tab->add()) {
            return false;
        }

        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::grantRoleAccess(
                (int) $tab->id,
                array('receptionist', 'supervisor', 'manager', 'it'),
                1,
                1,
                1,
                0
            );
        }
        return true;
    }

    /**
     * Remove `AdminLvhHousekeeping` tab on uninstall.
     */
    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminLvhHousekeeping');
        if ($idTab > 0) {
            $tab = new Tab($idTab);
            if (Validate::isLoadedObject($tab)) {
                $tab->delete();
            }
        }
        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::cleanupParentOperationsTabIfEmpty();
        }
    }

    /**
     * Automatically mark physical guest rooms as `dirty` when a guest checks out (`id_status == 3`).
     *
     * @param array $params
     */
    public function hookActionObjectHotelBookingDetailUpdateAfter($params)
    {
        if (!isset($params['object'])) {
            return;
        }
        $bd = $params['object'];
        $idStatus = isset($bd->id_status) ? (int) $bd->id_status : 0;
        $idRoom = isset($bd->id_room) ? (int) $bd->id_room : 0;

        if ($idStatus === 3 && $idRoom > 0) {
            $staffName = (isset($this->context->employee) && Validate::isLoadedObject($this->context->employee))
                ? trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname)
                : 'Auto Check-Out';
            LvhHousekeepingStatus::setRoomStatus(
                $idRoom,
                'dirty',
                '',
                'Auto-marked Dirty upon guest Check-Out (Order #' . (int) $bd->id_order . ')',
                $staffName
            );
        }
    }
}
