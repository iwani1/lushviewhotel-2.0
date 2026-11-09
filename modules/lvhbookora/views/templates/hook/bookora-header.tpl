{*
* Lush View Hotel - Bookora Top Utility Bar & Sticky Navigation Header
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div id="bk-header-shell" class="bk-root">
    <!-- Top Deep Forest Teal Utility Bar -->
    <div class="bk-topbar">
        <div class="bk-container bk-topbar__inner">
            <div class="bk-topbar__socials" aria-label="Social Links">
                <a href="{if $bk.social.facebook}{$bk.social.facebook|escape:'html':'UTF-8'}{else}#bk-footer{/if}" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z"/></svg>
                </a>
                <a href="{if $bk.social.instagram}{$bk.social.instagram|escape:'html':'UTF-8'}{else}#bk-footer{/if}" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
                </a>
                <a href="{if $bk.social.twitter}{$bk.social.twitter|escape:'html':'UTF-8'}{else}#bk-footer{/if}" aria-label="X">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a href="tel:{$bk.hotel_phone_href|escape:'html':'UTF-8'}" aria-label="Call Front Desk">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </a>
            </div>
            <div class="bk-topbar__contacts">
                <a href="tel:{$bk.hotel_phone_href|escape:'html':'UTF-8'}" class="bk-topbar__item">
                    <span class="bk-topbar__dot">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </span>
                    <span>{$bk.hotel_phone|escape:'html':'UTF-8'}</span>
                </a>
                <span class="bk-topbar__item bk-hide-xs">
                    <span class="bk-topbar__dot">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </span>
                    <span>{$bk.hotel_address|escape:'html':'UTF-8'}</span>
                </span>
                <a href="mailto:{$bk.hotel_email|escape:'html':'UTF-8'}" class="bk-topbar__item bk-hide-sm">
                    <span class="bk-topbar__dot">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                    </span>
                    <span>{$bk.hotel_email|escape:'html':'UTF-8'}</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Sticky Bookora Navigation Bar -->
    <div id="bk-navbar" class="bk-navbar">
        <div class="bk-container bk-navbar__inner">
            <a href="{$bk.home_url|escape:'html':'UTF-8'}" class="bk-brand" title="{$bk.hotel_name|escape:'html':'UTF-8'}">
                {if $bk.logo_url}
                    <img src="{$bk.logo_url|escape:'html':'UTF-8'}" alt="{$bk.hotel_name|escape:'html':'UTF-8'}" class="bk-brand__logo">
                {else}
                    <span class="bk-brand__crest" aria-hidden="true">
                        <svg viewBox="0 0 36 36" width="32" height="32">
                            <rect x="2" y="2" width="32" height="32" rx="6" fill="#0b3b36"/>
                            <path d="M11 10v16h12v-3h-8.5V10H11zm9.5 0L26 23h-3.4l-3.1-7.8L16.4 23H13l5.5-13h2z" fill="#c2a275"/>
                        </svg>
                    </span>
                {/if}
                <span class="bk-brand__name">{$bk.hotel_name|escape:'html':'UTF-8'}</span>
            </a>

            <nav class="bk-nav" aria-label="Primary Navigation">
                <a href="{$bk.home_url|escape:'html':'UTF-8'}" class="bk-nav__link{if $bk.is_home} is-active{/if}">Home</a>
                <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-nav__link{if !$bk.is_home} is-active{/if}">Rooms &amp; Suites</a>
                <a href="{if $bk.is_home}#bk-about{else}{$bk.home_url|escape:'html':'UTF-8'}#bk-about{/if}" class="bk-nav__link">About</a>
                <a href="{if $bk.is_home}#bk-facilities{else}{$bk.home_url|escape:'html':'UTF-8'}#bk-facilities{/if}" class="bk-nav__link">Facilities</a>
                <a href="{if $bk.is_home}#bk-offers{else}{$bk.home_url|escape:'html':'UTF-8'}#bk-offers{/if}" class="bk-nav__link">Offers &amp; Hall</a>
                <a href="{if $bk.is_home}#bk-gallery{else}{$bk.home_url|escape:'html':'UTF-8'}#bk-gallery{/if}" class="bk-nav__link">Gallery</a>
                <a href="{$bk.contact_url|escape:'html':'UTF-8'}" class="bk-nav__link">Contact</a>
            </nav>

            <div class="bk-navbar__actions">
                <a href="{$bk.cart_url|escape:'html':'UTF-8'}" class="bk-cart-pill" title="View Booking Cart">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    <span id="bk-cart-count" class="bk-cart-pill__badge">{$bk.cart_qty|intval}</span>
                </a>

                {if $bk.is_logged}
                    <a href="{$bk.account_url|escape:'html':'UTF-8'}" class="bk-signin-link">
                        {if $bk.customer_firstname}{$bk.customer_firstname|escape:'html':'UTF-8'}{else}My Account{/if}
                    </a>
                {else}
                    <a href="{$bk.login_url|escape:'html':'UTF-8'}" class="bk-signin-link">Sign In</a>
                {/if}

                <a href="{if $bk.is_home}#bk-booking-bar{else}{$bk.search_url|escape:'html':'UTF-8'}{/if}" class="bk-btn bk-btn--gold bk-navbar__cta">
                    Book Now
                </a>

                <button type="button" id="bk-mobile-toggle" class="bk-burger" aria-label="Open Menu" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Drawer -->
    <div id="bk-mobile-drawer" class="bk-drawer" aria-hidden="true">
        <div class="bk-drawer__backdrop" data-bk-close-drawer></div>
        <div class="bk-drawer__panel">
            <div class="bk-drawer__head">
                <span class="bk-brand__name">{$bk.hotel_name|escape:'html':'UTF-8'}</span>
                <button type="button" class="bk-drawer__close" data-bk-close-drawer aria-label="Close Menu">&times;</button>
            </div>
            <div class="bk-drawer__links">
                <a href="{$bk.home_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Home</a>
                <a href="{$bk.search_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Rooms &amp; Suites</a>
                <a href="{if $bk.is_home}#bk-about{else}{$bk.home_url|escape:'html':'UTF-8'}#bk-about{/if}" data-bk-close-drawer>About Us</a>
                <a href="{if $bk.is_home}#bk-facilities{else}{$bk.home_url|escape:'html':'UTF-8'}#bk-facilities{/if}" data-bk-close-drawer>Hotel Facilities</a>
                <a href="{if $bk.is_home}#bk-offers{else}{$bk.home_url|escape:'html':'UTF-8'}#bk-offers{/if}" data-bk-close-drawer>Special Offers &amp; Conference</a>
                <a href="{if $bk.is_home}#bk-gallery{else}{$bk.home_url|escape:'html':'UTF-8'}#bk-gallery{/if}" data-bk-close-drawer>Interior Gallery</a>
                <a href="{$bk.contact_url|escape:'html':'UTF-8'}" data-bk-close-drawer>Contact Us</a>
                <a href="{if $bk.is_logged}{$bk.account_url|escape:'html':'UTF-8'}{else}{$bk.login_url|escape:'html':'UTF-8'}{/if}" data-bk-close-drawer>
                    {if $bk.is_logged}My Account{else}Guest Sign In{/if}
                </a>
            </div>
            <div class="bk-drawer__foot">
                <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold bk-btn--block">Check Room Availability</a>
                <p class="bk-drawer__phone">Front Desk: <a href="tel:{$bk.hotel_phone_href|escape:'html':'UTF-8'}">{$bk.hotel_phone|escape:'html':'UTF-8'}</a></p>
            </div>
        </div>
    </div>
</div>
