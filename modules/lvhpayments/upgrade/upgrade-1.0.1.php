<?php
/**
 * Upgrade lvhpayments 1.0.0 -> 1.0.1
 * Fixes QloApps 1.8.0 hotel address/phone lookup on standalone receipt print
 * (`qlo_address.id_hotel` instead of `qlo_htl_branch_info`) and clears Smarty compile cache.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_1($module)
{
    unset($module);
    if (method_exists('Tools', 'clearSmartyCache')) {
        Tools::clearSmartyCache();
    }
    return true;
}
