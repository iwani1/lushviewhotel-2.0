<?php
/**
 * Lush View Hotel - Bookora Luxury Storefront Theme Module for QloApps 1.8.0.
 *
 * Exact Bookora "Home 2" luxury hotel storefront (https://themexriver.com/wp/bookora/home-2/)
 * for Lush View Hotel: glassmorphic Header 2 with dropdown navigation, dark
 * full-bleed hero, Popular Rooms grid, interactive Customer Services panels,
 * Featured Suites slider, 8-item Facilities grid, Check Availability booking
 * form, Our Expert team, testimonial slider, Trending Topics blog cards,
 * partner strip, Footer 2 with embedded map & working newsletter — plus every
 * standalone Bookora webpage (About, Service, Project, Team, Testimonial,
 * Pricing, Blog, Blog Article, Home 1, Home 3, Bookmarks) and Bookora-styled
 * native QloApps pages (All Listings, Room Detail, Contact, Sign In, My
 * Account, Cart & Checkout, CMS) — all while keeping 100% of QloApps 1.8.0
 * booking, cart, checkout, inventory, and Back Office intact.
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

require_once dirname(__FILE__) . '/classes/LvhBookoraRouteMap.php';
require_once dirname(__FILE__) . '/classes/LvhBookoraDataProvider.php';
require_once dirname(__FILE__) . '/classes/LvhBookoraImageManager.php';

class LvhBookora extends Module
{
    /** @var array|null Request-scoped common data cache */
    protected $commonCache = null;

    public function __construct()
    {
        $this->name = 'lvhbookora';
        $this->tab = 'front_office_features';
        $this->version = '2.1.0';
        $this->author = 'Lush View Hotel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('LVH Bookora Luxury Storefront Theme');
        $this->description = $this->l('Exact Bookora Home 2 luxury hotel storefront for Lush View Hotel — glassmorphic header, Home 2 hero & sections, all Bookora webpages (About, Service, Project, Team, Testimonial, Pricing, Blog, Home 1/3, Bookmarks), Bookora-styled listings/room detail/contact/account pages, live QloApps 1.8.0 GHS booking & inventory.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Default customizable Bookora copy & palette settings.
     *
     * @return array
     */
    public static function getDefaults()
    {
        return LvhBookoraRouteMap::configDefaults();
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
            && $this->registerHook('displayTopColumn')
            && $this->registerHook('displayRoomTypeDetailRoomTypeNameBlock')
            && $this->registerHook('displayColumnsBottom')
            && $this->registerHook('displayAfterHotelBranchInformation')
            && $this->registerHook('displayFooterBefore')
            && $this->registerHook('moduleRoutes');

        $this->refreshRewrites();
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
        $this->refreshRewrites();
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
     * Register any hook added after the first install (idempotent).
     */
    public function registerMissingHooks()
    {
        $hooks = array(
            'actionFrontControllerSetMedia',
            'displayHeader',
            'displayBanner',
            'displayHome',
            'displayTopColumn',
            'displayRoomTypeDetailRoomTypeNameBlock',
            'displayColumnsBottom',
            'displayAfterHotelBranchInformation',
            'displayFooterBefore',
            'moduleRoutes',
        );
        foreach ($hooks as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                $this->registerHook($hook);
            }
        }
    }

    /**
     * Regenerate `.htaccess` so `module-lvhbookora-*` friendly URLs resolve.
     */
    protected function refreshRewrites()
    {
        if (method_exists('Tools', 'generateHtaccess')) {
            Tools::generateHtaccess();
        }
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
                LvhBookoraDataProvider::getConfig()
            );
        }
        return $this->commonCache;
    }

    /**
     * Friendly URL rules for every standalone Bookora webpage.
     *
     * @return array
     */
    public function hookModuleRoutes($params)
    {
        return LvhBookoraRouteMap::routes();
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
        $this->context->controller->addCSS($this->_path . 'views/css/bookora-v2.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/bookora.js');
    }

    /**
     * Inject Google Fonts (`Cormorant Garamond` + `Plus Jakarta Sans`), dynamic
     * CSS variables, and shared JS definitions.
     *
     * @return string
     */
    public function hookDisplayHeader()
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $cfg = LvhBookoraDataProvider::getConfig();
        $teal = preg_match('/^#[0-9a-fA-F]{6}$/', $cfg['LVH_BK_COLOR_TEAL']) ? $cfg['LVH_BK_COLOR_TEAL'] : '#14110d';
        $gold = preg_match('/^#[0-9a-fA-F]{6}$/', $cfg['LVH_BK_COLOR_GOLD']) ? $cfg['LVH_BK_COLOR_GOLD'] : '#c9a063';

        $common = $this->getSharedCommon();

        // Bookmarks seed: every live room type as compact JSON for localStorage-backed bookmarks
        $bookmarksSeed = array();
        $roomSets = LvhBookoraDataProvider::getPageData($this->context, $common, 'bookmarks');
        if (!empty($roomSets['bk_page']['rooms_json'])) {
            foreach ($roomSets['bk_page']['rooms_json'] as $rm) {
                $bookmarksSeed[] = array(
                    'id' => (int) $rm['id_product'],
                    'name' => (string) $rm['name'],
                    'url' => (string) $rm['product_url'],
                    'price' => (string) $rm['price_formatted'],
                    'currency' => (string) $common['currency_sign'],
                    'image' => (string) $rm['image'],
                );
            }
        }

        Media::addJsDef(array(
            'lvhBookoraSearchUrl' => $common['search_url'],
            'lvhBookoraUseOccupancy' => (int) $common['use_occupancy_param'],
            'lvhBookoraImg' => $common['module_img'],
            'lvhBookoraBookmarksSeed' => $bookmarksSeed,
            'lvhBookoraFeedback' => (string) Tools::getValue('bk_feedback', ''),
        ));

        return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
            . '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n"
            . '<style>:root { --bk-teal: ' . $teal . '; --bk-gold: ' . $gold . '; }</style>' . "\n";
    }

    /**
     * Render Bookora Header 2 (glassmorphic nav + dropdowns) + breadcrumb page hero.
     *
     * @return string
     */
    public function hookDisplayBanner()
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $common = $this->getSharedCommon();

        // Recent posts for the Header 2 "Blog" dropdown
        $recent = array();
        foreach (array_slice(LvhBookoraDataProvider::getArticles($this->context, $common), 0, 3) as $a) {
            $recent[] = array('title' => $a['title'], 'url' => $a['url'], 'date' => $a['date']);
        }

        $this->context->smarty->assign(array(
            'bk' => $common,
            'bk_hero' => $this->resolvePageHero(),
            'bk_recent_posts' => $recent,
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-header.tpl');
    }

    /**
     * Determine the breadcrumb hero for the current page (null when the page
     * renders its own hero — Home 1 / Home 3 — or no hero is needed).
     *
     * @return array|null
     */
    protected function resolvePageHero()
    {
        $ctrl = $this->context->controller;

        // Bookora module controllers declare their own page identity
        if (isset($ctrl->lvhPage) && $ctrl->lvhPage !== '') {
            if (!empty($ctrl->lvhNoHero)) {
                return null;
            }
            $titles = LvhBookoraRouteMap::moduleHeroTitles();
            $title = isset($titles[$ctrl->lvhPage]) ? $titles[$ctrl->lvhPage] : 'Lush View Hotel';
            if ($ctrl->lvhPage === 'article' && !empty($ctrl->lvhArticleTitle)) {
                $title = (string) $ctrl->lvhArticleTitle;
            }
            $parent = ($ctrl->lvhPage === 'article') ? 'Blog' : $title;
            return array('title' => $title, 'crumb' => $parent);
        }

        $pageName = isset($ctrl->php_self) ? (string) $ctrl->php_self : '';
        if ($pageName === '' || $pageName === 'index') {
            return null;
        }

        $titles = LvhBookoraRouteMap::nativeHeroTitles();
        $title = isset($titles[$pageName]) ? $titles[$pageName] : 'Lush View Hotel';

        // Room detail hero shows the live room type name when available
        if ($pageName === 'product' && isset($ctrl->product) && is_object($ctrl->product)) {
            $name = '';
            if (isset($ctrl->product->name)) {
                $name = is_array($ctrl->product->name)
                    ? (isset($ctrl->product->name[$this->context->language->id]) ? $ctrl->product->name[$this->context->language->id] : reset($ctrl->product->name))
                    : (string) $ctrl->product->name;
            }
            if ($name !== '') {
                $title = $name;
            }
        }

        return array('title' => $title, 'crumb' => $title);
    }

    /**
     * Render full Bookora Home 2 homepage on `index`.
     *
     * @return string
     */
    public function hookDisplayHome()
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $common = $this->getSharedCommon();
        $home = LvhBookoraDataProvider::getHome2Data($this->context, $common);

        $this->context->smarty->assign(array(
            'bk' => $common,
            'bk_home' => $home,
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-home.tpl');
    }

    /**
     * Bookora filter bar on All Listings / Search Result (`category` page).
     *
     * @return string
     */
    public function hookDisplayTopColumn($params)
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        // Only the All Listings / Search Result pages get the Bookora filter bar
        $pageName = isset($this->context->controller->php_self) ? (string) $this->context->controller->php_self : '';
        if (!in_array($pageName, array('category', 'search', 'our-properties'), true)) {
            return '';
        }

        $ctrl = $this->context->controller;
        $sortOptions = array(
            '' => 'Sort By',
            'price:asc' => 'Price: Low to High',
            'price:desc' => 'Price: High to Low',
            'name:asc' => 'Name: A to Z',
            'name:desc' => 'Name: Z to A',
            'date:desc' => 'Newest First',
            'date:asc' => 'Oldest First',
            'position:asc' => 'Position: First to Last',
            'position:desc' => 'Position: Last to First',
        );

        $this->context->smarty->assign(array(
            'bk' => $this->getSharedCommon(),
            'bk_sort_options' => $sortOptions,
            'bk_current_sort' => (string) Tools::getValue('orderby', ''),
            'bk_search_keywords' => (string) Tools::getValue('search_query', Tools::getValue('s', '')),
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-filters.tpl');
    }

    /**
     * Bookora room detail top block (`Go Back`, `Bookmark`, `Share`, `Report`).
     *
     * @return string
     */
    public function hookDisplayRoomTypeDetailRoomTypeNameBlock($params)
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $room = $this->getRoomDetailContext();
        $this->context->smarty->assign(array(
            'bk' => $this->getSharedCommon(),
            'bk_room' => $room,
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-room-top.tpl');
    }

    /**
     * Bookora room detail bottom block (Beds/Bath/Guest cards, map, reviews).
     *
     * @return string
     */
    public function hookDisplayColumnsBottom($params)
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        // This hook renders site-wide from footer.tpl — only decorate room detail
        $pageName = isset($this->context->controller->php_self) ? (string) $this->context->controller->php_self : '';
        if ($pageName !== 'product') {
            return '';
        }

        $room = $this->getRoomDetailContext();
        $this->context->smarty->assign(array(
            'bk' => $this->getSharedCommon(),
            'bk_room' => $room,
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-room-bottom.tpl');
    }

    /**
     * Bookora contact cards + embedded map on the native Contact page.
     *
     * @return string
     */
    public function hookDisplayAfterHotelBranchInformation($params)
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $this->context->smarty->assign(array(
            'bk' => $this->getSharedCommon(),
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-contact-extra.tpl');
    }

    /**
     * Room detail helpers shared by the two product-page hooks.
     *
     * @return array
     */
    protected function getRoomDetailContext()
    {
        $ctrl = $this->context->controller;
        $common = $this->getSharedCommon();
        $room = array(
            'name' => 'Room Details',
            'id_product' => 0,
            'price' => '',
            'max_adults' => 2,
            'max_children' => 0,
            'max_guests' => 2,
            'beds_label' => '1 Bed',
            'bath_label' => '1 Bath',
            'persons_label' => '2 Persons',
        );

        if (isset($ctrl->product) && is_object($ctrl->product)) {
            $idProduct = isset($ctrl->product->id) ? (int) $ctrl->product->id : 0;
            $room['id_product'] = $idProduct;

            if (isset($ctrl->product->name)) {
                $room['name'] = is_array($ctrl->product->name)
                    ? (isset($ctrl->product->name[$this->context->language->id]) ? $ctrl->product->name[$this->context->language->id] : reset($ctrl->product->name))
                    : (string) $ctrl->product->name;
            }

            if ($idProduct > 0) {
                $rtRow = Db::getInstance()->getRow(
                    'SELECT `max_adults`, `max_children`, `max_guests`
                     FROM `' . _DB_PREFIX_ . 'htl_room_type`
                     WHERE `id_product` = ' . $idProduct
                );
                if (is_array($rtRow)) {
                    $room['max_adults'] = max(1, (int) $rtRow['max_adults']);
                    $room['max_children'] = max(0, (int) $rtRow['max_children']);
                    $room['max_guests'] = max(1, (int) $rtRow['max_guests']);
                }

                $price = 0.0;
                if (method_exists('Product', 'getPriceStatic')) {
                    $price = (float) Product::getPriceStatic($idProduct, true);
                }
                if ($price > 0) {
                    $room['price'] = $common['currency_sign'] . number_format($price, 2) . ' / Night';
                }

                $room['persons_label'] = $room['max_guests'] . ' Persons';
                $room['beds_label'] = ($price >= 850 ? '3 Beds' : ($price >= 650 ? '2 Beds' : '1 Bed'));
            }
        }

        return $room;
    }

    /**
     * Render Bookora partner strip + Footer 2 + floating [TOP] button + modals.
     *
     * @return string
     */
    public function hookDisplayFooterBefore()
    {
        if (!$this->isBookoraActive()) {
            return '';
        }

        $common = $this->getSharedCommon();
        $this->context->smarty->assign(array(
            'bk' => $common,
            'bk_year' => date('Y'),
            'bk_partners' => LvhBookoraDataProvider::getPartners(),
        ));

        return $this->display(__FILE__, 'views/templates/hook/bookora-footer.tpl');
    }

    /**
     * Back Office: Hotel Photo Library + configuration form.
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
                        'label' => $this->l('About Stat Value'),
                        'name' => 'LVH_BK_STAT1_NUM',
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('About Stat Caption'),
                        'name' => 'LVH_BK_STAT1_LABEL',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Secondary Stat Value'),
                        'name' => 'LVH_BK_STAT2_NUM',
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Secondary Stat Caption'),
                        'name' => 'LVH_BK_STAT2_LABEL',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Primary Dark Ink (#hex)'),
                        'name' => 'LVH_BK_COLOR_TEAL',
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Primary Warm Gold (#hex)'),
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
        $helper->fields_value = LvhBookoraDataProvider::getConfig();

        return $helper->generateForm(array($fieldsForm));
    }
}
