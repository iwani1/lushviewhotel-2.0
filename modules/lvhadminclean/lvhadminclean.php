<?php
/**
 * Lush View Hotel - Complete Ad Blocker & White-Label Rebranding Module.
 *
 * Compatible with BOTH QloApps 1.7.x (live lushviewhotel.net) and QloApps 1.8.x
 * with zero edits to core application or theme files:
 *
 *   1. Blocks all QloApps/Webkul advertising banners (including the Google Hotel
 *      Connector promo carousel on Dashboard & Modules Catalog), deletes cached
 *      ad HTML, and aborts background phone-home calls (`getRecommendationContent`,
 *      `recommendationClosed`, `checkUpgrade`) to `api.qloapps.com`.
 *   2. Removes top-bar "Explore QloApps Addons", toolbar "Recommendations",
 *      Dashboard "QLOAPPS UPDATE" and "HELP CENTER" panels (expanding the main
 *      Dashboard operations/revenue grid cleanly across the screen), and Addons
 *      upsell badges/forms.
 *   3. Replaces the top-left QloApps logo & version string, Back-Office Footer,
 *      Back-Office Login screen (`AdminLogin`), Guest Website Footer ("Powered by
 *      QloApps"), `<title>` tags, and all visible "QloApps" / "Webkul" text with
 *      "Lush View Hotel".
 *   4. Synchronizes `PS_SHOP_NAME`, `WK_HTL_CHAIN_NAME`, and `WK_TITLE_HEADER_BLOCK`
 *      to "Lush View Hotel" and clears default `qloapps` social placeholder URLs.
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
    const KEY_BRAND_NAME   = 'LVH_WL_BRAND_NAME';
    const KEY_BLOCK_BANNER = 'LVH_ADS_BLOCK_BANNER';
    const KEY_HIDE_HELP    = 'LVH_ADS_HIDE_HELP_PANEL';
    const KEY_REPLACE_TEXT = 'LVH_WL_REPLACE_TEXT';
    const KEY_HIDE_DEMO    = 'LVH_WL_HIDE_DEMO_BTN';
    const KEY_STRIP_ADDONS = 'LVH_ADS_STRIP_ADDONS_LINK';

    /** Cached ad and upgrade files written by core `addonsRequest()`. */
    protected static $adCacheFiles = array(
        '/cache/dashboard_recommendation.html',
        '/cache/catalog_recommendation.html',
        '/config/xml/upgrade_available.xml',
    );

    public function __construct()
    {
        $this->name = 'lvhadminclean';
        $this->tab = 'administration';
        $this->version = '2.0.1';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH White-Label & Ad Blocker');
        $this->description = $this->l('Blocks all QloApps ads & phone-home banners, removes Addons/Update/Help clutter, and replaces all QloApps/Webkul text & logos with Lush View Hotel.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install hooks, apply white-label defaults, sync core shop name, and purge ad caches.
     *
     * @return bool
     */
    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('actionAdminLoginControllerSetMedia')
            || !$this->registerHook('displayAdminLogin')
            || !$this->registerHook('displayHeader')
        ) {
            return false;
        }

        $this->applyDefaultConfig(true);
        $this->syncCoreBrandSettings(self::getBrandName());
        $this->purgeAdCacheFiles();

        return true;
    }

    /**
     * Clean up module-owned configuration keys on uninstall.
     *
     * @return bool
     */
    public function uninstall()
    {
        $keys = array(
            self::KEY_BRAND_NAME,
            self::KEY_BLOCK_BANNER,
            self::KEY_HIDE_HELP,
            self::KEY_REPLACE_TEXT,
            self::KEY_HIDE_DEMO,
            self::KEY_STRIP_ADDONS,
        );
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /**
     * Initialize or upgrade default configuration flags.
     *
     * @param bool $forceEnableWhiteLabel
     */
    public function applyDefaultConfig($forceEnableWhiteLabel = false)
    {
        $defaults = array(
            self::KEY_BRAND_NAME   => 'Lush View Hotel',
            self::KEY_BLOCK_BANNER => '1',
            self::KEY_HIDE_HELP    => '1',
            self::KEY_REPLACE_TEXT => '1',
            self::KEY_HIDE_DEMO    => '0',
            self::KEY_STRIP_ADDONS => '1',
        );

        foreach ($defaults as $key => $val) {
            if (Configuration::get($key) === false || ($forceEnableWhiteLabel && $key === self::KEY_HIDE_HELP)) {
                Configuration::updateValue($key, $val);
            }
        }
    }

    /**
     * Synchronize QloApps core shop & header brand titles and clear `qloapps` social placeholders.
     *
     * @param string $brandName
     */
    public function syncCoreBrandSettings($brandName)
    {
        $brandName = trim((string) $brandName);
        if ($brandName === '') {
            $brandName = 'Lush View Hotel';
        }

        Configuration::updateValue('PS_SHOP_NAME', $brandName);
        Configuration::updateValue('WK_HTL_CHAIN_NAME', $brandName);
        Configuration::updateValue('WK_TITLE_HEADER_BLOCK', $brandName);

        // Multi-language fallback in case a module reads language-indexed keys
        $langs = Language::getLanguages(false);
        if (is_array($langs)) {
            $multi = array();
            foreach ($langs as $l) {
                $multi[(int) $l['id_lang']] = $brandName;
            }
            Configuration::updateValue('WK_HTL_CHAIN_NAME', $multi);
            Configuration::updateValue('WK_TITLE_HEADER_BLOCK', $multi);
        }

        // Remove placeholder QloApps social URLs if untouched
        $socialKeys = array(
            'BLOCKSOCIAL_FACEBOOK',
            'BLOCKSOCIAL_TWITTER',
            'BLOCKSOCIAL_RSS',
            'BLOCKSOCIAL_YOUTUBE',
            'BLOCKSOCIAL_GOOGLE_PLUS',
            'BLOCKSOCIAL_PINTEREST',
            'BLOCKSOCIAL_VIMEO',
            'BLOCKSOCIAL_INSTAGRAM',
        );
        foreach ($socialKeys as $sk) {
            $cur = (string) Configuration::get($sk);
            if ($cur !== '' && (stripos($cur, 'qloapps') !== false || stripos($cur, 'prestashop') !== false || stripos($cur, 'webkul') !== false)) {
                Configuration::updateValue($sk, '');
            }
        }
    }

    /**
     * Delete any cached remote ad banners or upgrade XML notices.
     */
    public function purgeAdCacheFiles()
    {
        foreach (self::$adCacheFiles as $relPath) {
            $full = _PS_ROOT_DIR_ . $relPath;
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    /**
     * Resolve configured hotel brand name.
     *
     * @return string
     */
    public static function getBrandName()
    {
        $b = trim((string) Configuration::get(self::KEY_BRAND_NAME));
        return $b !== '' ? $b : 'Lush View Hotel';
    }

    /**
     * Resolve custom hotel logo URL if a real non-default logo exists in `/img/`.
     *
     * @return string
     */
    protected function getCustomLogoUrl()
    {
        $logo = trim((string) Configuration::get('PS_LOGO'));
        if ($logo === '' || preg_match('/^logo\.(jpg|jpeg|png|gif)$/i', $logo)) {
            return '';
        }
        if (defined('_PS_IMG_DIR_') && is_file(_PS_IMG_DIR_ . $logo)) {
            return _PS_IMG_ . $logo;
        }
        return '';
    }

    /**
     * Build JS configuration payload shared by Back-Office and Login pages.
     *
     * @return array
     */
    protected function getJsConfig()
    {
        $this->applyDefaultConfig(false);

        return array(
            'brand' => self::getBrandName(),
            'blockAds' => (bool) Configuration::get(self::KEY_BLOCK_BANNER),
            'hideHelpAndUpdate' => (bool) Configuration::get(self::KEY_HIDE_HELP),
            'hideDemoBtn' => (bool) Configuration::get(self::KEY_HIDE_DEMO),
            'replaceText' => (bool) Configuration::get(self::KEY_REPLACE_TEXT),
            'customLogoUrl' => $this->getCustomLogoUrl(),
        );
    }

    /**
     * Inject CSS + JS into `<head>` on every Back-Office screen.
     *
     * Inline injection inside `displayBackOfficeHeader` guarantees execution AFTER
     * jQuery & `js/admin.js` and BEFORE inline `loadRecommendation()` calls in `<body>`,
     * and works immediately even before module upgrade scripts run.
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        $cfg = $this->getJsConfig();
        if ($cfg['blockAds']) {
            $this->purgeAdCacheFiles();
        }

        $cssPath = dirname(__FILE__) . '/views/css/lvh-whitelabel-bo.css';
        $jsPath  = dirname(__FILE__) . '/views/js/lvh-whitelabel-bo.js';

        $css = is_file($cssPath) ? file_get_contents($cssPath) : '';
        if (!$cfg['replaceText']) {
            // If white-label text replacement is turned off, keep only ad-blocking rules
            $css = '#recommendation-wrapper,#recommendation-wrapper-skeleton,.alert-addons{display:none !important;}';
        }

        $js = is_file($jsPath) ? file_get_contents($jsPath) : '';

        return '<style type="text/css">' . $css . '</style>' . "\n"
            . '<script type="text/javascript">window.LVH_WHITELABEL_CFG = ' . json_encode($cfg) . ";\n"
            . $js . '</script>' . "\n";
    }

    /**
     * Rebrand the Back-Office Login page (`AdminLogin`) via media assets.
     */
    public function hookActionAdminLoginControllerSetMedia()
    {
        $cfg = $this->getJsConfig();
        Media::addJsDef(array('LVH_WHITELABEL_CFG' => $cfg));
        if (isset($this->context->controller)) {
            $this->context->controller->addCSS($this->_path . 'views/css/lvh-whitelabel-bo.css', 'all');
            $this->context->controller->addJS($this->_path . 'views/js/lvh-whitelabel-bo.js');
        }
    }

    /**
     * Fallback inline white-label payload on `AdminLogin` (covers 1.7.x & 1.8.x).
     *
     * @return string
     */
    public function hookDisplayAdminLogin()
    {
        return $this->hookDisplayBackOfficeHeader(array());
    }

    /**
     * Clean Guest-Facing Website Footer ("| Powered by QloApps") & titles on 1.7.x and 1.8.x.
     *
     * @return string
     */
    public function hookDisplayHeader()
    {
        if (!Configuration::get(self::KEY_REPLACE_TEXT)) {
            return '';
        }
        $brand = self::getBrandName();
        $foJsPath = dirname(__FILE__) . '/views/js/lvh-whitelabel-fo.js';
        $foJs = is_file($foJsPath) ? file_get_contents($foJsPath) : '';

        return '<style type="text/css">.copyRightWrapper a[href*="qloapps.com"], .copyRight a[href*="qloapps.com"] { display: none !important; }</style>' . "\n"
            . '<script type="text/javascript">window.LVH_FO_BRAND = ' . json_encode($brand) . ";\n"
            . $foJs . '</script>' . "\n";
    }

    /**
     * Module configuration screen (`Modules -> LVH White-Label & Ad Blocker -> Configure`).
     *
     * @return string
     */
    public function getContent()
    {
        $this->applyDefaultConfig(false);
        $output = '';

        if (Tools::isSubmit('submitLvhAdminClean')) {
            $brandName = trim((string) Tools::getValue(self::KEY_BRAND_NAME, 'Lush View Hotel'));
            if ($brandName === '') {
                $brandName = 'Lush View Hotel';
            }

            Configuration::updateValue(self::KEY_BRAND_NAME, $brandName);
            Configuration::updateValue(self::KEY_BLOCK_BANNER, Tools::getValue(self::KEY_BLOCK_BANNER) ? '1' : '0');
            Configuration::updateValue(self::KEY_HIDE_HELP, Tools::getValue(self::KEY_HIDE_HELP) ? '1' : '0');
            Configuration::updateValue(self::KEY_REPLACE_TEXT, Tools::getValue(self::KEY_REPLACE_TEXT) ? '1' : '0');
            Configuration::updateValue(self::KEY_HIDE_DEMO, Tools::getValue(self::KEY_HIDE_DEMO) ? '1' : '0');
            Configuration::updateValue(self::KEY_STRIP_ADDONS, '1');

            if (Configuration::get(self::KEY_REPLACE_TEXT)) {
                $this->syncCoreBrandSettings($brandName);
            }
            $this->purgeAdCacheFiles();

            $output .= $this->displayConfirmation($this->l('White-label & ad-blocker settings saved, shop titles updated, and cached ads purged.'));
        }

        $action = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $brandVal = htmlspecialchars(self::getBrandName(), ENT_QUOTES, 'UTF-8');

        $toggles = array(
            self::KEY_BLOCK_BANNER => array(
                'label' => $this->l('Block all promotional banners, Addons links & phone-home requests'),
                'help'  => $this->l('Removes the Dashboard & Modules Catalog ad carousels (e.g. Google Hotel Connector banner), top-bar "Explore QloApps Addons", toolbar "Recommendations", and aborts background requests to api.qloapps.com.'),
            ),
            self::KEY_HIDE_HELP => array(
                'label' => $this->l('Hide Dashboard "QloApps Update" & "Help Center" right-hand boxes'),
                'help'  => $this->l('Removes the right-column Update & Help Center boxes on the Dashboard and expands Operations Today & Revenue across the full width.'),
            ),
            self::KEY_REPLACE_TEXT => array(
                'label' => $this->l('Replace all QloApps / Webkul logos & text with Brand Name'),
                'help'  => $this->l('Rebrands the top-left header bar, browser tab titles, Back-Office login screen, Back-Office footer, Guest Website copyright footer, and all visible "QloApps" / "Webkul" text.'),
            ),
            self::KEY_HIDE_DEMO => array(
                'label' => $this->l('Hide Dashboard "Demo mode" button'),
                'help'  => $this->l('Optional: hides the Demo mode switch on the Dashboard so staff never toggle fake demo figures by mistake.'),
            ),
        );

        $html  = '<div class="panel">';
        $html .= '<h3><i class="icon-shield"></i> ' . htmlspecialchars($this->displayName, ENT_QUOTES, 'UTF-8') . '</h3>';
        $html .= '<p class="text-muted">' . $this->l('100% module-based white-labelling and ad blocking — zero core or theme files are modified, so upgrades stay clean.') . '</p>';
        $html .= '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="form-group" style="max-width: 420px; margin-bottom: 18px;">';
        $html .= '<label><strong>' . $this->l('Hotel Brand Name') . '</strong></label>';
        $html .= '<input type="text" name="' . self::KEY_BRAND_NAME . '" value="' . $brandVal . '" class="form-control" required="required" />';
        $html .= '<p class="help-block">' . $this->l('Displayed in the top bar, login screen, page titles, website header/footer, and wherever QloApps text appeared.') . '</p>';
        $html .= '</div>';

        $html .= '<table class="table table-striped">';
        foreach ($toggles as $key => $meta) {
            $checked = Configuration::get($key) ? ' checked="checked"' : '';
            $html .= '<tr><td><label style="font-weight:600;"><input type="checkbox" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="1"'
                . $checked . ' /> ' . htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') . '</label>'
                . '<p class="help-block" style="margin:4px 0 0;">' . htmlspecialchars($meta['help'], ENT_QUOTES, 'UTF-8') . '</p></td></tr>';
        }
        $html .= '</table>';
        $html .= '<button type="submit" name="submitLvhAdminClean" class="btn btn-primary"><i class="icon-save"></i> ' . $this->l('Save & Apply White-Label') . '</button>';
        $html .= '</form></div>';

        return $output . $html;
    }
}
