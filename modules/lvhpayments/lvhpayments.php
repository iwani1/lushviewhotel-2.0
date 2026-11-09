<?php
/**
 * Lush View Hotel - Canonical Payments, MoMo Reference Guard & Shift Cash-Up.
 *
 * Standardizes front-desk payment capture (`Cash (GHS)`, `MTN MoMo`, `Telecel Cash`,
 * `AT Money`, `POS / Card`, `Bank Transfer`, `Corporate / Pay Later`, `Management Comp`),
 * enforces transaction IDs on electronic tenders, adds 80mm thermal & A4 guest receipts,
 * and provides receptionist shift cash-up + accountant verification.
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

require_once dirname(__FILE__) . '/classes/LvhPaymentNormalizer.php';
if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class LvhPayments extends Module
{
    public function __construct()
    {
        $this->name = 'lvhpayments';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.1';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH Payments, MoMo & Shift Cash-Up');
        $this->description = $this->l('Canonical Ghana payment dropdowns, mandatory MoMo/POS references, thermal/A4 receipts, and shift cash-up reconciliation.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install database tables, hooks, and menu tab.
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()
            || !LvhPaymentNormalizer::createTables()
            || !$this->registerHook('actionAdminControllerSetMedia')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('actionObjectOrderPaymentAddAfter')
            || !$this->registerHook('displayAdminOrderLeft')
            || !$this->installTab()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Remove menu tab on uninstall (preserves financial payment meta & shift history).
     *
     * @return bool
     */
    public function uninstall()
    {
        $this->uninstallTab();
        return parent::uninstall();
    }

    /**
     * Redirect module Configure button to Shift Cash-Up & Payments screen.
     *
     * @return string
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminLvhPayments'));
        return '';
    }

    /**
     * Install `AdminLvhPayments` tab under `AdminLvhOperations` with role access.
     *
     * @return bool
     */
    protected function installTab()
    {
        $parentId = $this->ensureOperationsParent();
        $existing = (int) Tab::getIdFromClassName('AdminLvhPayments');
        if ($existing > 0) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminLvhPayments';
        $tab->id_parent = $parentId;
        $tab->module = $this->name;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Shift Cash-Up & Payments';
        }

        if (!$tab->add()) {
            return false;
        }

        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::grantRoleAccess(
                (int) $tab->id,
                array('receptionist', 'supervisor', 'accountant', 'manager', 'it'),
                1,
                1,
                1,
                0
            );
        }
        return true;
    }

    /**
     * Ensure shared `AdminLvhOperations` parent menu exists even if installed first.
     *
     * @return int
     */
    protected function ensureOperationsParent()
    {
        if (class_exists('LvhStaffAudit')) {
            return LvhStaffAudit::ensureParentOperationsTab();
        }
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
        return $tab->add() ? (int) $tab->id : 0;
    }

    /**
     * Remove `AdminLvhPayments` tab on uninstall.
     */
    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminLvhPayments');
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
     * Load canonical payment dropdown JS on `AdminOrders`.
     */
    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('controller') === 'AdminOrders') {
            $this->context->controller->addJS($this->_path . 'views/js/lvh-order-payments.js');
        }
    }

    /**
     * Pass role-filtered canonical payment methods to `AdminOrders` JS.
     *
     * @return string
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('controller') !== 'AdminOrders') {
            return '';
        }

        $includeComp = LvhPaymentNormalizer::canAuthorizeSupervisorActions($this->context);
        $methods = LvhPaymentNormalizer::getCanonicalMethods($includeComp);
        $jsList = array();
        foreach ($methods as $m) {
            $jsList[] = array(
                'code' => $m['code'],
                'label' => $m['label'],
                'requires_ref' => (bool) $m['requires_ref'],
                'placeholder' => $m['ref_placeholder'],
            );
        }

        return '<script type="text/javascript">window.LVH_PAYMENT_METHODS = '
            . json_encode($jsList)
            . ';</script>';
    }

    /**
     * Automatically stamp staff attribution & canonical code whenever an
     * `OrderPayment` row is created anywhere in QloApps.
     *
     * @param array $params
     */
    public function hookActionObjectOrderPaymentAddAfter($params)
    {
        if (!isset($params['object']) || !($params['object'] instanceof OrderPayment)) {
            return;
        }
        $op = $params['object'];
        $cls = LvhPaymentNormalizer::classify($op->payment_method);

        $idOrder = (int) Db::getInstance()->getValue(
            "SELECT `id_order` FROM `" . _DB_PREFIX_ . "orders`
             WHERE `reference` = '" . pSQL($op->order_reference) . "'
             ORDER BY `id_order` ASC"
        );

        $empId = (isset($this->context->employee) && Validate::isLoadedObject($this->context->employee))
            ? (int) $this->context->employee->id
            : 0;
        $empName = ($empId > 0)
            ? trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname)
            : 'Front Office / System';

        $exists = (int) Db::getInstance()->getValue(
            'SELECT `id_payment_meta` FROM `' . _DB_PREFIX_ . 'lvh_payment_meta` WHERE `id_order_payment` = ' . (int) $op->id
        );
        if ($exists > 0) {
            return;
        }

        Db::getInstance()->insert('lvh_payment_meta', array(
            'id_order_payment' => (int) $op->id,
            'id_order' => $idOrder,
            'canonical_code' => pSQL($cls['code']),
            'payer_phone' => '',
            'recorded_by_employee' => $empId,
            'recorded_by_name' => pSQL(Tools::substr($empName, 0, 128)),
            'verified_status' => 'pending',
            'verified_by_employee' => 0,
            'verified_by_name' => '',
            'verified_at' => null,
            'notes' => '',
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    /**
     * Render Lush View Folio Balance & Thermal/A4 Receipt card on Order View.
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderLeft($params)
    {
        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : (int) Tools::getValue('id_order');
        if ($idOrder <= 0) {
            return '';
        }
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return '';
        }

        $p = _DB_PREFIX_;
        $extrasTotal = 0.0;
        $hasFolio = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'lvh_folio_charge') . "'");
        if (!empty($hasFolio)) {
            $extrasTotal = (float) Db::getInstance()->getValue(
                'SELECT COALESCE(SUM(`amount`), 0) FROM `' . $p . 'lvh_folio_charge`
                 WHERE `id_order` = ' . (int) $order->id . ' AND `voided` = 0'
            );
        }

        $rows = Db::getInstance()->executeS(
            "SELECT op.`id_order_payment`, op.`date_add`, op.`payment_method`, op.`transaction_id`, op.`amount`,
                    pm.`recorded_by_name`, pm.`verified_status`
             FROM `{$p}order_payment` op
             LEFT JOIN `{$p}lvh_payment_meta` pm ON (pm.`id_order_payment` = op.`id_order_payment`)
             WHERE op.`order_reference` = '" . pSQL($order->reference) . "'
             ORDER BY op.`date_add` ASC"
        );

        $paidTotal = 0.0;
        $paymentRows = array();
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $cls = LvhPaymentNormalizer::classify($r['payment_method']);
                $r['canonical_label'] = $cls['label'];
                $r['recorded_by_name'] = !empty($r['recorded_by_name']) ? $r['recorded_by_name'] : 'System / Legacy';
                $r['verified_status'] = !empty($r['verified_status']) ? $r['verified_status'] : 'pending';
                $paidTotal += (float) $r['amount'];
                $paymentRows[] = $r;
            }
        }

        $grandTotal = (float) $order->total_paid_tax_incl + $extrasTotal;
        $balanceDue = max(0.0, round($grandTotal - $paidTotal, 2));
        $baseUrl = $this->context->link->getAdminLink('AdminLvhPayments') . '&printReceipt=1&id_order=' . (int) $order->id;

        $this->context->smarty->assign(array(
            'lvh_grand_total' => $grandTotal,
            'lvh_paid_total' => $paidTotal,
            'lvh_balance_due' => $balanceDue,
            'lvh_payment_rows' => $paymentRows,
            'lvh_thermal_url' => $baseUrl . '&format=thermal',
            'lvh_a4_url' => $baseUrl . '&format=a4',
        ));

        return $this->display(__FILE__, 'views/templates/hook/admin-order-left.tpl');
    }
}
