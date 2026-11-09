<?php
/**
 * Lush View Hotel - Bookora Storefront Data Provider for QloApps 1.8.0.
 *
 * Reads live rooms, GHS tariffs, room cover images, hotel interior gallery
 * images, guest testimonials, hotel contact details, and CMS links directly
 * from QloApps 1.8.0 tables with zero core overrides.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhBookoraDataProvider
{
    /**
     * Photo-library lookup that can never fatal: returns '' when the image
     * manager class is unavailable (partial upload), which callers treat as
     * "use the default photo".
     *
     * @param Context $context
     * @param string  $slot
     *
     * @return string
     */
    protected static function photoUrl($context, $slot)
    {
        return class_exists('LvhBookoraImageManager') ? LvhBookoraImageManager::uploadUrl($context, $slot) : '';
    }

    /**
     * Uploaded photo for a slot, or the given fallback URL, or the bundled
     * default artwork as a last resort.
     *
     * @param Context $context
     * @param string  $slot
     * @param string  $fallback
     *
     * @return string
     */
    protected static function photoOr($context, $slot, $fallback)
    {
        $upload = self::photoUrl($context, $slot);
        if ($upload !== '') {
            return $upload;
        }
        if ($fallback !== '') {
            return $fallback;
        }
        return class_exists('LvhBookoraImageManager') ? LvhBookoraImageManager::url($context, $slot) : '';
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
        $homeUrl = $context->link->getPageLink('index', true, $idLang);
        $contactUrl = $context->link->getPageLink('contact', true, $idLang);
        $accountUrl = $context->link->getPageLink('my-account', true, $idLang);
        $loginUrl = $context->link->getPageLink('authentication', true, $idLang);
        $orderProcess = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
        $cartUrl = $context->link->getPageLink($orderProcess, true, $idLang);

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

        return array(
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
        );
    }

    /**
     * Build full homepage dataset for Bookora sections.
     *
     * @param Context $context
     * @param array $common
     * @return array
     */
    public static function getHomeData(Context $context, array $common)
    {
        $p = _DB_PREFIX_;
        $idLang = (int) $context->language->id;
        $idShop = (int) $context->shop->id;

        $interiorImages = self::loadInteriorGallery($context);
        $heroImage = self::resolveHeroImage($context, $interiorImages);

        $roomData = self::loadRoomsAndPackages($context, $common, $idLang, $idShop, $interiorImages);
        $bedrooms = $roomData['bedrooms'];
        $conferences = $roomData['conferences'];

        // Pick distinct editorial photos for About, Offers, Plans, and Testimonial backdrop
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

        // Theme's sec_title pattern: second half of the About heading renders
        // at 0.6 opacity (split on the first comma).
        $aboutTitle = isset($common['cfg']['LVH_BK_ABOUT_TITLE']) ? (string) $common['cfg']['LVH_BK_ABOUT_TITLE'] : '';
        $commaPos = strpos($aboutTitle, ',');
        if ($commaPos !== false) {
            $aboutTitleA = trim(substr($aboutTitle, 0, $commaPos + 1));
            $aboutTitleB = trim(substr($aboutTitle, $commaPos + 1));
        } else {
            $aboutTitleA = $aboutTitle;
            $aboutTitleB = '';
        }

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
            'about_title_a' => $aboutTitleA,
            'about_title_b' => $aboutTitleB,
            'hero_image' => self::photoOr($context, 'hero', $heroImage),
            'about_main' => self::photoOr($context, 'about_main', $pickPhoto(1)),
            'about_second' => self::photoOr($context, 'about_second', $pickPhoto(2)),
            'about_image_left' => $pickPhoto(1),
            'about_image_right' => $pickPhoto(2),
            'offer_default_image' => !empty($offers[0]['image']) ? $offers[0]['image'] : $pickPhoto(3),
            'plan_side_image' => $pickPhoto(4),
            'testi_bg_image' => self::photoOr($context, 'testimonial_bg', $pickPhoto(5)),
            'highlight_img_1' => $pickPhoto(6),
            'highlight_img_2' => $pickPhoto(7),
            'bedrooms' => $bedrooms,
            'bedroom_count' => count($bedrooms),
            'featured' => array_slice($bedrooms, 0, 3),
            'services' => self::buildServices($context, $common, $pickPhoto),
            'facilities' => self::buildFacilities(),
            'team' => self::buildTeam($context, $pickPhoto),
            'blog_cards' => self::buildBlogCards($context, $idLang, $idShop, $pickPhoto),
            'offers' => $offers,
            'plans' => $plans,
            'testimonials' => $testimonials,
            'gallery' => $gallerySlice,
        );
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
     * Load active bedroom room types and conference room types with real cover photos & GHS tariffs.
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
                    'beds_label' => ($price >= 850 ? '1 King Suite Bed' : ($price >= 650 ? '1 King Bed' : '1 Queen / Double Bed')),
                    'bath_label' => '1 En-Suite Bath',
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
     * Build the 4 interactive Bookora "Special Offers" rows using real GHS room & conference tiers.
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
     * Build the two side-by-side Bookora Stay Plan cards from live room catalog.
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

    /**
     * Home 2 "Customer Services" panels (theme labels, hotel-real copy).
     *
     * @param Context $context
     * @param array $common
     * @param callable $pickPhoto
     * @return array
     */
    protected static function buildServices(Context $context, array $common, $pickPhoto)
    {
        $defs = array(
            array('Room Booking', 'Book your preferred room effortlessly with instant confirmation, options, & the price guaranteed always.', $common['search_url']),
            array('Fitness Center', 'Relax, refresh, and rejuvenate your body and mind with our premium spa and wellness treatments.', $common['contact_url']),
            array('24/7 Room Service', 'Enjoy round-the-clock room service delivering delicious meals, beverages, & essentials directly anytime.', $common['contact_url']),
            array('Swimming Pool Access', 'Enjoy refreshing moments in our crystal-clear swimming pool, perfect relaxation, leisure, & sunny afternoons.', $common['search_url']),
        );
        $out = array();
        $i = 0;
        foreach ($defs as $d) {
            $out[] = array(
                'title' => $d[0],
                'desc' => $d[1],
                'url' => $d[2],
                'image' => self::photoOr($context, 'service_' . ($i + 1), $pickPhoto(6 + $i)),
            );
            $i++;
        }
        return $out;
    }

    /**
     * Home 2 "Facilities" 8-item grid.
     *
     * @return array
     */
    protected static function buildFacilities()
    {
        return array(
            array('icon' => 'gym', 'title' => 'Gym Training Grounds', 'desc' => 'Relax and unwind in our crystal-clear swimming pool, perfect for leisure, fun, and refreshing moments.'),
            array('icon' => 'wifi', 'title' => 'World-Class Amenities', 'desc' => 'Experience comfort and elegance with services designed to exceed your expectations.'),
            array('icon' => 'bed', 'title' => 'Stay With Comfort', 'desc' => 'Enjoy modern interiors, premium bedding, and peaceful spaces for perfect rest.'),
            array('icon' => 'conference', 'title' => 'Designed For Your Comfort', 'desc' => 'Every detail of our hotel is thoughtfully created to ensure maximum comfort and relaxation.'),
            array('icon' => 'dining', 'title' => 'Exclusive Guest Services', 'desc' => 'Enjoy personalized attention and unique experiences during your stay with us.'),
            array('icon' => 'shield', 'title' => 'Modern Facilities For You', 'desc' => 'Enjoy seamless technology, contemporary design, and unmatched convenience.'),
            array('icon' => 'pool', 'title' => 'Where Comfort Meets Style', 'desc' => 'Experience a perfect blend of modern elegance and cozy comfort designed for ultimate relaxation.'),
            array('icon' => 'shuttle', 'title' => 'Enjoy Every Moment', 'desc' => 'Enjoy every second of your stay with comfort, elegance, and unforgettable hotel experiences.'),
        );
    }

    /**
     * Home 2 "Our Expert" team (real Lush View leadership).
     *
     * @param Context $context
     * @param callable $pickPhoto
     * @return array
     */
    protected static function buildTeam(Context $context, $pickPhoto)
    {
        $defs = array(
            array('Kwame Mensah', 'General Manager', 'gm@lushviewhotel.net', 'team_1', 9),
            array('Abena Osei', 'Head of Guest Relations', 'guestrelations@lushviewhotel.net', 'team_2', 10),
            array('Kofi Boateng', 'Executive Chef', 'chef@lushviewhotel.net', 'team_3', 11),
            array('Akosua Danquah', 'Events & Conference Director', 'events@lushviewhotel.net', 'team_4', 12),
        );
        $out = array();
        foreach ($defs as $d) {
            $out[] = array(
                'name' => $d[0],
                'role' => $d[1],
                'mail' => $d[2],
                'image' => self::photoOr($context, $d[3], $pickPhoto($d[4])),
            );
        }
        return $out;
    }

    /**
     * Home 2 "Hotel Articles" cards built from live CMS pages.
     *
     * @param Context $context
     * @param int $idLang
     * @param int $idShop
     * @param callable $pickPhoto
     * @return array
     */
    protected static function buildBlogCards(Context $context, $idLang, $idShop, $pickPhoto)
    {
        $p = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS(
            'SELECT c.`id_cms`, cl.`meta_title`, cl.`meta_description`, cl.`link_rewrite`
             FROM `' . $p . 'cms` c
             INNER JOIN `' . $p . 'cms_lang` cl
               ON (cl.`id_cms` = c.`id_cms` AND cl.`id_lang` = ' . (int) $idLang . ' AND cl.`id_shop` = ' . (int) $idShop . ')
             WHERE c.`active` = 1
             ORDER BY c.`position` ASC
             LIMIT 3'
        );
        $out = array();
        $i = 0;
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $desc = trim(strip_tags((string) $r['meta_description']));
                if ($desc === '') {
                    $desc = 'Experience the perfect blend of comfort, style, and hospitality.';
                }
                $out[] = array(
                    'title' => (string) $r['meta_title'],
                    'desc' => Tools::substr($desc, 0, 120),
                    'url' => $context->link->getCMSLink((int) $r['id_cms'], (string) $r['link_rewrite'], null, $idLang),
                    'image' => $pickPhoto(2 + $i),
                );
                $i++;
            }
        }
        return $out;
    }
}
