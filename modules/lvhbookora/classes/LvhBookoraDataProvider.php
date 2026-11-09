<?php
/**
 * Lush View Hotel - Bookora Storefront Data Provider for QloApps 1.8.0.
 *
 * Reads live rooms, GHS tariffs, room cover images, hotel interior gallery
 * images, guest testimonials, hotel contact details, and CMS links directly
 * from QloApps 1.8.0 tables with zero core overrides. Supplies data for the
 * Bookora Home 2 homepage, all standalone Bookora webpages, and the
 * transformed native QloApps pages.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../LvhBookoraRouteMap.php';

class LvhBookoraDataProvider
{
    /**
     * Read merged Bookora configuration (defaults + stored).
     *
     * @return array
     */
    public static function getConfig()
    {
        $defaults = LvhBookoraRouteMap::configDefaults();
        $out = array();
        foreach ($defaults as $k => $v) {
            $stored = Configuration::get($k);
            $out[$k] = ($stored !== false && $stored !== '') ? (string) $stored : $v;
        }
        return $out;
    }

    /**
     * Build shared header/footer context for all storefront pages.
     *
     * @param Context $context
     * @param array $cfg
     * @return array
     */
    public static function getCommonData(Context $context, array $cfg)
    {
        $p = _DB_PREFIX_;
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        $branch = Db::getInstance()->getRow(
            'SELECT hbi.`id`, hbi.`id_category`, hbi.`email`, hbi.`check_in`, hbi.`check_out`,
                    hbil.`hotel_name`
             FROM `' . $p . 'htl_branch_info` hbi
             LEFT JOIN `' . $p . 'htl_branch_info_lang` hbil
               ON (hbil.`id` = hbi.`id` AND hbil.`id_lang` = ' . $idLang . ')
             WHERE hbi.`active` = 1
             ORDER BY hbi.`id` ASC'
        );

        $idHotel = !empty($branch['id']) ? (int) $branch['id'] : 1;
        $idCategory = !empty($branch['id_category']) ? (int) $branch['id_category'] : 8;
        $hotelName = !empty($branch['hotel_name'])
            ? (string) $branch['hotel_name']
            : ((string) Configuration::get('PS_SHOP_NAME') ?: 'Lush View Hotel');

        $addrRow = Db::getInstance()->getRow(
            'SELECT `address1`, `address2`, `city`, COALESCE(NULLIF(`phone`, \'\'), `phone_mobile`) AS `phone`
             FROM `' . $p . 'address`
             WHERE `id_hotel` = ' . $idHotel . ' AND `deleted` = 0
             ORDER BY `id_address` DESC'
        );
        if (!$addrRow) {
            $addrRow = Db::getInstance()->getRow(
                'SELECT `address1`, `address2`, `city`, COALESCE(NULLIF(`phone`, \'\'), `phone_mobile`) AS `phone`
                 FROM `' . $p . 'address`
                 WHERE `id_hotel` > 0 AND `deleted` = 0
                 ORDER BY `id_hotel` ASC, `id_address` DESC'
            );
        }

        $addr1 = !empty($addrRow['address1']) ? trim((string) $addrRow['address1']) : trim((string) Configuration::get('PS_SHOP_ADDR1'));
        $city = !empty($addrRow['city']) ? trim((string) $addrRow['city']) : trim((string) Configuration::get('PS_SHOP_CITY'));
        $hotelAddress = $addr1 !== ''
            ? ($addr1 . ($city !== '' && stripos($addr1, $city) === false ? ', ' . $city : ''))
            : ($city !== '' ? $city . ', Ghana' : 'Accra, Ghana');

        $hotelPhone = !empty($addrRow['phone'])
            ? trim((string) $addrRow['phone'])
            : trim((string) Configuration::get('PS_SHOP_PHONE'));
        if ($hotelPhone === '') {
            $hotelPhone = '030 298 5499';
        }

        $hotelEmail = !empty($branch['email'])
            ? trim((string) $branch['email'])
            : trim((string) Configuration::get('PS_SHOP_EMAIL'));
        if ($hotelEmail === '') {
            $hotelEmail = 'info@lushviewhotel.net';
        }

        $searchUrl = $context->link->getCategoryLink($idCategory, null, $idLang);
        $searchPageUrl = $context->link->getPageLink('search', true, $idLang);
        $homeUrl = $context->link->getPageLink('index', true, $idLang);
        $contactUrl = $context->link->getPageLink('contact', true, $idLang);
        $accountUrl = $context->link->getPageLink('my-account', true, $idLang);
        $loginUrl = $context->link->getPageLink('authentication', true, $idLang);
        $orderProcess = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
        $cartUrl = $context->link->getPageLink($orderProcess, true, $idLang);

        $moduleUrls = array();
        foreach (LvhBookoraRouteMap::pageControllers() as $ctrl) {
            $moduleUrls[$ctrl . '_url'] = $context->link->getModuleLink('lvhbookora', $ctrl, array(), null, $idLang);
        }

        $isLogged = isset($context->customer) && $context->customer->isLogged();
        $customerFirst = $isLogged ? trim((string) $context->customer->firstname) : '';

        $cartQty = isset($context->cart) ? (int) $context->cart->nbProducts() : 0;

        $logoFile = (string) Configuration::get('PS_LOGO');
        $logoUrl = ($logoFile !== '' && file_exists(_PS_IMG_DIR_ . $logoFile))
            ? $context->link->getMediaLink(_PS_IMG_ . $logoFile)
            : '';

        $currencySign = (isset($context->currency) && !empty($context->currency->sign))
            ? (string) $context->currency->sign
            : '₵';

        $cmsRows = Db::getInstance()->executeS(
            'SELECT c.`id_cms`, cl.`meta_title`, cl.`link_rewrite`
             FROM `' . $p . 'cms` c
             INNER JOIN `' . $p . 'cms_lang` cl
               ON (cl.`id_cms` = c.`id_cms` AND cl.`id_lang` = ' . $idLang . ' AND cl.`id_shop` = ' . $idShop . ')
             WHERE c.`active` = 1
             ORDER BY c.`position` ASC
             LIMIT 6'
        );
        $cmsLinks = array();
        if (is_array($cmsRows)) {
            foreach ($cmsRows as $cr) {
                $cmsLinks[] = array(
                    'title' => (string) $cr['meta_title'],
                    'url' => $context->link->getCMSLink((int) $cr['id_cms'], (string) $cr['link_rewrite'], null, $idLang),
                );
            }
        }

        $social = array(
            'facebook' => trim((string) Configuration::get('BLOCKSOCIAL_FACEBOOK')),
            'twitter' => trim((string) Configuration::get('BLOCKSOCIAL_TWITTER')),
            'instagram' => trim((string) Configuration::get('BLOCKSOCIAL_INSTAGRAM')),
            'youtube' => trim((string) Configuration::get('BLOCKSOCIAL_YOUTUBE')),
        );

        $selectionType = (int) Configuration::get('PS_FRONT_ROOM_UNIT_SELECTION_TYPE');
        $useOccupancyParam = ($selectionType === 0 || $selectionType === 1);

        $pageName = '';
        if (isset($context->controller->php_self) && $context->controller->php_self) {
            $pageName = (string) $context->controller->php_self;
        }

        $common = array(
            'hotel_id' => $idHotel,
            'category_id' => $idCategory,
            'hotel_name' => $hotelName,
            'hotel_address' => $hotelAddress,
            'hotel_phone' => $hotelPhone,
            'hotel_phone_href' => preg_replace('/[^0-9+]/', '', $hotelPhone),
            'hotel_email' => $hotelEmail,
            'check_in_time' => !empty($branch['check_in']) ? (string) $branch['check_in'] : '13:00',
            'check_out_time' => !empty($branch['check_out']) ? (string) $branch['check_out'] : '12:00',
            'logo_url' => $logoUrl,
            'currency_sign' => $currencySign,
            'home_url' => $homeUrl,
            'search_url' => $searchUrl,
            'search_page_url' => $searchPageUrl,
            'contact_url' => $contactUrl,
            'account_url' => $accountUrl,
            'login_url' => $loginUrl,
            'cart_url' => $cartUrl,
            'cart_qty' => $cartQty,
            'is_logged' => $isLogged,
            'customer_firstname' => $customerFirst,
            'cms_links' => $cmsLinks,
            'social' => $social,
            'use_occupancy_param' => $useOccupancyParam ? 1 : 0,
            'is_home' => ($pageName === 'index') ? 1 : 0,
            'default_checkin' => date('Y-m-d'),
            'default_checkout' => date('Y-m-d', strtotime('+2 days')),
            'cfg' => $cfg,
            'module_img' => $context->link->getMediaLink(_MODULE_DIR_ . 'lvhbookora/views/img/'),
            'map_embed_url' => LvhBookoraRouteMap::mapEmbedUrl(),
            'map_directions_url' => LvhBookoraRouteMap::mapDirectionsUrl(),
        );

        return array_merge($common, $moduleUrls);
    }

    /**
     * Build full Bookora Home 2 homepage dataset.
     *
     * @param Context $context
     * @param array $common
     * @return array
     */
    public static function getHome2Data(Context $context, array $common)
    {
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        $interiorImages = self::loadInteriorGallery($context);
        $heroImage = self::resolveHeroImage($context, $interiorImages);
        $roomData = self::loadRoomsAndPackages($context, $common, $idLang, $idShop, $interiorImages);
        $bedrooms = $roomData['bedrooms'];
        $conferences = $roomData['conferences'];

        $pool = array();
        foreach ($interiorImages as $img) {
            $pool[] = $img['url'];
        }
        foreach ($bedrooms as $rm) {
            if (!empty($rm['image'])) {
                $pool[] = $rm['image'];
            }
        }
        if ($heroImage !== '') {
            $pool[] = $heroImage;
        }
        $pool = array_values(array_unique(array_filter($pool)));
        $poolCount = count($pool);

        $pickPhoto = function ($idx) use ($pool, $poolCount, $heroImage) {
            if ($poolCount === 0) {
                return $heroImage;
            }
            return $pool[$idx % $poolCount];
        };

        // Popular Rooms: 6 cards (4 live bedrooms + live conference packages fill the set)
        $popular = array();
        foreach ($bedrooms as $rm) {
            $popular[] = $rm;
        }
        foreach ($conferences as $cf) {
            $popular[] = $cf;
        }
        $popular = array_slice($popular, 0, 6);

        // Featured suites slider uses the live bedrooms
        $featured = !empty($bedrooms) ? $bedrooms : $popular;

        $testimonials = self::loadTestimonials($context, $idLang);
        $articles = self::getArticles($context, $common);

        return array(
            'hero_image' => $common['module_img'] . 'hero-home2.jpg',
            'hero_image_fallback' => $heroImage,
            'about_image_1' => $pickPhoto(1),
            'about_image_2' => $pickPhoto(2),
            'popular_rooms' => $popular,
            'services' => self::getServicePanels($common),
            'featured_suites' => $featured,
            'facilities' => self::getFacilities(8),
            'availability_checks' => self::getAvailabilityChecks(),
            'team' => self::getTeamMembers($common, 4),
            'testimonials' => $testimonials,
            'articles' => array_slice($articles, 0, 3),
            'partners' => self::getPartners(),
        );
    }

    /**
     * Backward-compatible homepage dataset (Home 1 controller reuses this).
     *
     * @param Context $context
     * @param array $common
     * @return array
     */
    public static function getHomeData(Context $context, array $common)
    {
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        $interiorImages = self::loadInteriorGallery($context);
        $heroImage = self::resolveHeroImage($context, $interiorImages);
        $roomData = self::loadRoomsAndPackages($context, $common, $idLang, $idShop, $interiorImages);
        $bedrooms = $roomData['bedrooms'];
        $conferences = $roomData['conferences'];

        $pool = array();
        foreach ($interiorImages as $img) {
            $pool[] = $img['url'];
        }
        foreach ($bedrooms as $rm) {
            if (!empty($rm['image'])) {
                $pool[] = $rm['image'];
            }
        }
        if ($heroImage !== '') {
            $pool[] = $heroImage;
        }
        $pool = array_values(array_unique(array_filter($pool)));
        $poolCount = count($pool);

        $pickPhoto = function ($idx) use ($pool, $poolCount, $heroImage) {
            if ($poolCount === 0) {
                return $heroImage;
            }
            return $pool[$idx % $poolCount];
        };

        if ($heroImage === '' && $poolCount > 0) {
            $heroImage = $pool[0];
        }

        $offers = self::buildSpecialOffers($bedrooms, $conferences, $common, $pickPhoto);
        $plans = self::buildPricingPlans($bedrooms, $common);
        $testimonials = self::loadTestimonials($context, $idLang);

        $gallerySlice = !empty($interiorImages) ? array_slice($interiorImages, 0, 8) : array();
        if (empty($gallerySlice)) {
            foreach ($pool as $idx => $u) {
                if ($idx >= 8) {
                    break;
                }
                $gallerySlice[] = array(
                    'url' => $u,
                    'caption' => 'Lush View Hotel Interior ' . ($idx + 1),
                );
            }
        }

        return array(
            'hero_image' => $heroImage,
            'about_image_left' => $pickPhoto(1),
            'about_image_right' => $pickPhoto(2),
            'offer_default_image' => !empty($offers[0]['image']) ? $offers[0]['image'] : $pickPhoto(3),
            'plan_side_image' => $pickPhoto(4),
            'testi_bg_image' => $pickPhoto(5),
            'highlight_img_1' => $pickPhoto(6),
            'highlight_img_2' => $pickPhoto(7),
            'bedrooms' => $bedrooms,
            'bedroom_count' => count($bedrooms),
            'offers' => $offers,
            'plans' => $plans,
            'testimonials' => $testimonials,
            'gallery' => $gallerySlice,
            'services' => self::getServicePanels($common),
            'facilities' => self::getFacilities(8),
            'team' => self::getTeamMembers($common, 4),
            'partners' => self::getPartners(),
        );
    }

    /**
     * Dataset for a standalone Bookora webpage.
     *
     * @param Context $context
     * @param array $common
     * @param string $page
     * @param string $param
     * @return array
     */
    public static function getPageData(Context $context, array $common, $page, $param = '')
    {
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;
        $interiorImages = self::loadInteriorGallery($context);
        $heroImage = self::resolveHeroImage($context, $interiorImages);
        $roomData = self::loadRoomsAndPackages($context, $common, $idLang, $idShop, $interiorImages);
        $bedrooms = $roomData['bedrooms'];
        $conferences = $roomData['conferences'];

        $pool = array();
        foreach ($interiorImages as $img) {
            $pool[] = $img['url'];
        }
        foreach ($bedrooms as $rm) {
            if (!empty($rm['image'])) {
                $pool[] = $rm['image'];
            }
        }
        $pool = array_values(array_unique(array_filter($pool)));
        if ($heroImage !== '') {
            $pool[] = $heroImage;
        }
        $pool = array_values(array_unique(array_filter($pool)));
        $poolCount = count($pool);
        $pickPhoto = function ($idx) use ($pool, $poolCount, $heroImage) {
            return $poolCount > 0 ? $pool[$idx % $poolCount] : $heroImage;
        };

        $allRooms = array_merge($bedrooms, $conferences);
        $featured = !empty($bedrooms) ? $bedrooms : $allRooms;

        switch ($page) {
            case 'about':
                return array(
                    'bk_page' => array(
                        'about_image_1' => $pickPhoto(1),
                        'about_image_2' => $pickPhoto(2),
                        'counters' => array(
                            array('num' => '12', 'suffix' => '+', 'label' => 'Years of Experience'),
                            array('num' => '80', 'suffix' => 'k+', 'label' => 'Happy Guests Hosted'),
                        ),
                        'about_facilities' => array_slice(self::getFacilities(8), 0, 4),
                        'offers' => self::buildSpecialOffers($bedrooms, $conferences, $common, $pickPhoto),
                        'featured_suites' => $featured,
                        'facilities' => self::getFacilities(8),
                    ),
                );

            case 'service':
                return array(
                    'bk_page' => array(
                        'services' => self::getServiceCards($common),
                    ),
                );

            case 'project':
                $gallery = array();
                $cats = array('Rooms', 'Suites', 'Dining', 'Events', 'Wellness');
                $sources = array_merge($interiorImages, array());
                foreach ($allRooms as $idx => $rm) {
                    if (!empty($rm['image'])) {
                        $sources[] = array('url' => $rm['image'], 'caption' => $rm['name']);
                    }
                }
                if (empty($sources)) {
                    $sources = array(array('url' => $pickPhoto(0), 'caption' => 'Lush View Hotel'));
                }
                foreach ($sources as $idx => $src) {
                    $gallery[] = array(
                        'url' => $src['url'],
                        'caption' => !empty($src['caption']) ? $src['caption'] : 'Lush View Hotel Interior',
                        'category' => $cats[$idx % count($cats)],
                    );
                }
                return array(
                    'bk_page' => array(
                        'gallery' => $gallery,
                        'categories' => $cats,
                        'showcases' => array_slice($featured, 0, 3),
                    ),
                );

            case 'team':
                return array(
                    'bk_page' => array(
                        'team' => self::getTeamMembers($common, 8),
                    ),
                );

            case 'testimonial':
                return array(
                    'bk_page' => array(
                        'testimonials' => self::loadTestimonials($context, $idLang),
                    ),
                );

            case 'pricing':
                return array(
                    'bk_page' => array(
                        'plans' => self::buildStayPlans($bedrooms, $conferences, $common),
                    ),
                );

            case 'blog':
                return array(
                    'bk_page' => array(
                        'articles' => self::getArticles($context, $common),
                    ),
                );

            case 'article':
                $articles = self::getArticles($context, $common);
                $current = null;
                foreach ($articles as $a) {
                    if ($a['slug'] === $param) {
                        $current = $a;
                        break;
                    }
                }
                if ($current === null && !empty($articles)) {
                    $current = $articles[0];
                }
                $related = array();
                if ($current !== null) {
                    foreach ($articles as $a) {
                        if ($a['slug'] !== $current['slug']) {
                            $related[] = $a;
                        }
                        if (count($related) >= 3) {
                            break;
                        }
                    }
                }
                return array(
                    'bk_page' => array(
                        'article' => $current,
                        'related' => $related,
                    ),
                );

            case 'home1':
                return array('bk_page' => self::getHomeData($context, $common));

            case 'home3':
                return array(
                    'bk_page' => array(
                        'hero_image' => $pickPhoto(0),
                        'menu' => self::getSpecialtyFoods($common),
                        'facts' => array(
                            array('num' => '34', 'suffix' => '+', 'label' => 'Rooms, Suites & Event Spaces'),
                            array('num' => '80', 'suffix' => 'k+', 'label' => 'Happy Guests Hosted'),
                            array('num' => '12', 'suffix' => '+', 'label' => 'Years Of Experience'),
                            array('num' => '24', 'suffix' => '/7', 'label' => 'Concierge & Room Service'),
                        ),
                        'featured_suites' => $featured,
                        'facilities' => self::getFacilities(8),
                    ),
                );

            case 'bookmarks':
                return array(
                    'bk_page' => array(
                        'rooms_json' => $allRooms,
                    ),
                );
        }

        return array('bk_page' => array());
    }

    /**
     * The 4 interactive Home 2 "Customer Services" panels.
     *
     * @param array $common
     * @return array
     */
    public static function getServicePanels(array $common)
    {
        $img = $common['module_img'];
        return array(
            array(
                'title' => 'Room Booking',
                'text' => 'Reserve elegantly appointed rooms and suites in seconds with live availability, instant confirmation, and flexible direct-booking rates.',
                'image' => $img . 'wellness-concierge.jpg',
                'url' => $common['search_url'],
            ),
            array(
                'title' => 'Fitness Center',
                'text' => 'Keep your routine in our fully equipped fitness center with modern cardio and strength stations, open daily for resident guests.',
                'image' => $img . 'dining-lounge.jpg',
                'url' => $common['contact_url'],
            ),
            array(
                'title' => '24/7 Room Service',
                'text' => 'From late-night refreshments to sunrise breakfast in bed, our kitchen and service team are on call around the clock.',
                'image' => $img . 'conference-banquet.jpg',
                'url' => $common['contact_url'],
            ),
            array(
                'title' => 'Swimming Pool Access',
                'text' => 'Unwind beside our serene pool and lounge deck — complimentary for in-house guests, with poolside refreshments all day.',
                'image' => $img . 'hero-home2.jpg',
                'url' => $common['contact_url'],
            ),
        );
    }

    /**
     * The 8-card Service page grid.
     *
     * @param array $common
     * @return array
     */
    public static function getServiceCards(array $common)
    {
        $panels = self::getServicePanels($common);
        $extra = array(
            array(
                'title' => 'Airport Transfers',
                'text' => 'Seamless private pick-up and drop-off coordinated with your arrival and departure times.',
                'image' => $common['module_img'] . 'wellness-concierge.jpg',
                'url' => $common['contact_url'],
            ),
            array(
                'title' => 'Laundry & Dry Cleaning',
                'text' => 'Same-day laundry, pressing, and dry cleaning so you always look your best.',
                'image' => $common['module_img'] . 'dining-lounge.jpg',
                'url' => $common['contact_url'],
            ),
            array(
                'title' => 'Conference & Events',
                'text' => 'Flexible halls for up to 60 guests with catering, AV equipment, and dedicated event support.',
                'image' => $common['module_img'] . 'conference-banquet.jpg',
                'url' => $common['contact_url'],
            ),
            array(
                'title' => 'Concierge & Tours',
                'text' => 'Local recommendations, tour arrangements, and ticketing handled by our knowledgeable concierge team.',
                'image' => $common['module_img'] . 'hero-home2.jpg',
                'url' => $common['contact_url'],
            ),
        );
        return array_merge($panels, $extra);
    }

    /**
     * The 8-item Home 2 Facilities icon grid (exact Bookora labels).
     *
     * @param int $count
     * @return array
     */
    public static function getFacilities($count = 8)
    {
        $items = array(
            array('title' => 'Gym Training Grounds', 'icon' => 'gym'),
            array('title' => 'World-Class Amenities', 'icon' => 'star'),
            array('title' => 'Stay With Comfort', 'icon' => 'bed'),
            array('title' => 'Designed For Your Comfort', 'icon' => 'sofa'),
            array('title' => 'Exclusive Guest Services', 'icon' => 'bell'),
            array('title' => 'Modern Facilities For You', 'icon' => 'wifi'),
            array('title' => 'Where Comfort Meets Style', 'icon' => 'gem'),
            array('title' => 'Enjoy Every Moment', 'icon' => 'glass'),
        );
        return array_slice($items, 0, (int) $count);
    }

    /**
     * The 4 "Check Availability" checkmarks (exact Bookora labels).
     *
     * @return array
     */
    public static function getAvailabilityChecks()
    {
        return array(
            '24/7 Concierge Service',
            'Wellness & Spa Center',
            'Gourmet On-Site Dining',
            'Rooftop Pool & Lounge',
        );
    }

    /**
     * Our Expert / team members with portraits and social links.
     *
     * @param array $common
     * @param int $count
     * @return array
     */
    public static function getTeamMembers(array $common, $count = 4)
    {
        $img = $common['module_img'];
        $members = array(
            array('name' => 'Kwame Mensah', 'role' => 'General Manager', 'image' => $img . 'team-1.jpg', 'mail' => 'gm@lushviewhotel.net'),
            array('name' => 'Abena Osei', 'role' => 'Head of Guest Relations', 'image' => $img . 'team-2.jpg', 'mail' => 'guestrelations@lushviewhotel.net'),
            array('name' => 'Kofi Boateng', 'role' => 'Executive Chef', 'image' => $img . 'team-3.jpg', 'mail' => 'chef@lushviewhotel.net'),
            array('name' => 'Akosua Danquah', 'role' => 'Events & Conference Director', 'image' => $img . 'team-4.jpg', 'mail' => 'events@lushviewhotel.net'),
            array('name' => 'Yaw Appiah', 'role' => 'Chief Engineer', 'image' => '', 'mail' => 'engineering@lushviewhotel.net'),
            array('name' => 'Efua Sarpong', 'role' => 'Reservations Manager', 'image' => '', 'mail' => 'reservations@lushviewhotel.net'),
            array('name' => 'Nii Lartey', 'role' => 'Head of Security', 'image' => '', 'mail' => 'security@lushviewhotel.net'),
            array('name' => 'Ama Darko', 'role' => 'Wellness & Spa Lead', 'image' => '', 'mail' => 'spa@lushviewhotel.net'),
        );
        foreach ($members as $i => $m) {
            $members[$i]['initials'] = Tools::strtoupper(
                Tools::substr($m['name'], 0, 1) . Tools::substr(strstr($m['name'], ' '), 1, 1)
            );
        }
        return array_slice($members, 0, (int) $count);
    }

    /**
     * Partner / sponsor logo strip (SVG wordmarks).
     *
     * @return array
     */
    public static function getPartners()
    {
        return array(
            array('name' => 'TripNova', 'tag' => 'Travel Awards'),
            array('name' => 'Guestly', 'tag' => 'Booking Partner'),
            array('name' => 'LuxeGuide', 'tag' => 'Hospitality'),
            array('name' => 'StayScore', 'tag' => 'Rated 9.4'),
            array('name' => 'TourAfrica', 'tag' => 'Tourism Board'),
        );
    }

    /**
     * Home 3 "Specialties Foods" resort dining menu.
     *
     * @param array $common
     * @return array
     */
    public static function getSpecialtyFoods(array $common)
    {
        $sign = $common['currency_sign'];
        return array(
            array('title' => 'Grilled Tilapia & Kelewele', 'desc' => 'Whole grilled tilapia with spiced plantain, pepper sauce, and garden greens.', 'price' => $sign . '95', 'category' => 'Mains'),
            array('title' => 'Jollof Rice & Suya Chicken', 'desc' => 'Smoky party jollof with suya-spiced chicken skewers and fried ripe plantain.', 'price' => $sign . '85', 'category' => 'Mains'),
            array('title' => 'Seafood Platter For Two', 'desc' => 'Prawns, calamari, and lobster tail with lemon butter and herb rice.', 'price' => $sign . '220', 'category' => 'Chef Special'),
            array('title' => 'Continental Breakfast', 'desc' => 'Eggs to order, artisan bread, tropical fruit, pastries, and freshly brewed coffee.', 'price' => $sign . '60', 'category' => 'Breakfast'),
            array('title' => 'Signature Mocktail Trio', 'desc' => 'Hibiscus, pineapple-ginger, and cucumber-mint coolers served over crushed ice.', 'price' => $sign . '45', 'category' => 'Drinks'),
            array('title' => 'Chocolate Lava Cake', 'desc' => 'Warm dark chocolate cake with vanilla bean ice cream and berry coulis.', 'price' => $sign . '40', 'category' => 'Dessert'),
        );
    }

    /**
     * Pricing page stay plans built from the live room catalog.
     *
     * @param array $bedrooms
     * @param array $conferences
     * @param array $common
     * @return array
     */
    public static function buildStayPlans(array $bedrooms, array $conferences, array $common)
    {
        $plans = array();
        foreach ($bedrooms as $rm) {
            $plans[] = array(
                'title' => $rm['name'],
                'subtitle' => $rm['short_desc'],
                'price_formatted' => $rm['price_formatted'],
                'price_exact' => $rm['price_exact'],
                'period' => '/ Night',
                'book_url' => $rm['book_url'],
                'features' => array(
                    'Spacious air-conditioned room with en-suite bath',
                    'Plush bedding with daily housekeeping',
                    'Complimentary high-speed Wi-Fi & smart TV',
                    '24/7 standby power & secure parking',
                    'In-room dining and restaurant access',
                    'Flexible cancellation on direct bookings',
                ),
            );
        }
        foreach ($conferences as $cf) {
            $plans[] = array(
                'title' => $cf['name'],
                'subtitle' => $cf['short_desc'],
                'price_formatted' => $cf['price_formatted'],
                'price_exact' => $cf['price_exact'],
                'period' => '/ Session',
                'book_url' => $common['contact_url'],
                'features' => array(
                    'Climate-controlled hall for up to 60 delegates',
                    'HD projection, sound system & flipcharts',
                    'Dedicated event support & high-speed Wi-Fi',
                    'Custom boardroom, classroom, or banquet setup',
                    'Optional tea break & group lunch catering',
                    'Preferential group bedroom rates for attendees',
                ),
            );
        }
        return $plans;
    }

    /**
     * Editorial blog articles shared across Home 2, /blog and /blog/{slug}.
     *
     * @param Context $context
     * @param array $common
     * @return array
     */
    public static function getArticles(Context $context, array $common)
    {
        $img = $common['module_img'];
        $author = array(
            'name' => 'Lush View Editorial',
            'role' => 'Hotel Editorial Team',
            'bio' => 'The Lush View Hotel editorial team shares travel inspiration, hospitality insights, and guides to help guests get the most from every stay in Ghana.',
        );

        $raw = array(
            array(
                'slug' => 'exploring-scenic-destinations-hotels-with-the-most-amazing-city-and-nature-views',
                'title' => 'Exploring Scenic Destinations: Hotels with the Most Amazing City and Nature Views',
                'date' => 'October 18, 2026',
                'category' => 'Travel',
                'image' => $img . 'hero-home2.jpg',
                'excerpt' => 'The best hotel stays begin with a view. From glittering skylines at dusk to tranquil green horizons at dawn, discover how the right setting transforms an ordinary trip into an unforgettable escape.',
                'tags' => array('Travel', 'City Views', 'Nature', 'Luxury Stays'),
                'body' => array(
                    array('type' => 'p', 'text' => 'There is a moment every traveler knows — you pull back the curtain, and the destination reveals itself all at once. Whether it is a city skyline glittering after sunset or a stretch of greenery dissolving into morning mist, the view from your room sets the tone for the entire stay.'),
                    array('type' => 'h', 'text' => 'Where the city meets the horizon'),
                    array('type' => 'p', 'text' => 'Urban hotels with panoramic glass and elevated suites give guests a living canvas of the city below. At Lush View Hotel, our Executive Rooms and Suites are positioned to capture exactly that balance: close enough to feel the pulse of Accra, yet serene enough to hear yourself think.'),
                    array('type' => 'quote', 'text' => 'A great hotel does not just give you a room — it gives you a perspective on the place you came to see.'),
                    array('type' => 'p', 'text' => 'When choosing a hotel for the view, look beyond the photos. Check the floor level of your room category, ask which direction the windows face, and read recent guest reviews that mention light, noise, and scenery.'),
                    array('type' => 'h', 'text' => 'Nature as an amenity'),
                    array('type' => 'p', 'text' => 'Gardens, pool decks, and open terraces are more than decorative — they change the pace of a trip. Morning walks through landscaped grounds, breakfast overlooking greenery, and quiet corners for reading all contribute to the restorative side of travel.'),
                    array('type' => 'p', 'text' => 'The takeaway is simple: choose stays where the surroundings are part of the experience. When your hotel is a destination in itself, every hour of the trip — from sunrise coffee to nightcap — feels considered.'),
                ),
            ),
            array(
                'slug' => 'the-art-of-hospitality-what-makes-a-hotel-stay-unforgettable',
                'title' => 'The Art of Hospitality: What Makes a Hotel Stay Unforgettable',
                'date' => 'October 10, 2026',
                'category' => 'Hospitality',
                'image' => $img . 'wellness-concierge.jpg',
                'excerpt' => 'Luxury is not only thread count and chandeliers. It is the warm greeting at the door, the remembered preference, the seamless check-in. Explore the small details that turn service into hospitality.',
                'tags' => array('Hospitality', 'Service', 'Guest Experience'),
                'body' => array(
                    array('type' => 'p', 'text' => 'Ask ten guests to describe their favorite hotel stay and you will rarely hear about square footage. You will hear about people — the receptionist who had the room key ready before they reached the desk, the waiter who remembered how they take their coffee.'),
                    array('type' => 'h', 'text' => 'Anticipation beats reaction'),
                    array('type' => 'p', 'text' => 'Great hospitality is quiet and proactive. Extra pillows appear before they are asked for. A taxi is arranged before the meeting ends. These gestures are not accidents; they are the product of training, culture, and teams who genuinely care.'),
                    array('type' => 'quote', 'text' => 'People will forget what you said, but they will never forget how you made them feel — that is the core of hospitality.'),
                    array('type' => 'p', 'text' => 'At Lush View Hotel, our teams are trained to listen first. From concierge requests to conference logistics, every interaction is an opportunity to remove friction and add warmth.'),
                    array('type' => 'h', 'text' => 'Consistency is the true luxury'),
                    array('type' => 'p', 'text' => 'One spectacular moment is memorable; consistent excellence is what earns trust. Clean rooms, reliable power, prompt service, and honest pricing — night after night — are the foundations guests return for.'),
                ),
            ),
            array(
                'slug' => 'why-conference-travel-works-better-with-the-right-hotel',
                'title' => 'Why Conference Travel Works Better With The Right Hotel',
                'date' => 'September 28, 2026',
                'category' => 'Events',
                'image' => $img . 'conference-banquet.jpg',
                'excerpt' => 'Business travel should sharpen focus, not scatter it. Discover how hosting your conference and your stay under one roof saves time, energy, and budget.',
                'tags' => array('Conferences', 'Business Travel', 'Events'),
                'body' => array(
                    array('type' => 'p', 'text' => 'The most productive conferences share a trait: nothing distracts from the agenda. When meeting rooms, guest rooms, dining, and AV support live in one place, delegates spend their energy on the work — not on logistics.'),
                    array('type' => 'h', 'text' => 'One venue, zero friction'),
                    array('type' => 'p', 'text' => 'Co-locating accommodation and conference space eliminates transfers, parking hunts, and late arrivals. Organizers get a single point of contact; attendees get the rare luxury of walking from a boardroom to a comfortable bed in minutes.'),
                    array('type' => 'quote', 'text' => 'The best-run events feel effortless to attendees — because the hard work happened before the first slide loaded.'),
                    array('type' => 'p', 'text' => 'Our conference packages scale from focused 2-hour sessions to full 24-hour summits for up to 60 delegates, with catering, projection, sound, and high-speed Wi-Fi included or arranged on request.'),
                    array('type' => 'h', 'text' => 'Budgets that make sense'),
                    array('type' => 'p', 'text' => 'Bundled delegate rates, on-site catering, and zero transport overhead make hotel-hosted events surprisingly cost-efficient. Add preferential bedroom rates for out-of-town guests and the value compounds.'),
                ),
            ),
            array(
                'slug' => 'a-food-lovers-guide-to-hotel-dining-in-accra',
                'title' => 'A Food Lover’s Guide to Hotel Dining in Accra',
                'date' => 'September 15, 2026',
                'category' => 'Dining',
                'image' => $img . 'dining-lounge.jpg',
                'excerpt' => 'From smoky jollof to elegant continental breakfasts, Accra’s hotel kitchens are rewriting the city’s food map. Here is how to eat well without leaving your hotel.',
                'tags' => array('Dining', 'Accra', 'Cuisine'),
                'body' => array(
                    array('type' => 'p', 'text' => 'Accra has always eaten well — but the city’s hotel kitchens have quietly become destinations in their own right. Chefs are blending Ghanaian classics with continental technique, and the results deserve a seat at the table.'),
                    array('type' => 'h', 'text' => 'Breakfast as a ritual'),
                    array('type' => 'p', 'text' => 'A good hotel breakfast sets the day: fresh tropical fruit, eggs cooked to order, flaky pastries, and coffee that actually tastes like coffee. It is the easiest luxury of any trip.'),
                    array('type' => 'quote', 'text' => 'The table is where a hotel’s personality shows most honestly — generous, attentive, and full of flavor.'),
                    array('type' => 'p', 'text' => 'For dinner, look for kitchens that source locally: grilled tilapia with kelewele, suya-spiced skewers, and seafood platters that taste of the coast. Pair with a house mocktail and the evening writes itself.'),
                    array('type' => 'h', 'text' => 'In-room dining done right'),
                    array('type' => 'p', 'text' => 'Room service is not an afterthought at a great hotel. Late arrivals, working lunches, and midnight cravings are all answered with a menu that respects the hour and a kitchen that stays open.'),
                ),
            ),
            array(
                'slug' => 'wellness-on-the-road-staying-balanced-while-you-travel',
                'title' => 'Wellness on the Road: Staying Balanced While You Travel',
                'date' => 'September 2, 2026',
                'category' => 'Wellness',
                'image' => $img . 'hero-home2.jpg',
                'excerpt' => 'Travel disrupts routines — but it does not have to derail them. From quick workouts to poolside recovery, here is how to stay balanced on the move.',
                'tags' => array('Wellness', 'Fitness', 'Travel Tips'),
                'body' => array(
                    array('type' => 'p', 'text' => 'Between flights, meetings, and unfamiliar beds, travel can quietly drain your energy. The good news: a few deliberate habits — and the right hotel amenities — keep body and mind in balance.'),
                    array('type' => 'h', 'text' => 'Move a little, often'),
                    array('type' => 'p', 'text' => 'Twenty minutes in a well-equipped gym, a swim before breakfast, or a walk through the hotel grounds does more for travel energy than another espresso. Choose stays with real fitness facilities and use them on day one — before excuses arrive.'),
                    array('type' => 'quote', 'text' => 'Wellness on the road is not about perfection; it is about keeping one or two anchors from home.'),
                    array('type' => 'p', 'text' => 'Hydration, consistent sleep hours, and lighter evening meals are unglamorous but powerful. Add a poolside hour to decompress and the trip ends with you better than it found you.'),
                    array('type' => 'h', 'text' => 'Design your stay, not just your schedule'),
                    array('type' => 'p', 'text' => 'Book a room that supports rest: quiet floors, blackout comfort, and space to breathe. Then protect the calendar around it. The most successful travelers treat recovery as part of the itinerary.'),
                ),
            ),
            array(
                'slug' => 'planning-the-perfect-weekend-getaway-from-the-city',
                'title' => 'Planning the Perfect Weekend Getaway From the City',
                'date' => 'August 20, 2026',
                'category' => 'Travel',
                'image' => $img . 'dining-lounge.jpg',
                'excerpt' => 'You do not need two weeks of leave to reset. A well-planned weekend — with the right hotel, the right pace, and zero logistics — can feel like a full vacation.',
                'tags' => array('Getaways', 'Weekend', 'Travel Planning'),
                'body' => array(
                    array('type' => 'p', 'text' => 'The perfect weekend getaway is an exercise in subtraction: fewer decisions, fewer transfers, fewer things to pack. What remains is time — the one ingredient vacations are supposed to be about.'),
                    array('type' => 'h', 'text' => 'Choose ease over distance'),
                    array('type' => 'p', 'text' => 'A great getaway hotel is close enough to reach without exhaustion, and complete enough that you never need to leave. Pool, restaurant, spa, and comfortable beds cover ninety percent of what a weekend should be.'),
                    array('type' => 'quote', 'text' => 'A weekend well spent gives you back a week of energy.'),
                    array('type' => 'p', 'text' => 'Plan one anchor activity — a dinner reservation, a massage, a city tour — and let the rest unfold. The best weekends have a rhythm, not a checklist.'),
                    array('type' => 'h', 'text' => 'Pack light, arrive early'),
                    array('type' => 'p', 'text' => 'A small bag, an early check-in request, and a flexible Sunday checkout turn forty-eight hours into something that feels much longer. Monday arrives, and you are genuinely ready for it.'),
                ),
            ),
        );

        foreach ($raw as $i => $a) {
            $raw[$i]['author'] = $author;
            $raw[$i]['url'] = $context->link->getModuleLink('lvhbookora', 'article', array('slug' => $a['slug']));
        }
        return $raw;
    }

    /**
     * Persist a guest review / blog comment / contact-style feedback entry.
     *
     * @param string $type review|comment
     * @param array $payload
     * @return bool
     */
    public static function storeFeedback($type, array $payload)
    {
        $key = 'LVH_BK_FEEDBACK';
        $existing = array();
        $raw = (string) Configuration::get($key);
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        $payload['type'] = (string) $type;
        $payload['date'] = date('Y-m-d H:i:s');
        $existing[] = $payload;
        if (count($existing) > 60) {
            $existing = array_slice($existing, -60);
        }
        return Configuration::updateValue($key, json_encode($existing));
    }

    /**
     * Persist a newsletter subscriber (validated by caller).
     *
     * @param string $email
     * @return bool
     */
    public static function storeSubscriber($email)
    {
        $key = 'LVH_BK_SUBSCRIBERS';
        $existing = array();
        $raw = (string) Configuration::get($key);
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        $email = Tools::strtolower(trim((string) $email));
        if (!in_array($email, $existing)) {
            $existing[] = $email;
        }
        if (count($existing) > 500) {
            $existing = array_slice($existing, -500);
        }
        return Configuration::updateValue($key, json_encode($existing));
    }

    /**
     * Load hotel interior gallery photos from `qlo_htl_interior_image`.
     *
     * @param Context $context
     * @return array
     */
    protected static function loadInteriorGallery(Context $context)
    {
        $p = _DB_PREFIX_;
        $out = array();
        $hasTable = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'htl_interior_image') . "'", true, false);
        if (empty($hasTable)) {
            return $out;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_interior_image`, `name`, `display_name`
             FROM `' . $p . 'htl_interior_image`
             WHERE `active` = 1
             ORDER BY `position` ASC, `id_interior_image` ASC
             LIMIT 16'
        );
        if (!is_array($rows)) {
            return $out;
        }

        foreach ($rows as $r) {
            $fname = basename((string) $r['name']);
            if ($fname === '') {
                continue;
            }
            $disk = _PS_MODULE_DIR_ . 'wkabouthotelblock/views/img/hotel_interior/' . $fname;
            if (file_exists($disk)) {
                $out[] = array(
                    'url' => $context->link->getMediaLink(_MODULE_DIR_ . 'wkabouthotelblock/views/img/hotel_interior/' . $fname),
                    'caption' => !empty($r['display_name']) ? (string) $r['display_name'] : 'Lush View Hotel',
                );
            }
        }
        return $out;
    }

    /**
     * Resolve primary hero banner image from QloApps 1.8.0 header media or fallback.
     *
     * @param Context $context
     * @param array $interiorImages
     * @return string
     */
    protected static function resolveHeroImage(Context $context, array $interiorImages)
    {
        $p = _DB_PREFIX_;
        $hasHeaderTbl = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'htl_header_image') . "'", true, false);
        if (!empty($hasHeaderTbl)) {
            $hRow = Db::getInstance()->getRow(
                'SELECT `name` FROM `' . $p . 'htl_header_image`
                 WHERE `active` = 1
                 ORDER BY `position` ASC, `id_header_image` ASC'
            );
            if (!empty($hRow['name'])) {
                $fname = basename((string) $hRow['name']);
                if (file_exists(_PS_IMG_DIR_ . 'hotel_header_media/' . $fname)) {
                    return $context->link->getMediaLink(_PS_IMG_ . 'hotel_header_media/' . $fname);
                }
            }
        }

        $legacyHero = basename((string) Configuration::get('WK_HOTEL_HEADER_IMAGE'));
        if ($legacyHero !== '' && file_exists(_PS_IMG_DIR_ . $legacyHero)) {
            return $context->link->getMediaLink(_PS_IMG_ . $legacyHero);
        }

        if (!empty($interiorImages[0]['url'])) {
            return $interiorImages[0]['url'];
        }

        if (file_exists(_PS_IMG_DIR_ . 'hotel_header_image.jpg')) {
            return $context->link->getMediaLink(_PS_IMG_ . 'hotel_header_image.jpg');
        }

        return '';
    }

    /**
     * Load active room types with real cover photos & GHS tariffs.
     *
     * @param Context $context
     * @param array $common
     * @param int $idLang
     * @param int $idShop
     * @param array $interiorImages
     * @return array{bedrooms:array,conferences:array}
     */
    protected static function loadRoomsAndPackages(Context $context, array $common, $idLang, $idShop, array $interiorImages)
    {
        $p = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS(
            'SELECT hrt.`id` AS `id_htl_room_type`, hrt.`id_product`, hrt.`adults`, hrt.`children`,
                    hrt.`max_adults`, hrt.`max_children`, hrt.`max_guests`,
                    ps.`price` AS `base_price`,
                    pl.`name`, pl.`description_short`, pl.`link_rewrite`
             FROM `' . $p . 'htl_room_type` hrt
             INNER JOIN `' . $p . 'product` pr ON (pr.`id_product` = hrt.`id_product`)
             INNER JOIN `' . $p . 'product_shop` ps
               ON (ps.`id_product` = hrt.`id_product` AND ps.`id_shop` = ' . $idShop . ')
             INNER JOIN `' . $p . 'product_lang` pl
               ON (pl.`id_product` = hrt.`id_product` AND pl.`id_lang` = ' . $idLang . ' AND pl.`id_shop` = ' . $idShop . ')
             WHERE ps.`active` = 1
             ORDER BY ps.`price` ASC, hrt.`id` ASC'
        );

        $bedrooms = array();
        $conferences = array();
        $largeType = method_exists('ImageType', 'getFormatedName')
            ? ImageType::getFormatedName('large')
            : 'large_default';

        if (is_array($rows)) {
            $idx = 0;
            foreach ($rows as $r) {
                $idProduct = (int) $r['id_product'];
                $name = trim((string) $r['name']);
                $rewrite = !empty($r['link_rewrite']) ? (string) $r['link_rewrite'] : 'room-' . $idProduct;
                $price = round((float) $r['base_price'], 2);
                if ($price <= 0) {
                    $price = round((float) Product::getPriceStatic($idProduct, true), 2);
                }

                $imgUrl = '';
                $cover = Product::getCover($idProduct);
                if (!empty($cover['id_image'])) {
                    $imgUrl = $context->link->getImageLink($rewrite, $idProduct . '-' . (int) $cover['id_image'], $largeType);
                } elseif (!empty($interiorImages)) {
                    $imgUrl = $interiorImages[$idx % count($interiorImages)]['url'];
                }

                $maxGuests = max(1, (int) $r['max_guests']);
                $maxAdults = max(1, (int) $r['max_adults']);
                $maxChildren = max(0, (int) $r['max_children']);
                $shortDesc = trim(preg_replace('/\s+/', ' ', strip_tags((string) $r['description_short'])));
                if ($shortDesc === '') {
                    $shortDesc = 'Elegantly appointed accommodation featuring climate control, plush bedding, high-speed Wi-Fi, and private en-suite bath.';
                }

                $productUrl = $context->link->getProductLink($idProduct, $rewrite, null, null, $idLang);
                $sep = (strpos($common['search_url'], '?') !== false) ? '&' : '?';
                $bookUrl = $common['search_url'] . $sep . http_build_query(array(
                    'date_from' => $common['default_checkin'],
                    'date_to' => $common['default_checkout'],
                    'occupancy' => array(
                        array('adults' => min(2, $maxAdults), 'children' => 0),
                    ),
                ));

                $item = array(
                    'id_product' => $idProduct,
                    'name' => $name,
                    'price' => $price,
                    'price_formatted' => number_format($price, 0),
                    'price_exact' => number_format($price, 2),
                    'image' => $imgUrl,
                    'max_guests' => $maxGuests,
                    'max_adults' => $maxAdults,
                    'max_children' => $maxChildren,
                    'persons_label' => $maxGuests . ' Persons',
                    'beds_label' => ($price >= 850 ? '3 Beds' : ($price >= 650 ? '2 Beds' : '1 Bed')),
                    'bath_label' => '1 Bath',
                    'short_desc' => Tools::substr($shortDesc, 0, 145),
                    'product_url' => $productUrl,
                    'book_url' => $bookUrl,
                );

                $isConf = (stripos($name, 'conference') !== false || $maxAdults >= 15);
                if ($isConf) {
                    $conferences[] = $item;
                } else {
                    $bedrooms[] = $item;
                }
                $idx++;
            }
        }

        return array('bedrooms' => $bedrooms, 'conferences' => $conferences);
    }

    /**
     * Build the 4 interactive "Special Offers" rows using live GHS tiers.
     *
     * @param array $bedrooms
     * @param array $conferences
     * @param array $common
     * @param callable $pickPhoto
     * @return array
     */
    protected static function buildSpecialOffers(array $bedrooms, array $conferences, array $common, $pickPhoto)
    {
        $execPrice = 700;
        $suitePrice = 900;
        $execImg = $pickPhoto(2);
        $suiteImg = $pickPhoto(3);

        foreach ($bedrooms as $rm) {
            if (stripos($rm['name'], 'exec') !== false) {
                $execPrice = (float) $rm['price'];
                if (!empty($rm['image'])) {
                    $execImg = $rm['image'];
                }
            } elseif (stripos($rm['name'], 'suite') !== false) {
                $suitePrice = (float) $rm['price'];
                if (!empty($rm['image'])) {
                    $suiteImg = $rm['image'];
                }
            }
        }

        $confShortPrice = 1600;
        $confFullPrice = 3000;
        $confImg = $pickPhoto(6);
        foreach ($conferences as $cf) {
            if (!empty($cf['image'])) {
                $confImg = $cf['image'];
            }
            if (stripos($cf['name'], '3') !== false || $cf['price'] <= 1800) {
                $confShortPrice = (float) $cf['price'];
            }
            if (stripos($cf['name'], '24') !== false || $cf['price'] >= 2500) {
                $confFullPrice = (float) $cf['price'];
            }
        }

        $sep = (strpos($common['search_url'], '?') !== false) ? '&' : '?';
        $stayBookUrl = $common['search_url'] . $sep . http_build_query(array(
            'date_from' => $common['default_checkin'],
            'date_to' => $common['default_checkout'],
            'occupancy' => array(array('adults' => 2, 'children' => 0)),
        ));

        return array(
            array(
                'title' => 'Executive Business Stay',
                'price_label' => $common['currency_sign'] . number_format($execPrice, 0),
                'unit_label' => '/ Night',
                'image' => $execImg,
                'url' => $stayBookUrl,
                'perks' => array(
                    'Spacious Executive Room for up to 2 Adults',
                    'High-speed fiber Wi-Fi & executive work desk',
                    '24/7 standby power & secure on-site parking',
                    'In-room dining & restaurant access',
                ),
            ),
            array(
                'title' => 'Signature Suite Retreat',
                'price_label' => $common['currency_sign'] . number_format($suitePrice, 0),
                'unit_label' => '/ Night',
                'image' => $suiteImg,
                'url' => $stayBookUrl,
                'perks' => array(
                    'Luxury Suite accommodation with lounge seating',
                    'Panoramic serene views & king-size comfort',
                    'Priority check-in & personalized concierge care',
                    'Complimentary welcome refreshments',
                ),
            ),
            array(
                'title' => 'Executive Conference Session (3 Hours)',
                'price_label' => $common['currency_sign'] . number_format($confShortPrice, 0),
                'unit_label' => '/ Session',
                'image' => $confImg,
                'url' => $common['contact_url'],
                'perks' => array(
                    'Climate-controlled hall for up to 60 delegates',
                    'HD projection, sound system & flipcharts',
                    'Dedicated event support & high-speed Wi-Fi',
                    'Optional tea break & group lunch catering',
                ),
            ),
            array(
                'title' => 'Full-Day Corporate Summit (24 Hours)',
                'price_label' => $common['currency_sign'] . number_format($confFullPrice, 0),
                'unit_label' => '/ Full Access',
                'image' => $pickPhoto(0),
                'url' => $common['contact_url'],
                'perks' => array(
                    'Full-day exclusive Conference Hall access',
                    'Custom boardroom, classroom, or banquet setup',
                    'Uninterrupted backup generator & security',
                    'Preferential group bedroom rates for attendees',
                ),
            ),
        );
    }

    /**
     * Build the two side-by-side Stay Plan cards from the live room catalog.
     *
     * @param array $bedrooms
     * @param array $common
     * @return array
     */
    protected static function buildPricingPlans(array $bedrooms, array $common)
    {
        $deluxe = array(
            'name' => 'Double Standard Room',
            'desc' => 'Stylish interiors & serene comfort designed to make your business or leisure stay relaxing.',
            'price' => 600.0,
            'book_url' => $common['search_url'],
        );
        $luxury = array(
            'name' => 'Luxury Suite',
            'desc' => 'Our premier accommodation offering generous living space, top-tier amenities & elevated views.',
            'price' => 900.0,
            'book_url' => $common['search_url'],
        );

        foreach ($bedrooms as $rm) {
            if (stripos($rm['name'], 'double') !== false) {
                $deluxe['name'] = $rm['name'];
                $deluxe['price'] = (float) $rm['price'];
                $deluxe['book_url'] = $rm['book_url'];
            } elseif (stripos($rm['name'], 'suite') !== false) {
                $luxury['name'] = $rm['name'];
                $luxury['price'] = (float) $rm['price'];
                $luxury['book_url'] = $rm['book_url'];
            }
        }

        return array(
            array(
                'title' => $deluxe['name'],
                'subtitle' => $deluxe['desc'],
                'price_formatted' => number_format($deluxe['price'], 2),
                'period' => 'For One Night (All-Inclusive)',
                'book_url' => $deluxe['book_url'],
                'featured' => false,
                'features' => array(
                    'Plush Queen / Double bed with crisp linens',
                    'Complimentary high-speed Wi-Fi access',
                    'Air-conditioned comfort & 24/7 power',
                    'Smart flat-screen TV & mini-fridge',
                    'Private modern en-suite bathroom',
                    '24/7 front desk & room service available',
                ),
            ),
            array(
                'title' => $luxury['name'],
                'subtitle' => $luxury['desc'],
                'price_formatted' => number_format($luxury['price'], 2),
                'period' => 'For One Night (All-Inclusive)',
                'book_url' => $luxury['book_url'],
                'featured' => true,
                'features' => array(
                    'King-size bed & spacious lounge seating',
                    'Tranquil elevated views & executive desk',
                    'Access to restaurant, lounge bar & grounds',
                    'In-room dining & laundry service',
                    'Secure gated parking with 24-hour security',
                    'Dedicated priority guest assistance',
                ),
            ),
        );
    }

    /**
     * Load real guest testimonials from `qlo_htl_testimonials_block_data`.
     *
     * @param Context $context
     * @param int $idLang
     * @return array
     */
    protected static function loadTestimonials(Context $context, $idLang)
    {
        $p = _DB_PREFIX_;
        $out = array();
        $hasTbl = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($p . 'htl_testimonials_block_data') . "'", true, false);
        if (!empty($hasTbl)) {
            $rows = Db::getInstance()->executeS(
                'SELECT t.`id_testimonial_block`, t.`name`, t.`designation`, tl.`testimonial_content`
                 FROM `' . $p . 'htl_testimonials_block_data` t
                 INNER JOIN `' . $p . 'htl_testimonials_block_data_lang` tl
                   ON (tl.`id_testimonial_block` = t.`id_testimonial_block` AND tl.`id_lang` = ' . (int) $idLang . ')
                 WHERE t.`active` = 1
                 ORDER BY t.`position` ASC, t.`id_testimonial_block` ASC
                 LIMIT 8'
            );
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $idT = (int) $r['id_testimonial_block'];
                    $avatar = '';
                    $disk = _PS_MODULE_DIR_ . 'wktestimonialblock/views/img/hotels_testimonials_img/' . $idT . '.jpg';
                    if (file_exists($disk)) {
                        $avatar = $context->link->getMediaLink(
                            _MODULE_DIR_ . 'wktestimonialblock/views/img/hotels_testimonials_img/' . $idT . '.jpg'
                        );
                    }
                    $name = trim((string) $r['name']);
                    $quote = trim(strip_tags((string) $r['testimonial_content']));
                    if ($quote === '') {
                        continue;
                    }
                    $initials = '';
                    foreach (preg_split('/\s+/', $name) as $part) {
                        if ($part !== '') {
                            $initials .= Tools::strtoupper(Tools::substr($part, 0, 1));
                        }
                    }
                    $out[] = array(
                        'name' => $name !== '' ? $name : 'Verified Guest',
                        'role' => !empty($r['designation']) ? (string) $r['designation'] : 'Hotel Guest',
                        'quote' => $quote,
                        'avatar' => $avatar,
                        'initials' => Tools::substr($initials, 0, 2) ?: 'LV',
                    );
                }
            }
        }

        if (empty($out)) {
            $out[] = array(
                'name' => 'Paul Kumi',
                'role' => 'CEO',
                'quote' => 'Lush View Hotel surpassed my expectations in every way. The Executive Room was not only luxurious but also provided a peaceful retreat with stunning views and personalized service.',
                'avatar' => '',
                'initials' => 'PK',
            );
        }

        return $out;
    }
}
