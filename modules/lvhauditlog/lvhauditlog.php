<?php
/**
 * Lush View Hotel - Staff Action & Security Audit Trail Module.
 *
 * Automatically records staff logins, booking creations, status changes,
 * payment postings, check-ins/check-outs, management comps, and room-status
 * updates into an append-only audit trail under Lush View Ops -> Staff Audit Trail.
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

require_once dirname(__FILE__) . '/classes/LvhStaffAudit.php';

class LvhAuditLog extends Module
{
    public function __construct()
    {
        $this->name = 'lvhauditlog';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH Staff Action & Audit Log');
        $this->description = $this->l('Append-only accountability trail for bookings, payments, check-ins/outs, comps, and staff logins.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install database table, hooks, and menu tab with role permissions.
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()
            || !LvhStaffAudit::createTables()
            || !$this->registerHook('actionObjectOrderAddAfter')
            || !$this->registerHook('actionOrderStatusPostUpdate')
            || !$this->registerHook('actionObjectOrderPaymentAddAfter')
            || !$this->registerHook('actionObjectHotelBookingDetailUpdateAfter')
            || !$this->registerHook('actionAdminLoginControllerLoginAfter')
            || !$this->installTab()
        ) {
            return false;
        }

        LvhStaffAudit::record('module.installed', 'Module', (int) $this->id, 'LVH Staff Audit Log module installed');
        return true;
    }

    /**
     * Uninstall menu tab (deliberately preserves historical audit rows in DB).
     *
     * @return bool
     */
    public function uninstall()
    {
        $this->uninstallTab();
        return parent::uninstall();
    }

    /**
     * Redirect Configure link directly to the Staff Audit Trail screen.
     *
     * @return string
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminLvhAuditLog'));
        return '';
    }

    /**
     * Create `AdminLvhAuditLog` under `AdminLvhOperations` and grant view-only
     * access to Supervisors, Accountants, Managers, and IT Team.
     *
     * @return bool
     */
    protected function installTab()
    {
        $parentId = LvhStaffAudit::ensureParentOperationsTab();
        $existing = (int) Tab::getIdFromClassName('AdminLvhAuditLog');
        if ($existing > 0) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminLvhAuditLog';
        $tab->id_parent = $parentId;
        $tab->module = $this->name;
        $tab->active = 1;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Staff Audit Trail';
        }

        if (!$tab->add()) {
            return false;
        }

        LvhStaffAudit::grantRoleAccess((int) $tab->id, array('supervisor', 'accountant', 'manager', 'it'), 1, 0, 0, 0);
        return true;
    }

    /**
     * Remove `AdminLvhAuditLog` tab on uninstall.
     */
    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminLvhAuditLog');
        if ($idTab > 0) {
            $tab = new Tab($idTab);
            if (Validate::isLoadedObject($tab)) {
                $tab->delete();
            }
        }
        LvhStaffAudit::cleanupParentOperationsTabIfEmpty();
    }

    /**
     * Log new booking/order creation.
     *
     * @param array $params
     */
    public function hookActionObjectOrderAddAfter($params)
    {
        if (!isset($params['object']) || !($params['object'] instanceof Order)) {
            return;
        }
        $order = $params['object'];
        $summary = sprintf(
            'Created Order #%d (%s) — Total %s via %s',
            (int) $order->id,
            (string) $order->reference,
            number_format((float) $order->total_paid_tax_incl, 2),
            (string) $order->payment
        );
        LvhStaffAudit::record('order.created', 'Order', (int) $order->id, $summary, array(
            'reference' => (string) $order->reference,
            'module' => (string) $order->module,
            'payment' => (string) $order->payment,
            'total_tax_incl' => (float) $order->total_paid_tax_incl,
        ));
    }

    /**
     * Log order status transitions (cancellations, refunds, payment accepted).
     *
     * @param array $params
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : 0;
        $newOs = isset($params['newOrderStatus']) ? $params['newOrderStatus'] : null;
        $stateName = ($newOs && isset($newOs->name))
            ? (is_array($newOs->name) ? reset($newOs->name) : (string) $newOs->name)
            : 'State updated';
        $stateId = ($newOs && isset($newOs->id)) ? (int) $newOs->id : 0;

        $sev = in_array($stateId, array(
            (int) Configuration::get('PS_OS_CANCELED'),
            (int) Configuration::get('PS_OS_REFUND'),
            (int) Configuration::get('PS_OS_ERROR'),
        ), true) ? 'warning' : 'info';

        LvhStaffAudit::record(
            'order.status_changed',
            'Order',
            $idOrder,
            sprintf('Order #%d status changed to "%s"', $idOrder, $stateName),
            array('id_order_state' => $stateId, 'state_name' => $stateName),
            $sev
        );
    }

    /**
     * Log every payment posted to an order.
     *
     * @param array $params
     */
    public function hookActionObjectOrderPaymentAddAfter($params)
    {
        if (!isset($params['object']) || !($params['object'] instanceof OrderPayment)) {
            return;
        }
        $op = $params['object'];
        $method = (string) $op->payment_method;
        $isComp = (stripos($method, 'comp') !== false || stripos($method, 'management') !== false);

        $summary = sprintf(
            'Payment of %s recorded on %s via %s%s',
            number_format((float) $op->amount, 2),
            (string) $op->order_reference,
            $method,
            $op->transaction_id ? ' (Ref: ' . $op->transaction_id . ')' : ''
        );
        LvhStaffAudit::record(
            $isComp ? 'payment.management_comp' : 'payment.recorded',
            'OrderPayment',
            (int) $op->id,
            $summary,
            array(
                'order_reference' => (string) $op->order_reference,
                'amount' => (float) $op->amount,
                'method' => $method,
                'transaction_id' => (string) $op->transaction_id,
            ),
            $isComp ? 'warning' : 'info'
        );
    }

    /**
     * Log room check-in, check-out, reallocation, or cancellation updates.
     *
     * @param array $params
     */
    public function hookActionObjectHotelBookingDetailUpdateAfter($params)
    {
        if (!isset($params['object'])) {
            return;
        }
        $bd = $params['object'];
        $statusMap = array(
            1 => 'Allotted / Reserved',
            2 => 'Checked-In',
            3 => 'Checked-Out',
            4 => 'No-Show',
            5 => 'Cancelled',
        );
        $st = isset($bd->id_status) ? (int) $bd->id_status : 0;
        $label = isset($statusMap[$st]) ? $statusMap[$st] : ('Status #' . $st);
        $roomNum = isset($bd->room_num) ? (string) $bd->room_num : ('Room #' . (int) $bd->id_room);
        $idOrder = isset($bd->id_order) ? (int) $bd->id_order : 0;

        LvhStaffAudit::record(
            'booking.updated',
            'HotelBookingDetail',
            (int) $bd->id,
            sprintf('Room %s (Order #%d) updated -> %s', $roomNum, $idOrder, $label),
            array(
                'id_order' => $idOrder,
                'id_room' => isset($bd->id_room) ? (int) $bd->id_room : 0,
                'room_num' => $roomNum,
                'id_status' => $st,
            ),
            ($st === 4 || $st === 5) ? 'warning' : 'info'
        );
    }

    /**
     * Log employee back-office login events.
     *
     * @param array $params
     */
    public function hookActionAdminLoginControllerLoginAfter($params)
    {
        $empId = isset($this->context->employee) ? (int) $this->context->employee->id : 0;
        LvhStaffAudit::record('staff.login', 'Employee', $empId, 'Back-office session signed in');
    }
}
