<?php
/**
 * Lush View Hotel - single source of truth for the brand.
 *
 * Stores the hotel's name, tagline, contact details and social links as
 * Configuration keys, exposes them to every other LVH module and to the theme,
 * and publishes brand meta tags in the front-office <head>.
 *
 * Deliberately does NOT edit core files, theme templates or JavaScript: the
 * visible guest-facing brand (logo, shop name in the header, emails, PDFs) is
 * already configuration-driven in QloApps (PS_SHOP_NAME, PS_LOGO, PS_FAVICON),
 * and the front-page demo copy is CMS content editable from the back office.
 * See docs/project/06-branding-plan.md for the full surface map and for the two
 * template literals this module intentionally leaves alone.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author    Lush View Hotel <info@lushviewhotel.net>
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhBranding extends Module
{
    const KEY_NAME        = 'LVH_BRAND_NAME';
    const KEY_TAGLINE     = 'LVH_BRAND_TAGLINE';
    const KEY_EMAIL       = 'LVH_BRAND_EMAIL';
    const KEY_PHONE       = 'LVH_BRAND_PHONE';
    const KEY_ADDRESS     = 'LVH_BRAND_ADDRESS';
    const KEY_FACEBOOK    = 'LVH_BRAND_FACEBOOK';
    const KEY_X           = 'LVH_BRAND_X';
    const KEY_THEME_COLOR = 'LVH_BRAND_THEME_COLOR';

    public function __construct()
    {
        $this->name = 'lvhbranding';
        $this->tab = 'administration';
        $this->version = '0.1.0';
        $this->author = 'Lush View Hotel';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'LVH Branding';
        $this->description = 'Single source of truth for the Lush View Hotel brand: name, tagline, contact details and social links, shared by every LVH module.';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('displayHeader')) {
            return false;
        }

        // Seed the two keys the core already owns; everything else starts empty
        // and is filled in from the back office (task B2 in
        // docs/project/06-branding-plan.md). No contact details are invented
        // here: this module is meant to be the source of truth, so it must only
        // ever hold verified values. Existing values survive a reinstall.
        $defaults = array(
            self::KEY_NAME        => (string) Configuration::get('PS_SHOP_NAME'),
            self::KEY_TAGLINE     => '',
            self::KEY_EMAIL       => (string) Configuration::get('PS_SHOP_EMAIL'),
            self::KEY_PHONE       => '',
            self::KEY_ADDRESS     => '',
            self::KEY_FACEBOOK    => '',
            self::KEY_X           => '',
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
        foreach (array(self::KEY_NAME, self::KEY_TAGLINE, self::KEY_EMAIL, self::KEY_PHONE,
                     self::KEY_ADDRESS, self::KEY_FACEBOOK, self::KEY_X, self::KEY_THEME_COLOR) as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /**
     * Read a brand value from anywhere: LvhBranding::brand('PHONE').
     *
     * @param string $short   suffix of the Configuration key, e.g. 'NAME'
     * @param string $default returned when the key is unset or empty
     *
     * @return string
     */
    public static function brand($short, $default = '')
    {
        $value = Configuration::get('LVH_BRAND_' . $short);

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
            $email = (string) Tools::getValue(self::KEY_EMAIL);
            if ($email !== '' && !Validate::isEmail($email)) {
                $output .= $this->displayError('The contact email address is not valid.');
            } else {
                foreach (array(self::KEY_NAME, self::KEY_TAGLINE, self::KEY_EMAIL, self::KEY_PHONE,
                             self::KEY_ADDRESS, self::KEY_FACEBOOK, self::KEY_X, self::KEY_THEME_COLOR) as $key) {
                    Configuration::updateValue($key, (string) Tools::getValue($key));
                }
                $output .= $this->displayConfirmation('Brand settings saved.');
            }
        }

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $fields = array(
            self::KEY_NAME        => 'Brand name (guest-facing)',
            self::KEY_TAGLINE     => 'Tagline',
            self::KEY_EMAIL       => 'Contact email',
            self::KEY_PHONE       => 'Contact phone',
            self::KEY_ADDRESS     => 'Postal / street address',
            self::KEY_FACEBOOK    => 'Facebook URL (optional)',
            self::KEY_X           => 'X / Twitter URL (optional)',
            self::KEY_THEME_COLOR => 'Theme colour (hex, optional)',
        );

        $html  = '<div class="panel">';
        $html .= '<h3><i class="icon-star"></i> ' . $this->displayName . '</h3>';
        $html .= '<p>These values are the single source of truth for every LVH module '
               . '(emails, reports, guest messages). The header logo, shop name in mails '
               . 'and PDFs come from the core settings under <strong>Preferences &gt; General</strong> '
               . 'and <strong>Appearance</strong>; the front-page copy is CMS content under '
               . '<strong>Preferences &gt; CMS</strong>. See docs/project/06-branding-plan.md.</p>';
        $action = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $html .= '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<table class="table table-striped">';
        foreach ($fields as $key => $label) {
            $html .= '<tr><th style="width:30%">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th>'
                   . '<td><input type="text" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" '
                   . 'value="' . htmlspecialchars((string) Configuration::get($key), ENT_QUOTES, 'UTF-8') . '" '
                   . 'class="form-control fixed-width-xxl" /></td></tr>';
        }
        $html .= '</table>';
        $html .= '<button type="submit" name="submitLvhBranding" class="btn btn-primary">'
               . 'Save brand settings</button>';
        $html .= '</form></div>';

        return $html;
    }
}
