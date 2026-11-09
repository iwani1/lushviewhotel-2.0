{*
* Lush View Hotel - Bookora Home 2 — pixel-faithful to the original Bookora
* theme package (bk-hero2 video hero with curved base, bk-ab2 about with
* counter, bk-room2 slider with flag price badges, bk-cus service cards,
* dark facilities, bk-team cards, go-testimonial, bk-blog items) powered by
* live QloApps 1.8.0 rooms, prices, testimonials & CMS.
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div id="bk-home-root" class="bk-root bk-home bk2-home">

    <!-- ===================================================================
         1. bk-hero2 — full-bleed hero, curved base, flourished slug
         =================================================================== -->
    <section class="bk2-hero2">
        <div class="bk2-hero2__bg" style="background-image: url('{$bk_home.hero_image|escape:'html':'UTF-8'}');"></div>
        <div class="bk2-hero2__text">
            <div class="bk2-slug bk2-slug--light"><i></i>{$bk.cfg.LVH_BK_EYEBROW|escape:'html':'UTF-8'}<i></i></div>
            <h1 class="bk2-hero2__title">{$bk.cfg.LVH_BK_HERO_LINE1|escape:'html':'UTF-8'}<br>{$bk.cfg.LVH_BK_HERO_LINE2|escape:'html':'UTF-8'}</h1>
            <p class="bk2-hero2__desc">{$bk.cfg.LVH_BK_HERO_SUB|escape:'html':'UTF-8'}</p>
            <div class="bk2-hero2__btns">
                <a href="#bk2-about">Explore More</a>
                <a href="#bk-booking-bar">Booking Now</a>
            </div>
        </div>
    </section>

    <!-- Glass availability bar floating over the hero curve (live QloApps) -->
    <div id="bk-booking-bar" class="bk2-booking-wrap">
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
    </div>

    <!-- ===================================================================
         2. bk-ab2 — portrait + cream counter | heading + desc | seal + wide
         =================================================================== -->
    <section id="bk2-about" class="bk2-about">
        <div class="bk-container bk2-ab2">
            <div class="bk2-ab2__left">
                <div class="bk2-ab2__portrait">
                    <img src="{$bk_home.about_main|escape:'html':'UTF-8'}" alt="{$bk.hotel_name|escape:'html':'UTF-8'} welcome" loading="lazy">
                </div>
                <div class="bk2-ab2__count">
                    <h3>{$bk.cfg.LVH_BK_STAT1_NUM|escape:'html':'UTF-8'}</h3>
                    <p>{$bk.cfg.LVH_BK_STAT1_LABEL|escape:'html':'UTF-8'}</p>
                </div>
            </div>

            <div class="bk2-ab2__right">
                <div class="bk2-ab2__top">
                    <div class="bk2-ab2__head">
                        <div class="bk2-slug"><i></i>About Us<i></i></div>
                        <h2 class="bk2-ab2__title">{$bk_home.about_title_a|escape:'html':'UTF-8'}{if $bk_home.about_title_b} <span>{$bk_home.about_title_b|escape:'html':'UTF-8'}</span>{/if}</h2>
                    </div>
                    <div class="bk2-ab2__desc">
                        <p>{$bk.cfg.LVH_BK_ABOUT_BODY|escape:'html':'UTF-8'}</p>
                        <p class="bk2-ab2__stat2"><strong>{$bk.cfg.LVH_BK_STAT2_NUM|escape:'html':'UTF-8'}</strong> {$bk.cfg.LVH_BK_STAT2_LABEL|escape:'html':'UTF-8'}</p>
                        <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk2-btn-gold">Book Your Stay</a>
                    </div>
                </div>
                <div class="bk2-ab2__scroll">
                    <div class="bk2-ab2__wide">
                        <img src="{$bk_home.about_second|escape:'html':'UTF-8'}" alt="{$bk.hotel_name|escape:'html':'UTF-8'} interiors" loading="lazy">
                    </div>
                    <div class="bk2-ab2__badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="#fff" stroke-width="1.4"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         3. bk-room2 — Popular Rooms slider with flag price badges
         =================================================================== -->
    <section id="bk-rooms" class="bk2-room2">
        <div class="bk-container">
            <div class="bk2-sec-title">
                <div class="bk2-slug"><i></i>Popular Rooms<i></i></div>
                <h2 class="bk2-sec-title__main">Discover The Rooms <span>Guests Love Most</span></h2>
                <p>Handpicked rooms and suites with transparent Ghana Cedi rates — book directly for instant confirmation.</p>
            </div>

            {if !empty($bk_home.bedrooms)}
                <div class="bk-rooms-slider" data-bk-rooms-slider>
                    <div class="bk-rooms-track" data-bk-rooms-track>
                        {foreach from=$bk_home.bedrooms item=rm}
                            <article class="bk-room-card bk2-room2-item">
                                <a href="{$rm.product_url|escape:'html':'UTF-8'}" class="bk-room-card__media bk2-room2-item__img">
                                    <img src="{$rm.image|escape:'html':'UTF-8'}" alt="{$rm.name|escape:'html':'UTF-8'}" loading="lazy">
                                    <span class="bk2-room2-flag">PRICE/ {$bk.currency_sign|escape:'html':'UTF-8'}{$rm.price_formatted|escape:'html':'UTF-8'} Night</span>
                                </a>
                                <div class="bk-room-card__body bk2-room2-item__text">
                                    <h3><a href="{$rm.product_url|escape:'html':'UTF-8'}">{$rm.name|escape:'html':'UTF-8'}</a></h3>
                                    <ul class="bk2-room2-meta">
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
                                    <a href="{$rm.product_url|escape:'html':'UTF-8'}" class="bk2-read-more">Discover More</a>
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
         4. bk-cus — Customer Services icon cards (gold hover)
         =================================================================== -->
    <section class="bk2-services2">
        <div class="bk-container">
            <div class="bk2-sec-title bk2-sec-title--center">
                <div class="bk2-slug"><i></i>Customer Services<i></i></div>
                <h2 class="bk2-sec-title__main">Tailored Services <span>For Every Guest</span></h2>
            </div>
            <div class="bk2-cus-content">
                {foreach from=$bk_home.services item=sv name=svLoop}
                    <div class="bk2-cus-item">
                        <div class="bk2-cus-item__inner">
                            <div class="bk2-cus-item__icon">
                                {if $svLoop.iteration == 1}
                                    <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="18" height="17" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 15 2 2 4-4"/></svg>
                                {elseif $svLoop.iteration == 2}
                                    <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6.5 6.5 17.5 17.5"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/></svg>
                                {elseif $svLoop.iteration == 3}
                                    <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>
                                {else}
                                    <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 15c2 0 2-1.5 4-1.5S8 15 10 15s2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5"/><path d="M2 20c2 0 2-1.5 4-1.5S8 20 10 20s2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5"/><path d="M7 13V5a2 2 0 0 1 4 0"/><path d="M13 13V5a2 2 0 0 1 4 0"/></svg>
                                {/if}
                            </div>
                            <div class="bk2-cus-item__text">
                                <h3><a href="{$sv.url|escape:'html':'UTF-8'}">{$sv.title|escape:'html':'UTF-8'}</a></h3>
                                <p>{$sv.desc|escape:'html':'UTF-8'}</p>
                            </div>
                        </div>
                    </div>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- ===================================================================
         5. Featured room showcase — dark slider
         =================================================================== -->
    {if !empty($bk_home.featured)}
        <section class="bk2-showcase" data-bk-testi-slider>
            <div class="bk2-showcase__slides">
                {foreach from=$bk_home.featured item=rm name=ftLoop}
                    <div class="bk2-showcase__slide{if $smarty.foreach.ftLoop.first} is-active{/if}" data-bk-testi-slide style="background-image: url('{$rm.image|escape:'html':'UTF-8'}');">
                        <div class="bk2-showcase__overlay"></div>
                        <div class="bk-container bk2-showcase__grid">
                            <div class="bk2-showcase__thumbs">
                                {foreach from=$bk_home.featured item=th name=thLoop}
                                    <button type="button" class="bk2-showcase__thumb{if $smarty.foreach.thLoop.first} is-active{/if}" data-bk-testi-goto aria-label="Show {$th.name|escape:'html':'UTF-8'}">
                                        <img src="{$th.image|escape:'html':'UTF-8'}" alt="{$th.name|escape:'html':'UTF-8'}" loading="lazy">
                                    </button>
                                {/foreach}
                            </div>
                            <div class="bk2-showcase__content">
                                <div class="bk2-showcase__reviews">// {$bk.hotel_name|escape:'html':'UTF-8'} Guest Reviews</div>
                                <h2 class="bk2-showcase__title">{$rm.name|escape:'html':'UTF-8'}</h2>
                                <p class="bk2-showcase__desc">{$rm.short_desc|escape:'html':'UTF-8'}</p>
                                <a href="{$rm.book_url|escape:'html':'UTF-8'}" class="bk2-btn-gold">Booking Now</a>
                            </div>
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
         6. Facilities — dark, white flourishes, 8-item grid
         =================================================================== -->
    <section id="bk-facilities" class="bk2-facilities">
        <div class="bk-container">
            <div class="bk2-sec-title bk2-sec-title--center">
                <div class="bk2-slug bk2-slug--light"><i></i>Facilities<i></i></div>
                <h2 class="bk2-sec-title__main bk2-sec-title__main--light">We help guests create <span>lifetime memories they never forget.</span></h2>
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
         6b. Check availability & book Your Stay (demo Home 2 booking block)
         =================================================================== -->
    <section class="bk2-checkav" style="background-image: url('{$bk_home.hero_image|escape:'html':'UTF-8'}');">
        <div class="bk2-checkav__overlay"></div>
        <div class="bk-container bk2-checkav__grid">
            <div class="bk2-checkav__left">
                <h2 class="bk2-checkav__title">Check availability &amp;<br>book Your Stay</h2>
                <form class="bk2-checkav__form" data-bk-avail-form action="{$bk.search_url|escape:'html':'UTF-8'}" method="get">
                    <div class="bk2-checkav__field">
                        <label for="bk2-checkin">Check-In</label>
                        <input type="date" id="bk2-checkin" name="date_from" value="{$bk.default_checkin|escape:'html':'UTF-8'}" min="{$bk.default_checkin|escape:'html':'UTF-8'}" required>
                    </div>
                    <div class="bk2-checkav__field">
                        <label for="bk2-checkout">Check-Out</label>
                        <input type="date" id="bk2-checkout" name="date_to" value="{$bk.default_checkout|escape:'html':'UTF-8'}" min="{$bk.default_checkin|escape:'html':'UTF-8'}" required>
                    </div>
                    <div class="bk2-checkav__field">
                        <label for="bk2-guests">Guests</label>
                        <select id="bk2-guests" name="bk_guests">
                            <option value="1-0">1 Person</option>
                            <option value="2-0" selected="selected">2 Persons</option>
                            <option value="3-0">3 Persons</option>
                            <option value="4-0">4 Persons</option>
                        </select>
                    </div>
                    <div class="bk2-checkav__field">
                        <label for="bk2-rooms">Rooms</label>
                        <select id="bk2-rooms" name="bk_rooms_count">
                            <option value="1" selected="selected">1 Room</option>
                            <option value="2">2 Rooms</option>
                            <option value="3">3 Rooms</option>
                            <option value="4">4 Rooms</option>
                        </select>
                    </div>
                    <button type="submit" class="bk2-checkav__submit">Check Now</button>
                </form>
            </div>
            <ul class="bk2-checkav__features">
                <li>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    24/7 Concierge Service
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    Wellness &amp; Spa Center
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    Gourmet On-Site Dining
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    Rooftop Pool &amp; Lounge
                </li>
            </ul>
        </div>
    </section>

    <!-- ===================================================================
         7. Our Expert — bk-team cards
         =================================================================== -->
    <section class="bk2-team">
        <div class="bk-container">
            <div class="bk2-sec-title bk2-sec-title--center">
                <div class="bk2-slug"><i></i>Our Expert<i></i></div>
                <h2 class="bk2-sec-title__main">Meet The Faces Behind <span>Your Perfect Stay</span></h2>
                <p>A passionate team ensuring comfort, care, and unforgettable guest experiences.</p>
            </div>

            <div class="bk2-team__grid">
                {foreach from=$bk_home.team item=tm}
                    <article class="bk2-team-item">
                        <div class="bk2-team-item__img">
                            <img src="{$tm.image|escape:'html':'UTF-8'}" alt="{$tm.name|escape:'html':'UTF-8'}" loading="lazy">
                            <div class="bk2-team-item__social">
                                <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z"/></svg></a>
                                <a href="#" aria-label="X"><svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                                <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg></a>
                                <a href="mailto:{$tm.mail|escape:'html':'UTF-8'}" aria-label="Email"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></a>
                            </div>
                        </div>
                        <div class="bk2-team-item__text">
                            <h3>{$tm.name|escape:'html':'UTF-8'}</h3>
                            <span>{$tm.role|escape:'html':'UTF-8'}</span>
                            <a class="bk2-team-item__mail" href="mailto:{$tm.mail|escape:'html':'UTF-8'}">{$tm.mail|escape:'html':'UTF-8'}</a>
                        </div>
                    </article>
                {/foreach}
            </div>

            <div class="bk2-team__cta">
                <a href="{$bk.contact_url|escape:'html':'UTF-8'}" class="bk2-btn-outline">View More</a>
            </div>
        </div>
    </section>

    <!-- ===================================================================
         8. Guest testimonials — floating card over backdrop
         =================================================================== -->
    <section class="bk-testimonials" style="background-image: url('{$bk_home.testi_bg_image|escape:'html':'UTF-8'}');">
        <div class="bk-testimonials__backdrop"></div>
        <div class="bk-container bk-testimonials__inner">
            <div class="bk-testi-card" data-bk-testi-slider>
                <div class="bk2-slug"><i></i>Testimonials<i></i></div>
                <h2 class="bk2-sec-title__main" style="margin-bottom: 26px;">Testimonial</h2>
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
         9. bk-blog — Hotel Articles
         =================================================================== -->
    {if !empty($bk_home.blog_cards)}
        <section class="bk2-blog">
            <div class="bk-container">
                <div class="bk2-sec-title bk2-sec-title--center">
                    <div class="bk2-slug"><i></i>Trending Topics<i></i></div>
                    <h2 class="bk2-sec-title__main bk2-sec-title__main--xl">Hotel <span>Articles</span></h2>
                    <p>Experience the perfect blend of comfort, style, and hospitality.</p>
                    <div class="bk2-sec-title__cta">
                        <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk2-btn-outline">View More</a>
                    </div>
                </div>

                <div class="bk2-blog__grid">
                    {foreach from=$bk_home.blog_cards item=po}
                        <article class="bk2-blog-item">
                            <a href="{$po.url|escape:'html':'UTF-8'}" class="bk2-blog-item__img">
                                <img src="{$po.image|escape:'html':'UTF-8'}" alt="{$po.title|escape:'html':'UTF-8'}" loading="lazy">
                            </a>
                            <div class="bk2-blog-item__text">
                                <span>Hotel Articles</span>
                                <h3 class="bk2-blog-item__title"><a href="{$po.url|escape:'html':'UTF-8'}">{$po.title|escape:'html':'UTF-8'}</a></h3>
                                <p>{$po.desc|escape:'html':'UTF-8'}</p>
                                <a href="{$po.url|escape:'html':'UTF-8'}" class="bk2-read-more bk2-read-more--dark">Read More</a>
                            </div>
                        </article>
                    {/foreach}
                </div>
            </div>
        </section>
    {/if}

</div>
