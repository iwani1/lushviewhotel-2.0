<?php
/**
 * Lush View Hotel - Bookora Route Map & Static Content Definitions for QloApps 1.8.0.
 *
 * Declares the Bookora module front routes (`module-lvhbookora-*`), default
 * configuration values, page hero titles, and static map/geo helpers shared by
 * the module class and the data provider.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class LvhBookoraRouteMap
{
    /**
     * Friendly URL rules registered through `hookModuleRoutes`.
     *
     * @return array
     */
    public static function routes()
    {
        $ctrl = array('fc' => 'module', 'module' => 'lvhbookora');
        return array(
            'module-lvhbookora-home1' => array(
                'rule' => 'home-1',
                'keywords' => array(),
                'controller' => 'home1',
                'params' => $ctrl,
            ),
            'module-lvhbookora-home3' => array(
                'rule' => 'home-3',
                'keywords' => array(),
                'controller' => 'home3',
                'params' => $ctrl,
            ),
            'module-lvhbookora-about' => array(
                'rule' => 'about',
                'keywords' => array(),
                'controller' => 'about',
                'params' => $ctrl,
            ),
            'module-lvhbookora-service' => array(
                'rule' => 'service',
                'keywords' => array(),
                'controller' => 'service',
                'params' => $ctrl,
            ),
            'module-lvhbookora-project' => array(
                'rule' => 'project',
                'keywords' => array(),
                'controller' => 'project',
                'params' => $ctrl,
            ),
            'module-lvhbookora-team' => array(
                'rule' => 'team',
                'keywords' => array(),
                'controller' => 'team',
                'params' => $ctrl,
            ),
            'module-lvhbookora-testimonial' => array(
                'rule' => 'testimonial',
                'keywords' => array(),
                'controller' => 'testimonial',
                'params' => $ctrl,
            ),
            'module-lvhbookora-pricing' => array(
                'rule' => 'pricing',
                'keywords' => array(),
                'controller' => 'pricing',
                'params' => $ctrl,
            ),
            'module-lvhbookora-blog' => array(
                'rule' => 'blog',
                'keywords' => array(),
                'controller' => 'blog',
                'params' => $ctrl,
            ),
            'module-lvhbookora-article' => array(
                'rule' => 'blog/{slug}',
                'keywords' => array(
                    'slug' => array('regexp' => '[^/]+', 'param' => 'slug'),
                ),
                'controller' => 'article',
                'params' => $ctrl,
            ),
            'module-lvhbookora-bookmarks' => array(
                'rule' => 'bookmarks',
                'keywords' => array(),
                'controller' => 'bookmarks',
                'params' => $ctrl,
            ),
            'module-lvhbookora-subscribe' => array(
                'rule' => 'newsletter-subscribe',
                'keywords' => array(),
                'controller' => 'subscribe',
                'params' => $ctrl,
            ),
        );
    }

    /**
     * Module front controllers that must exist for the routes above.
     *
     * @return array
     */
    public static function pageControllers()
    {
        return array('home1', 'home3', 'about', 'service', 'project', 'team', 'testimonial', 'pricing', 'blog', 'article', 'bookmarks', 'subscribe');
    }

    /**
     * Default customizable Bookora copy & palette settings.
     *
     * @return array
     */
    public static function configDefaults()
    {
        return array(
            'LVH_BK_ENABLED' => '1',
            'LVH_BK_EYEBROW' => 'Welcome To Lush View Hotel',
            'LVH_BK_HERO_LINE1' => 'Your Next Booking',
            'LVH_BK_HERO_LINE2' => 'Luxury Hotel',
            'LVH_BK_HERO_SUB' => 'Experience world-class Ghanaian hospitality where luxury, serene views, and timeless comfort come together for an unforgettable stay.',
            'LVH_BK_ABOUT_TITLE' => 'Experience Comfort, Elegance & Exceptional Hospitality',
            'LVH_BK_ABOUT_BODY' => 'Nestled in a tranquil setting with effortless access to the city, Lush View Hotel blends contemporary luxury with attentive personal service—crafted for discerning business leaders, conference delegates, and leisure travelers.',
            'LVH_BK_STAT1_NUM' => '80k+',
            'LVH_BK_STAT1_LABEL' => 'Happy Customer',
            'LVH_BK_STAT2_NUM' => '34+',
            'LVH_BK_STAT2_LABEL' => 'Luxury Bedrooms, Suites & Event Hall',
            'LVH_BK_COLOR_TEAL' => '#14110d',
            'LVH_BK_COLOR_GOLD' => '#c9a063',
        );
    }

    /**
     * Breadcrumb hero titles for native QloApps pages (php_self => title).
     *
     * @return array
     */
    public static function nativeHeroTitles()
    {
        return array(
            'category' => 'All Listings',
            'product' => 'Room Details',
            'contact' => 'Contact Us',
            'authentication' => 'Sign In',
            'password' => 'Reset Password',
            'my-account' => 'My Account',
            'history' => 'Order History',
            'identity' => 'Your Account',
            'addresses' => 'Your Addresses',
            'order-opc' => 'Cart & Checkout',
            'order' => 'Cart & Checkout',
            'order-confirmation' => 'Order Confirmation',
            'order-detail' => 'Order Details',
            'cms' => 'Information',
            'cms-content' => 'Information',
            'sitemap' => 'Sitemap',
            '404' => 'Page Not Found',
            'pagenotfound' => 'Page Not Found',
            'discount' => 'Your Vouchers',
            'guest-tracking' => 'Guest Tracking',
            'stores' => 'Our Properties',
            'our-properties' => 'Our Properties',
            'search' => 'Search Results',
            'compare' => 'Compare Rooms',
        );
    }

    /**
     * Breadcrumb hero titles for Bookora module pages (controller => title).
     *
     * @return array
     */
    public static function moduleHeroTitles()
    {
        return array(
            'about' => 'About Us',
            'service' => 'Our Services',
            'project' => 'Our Projects',
            'team' => 'Our Team',
            'testimonial' => 'Testimonials',
            'pricing' => 'Pricing',
            'blog' => 'Blog',
            'article' => 'Blog Details',
            'bookmarks' => 'Bookmarks',
            'subscribe' => 'Newsletter',
        );
    }

    /**
     * Embedded OpenStreetMap view for the Accra hotel location.
     *
     * @return string
     */
    public static function mapEmbedUrl()
    {
        return 'https://www.openstreetmap.org/export/embed.html?bbox=-0.2140%2C5.5860%2C-0.1600%2C5.6210&layer=mapnik&marker=5.6037%2C-0.1870';
    }

    /**
     * Turn-by-turn directions URL to the hotel.
     *
     * @return string
     */
    public static function mapDirectionsUrl()
    {
        return 'https://www.google.com/maps/dir/?api=1&destination=5.6037,-0.1870';
    }
}
