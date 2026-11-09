{*
* Lush View Hotel - Bookora Home 1 webpage (classic editorial homepage).
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-home1">

    <!-- Editorial split hero -->
    <section class="bk2-hero" style="background-image: url('{$bk_page.hero_image|escape:'html':'UTF-8'}');">
        <div class="bk2-hero__overlay"></div>
        <div class="bk-container bk2-hero__inner">
            <p class="bk2-hero__eyebrow">{$bk.cfg.LVH_BK_EYEBROW|escape:'html':'UTF-8'}</p>
            <h1 class="bk2-hero__title">Luxury Stay Hotel<br><em>Experience Comfort &amp; Elegance</em></h1>
            <p class="bk2-hero__sub">{$bk.cfg.LVH_BK_HERO_SUB|escape:'html':'UTF-8'}</p>
            <div class="bk2-hero__ctas">
                <a href="#bk-h1-rooms" class="bk-btn bk-btn--outline-gold">Explore More</a>
                <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">Booking Now</a>
            </div>
        </div>
    </section>

    <!-- About split -->
    <section class="bk2-about">
        <div class="bk-container bk2-about__grid">
            <div class="bk2-about__media">
                <div class="bk2-about__photo bk2-about__photo--main" style="background-image: url('{$bk_page.about_image_left|escape:'html':'UTF-8'}');"></div>
            </div>
            <div class="bk2-about__copy">
                <p class="bk2-eyebrow">About Us</p>
                <h2 class="bk2-title">{$bk.cfg.LVH_BK_ABOUT_TITLE|escape:'html':'UTF-8'}</h2>
                <p class="bk2-lead">{$bk.cfg.LVH_BK_ABOUT_BODY|escape:'html':'UTF-8'}</p>
                <a href="{$bk.about_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--dark">Discover More</a>
            </div>
        </div>
    </section>

    <!-- Rooms slider grid -->
    <section id="bk-h1-rooms" class="bk2-rooms">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Popular Rooms</p>
                <h2 class="bk2-title">Discover The Rooms Guests Love Most</h2>
            </div>
            <div class="bk2-rooms__grid">
                {foreach from=$bk_page.bedrooms item=rm}
                <article class="bk2-room-card">
                    <div class="bk2-room-card__media" style="background-image: url('{$rm.image|escape:'html':'UTF-8'}');">
                        <span class="bk2-room-card__price">{$bk.currency_sign|escape:'html':'UTF-8'}{$rm.price_formatted|escape:'html':'UTF-8'} <small>/Night</small></span>
                    </div>
                    <div class="bk2-room-card__body">
                        <h3 class="bk2-room-card__title">{$rm.name|escape:'html':'UTF-8'}</h3>
                        <p class="bk2-room-card__meta">{$rm.persons_label|escape:'html':'UTF-8'} · {$rm.bath_label|escape:'html':'UTF-8'} · {$rm.beds_label|escape:'html':'UTF-8'}</p>
                        <a href="{$rm.product_url|escape:'html':'UTF-8'}" class="bk2-room-card__link">Discover More</a>
                    </div>
                </article>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- Special offers -->
    <section class="bk-page-offers">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Special Offers</p>
                <h2 class="bk2-title">Exclusive Packages For Every Occasion</h2>
            </div>
            <div class="bk-page-offers__grid">
                {foreach from=$bk_page.offers item=offer}
                <article class="bk-offer-card">
                    <div class="bk-offer-card__media" style="background-image: url('{$offer.image|escape:'html':'UTF-8'}');">
                        <span class="bk-offer-card__price">{$offer.price_label|escape:'html':'UTF-8'} <small>{$offer.unit_label|escape:'html':'UTF-8'}</small></span>
                    </div>
                    <div class="bk-offer-card__body">
                        <h3>{$offer.title|escape:'html':'UTF-8'}</h3>
                        <ul>
                            {foreach from=$offer.perks item=perk}
                            <li><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M20 6 9 17l-5-5"/></svg>{$perk|escape:'html':'UTF-8'}</li>
                            {/foreach}
                        </ul>
                        <a href="{$offer.url|escape:'html':'UTF-8'}" class="bk2-room-card__link">Booking Now</a>
                    </div>
                </article>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- Gallery lightbox -->
    <section class="bk-page-gallery">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Interior Gallery</p>
                <h2 class="bk2-title">A Look Inside Lush View Hotel</h2>
            </div>
            <div class="bk-page-gallery__grid" data-bk-lightbox>
                {foreach from=$bk_page.gallery item=gi}
                <figure class="bk-gallery-item">
                    <img src="{$gi.url|escape:'html':'UTF-8'}" alt="{$gi.caption|escape:'html':'UTF-8'}" loading="lazy">
                    <figcaption><strong>{$gi.caption|escape:'html':'UTF-8'}</strong></figcaption>
                </figure>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="bk2-testi" data-bk-testi-slider>
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Testimonial</p>
                <h2 class="bk2-title">What Our Guests Say About Their Stay</h2>
            </div>
            <div class="bk2-testi__viewport">
                {foreach from=$bk_page.testimonials item=ts name=tloop}
                <div class="bk2-testi__slide{if $smarty.foreach.tloop.first} is-active{/if}" data-bk-testi-slide>
                    <div class="bk2-testi__quote-mark" aria-hidden="true">“</div>
                    <p class="bk2-testi__quote">{$ts.quote|escape:'html':'UTF-8'}</p>
                    <div class="bk2-testi__who">
                        <span class="bk2-testi__initials">{$ts.initials|escape:'html':'UTF-8'}</span>
                        <span><strong>{$ts.name|escape:'html':'UTF-8'}</strong><small>{$ts.role|escape:'html':'UTF-8'}</small></span>
                        <span class="bk2-stars">★★★★★</span>
                    </div>
                </div>
                {/foreach}
            </div>
            <div class="bk2-testi__nav">
                <button type="button" data-bk-testi-prev aria-label="Previous review">‹</button>
                <button type="button" data-bk-testi-next aria-label="Next review">›</button>
            </div>
        </div>
    </section>
</div>
