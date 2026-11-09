{*
* Lush View Hotel - Bookora Home 2 Complete Homepage (exact Bookora Home 2
* section order): dark hero, About Us + rotating seal, Popular Rooms,
* interactive Customer Services, Featured Suites slider, Facilities grid,
* Check Availability & Book Your Stay, Our Expert, Testimonial slider,
* Trending Topics / Hotel Articles.
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div id="bk-home-root" class="bk-root bk-h2 bk-home2">

    <!-- ============ 1. Dark Full-Viewport Hero ============ -->
    <section class="bk2-hero" style="background-image: url('{$bk_home.hero_image|escape:'html':'UTF-8'}');">
        <div class="bk2-hero__overlay"></div>
        <div class="bk-container bk2-hero__inner">
            <p class="bk2-hero__eyebrow">{$bk.cfg.LVH_BK_EYEBROW|escape:'html':'UTF-8'}</p>
            <h1 class="bk2-hero__title">
                {$bk.cfg.LVH_BK_HERO_LINE1|escape:'html':'UTF-8'}<br>
                <em>{$bk.cfg.LVH_BK_HERO_LINE2|escape:'html':'UTF-8'}</em>
            </h1>
            <p class="bk2-hero__sub">{$bk.cfg.LVH_BK_HERO_SUB|escape:'html':'UTF-8'}</p>
            <div class="bk2-hero__ctas">
                <a href="#bk-about" class="bk-btn bk-btn--outline-gold">Explore More</a>
                <a href="#bk-booking" class="bk-btn bk-btn--gold">Booking Now</a>
            </div>
        </div>
        <div class="bk2-hero__scroll" aria-hidden="true"><span></span></div>
    </section>

    <!-- ============ 2. About Us (Home 2 — Bookora bk-ab2 layout) ============ -->
    <section id="bk-about" class="bk2-about">
        <div class="bk-container bk2-about__grid">
            <div class="bk2-about__media">
                <div class="bk2-about__photo bk2-about__photo--main" style="background-image: url('{$bk_home.about_image_1|escape:'html':'UTF-8'}');"></div>
                <div class="bk2-about__stat">
                    <strong data-bk-countup="80">{$bk.cfg.LVH_BK_STAT1_NUM|escape:'html':'UTF-8'}</strong>
                    <span>{$bk.cfg.LVH_BK_STAT1_LABEL|escape:'html':'UTF-8'}</span>
                </div>
            </div>

            <div class="bk2-about__text-wrap">
                <div class="bk2-about__top">
                    <div class="bk2-about__heading">
                        <p class="bk2-eyebrow">About Us</p>
                        <h2 class="bk2-title">Experience Comfort, <span class="bk2-title__accent">Elegance &amp; Exceptional Hospitality</span></h2>
                    </div>
                    <div class="bk2-about__desc">
                        <p class="bk2-lead">{$bk.cfg.LVH_BK_ABOUT_BODY|escape:'html':'UTF-8'}</p>
                        <a href="{$bk.about_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--dark">Discover More</a>
                    </div>
                </div>

                <div class="bk2-about__scroll">
                    <div class="bk2-seal" aria-hidden="true">
                        <svg viewBox="0 0 120 120" class="bk2-seal__ring">
                            <defs>
                                <path id="bkSealPath" d="M60,60 m-46,0 a46,46 0 1,1 92,0 a46,46 0 1,1 -92,0"/>
                            </defs>
                            <text class="bk2-seal__text">
                                <textPath href="#bkSealPath">LUSH VIEW HOTEL • BOOK DIRECT &amp; SAVE • LUSH VIEW HOTEL • </textPath>
                            </text>
                        </svg>
                        <span class="bk2-seal__core">
                            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2 15 9l7 .5-5.4 4.6L18.5 21 12 17.2 5.5 21l1.9-6.9L2 9.5 9 9z"/></svg>
                        </span>
                    </div>
                    <div class="bk2-about__photo bk2-about__photo--second" style="background-image: url('{$bk_home.about_image_2|escape:'html':'UTF-8'}');"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 3. Popular Rooms (Bookora bk-room2 slider) ============ -->
    <section id="bk-rooms" class="bk2-rooms">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Popular Rooms</p>
                <h2 class="bk2-title">Discover The Rooms Guests Love Most</h2>
            </div>

            <div class="bk2-rooms__slider" data-bk-rooms-slider>
                <div class="bk2-rooms__track" data-bk-rooms-track>
                {foreach from=$bk_home.popular_rooms item=rm}
                <article class="bk2-room-card">
                    <div class="bk2-room-card__media" style="background-image: url('{$rm.image|escape:'html':'UTF-8'}');">
                        <span class="bk2-room-card__price">{$bk.currency_sign|escape:'html':'UTF-8'}{$rm.price_formatted|escape:'html':'UTF-8'} <small>/Night</small></span>
                        <button type="button" class="bk2-room-card__bookmark" data-bk-bookmark
                                data-id="{$rm.id_product|intval}"
                                data-name="{$rm.name|escape:'html':'UTF-8'}"
                                data-url="{$rm.product_url|escape:'html':'UTF-8'}"
                                data-price="{$rm.price_formatted|escape:'html':'UTF-8'}"
                                data-currency="{$bk.currency_sign|escape:'html':'UTF-8'}"
                                data-image="{$rm.image|escape:'html':'UTF-8'}"
                                aria-label="Bookmark {$rm.name|escape:'html':'UTF-8'}">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        </button>
                    </div>
                    <div class="bk2-room-card__body">
                        <h3 class="bk2-room-card__title">{$rm.name|escape:'html':'UTF-8'}</h3>
                        <p class="bk2-room-card__meta">{$rm.persons_label|escape:'html':'UTF-8'} · {$rm.bath_label|escape:'html':'UTF-8'} · {$rm.beds_label|escape:'html':'UTF-8'}</p>
                        <a href="{$rm.product_url|escape:'html':'UTF-8'}" class="bk2-room-card__link">Discover More</a>
                    </div>
                </article>
                {/foreach}
                </div>

                <button type="button" class="bk2-rooms__arrow bk2-rooms__arrow--prev" data-bk-rooms-prev aria-label="Previous rooms">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <button type="button" class="bk2-rooms__arrow bk2-rooms__arrow--next" data-bk-rooms-next aria-label="Next rooms">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </section>

    <!-- ============ 4. Customer Services (4 interactive panels) ============ -->
    <section id="bk-services" class="bk2-services" data-bk-services>
        <div class="bk2-services__bg-stack" aria-hidden="true">
            {foreach from=$bk_home.services item=svc name=svcbg}
            <div class="bk2-services__bg{if $smarty.foreach.svcbg.first} is-active{/if}" data-bk-service-bg="{$smarty.foreach.svcbg.index}" style="background-image: url('{$svc.image|escape:'html':'UTF-8'}');"></div>
            {/foreach}
        </div>
        <div class="bk2-services__veil" aria-hidden="true"></div>
        <div class="bk-container bk2-services__inner">
            <div class="bk2-services__intro">
                <p class="bk2-eyebrow bk2-eyebrow--light">Customer Services</p>
                <h2 class="bk2-title bk2-title--light">Tailored Services For Every Guest</h2>
            </div>
            <div class="bk2-services__list">
                {foreach from=$bk_home.services item=svc name=svcitem}
                <button type="button" class="bk2-service-item{if $smarty.foreach.svcitem.first} is-active{/if}" data-bk-service-item="{$smarty.foreach.svcitem.index}">
                    <span class="bk2-service-item__num">0{$smarty.foreach.svcitem.iteration}</span>
                    <span class="bk2-service-item__main">
                        <span class="bk2-service-item__title">{$svc.title|escape:'html':'UTF-8'}</span>
                        <span class="bk2-service-item__text">{$svc.text|escape:'html':'UTF-8'}</span>
                    </span>
                    <a href="{$svc.url|escape:'html':'UTF-8'}" class="bk2-service-item__cta" tabindex="-1">Book Now</a>
                </button>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- ============ 5. Featured Suites Slider (full-width dark) ============ -->
    <section class="bk2-featured" data-bk-featured-slider>
        <div class="bk-container bk2-featured__frame">
            <div class="bk2-featured__track">
                {foreach from=$bk_home.featured_suites item=rm name=fslide}
                <div class="bk2-featured__slide{if $smarty.foreach.fslide.first} is-active{/if}" data-bk-featured-slide>
                    <div class="bk2-featured__info">
                        <p class="bk2-featured__tag">// Location</p>
                        <p class="bk2-featured__loc">{$bk.hotel_address|escape:'html':'UTF-8'}</p>
                        <p class="bk2-featured__tag">Reviews</p>
                        <p class="bk2-featured__reviews">
                            <span class="bk2-stars" aria-hidden="true">★★★★★</span>
                            4.9 (2.4k Reviews)
                        </p>
                    </div>
                    <div class="bk2-featured__photo" style="background-image: url('{$rm.image|escape:'html':'UTF-8'}');"></div>
                    <div class="bk2-featured__copy">
                        <h2 class="bk2-featured__title">{$rm.name|escape:'html':'UTF-8'}</h2>
                        <p class="bk2-featured__desc">{$rm.short_desc|escape:'html':'UTF-8'}</p>
                        <p class="bk2-featured__price">{$bk.currency_sign|escape:'html':'UTF-8'}{$rm.price_formatted|escape:'html':'UTF-8'} <small>/ Night</small></p>
                        <a href="{$rm.book_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">Booking Now</a>
                    </div>
                </div>
                {/foreach}
            </div>

            <div class="bk2-featured__nav">
                <button type="button" class="bk2-featured__arrow" data-bk-featured-prev aria-label="Previous suite">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <span class="bk2-featured__count"><span data-bk-featured-current>01</span> / {$bk_home.featured_suites|@count|string_format:'%02d'}</span>
                <button type="button" class="bk2-featured__arrow" data-bk-featured-next aria-label="Next suite">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </section>

    <!-- ============ 6. Facilities (8-item icon grid) ============ -->
    <section id="bk-facilities" class="bk2-facilities">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Facilities</p>
                <h2 class="bk2-title">Facilities</h2>
                <p class="bk2-lead">We help guests create lifetime memories they never forget.</p>
            </div>

            <div class="bk2-facilities__grid">
                {foreach from=$bk_home.facilities item=fac}
                <div class="bk2-facility">
                    <span class="bk2-facility__icon">
                        {if $fac.icon == 'gym'}
                            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 6v12M18 6v12M3 9v6M21 9v6M6 12h12"/></svg>
                        {elseif $fac.icon == 'star'}
                            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 2 15 9l7 .5-5.4 4.6L18.5 21 12 17.2 5.5 21l1.9-6.9L2 9.5 9 9z"/></svg>
                        {elseif $fac.icon == 'bed'}
                            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8M2 15h20M6 10V7a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3M13 10V7a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3"/></svg>
                        {elseif $fac.icon == 'sofa'}
                            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 11V8a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v3M3 11a2 2 0 0 1 2 2v3h14v-3a2 2 0 1 1 4 0v6H1v-6a2 2 0 0 1 2-2z"/></svg>
                        {elseif $fac.icon == 'bell'}
                            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                        {elseif $fac.icon == 'wifi'}
                            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5.5 5.5 0 0 1 7 0M2 9a15 15 0 0 1 20 0"/><circle cx="12" cy="19.5" r="1.2" fill="currentColor" stroke="none"/></svg>
                        {elseif $fac.icon == 'gem'}
                            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 3h12l4 6-10 12L2 9zM2 9h20M9 3l-1 6 4 12 4-12-1-6"/></svg>
                        {else}
                            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 22h8M12 15v7M5 3h14l-2 8a5 5 0 0 1-10 0z"/></svg>
                        {/if}
                    </span>
                    <h3 class="bk2-facility__title">{$fac.title|escape:'html':'UTF-8'}</h3>
                </div>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- ============ 7. Check Availability & Book Your Stay ============ -->
    <section id="bk-booking" class="bk2-booking">
        <div class="bk2-booking__photo" style="background-image: url('{$bk_home.about_image_2|escape:'html':'UTF-8'}');"></div>
        <div class="bk2-booking__panel">
            <p class="bk2-eyebrow">Check Availability</p>
            <h2 class="bk2-title">Check Availability &amp; Book Your Stay</h2>

            <form class="bk2-booking__form" id="bk-availability-form" action="{$bk.search_url|escape:'html':'UTF-8'}" method="get">
                <div class="bk2-field">
                    <label for="bk-checkin">Check-In</label>
                    <input type="date" id="bk-checkin" name="date_from" value="{$bk.default_checkin|escape:'html':'UTF-8'}" min="{$bk.default_checkin|escape:'html':'UTF-8'}" required>
                </div>
                <div class="bk2-field">
                    <label for="bk-checkout">Check-Out</label>
                    <input type="date" id="bk-checkout" name="date_to" value="{$bk.default_checkout|escape:'html':'UTF-8'}" min="{$bk.default_checkin|escape:'html':'UTF-8'}" required>
                </div>
                <div class="bk2-field">
                    <label for="bk-guests">Guests</label>
                    <select id="bk-guests" name="bk_guests">
                        <option value="1">1 Guest</option>
                        <option value="2" selected="selected">2 Guests</option>
                        <option value="3">3 Guests</option>
                        <option value="4">4 Guests</option>
                    </select>
                </div>
                <div class="bk2-field">
                    <label for="bk-rooms">Rooms</label>
                    <select id="bk-rooms-count" name="bk_rooms">
                        <option value="1" selected="selected">1 Room</option>
                        <option value="2">2 Rooms</option>
                        <option value="3">3 Rooms</option>
                        <option value="4">4 Rooms</option>
                    </select>
                </div>
                <button type="submit" class="bk-btn bk-btn--gold bk2-booking__submit">Check Now</button>
            </form>

            <ul class="bk2-booking__checks">
                {foreach from=$bk_home.availability_checks item=chk}
                <li>
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6 9 17l-5-5"/></svg>
                    {$chk|escape:'html':'UTF-8'}
                </li>
                {/foreach}
            </ul>
        </div>
    </section>

    <!-- ============ 8. Our Expert (team grid) ============ -->
    <section id="bk-team" class="bk2-team">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Our Expert</p>
                <h2 class="bk2-title">Meet The Faces Behind Your Perfect Stay</h2>
                <p class="bk2-lead">A passionate team ensuring comfort, care, and unforgettable guest experiences.</p>
            </div>

            <div class="bk2-team__grid">
                {foreach from=$bk_home.team item=member}
                <article class="bk2-team-card">
                    <div class="bk2-team-card__media">
                        {if $member.image}
                            <img src="{$member.image|escape:'html':'UTF-8'}" alt="{$member.name|escape:'html':'UTF-8'}">
                        {else}
                            <span class="bk2-team-card__initials">{$member.initials|escape:'html':'UTF-8'}</span>
                        {/if}
                        <div class="bk2-team-card__socials">
                            <a href="javascript:void(0)" aria-label="Facebook"><svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z"/></svg></a>
                            <a href="javascript:void(0)" aria-label="X"><svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231z"/></svg></a>
                            <a href="javascript:void(0)" aria-label="LinkedIn"><svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M4.98 3.5C4.98 4.88 3.87 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.24 8.31h4.52V23H.24zM8.34 8.31h4.33v2h.06c.6-1.14 2.08-2.34 4.28-2.34 4.58 0 5.42 3.01 5.42 6.92V23h-4.5v-7.1c0-1.7-.03-3.88-2.37-3.88-2.37 0-2.73 1.85-2.73 3.76V23h-4.5z"/></svg></a>
                            <a href="mailto:{$member.mail|escape:'html':'UTF-8'}" aria-label="Email"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></a>
                        </div>
                    </div>
                    <div class="bk2-team-card__body">
                        <h3 class="bk2-team-card__name">{$member.name|escape:'html':'UTF-8'}</h3>
                        <p class="bk2-team-card__role">{$member.role|escape:'html':'UTF-8'}</p>
                    </div>
                </article>
                {/foreach}
            </div>

            <div class="bk2-center-cta">
                <a href="{$bk.team_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--dark">View More</a>
            </div>
        </div>
    </section>

    <!-- ============ 9. Testimonial slider ============ -->
    <section id="bk-testimonials" class="bk2-testi" data-bk-testi-slider{if $bk_home.testi_bg} style="background-image: url('{$bk_home.testi_bg|escape:'html':'UTF-8'}');"{/if}>
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Testimonial</p>
                <h2 class="bk2-title">What Our Guests Say About Their Stay</h2>
            </div>

            <div class="bk2-testi__viewport">
                {foreach from=$bk_home.testimonials item=ts name=tloop}
                <div class="bk2-testi__slide{if $smarty.foreach.tloop.first} is-active{/if}" data-bk-testi-slide>
                    <div class="bk2-testi__quote-mark" aria-hidden="true">“</div>
                    <p class="bk2-testi__quote">{$ts.quote|escape:'html':'UTF-8'}</p>
                    <div class="bk2-testi__who">
                        <span class="bk2-testi__initials">{$ts.initials|escape:'html':'UTF-8'}</span>
                        <span>
                            <strong>{$ts.name|escape:'html':'UTF-8'}</strong>
                            <small>{$ts.role|escape:'html':'UTF-8'}</small>
                        </span>
                        <span class="bk2-stars">★★★★★</span>
                    </div>
                </div>
                {/foreach}
            </div>

            <div class="bk2-testi__nav">
                <button type="button" data-bk-testi-prev aria-label="Previous review">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <button type="button" data-bk-testi-next aria-label="Next review">
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
                </button>
            </div>
        </div>
    </section>

    <!-- ============ 10. Trending Topics / Hotel Articles ============ -->
    <section id="bk-blog" class="bk2-blog">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Trending Topics</p>
                <h2 class="bk2-title">Hotel Articles</h2>
                <p class="bk2-lead">Experience the perfect blend of comfort, style, and hospitality.</p>
            </div>

            <div class="bk2-blog__grid">
                {foreach from=$bk_home.articles item=post}
                <article class="bk2-post-card">
                    <a href="{$post.url|escape:'html':'UTF-8'}" class="bk2-post-card__media" style="background-image: url('{$post.image|escape:'html':'UTF-8'}');">
                        <span class="bk2-post-card__date">{$post.date|escape:'html':'UTF-8'}</span>
                    </a>
                    <div class="bk2-post-card__body">
                        <p class="bk2-post-card__cat">{$post.category|escape:'html':'UTF-8'}</p>
                        <h3 class="bk2-post-card__title"><a href="{$post.url|escape:'html':'UTF-8'}">{$post.title|escape:'html':'UTF-8'}</a></h3>
                        <p class="bk2-post-card__excerpt">{$post.excerpt|escape:'html':'UTF-8'}</p>
                        <a href="{$post.url|escape:'html':'UTF-8'}" class="bk2-room-card__link">Explore More</a>
                    </div>
                </article>
                {/foreach}
            </div>

            <div class="bk2-center-cta">
                <a href="{$bk.blog_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--dark">View More</a>
            </div>
        </div>
    </section>
</div>
