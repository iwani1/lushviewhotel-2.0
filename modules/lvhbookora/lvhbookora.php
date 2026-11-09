<?php
/**
 * Lush View Hotel - Bookora Luxury Storefront Theme Module for QloApps 1.8.0.
 *
 * Replaces the stock QloApps storefront appearance with the Bookora Luxury
 * Hotel aesthetic (editorial serif typography, deep forest teal & warm gold
 * palette, frosted-glass availability search bar, dark facilities showcase,
 * live GHS room carousel, interactive offers, pricing plans, guest reviews,
 * interior gallery lightbox, and luxury dark footer) while keeping 100% of
 * QloApps 1.8.0 booking, cart, checkout, and Lush View Ops intact.
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

require_once dirname(__FILE__) . '/classes/LvhBookoraDataProvider.php';
// The photo library is optional: a partially-uploaded module must never fatal.
if (file_exists(dirname(__FILE__) . '/classes/LvhBookoraImageManager.php')) {
    require_once dirname(__FILE__) . '/classes/LvhBookoraImageManager.php';
}

class LvhBookora extends Module
{
    /** @var array|null Request-scoped common data cache */
    protected $commonCache = null;

    public function __construct()
    {
        $this->name = 'lvhbookora';
        $this->tab = 'front_office_features';
        $this->version = '1.1.0';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH Bookora Luxury Storefront Theme');
        $this->description = $this->l('Bookora luxury hotel storefront design for Lush View Hotel — live QloApps 1.8.0 availability search, GHS room showcase, facilities, offers, gallery & inner-page styling.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Default customizable Bookora copy & palette settings.
     *
     * @return array
     */
    public static function getDefaults()
    {
        return array(
            'LVH_BK_ENABLED' => '1',
            'LVH_BK_EYEBROW' => 'Welcome To Lush View Hotel',
            'LVH_BK_HERO_LINE1' => 'Your Next Booking',
            'LVH_BK_HERO_LINE2' => 'Luxury Hotel',
            'LVH_BK_HERO_SUB' => 'Experience world-class hospitality where luxury, comfort, and elegance come together for an unforgettable stay.',
            'LVH_BK_ABOUT_TITLE' => 'Experience Comfort, Elegance & Exceptional Hospitality',
            'LVH_BK_ABOUT_BODY' => 'Experience world-class comfort and elegant design combined with exceptional hospitality, ensuring every of your stay feels special & unforgettable.',
            'LVH_BK_STAT1_NUM' => '80k+',
            'LVH_BK_STAT1_LABEL' => 'Happy Customer',
            'LVH_BK_STAT2_NUM' => '34+',
            'LVH_BK_STAT2_LABEL' => 'Luxury bedrooms, suites & event hall',
            'LVH_BK_COLOR_TEAL' => '#14110d',
            'LVH_BK_COLOR_GOLD' => '#c9a063',
        );
    }

    public function install()
    {
        foreach (self::getDefaults() as $key => $val) {
            if (Configuration::get($key) === false) {
                Configuration::updateValue($key, $val);
            }
        }

        $ok = parent::install()
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBanner')
            && $this->registerHook('displayHome')
            && $this->registerHook('displayFooterBefore');

        $this->flushThemeCaches();
        return $ok;
    }

    public function uninstall()
    {
        $ok = parent::uninstall();
        $this->flushThemeCaches();
        return $ok;
    }

    public function enable($force_all = false)
    {
        $ok = parent::enable($force_all);
        $this->flushThemeCaches();
        return $ok;
    }

    public function disable($force_all = false)
    {
        $ok = parent::disable($force_all);
        $this->flushThemeCaches();
        return $ok;
    }

    /**
     * Flush Smarty compiled templates and CCC versioned CSS/JS bundles.
     */
    protected function flushThemeCaches()
    {
        if (method_exists('Tools', 'clearSmartyCache')) {
            Tools::clearSmartyCache();
        }
        if (method_exists('Media', 'clearCache')) {
            Media::clearCache();
        }
    }

    /**
     * Read current configuration merged over defaults.
     *
     * @return array
     */
    protected function getBookoraConfig()
    {
        $defaults = self::getDefaults();
        $out = array();
        foreach ($defaults as $k => $v) {
            $stored = Configuration::get($k);
            $out[$k] = ($stored !== false && $stored !== '') ? (string) $stored : $v;
        }
        return $out;
    }

    /**
     * Whether the Bookora storefront transformation is enabled.
     *
     * @return bool
     */
    protected function isBookoraActive()
    {
        $val = Configuration::get('LVH_BK_ENABLED');
        return ($val === false || (int) $val === 1);
    }

    /**
     * Shared request-cached common data.
     *
     * @return array
     */
    protected function getSharedCommon()
    {
        if ($this->commonCache === null) {
            $this->commonCache = LvhBookoraDataProvider::getCommonData(
                $this->context,
                $this->getBookoraConfig()
            );
        }
        return $this->commonCache;
    }

    /**
     * Enqueue Bookora CSS & JS through QloApps Media (CCC-compatible).
     */
    public function hookActionFrontControllerSetMedia()
    {
        if (!$this->isBookoraActive()) {
            return;
        }
        $this->context->controller->addCSS($this->_path . 'views/css/bookora.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/bookora.js');
    }

    /**
     * Inject Google Fonts (`Cormorant Garamond` + `Plus Jakarta Sans`) and dynamic CSS variables.
     *
     * @return string
     */
    public function hookDisplayHeader()
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $cfg = $this->getBookoraConfig();
        $teal = preg_match('/^#[0-9a-fA-F]{6}$/', $cfg['LVH_BK_COLOR_TEAL']) ? $cfg['LVH_BK_COLOR_TEAL'] : '#0b3b36';
        $gold = preg_match('/^#[0-9a-fA-F]{6}$/', $cfg['LVH_BK_COLOR_GOLD']) ? $cfg['LVH_BK_COLOR_GOLD'] : '#c2a275';

        $common = $this->getSharedCommon();
        Media::addJsDef(array(
            'lvhBookoraSearchUrl' => $common['search_url'],
            'lvhBookoraUseOccupancy' => (int) $common['use_occupancy_param'],
        ));

        return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
            . '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Lora:ital,wght@0,400;0,500;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n"
            . '<style>:root { --bk-teal: ' . $teal . '; --bk-gold: ' . $gold . '; }</style>' . "\n";
    }

    /**
     * Render Bookora Top Utility Bar + Sticky Main Navbar across all storefront pages.
     *
     * @return string
     */
    public function hookDisplayBanner()
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $this->context->smarty->assign(array(
            'bk' => $this->getSharedCommon(),
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-header.tpl');
    }

    /**
     * Render full 10-section Bookora Homepage on `index`.
     *
     * @return string
     */
    public function hookDisplayHome()
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $common = $this->getSharedCommon();
        $home = LvhBookoraDataProvider::getHomeData($this->context, $common);

        $this->context->smarty->assign(array(
            'bk' => $common,
            'bk_home' => $home,
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-home.tpl');
    }

    /**
     * Render Bookora Gold Ribbon + 4-Column Dark Footer + Lightbox Modal across all storefront pages.
     *
     * @return string
     */
    public function hookDisplayFooterBefore()
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $this->context->smarty->assign(array(
            'bk' => $this->getSharedCommon(),
            'bk_year' => date('Y'),
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-footer.tpl');
    }

    /**
     * Back Office configuration form to customize headlines, stats, or accent colors.
     *
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitResetBookoraDefaults')) {
            foreach (self::getDefaults() as $k => $v) {
                Configuration::updateValue($k, $v);
            }
            $this->flushThemeCaches();
            $output .= $this->displayConfirmation($this->l('Bookora defaults restored and Smart Cache refreshed.'));
        } elseif (Tools::isSubmit('submitSaveBookoraConfig')) {
            foreach (array_keys(self::getDefaults()) as $k) {
                $val = trim((string) Tools::getValue($k, ''));
                Configuration::updateValue($k, $val);
            }
            $this->flushThemeCaches();
            $output .= $this->displayConfirmation($this->l('Bookora storefront settings saved and Smart Cache refreshed.'));
        }

        if (Tools::isSubmit('submitSaveBookoraPhotos')) {
            $output .= $this->processPhotoLibrary();
        }

        if (!class_exists('LvhBookoraImageManager')) {
            $output .= $this->displayWarning($this->l('Photo library files are missing (LvhBookoraImageManager.php). Upload the complete lvhbookora module files to enable photo uploads.'));
            return $output . $this->renderConfigForm();
        }

        return $output . $this->renderPhotoLibrary() . $this->renderConfigForm();
    }

    /**
     * Process the Hotel Photo Library form: per-slot uploads and removals.
     * Every photo is cover-cropped to the exact size the site displays.
     *
     * @return string Rendered confirmation / error block
     */
    protected function processPhotoLibrary()
    {
        if (!class_exists('LvhBookoraImageManager')) {
            return $this->displayError($this->l('Photo library files are missing. Upload the complete lvhbookora module files.'));
        }
        $ok = 0;
        $err = array();

        foreach (LvhBookoraImageManager::slots() as $slot => $spec) {
            // Removal
            if (Tools::getValue('bk_remove_' . $slot)) {
                LvhBookoraImageManager::delete($slot);
                continue;
            }
            // Upload
            if (!empty($_FILES['bk_photo_' . $slot]) && is_array($_FILES['bk_photo_' . $slot])) {
                if ($_FILES['bk_photo_' . $slot]['error'] !== UPLOAD_ERR_NO_FILE) {
                    $problem = LvhBookoraImageManager::processUpload($slot, $_FILES['bk_photo_' . $slot]);
                    if ($problem === '') {
                        $ok++;
                    } else {
                        $err[] = $spec['label'] . ': ' . $problem;
                    }
                }
            }
        }

        $out = '';
        if ($ok > 0) {
            $this->flushThemeCaches();
            $out .= $this->displayConfirmation(sprintf(
                $this->l('%d photo(s) uploaded and resized to the exact size the website needs. Smart Cache refreshed.'),
                $ok
            ));
        }
        foreach ($err as $e) {
            $out .= $this->displayError($e);
        }
        if ($ok === 0 && empty($err)) {
            $out .= $this->displayConfirmation($this->l('Photo library updated.'));
        }
        return $out;
    }

    /**
     * Render the Hotel Photo Library panel (custom upload UI with live
     * previews and required dimensions per slot).
     *
     * @return string
     */
    protected function renderPhotoLibrary()
    {
        $html = '<div class="panel" id="bk-photo-library">'
            . '<div class="panel-heading"><i class="icon-picture"></i> '
            . $this->l('Hotel Photo Library — Upload Your Real Hotel Photos') . '</div>'
            . '<div class="panel-body">'
            . '<p class="help-block">'
            . $this->l('Upload your own hotel photography below. Each photo is automatically cropped to the exact size the website displays — just pick a clear shot with the right orientation and we handle the rest. JPG, PNG or WebP, max 10 MB per photo.')
            . '</p>'
            . '<form action="' . html_entity_decode((string) Tools::safeOutput($_SERVER['REQUEST_URI']), ENT_QUOTES, 'UTF-8')
            . '" method="post" enctype="multipart/form-data" class="form-horizontal">';

        foreach (LvhBookoraImageManager::slots() as $slot => $spec) {
            $current = LvhBookoraImageManager::url($this->context, $slot);
            $has = LvhBookoraImageManager::hasUpload($slot);
            $html .= '<div class="row" style="border-bottom:1px solid #eee;padding:14px 0;">'
                . '<div class="col-lg-2 col-md-2 text-center">'
                . '<img src="' . htmlspecialchars($current, ENT_QUOTES, 'UTF-8') . '" alt="" '
                . 'style="max-width:100%;max-height:110px;border-radius:6px;border:1px solid #ddd;object-fit:cover;">'
                . '</div>'
                . '<div class="col-lg-4 col-md-4">'
                . '<strong>' . htmlspecialchars($spec['label'], ENT_QUOTES, 'UTF-8') . '</strong>'
                . '<p class="help-block" style="margin:4px 0 0;">' . htmlspecialchars($spec['help'], ENT_QUOTES, 'UTF-8') . '</p>'
                . '</div>'
                . '<div class="col-lg-2 col-md-2">'
                . '<span class="label label-info" style="font-size:12px;">'
                . (int) $spec['w'] . ' × ' . (int) $spec['h'] . ' px</span>'
                . '<p class="help-block" style="margin:4px 0 0;">' . $this->l('Exact output size') . '</p>'
                . '</div>'
                . '<div class="col-lg-4 col-md-4">'
                . '<input type="file" name="bk_photo_' . htmlspecialchars($slot, ENT_QUOTES, 'UTF-8')
                . '" accept=".jpg,.jpeg,.png,.webp,image/*">'
                . ($has
                    ? '<label style="font-weight:normal;margin-top:6px;"><input type="checkbox" name="bk_remove_'
                      . htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') . '" value="1"> '
                      . $this->l('Remove — restore the default photo') . '</label>'
                    : '')
                . '</div>'
                . '</div>';
        }

        $html .= '<div class="panel-footer" style="margin-top:14px;">'
            . '<button type="submit" name="submitSaveBookoraPhotos" class="btn btn-primary">'
            . '<i class="process-icon-save"></i> ' . $this->l('Save Photo Library') . '</button>'
            . '</div>'
            . '</form>'
            . '<p class="help-block" style="margin-top:10px;">'
            . $this->l('Tip: room & suite catalogue photos are managed separately in Rooms → each room type, so prices and availability stay in sync with the booking engine.')
            . '</p>'
            . '</div></div>';

        return $html;
    }

    /**
     * Build Back Office HelperForm.
     *
     * @return string
     */
    protected function renderConfigForm()
    {
        $fieldsForm = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Bookora Luxury Storefront Settings'),
                    'icon' => 'icon-PaintBrush',
                ),
                'description' => $this->l('All room types, GHS prices, room photos, interior gallery photos, and guest testimonials are pulled automatically from your live QloApps catalog. Use the fields below to fine-tune headlines and brand colors.'),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable Bookora Storefront'),
                        'name' => 'LVH_BK_ENABLED',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'bk_on', 'value' => 1, 'label' => $this->l('Yes')),
                            array('id' => 'bk_off', 'value' => 0, 'label' => $this->l('No')),
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Hero Eyebrow Tag'),
                        'name' => 'LVH_BK_EYEBROW',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Hero Headline (Line 1)'),
                        'name' => 'LVH_BK_HERO_LINE1',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Hero Headline (Line 2)'),
                        'name' => 'LVH_BK_HERO_LINE2',
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Hero Panoramic Caption'),
                        'name' => 'LVH_BK_HERO_SUB',
                        'rows' => 3,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('About Section Headline'),
                        'name' => 'LVH_BK_ABOUT_TITLE',
                        'rows' => 3,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('About Section Paragraph'),
                        'name' => 'LVH_BK_ABOUT_BODY',
                        'rows' => 3,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Left Stat Value'),
                        'name' => 'LVH_BK_STAT1_NUM',
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Left Stat Caption'),
                        'name' => 'LVH_BK_STAT1_LABEL',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Middle Stat Value'),
                        'name' => 'LVH_BK_STAT2_NUM',
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Middle Stat Caption'),
                        'name' => 'LVH_BK_STAT2_LABEL',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Primary Forest Teal (#hex)'),
                        'name' => 'LVH_BK_COLOR_TEAL',
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Primary Champagne Gold (#hex)'),
                        'name' => 'LVH_BK_COLOR_GOLD',
                        'class' => 'fixed-width-md',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save & Refresh Storefront Cache'),
                    'name' => 'submitSaveBookoraConfig',
                ),
                'buttons' => array(
                    array(
                        'title' => $this->l('Restore Bookora Defaults'),
                        'name' => 'submitResetBookoraDefaults',
                        'type' => 'submit',
                        'icon' => 'process-icon-refresh',
                        'class' => 'btn btn-default pull-right',
                    ),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->fields_value = $this->getBookoraConfig();

        return $helper->generateForm(array($fieldsForm));
    }
}
