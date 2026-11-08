<?php
/**
 * Lush View Hotel - Shared Conference Hall Hourly Scheduler Module.
 *
 * Replaces Lush View Hotel's 4 separate `CON-1..CON-4` fake room types with one
 * unified hourly Conference Hall schedule supporting 1h (GHS 1,000), 2h (GHS 1,300),
 * 3h (GHS 1,600), Full-Day 24h (GHS 3,000), and Custom corporate bookings with
 * strict overlap blocking and printable Banquet Event Orders (BEO).
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

require_once dirname(__FILE__) . '/classes/LvhConferenceManager.php';
if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class LvhConference extends Module
{
    public function __construct()
    {
        $this->name = 'lvhconference';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH Shared Conference Hall Scheduler');
        $this->description = $this->l('Single physical Conference Hall hourly calendar (1h/2h/3h/24h/Custom) with overlap prevention and printable BEO receipts.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (!parent::install()
            || !LvhConferenceManager::createTables()
            || !$this->installTab()
        ) {
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
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminLvhConference'));
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

        $existing = (int) Tab::getIdFromClassName('AdminLvhConference');
        if ($existing > 0) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminLvhConference';
        $tab->id_parent = $parentId;
        $tab->module = $this->name;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Conference Hall';
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
            LvhStaffAudit::grantRoleAccess((int) $tab->id, array('accountant'), 1, 0, 0, 0);
        }
        return true;
    }

    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminLvhConference');
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
