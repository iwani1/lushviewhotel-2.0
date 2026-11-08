<?php
/**
 * Lush View Hotel - Shared Conference Hall Scheduler Admin Controller.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhConferenceManager.php';
if (file_exists(_PS_MODULE_DIR_ . 'lvhpayments/classes/LvhPaymentNormalizer.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhpayments/classes/LvhPaymentNormalizer.php';
}
if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class AdminLvhConferenceController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Conference Hall Schedule');
    }

    /**
     * Handle new conference booking, deposit settlement, cancellation, BEO print,
     * and Supervisor toggle to hide legacy `CON-*` room types from website bedroom search.
     */
    public function postProcess()
    {
        parent::postProcess();

        if (Tools::getValue('printBeo')) {
            $this->renderPrintableBeo((int) Tools::getValue('id_conf_booking'));
        }

        if (Tools::isSubmit('submitCreateConfBooking')) {
            $this->handleCreateBooking();
        }

        if (Tools::isSubmit('submitRecordConfPayment')) {
            $this->handleRecordPayment();
        }

        if (Tools::getValue('updateConfStatus')) {
            $this->handleUpdateStatus();
        }

        if (Tools::isSubmit('submitHideLegacyConRooms') && $this->isSupervisor()) {
            $this->hideLegacyConRoomProducts();
        }
    }

    /**
     * Render the shared Conference Hall schedule and hourly booking form.
     */
    public function initContent()
    {
        parent::initContent();

        LvhConferenceManager::syncLegacyConferenceOrders();
        $packages = LvhConferenceManager::getPackages();
        $isSupervisor = $this->isSupervisor();

        $methods = class_exists('LvhPaymentNormalizer')
            ? LvhPaymentNormalizer::getCanonicalMethods($isSupervisor)
            : array(
                'CASH' => array('label' => 'Cash (GHS)', 'requires_ref' => false),
                'MTN_MOMO' => array('label' => 'MTN MoMo', 'requires_ref' => true),
                'TELECEL_CASH' => array('label' => 'Telecel Cash', 'requires_ref' => true),
                'AT_MONEY' => array('label' => 'AT Money', 'requires_ref' => true),
                'POS_CARD' => array('label' => 'POS / Card', 'requires_ref' => true),
                'BANK_TRANSFER' => array('label' => 'Bank Transfer', 'requires_ref' => true),
                'PAY_LATER' => array('label' => 'Corporate / Pay Later', 'requires_ref' => false),
            );

        $fromDate = trim((string) Tools::getValue('from_date', date('Y-m-d', strtotime('-7 days'))));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $fromDate = date('Y-m-d');
        }

        $bookings = Db::getInstance()->executeS(
            "SELECT * FROM `" . _DB_PREFIX_ . "lvh_conference_booking`
             WHERE `start_datetime` >= '" . pSQL($fromDate) . " 00:00:00'
             ORDER BY `start_datetime` ASC
             LIMIT 200"
        );
        if (!is_array($bookings)) {
            $bookings = array();
        }
        foreach ($bookings as &$b) {
            $b['balance_due'] = max(0.0, round((float) $b['total_amount'] - (float) $b['paid_amount'], 2));
        }

        $activeConProducts = (int) Db::getInstance()->getValue(
            "SELECT COUNT(DISTINCT hr.`id_product`)
             FROM `" . _DB_PREFIX_ . "htl_room_information` hr
             INNER JOIN `" . _DB_PREFIX_ . "product` p ON (p.`id_product` = hr.`id_product`)
             WHERE hr.`room_num` LIKE 'CON-%' AND p.`active` = 1"
        );

        $this->context->smarty->assign(array(
            'lvh_packages' => $packages,
            'lvh_payment_methods' => $methods,
            'lvh_bookings' => $bookings,
            'lvh_from_date' => $fromDate,
            'lvh_today' => date('Y-m-d'),
            'lvh_is_supervisor' => $isSupervisor,
            'lvh_active_con_products' => $activeConProducts,
            'lvh_controller_url' => $this->context->link->getAdminLink('AdminLvhConference'),
            'lvh_token' => $this->token,
        ));

        $tpl = _PS_MODULE_DIR_ . 'lvhconference/views/templates/admin/conference-calendar.tpl';
        $this->content .= $this->context->smarty->fetch($tpl);
        $this->context->smarty->assign('content', $this->content);
    }

    /**
     * Validate overlap-free slot and save new Conference Hall reservation.
     */
    protected function handleCreateBooking()
    {
        $packages = LvhConferenceManager::getPackages();
        $pkgCode = trim((string) Tools::getValue('package_code', '1_HOUR'));
        if (!isset($packages[$pkgCode])) {
            $pkgCode = '1_HOUR';
        }

        $eventDate = trim((string) Tools::getValue('event_date', date('Y-m-d')));
        $startTime = trim((string) Tools::getValue('start_time', '09:00'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate) || !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            $this->errors[] = $this->l('Please provide a valid Event Date and Start Time.');
            return;
        }

        $durationMins = ($pkgCode === 'CUSTOM')
            ? max(30, (int) Tools::getValue('custom_minutes', 120))
            : (int) $packages[$pkgCode]['minutes'];
        $bufferMins = max(0, min(180, (int) Tools::getValue('buffer_minutes', 30)));

        $startTs = strtotime($eventDate . ' ' . $startTime . ':00');
        $endTs = $startTs + ($durationMins * 60);
        $effEndTs = $endTs + ($bufferMins * 60);

        $startDt = date('Y-m-d H:i:s', $startTs);
        $endDt = date('Y-m-d H:i:s', $endTs);
        $effEndDt = date('Y-m-d H:i:s', $effEndTs);

        // Enforce single shared physical Hall overlap check!
        $conflict = LvhConferenceManager::findConflict($startDt, $effEndDt);
        if ($conflict) {
            $this->errors[] = sprintf(
                $this->l('CONFERENCE HALL CONFLICT: "%s" (%s) already occupies the Hall from %s to %s (incl. turnaround buffer).'),
                $conflict['event_title'],
                $conflict['booking_ref'],
                $conflict['start_datetime'],
                $conflict['effective_end_datetime']
            );
            return;
        }

        $totalAmt = ($pkgCode === 'CUSTOM')
            ? max(0.0, (float) Tools::getValue('total_amount', 0))
            : (float) $packages[$pkgCode]['price'];
        $paidAmt = max(0.0, min($totalAmt, (float) Tools::getValue('paid_amount', 0)));
        $method = trim((string) Tools::getValue('payment_method', 'Cash (GHS)'));
        $txId = trim((string) Tools::getValue('transaction_id', ''));

        if ($paidAmt > 0 && class_exists('LvhPaymentNormalizer')) {
            foreach (LvhPaymentNormalizer::getCanonicalMethods(true) as $m) {
                if ($m['label'] === $method && $m['requires_ref'] && $txId === '') {
                    $this->errors[] = sprintf($this->l('Reference / Transaction ID is required for %s.'), $method);
                    return;
                }
            }
        }

        $title = trim((string) Tools::getValue('event_title', ''));
        $org = trim((string) Tools::getValue('organization', ''));
        $contact = trim((string) Tools::getValue('contact_name', ''));
        if ($title === '' || $contact === '') {
            $this->errors[] = $this->l('Event Title and Contact Name are required.');
            return;
        }

        $ref = 'CONF-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $staffName = $this->getStaffName();
        $status = trim((string) Tools::getValue('status', 'confirmed')) === 'tentative' ? 'tentative' : 'confirmed';

        Db::getInstance()->insert('lvh_conference_booking', array(
            'booking_ref' => pSQL($ref),
            'event_title' => pSQL(Tools::substr($title, 0, 160)),
            'organization' => pSQL(Tools::substr($org, 0, 160)),
            'contact_name' => pSQL(Tools::substr($contact, 0, 128)),
            'contact_phone' => pSQL(Tools::substr(trim((string) Tools::getValue('contact_phone', '')), 0, 32)),
            'contact_email' => pSQL(Tools::substr(trim((string) Tools::getValue('contact_email', '')), 0, 128)),
            'package_code' => pSQL($pkgCode),
            'start_datetime' => pSQL($startDt),
            'end_datetime' => pSQL($endDt),
            'buffer_minutes' => $bufferMins,
            'effective_end_datetime' => pSQL($effEndDt),
            'attendees' => max(1, (int) Tools::getValue('attendees', 20)),
            'seating_layout' => pSQL(Tools::substr(trim((string) Tools::getValue('seating_layout', 'Boardroom')), 0, 32)),
            'equipment_catering' => pSQL(trim((string) Tools::getValue('equipment_catering', ''))),
            'total_amount' => $totalAmt,
            'paid_amount' => $paidAmt,
            'payment_method' => pSQL(Tools::substr($method, 0, 64)),
            'transaction_id' => pSQL(Tools::substr($txId, 0, 128)),
            'status' => pSQL($status),
            'legacy_booking_detail_id' => 0,
            'created_by_name' => pSQL(Tools::substr($staffName, 0, 128)),
            'date_add' => date('Y-m-d H:i:s'),
        ));

        $newId = (int) Db::getInstance()->Insert_ID();
        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record(
                'conference.booked',
                'ConferenceBooking',
                $newId,
                sprintf('Conference Hall booked (%s): "%s" on %s (%s, GHS %.2f)', $ref, $title, $startDt, $pkgCode, $totalAmt)
            );
        }

        $this->confirmations[] = sprintf($this->l('Conference Hall booking %s confirmed!'), $ref);
    }

    /**
     * Record additional deposit or balance payment on an existing Conference booking.
     */
    protected function handleRecordPayment()
    {
        $idConf = (int) Tools::getValue('id_conf_booking');
        $addAmt = (float) Tools::getValue('add_payment_amount', 0);
        $method = trim((string) Tools::getValue('add_payment_method', 'Cash (GHS)'));
        $txId = trim((string) Tools::getValue('add_tx_id', ''));

        if ($idConf <= 0 || $addAmt <= 0) {
            return;
        }
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'lvh_conference_booking` WHERE `id_conf_booking` = ' . $idConf
        );
        if (!$row) {
            return;
        }

        $newPaid = min((float) $row['total_amount'], (float) $row['paid_amount'] + $addAmt);
        Db::getInstance()->update(
            'lvh_conference_booking',
            array(
                'paid_amount' => $newPaid,
                'payment_method' => pSQL(Tools::substr($method, 0, 64)),
                'transaction_id' => pSQL(Tools::substr($txId ?: (string) $row['transaction_id'], 0, 128)),
            ),
            '`id_conf_booking` = ' . $idConf
        );

        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record(
                'conference.payment_added',
                'ConferenceBooking',
                $idConf,
                sprintf('Recorded GHS %.2f via %s on Conference %s', $addAmt, $method, $row['booking_ref'])
            );
        }
        $this->confirmations[] = $this->l('Conference payment updated.');
    }

    /**
     * Transition status (`confirmed`, `completed`, `cancelled`).
     */
    protected function handleUpdateStatus()
    {
        $idConf = (int) Tools::getValue('id_conf_booking');
        $newStatus = trim((string) Tools::getValue('updateConfStatus'));
        if ($idConf <= 0 || !in_array($newStatus, array('confirmed', 'completed', 'cancelled'), true)) {
            return;
        }
        if ($newStatus === 'cancelled' && !$this->isSupervisor()) {
            $this->errors[] = $this->l('Only a Supervisor or Manager can cancel a Conference Hall booking.');
            return;
        }
        Db::getInstance()->update(
            'lvh_conference_booking',
            array('status' => pSQL($newStatus)),
            '`id_conf_booking` = ' . $idConf
        );
        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record(
                'conference.status_' . $newStatus,
                'ConferenceBooking',
                $idConf,
                sprintf('Conference booking #%d marked "%s"', $idConf, $newStatus),
                array(),
                $newStatus === 'cancelled' ? 'warning' : 'info'
            );
        }
        $this->confirmations[] = $this->l('Conference booking status updated.');
    }

    /**
     * Unpublish legacy `CON-1..CON-4` fake room products from online guest room search
     * (`product.active = 0`, `product_shop.active = 0`) so website visitors only book bedrooms.
     */
    protected function hideLegacyConRoomProducts()
    {
        $p = _DB_PREFIX_;
        $prodIds = Db::getInstance()->executeS(
            "SELECT DISTINCT `id_product` FROM `{$p}htl_room_information` WHERE `room_num` LIKE 'CON-%'"
        );
        if (is_array($prodIds)) {
            foreach ($prodIds as $pr) {
                $idP = (int) $pr['id_product'];
                Db::getInstance()->update('product', array('active' => 0), '`id_product` = ' . $idP);
                Db::getInstance()->update('product_shop', array('active' => 0), '`id_product` = ' . $idP);
            }
        }
        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record(
                'conference.legacy_con_hidden',
                'Product',
                0,
                'Unpublished legacy CON-1..CON-4 fake room products from website bedroom search; unified Conference Hall active',
                array(),
                'warning'
            );
        }
        $this->confirmations[] = $this->l('Legacy CON-1..CON-4 bedroom products hidden from online search; all Conference Hall bookings now run on the unified hourly schedule.');
    }

    /**
     * Render printable A4 Banquet Event Order (BEO) & Conference Receipt.
     *
     * @param int $idConf
     */
    protected function renderPrintableBeo($idConf)
    {
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'lvh_conference_booking` WHERE `id_conf_booking` = ' . (int) $idConf
        );
        if (!$row) {
            die('Conference booking not found.');
        }
        $row['balance_due'] = max(0.0, round((float) $row['total_amount'] - (float) $row['paid_amount'], 2));
        $packages = LvhConferenceManager::getPackages();
        $row['package_label'] = isset($packages[$row['package_code']])
            ? $packages[$row['package_code']]['label']
            : $row['package_code'];

        $this->context->smarty->assign(array(
            'lvh_conf' => $row,
            'lvh_printed_at' => date('d M Y H:i'),
        ));

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: text/html; charset=UTF-8');
        echo $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'lvhconference/views/templates/admin/conference-beo-print.tpl');
        exit;
    }

    protected function isSupervisor()
    {
        return class_exists('LvhPaymentNormalizer')
            ? LvhPaymentNormalizer::canAuthorizeSupervisorActions($this->context)
            : ((int) $this->context->employee->id_profile === 1);
    }

    protected function getStaffName()
    {
        if (isset($this->context->employee) && Validate::isLoadedObject($this->context->employee)) {
            return trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname);
        }
        return 'Front Desk';
    }
}
