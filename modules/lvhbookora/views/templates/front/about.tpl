{*
* Lush View Hotel - Bookora About Us webpage content.
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-about-page">

    <!-- Intro + More About Us + dual counters -->
    <section class="bk2-about bk-page-about-intro">
        <div class="bk-container bk2-about__grid">
            <div class="bk2-about__media">
                <div class="bk2-about__photo bk2-about__photo--main" style="background-image: url('{$bk_page.about_image_1|escape:'html':'UTF-8'}');"></div>
            </div>
            <div class="bk2-about__copy">
                <p class="bk2-eyebrow">About Us</p>
                <h2 class="bk2-title">{$bk.cfg.LVH_BK_ABOUT_TITLE|escape:'html':'UTF-8'}</h2>
                <p class="bk2-lead">{$bk.cfg.LVH_BK_ABOUT_BODY|escape:'html':'UTF-8'}</p>
                <p class="bk2-lead">More About Us — from our restaurant and conference hall to our serene suites and around-the-clock concierge, every corner of Lush View Hotel is designed to make your stay effortless.</p>
                <div class="bk-page-about-intro__counters">
                    {foreach from=$bk_page.counters item=ct}
                    <div class="bk-page-about-intro__counter">
                        <strong>{$ct.num|escape:'html':'UTF-8'}{$ct.suffix|escape:'html':'UTF-8'}</strong>
                        <span>{$ct.label|escape:'html':'UTF-8'}</span>
                    </div>
                    {/foreach}
                </div>
                <a href="{$bk.contact_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--dark">Discover More</a>
            </div>
        </div>
    </section>

    <!-- Dark Our Facilities (4 cards) -->
    <section class="bk2-facilities bk-dark-section">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow bk2-eyebrow--light">Our Facilities</p>
                <h2 class="bk2-title bk2-title--light">Designed Around The Way You Stay</h2>
            </div>
            <div class="bk2-facilities__grid bk2-facilities__grid--4">
                {foreach from=$bk_page.about_facilities item=fac}
                <div class="bk2-facility bk2-facility--dark">
                    <span class="bk2-facility__icon">
                        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 2 15 9l7 .5-5.4 4.6L18.5 21 12 17.2 5.5 21l1.9-6.9L2 9.5 9 9z"/></svg>
                    </span>
                    <h3 class="bk2-facility__title">{$fac.title|escape:'html':'UTF-8'}</h3>
                </div>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- Our Special Offers (4 interactive packages) -->
    <section class="bk-page-offers">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Our Special Offers</p>
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

    <!-- Featured Suite slider -->
    <section class="bk2-featured" data-bk-featured-slider>
        <div class="bk-container bk2-featured__frame">
            <div class="bk2-featured__track">
                {foreach from=$bk_page.featured_suites item=rm name=fslide}
                <div class="bk2-featured__slide{if $smarty.foreach.fslide.first} is-active{/if}" data-bk-featured-slide>
                    <div class="bk2-featured__info">
                        <p class="bk2-featured__tag">// Location</p>
                        <p class="bk2-featured__loc">{$bk.hotel_address|escape:'html':'UTF-8'}</p>
                        <p class="bk2-featured__tag">Reviews</p>
                        <p class="bk2-featured__reviews"><span class="bk2-stars">★★★★★</span> 4.9 (2.4k Reviews)</p>
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
                <button type="button" class="bk2-featured__arrow" data-bk-featured-prev aria-label="Previous suite">‹</button>
                <span class="bk2-featured__count"><span data-bk-featured-current>01</span> / {$bk_page.featured_suites|@count|string_format:'%02d'}</span>
                <button type="button" class="bk2-featured__arrow" data-bk-featured-next aria-label="Next suite">›</button>
            </div>
        </div>
    </section>

    <!-- 8-item Facilities -->
    <section class="bk2-facilities">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Facilities</p>
                <h2 class="bk2-title">World-Class Facilities For Your Comfort</h2>
            </div>
            <div class="bk2-facilities__grid">
                {foreach from=$bk_page.facilities item=fac}
                <div class="bk2-facility">
                    <span class="bk2-facility__icon">
                        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 2 15 9l7 .5-5.4 4.6L18.5 21 12 17.2 5.5 21l1.9-6.9L2 9.5 9 9z"/></svg>
                    </span>
                    <h3 class="bk2-facility__title">{$fac.title|escape:'html':'UTF-8'}</h3>
                </div>
                {/foreach}
            </div>
        </div>
    </section>
</div>
