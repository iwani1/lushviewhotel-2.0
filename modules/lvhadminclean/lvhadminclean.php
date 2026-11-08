<?php
/**
 * Lush View Hotel - removes the promotional content from the back office.
 *
 * QloApps injects advertising into the admin panel: a "recommendation" banner
 * whose HTML is fetched from api.qloapps.com once a day and cached in
 * cache/dashboard_recommendation.html and cache/catalog_recommendation.html,
 * plus a "Help Center" panel that links to the QloApps Addons shop.
 *
 * The request that fetches the banner (Tools::addonsRequest, classes/Tools.php:3375)
 * also sends the shop domain, the shop email address, the QloApps version, the
 * language and the country. Blocking it therefore removes the ads AND stops
 * that data from leaving the server.
 *
 * How this module removes them, without editing a single application file:
 *
 *   1. hooks displayBackOfficeHeader, which QloApps prints inside <head>
 *      (admin-dev/themes/default/template/header.tpl:102) AFTER jQuery and
 *      admin.js are loaded (line 98-100) and BEFORE the inline
 *      loadRecommendation() call in the page body, so overriding that function
 *      here wins;
 *   2. redefines loadRecommendation()/closeRecommendation() as no-ops, so the
 *      banner HTML is never requested and never injected;
 *   3. registers a jQuery ajaxPrefilter that aborts the request anyway, in case
 *      anything else issues it;
 *   4. hides #recommendation-wrapper and its skeleton with CSS, so even a stale
 *      cached banner cannot flash on screen;
 *   5. removes the commercial "QloApps Addons" entry from the Help Center
 *      panel, keeping the genuinely useful documentation and forum links.
 *
 * Deleting the markup from the core templates instead would be a smaller diff,
 * but it edits application files and is silently reverted by every upgrade.
 * This module is disabled or uninstalled from the back office in one click.
 *
 * Inventory of every promotional surface, and what was deliberately left alone,
 * is in docs/project/07-backoffice-ads.md.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhAdminClean extends Module
{
    const KEY_BLOCK_BANNER   = 'LVH_ADS_BLOCK_BANNER';
    const KEY_STRIP_ADDONS   = 'LVH_ADS_STRIP_ADDONS_LINK';
    const KEY_HIDE_HELP      = 'LVH_ADS_HIDE_HELP_PANEL';

    /** Ad HTML cached by the core, relative to the shop root. */
    protected static $adCacheFiles = array(
        '/cache/dashboard_recommendation.html',
        '/cache/catalog_recommendation.html',
    );

    public function __construct()
    {
        $this->name = 'lvhadminclean';
        $this->tab = 'administration';
        $this->version = '0.1.0';
        $this->author = 'Lush View Hotel';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'LVH Admin Clean';
        $this->description = 'Removes the QloApps advertising banner and the Addons shop link from the back office, and stops the request that sends your domain, email and version to api.qloapps.com.';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('displayBackOfficeHeader')) {
            return false;
        }

        $defaults = array(
            self::KEY_BLOCK_BANNER => '1',
            self::KEY_STRIP_ADDONS => '1',
            self::KEY_HIDE_HELP    => '0',
        );
        foreach ($defaults as $key => $value) {
            if (Configuration::get($key) === false) {
                Configuration::updateValue($key, $value);
            }
        }

        // Generated ad cache, not application files: safe to delete, and it
        // stops a previously downloaded banner from being served once more.
        foreach (self::$adCacheFiles as $file) {
            $path = _PS_ROOT_DIR_ . $file;
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        foreach (array(self::KEY_BLOCK_BANNER, self::KEY_STRIP_ADDONS, self::KEY_HIDE_HELP) as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $blockBanner = (bool) Configuration::get(self::KEY_BLOCK_BANNER);
        $stripAddons = (bool) Configuration::get(self::KEY_STRIP_ADDONS);
        $hideHelp    = (bool) Configuration::get(self::KEY_HIDE_HELP);

        if (!$blockBanner && !$stripAddons && !$hideHelp) {
            return '';
        }

        $css = '#recommendation-wrapper,#recommendation-wrapper-skeleton{display:none !important;}';
        if ($hideHelp) {
            $css .= '.dash_links{display:none !important;}';
        }

        $js = array();
        $js[] = "(function () {";
        $js[] = "'use strict';";
        if ($blockBanner) {
            // Runs in <head>, so this replaces admin.js's loader before the
            // inline loadRecommendation() call in the page body executes.
            $js[] = "window.loadRecommendation = function () {};";
            $js[] = "window.closeRecommendation = function () {};";
        }
        if ($blockBanner || $stripAddons) {
            $js[] = "function whenJQuery(fn) {";
            $js[] = "if (window.jQuery) { fn(window.jQuery); } else { setTimeout(function () { whenJQuery(fn); }, 10); }";
            $js[] = "}";
            $js[] = "whenJQuery(function ($) {";
            if ($blockBanner) {
                $js[] = "$.ajaxPrefilter(function (options, original, jqXHR) {";
                $js[] = "var data = original && original.data, action = '';";
                $js[] = "if (data && typeof data === 'object') { action = String(data.action || ''); }";
                $js[] = "else if (data) { action = String(data); }";
                $js[] = "if (action === 'getRecommendationContent' || action === 'recommendationClosed') { jqXHR.abort(); }";
                $js[] = "});";
            }
            if ($stripAddons && !$hideHelp) {
                $js[] = "$(function () {";
                $js[] = "$('.dash_links dl').each(function () {";
                $js[] = "var href = $(this).find('dt a').attr('href') || '';";
                $js[] = "if (/qloapps\\.com\\/addons/i.test(href)) { $(this).remove(); }";
                $js[] = "});";
                $js[] = "});";
            }
            $js[] = "});";
        }
        $js[] = "})();";

        return '<style>' . $css . '</style>' . "\n"
             . '<script>' . implode("\n", $js) . '</script>' . "\n";
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitLvhAdminClean')) {
            foreach (array(self::KEY_BLOCK_BANNER, self::KEY_STRIP_ADDONS, self::KEY_HIDE_HELP) as $key) {
                Configuration::updateValue($key, Tools::getValue($key) ? '1' : '0');
            }
            foreach (self::$adCacheFiles as $file) {
                $path = _PS_ROOT_DIR_ . $file;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            $output .= $this->displayConfirmation('Settings saved and any cached advertising removed.');
        }

        $rows = array(
            self::KEY_BLOCK_BANNER => array(
                'label' => 'Block the advertising banner',
                'help'  => 'Stops the daily request to api.qloapps.com that fetches the promotional banner on the Dashboard and Modules Catalog pages, '
                         . 'and hides any copy already downloaded. That request also carries your shop domain, shop email, QloApps version, language and country.',
            ),
            self::KEY_STRIP_ADDONS => array(
                'label' => 'Remove the "QloApps Addons" shop link',
                'help'  => 'Deletes only the commercial entry from the Help Center panel. The documentation, forum and contact links stay, because they are useful.',
            ),
            self::KEY_HIDE_HELP => array(
                'label' => 'Hide the whole Help Center panel',
                'help'  => 'Removes the documentation and forum links too. Off by default.',
            ),
        );

        $action = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        $html  = '<div class="panel">';
        $html .= '<h3><i class="icon-ban-circle"></i> ' . $this->displayName . '</h3>';
        $html .= '<p>Nothing here edits a QloApps file: the changes are injected into the admin header and disappear '
               . 'the moment this module is disabled. Full inventory of every promotional surface, including the ones '
               . 'left alone and why: <code>docs/project/07-backoffice-ads.md</code>.</p>';
        $html .= '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<table class="table">';
        foreach ($rows as $key => $row) {
            $checked = Configuration::get($key) ? ' checked="checked"' : '';
            $html .= '<tr><td style="width:30%"><label><input type="checkbox" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"'
                   . $checked . ' /> ' . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</label>'
                   . '<p class="help-block">' . htmlspecialchars($row['help'], ENT_QUOTES, 'UTF-8') . '</p></td></tr>';
        }
        $html .= '</table>';
        $html .= '<button type="submit" name="submitLvhAdminClean" class="btn btn-primary">Save</button>';
        $html .= '</form></div>';

        return $output . $html;
    }
}
