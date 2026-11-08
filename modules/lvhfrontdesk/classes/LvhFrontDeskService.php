<?php
/**
 * Lush View Hotel - Front Desk Rack, Express Walk-In, Guest ID & Folio Service.
 *
 * Powers the 1-screen 34-room visual rack, 30-second walk-in booking via QloApps'
 * native `Cart` + `HotelCartBookingData` + `BoOrder::validateOrder` pipeline,
 * Ghana Card / Passport guest registry, repeat-guest VIP recognition, and
 * room folio extras (Restaurant, Laundry, Late Check-Out, etc.).
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhFrontDeskService
{
    /**
     * Create `qlo_lvh_guest_identification` and `qlo_lvh_folio_charge` tables.
     *
     * @return bool
     */
    public static function createTables()
    {
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;

        $sql1 = "CREATE TABLE IF NOT EXISTS `{$p}lvh_guest_identification` (
            `id_guest_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_customer` INT(11) UNSIGNED NOT NULL,
            `id_order` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `doc_type` VARCHAR(32) NOT NULL DEFAULT 'Ghana Card',
            `doc_number` VARCHAR(64) NOT NULL DEFAULT '',
            `nationality` VARCHAR(64) NOT NULL DEFAULT 'Ghanaian',
            `recorded_by_name` VARCHAR(128) NOT NULL DEFAULT '',
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_guest_id`),
            KEY `idx_lvh_gid_cust` (`id_customer`),
            KEY `idx_lvh_gid_ord` (`id_order`),
            KEY `idx_lvh_gid_doc` (`doc_number`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql2 = "CREATE TABLE IF NOT EXISTS `{$p}lvh_folio_charge` (
            `id_folio_charge` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order` INT(11) UNSIGNED NOT NULL,
            `id_room` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `category` VARCHAR(64) NOT NULL DEFAULT 'Restaurant / Bar',
            `description` VARCHAR(255) NOT NULL DEFAULT '',
            `amount` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `recorded_by_name` VARCHAR(128) NOT NULL DEFAULT '',
            `voided` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `voided_by_name` VARCHAR(128) NOT NULL DEFAULT '',
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_folio_charge`),
            KEY `idx_lvh_fc_order` (`id_order`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        return Db::getInstance()->execute($sql1) && Db::getInstance()->execute($sql2);
    }

    /**
     * Search existing guests by phone, name, email, or ID document number,
     * returning stay count, VIP tier, and most recent Ghana Card / Passport on file.
     *
     * @param string $query
     * @return array
     */
    public static function searchGuests($query)
    {
        $q = trim((string) $query);
        if (Tools::strlen($q) < 2) {
            return array();
        }
        $p = _DB_PREFIX_;
        $like = '%' . pSQL($q) . '%';

        $sql = "SELECT c.`id_customer`, c.`firstname`, c.`lastname`, c.`email`,
                       COALESCE(
                           (SELECT NULLIF(a.`phone_mobile`, '') FROM `{$p}address` a WHERE a.`id_customer` = c.`id_customer` AND a.`deleted` = 0 ORDER BY a.`id_address` DESC LIMIT 1),
                           (SELECT a2.`phone` FROM `{$p}address` a2 WHERE a2.`id_customer` = c.`id_customer` AND a2.`deleted` = 0 ORDER BY a2.`id_address` DESC LIMIT 1),
                           ''
                       ) AS `phone`,
                       (SELECT COUNT(*) FROM `{$p}orders` o WHERE o.`id_customer` = c.`id_customer` AND o.`valid` = 1) AS `stays_count`,
                       (SELECT gid.`doc_type` FROM `{$p}lvh_guest_identification` gid WHERE gid.`id_customer` = c.`id_customer` ORDER BY gid.`id_guest_id` DESC LIMIT 1) AS `doc_type`,
                       (SELECT gid2.`doc_number` FROM `{$p}lvh_guest_identification` gid2 WHERE gid2.`id_customer` = c.`id_customer` ORDER BY gid2.`id_guest_id` DESC LIMIT 1) AS `doc_number`,
                       (SELECT gid3.`nationality` FROM `{$p}lvh_guest_identification` gid3 WHERE gid3.`id_customer` = c.`id_customer` ORDER BY gid3.`id_guest_id` DESC LIMIT 1) AS `nationality`
                FROM `{$p}customer` c
                WHERE c.`deleted` = 0
                  AND (
                      c.`firstname` LIKE '{$like}'
                      OR c.`lastname` LIKE '{$like}'
                      OR CONCAT(c.`firstname`, ' ', c.`lastname`) LIKE '{$like}'
                      OR c.`email` LIKE '{$like}'
                      OR EXISTS (
                          SELECT 1 FROM `{$p}address` ad
                          WHERE ad.`id_customer` = c.`id_customer`
                            AND (ad.`phone` LIKE '{$like}' OR ad.`phone_mobile` LIKE '{$like}')
                      )
                      OR EXISTS (
                          SELECT 1 FROM `{$p}lvh_guest_identification` gi
                          WHERE gi.`id_customer` = c.`id_customer` AND gi.`doc_number` LIKE '{$like}'
                      )
                  )
                ORDER BY `stays_count` DESC, c.`id_customer` DESC
                LIMIT 12";

        $rows = Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return array();
        }
        foreach ($rows as &$r) {
            $stays = (int) $r['stays_count'];
            $r['vip_tier'] = self::computeVipTier($stays);
        }
        return $rows;
    }

    /**
     * Compute VIP badge label based on completed stays.
     *
     * @param int $stays
     * @return string
     */
    public static function computeVipTier($stays)
    {
        $stays = (int) $stays;
        if ($stays >= 15) {
            return 'Platinum VIP';
        }
        if ($stays >= 5) {
            return 'VIP Regular';
        }
        if ($stays >= 2) {
            return 'Returning Guest';
        }
        return '';
    }

    /**
     * Load the 34 physical guest rooms with live occupancy, arrival/departure flags,
     * guest VIP status, folio balance due, and housekeeping readiness for `$businessDate`.
     *
     * @param string $businessDate YYYY-MM-DD
     * @param int    $idLang
     * @return array{kpis:array,floors:array,available_rooms:array}
     */
    public static function getRackSnapshot($businessDate, $idLang)
    {
        $p = _DB_PREFIX_;
        $idLang = (int) $idLang;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $businessDate)) {
            $businessDate = date('Y-m-d');
        }
        $nextDay = date('Y-m-d', strtotime($businessDate . ' +1 day'));

        $hasHk = !empty(Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'lvh_room_housekeeping') . "'"));
        $hasFolio = !empty(Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'lvh_folio_charge') . "'"));

        $hkSelect = $hasHk
            ? "COALESCE(hk.`hk_status`, IF(hr.`id_status` = 2, 'ooo', 'clean')) AS `hk_status`, COALESCE(hk.`maintenance_note`, '') AS `hk_note`,"
            : "IF(hr.`id_status` = 2, 'ooo', 'clean') AS `hk_status`, '' AS `hk_note`,";
        $hkJoin = $hasHk
            ? "LEFT JOIN `{$p}lvh_room_housekeeping` hk ON (hk.`id_room` = hr.`id`)"
            : "";

        $rooms = Db::getInstance()->executeS(
            "SELECT hr.`id` AS `id_room`, hr.`room_num`, hr.`floor`, hr.`id_product`, hr.`id_hotel`, hr.`id_status` AS `native_status`,
                    {$hkSelect}
                    COALESCE(pl.`name`, 'Room') AS `room_type`,
                    COALESCE(p.`price`, 0) AS `unit_price`
             FROM `{$p}htl_room_information` hr
             LEFT JOIN `{$p}product` p ON (p.`id_product` = hr.`id_product`)
             LEFT JOIN `{$p}product_lang` pl ON (pl.`id_product` = hr.`id_product` AND pl.`id_lang` = {$idLang})
             {$hkJoin}
             WHERE hr.`room_num` NOT LIKE 'CON-%'
             ORDER BY hr.`floor` ASC, hr.`room_num` ASC"
        );

        // Active bookings touching businessDate (either staying tonight OR due to depart today)
        $bookings = Db::getInstance()->executeS(
            "SELECT hbd.`id` AS `id_booking_detail`, hbd.`id_room`, hbd.`id_order`, hbd.`id_customer`,
                    hbd.`date_from`, hbd.`date_to`, hbd.`id_status`, hbd.`check_in`, hbd.`check_out`,
                    o.`reference`, o.`total_paid_tax_incl`,
                    CONCAT(c.`firstname`, ' ', c.`lastname`) AS `guest_name`,
                    (SELECT COUNT(*) FROM `{$p}orders` oc WHERE oc.`id_customer` = hbd.`id_customer` AND oc.`valid` = 1) AS `stays_count`,
                    COALESCE((SELECT SUM(op.`amount`) FROM `{$p}order_payment` op WHERE op.`order_reference` = o.`reference`), 0) AS `paid_amount`
             FROM `{$p}htl_booking_detail` hbd
             INNER JOIN `{$p}orders` o ON (o.`id_order` = hbd.`id_order`)
             LEFT JOIN `{$p}customer` c ON (c.`id_customer` = hbd.`id_customer`)
             WHERE hbd.`is_refunded` = 0 AND hbd.`is_back_order` = 0
               AND hbd.`id_status` IN (1, 2)
               AND hbd.`date_from` < '" . pSQL($nextDay) . " 00:00:00'
               AND hbd.`date_to` >= '" . pSQL($businessDate) . " 00:00:00'
             ORDER BY hbd.`id_status` DESC, hbd.`date_from` DESC, hbd.`id` DESC"
        );

        $byRoom = array();
        if (is_array($bookings)) {
            foreach ($bookings as $bk) {
                $idRoom = (int) $bk['id_room'];
                // Prefer staying-tonight booking over departing-today booking if back-to-back turnover exists
                $isDepartingToday = (substr((string) $bk['date_to'], 0, 10) === $businessDate);
                if (!isset($byRoom[$idRoom])) {
                    $byRoom[$idRoom] = array('primary' => $bk, 'departing' => $isDepartingToday ? $bk : null);
                } else {
                    if ($isDepartingToday && !$byRoom[$idRoom]['departing']) {
                        $byRoom[$idRoom]['departing'] = $bk;
                    } elseif (!$isDepartingToday && substr((string) $byRoom[$idRoom]['primary']['date_to'], 0, 10) === $businessDate) {
                        $byRoom[$idRoom]['departing'] = $byRoom[$idRoom]['primary'];
                        $byRoom[$idRoom]['primary'] = $bk;
                    }
                }
            }
        }

        $kpis = array(
            'total_rooms' => 0,
            'occupied' => 0,
            'arrivals_today' => 0,
            'departures_due' => 0,
            'vacant_clean' => 0,
            'dirty_or_ooo' => 0,
            'unpaid_folios' => 0,
        );

        $floors = array();
        $availableRooms = array();

        if (is_array($rooms)) {
            foreach ($rooms as $rm) {
                $kpis['total_rooms']++;
                $idRoom = (int) $rm['id_room'];
                $hk = (string) $rm['hk_status'];
                $active = isset($byRoom[$idRoom]) ? $byRoom[$idRoom]['primary'] : null;

                $rm['RackState'] = 'vacant_clean';
                $rm['RackLabel'] = 'Vacant & Ready';
                $rm['RackColor'] = '#15803d';
                $rm['RackBg'] = '#f0fdf4';
                $rm['booking'] = null;
                $rm['balance_due'] = 0.0;
                $rm['vip_tier'] = '';

                if ($active) {
                    $idOrder = (int) $active['id_order'];
                    $extrasTotal = 0.0;
                    if ($hasFolio && $idOrder > 0) {
                        $extrasTotal = (float) Db::getInstance()->getValue(
                            'SELECT COALESCE(SUM(`amount`), 0) FROM `' . $p . 'lvh_folio_charge`
                             WHERE `id_order` = ' . $idOrder . ' AND `voided` = 0'
                        );
                    }
                    $grand = (float) $active['total_paid_tax_incl'] + $extrasTotal;
                    $paid = (float) $active['paid_amount'];
                    $bal = max(0.0, round($grand - $paid, 2));

                    $rm['booking'] = $active;
                    $rm['balance_due'] = $bal;
                    $rm['vip_tier'] = self::computeVipTier((int) $active['stays_count']);
                    if ($bal > 0.01) {
                        $kpis['unpaid_folios']++;
                    }

                    $depDate = substr((string) $active['date_to'], 0, 10);
                    $arrDate = substr((string) $active['date_from'], 0, 10);
                    $st = (int) $active['id_status'];

                    if ($depDate === $businessDate && $st === 2) {
                        $rm['RackState'] = 'due_out';
                        $rm['RackLabel'] = 'Due Out 12:00';
                        $rm['RackColor'] = '#d97706';
                        $rm['RackBg'] = '#fffbeb';
                        $kpis['departures_due']++;
                    } elseif ($st === 2) {
                        $rm['RackState'] = 'checked_in';
                        $rm['RackLabel'] = 'In-House';
                        $rm['RackColor'] = '#1d4ed8';
                        $rm['RackBg'] = '#eff6ff';
                        $kpis['occupied']++;
                    } else {
                        $rm['RackState'] = 'reserved';
                        $rm['RackLabel'] = ($arrDate === $businessDate) ? 'Arriving Today' : 'Reserved';
                        $rm['RackColor'] = '#7c3aed';
                        $rm['RackBg'] = '#f5f3ff';
                        $kpis['occupied']++;
                        if ($arrDate === $businessDate) {
                            $kpis['arrivals_today']++;
                        }
                    }
                } else {
                    if ($hk === 'ooo' || (int) $rm['native_status'] === 2) {
                        $rm['RackState'] = 'ooo';
                        $rm['RackLabel'] = 'Out of Order';
                        $rm['RackColor'] = '#4b5563';
                        $rm['RackBg'] = '#f3f4f6';
                        $kpis['dirty_or_ooo']++;
                    } elseif ($hk !== 'clean') {
                        $rm['RackState'] = 'dirty';
                        $rm['RackLabel'] = ucfirst($hk) . ' (Turnover)';
                        $rm['RackColor'] = '#dc2626';
                        $rm['RackBg'] = '#fef2f2';
                        $kpis['dirty_or_ooo']++;
                    } else {
                        $kpis['vacant_clean']++;
                        $availableRooms[] = $rm;
                    }
                }

                $fl = !empty($rm['floor']) ? (string) $rm['floor'] . ' Floor' : 'Main Floor';
                if (!isset($floors[$fl])) {
                    $floors[$fl] = array();
                }
                $floors[$fl][] = $rm;
            }
        }

        $kpis['occupancy_pct'] = $kpis['total_rooms'] > 0
            ? round(($kpis['occupied'] / $kpis['total_rooms']) * 100, 1)
            : 0.0;

        return array(
            'kpis' => $kpis,
            'floors' => $floors,
            'available_rooms' => $availableRooms,
        );
    }

    /**
     * Save Ghana Card / Passport / Driver License identification for a guest & order.
     *
     * @param int    $idCustomer
     * @param int    $idOrder
     * @param string $docType
     * @param string $docNumber
     * @param string $nationality
     * @param string $staffName
     */
    public static function saveGuestIdentification($idCustomer, $idOrder, $docType, $docNumber, $nationality, $staffName)
    {
        $docNumber = trim((string) $docNumber);
        if ((int) $idCustomer <= 0 || $docNumber === '') {
            return;
        }
        Db::getInstance()->insert('lvh_guest_identification', array(
            'id_customer' => (int) $idCustomer,
            'id_order' => (int) $idOrder,
            'doc_type' => pSQL(Tools::substr($docType ?: 'Ghana Card', 0, 32)),
            'doc_number' => pSQL(Tools::substr($docNumber, 0, 64)),
            'nationality' => pSQL(Tools::substr($nationality ?: 'Ghanaian', 0, 64)),
            'recorded_by_name' => pSQL(Tools::substr($staffName, 0, 128)),
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    /**
     * Create a complete native QloApps Walk-In Booking (Customer + Address + Cart +
     * HotelCartBookingData + BoOrder::validateOrder + optional Instant Check-In + ID Doc).
     *
     * @param array   $payload
     * @param Context $context
     * @return array{ok:bool,id_order?:int,reference?:string,error?:string}
     */
    public static function createExpressWalkIn($payload, $context)
    {
        if (!file_exists(_PS_MODULE_DIR_ . 'hotelreservationsystem/define.php')) {
            return array('ok' => false, 'error' => 'QloApps hotelreservationsystem module is not available.');
        }
        require_once _PS_MODULE_DIR_ . 'hotelreservationsystem/define.php';

        $idRoom = (int) $payload['id_room'];
        $dateFrom = trim((string) $payload['date_from']);
        $nights = max(1, min(90, (int) $payload['nights']));
        $dateTo = date('Y-m-d', strtotime($dateFrom . ' +' . $nights . ' days'));
        $adults = max(1, (int) $payload['adults']);

        $roomRow = Db::getInstance()->getRow(
            'SELECT `id`, `id_product`, `id_hotel`, `room_num`, `id_status`
             FROM `' . _DB_PREFIX_ . 'htl_room_information`
             WHERE `id` = ' . $idRoom
        );
        if (!$roomRow || (int) $roomRow['id_status'] === 2) {
            return array('ok' => false, 'error' => 'Selected room is inactive or Out of Order.');
        }

        // Resolve or create Customer + Address
        $idCustomer = (int) $payload['id_customer'];
        $phoneRaw = preg_replace('/[^0-9+]/', '', (string) $payload['phone']);
        $firstName = trim((string) $payload['firstname']);
        $lastName = trim((string) $payload['lastname']);
        $email = trim((string) $payload['email']);

        if ($idCustomer > 0) {
            $customer = new Customer($idCustomer);
            if (!Validate::isLoadedObject($customer)) {
                $idCustomer = 0;
            }
        }

        if ($idCustomer <= 0) {
            if ($firstName === '' || $lastName === '') {
                return array('ok' => false, 'error' => 'Please enter guest First Name and Last Name.');
            }
            // Strip any digits from names to satisfy Validate::isCustomerName
            $cleanFirst = trim(preg_replace('/[0-9!<>,;?=+()@#"°{}_$%:]+/', '', $firstName)) ?: 'Guest';
            $cleanLast = trim(preg_replace('/[0-9!<>,;?=+()@#"°{}_$%:]+/', '', $lastName)) ?: 'WalkIn';

            if ($email === '' || !Validate::isEmail($email)) {
                $slug = $phoneRaw !== '' ? preg_replace('/[^0-9]/', '', $phoneRaw) : uniqid();
                $email = 'guest.' . $slug . '.' . rand(10, 99) . '@walkin.lushviewhotel.net';
            }

            $existingByEmail = Customer::getCustomersByEmail($email);
            if (!empty($existingByEmail) && isset($existingByEmail[0]['id_customer'])) {
                $customer = new Customer((int) $existingByEmail[0]['id_customer']);
            } else {
                $customer = new Customer();
                $customer->firstname = $cleanFirst;
                $customer->lastname = $cleanLast;
                $customer->email = $email;
                $customer->passwd = Tools::encrypt(Tools::passwdGen(12));
                $customer->is_guest = 0;
                $customer->active = 1;
                if (!$customer->add()) {
                    return array('ok' => false, 'error' => 'Could not create guest profile.');
                }
            }
            $idCustomer = (int) $customer->id;
        }

        $idAddress = (int) Address::getFirstCustomerAddressId($idCustomer);
        if ($idAddress <= 0) {
            $idCountry = (int) Country::getByIso('GH');
            if ($idCountry <= 0) {
                $idCountry = (int) Configuration::get('PS_COUNTRY_DEFAULT');
            }
            $addr = new Address();
            $addr->id_customer = $idCustomer;
            $addr->id_country = $idCountry;
            $addr->alias = 'Front Desk';
            $addr->firstname = $customer->firstname;
            $addr->lastname = $customer->lastname;
            $addr->address1 = 'Lush View Hotel Walk-In, Accra';
            $addr->city = 'Accra';
            $addr->phone_mobile = $phoneRaw !== '' ? $phoneRaw : '0000000000';
            $addr->phone = $addr->phone_mobile;
            $addr->add();
            $idAddress = (int) $addr->id;
        } elseif ($phoneRaw !== '') {
            $addr = new Address($idAddress);
            if (Validate::isLoadedObject($addr) && empty($addr->phone_mobile)) {
                $addr->phone_mobile = $phoneRaw;
                $addr->update();
            }
        }

        // Build QloApps Cart & HotelCartBookingData
        $idCurrency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $idLang = (int) $context->language->id;

        $cart = new Cart();
        $cart->id_customer = $idCustomer;
        $cart->id_address_delivery = $idAddress;
        $cart->id_address_invoice = $idAddress;
        $cart->id_currency = $idCurrency;
        $cart->id_lang = $idLang;
        $cart->secure_key = $customer->secure_key;
        $cart->id_shop = (int) $context->shop->id;
        if (!$cart->add()) {
            return array('ok' => false, 'error' => 'Could not initialize booking cart.');
        }

        $prevCart = $context->cart;
        $context->cart = $cart;

        $selType = (int) Configuration::get('PS_BACKOFFICE_ROOM_BOOKING_TYPE');
        if ($selType == HotelBookingDetail::PS_ROOM_UNIT_SELECTION_TYPE_OCCUPANCY) {
            $occupancy = array(
                array('adults' => $adults, 'children' => 0, 'child_ages' => array()),
            );
        } else {
            $occupancy = 1;
        }

        $cartBooking = new HotelCartBookingData();
        $booked = $cartBooking->updateCartBooking(
            (int) $roomRow['id_product'],
            $occupancy,
            'up',
            (int) $roomRow['id_hotel'],
            $idRoom,
            $dateFrom,
            $dateTo,
            array(),
            (int) $cart->id,
            0,
            HotelBookingDetail::ALLOTMENT_MANUAL,
            trim((string) $payload['comment'])
        );

        if (!$booked) {
            $cart->delete();
            $context->cart = $prevCart;
            return array(
                'ok' => false,
                'error' => sprintf(
                    'Room %s is already booked or blocked between %s and %s.',
                    $roomRow['room_num'],
                    $dateFrom,
                    $dateTo
                ),
            );
        }

        $orderTotal = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $paymentLabel = trim((string) $payload['payment_method']) ?: 'Cash (GHS)';
        $txId = trim((string) $payload['transaction_id']);
        $isPayLater = (stripos($paymentLabel, 'Pay Later') !== false);
        $amountPaid = $isPayLater
            ? 0.0
            : max(0.0, (float) $payload['amount_paid']);
        if ($amountPaid > $orderTotal) {
            $amountPaid = $orderTotal;
        }

        if ($amountPaid <= 0.0) {
            $idOrderState = (int) Configuration::get('PS_OS_AWAITING_PAYMENT');
            if ($idOrderState <= 0) {
                $idOrderState = (int) Configuration::get('PS_OS_PREPARATION');
            }
        } elseif ($amountPaid + 0.01 >= $orderTotal) {
            $idOrderState = (int) Configuration::get('PS_OS_PAYMENT_ACCEPTED');
            if ($idOrderState <= 0) {
                $idOrderState = (int) Configuration::get('PS_OS_PAYMENT');
            }
        } else {
            $idOrderState = (int) Configuration::get('PS_OS_PARTIAL_PAYMENT_ACCEPTED');
            if ($idOrderState <= 0) {
                $idOrderState = (int) Configuration::get('PS_OS_PAYMENT');
            }
        }

        $boModule = new BoOrder();
        $boModule->displayName = $paymentLabel;
        $extraVars = $txId !== '' ? array('transaction_id' => $txId) : array();

        $staffName = (isset($context->employee) && Validate::isLoadedObject($context->employee))
            ? trim($context->employee->firstname . ' ' . $context->employee->lastname)
            : 'Front Desk';

        // Deliberately pass $sendMails = false to prevent mailbox inode exhaustion on walk-ins
        $boModule->validateOrder(
            (int) $cart->id,
            $idOrderState,
            Tools::ps_round($amountPaid, 6),
            $paymentLabel,
            'LVH Express Front Desk — ' . $staffName,
            $extraVars,
            null,
            false,
            $cart->secure_key,
            null,
            false
        );

        $context->cart = $prevCart;
        $idOrder = (int) $boModule->currentOrder;
        if ($idOrder <= 0) {
            return array('ok' => false, 'error' => 'Order validation failed.');
        }

        $order = new Order($idOrder);

        // Save Ghana Card / Passport identification if supplied
        if (!empty($payload['doc_number'])) {
            self::saveGuestIdentification(
                $idCustomer,
                $idOrder,
                (string) $payload['doc_type'],
                (string) $payload['doc_number'],
                (string) $payload['nationality'],
                $staffName
            );
        }

        // Instant Check-In when arriving today and requested
        if (!empty($payload['instant_checkin']) && $dateFrom <= date('Y-m-d')) {
            $bdIds = Db::getInstance()->executeS(
                'SELECT `id` FROM `' . _DB_PREFIX_ . 'htl_booking_detail` WHERE `id_order` = ' . $idOrder
            );
            if (is_array($bdIds)) {
                foreach ($bdIds as $bRow) {
                    $bd = new HotelBookingDetail((int) $bRow['id']);
                    if (Validate::isLoadedObject($bd)) {
                        $bd->id_status = HotelBookingDetail::STATUS_CHECKED_IN;
                        $bd->check_in = date('Y-m-d H:i:s');
                        $bd->update();
                    }
                }
            }
        }

        return array(
            'ok' => true,
            'id_order' => $idOrder,
            'reference' => (string) $order->reference,
        );
    }
}
