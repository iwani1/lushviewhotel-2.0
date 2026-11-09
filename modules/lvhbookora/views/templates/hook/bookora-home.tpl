{*
* Lush View Hotel - Bookora Home 2 (structure from the original Bookora theme
* package: hero, about-2, popular-rooms slider, customer services, featured
* room showcase, facilities, our expert team, guest testimonials, hotel
* articles) — powered by live QloApps 1.8.0 rooms, prices, testimonials & CMS.
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div id="bk-home-root" class="bk-root bk-home bk2-home">

    <!-- ===================================================================
         1. BOOKORA SPLIT EDITORIAL HERO + FROSTED AVAILABILITY SEARCH BAR
         =================================================================== -->
    <section class="bk-hero">
        <div class="bk-hero__upper">
            <div class="bk-hero__lines" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span><span></span>
            </div>
            <div class="bk-container bk-hero__headline-wrap">
                <div class="bk-eyebrow bk-eyebrow--center">
                    <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                    <span class="bk-eyebrow__text">{$bk.cfg.LVH_BK_EYEBROW|escape:'html':'UTF-8'}</span>
                    <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                </div>
                <h1 class="bk-hero__title">
                    {$bk.cfg.LVH_BK_HERO_LINE1|escape:'html':'UTF-8'}<br>
                    <span>{$bk.cfg.LVH_BK_HERO_LINE2|escape:'html':'UTF-8'}</span>
                </h1>
                <div class="bk-hero__ctas">
                    <a href="#bk2-about" class="bk-btn bk-btn--outline">Explore More</a>
                    <a href="#bk-booking-bar" class="bk-btn bk-btn--gold">Booking Now</a>
                </div>
            </div>
        </div>

        <div id="bk-booking-bar" class="bk-hero__visual" style="background-image: url('{$bk_home.hero_image|escape:'html':'UTF-8'}');">
            <div class="bk-hero__overlay"></div>
            <div class="bk-container bk-hero__visual-inner">

                <!-- Glassmorphic Pill Search & Availability Bar (live QloApps) -->
                <form id="bk-availability-form" class="bk-search-pill" action="{$bk.search_url|escape:'html':'UTF-8'}" method="get">
                    <div class="bk-search-pill__field">
                        <label for="bk-checkin">Check-In</label>
                        <div class="bk-search-pill__control">
                            <input type="date" id="bk-checkin" name="date_from" value="{$bk.default_checkin|escape:'html':'UTF-8'}" min="{$bk.default_checkin|escape:'html':'UTF-8'}" required>
                        </div>
                    </div>

                    <div class="bk-search-pill__field">
                        <label for="bk-checkout">Check-Out</label>
                        <div class="bk-search-pill__control">
                            <input type="date" id="bk-checkout" name="date_to" value="{$bk.default_checkout|escape:'html':'UTF-8'}" min="{$bk.default_checkin|escape:'html':'UTF-8'}" required>
                        </div>
                    </div>

                    <div class="bk-search-pill__field">
                        <label for="bk-room-cat">Room Preference</label>
                        <div class="bk-search-pill__control">
                            <select id="bk-room-cat" name="bk_room_pref">
                                <option value="">All Rooms &amp; Suites</option>
                                {foreach from=$bk_home.bedrooms item=rm}
                                    <option value="{$rm.id_product|intval}">{$rm.name|escape:'html':'UTF-8'} ({$bk.currency_sign|escape:'html':'UTF-8'}{$rm.price_formatted|escape:'html':'UTF-8'})</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>

                    <div class="bk-search-pill__field">
                        <label for="bk-guests">Guests</label>
                        <div class="bk-search-pill__control">
                            <select id="bk-guests" name="bk_guests">
                                <option value="1-0">1 Adult, 1 Room</option>
                                <option value="2-0" selected="selected">2 Adults, 1 Room</option>
                                <option value="2-1">2 Adults, 1 Child</option>
                                <option value="2-2">2 Adults, 2 Children</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="bk-search-pill__submit">Check Now</button>
                </form>

                <!-- Hero Bottom Caption & Tour Trigger -->
                <div class="bk-hero__bottom">
                    <p class="bk-hero__caption">
                        {$bk.cfg.LVH_BK_HERO_SUB|escape:'html':'UTF-8'}
                    </p>
                    <button type="button" class="bk-tour-card" data-bk-lightbox="{$bk_home.hero_image|escape:'html':'UTF-8'}" data-bk-caption="{$bk.hotel_name|escape:'html':'UTF-8'} — Signature Hospitality">
                        <span class="bk-tour-card__play" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        </span>
                        <span class="bk-tour-card__text">Take a closer look at what makes {$bk.hotel_name|escape:'html':'UTF-8'} more than just a stay.</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         2. ABOUT (Home 2 style) — PORTRAIT + COUNTER | STORY | SEAL + WIDE
         =================================================================== -->
    <section id="bk2-about" class="bk-section bk2-about">
        <div class="bk-container">
            <div class="bk2-about__grid">

                <!-- Left: portrait photo + happy-customer counter -->
                <div class="bk2-about__left">
                    <div class="bk2-about__portrait">
                        <img src="{$bk_home.about_main|escape:'html':'UTF-8'}" alt="{$bk.hotel_name|escape:'html':'UTF-8'} welcome" loading="lazy">
                    </div>
                    <div class="bk2-counter">
                        <strong>{$bk.cfg.LVH_BK_STAT1_NUM|escape:'html':'UTF-8'}</strong>
                        <span>{$bk.cfg.LVH_BK_STAT1_LABEL|escape:'html':'UTF-8'}</span>
                    </div>
                </div>

                <!-- Center: heading + story + second stat -->
                <div class="bk2-about__story">
                    <div class="bk-eyebrow">
                        <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                        <span class="bk-eyebrow__text">About {$bk.hotel_name|escape:'html':'UTF-8'}</span>
                        <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                    </div>
                    <h2 class="bk-section-title">{$bk.cfg.LVH_BK_ABOUT_TITLE|escape:'html':'UTF-8'}</h2>
                    <p class="bk-section-desc">{$bk.cfg.LVH_BK_ABOUT_BODY|escape:'html':'UTF-8'}</p>
                    <div class="bk2-about__stat2">
                        <strong>{$bk.cfg.LVH_BK_STAT2_NUM|escape:'html':'UTF-8'}</strong>
                        <span>{$bk.cfg.LVH_BK_STAT2_LABEL|escape:'html':'UTF-8'}</span>
                    </div>
                    <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">Book Your Stay</a>
                </div>

                <!-- Right: rotating seal + wide landscape photo -->
                <div class="bk2-about__right">
                    <div class="bk2-seal" aria-hidden="true">
                        <svg viewBox="0 0 120 120" width="110" height="110">
                            <defs>
                                <path id="bk2SealPath" d="M60,60 m-44,0 a44,44 0 1,1 88,0 a44,44 0 1,1 -88,0"></path>
                            </defs>
                            <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="1.2"></circle>
                            <circle cx="60" cy="60" r="34" fill="none" stroke="currentColor" stroke-width="1"></circle>
                            <text font-size="10.5" letter-spacing="2.6" fill="currentColor">
                                <textPath href="#bk2SealPath" startOffset="0">LUSH VIEW HOTEL &#8226; EST. GHANA &#8226; LUXURY STAYS &#8226;</textPath>
                            </text>
                            <text x="60" y="66" text-anchor="middle" font-size="20" fill="currentColor">&#10022;</text>
                        </svg>
                    </div>
                    <div class="bk2-about__wide">
                        <img src="{$bk_home.about_second|escape:'html':'UTF-8'}" alt="{$bk.hotel_name|escape:'html':'UTF-8'} interiors" loading="lazy">
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ===================================================================
         3. POPULAR ROOMS (Home 2 property slider) — LIVE QLOAPPS ROOMS
         =================================================================== -->
    <section id="bk-rooms" class="bk-section bk2-rooms">
        <div class="bk-container">
            <div class="bk-section-head bk-section-head--center">
                <div class="bk-eyebrow bk-eyebrow--center">
                    <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                    <span class="bk-eyebrow__text">Popular Rooms</span>
                    <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                </div>
                <h2 class="bk-section-title">Discover The Rooms Guests Love Most</h2>
                <p class="bk-section-desc bk-section-desc--narrow">
                    Handpicked rooms and suites with transparent Ghana Cedi rates — book directly for instant confirmation.
                </p>
            </div>

            {if !empty($bk_home.bedrooms)}
                <div class="bk-rooms-slider" data-bk-rooms-slider>
                    <div class="bk-rooms-track" data-bk-rooms-track>
                        {foreach from=$bk_home.bedrooms item=rm}
                            <article class="bk-room-card">
                                <a href="{$rm.product_url|escape:'html':'UTF-8'}" class="bk-room-card__media">
                                    <img src="{$rm.image|escape:'html':'UTF-8'}" alt="{$rm.name|escape:'html':'UTF-8'}" loading="lazy">
                                    <span class="bk-room-card__price-float">
                                        <small>PRICE/</small>
                                        <strong>{$bk.currency_sign|escape:'html':'UTF-8'}{$rm.price_formatted|escape:'html':'UTF-8'}</strong>
                                        <small>Night</small>
                                    </span>
                                </a>
                                <div class="bk-room-card__body">
                                    <div class="bk-room-card__top">
                                        <h3 class="bk-room-card__title">
                                            <a href="{$rm.product_url|escape:'html':'UTF-8'}">{$rm.name|escape:'html':'UTF-8'}</a>
                                        </h3>
                                    </div>
                                    <ul class="bk-room-card__meta">
                                        <li>
                                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                            <span>1–{$rm.max_guests|intval} Persons</span>
                                        </li>
                                        <li>
                                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.683 3 4 3.683 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><line x1="2" y1="12" x2="22" y2="12"/></svg>
                                            <span>{$rm.bath_label|escape:'html':'UTF-8'}</span>
                                        </li>
                                        <li>
                                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>
                                            <span>{$rm.beds_label|escape:'html':'UTF-8'}</span>
                                        </li>
                                    </ul>
                                    <div class="bk-room-card__actions">
                                        <a href="{$rm.product_url|escape:'html':'UTF-8'}" class="bk-link-arrow">Discover More &rarr;</a>
                                    </div>
                                </div>
                            </article>
                        {/foreach}
                    </div>

                    <div class="bk-slider-bar">
                        <div class="bk-slider-bar__count">
                            <strong data-bk-slide-current>01</strong>
                            <span>/ {if $bk_home.bedroom_count < 10}0{/if}{$bk_home.bedroom_count|intval}</span>
                        </div>
                        <div class="bk-slider-bar__line">
                            <span data-bk-slide-progress style="width: 35%;"></span>
                        </div>
                        <div class="bk-slider-bar__nav">
                            <button type="button" class="bk-circle-btn" data-bk-slide-prev aria-label="Previous Rooms">&larr;</button>
                            <button type="button" class="bk-circle-btn" data-bk-slide-next aria-label="Next Rooms">&rarr;</button>
                        </div>
                    </div>
                </div>
            {/if}
        </div>
    </section>

    <!-- ===================================================================
         4. CUSTOMER SERVICES (Home 2 style) — HOVER CROSSFADE PANELS
         =================================================================== -->
    <section class="bk-section bk2-services" data-bk-services>
        <div class="bk2-services__bgs" aria-hidden="true">
            {foreach from=$bk_home.services item=sv name=svBgLoop}
                <div class="bk2-services__bg{if $smarty.foreach.svBgLoop.first} is-active{/if}" data-bk-service-bg style="background-image: url('{$sv.image|escape:'html':'UTF-8'}');"></div>
            {/foreach}
        </div>
        <div class="bk2-services__overlay" aria-hidden="true"></div>
        <div class="bk-container bk2-services__inner">
            <div class="bk2-services__head">
                <div class="bk-eyebrow bk-eyebrow--light">
                    <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                    <span class="bk-eyebrow__text">Customer Services</span>
                    <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                </div>
                <h2 class="bk-section-title bk-section-title--light">Tailored Services For Every Guest</h2>
            </div>
            <ul class="bk2-services__list">
                {foreach from=$bk_home.services item=sv name=svLoop}
                    <li class="bk2-services__item{if $smarty.foreach.svLoop.first} is-active{/if}" data-bk-service-item>
                        <a href="{$sv.url|escape:'html':'UTF-8'}">
                            <span class="bk2-services__num">0{$svLoop.iteration|intval}</span>
                            <div>
                                <h3>{$sv.title|escape:'html':'UTF-8'}</h3>
                                <p>{$sv.desc|escape:'html':'UTF-8'}</p>
                            </div>
                        </a>
                    </li>
                {/foreach}
            </ul>
        </div>
    </section>

    <!-- ===================================================================
         5. FEATURED ROOM SHOWCASE (Home 2 room slider) — DARK FULL-BLEED
         =================================================================== -->
    {if !empty($bk_home.featured)}
        <section class="bk2-showcase" data-bk-testi-slider>
            <div class="bk2-showcase__slides">
                {foreach from=$bk_home.featured item=rm name=ftLoop}
                    <div class="bk2-showcase__slide{if $smarty.foreach.ftLoop.first} is-active{/if}" data-bk-testi-slide style="background-image: url('{$rm.image|escape:'html':'UTF-8'}');">
                        <div class="bk2-showcase__overlay"></div>
                        <div class="bk-container bk2-showcase__content">
                            <div class="bk-eyebrow bk-eyebrow--light">
                                <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                                <span class="bk-eyebrow__text">Featured Stay</span>
                                <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                            </div>
                            <h2 class="bk2-showcase__title">{$rm.name|escape:'html':'UTF-8'}</h2>
                            <p class="bk2-showcase__desc">{$rm.short_desc|escape:'html':'UTF-8'}</p>
                            <div class="bk2-showcase__price">
                                <small>PRICE/</small>
                                <strong>{$bk.currency_sign|escape:'html':'UTF-8'}{$rm.price_formatted|escape:'html':'UTF-8'}</strong>
                                <small>Night</small>
                            </div>
                            <ul class="bk2-showcase__meta">
                                <li>1–{$rm.max_guests|intval} Persons</li>
                                <li>{$rm.bath_label|escape:'html':'UTF-8'}</li>
                                <li>{$rm.beds_label|escape:'html':'UTF-8'}</li>
                            </ul>
                            <a href="{$rm.book_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">Book This Room</a>
                        </div>
                    </div>
                {/foreach}
            </div>
            {if $bk_home.featured|@count > 1}
                <div class="bk2-showcase__nav">
                    <button type="button" class="bk-circle-btn" data-bk-testi-prev aria-label="Previous Stay">&larr;</button>
                    <button type="button" class="bk-circle-btn" data-bk-testi-next aria-label="Next Stay">&rarr;</button>
                </div>
            {/if}
        </section>
    {/if}

    <!-- ===================================================================
         6. FACILITIES (Home 2) — 8-ITEM GRID OVER DEEP INK
         =================================================================== -->
    <section id="bk-facilities" class="bk-section bk-facilities bk2-facilities">
        <div class="bk-container">
            <div class="bk-section-head bk-section-head--center">
                <div class="bk-eyebrow bk-eyebrow--center bk-eyebrow--light">
                    <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                    <span class="bk-eyebrow__text">Facilities</span>
                    <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                </div>
                <h2 class="bk-section-title bk-section-title--light">We help guests create lifetime memories they never forget.</h2>
            </div>

            <div class="bk2-fac-grid">
                {foreach from=$bk_home.facilities item=fi}
                    <article class="bk2-fac-item">
                        <div class="bk2-fac-item__icon">
                            {if $fi.icon == 'bed'}
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>
                            {elseif $fi.icon == 'shield'}
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            {elseif $fi.icon == 'dining'}
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
                            {elseif $fi.icon == 'conference'}
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            {elseif $fi.icon == 'pool'}
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 15c2 0 2-1.5 4-1.5S8 15 10 15s2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5"/><path d="M2 20c2 0 2-1.5 4-1.5S8 20 10 20s2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5"/><path d="M7 13V5a2 2 0 0 1 4 0"/><path d="M13 13V5a2 2 0 0 1 4 0"/></svg>
                            {elseif $fi.icon == 'gym'}
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6.5 6.5 17.5 17.5"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/></svg>
                            {elseif $fi.icon == 'wifi'}
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                            {else}
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="1" y="6" width="20" height="12" rx="2"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/><path d="M21 10h2"/></svg>
                            {/if}
                        </div>
                        <h3>{$fi.title|escape:'html':'UTF-8'}</h3>
                        <p>{$fi.desc|escape:'html':'UTF-8'}</p>
                    </article>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- ===================================================================
         7. OUR EXPERT (Home 2 team) — MEET THE FACES BEHIND YOUR STAY
         =================================================================== -->
    <section class="bk-section bk2-team">
        <div class="bk-container">
            <div class="bk-section-head bk-section-head--center">
                <div class="bk-eyebrow bk-eyebrow--center">
                    <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                    <span class="bk-eyebrow__text">Our Expert</span>
                    <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                </div>
                <h2 class="bk-section-title">Meet The Faces Behind Your Perfect Stay</h2>
                <p class="bk-section-desc bk-section-desc--narrow">
                    A passionate team ensuring comfort, care, and unforgettable guest experiences.
                </p>
            </div>

            <div class="bk2-team__grid">
                {foreach from=$bk_home.team item=tm}
                    <article class="bk2-team-card">
                        <div class="bk2-team-card__media">
                            <img src="{$tm.image|escape:'html':'UTF-8'}" alt="{$tm.name|escape:'html':'UTF-8'}" loading="lazy">
                        </div>
                        <div class="bk2-team-card__body">
                            <h3>{$tm.name|escape:'html':'UTF-8'}</h3>
                            <span>{$tm.role|escape:'html':'UTF-8'}</span>
                            <a class="bk2-team-card__mail" href="mailto:{$tm.mail|escape:'html':'UTF-8'}">{$tm.mail|escape:'html':'UTF-8'}</a>
                        </div>
                    </article>
                {/foreach}
            </div>

            <div class="bk2-team__cta">
                <a href="{$bk.contact_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--outline">View More</a>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         8. GUEST TESTIMONIALS — FULL-BLEED BACKDROP + FLOATING REVIEW CARD
         =================================================================== -->
    <section class="bk-testimonials" style="background-image: url('{$bk_home.testi_bg_image|escape:'html':'UTF-8'}');">
        <div class="bk-testimonials__backdrop"></div>
        <div class="bk-container bk-testimonials__inner">
            <div class="bk-testi-card" data-bk-testi-slider>
                <div class="bk-eyebrow bk-eyebrow--center">
                    <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                    <span class="bk-eyebrow__text">Testimonials</span>
                    <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                </div>
                <h2 class="bk-section-title" style="margin-bottom: 26px;">What Our Guests Say</h2>
                <div class="bk-testi-card__stars" aria-label="5 out of 5 stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>

                <div class="bk-testi-slides">
                    {foreach from=$bk_home.testimonials item=ts name=tsLoop}
                        <div class="bk-testi-slide{if $smarty.foreach.tsLoop.first} is-active{/if}" data-bk-testi-slide>
                            <blockquote class="bk-testi-slide__quote">
                                &ldquo;{$ts.quote|escape:'html':'UTF-8'}&rdquo;
                            </blockquote>
                            <div class="bk-testi-slide__author">
                                {if $ts.avatar}
                                    <img src="{$ts.avatar|escape:'html':'UTF-8'}" alt="{$ts.name|escape:'html':'UTF-8'}" class="bk-testi-slide__avatar">
                                {else}
                                    <span class="bk-testi-slide__monogram">{$ts.initials|escape:'html':'UTF-8'}</span>
                                {/if}
                                <div>
                                    <strong>{$ts.name|escape:'html':'UTF-8'}</strong>
                                    <span>{$ts.role|escape:'html':'UTF-8'}</span>
                                </div>
                            </div>
                        </div>
                    {/foreach}
                </div>

                {if $bk_home.testimonials|@count > 1}
                    <div class="bk-testi-card__nav">
                        <button type="button" class="bk-circle-btn bk-circle-btn--sm" data-bk-testi-prev aria-label="Previous Review">&larr;</button>
                        <button type="button" class="bk-circle-btn bk-circle-btn--sm" data-bk-testi-next aria-label="Next Review">&rarr;</button>
                    </div>
                {/if}
            </div>
        </div>
    </section>

    <!-- ===================================================================
         9. HOTEL ARTICLES (Home 2 blog) — TRENDING TOPICS FROM CMS
         =================================================================== -->
    {if !empty($bk_home.blog_cards)}
        <section class="bk-section bk2-blog">
            <div class="bk-container">
                <div class="bk-section-head bk-section-head--center">
                    <div class="bk-eyebrow bk-eyebrow--center">
                        <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                        <span class="bk-eyebrow__text">Trending Topics</span>
                        <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                    </div>
                    <h2 class="bk-section-title">Hotel Articles</h2>
                    <p class="bk-section-desc bk-section-desc--narrow">
                        Experience the perfect blend of comfort, style, and hospitality.
                    </p>
                </div>

                <div class="bk2-blog__grid">
                    {foreach from=$bk_home.blog_cards item=po}
                        <article class="bk-post-card">
                            <a href="{$po.url|escape:'html':'UTF-8'}" class="bk-post-card__media">
                                <img src="{$po.image|escape:'html':'UTF-8'}" alt="{$po.title|escape:'html':'UTF-8'}" loading="lazy">
                            </a>
                            <div class="bk-post-card__body">
                                <span class="bk-post-card__tag">Hotel Articles</span>
                                <h3><a href="{$po.url|escape:'html':'UTF-8'}">{$po.title|escape:'html':'UTF-8'}</a></h3>
                                <p>{$po.desc|escape:'html':'UTF-8'}</p>
                                <a href="{$po.url|escape:'html':'UTF-8'}" class="bk-link-arrow">Read More &rarr;</a>
                            </div>
                        </article>
                    {/foreach}
                </div>
            </div>
        </section>
    {/if}

</div>
