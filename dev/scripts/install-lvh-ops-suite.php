<?php
/**
 * Lush View Hotel - 1-Command CLI Installer for the Operational Module Suite.
 *
 * Installs and activates all 6 custom Lush View Hotel operational modules in
 * order on whichever QloApps database `config/settings.inc.php` currently
 * points to (`qloapps_18` local rehearsal or production 1.8.0 cutover):
 *
 *   1. lvhauditlog     (Staff Action & Security Audit Trail + Lush View Ops menu)
 *   2. lvhpayments     (Canonical Ghana Payments, MoMo Ref Guard, Thermal/A4 Receipts, Shift Cash-Up)
 *   3. lvhhousekeeping (34-Room Clean/Dirty/OOO Board + Auto Check-Out Dirty Trigger)
 *   4. lvhfrontdesk    (1-Screen 34-Room Rack, 30s Walk-In & Instant Check-In, Ghana Card ID, Folio Extras)
 *   5. lvhconference   (Shared Conference Hall Hourly Scheduler — 1h/2h/3h/24h/Custom + Overlap Guard)
 *   6. lvhreports      (True 34-Room Flash, Normalized Tender Reconciliation, VIPs & Hygiene Console)
 *
 * USAGE:
 *   podman-compose exec app php dev/scripts/install-lvh-ops-suite.php
 *   podman-compose exec app php dev/scripts/install-lvh-ops-suite.php --apply-hygiene
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$root = dirname(__DIR__, 2);
if (!is_file($root . '/config/settings.inc.php')) {
    exit("error: config/settings.inc.php not found.\n");
}

if (!defined('_PS_ADMIN_DIR_')) {
    define('_PS_ADMIN_DIR_', $root . '/admin-dev');
}

require_once $root . '/config/config.inc.php';

$args = array_slice($argv, 1);
$applyHygiene = in_array('--apply-hygiene', $args, true);

// Ensure employee context is SuperAdmin (#1) so Tab::initAccess grants full creator rights
$ctx = Context::getContext();
$emp = new Employee(1);
if (Validate::isLoadedObject($emp)) {
    $ctx->employee = $emp;
}

$suite = array(
    'lvhauditlog',
    'lvhfrontdesk',
    'lvhpayments',
    'lvhhousekeeping',
    'lvhconference',
    'lvhreports',
);

echo "=== Lush View Hotel Operational Suite Installer ===\n";
echo 'Database: ' . _DB_NAME_ . ' (QloApps ' . _PS_VERSION_ . ")\n\n";

foreach ($suite as $modName) {
    if (Module::isInstalled($modName)) {
        echo sprintf("  [SKIP] %-16s already installed (ensuring enabled)\n", $modName);
        $inst = Module::getInstanceByName($modName);
        if ($inst) {
            $inst->enable();
        }
        continue;
    }

    $inst = Module::getInstanceByName($modName);
    if (!$inst) {
        fwrite(STDERR, sprintf("  [FAIL] %-16s could not be instantiated\n", $modName));
        exit(1);
    }

    if (!$inst->install()) {
        fwrite(STDERR, sprintf("  [FAIL] %-16s install() returned false\n", $modName));
        exit(1);
    }
    echo sprintf("  [OK]   %-16s v%s installed & role permissions configured\n", $modName, $inst->version);
}

if ($applyHygiene) {
    echo "\nApplying confirmed Lush View Hotel live hygiene remediations...\n";
    $p = _DB_PREFIX_;

    // 1. Deactivate unused US/foreign taxes while keeping flat all-inclusive GHS room rates
    Db::getInstance()->execute("UPDATE `{$p}tax` SET `active` = 0 WHERE `active` = 1");
    Db::getInstance()->execute("UPDATE `{$p}tax_rules_group` SET `active` = 0 WHERE `active` = 1");
    echo "  [OK] Deactivated unused foreign/US state taxes (flat GHS room tariffs preserved)\n";

    // 2. Disable internal admin order-confirmation email spam (fixes 550 Mailbox Full / inode exhaustion)
    Configuration::updateValue('PS_ORDER_CONF_MAIL_TO_EMPLOYEE', 0);
    Configuration::updateValue('PS_ORDER_CONF_MAIL_TO_HOTEL_MANAGER', 0);
    Configuration::updateValue('PS_ORDER_CONF_MAIL_TO_SUPERADMIN', 0);
    echo "  [OK] Disabled internal order_conf_admin email copies (guest emails untouched)\n";

    // 3. Purge orphan `cashpayment` DB rows & disable vulnerable `qlohotelreview`
    if (!is_dir(_PS_MODULE_DIR_ . 'cashpayment')) {
        $idOrphan = (int) Db::getInstance()->getValue("SELECT `id_module` FROM `{$p}module` WHERE `name` = 'cashpayment'");
        if ($idOrphan > 0) {
            Db::getInstance()->delete('module_shop', '`id_module` = ' . $idOrphan);
            Db::getInstance()->delete('module_group', '`id_module` = ' . $idOrphan);
            Db::getInstance()->delete('hook_module', '`id_module` = ' . $idOrphan);
            Db::getInstance()->delete('module', '`id_module` = ' . $idOrphan);
            echo "  [OK] Purged orphan cashpayment DB rows (id_module={$idOrphan})\n";
        }
    }
    if (Module::isInstalled('qlohotelreview')) {
        $rev = Module::getInstanceByName('qlohotelreview');
        if ($rev) {
            $rev->disable();
        } else {
            Db::getInstance()->update('module', array('active' => 0), "`name` = 'qlohotelreview'");
        }
        echo "  [OK] Disabled vulnerable qlohotelreview module\n";
    }

    // 4. Unpublish legacy CON-1..CON-4 fake bedroom products from online bedroom search
    $conProds = Db::getInstance()->executeS(
        "SELECT DISTINCT `id_product` FROM `{$p}htl_room_information` WHERE `room_num` LIKE 'CON-%'"
    );
    if (is_array($conProds) && count($conProds) > 0) {
        foreach ($conProds as $cp) {
            $idP = (int) $cp['id_product'];
            Db::getInstance()->update('product', array('active' => 0), '`id_product` = ' . $idP);
            Db::getInstance()->update('product_shop', array('active' => 0), '`id_product` = ' . $idP);
        }
        echo "  [OK] Unpublished CON-1..CON-4 from bedroom search (shared Conference Hall active)\n";
    }

    // 5. Set Lush View Hotel shop title & clear QloApps placeholder social URLs
    Configuration::updateValue('PS_SHOP_NAME', 'Lush View Hotel');
    Configuration::updateValue('WK_TITLE_HEADER_BLOCK', 'Lush View Hotel');
    foreach (array('BLOCKSOCIAL_FACEBOOK', 'BLOCKSOCIAL_TWITTER', 'BLOCKSOCIAL_RSS', 'BLOCKSOCIAL_YOUTUBE', 'BLOCKSOCIAL_GOOGLE_PLUS', 'BLOCKSOCIAL_PINTEREST', 'BLOCKSOCIAL_VIMEO', 'BLOCKSOCIAL_INSTAGRAM') as $sk) {
        $val = (string) Configuration::get($sk);
        if (stripos($val, 'qloapps') !== false || stripos($val, 'prestashop') !== false) {
            Configuration::updateValue($sk, '');
        }
    }
    echo "  [OK] Applied Lush View Hotel brand titles & cleared placeholder social links\n";
}

// Order menu items ergonomically inside 'Lush View Ops'
$desiredOrder = array(
    'AdminLvhFrontDesk',
    'AdminLvhPayments',
    'AdminLvhHousekeeping',
    'AdminLvhConference',
    'AdminLvhReports',
    'AdminLvhAuditLog',
);
foreach ($desiredOrder as $pos => $cls) {
    $idTab = (int) Tab::getIdFromClassName($cls);
    if ($idTab > 0) {
        Db::getInstance()->update('tab', array('position' => (int) $pos), '`id_tab` = ' . $idTab);
    }
}

// Clear class index cache so new AdminControllers resolve immediately
if (is_file($root . '/cache/class_index.php')) {
    @unlink($root . '/cache/class_index.php');
}

echo "\nDone! Open Back Office -> 'Lush View Ops' in the left sidebar.\n";
