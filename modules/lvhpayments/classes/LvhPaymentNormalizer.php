<?php
/**
 * Lush View Hotel - Canonical Payment Method Normalizer & Schema Helper.
 *
 * Enforces standardized Ghana hospitality payment methods on new transactions
 * and maps all 18 audited legacy free-text spellings into clean accounting buckets.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhPaymentNormalizer
{
    /**
     * Return canonical tender definitions used across Front Desk, AdminOrders,
     * Shift Cash-Up, and Accountant Reconciliation.
     *
     * @param bool $includeComp Whether to expose Management Comp in UI dropdowns
     * @return array
     */
    public static function getCanonicalMethods($includeComp = true)
    {
        $methods = array(
            'CASH' => array(
                'code' => 'CASH',
                'label' => 'Cash (GHS)',
                'group' => 'cash',
                'requires_ref' => false,
                'ref_placeholder' => 'Optional receipt / note',
                'supervisor_only' => false,
            ),
            'MTN_MOMO' => array(
                'code' => 'MTN_MOMO',
                'label' => 'MTN MoMo',
                'group' => 'momo',
                'requires_ref' => true,
                'ref_placeholder' => 'Required: MTN MoMo Transaction ID',
                'supervisor_only' => false,
            ),
            'TELECEL_CASH' => array(
                'code' => 'TELECEL_CASH',
                'label' => 'Telecel Cash',
                'group' => 'momo',
                'requires_ref' => true,
                'ref_placeholder' => 'Required: Telecel Cash Transaction ID',
                'supervisor_only' => false,
            ),
            'AT_MONEY' => array(
                'code' => 'AT_MONEY',
                'label' => 'AT Money',
                'group' => 'momo',
                'requires_ref' => true,
                'ref_placeholder' => 'Required: AT Money Transaction ID',
                'supervisor_only' => false,
            ),
            'POS_CARD' => array(
                'code' => 'POS_CARD',
                'label' => 'POS / Card',
                'group' => 'pos',
                'requires_ref' => true,
                'ref_placeholder' => 'Required: POS Slip / Approval Code',
                'supervisor_only' => false,
            ),
            'BANK_TRANSFER' => array(
                'code' => 'BANK_TRANSFER',
                'label' => 'Bank Transfer',
                'group' => 'bank',
                'requires_ref' => true,
                'ref_placeholder' => 'Required: Bank Transfer Reference',
                'supervisor_only' => false,
            ),
            'PAY_LATER' => array(
                'code' => 'PAY_LATER',
                'label' => 'Corporate / Pay Later',
                'group' => 'credit',
                'requires_ref' => false,
                'ref_placeholder' => 'Company name or LPO reference',
                'supervisor_only' => false,
            ),
            'MGMT_COMP' => array(
                'code' => 'MGMT_COMP',
                'label' => 'Management Comp',
                'group' => 'comp',
                'requires_ref' => true,
                'ref_placeholder' => 'Required: Authorizing Manager & Reason',
                'supervisor_only' => true,
            ),
        );

        if (!$includeComp) {
            unset($methods['MGMT_COMP']);
        }

        return $methods;
    }

    /**
     * Map any raw `qlo_order_payment.payment_method` or `qlo_orders.payment`
     * string (including historical free-text typos) to a canonical bucket.
     *
     * @param string $rawMethod
     * @return array{code:string,label:string,group:string}
     */
    public static function classify($rawMethod)
    {
        $raw = trim((string) $rawMethod);
        $norm = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $raw));
        $norm = trim(preg_replace('/\s+/', ' ', $norm));

        $methods = self::getCanonicalMethods(true);

        // Exact canonical label match first
        foreach ($methods as $code => $def) {
            if (strcasecmp($raw, $def['label']) === 0 || strcasecmp($raw, $code) === 0) {
                return array(
                    'code' => $code,
                    'label' => $def['label'],
                    'group' => $def['group'],
                );
            }
        }

        // Mixed legacy strings like "momo and cash" / "cash and momo"
        if (strpos($norm, 'momo') !== false && strpos($norm, 'cash') !== false) {
            return array(
                'code' => 'MIXED_LEGACY',
                'label' => 'Mixed Cash + MoMo (Legacy)',
                'group' => 'momo',
            );
        }

        // Network-specific Mobile Money
        if (strpos($norm, 'mtn') !== false) {
            return array('code' => 'MTN_MOMO', 'label' => 'MTN MoMo', 'group' => 'momo');
        }
        if (strpos($norm, 'telecel') !== false || strpos($norm, 'vodafone') !== false) {
            return array('code' => 'TELECEL_CASH', 'label' => 'Telecel Cash', 'group' => 'momo');
        }
        if (strpos($norm, 'airtel') !== false || strpos($norm, 'tigo') !== false || $norm === 'at money') {
            return array('code' => 'AT_MONEY', 'label' => 'AT Money', 'group' => 'momo');
        }
        if (strpos($norm, 'momo') !== false || strpos($norm, 'mobile money') !== false) {
            return array(
                'code' => 'MOMO_LEGACY',
                'label' => 'Mobile Money (Legacy Unspecified)',
                'group' => 'momo',
            );
        }

        // POS / Card variations ("P.O.S", "P O S", "P. O. S", "POS", "card", "visa")
        $collapsed = str_replace(' ', '', $norm);
        if ($collapsed === 'pos'
            || strpos($norm, 'p o s') !== false
            || strpos($norm, 'pos ') === 0
            || strpos($norm, 'card') !== false
            || strpos($norm, 'visa') !== false
            || strpos($norm, 'mastercard') !== false
        ) {
            return array('code' => 'POS_CARD', 'label' => 'POS / Card', 'group' => 'pos');
        }

        // Bank transfer / cheque
        if (strpos($norm, 'bank') !== false || strpos($norm, 'transfer') !== false || strpos($norm, 'cheque') !== false || strpos($norm, 'check') !== false) {
            return array('code' => 'BANK_TRANSFER', 'label' => 'Bank Transfer', 'group' => 'bank');
        }

        // Management complimentary
        if (strpos($norm, 'management') !== false || strpos($norm, 'comp') !== false) {
            return array('code' => 'MGMT_COMP', 'label' => 'Management Comp', 'group' => 'comp');
        }

        // Corporate / Pay Later / awaiting payment
        if (strpos($norm, 'awaiting') !== false || strpos($norm, 'later') !== false || strpos($norm, 'corporate') !== false || strpos($norm, 'credit') !== false) {
            return array('code' => 'PAY_LATER', 'label' => 'Corporate / Pay Later', 'group' => 'credit');
        }

        // Cash
        if (strpos($norm, 'cash') !== false) {
            return array('code' => 'CASH', 'label' => 'Cash (GHS)', 'group' => 'cash');
        }

        // Legacy generic Back office order or numeric tx id entered in method box
        return array(
            'code' => 'UNCLASSIFIED_BO',
            'label' => 'Unclassified Legacy (' . ($raw !== '' ? $raw : 'Blank') . ')',
            'group' => 'unclassified',
        );
    }

    /**
     * Create `qlo_lvh_payment_meta` and `qlo_lvh_shift_cashup` tables.
     *
     * @return bool
     */
    public static function createTables()
    {
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;

        $sql1 = "CREATE TABLE IF NOT EXISTS `{$p}lvh_payment_meta` (
            `id_payment_meta` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order_payment` INT(11) UNSIGNED NOT NULL,
            `id_order` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `canonical_code` VARCHAR(32) NOT NULL DEFAULT 'CASH',
            `payer_phone` VARCHAR(32) NOT NULL DEFAULT '',
            `recorded_by_employee` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `recorded_by_name` VARCHAR(128) NOT NULL DEFAULT '',
            `verified_status` VARCHAR(16) NOT NULL DEFAULT 'pending',
            `verified_by_employee` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `verified_by_name` VARCHAR(128) NOT NULL DEFAULT '',
            `verified_at` DATETIME DEFAULT NULL,
            `notes` VARCHAR(255) NOT NULL DEFAULT '',
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_payment_meta`),
            UNIQUE KEY `idx_lvh_pm_op` (`id_order_payment`),
            KEY `idx_lvh_pm_order` (`id_order`),
            KEY `idx_lvh_pm_code` (`canonical_code`),
            KEY `idx_lvh_pm_emp` (`recorded_by_employee`),
            KEY `idx_lvh_pm_ver` (`verified_status`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql2 = "CREATE TABLE IF NOT EXISTS `{$p}lvh_shift_cashup` (
            `id_cashup` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `shift_date` DATE NOT NULL,
            `shift_label` VARCHAR(32) NOT NULL DEFAULT 'Full Day',
            `id_employee` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `employee_name` VARCHAR(128) NOT NULL DEFAULT '',
            `expected_cash` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `counted_cash` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `variance_cash` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `expected_momo` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `expected_pos` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `expected_bank` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `handover_notes` TEXT,
            `supervisor_status` VARCHAR(16) NOT NULL DEFAULT 'submitted',
            `approved_by_name` VARCHAR(128) NOT NULL DEFAULT '',
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_cashup`),
            KEY `idx_lvh_cashup_date` (`shift_date`),
            KEY `idx_lvh_cashup_emp` (`id_employee`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        return Db::getInstance()->execute($sql1) && Db::getInstance()->execute($sql2);
    }

    /**
     * Check whether the current logged-in employee is Supervisor, Accountant,
     * Manager, or SuperAdmin.
     *
     * @param Context|null $context
     * @return bool
     */
    public static function canAuthorizeSupervisorActions($context = null)
    {
        if ($context === null) {
            $context = Context::getContext();
        }
        if (!$context || !isset($context->employee) || !Validate::isLoadedObject($context->employee)) {
            return false;
        }
        $idProfile = (int) $context->employee->id_profile;
        if ($idProfile === 1) {
            return true;
        }
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $profileName = strtolower((string) Db::getInstance()->getValue(
            'SELECT `name` FROM `' . _DB_PREFIX_ . 'profile_lang`
             WHERE `id_profile` = ' . $idProfile . ' AND `id_lang` = ' . $idLang
        ));
        return (strpos($profileName, 'supervisor') !== false
            || strpos($profileName, 'accountant') !== false
            || strpos($profileName, 'manager') !== false
            || strpos($profileName, 'admin') !== false);
    }
}
