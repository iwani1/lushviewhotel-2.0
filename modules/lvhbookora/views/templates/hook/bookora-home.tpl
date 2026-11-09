{*
* Lush View Hotel - Bookora Complete Luxury Homepage Experience
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div id="bk-home-root" class="bk-root bk-home">

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
                    <a href="#bk-rooms" class="bk-btn bk-btn--outline">Explore More</a>
                    <a href="#bk-booking-bar" class="bk-btn bk-btn--gold">Booking Now</a>
                </div>
            </div>
        </div>

        <div id="bk-booking-bar" class="bk-hero__visual" style="background-image: url('{$bk_home.hero_image|escape:'html':'UTF-8'}');">
            <div class="bk-hero__overlay"></div>
            <div class="bk-container bk-hero__visual-inner">

                <!-- Glassmorphic Pill Search & Availability Bar -->
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
         2. ABOUT US — 3-COLUMN ASYMMETRIC EDITORIAL + STAT COUNTERS
         =================================================================== -->
    <section id="bk-about" class="bk-section bk-about">
        <div class="bk-container">
            <div class="bk-about__grid">
                <!-- Left Image + Stat 1 -->
                <div class="bk-about__col-left">
                    <div class="bk-about__photo bk-about__photo--short">
                        <img src="{$bk_home.about_image_left|escape:'html':'UTF-8'}" alt="{$bk.hotel_name|escape:'html':'UTF-8'} Bedroom" loading="lazy">
                    </div>
                    <div class="bk-stat-card">
                        <div class="bk-stat-card__body">
                            <span class="bk-stat-card__num">{$bk.cfg.LVH_BK_STAT1_NUM|escape:'html':'UTF-8'}</span>
                            <span class="bk-stat-card__label">{$bk.cfg.LVH_BK_STAT1_LABEL|escape:'html':'UTF-8'}</span>
                        </div>
                        <span class="bk-stat-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                    </div>
                </div>

                <!-- Center Editorial Story + Stat 2 -->
                <div class="bk-about__col-mid">
                    <div class="bk-eyebrow">
                        <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                        <span class="bk-eyebrow__text">About Us</span>
                        <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                    </div>
                    <h2 class="bk-section-title">{$bk.cfg.LVH_BK_ABOUT_TITLE|escape:'html':'UTF-8'}</h2>
                    <p class="bk-section-desc">{$bk.cfg.LVH_BK_ABOUT_BODY|escape:'html':'UTF-8'}</p>
                    <div>
                        <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">Explore Our Rooms</a>
                    </div>

                    <div class="bk-stat-card bk-stat-card--mid">
                        <div class="bk-stat-card__body">
                            <span class="bk-stat-card__num">{$bk.cfg.LVH_BK_STAT2_NUM|escape:'html':'UTF-8'}</span>
                            <span class="bk-stat-card__label">{$bk.cfg.LVH_BK_STAT2_LABEL|escape:'html':'UTF-8'}</span>
                        </div>
                        <span class="bk-stat-card__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/></svg>
                        </span>
                    </div>
                </div>

                <!-- Right Tall Editorial Photo -->
                <div class="bk-about__col-right">
                    <div class="bk-about__photo bk-about__photo--tall">
                        <img src="{$bk_home.about_image_right|escape:'html':'UTF-8'}" alt="{$bk.hotel_name|escape:'html':'UTF-8'} Suite Interior" loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         3. OUR FACILITIES — OBSIDIAN LUXURY DARK SECTION + 2x2 ARCH CARDS
         =================================================================== -->
    <section id="bk-facilities" class="bk-section bk-facilities">
        <div class="bk-container">
            <div class="bk-facilities__layout">
                <div class="bk-facilities__intro">
                    <div class="bk-eyebrow bk-eyebrow--light">
                        <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                        <span class="bk-eyebrow__text">Our Facilities</span>
                        <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                    </div>
                    <h2 class="bk-section-title bk-section-title--light">
                        Discover Our Exceptional Hotel Facilities
                    </h2>
                    <p class="bk-section-desc bk-section-desc--light">
                        We provide unmatched comfort, uninterrupted utilities, and attentive Ghanaian hospitality that make every guest feel truly at home.
                    </p>
                    <a href="{$bk.contact_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">Contact Us</a>
                </div>

                <div class="bk-facilities__grid">
                    <article class="bk-fac-card">
                        <div class="bk-fac-card__icon">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>
                        </div>
                        <h3>Rooms &amp; Suites</h3>
                        <p>Relax in elegantly appointed, air-conditioned rooms and suites offering plush bedding, smart TVs, high-speed Wi-Fi, and serene views.</p>
                    </article>

                    <article class="bk-fac-card">
                        <div class="bk-fac-card__icon">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                        </div>
                        <h3>24-Hour Security &amp; Power</h3>
                        <p>Enjoy complete peace of mind with round-the-clock security, gated parking, CCTV coverage, and automatic standby generator backup.</p>
                    </article>

                    <article class="bk-fac-card">
                        <div class="bk-fac-card__icon">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
                        </div>
                        <h3>Restaurant &amp; Lounge Bar</h3>
                        <p>Savor freshly prepared Ghanaian favorites and continental dishes crafted from fine local ingredients, plus in-room dining service.</p>
                    </article>

                    <article class="bk-fac-card">
                        <div class="bk-fac-card__icon">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        </div>
                        <h3>Conference &amp; Event Hall</h3>
                        <p>Host executive meetings, trainings, and corporate summits for up to 60 delegates with projection, audio, Wi-Fi, and catering.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         4. TOP ROOMS & SUITES — LIVE QLOAPPS ROOM CAROUSEL WITH GHS RATES
         =================================================================== -->
    <section id="bk-rooms" class="bk-section bk-rooms">
        <div class="bk-container">
            <div class="bk-section-head bk-section-head--center">
                <div class="bk-eyebrow bk-eyebrow--center">
                    <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                    <span class="bk-eyebrow__text">Top Accommodations</span>
                    <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                </div>
                <h2 class="bk-section-title">
                    Find Your Perfect Room With<br>Instant Direct Booking.
                </h2>
            </div>

            {if !empty($bk_home.bedrooms)}
                <div class="bk-rooms-slider" data-bk-rooms-slider>
                    <div class="bk-rooms-track" data-bk-rooms-track>
                        {foreach from=$bk_home.bedrooms item=rm name=rmLoop}
                            <article class="bk-room-card">
                                <a href="{$rm.book_url|escape:'html':'UTF-8'}" class="bk-room-card__media">
                                    <img src="{$rm.image|escape:'html':'UTF-8'}" alt="{$rm.name|escape:'html':'UTF-8'}" loading="lazy">
                                    <span class="bk-room-card__badge">Instant Confirmation</span>
                                </a>
                                <div class="bk-room-card__body">
                                    <div class="bk-room-card__top">
                                        <h3 class="bk-room-card__title">
                                            <a href="{$rm.product_url|escape:'html':'UTF-8'}">{$rm.name|escape:'html':'UTF-8'}</a>
                                        </h3>
                                        <div class="bk-room-card__price">
                                            <strong>{$bk.currency_sign|escape:'html':'UTF-8'}{$rm.price_formatted|escape:'html':'UTF-8'}</strong><span>/ Night</span>
                                        </div>
                                    </div>
                                    <p class="bk-room-card__desc">{$rm.short_desc|escape:'html':'UTF-8'}</p>
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
                                        <a href="{$rm.book_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold bk-btn--sm">Book Now</a>
                                        <a href="{$rm.product_url|escape:'html':'UTF-8'}" class="bk-link-arrow">Room Details &rarr;</a>
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
         5. SPECIAL OFFERS & CONFERENCE PACKAGES (INTERACTIVE ACCORDION)
         =================================================================== -->
    <section id="bk-offers" class="bk-section bk-offers">
        <div class="bk-container">
            <div class="bk-offers__header">
                <div>
                    <div class="bk-eyebrow">
                        <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                        <span class="bk-eyebrow__text">Special Offers</span>
                        <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                    </div>
                    <h2 class="bk-section-title">Our Special Offers &amp; Packages</h2>
                </div>
                <div class="bk-offers__header-right">
                    <p>Tailored accommodation and conference packages crafted for effortless business and leisure stays.</p>
                    <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">View All Rooms</a>
                </div>
            </div>

            <div class="bk-offers__body">
                <div class="bk-offers__list" data-bk-offers-list>
                    {foreach from=$bk_home.offers item=of name=ofLoop}
                        <div class="bk-offer-item{if $smarty.foreach.ofLoop.first} is-active{/if}" data-bk-offer-item data-image="{$of.image|escape:'html':'UTF-8'}">
                            <div class="bk-offer-item__head">
                                <h3>{$of.title|escape:'html':'UTF-8'}</h3>
                                <div class="bk-offer-item__price">
                                    <strong>{$of.price_label|escape:'html':'UTF-8'}</strong>
                                    <small>{$of.unit_label|escape:'html':'UTF-8'}</small>
                                </div>
                            </div>
                            <div class="bk-offer-item__details">
                                <ul>
                                    {foreach from=$of.perks item=pk}
                                        <li>{$pk|escape:'html':'UTF-8'}</li>
                                    {/foreach}
                                </ul>
                                <a href="{$of.url|escape:'html':'UTF-8'}" class="bk-link-arrow">Reserve This Package &rarr;</a>
                            </div>
                        </div>
                    {/foreach}
                </div>

                <div class="bk-offers__media">
                    <img id="bk-offer-preview-img" src="{$bk_home.offer_default_image|escape:'html':'UTF-8'}" alt="Lush View Hotel Special Package" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         6. PRICING PLANS — 3-COLUMN BOOKORA COMPARISON SECTION
         =================================================================== -->
    <section class="bk-section bk-plans">
        <div class="bk-container">
            <div class="bk-plans__head">
                <div class="bk-plans__head-inner">
                    <div class="bk-eyebrow">
                        <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                        <span class="bk-eyebrow__text">Our Stay Plans</span>
                        <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                    </div>
                    <h2 class="bk-section-title">
                        Choose The Perfect Stay Plan For Your Comfort And Budget.
                    </h2>
                </div>
            </div>

            <div class="bk-plans__grid">
                <div class="bk-plans__visual" style="background-image: url('{$bk_home.plan_side_image|escape:'html':'UTF-8'}');">
                    <div class="bk-plans__visual-overlay">
                        <h3>We make your stay in Accra seamless with warmth and class.</h3>
                    </div>
                </div>

                {foreach from=$bk_home.plans item=pl}
                    <div class="bk-plan-card{if $pl.featured} bk-plan-card--featured{/if}">
                        <h3 class="bk-plan-card__title">{$pl.title|escape:'html':'UTF-8'}</h3>
                        <p class="bk-plan-card__desc">{$pl.subtitle|escape:'html':'UTF-8'}</p>
                        <div class="bk-plan-card__price">
                            <strong>{$bk.currency_sign|escape:'html':'UTF-8'}{$pl.price_formatted|escape:'html':'UTF-8'}</strong>
                            <span>{$pl.period|escape:'html':'UTF-8'}</span>
                        </div>
                        <a href="{$pl.book_url|escape:'html':'UTF-8'}" class="bk-btn {if $pl.featured}bk-btn--gold{else}bk-btn--outline-full{/if} bk-btn--block">
                            Reserve This Room
                        </a>
                        <ul class="bk-plan-card__list">
                            {foreach from=$pl.features item=ft}
                                <li>
                                    <span class="bk-check" aria-hidden="true">&#10003;</span>
                                    <span>{$ft|escape:'html':'UTF-8'}</span>
                                </li>
                            {/foreach}
                        </ul>
                    </div>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- ===================================================================
         7. GUEST TESTIMONIALS — FULL-BLEED BACKDROP + FLOATING REVIEW CARD
         =================================================================== -->
    <section class="bk-testimonials" style="background-image: url('{$bk_home.testi_bg_image|escape:'html':'UTF-8'}');">
        <div class="bk-testimonials__backdrop"></div>
        <div class="bk-container bk-testimonials__inner">
            <div class="bk-testi-card" data-bk-testi-slider>
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
         8. EXPLORE OUR ELEGANT INTERIORS — STAGGERED GALLERY + LIGHTBOX
         =================================================================== -->
    <section id="bk-gallery" class="bk-section bk-gallery">
        <div class="bk-container">
            <div class="bk-section-head bk-section-head--center">
                <div class="bk-eyebrow bk-eyebrow--center">
                    <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                    <span class="bk-eyebrow__text">Our Hotel &amp; Interiors</span>
                    <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                </div>
                <h2 class="bk-section-title">Explore Our Elegant Rooms</h2>
                <p class="bk-section-desc bk-section-desc--narrow">
                    Experience the blend of comfort, tranquility, and contemporary style across our bedrooms, suites, lounge, and grounds.
                </p>
                <div class="bk-gallery__cta">
                    <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">Book Your Stay</a>
                </div>
            </div>
        </div>

        {if !empty($bk_home.gallery)}
            <div class="bk-gallery__strip">
                {foreach from=$bk_home.gallery item=gl name=glLoop}
                    <button type="button"
                            class="bk-gallery__tile{if $smarty.foreach.glLoop.index % 2 == 1} bk-gallery__tile--offset{/if}"
                            data-bk-lightbox="{$gl.url|escape:'html':'UTF-8'}"
                            data-bk-caption="{$gl.caption|escape:'html':'UTF-8'}">
                        <img src="{$gl.url|escape:'html':'UTF-8'}" alt="{$gl.caption|escape:'html':'UTF-8'}" loading="lazy">
                        <span class="bk-gallery__zoom">View Photo</span>
                    </button>
                {/foreach}
            </div>
        {/if}
    </section>

    <!-- ===================================================================
         9. HOTEL HIGHLIGHTS & EXPERIENCES (EDITORIAL FEATURE CARDS)
         =================================================================== -->
    <section class="bk-section bk-highlights">
        <div class="bk-container">
            <div class="bk-section-head bk-section-head--center">
                <div class="bk-eyebrow bk-eyebrow--center">
                    <span class="bk-eyebrow__line bk-eyebrow__line--left"></span>
                    <span class="bk-eyebrow__text">Lush View Experiences</span>
                    <span class="bk-eyebrow__line bk-eyebrow__line--right"></span>
                </div>
                <h2 class="bk-section-title">Crafted For Business &amp; Leisure</h2>
                <p class="bk-section-desc bk-section-desc--narrow">
                    Whether you are visiting for an executive retreat, a corporate conference, or a restful weekend getaway, every detail is ready for your arrival.
                </p>
            </div>

            <div class="bk-highlights__grid">
                <article class="bk-post-card">
                    <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-post-card__media">
                        <img src="{$bk_home.highlight_img_1|escape:'html':'UTF-8'}" alt="Weekend &amp; Leisure Stays at {$bk.hotel_name|escape:'html':'UTF-8'}" loading="lazy">
                    </a>
                    <div class="bk-post-card__body">
                        <span class="bk-post-card__tag">Check-In {$bk.check_in_time|escape:'html':'UTF-8'} &bull; Check-Out {$bk.check_out_time|escape:'html':'UTF-8'}</span>
                        <h3><a href="{$bk.search_url|escape:'html':'UTF-8'}">Serene Executive &amp; Suite Stays With Effortless Direct Reservation</a></h3>
                        <p>Book directly online for guaranteed room availability, transparent all-inclusive Ghana Cedi rates, and flexible MTN MoMo, card, or front-desk settlement.</p>
                        <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-link-arrow">Check Available Dates &rarr;</a>
                    </div>
                </article>

                <article class="bk-post-card">
                    <a href="{$bk.contact_url|escape:'html':'UTF-8'}" class="bk-post-card__media">
                        <img src="{$bk_home.highlight_img_2|escape:'html':'UTF-8'}" alt="Corporate Conferences at {$bk.hotel_name|escape:'html':'UTF-8'}" loading="lazy">
                    </a>
                    <div class="bk-post-card__body">
                        <span class="bk-post-card__tag">Up To 60 Delegates &bull; Full AV &amp; Standby Power</span>
                        <h3><a href="{$bk.contact_url|escape:'html':'UTF-8'}">Host Seamless Corporate Meetings, Workshops &amp; Group Retreats</a></h3>
                        <p>Combine our modern 60-seat Conference Hall with group accommodation blocks, tailored refreshments, and dedicated on-site event coordination.</p>
                        <a href="{$bk.contact_url|escape:'html':'UTF-8'}" class="bk-link-arrow">Inquire About Events &rarr;</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

</div>
