<?php
/**
 * Lush View Hotel - lvhbookora v2.1.0 upgrade script.
 *
 * Adds the Hotel Photo Library (real hotel photo uploads sized exactly for
 * the storefront) and aligns the homepage with the original Bookora Home 2
 * template structure.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_1_0($module)
{
    // 1. Create the photo uploads directory (with an index.php guard).
    if (method_exists('LvhBookoraImageManager', 'uploadDir')) {
        LvhBookoraImageManager::uploadDir();
    }

    // 2. Register any hook added since install (idempotent).
    if (method_exists($module, 'registerMissingHooks')) {
        $module->registerMissingHooks();
    }

    // 3. Refresh .htaccess for the module friendly routes.
    if (method_exists('Tools', 'generateHtaccess')) {
        Tools::generateHtaccess();
    }

    // 4. Clear Smarty compile + CCC bundles so the new CSS/JS load immediately.
    if (method_exists('Tools', 'clearSmartyCache')) {
        Tools::clearSmartyCache();
    }
    if (method_exists('Media', 'clearCache')) {
        Media::clearCache();
    }

    return true;
}
