<?php
/**
 * Lush View Hotel - Express Front Desk & 34-Room Visual Rack Controller.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/LvhFrontDeskService.php';
if (file_exists(_PS_MODULE_DIR_ . 'lvhpayments/classes/LvhPaymentNormalizer.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhpayments/classes/LvhPaymentNormalizer.php';
}
if (file_exists(_PS_MODULE_DIR_ . 'lvhhousekeeping/classes/LvhHousekeepingStatus.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhhousekeeping/classes/LvhHousekeepingStatus.php';
}
if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class AdminLvhFrontDeskController extends ModuleAdminController
{
    protected $createdOrderBanner = null;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Express Front Desk & 34-Room Rack');
    }

    /**
     * Handle AJAX guest lookup, express walk-in, 1-click check-in/out, and folio extras.
     */
    public function postProcess()
    {
        parent::postProcess();

        if (Tools::getValue('ajaxSearchGuest')) {
            $q = trim((string) Tools::getValue('q', ''));
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            header('Content-Type: application/json; charset=UTF-8');
            die(json_encode(array('guests' => LvhFrontDeskService::searchGuests($q))));
        }

        if (Tools::isSubmit('submitExpressWalkIn')) {
            $this->handleExpressWalkIn();
        }

        if (Tools::isSubmit('submitQuickCheckIn')) {
            $this->handleQuickStatusChange(2);
        }

        if (Tools::isSubmit('submitQuickCheckOut')) {
            $this->handleQuickStatusChange(3);
        }

        if (Tools::isSubmit('submitAddFolioExtra')) {
            $this->handleAddFolioExtra();
        }

        if (Tools::isSubmit('submitQuickMarkClean') && class_exists('LvhHousekeepingStatus')) {
            $idRoom = (int) Tools::getValue('id_room');
            $staffName = $this->getStaffName();
            if (LvhHousekeepingStatus::setRoomStatus($idRoom, 'clean', '', 'Marked Clean from Front Desk Rack', $staffName)) {
                $this->confirmations[] = $this->l('Room marked Clean & Ready.');
            }
        }
    }

    /**
     * Render the 1-screen Front Desk command center.
     */
    public function initContent()
    {
        parent::initContent();

        $businessDate = $this->getBusinessDate();
        $rack = LvhFrontDeskService::getRackSnapshot($businessDate, (int) $this->context->language->id);

        $isSupervisor = class_exists('LvhPaymentNormalizer')
            ? LvhPaymentNormalizer::canAuthorizeSupervisorActions($this->context)
            : ((int) $this->context->employee->id_profile === 1);

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

        $this->context->smarty->assign(array(
            'lvh_business_date' => $businessDate,
            'lvh_kpis' => $rack['kpis'],
            'lvh_floors' => $rack['floors'],
            'lvh_available_rooms' => $rack['available_rooms'],
            'lvh_payment_methods' => $methods,
            'lvh_is_supervisor' => $isSupervisor,
            'lvh_created_banner' => $this->createdOrderBanner,
            'lvh_controller_url' => $this->context->link->getAdminLink('AdminLvhFrontDesk'),
            'lvh_order_base_url' => $this->context->link->getAdminLink('AdminOrders'),
            'lvh_payments_url' => $this->context->link->getAdminLink('AdminLvhPayments'),
            'lvh_hk_url' => $this->context->link->getAdminLink('AdminLvhHousekeeping'),
            'lvh_token' => $this->token,
        ));

        $tpl = _PS_MODULE_DIR_ . 'lvhfrontdesk/views/templates/admin/frontdesk-rack.tpl';
        $this->content .= $this->context->smarty->fetch($tpl);
        $this->context->smarty->assign('content', $this->content);
    }

    /**
     * Validate and execute 30-second walk-in booking.
     */
    protected function handleExpressWalkIn()
    {
        $method = trim((string) Tools::getValue('payment_method', 'Cash (GHS)'));
        $txId = trim((string) Tools::getValue('transaction_id', ''));

        if (class_exists('LvhPaymentNormalizer')) {
            $defs = LvhPaymentNormalizer::getCanonicalMethods(true);
            foreach ($defs as $d) {
                if ($d['label'] === $method) {
                    if ($d['supervisor_only'] && !LvhPaymentNormalizer::canAuthorizeSupervisorActions($this->context)) {
                        $this->errors[] = $this->l('Only a Supervisor or Manager can authorize a Management Comp.');
                        return;
                    }
                    if ($d['requires_ref'] && $txId === '') {
                        $this->errors[] = sprintf(
                            $this->l('Reference / Transaction ID is mandatory for %s.'),
                            $method
                        );
                        return;
                    }
                }
            }
        }

        $payload = array(
            'id_room' => (int) Tools::getValue('id_room'),
            'date_from' => trim((string) Tools::getValue('date_from', date('Y-m-d'))),
            'nights' => (int) Tools::getValue('nights', 1),
            'adults' => (int) Tools::getValue('adults', 1),
            'id_customer' => (int) Tools::getValue('id_customer', 0),
            'phone' => trim((string) Tools::getValue('phone', '')),
            'firstname' => trim((string) Tools::getValue('firstname', '')),
            'lastname' => trim((string) Tools::getValue('lastname', '')),
            'email' => trim((string) Tools::getValue('email', '')),
            'doc_type' => trim((string) Tools::getValue('doc_type', 'Ghana Card')),
            'doc_number' => trim((string) Tools::getValue('doc_number', '')),
            'nationality' => trim((string) Tools::getValue('nationality', 'Ghanaian')),
            'payment_method' => $method,
            'amount_paid' => (float) Tools::getValue('amount_paid', 0),
            'transaction_id' => $txId,
            'instant_checkin' => (int) Tools::getValue('instant_checkin', 0),
            'comment' => trim((string) Tools::getValue('comment', '')),
        );

        $res = LvhFrontDeskService::createExpressWalkIn($payload, $this->context);
        if (!$res['ok']) {
            $this->errors[] = $res['error'];
            return;
        }

        $this->createdOrderBanner = $res;
        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record(
                'frontdesk.express_walkin',
                'Order',
                (int) $res['id_order'],
                sprintf('Express Walk-In Order #%d (%s) created for Room #%d via %s', (int) $res['id_order'], $res['reference'], $payload['id_room'], $method),
                array('id_room' => $payload['id_room'], 'nights' => $payload['nights'], 'method' => $method)
            );
        }
        $this->confirmations[] = sprintf(
            $this->l('Booking %s (#%d) created successfully!'),
            $res['reference'],
            (int) $res['id_order']
        );
    }

    /**
     * Execute 1-click Check-In (`2`) or Check-Out (`3`) on a `HotelBookingDetail` row.
     *
     * @param int $targetStatus
     */
    protected function handleQuickStatusChange($targetStatus)
    {
        if (!file_exists(_PS_MODULE_DIR_ . 'hotelreservationsystem/define.php')) {
            return;
        }
        require_once _PS_MODULE_DIR_ . 'hotelreservationsystem/define.php';

        $idBd = (int) Tools::getValue('id_booking_detail');
        $bd = new HotelBookingDetail($idBd);
        if (!Validate::isLoadedObject($bd)) {
            $this->errors[] = $this->l('Booking detail record not found.');
            return;
        }

        // Guard against accidental unpaid walk-outs unless Supervisor explicitly overrides
        if ($targetStatus === 3) {
            $balanceDue = (float) Tools::getValue('balance_due', 0);
            $forceCheckout = (int) Tools::getValue('force_unpaid_checkout', 0);
            $isSup = class_exists('LvhPaymentNormalizer')
                ? LvhPaymentNormalizer::canAuthorizeSupervisorActions($this->context)
                : ((int) $this->context->employee->id_profile === 1);

            if ($balanceDue > 0.01 && !($forceCheckout && $isSup)) {
                $this->errors[] = sprintf(
                    $this->l('Cannot check out with unpaid balance of GHS %.2f. Please collect payment on the Order first (or have a Supervisor authorize Corporate/Open-Balance Check-Out).'),
                    $balanceDue
                );
                return;
            }
        }

        $bd->id_status = (int) $targetStatus;
        if ($targetStatus === 2) {
            $bd->check_in = date('Y-m-d H:i:s');
        } elseif ($targetStatus === 3) {
            $bd->check_out = date('Y-m-d H:i:s');
        }

        if ($bd->update()) {
            $this->confirmations[] = ($targetStatus === 2)
                ? $this->l('Guest checked in successfully.')
                : $this->l('Guest checked out and room marked Dirty for housekeeping.');
        } else {
            $this->errors[] = $this->l('Could not update room booking status.');
        }
    }

    /**
     * Post an extra charge (Restaurant / Bar / Laundry / Late Check-Out) onto a guest folio.
     */
    protected function handleAddFolioExtra()
    {
        $idOrder = (int) Tools::getValue('id_order');
        $idRoom = (int) Tools::getValue('id_room', 0);
        $cat = trim((string) Tools::getValue('category', 'Restaurant / Bar'));
        $desc = trim((string) Tools::getValue('description', ''));
        $amt = (float) Tools::getValue('amount', 0);

        if ($idOrder <= 0 || $amt <= 0) {
            $this->errors[] = $this->l('Please select a valid order and positive charge amount.');
            return;
        }

        $staffName = $this->getStaffName();
        Db::getInstance()->insert('lvh_folio_charge', array(
            'id_order' => $idOrder,
            'id_room' => $idRoom,
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
                $idOrder,
                sprintf('Added %s charge of GHS %.2f (%s) to Order #%d', $cat, $amt, $desc, $idOrder),
                array('category' => $cat, 'amount' => $amt, 'description' => $desc)
            );
        }

        $this->confirmations[] = $this->l('Folio extra charge added to guest room bill.');
    }

    /**
     * Validated rack date (`YYYY-MM-DD`).
     *
     * @return string
     */
    protected function getBusinessDate()
    {
        $d = trim((string) Tools::getValue('business_date', date('Y-m-d')));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : date('Y-m-d');
    }

    /**
     * Current employee full name.
     *
     * @return string
     */
    protected function getStaffName()
    {
        if (isset($this->context->employee) && Validate::isLoadedObject($this->context->employee)) {
            return trim($this->context->employee->firstname . ' ' . $this->context->employee->lastname);
        }
        return 'Front Desk';
    }
}
