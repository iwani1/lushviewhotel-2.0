<?php
/**
 * Upgrade LvhAdminClean from 0.1.0 -> 2.0.0 (complete White-Label & Ad Blocker).
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param LvhAdminClean $module
 * @return bool
 */
function upgrade_module_2_0_0($module)
{
    $module->registerHook('actionAdminLoginControllerSetMedia');
    $module->registerHook('displayAdminLogin');
    $module->registerHook('displayHeader');

    $module->applyDefaultConfig(true);
    $module->syncCoreBrandSettings(LvhAdminClean::getBrandName());
    $module->purgeAdCacheFiles();

    return true;
}
