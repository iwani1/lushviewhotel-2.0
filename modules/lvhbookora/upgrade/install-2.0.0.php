<?php
/**
 * Lush View Hotel - lvhbookora v2.0.0 upgrade script.
 *
 * Runs automatically when a live v1.0.0 install is upgraded through the
 * QloApps Back Office: registers the new v2 hooks, seeds any new
 * configuration keys, regenerates `.htaccess` for the `module-lvhbookora-*`
 * friendly routes, and refreshes Smarty / CCC caches.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_0($module)
{
    // 1. Register every hook added in v2 (idempotent).
    if (method_exists($module, 'registerMissingHooks')) {
        $module->registerMissingHooks();
    }

    // 2. Seed new configuration keys without overwriting customized values.
    $defaults = LvhBookoraRouteMap::configDefaults();
    foreach ($defaults as $key => $val) {
        if (Configuration::get($key) === false || Configuration::get($key) === '') {
            Configuration::updateValue($key, $val);
        }
    }

    // 3. Regenerate .htaccess so /about, /blog/{slug}, /home-3 ... resolve.
    if (method_exists('Tools', 'generateHtaccess')) {
        Tools::generateHtaccess();
    }

    // 4. Clear Smarty compile + CCC bundles so new CSS/JS load immediately.
    if (method_exists('Tools', 'clearSmartyCache')) {
        Tools::clearSmartyCache();
    }
    if (method_exists('Media', 'clearCache')) {
        Media::clearCache();
    }

    return true;
}
