<?php
/**
 * Lush View Hotel - Shift Cash-Up, Payment Verification & Printable Receipt Controller.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhPaymentNormalizer.php';
if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class AdminLvhPaymentsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Shift Cash-Up & Payments');
    }

    /**
     * Handle printable receipts, shift cash-up submissions, and supervisor verifications.
     */
    public function postProcess()
    {
        parent::postProcess();

        if (Tools::getValue('printReceipt')) {
            $idOrder = (int) Tools::getValue('id_order');
            $format = Tools::getValue('format') === 'a4' ? 'a4' : 'thermal';
            $this->renderStandaloneReceipt($idOrder, $format);
        }

        $shiftDate = $this->getShiftDate();

        if (Tools::isSubmit('submitShiftCashup')) {
            $this->handleSubmitCashup($shiftDate);
        }

        if (Tools::getValue('approveCashup') && LvhPaymentNormalizer::canAuthorizeSupervisorActions($this->context)) {
            $this->handleApproveCashup();
        }

        if (Tools::getValue('verifyPaymentAction') && LvhPaymentNormalizer::canAuthorizeSupervisorActions($this->context)) {
            $this->handleVerifyPayment();
        }
    }

    /**
     * Render Shift Cash-Up and Payment Verification console.
     */
    public function initContent()
    {
        parent::initContent();

        $shiftDate = $this->getShiftDate();
        $dayData = $this->loadDayPayments($shiftDate);

        $cashups = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'lvh_shift_cashup`
             ORDER BY `shift_date` DESC, `id_cashup` DESC
             LIMIT 30'
        );

        $this->context->smarty->assign(array(
            'lvh_shift_date' => $shiftDate,
            'lvh_summary' => $dayData['summary'],
            'lvh_day_payments' => $dayData['rows'],
            'lvh_cashups' => is_array($cashups) ? $cashups : array(),
            'lvh_is_supervisor' => LvhPaymentNormalizer::canAuthorizeSupervisorActions($this->context),
            'lvh_controller_url' => $this->context->link->getAdminLink('AdminLvhPayments'),
            'lvh_order_base_url' => $this->context->link->getAdminLink('AdminOrders'),
            'lvh_token' => $this->token,
        ));

        $tpl = _PS_MODULE_DIR_ . 'lvhpayments/views/templates/admin/cashup-and-verify.tpl';
        $this->content .= $this->context->smarty->fetch($tpl);
        $this->context->smarty->assign('content', $this->content);
    }

    /**
     * Validated shift date (`YYYY-MM-DD`).
     *
     * @return string
     */
    protected function getShiftDate()
    {
        $d = trim((string) Tools::getValue('shift_date', date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return date('Y-m-d');
        }
        return $d;
    }

    /**
     * Load all `order_payment` rows for `$shiftDate`, classify tenders, and join metadata.
     *
     * @param string $shiftDate
     * @return array{summary:array,rows:array}
     */
    protected function loadDayPayments($shiftDate)
    {
        $p = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS(
            "SELECT op.`id_order_payment`, op.`order_reference`, op.`amount`,
                    op.`payment_method`, op.`transaction_id`, op.`date_add`,
                    (SELECT o.`id_order` FROM `{$p}orders` o WHERE o.`reference` = op.`order_reference` ORDER BY o.`id_order` ASC LIMIT 1) AS `id_order`,
                    pm.`recorded_by_name`, pm.`verified_status`, pm.`verified_by_name`
             FROM `{$p}order_payment` op
             LEFT JOIN `{$p}lvh_payment_meta` pm ON (pm.`id_order_payment` = op.`id_order_payment`)
             WHERE op.`date_add` >= '" . pSQL($shiftDate) . " 00:00:00'
               AND op.`date_add` <= '" . pSQL($shiftDate) . " 23:59:59'
             ORDER BY op.`date_add` DESC, op.`id_order_payment` DESC"
        );

        $summary = array(
            'cash' => 0.0,
            'momo' => 0.0,
            'pos' => 0.0,
            'bank' => 0.0,
            'comp' => 0.0,
            'total' => 0.0,
        );

        $enriched = array();
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $cls = LvhPaymentNormalizer::classify($r['payment_method']);
                $amt = (float) $r['amount'];
                $grp = $cls['group'];
                if (isset($summary[$grp])) {
                    $summary[$grp] += $amt;
                } elseif ($grp === 'unclassified') {
                    // Legacy BO entries default into cash audit alert only in reports; keep separate from expected cash drawer unless explicitly Cash
                }
                if ($grp !== 'comp') {
                    $summary['total'] += $amt;
                }

                $r['canonical_code'] = $cls['code'];
                $r['canonical_label'] = $cls['label'];
                $r['group'] = $grp;
                $r['recorded_by_name'] = !empty($r['recorded_by_name']) ? $r['recorded_by_name'] : 'System / Legacy';
                $r['verified_status'] = !empty($r['verified_status']) ? $r['verified_status'] : 'pending';
                $r['verified_by_name'] = !empty($r['verified_by_name']) ? $r['verified_by_name'] : '';
                $enriched[] = $r;
            }
        }

        return array('summary' => $summary, 'rows' => $enriched);
    }

    /**
     * Save receptionist shift cash-up and compute variance.
     *
     * @param string $shiftDate
     */
    protected function handleSubmitCashup($shiftDate)
    {
        $dayData = $this->loadDayPayments($shiftDate);
        $expectedCash = (float) $dayData['summary']['cash'];
        $countedCash = (float) Tools::getValue('counted_cash', 0);
        $variance = round($countedCash - $expectedCash, 2);
        $shiftLabel = trim((string) Tools::getValue('shift_label', 'Full Day Handover'));
        $notes = trim((string) Tools::getValue('handover_notes', ''));

        $empId = isset($this->context->employee) ? (int) $this->context->employee->id : 0;
        $empName = isset($this->context->employee)
            ? trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname)
            : 'Staff';

        Db::getInstance()->insert('lvh_shift_cashup', array(
            'shift_date' => pSQL($shiftDate),
            'shift_label' => pSQL(Tools::substr($shiftLabel, 0, 32)),
            'id_employee' => $empId,
            'employee_name' => pSQL(Tools::substr($empName, 0, 128)),
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'variance_cash' => $variance,
            'expected_momo' => (float) $dayData['summary']['momo'],
            'expected_pos' => (float) $dayData['summary']['pos'],
            'expected_bank' => (float) $dayData['summary']['bank'],
            'handover_notes' => pSQL($notes),
            'supervisor_status' => 'submitted',
            'approved_by_name' => '',
            'date_add' => date('Y-m-d H:i:s'),
        ));

        if (class_exists('LvhStaffAudit')) {
            $sev = abs($variance) > 0.01 ? 'warning' : 'info';
            LvhStaffAudit::record(
                'shift.cashup_submitted',
                'ShiftCashup',
                (int) Db::getInstance()->Insert_ID(),
                sprintf(
                    'Shift Cash-Up (%s, %s): Expected GHS %.2f, Counted GHS %.2f, Variance GHS %+.2f',
                    $shiftDate,
                    $shiftLabel,
                    $expectedCash,
                    $countedCash,
                    $variance
                ),
                array('shift_date' => $shiftDate, 'variance' => $variance),
                $sev
            );
        }

        $this->confirmations[] = $this->l('Shift cash-up handover recorded.');
    }

    /**
     * Supervisor/Accountant approval or discrepancy flagging on a shift handover.
     */
    protected function handleApproveCashup()
    {
        $idCashup = (int) Tools::getValue('id_cashup');
        $status = Tools::getValue('approveCashup') === 'discrepancy' ? 'discrepancy' : 'approved';
        if ($idCashup <= 0) {
            return;
        }
        $supName = trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname);
        Db::getInstance()->update(
            'lvh_shift_cashup',
            array(
                'supervisor_status' => pSQL($status),
                'approved_by_name' => pSQL(Tools::substr($supName, 0, 128)),
            ),
            '`id_cashup` = ' . $idCashup
        );

        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record(
                'shift.cashup_' . $status,
                'ShiftCashup',
                $idCashup,
                sprintf('Shift Cash-Up #%d marked "%s" by %s', $idCashup, $status, $supName),
                array('status' => $status),
                $status === 'discrepancy' ? 'warning' : 'info'
            );
        }
        $this->confirmations[] = $this->l('Shift handover status updated.');
    }

    /**
     * Supervisor/Accountant verification of MoMo / POS / Bank Transfer references.
     */
    protected function handleVerifyPayment()
    {
        $idOp = (int) Tools::getValue('id_order_payment');
        $idOrder = (int) Tools::getValue('id_order');
        $code = trim((string) Tools::getValue('canonical_code', 'CASH'));
        $status = Tools::getValue('verifyPaymentAction') === 'flagged' ? 'flagged' : 'verified';
        if ($idOp <= 0) {
            return;
        }

        $supId = (int) $this->context->employee->id;
        $supName = trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname);
        $now = date('Y-m-d H:i:s');

        $existing = (int) Db::getInstance()->getValue(
            'SELECT `id_payment_meta` FROM `' . _DB_PREFIX_ . 'lvh_payment_meta` WHERE `id_order_payment` = ' . $idOp
        );

        if ($existing > 0) {
            Db::getInstance()->update(
                'lvh_payment_meta',
                array(
                    'verified_status' => pSQL($status),
                    'verified_by_employee' => $supId,
                    'verified_by_name' => pSQL(Tools::substr($supName, 0, 128)),
                    'verified_at' => pSQL($now),
                ),
                '`id_payment_meta` = ' . $existing
            );
        } else {
            Db::getInstance()->insert('lvh_payment_meta', array(
                'id_order_payment' => $idOp,
                'id_order' => $idOrder,
                'canonical_code' => pSQL(Tools::substr($code, 0, 32)),
                'payer_phone' => '',
                'recorded_by_employee' => 0,
                'recorded_by_name' => 'Legacy',
                'verified_status' => pSQL($status),
                'verified_by_employee' => $supId,
                'verified_by_name' => pSQL(Tools::substr($supName, 0, 128)),
                'verified_at' => pSQL($now),
                'notes' => '',
                'date_add' => pSQL($now),
            ));
        }

        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record(
                'payment.' . $status,
                'OrderPayment',
                $idOp,
                sprintf('OrderPayment #%d marked "%s" by %s', $idOp, $status, $supName),
                array('id_order' => $idOrder, 'status' => $status),
                $status === 'flagged' ? 'warning' : 'info'
            );
        }

        $this->confirmations[] = $this->l('Payment verification status saved.');
    }

    /**
     * Render standalone 80mm thermal or A4 guest receipt HTML and exit cleanly.
     *
     * @param int $idOrder
     * @param string $format
     */
    protected function renderStandaloneReceipt($idOrder, $format)
    {
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            die('Order not found.');
        }

        $p = _DB_PREFIX_;
        $idLang = (int) $this->context->language->id;
        $customer = new Customer((int) $order->id_customer);
        $guestName = Validate::isLoadedObject($customer)
            ? trim($customer->firstname . ' ' . $customer->lastname)
            : 'Walk-In Guest';

        $guestPhone = (string) Db::getInstance()->getValue(
            'SELECT COALESCE(NULLIF(`phone_mobile`, \'\'), `phone`)
             FROM `' . $p . 'address`
             WHERE `id_customer` = ' . (int) $order->id_customer . ' AND `deleted` = 0
             ORDER BY `id_address` DESC'
        );

        $bookings = Db::getInstance()->executeS(
            "SELECT hbd.`id_room`, hbd.`date_from`, hbd.`date_to`, hbd.`total_price_tax_incl`,
                    COALESCE(hr.`room_num`, CONCAT('Room #', hbd.`id_room`)) AS `room_num`,
                    COALESCE(pl.`name`, 'Room Stay') AS `room_type`
             FROM `{$p}htl_booking_detail` hbd
             LEFT JOIN `{$p}htl_room_information` hr ON (hr.`id` = hbd.`id_room`)
             LEFT JOIN `{$p}product_lang` pl ON (pl.`id_product` = hbd.`id_product` AND pl.`id_lang` = {$idLang})
             WHERE hbd.`id_order` = " . (int) $order->id . "
             ORDER BY hbd.`id` ASC"
        );

        $extras = array();
        $extrasTotal = 0.0;
        $hasFolioTable = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'lvh_folio_charge') . "'", true, false);
        if (!empty($hasFolioTable)) {
            $exRows = Db::getInstance()->executeS(
                'SELECT * FROM `' . $p . 'lvh_folio_charge`
                 WHERE `id_order` = ' . (int) $order->id . ' AND `voided` = 0
                 ORDER BY `id_folio_charge` ASC'
            );
            if (is_array($exRows)) {
                foreach ($exRows as $er) {
                    $extrasTotal += (float) $er['amount'];
                    $extras[] = $er;
                }
            }
        }

        $payRows = Db::getInstance()->executeS(
            "SELECT `date_add`, `payment_method`, `transaction_id`, `amount`
             FROM `{$p}order_payment`
             WHERE `order_reference` = '" . pSQL($order->reference) . "'
             ORDER BY `date_add` ASC"
        );
        $payments = array();
        $paidTotal = 0.0;
        if (is_array($payRows)) {
            foreach ($payRows as $pr) {
                $cls = LvhPaymentNormalizer::classify($pr['payment_method']);
                $pr['canonical_label'] = $cls['label'];
                $paidTotal += (float) $pr['amount'];
                $payments[] = $pr;
            }
        }

        $grandTotal = (float) $order->total_paid_tax_incl + $extrasTotal;
        $balanceDue = max(0.0, round($grandTotal - $paidTotal, 2));

        $hotelAddrRow = Db::getInstance()->getRow(
            'SELECT `address1`, `city`, COALESCE(NULLIF(`phone`, \'\'), `phone_mobile`) AS `phone`
             FROM `' . $p . 'address`
             WHERE `id_hotel` > 0 AND `deleted` = 0
             ORDER BY `id_hotel` ASC, `id_address` DESC'
        );
        $addrLine = !empty($hotelAddrRow['address1'])
            ? (string) $hotelAddrRow['address1']
            : (string) Configuration::get('PS_SHOP_ADDR1');
        $cityLine = !empty($hotelAddrRow['city'])
            ? (string) $hotelAddrRow['city']
            : (string) Configuration::get('PS_SHOP_CITY');
        $hotelAddress = trim($addrLine) !== ''
            ? trim($addrLine . (trim($cityLine) !== '' ? ', ' . trim($cityLine) : ''))
            : 'Accra, Ghana';
        $hotelPhone = !empty($hotelAddrRow['phone'])
            ? (string) $hotelAddrRow['phone']
            : (string) Configuration::get('PS_SHOP_PHONE');

        $staffName = isset($this->context->employee)
            ? trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname)
            : 'Front Desk';

        $altFormat = ($format === 'thermal') ? 'a4' : 'thermal';
        $switchUrl = $this->context->link->getAdminLink('AdminLvhPayments')
            . '&printReceipt=1&id_order=' . (int) $order->id . '&format=' . $altFormat;

        $this->context->smarty->assign(array(
            'lvh_format' => $format,
            'lvh_switch_url' => $switchUrl,
            'lvh_hotel_address' => $hotelAddress,
            'lvh_hotel_phone' => $hotelPhone,
            'lvh_order' => array(
                'id_order' => (int) $order->id,
                'reference' => (string) $order->reference,
            ),
            'lvh_printed_at' => date('d M Y H:i'),
            'lvh_guest_name' => $guestName,
            'lvh_guest_phone' => $guestPhone,
            'lvh_bookings' => is_array($bookings) ? $bookings : array(),
            'lvh_extras' => $extras,
            'lvh_payments' => $payments,
            'lvh_grand_total' => $grandTotal,
            'lvh_paid_total' => $paidTotal,
            'lvh_balance_due' => $balanceDue,
            'lvh_staff_name' => $staffName,
        ));

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: text/html; charset=UTF-8');
        echo $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'lvhpayments/views/templates/admin/receipt-print.tpl');
        exit;
    }
}
