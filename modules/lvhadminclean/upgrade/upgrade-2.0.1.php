<?php
/**
 * Upgrade lvhadminclean 2.0.0 -> 2.0.1
 * Fixes Bootstrap 3 push/pull alignment on AdminDashboard (#hookDashboardZoneOne col-lg-pull-9).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_1($module)
{
    $module->purgeAdCacheFiles();
    return true;
}
