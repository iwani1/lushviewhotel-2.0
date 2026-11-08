<?php
/**
 * Lush View Hotel - Accountant Daily Flash, Tender Reconciliation, VIP & Hygiene Controller.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(_PS_MODULE_DIR_ . 'lvhpayments/classes/LvhPaymentNormalizer.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhpayments/classes/LvhPaymentNormalizer.php';
}
if (file_exists(_PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php')) {
    require_once _PS_MODULE_DIR_ . 'lvhauditlog/classes/LvhStaffAudit.php';
}

class AdminLvhReportsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Daily Flash, Reconciliation & VIPs');
    }

    /**
     * Handle CSV exports and SuperAdmin 1-click hygiene remediations.
     */
    public function postProcess()
    {
        parent::postProcess();

        list($dateFrom, $dateTo) = $this->getDateRange();

        if (Tools::isSubmit('exportReconciliationCsv')) {
            $tenders = $this->computeTenderReconciliation($dateFrom, $dateTo);
            $this->streamTenderCsv($dateFrom, $dateTo, $tenders);
        }

        if ($this->isSuperAdminOrIt()) {
            if (Tools::isSubmit('hygieneDisableForeignTaxes')) {
                $this->runHygieneDisableForeignTaxes();
            }
            if (Tools::isSubmit('hygieneStopAdminMailSpam')) {
                $this->runHygieneStopAdminMailSpam();
            }
            if (Tools::isSubmit('hygieneCleanOrphanModules')) {
                $this->runHygieneCleanOrphanModules();
            }
            if (Tools::isSubmit('hygieneFixBrandingDefaults')) {
                $this->runHygieneFixBrandingDefaults();
            }
        }
    }

    /**
     * Render Accountant Flash, Tender Reconciliation, Open Balances, VIP Guests, and Hygiene checks.
     */
    public function initContent()
    {
        parent::initContent();

        list($dateFrom, $dateTo) = $this->getDateRange();

        $flash = $this->computeHospitalityFlash($dateFrom, $dateTo);
        $tenders = $this->computeTenderReconciliation($dateFrom, $dateTo);
        $openFolios = $this->loadOpenBalanceOrders();
        $vipGuests = $this->loadVipLeaderboard();
        $hygiene = $this->inspectHygieneState();

        $this->context->smarty->assign(array(
            'lvh_date_from' => $dateFrom,
            'lvh_date_to' => $dateTo,
            'lvh_flash' => $flash,
            'lvh_tenders' => $tenders,
            'lvh_open_folios' => $openFolios,
            'lvh_vip_guests' => $vipGuests,
            'lvh_hygiene' => $hygiene,
            'lvh_can_hygiene' => $this->isSuperAdminOrIt(),
            'lvh_controller_url' => $this->context->link->getAdminLink('AdminLvhReports'),
            'lvh_order_base_url' => $this->context->link->getAdminLink('AdminOrders'),
        ));

        $tpl = _PS_MODULE_DIR_ . 'lvhreports/views/templates/admin/reports-dashboard.tpl';
        $this->content .= $this->context->smarty->fetch($tpl);
        $this->context->smarty->assign('content', $this->content);
    }

    /**
     * Validated `[date_from, date_to]` range (defaults to current month-to-date).
     *
     * @return array{0:string,1:string}
     */
    protected function getDateRange()
    {
        $df = trim((string) Tools::getValue('date_from', date('Y-m-01')));
        $dt = trim((string) Tools::getValue('date_to', date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
            $df = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt) || $dt < $df) {
            $dt = $df;
        }
        return array($df, $dt);
    }

    /**
     * Compute true 34-room occupancy, ADR, RevPAR, Conference Hall revenue, and Folio Extras.
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    protected function computeHospitalityFlash($dateFrom, $dateTo)
    {
        $p = _DB_PREFIX_;
        $days = max(1, (int) round((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1);
        $rangeEndExclusive = date('Y-m-d', strtotime($dateTo . ' +1 day'));

        $physicalRooms = (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM `{$p}htl_room_information` WHERE `room_num` NOT LIKE 'CON-%'"
        );
        if ($physicalRooms <= 0) {
            $physicalRooms = 34;
        }
        $availableRoomNights = $physicalRooms * $days;

        // Fetch physical bedroom stays overlapping [dateFrom, rangeEndExclusive)
        $stays = Db::getInstance()->executeS(
            "SELECT hbd.`date_from`, hbd.`date_to`, hbd.`total_price_tax_incl`
             FROM `{$p}htl_booking_detail` hbd
             INNER JOIN `{$p}htl_room_information` hr ON (hr.`id` = hbd.`id_room`)
             WHERE hr.`room_num` NOT LIKE 'CON-%'
               AND hbd.`is_refunded` = 0 AND hbd.`is_back_order` = 0
               AND hbd.`id_status` IN (1, 2, 3)
               AND hbd.`date_from` < '" . pSQL($rangeEndExclusive) . " 00:00:00'
               AND hbd.`date_to` > '" . pSQL($dateFrom) . " 00:00:00'"
        );

        $soldNights = 0;
        $roomRevenue = 0.0;
        if (is_array($stays)) {
            $rStart = strtotime($dateFrom);
            $rEnd = strtotime($rangeEndExclusive);
            foreach ($stays as $st) {
                $sFrom = strtotime(substr((string) $st['date_from'], 0, 10));
                $sTo = strtotime(substr((string) $st['date_to'], 0, 10));
                $totalStayNights = max(1, (int) round(($sTo - $sFrom) / 86400));
                $ovStart = max($rStart, $sFrom);
                $ovEnd = min($rEnd, $sTo);
                $ovNights = max(0, (int) round(($ovEnd - $ovStart) / 86400));
                if ($ovNights > 0) {
                    $soldNights += $ovNights;
                    $roomRevenue += ((float) $st['total_price_tax_incl'] * ($ovNights / $totalStayNights));
                }
            }
        }

        // Conference Hall revenue (from unified table if installed, else legacy CON-* rows)
        $confRevenue = 0.0;
        $hasConf = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'lvh_conference_booking') . "'");
        if (!empty($hasConf)) {
            $confRevenue = (float) Db::getInstance()->getValue(
                "SELECT COALESCE(SUM(`total_amount`), 0) FROM `{$p}lvh_conference_booking`
                 WHERE `status` IN ('confirmed', 'completed')
                   AND `start_datetime` >= '" . pSQL($dateFrom) . " 00:00:00'
                   AND `start_datetime` <= '" . pSQL($dateTo) . " 23:59:59'"
            );
        } else {
            $confRevenue = (float) Db::getInstance()->getValue(
                "SELECT COALESCE(SUM(hbd.`total_price_tax_incl`), 0)
                 FROM `{$p}htl_booking_detail` hbd
                 INNER JOIN `{$p}htl_room_information` hr ON (hr.`id` = hbd.`id_room`)
                 WHERE hr.`room_num` LIKE 'CON-%'
                   AND hbd.`is_refunded` = 0 AND hbd.`id_status` IN (1, 2, 3)
                   AND hbd.`date_from` >= '" . pSQL($dateFrom) . " 00:00:00'
                   AND hbd.`date_from` <= '" . pSQL($dateTo) . " 23:59:59'"
            );
        }

        // Room Folio Extras revenue
        $extrasRevenue = 0.0;
        $hasFolio = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'lvh_folio_charge') . "'");
        if (!empty($hasFolio)) {
            $extrasRevenue = (float) Db::getInstance()->getValue(
                "SELECT COALESCE(SUM(`amount`), 0) FROM `{$p}lvh_folio_charge`
                 WHERE `voided` = 0
                   AND `date_add` >= '" . pSQL($dateFrom) . " 00:00:00'
                   AND `date_add` <= '" . pSQL($dateTo) . " 23:59:59'"
            );
        }

        $occPct = $availableRoomNights > 0 ? round(($soldNights / $availableRoomNights) * 100, 1) : 0.0;
        $adr = $soldNights > 0 ? round($roomRevenue / $soldNights, 2) : 0.0;
        $revpar = $availableRoomNights > 0 ? round($roomRevenue / $availableRoomNights, 2) : 0.0;

        return array(
            'days' => $days,
            'physical_rooms' => $physicalRooms,
            'available_nights' => $availableRoomNights,
            'sold_nights' => $soldNights,
            'occupancy_pct' => $occPct,
            'room_revenue' => round($roomRevenue, 2),
            'adr' => $adr,
            'revpar' => $revpar,
            'conference_revenue' => round($confRevenue, 2),
            'extras_revenue' => round($extrasRevenue, 2),
            'total_revenue' => round($roomRevenue + $confRevenue + $extrasRevenue, 2),
        );
    }

    /**
     * Normalize all `qlo_order_payment` rows in `[dateFrom, dateTo]` into canonical buckets.
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    protected function computeTenderReconciliation($dateFrom, $dateTo)
    {
        $p = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS(
            "SELECT `payment_method`, `transaction_id`, `amount`
             FROM `{$p}order_payment`
             WHERE `date_add` >= '" . pSQL($dateFrom) . " 00:00:00'
               AND `date_add` <= '" . pSQL($dateTo) . " 23:59:59'"
        );

        $buckets = array();
        $grandCashCollected = 0.0;

        if (is_array($rows)) {
            foreach ($rows as $r) {
                $cls = class_exists('LvhPaymentNormalizer')
                    ? LvhPaymentNormalizer::classify($r['payment_method'])
                    : array('code' => (string) $r['payment_method'], 'label' => (string) $r['payment_method'], 'group' => 'other');

                $key = $cls['code'];
                if (!isset($buckets[$key])) {
                    $buckets[$key] = array(
                        'code' => $cls['code'],
                        'label' => $cls['label'],
                        'group' => $cls['group'],
                        'tx_count' => 0,
                        'missing_ref_count' => 0,
                        'amount' => 0.0,
                    );
                }
                $amt = (float) $r['amount'];
                $buckets[$key]['tx_count']++;
                $buckets[$key]['amount'] += $amt;
                if ($cls['group'] !== 'cash' && trim((string) $r['transaction_id']) === '') {
                    $buckets[$key]['missing_ref_count']++;
                }
                if ($cls['group'] !== 'comp') {
                    $grandCashCollected += $amt;
                }
            }
        }

        uasort($buckets, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        return array(
            'rows' => array_values($buckets),
            'total_collected' => round($grandCashCollected, 2),
        );
    }

    /**
     * Load valid orders with unpaid or partially paid balances (aging report).
     *
     * @return array
     */
    protected function loadOpenBalanceOrders()
    {
        $p = _DB_PREFIX_;
        $canceled = (int) Configuration::get('PS_OS_CANCELED');
        $refunded = (int) Configuration::get('PS_OS_REFUND');

        $sql = "SELECT o.`id_order`, o.`reference`, o.`date_add`, o.`payment`, o.`total_paid_tax_incl`,
                       CONCAT(c.`firstname`, ' ', c.`lastname`) AS `guest_name`,
                       COALESCE((SELECT NULLIF(a.`phone_mobile`, '') FROM `{$p}address` a WHERE a.`id_customer` = o.`id_customer` AND a.`deleted` = 0 ORDER BY a.`id_address` DESC LIMIT 1), '') AS `phone`,
                       COALESCE((SELECT SUM(op.`amount`) FROM `{$p}order_payment` op WHERE op.`order_reference` = o.`reference`), 0) AS `paid_sum`,
                       DATEDIFF(NOW(), o.`date_add`) AS `age_days`
                FROM `{$p}orders` o
                LEFT JOIN `{$p}customer` c ON (c.`id_customer` = o.`id_customer`)
                WHERE o.`current_state` NOT IN ({$canceled}, {$refunded})
                HAVING (`total_paid_tax_incl` - `paid_sum`) > 0.50
                ORDER BY o.`date_add` DESC
                LIMIT 60";

        $rows = Db::getInstance()->executeS($sql);
        if (!is_array($rows)) {
            return array();
        }
        foreach ($rows as &$r) {
            $r['balance_due'] = round((float) $r['total_paid_tax_incl'] - (float) $r['paid_sum'], 2);
        }
        return $rows;
    }

    /**
     * Load top 30 repeat / VIP guests by completed stay count & lifetime spend.
     *
     * @return array
     */
    protected function loadVipLeaderboard()
    {
        $p = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS(
            "SELECT c.`id_customer`, CONCAT(c.`firstname`, ' ', c.`lastname`) AS `guest_name`, c.`email`,
                    COUNT(o.`id_order`) AS `stays_count`,
                    SUM(o.`total_paid_tax_incl`) AS `lifetime_spend`,
                    MAX(o.`date_add`) AS `last_stay`,
                    COALESCE((SELECT NULLIF(a.`phone_mobile`, '') FROM `{$p}address` a WHERE a.`id_customer` = c.`id_customer` AND a.`deleted` = 0 ORDER BY a.`id_address` DESC LIMIT 1), '') AS `phone`
             FROM `{$p}customer` c
             INNER JOIN `{$p}orders` o ON (o.`id_customer` = c.`id_customer` AND o.`valid` = 1)
             WHERE c.`deleted` = 0
             GROUP BY c.`id_customer`
             HAVING `stays_count` >= 3
             ORDER BY `stays_count` DESC, `lifetime_spend` DESC
             LIMIT 30"
        );
        return is_array($rows) ? $rows : array();
    }

    /**
     * Inspect live configuration hygiene items discovered in the production audit.
     *
     * @return array
     */
    protected function inspectHygieneState()
    {
        $p = _DB_PREFIX_;
        $activeTaxes = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . $p . 'tax` WHERE `active` = 1 AND `deleted` = 0'
        );
        $adminSpamEnabled = ((int) Configuration::get('PS_ORDER_CONF_MAIL_TO_EMPLOYEE') === 1
            || (int) Configuration::get('PS_ORDER_CONF_MAIL_TO_HOTEL_MANAGER') === 1
            || (int) Configuration::get('PS_ORDER_CONF_MAIL_TO_SUPERADMIN') === 1);
        $orphanCashPayment = (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM `{$p}module` WHERE `name` = 'cashpayment'"
        );
        $vulnReviewActive = (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM `{$p}module` WHERE `name` = 'qlohotelreview' AND `active` = 1"
        );
        $shopName = (string) Configuration::get('PS_SHOP_NAME');
        $headerTitle = (string) Configuration::get('WK_TITLE_HEADER_BLOCK');

        return array(
            'active_taxes' => $activeTaxes,
            'admin_spam_enabled' => $adminSpamEnabled,
            'orphan_or_vuln_modules' => ($orphanCashPayment + $vulnReviewActive),
            'branding_needs_fix' => ($shopName === 'lushviewhotel.net' || $headerTitle === 'Hotel Prime'),
            'shop_name' => $shopName,
            'header_title' => $headerTitle,
        );
    }

    protected function runHygieneDisableForeignTaxes()
    {
        Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'tax` SET `active` = 0 WHERE `active` = 1');
        Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'tax_rules_group` SET `active` = 0 WHERE `active` = 1');
        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record('hygiene.foreign_taxes_disabled', 'Configuration', 0, 'Deactivated unused foreign/US tax rules while preserving flat all-inclusive GHS room rates', array(), 'warning');
        }
        $this->confirmations[] = $this->l('All unused foreign/US state taxes deactivated; flat GHS room tariffs preserved.');
    }

    protected function runHygieneStopAdminMailSpam()
    {
        Configuration::updateValue('PS_ORDER_CONF_MAIL_TO_EMPLOYEE', 0);
        Configuration::updateValue('PS_ORDER_CONF_MAIL_TO_HOTEL_MANAGER', 0);
        Configuration::updateValue('PS_ORDER_CONF_MAIL_TO_SUPERADMIN', 0);
        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record('hygiene.admin_mail_spam_disabled', 'Configuration', 0, 'Disabled internal order_conf_admin email copies to prevent mailbox/inode exhaustion', array(), 'warning');
        }
        $this->confirmations[] = $this->l('Internal admin order-confirmation email spam disabled (guest emails unaffected).');
    }

    protected function runHygieneCleanOrphanModules()
    {
        $p = _DB_PREFIX_;
        if (!is_dir(_PS_MODULE_DIR_ . 'cashpayment')) {
            $idMod = (int) Db::getInstance()->getValue("SELECT `id_module` FROM `{$p}module` WHERE `name` = 'cashpayment'");
            if ($idMod > 0) {
                Db::getInstance()->delete('module_shop', '`id_module` = ' . $idMod);
                Db::getInstance()->delete('module_group', '`id_module` = ' . $idMod);
                Db::getInstance()->delete('hook_module', '`id_module` = ' . $idMod);
                Db::getInstance()->delete('module', '`id_module` = ' . $idMod);
            }
        }
        if (Module::isInstalled('qlohotelreview')) {
            $mod = Module::getInstanceByName('qlohotelreview');
            if ($mod) {
                $mod->disable();
            } else {
                Db::getInstance()->update('module', array('active' => 0), "`name` = 'qlohotelreview'");
            }
        }
        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record('hygiene.modules_cleaned', 'Module', 0, 'Purged orphan cashpayment DB row and disabled vulnerable qlohotelreview', array(), 'warning');
        }
        $this->confirmations[] = $this->l('Orphan cashpayment DB entry cleaned and qlohotelreview disabled.');
    }

    protected function runHygieneFixBrandingDefaults()
    {
        Configuration::updateValue('PS_SHOP_NAME', 'Lush View Hotel');
        Configuration::updateValue('WK_TITLE_HEADER_BLOCK', 'Lush View Hotel');
        $socialKeys = array('BLOCKSOCIAL_FACEBOOK', 'BLOCKSOCIAL_TWITTER', 'BLOCKSOCIAL_RSS', 'BLOCKSOCIAL_YOUTUBE', 'BLOCKSOCIAL_GOOGLE_PLUS', 'BLOCKSOCIAL_PINTEREST', 'BLOCKSOCIAL_VIMEO', 'BLOCKSOCIAL_INSTAGRAM');
        foreach ($socialKeys as $sk) {
            $val = (string) Configuration::get($sk);
            if (stripos($val, 'qloapps') !== false || stripos($val, 'prestashop') !== false) {
                Configuration::updateValue($sk, '');
            }
        }
        if (class_exists('LvhStaffAudit')) {
            LvhStaffAudit::record('hygiene.branding_updated', 'Configuration', 0, 'Updated PS_SHOP_NAME & WK_TITLE_HEADER_BLOCK to "Lush View Hotel" and cleared placeholder social URLs', array(), 'info');
        }
        $this->confirmations[] = $this->l('Shop name, header title, and social links updated for Lush View Hotel.');
    }

    protected function streamTenderCsv($dateFrom, $dateTo, $tenders)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $filename = 'lushview-reconciliation-' . $dateFrom . '-to-' . $dateTo . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, array('Canonical Code', 'Tender Label', 'Group', 'Transactions', 'Missing Reference Count', 'Total Amount (GHS)'));
        foreach ($tenders['rows'] as $r) {
            fputcsv($out, array($r['code'], $r['label'], $r['group'], $r['tx_count'], $r['missing_ref_count'], number_format((float) $r['amount'], 2, '.', '')));
        }
        fclose($out);
        exit;
    }

    protected function isSuperAdminOrIt()
    {
        if (!isset($this->context->employee) || !Validate::isLoadedObject($this->context->employee)) {
            return false;
        }
        $idProfile = (int) $this->context->employee->id_profile;
        if ($idProfile === 1) {
            return true;
        }
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $name = strtolower((string) Db::getInstance()->getValue(
            'SELECT `name` FROM `' . _DB_PREFIX_ . 'profile_lang` WHERE `id_profile` = ' . $idProfile . ' AND `id_lang` = ' . $idLang
        ));
        return ($name === 'it' || strpos($name, 'it ') === 0);
    }
}
