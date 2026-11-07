<?php
/**
 * Lush View Hotel - brand read API and brand meta tags.
 *
 * QloApps already stores almost every brand value a hotel needs, on one page
 * (back office -> Hotel General Configuration -> "Website Contact Details"):
 * PS_SHOP_NAME, PS_SHOP_EMAIL, PS_SHOP_PHONE, PS_SHOP_ADDR1/ADDR2/CODE,
 * PS_SHOP_DETAILS and WK_HTL_ESTABLISHMENT_YEAR. The logo, mobile logo and mail
 * logo are on the appearance page (PS_LOGO, PS_LOGO_MOBILE, PS_LOGO_MAIL), and
 * the footer social icons belong to the blocksocial module (BLOCKSOCIAL_*).
 *
 * So this module deliberately stores almost nothing. It does three jobs:
 *
 *   1. LvhBranding::brand('PHONE') - one read API for later LVH modules
 *      (emails, reports, guest messages) that resolves to the core key which
 *      really owns the value, so no brand datum exists in two places and can
 *      drift.
 *   2. The two things the core has no home for: a tagline (the legacy
 *      WK_HTL_TAG_LINE key is only listed in deleteConfigVars() and is never
 *      read or rendered in 1.8.0) and a theme colour.
 *   3. Brand meta tags in the front-office <head> - theme-color and
 *      og:site_name - which no core template emits.
 *
 * Nothing here edits core files, theme templates or JavaScript. The full
 * surface map, the live-site audit and the decisions behind this design are in
 * docs/project/06-branding-plan.md.
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

class LvhBranding extends Module
{
    /** Keys this module owns, because the core has nowhere to put them. */
    const KEY_TAGLINE     = 'LVH_BRAND_TAGLINE';
    const KEY_THEME_COLOR = 'LVH_BRAND_THEME_COLOR';

    /**
     * Brand surface => the core Configuration key that owns it.
     *
     * Read-only on purpose: these are edited on the core pages, not here, so
     * there is exactly one copy of each value in the database.
     *
     * @var array
     */
    protected static $coreKeys = array(
        'NAME'    => 'PS_SHOP_NAME',
        'EMAIL'   => 'PS_SHOP_EMAIL',
        'PHONE'   => 'PS_SHOP_PHONE',
        'ADDRESS' => 'PS_SHOP_ADDR1',
    );

    public function __construct()
    {
        $this->name = 'lvhbranding';
        $this->tab = 'administration';
        $this->version = '0.3.0';
        $this->author = 'Lush View Hotel';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'LVH Branding';
        $this->description = 'Brand read API for LVH modules, plus the tagline, theme colour and brand meta tags. Reads the core settings instead of duplicating them.';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('displayHeader')) {
            return false;
        }

        // Only the two LVH-owned keys. Existing values survive a reinstall.
        $defaults = array(
            self::KEY_TAGLINE     => '',
            self::KEY_THEME_COLOR => '#252525',
        );
        foreach ($defaults as $key => $value) {
            if (Configuration::get($key) === false) {
                Configuration::updateValue($key, $value);
            }
        }

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        Configuration::deleteByName(self::KEY_TAGLINE);
        Configuration::deleteByName(self::KEY_THEME_COLOR);

        return true;
    }

    /**
     * Read a brand value from anywhere: LvhBranding::brand('PHONE').
     *
     * Resolves NAME, EMAIL, PHONE and ADDRESS to the core key that owns them,
     * and TAGLINE / THEME_COLOR to this module's own keys.
     *
     * @param string $short   brand surface, e.g. 'NAME', 'PHONE', 'TAGLINE'
     * @param string $default returned when the value is unset or empty
     *
     * @return string
     */
    public static function brand($short, $default = '')
    {
        $short = strtoupper((string) $short);
        $key = isset(self::$coreKeys[$short]) ? self::$coreKeys[$short] : 'LVH_BRAND_' . $short;
        $value = Configuration::get($key);

        return ($value === false || $value === '') ? $default : (string) $value;
    }

    public function hookDisplayHeader($params)
    {
        $name  = self::brand('NAME');
        $color = self::brand('THEME_COLOR');
        $html  = '';

        if ($color !== '') {
            $html .= '<meta name="theme-color" content="' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        if ($name !== '') {
            $html .= '<meta property="og:site_name" content="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        return $html;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitLvhBranding')) {
            Configuration::updateValue(self::KEY_TAGLINE, (string) Tools::getValue(self::KEY_TAGLINE));
            Configuration::updateValue(self::KEY_THEME_COLOR, (string) Tools::getValue(self::KEY_THEME_COLOR));
            $output .= $this->displayConfirmation('Brand settings saved.');
        }

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $action = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        $html  = '<div class="panel">';
        $html .= '<h3><i class="icon-star"></i> ' . $this->displayName . '</h3>';
        $html .= '<p>Only two values live here. Everything else is read from the core settings, '
               . 'so no brand value can exist in two places and drift apart.</p>';

        // Read-only: where the rest of the brand actually lives.
        $html .= '<h4>Owned by QloApps - edit these on the core pages</h4>';
        $html .= '<table class="table"><thead><tr><th>Surface</th><th>Current value</th><th>Where to change it</th></tr></thead><tbody>';
        $coreRows = array(
            'NAME'    => 'Hotel General Configuration &rarr; Website Contact Details &rarr; Website name',
            'EMAIL'   => 'Hotel General Configuration &rarr; Website Contact Details &rarr; Website email',
            'PHONE'   => 'Hotel General Configuration &rarr; Website Contact Details &rarr; Phone',
            'ADDRESS' => 'Hotel General Configuration &rarr; Website Contact Details &rarr; Address line 1',
        );
        foreach ($coreRows as $short => $where) {
            $value = self::brand($short);
            $html .= '<tr><td>' . $short . '</td><td>'
                   . ($value === '' ? '<em>(empty)</em>' : htmlspecialchars($value, ENT_QUOTES, 'UTF-8'))
                   . '</td><td>' . $where . '</td></tr>';
        }
        $html .= '<tr><td>LOGO</td><td>&mdash;</td><td>Appearance page: PS_LOGO, PS_LOGO_MOBILE, PS_LOGO_MAIL</td></tr>';
        $html .= '<tr><td>SOCIAL</td><td>&mdash;</td><td>Modules &rarr; Social network links (blocksocial): Facebook, X, Instagram&hellip;</td></tr>';
        $html .= '</tbody></table>';

        // Editable: the two LVH-owned keys.
        $html .= '<h4>Owned by Lush View</h4>';
        $html .= '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<table class="table table-striped">';
        $fields = array(
            self::KEY_TAGLINE     => 'Tagline (the core has no tagline field in 1.8.0)',
            self::KEY_THEME_COLOR => 'Theme colour (hex, used for the theme-color meta tag)',
        );
        foreach ($fields as $key => $label) {
            $html .= '<tr><th style="width:40%">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th>'
                   . '<td><input type="text" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" '
                   . 'value="' . htmlspecialchars((string) Configuration::get($key), ENT_QUOTES, 'UTF-8') . '" '
                   . 'class="form-control fixed-width-xxl" /></td></tr>';
        }
        $html .= '</table>';
        $html .= '<button type="submit" name="submitLvhBranding" class="btn btn-primary">Save brand settings</button>';
        $html .= '</form>';
        $html .= '<p>Full surface map and live-site audit: docs/project/06-branding-plan.md</p>';
        $html .= '</div>';

        return $html;
    }
}
