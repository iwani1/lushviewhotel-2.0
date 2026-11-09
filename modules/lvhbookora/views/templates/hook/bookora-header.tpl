{*
* Lush View Hotel - Bookora Header 2: glassmorphic navigation + dropdown menus,
* bookmarks / account / cart badges, Book Now CTA, mobile drawer, and the
* shared breadcrumb page hero.
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div id="bk-header-shell" class="bk-root bk-h2">

    <!-- ============ Header 2 — Glassmorphic Main Navigation ============ -->
    <div id="bk-navbar" class="bk-h2-nav">
        <div class="bk-container bk-h2-nav__inner">

            <a href="{$bk.home_url|escape:'html':'UTF-8'}" class="bk-h2-brand" title="{$bk.hotel_name|escape:'html':'UTF-8'}">
                <img src="{$bk.module_img|escape:'html':'UTF-8'}logo.svg" alt="{$bk.hotel_name|escape:'html':'UTF-8'}" class="bk-h2-brand__logo">
            </a>

            <nav class="bk-h2-menu" aria-label="Primary Navigation">
                <div class="bk-h2-item bk-h2-item--drop">
                    <a href="{$bk.home_url|escape:'html':'UTF-8'}" class="bk-h2-link{if $bk.is_home} is-active{/if}">Home
                        <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
                    </a>
                    <div class="bk-h2-drop">
                        <a href="{$bk.home_url|escape:'html':'UTF-8'}">Home 2 <span>— The Luxury Hotel</span></a>
                        <a href="{$bk.home1_url|escape:'html':'UTF-8'}">Home 1 <span>— Classic Editorial</span></a>
                        <a href="{$bk.home3_url|escape:'html':'UTF-8'}">Home 3 <span>— Resort &amp; Dining</span></a>
                    </div>
                </div>

                <a href="{$bk.about_url|escape:'html':'UTF-8'}" class="bk-h2-link">About Us</a>

                <div class="bk-h2-item bk-h2-item--drop">
                    <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-h2-link">Hotels
                        <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
                    </a>
                    <div class="bk-h2-drop">
                        <a href="{$bk.search_url|escape:'html':'UTF-8'}">All Listings <span>— Rooms &amp; Suites</span></a>
                        <a href="{$bk.search_page_url|escape:'html':'UTF-8'}">Search Result <span>— Find Availability</span></a>
                        <a href="{$bk.bookmarks_url|escape:'html':'UTF-8'}">Bookmarks <span>— Saved Rooms</span></a>
                    </div>
                </div>

                <div class="bk-h2-item bk-h2-item--drop">
                    <a href="javascript:void(0)" class="bk-h2-link">Pages
                        <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
                    </a>
                    <div class="bk-h2-drop">
                        <a href="{$bk.service_url|escape:'html':'UTF-8'}">Our Services</a>
                        <a href="{$bk.project_url|escape:'html':'UTF-8'}">Our Projects</a>
                        <a href="{$bk.team_url|escape:'html':'UTF-8'}">Our Team</a>
                        <a href="{$bk.testimonial_url|escape:'html':'UTF-8'}">Testimonials</a>
                        <a href="{$bk.pricing_url|escape:'html':'UTF-8'}">Pricing</a>
                        <a href="{$bk.login_url|escape:'html':'UTF-8'}">Sign In</a>
                        <a href="{$bk.account_url|escape:'html':'UTF-8'}">My Account</a>
                        <a href="{$bk.cart_url|escape:'html':'UTF-8'}">Cart</a>
                    </div>
                </div>

                <div class="bk-h2-item bk-h2-item--drop">
                    <a href="{$bk.blog_url|escape:'html':'UTF-8'}" class="bk-h2-link">Blog
                        <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
                    </a>
                    <div class="bk-h2-drop">
                        {foreach from=$bk_recent_posts item=post}
                            <a href="{$post.url|escape:'html':'UTF-8'}">{$post.title|truncate:38:'…'|escape:'html':'UTF-8'}</a>
                        {/foreach}
                        <a href="{$bk.blog_url|escape:'html':'UTF-8'}">View All Articles</a>
                    </div>
                </div>

                <a href="{$bk.contact_url|escape:'html':'UTF-8'}" class="bk-h2-link">Contact Us</a>
            </nav>

            <div class="bk-h2-actions">
                <a href="{$bk.bookmarks_url|escape:'html':'UTF-8'}" class="bk-ico-btn" title="Bookmarks" aria-label="Bookmarks">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                    <span id="bk-bookmark-count" class="bk-ico-btn__badge">0</span>
                </a>

                <a href="{if $bk.is_logged}{$bk.account_url|escape:'html':'UTF-8'}{else}{$bk.login_url|escape:'html':'UTF-8'}{/if}" class="bk-h2-signin">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>{if $bk.is_logged && $bk.customer_firstname}{$bk.customer_firstname|escape:'html':'UTF-8'}{else}Sign In{/if}</span>
                </a>

                <a href="{$bk.cart_url|escape:'html':'UTF-8'}" class="bk-ico-btn" title="Booking Cart" aria-label="Cart">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    <span id="bk-cart-count" class="bk-ico-btn__badge">{$bk.cart_qty|intval}</span>
                </a>

                <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold bk-h2-cta">Book Now</a>

                <button type="button" id="bk-mobile-toggle" class="bk-burger" aria-label="Open Menu" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </div>

    {if isset($bk_hero) && $bk_hero}
    <!-- ============ Shared Breadcrumb Page Hero ============ -->
    <div class="bk-page-hero" style="background-image: url('{$bk.module_img|escape:'html':'UTF-8'}hero-home2.jpg');">
        <div class="bk-page-hero__overlay"></div>
        <div class="bk-container bk-page-hero__inner">
            <h1 class="bk-page-hero__title">{$bk_hero.title|escape:'html':'UTF-8'}</h1>
            <div class="bk-crumbs">
                <a href="{$bk.home_url|escape:'html':'UTF-8'}">Home</a>
                <span class="bk-crumbs__sep">—</span>
                <span>{$bk_hero.crumb|escape:'html':'UTF-8'}</span>
            </div>
        </div>
    </div>
    {/if}

    <!-- ============ Mobile Navigation Drawer ============ -->
    <div id="bk-mobile-drawer" class="bk-drawer" aria-hidden="true">
        <div class="bk-drawer__backdrop" data-bk-close-drawer></div>
        <div class="bk-drawer__panel">
            <div class="bk-drawer__head">
                <span class="bk-h2-brand__name">{$bk.hotel_name|escape:'html':'UTF-8'}</span>
                <button type="button" class="bk-drawer__close" data-bk-close-drawer aria-label="Close Menu">&times;</button>
            </div>
            <div class="bk-drawer__links">
                <a href="{$bk.home_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Home 2</a>
                <a href="{$bk.home1_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Home 1</a>
                <a href="{$bk.home3_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Home 3</a>
                <a href="{$bk.about_url|escape:'html':'UTF-8'}" data-bk-close-drawer>About Us</a>
                <a href="{$bk.search_url|escape:'html':'UTF-8'}" data-bk-close-drawer>All Listings</a>
                <a href="{$bk.bookmarks_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Bookmarks</a>
                <a href="{$bk.service_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Our Services</a>
                <a href="{$bk.project_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Our Projects</a>
                <a href="{$bk.team_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Our Team</a>
                <a href="{$bk.testimonial_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Testimonials</a>
                <a href="{$bk.pricing_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Pricing</a>
                <a href="{$bk.blog_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Blog</a>
                <a href="{$bk.contact_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Contact Us</a>
                <a href="{if $bk.is_logged}{$bk.account_url|escape:'html':'UTF-8'}{else}{$bk.login_url|escape:'html':'UTF-8'}{/if}" data-bk-close-drawer>
                    {if $bk.is_logged}My Account{else}Sign In{/if}
                </a>
            </div>
        </div>
    </div>
</div>
