<?php
/**
 * Lush View Hotel - Accountant Daily Flash, Reconciliation, VIP & Hygiene Module.
 *
 * Delivers accurate 34-room occupancy/ADR/RevPAR metrics (excluding `CON-*`),
 * normalized tender reconciliation across historical free-text spellings,
 * unpaid/partial balance aging, VIP repeat-guest leaderboard, and 1-click
 * SuperAdmin production hygiene actions.
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

if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class LvhReports extends Module
{
    public function __construct()
    {
        $this->name = 'lvhreports';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH Accountant Flash, Reconciliation & Hygiene');
        $this->description = $this->l('True 34-room occupancy/ADR/RevPAR flash, normalized Ghana tender reconciliation, open-balance aging, VIP leaderboard, and 1-click system hygiene.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (!parent::install() || !$this->installTab()) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        $this->uninstallTab();
        return parent::uninstall();
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminLvhReports'));
        return '';
    }

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

        $existing = (int) Tab::getIdFromClassName('AdminLvhReports');
        if ($existing > 0) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminLvhReports';
        $tab->id_parent = $parentId;
        $tab->module = $this->name;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Daily Flash & Accounting';
        }

        if (!$tab->add()) {
            return false;
        }

        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::grantRoleAccess(
                (int) $tab->id,
                array('supervisor', 'accountant', 'manager', 'it'),
                1,
                1,
                1,
                0
            );
        }
        return true;
    }

    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminLvhReports');
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
}
