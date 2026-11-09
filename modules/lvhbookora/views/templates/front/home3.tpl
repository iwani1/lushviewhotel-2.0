{*
* Lush View Hotel - Bookora Home 3 resort webpage content: resort hero,
* Specialties Foods dining menu, Facts & Figures counters.
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-home3">

    <!-- Resort hero -->
    <section class="bk2-hero" style="background-image: url('{$bk_page.hero_image|escape:'html':'UTF-8'}');">
        <div class="bk2-hero__overlay"></div>
        <div class="bk-container bk2-hero__inner">
            <p class="bk2-hero__eyebrow">Welcome To Lush View Resort</p>
            <h1 class="bk2-hero__title">Taste, Rest &amp;<br><em>Celebrate Every Moment</em></h1>
            <p class="bk2-hero__sub">A resort-style escape with gourmet dining, serene pools, and event spaces — minutes from the city.</p>
            <div class="bk2-hero__ctas">
                <a href="#bk-menu" class="bk-btn bk-btn--outline-gold">Specialties Foods</a>
                <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">Booking Now</a>
            </div>
        </div>
    </section>

    <!-- Specialties Foods dining menu -->
    <section id="bk-menu" class="bk-page-menu">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Specialties Foods</p>
                <h2 class="bk2-title">Resort Dining, Crafted With Passion</h2>
            </div>
            <div class="bk-page-menu__grid">
                {foreach from=$bk_page.menu item=dish}
                <article class="bk-dish">
                    <div class="bk-dish__row">
                        <div>
                            <p class="bk-dish__cat">{$dish.category|escape:'html':'UTF-8'}</p>
                            <h3 class="bk-dish__title">{$dish.title|escape:'html':'UTF-8'}</h3>
                            <p class="bk-dish__desc">{$dish.desc|escape:'html':'UTF-8'}</p>
                        </div>
                        <span class="bk-dish__price">{$dish.price|escape:'html':'UTF-8'}</span>
                    </div>
                </article>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- Facts & Figures -->
    <section class="bk2-facts">
        <div class="bk-container bk2-facts__grid">
            {foreach from=$bk_page.facts item=fact}
            <div class="bk2-fact">
                <strong data-bk-countup="{$fact.num|intval}">{$fact.num|escape:'html':'UTF-8'}{$fact.suffix|escape:'html':'UTF-8'}</strong>
                <span>{$fact.label|escape:'html':'UTF-8'}</span>
            </div>
            {/foreach}
        </div>
    </section>

    <!-- Featured suites -->
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

    <!-- Facilities -->
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
