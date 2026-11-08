<?php
/**
 * Lush View Hotel - Express Front Desk & 34-Room Visual Rack Module.
 *
 * Delivers a 1-screen Front Desk command center combining live occupancy across
 * Lush View Hotel's 34 guest rooms, housekeeping readiness, 30-second walk-in
 * booking & instant check-in, VIP repeat-guest lookup, Ghana Card / Passport
 * ID capture, 1-click Check-In/Out, and room folio extras.
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

require_once dirname(__FILE__) . '/classes/LvhFrontDeskService.php';
if (file_exists(_PS_MODULE_DIR_ . 'lvhpayments/classes/LvhPaymentNormalizer.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhpayments/classes/LvhPaymentNormalizer.php';
}
if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class LvhFrontDesk extends Module
{
    public function __construct()
    {
        $this->name = 'lvhfrontdesk';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH Express Front Desk & 34-Room Rack');
        $this->description = $this->l('1-screen 34-room visual rack, 30s walk-in & instant check-in, VIP repeat-guest badge, Ghana Card ID capture, and folio extras.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install database tables, Order View hook, and Front Desk menu tab.
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()
            || !LvhFrontDeskService::createTables()
            || !$this->registerHook('displayAdminOrderRight')
            || !$this->installTab()
        ) {
            return false;
        }
        return true;
    }

    /**
     * Uninstall menu tab (preserves guest ID & folio history).
     *
     * @return bool
     */
    public function uninstall()
    {
        $this->uninstallTab();
        return parent::uninstall();
    }

    /**
     * Redirect Configure link to Express Front Desk screen.
     *
     * @return string
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminLvhFrontDesk'));
        return '';
    }

    /**
     * Install `AdminLvhFrontDesk` at the top of `AdminLvhOperations` for
     * Receptionists, Supervisors, Accountants (view), Managers, and IT.
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

        $existing = (int) Tab::getIdFromClassName('AdminLvhFrontDesk');
        if ($existing > 0) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminLvhFrontDesk';
        $tab->id_parent = $parentId;
        $tab->module = $this->name;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Front Desk & Room Rack';
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

    /**
     * Remove `AdminLvhFrontDesk` tab on uninstall.
     */
    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminLvhFrontDesk');
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
     * Render Guest ID, VIP badge, and Folio Extras panel on native `AdminOrders` View.
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderRight($params)
    {
        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : (int) Tools::getValue('id_order');
        if ($idOrder <= 0) {
            return '';
        }
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return '';
        }

        $staffName = (isset($this->context->employee) && Validate::isLoadedObject($this->context->employee))
            ? trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname)
            : 'Front Desk';
        $isSupervisor = class_exists('LvhPaymentNormalizer')
            ? LvhPaymentNormalizer::canAuthorizeSupervisorActions($this->context)
            : ((int) $this->context->employee->id_profile === 1);

        $redirectUrl = $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . (int) $order->id . '&vieworder';

        // Handle inline Guest ID save
        if (Tools::isSubmit('submitLvhSaveGuestDoc')) {
            LvhFrontDeskService::saveGuestIdentification(
                (int) $order->id_customer,
                (int) $order->id,
                (string) Tools::getValue('lvh_doc_type', 'Ghana Card'),
                (string) Tools::getValue('lvh_doc_number', ''),
                (string) Tools::getValue('lvh_nationality', 'Ghanaian'),
                $staffName
            );
            Tools::redirectAdmin($redirectUrl);
        }

        // Handle inline Folio Extra add
        if (Tools::isSubmit('submitLvhAddFolioExtra')) {
            $cat = trim((string) Tools::getValue('lvh_extra_category', 'Restaurant / Bar'));
            $desc = trim((string) Tools::getValue('lvh_extra_desc', ''));
            $amt = (float) Tools::getValue('lvh_extra_amount', 0);
            if ($amt > 0) {
                Db::getInstance()->insert('lvh_folio_charge', array(
                    'id_order' => (int) $order->id,
                    'id_room' => 0,
                    'category' => pSQL(Tools::substr($cat, 0, 64)),
                    'description' => pSQL(Tools::substr($desc ?: $cat, 0, 255)),
                    'amount' => $amt,
                    'recorded_by_name' => pSQL(Tools::substr($staffName, 0, 128)),
                    'voided' => 0,
                    'voided_by_name' => '',
                    'date_add' => date('Y-m-d H:i:s'),
                ));
                if (class_exists('LvhStaffAudit')) {
                    LvhStaffAudit::record(
                        'folio.extra_added',
                        'Order',
                        (int) $order->id,
                        sprintf('Posted %s extra (GHS %.2f) on Order #%d', $cat, $amt, (int) $order->id)
                    );
                }
            }
            Tools::redirectAdmin($redirectUrl);
        }

        // Handle Supervisor Folio Extra void
        if (Tools::isSubmit('submitLvhVoidFolio') && $isSupervisor) {
            $idFc = (int) Tools::getValue('id_folio_charge');
            if ($idFc > 0) {
                Db::getInstance()->update(
                    'lvh_folio_charge',
                    array('voided' => 1, 'voided_by_name' => pSQL(Tools::substr($staffName, 0, 128))),
                    '`id_folio_charge` = ' . $idFc . ' AND `id_order` = ' . (int) $order->id
                );
                if (class_exists('LvhStaffAudit')) {
                    LvhStaffAudit::record(
                        'folio.extra_voided',
                        'Order',
                        (int) $order->id,
                        sprintf('Voided FolioCharge #%d on Order #%d', $idFc, (int) $order->id),
                        array('id_folio_charge' => $idFc),
                        'warning'
                    );
                }
            }
            Tools::redirectAdmin($redirectUrl);
        }

        $p = _DB_PREFIX_;
        $staysCount = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . $p . 'orders` WHERE `id_customer` = ' . (int) $order->id_customer . ' AND `valid` = 1'
        );
        $guestDoc = Db::getInstance()->getRow(
            'SELECT * FROM `' . $p . 'lvh_guest_identification`
             WHERE `id_customer` = ' . (int) $order->id_customer . '
             ORDER BY `id_guest_id` DESC'
        );
        $folioExtras = Db::getInstance()->executeS(
            'SELECT * FROM `' . $p . 'lvh_folio_charge`
             WHERE `id_order` = ' . (int) $order->id . '
             ORDER BY `id_folio_charge` ASC'
        );

        $this->context->smarty->assign(array(
            'lvh_stays_count' => $staysCount,
            'lvh_vip_tier' => LvhFrontDeskService::computeVipTier($staysCount),
            'lvh_guest_doc' => $guestDoc ?: null,
            'lvh_folio_extras' => is_array($folioExtras) ? $folioExtras : array(),
            'lvh_is_supervisor' => $isSupervisor,
            'lvh_post_url' => $redirectUrl,
        ));

        return $this->display(__FILE__, 'views/templates/hook/admin-order-guest-folio.tpl');
    }
}
