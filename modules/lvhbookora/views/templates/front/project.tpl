{*
* Lush View Hotel - Bookora Project webpage content: filterable lightbox gallery
* + 3 Featured Suite showcase banners.
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-project-page">

    <!-- Visual showcase gallery -->
    <section class="bk-page-gallery">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Our Projects</p>
                <h2 class="bk2-title">A Visual Showcase Of Lush View Hotel</h2>
            </div>

            <div class="bk-page-gallery__filters">
                <button type="button" class="is-active" data-bk-gallery-filter="*">All</button>
                {foreach from=$bk_page.categories item=cat}
                <button type="button" data-bk-gallery-filter="{$cat|escape:'html':'UTF-8'}">{$cat|escape:'html':'UTF-8'}</button>
                {/foreach}
            </div>

            <div class="bk-page-gallery__grid" data-bk-lightbox>
                {foreach from=$bk_page.gallery item=gi}
                <figure class="bk-gallery-item" data-category="{$gi.category|escape:'html':'UTF-8'}">
                    <img src="{$gi.url|escape:'html':'UTF-8'}" alt="{$gi.caption|escape:'html':'UTF-8'}" loading="lazy">
                    <figcaption>
                        <span>{$gi.category|escape:'html':'UTF-8'}</span>
                        <strong>{$gi.caption|escape:'html':'UTF-8'}</strong>
                    </figcaption>
                </figure>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- 3 Featured Suite showcase banners -->
    <section class="bk-page-showcases">
        <div class="bk-container">
            {foreach from=$bk_page.showcases item=rm}
            <div class="bk-showcase" style="background-image: url('{$rm.image|escape:'html':'UTF-8'}');">
                <div class="bk-showcase__veil"></div>
                <div class="bk-showcase__copy">
                    <p class="bk2-eyebrow bk2-eyebrow--light">Featured Suite</p>
                    <h2>{$rm.name|escape:'html':'UTF-8'}</h2>
                    <p>{$rm.short_desc|escape:'html':'UTF-8'}</p>
                    <p class="bk2-featured__price">{$bk.currency_sign|escape:'html':'UTF-8'}{$rm.price_formatted|escape:'html':'UTF-8'} <small>/ Night</small></p>
                    <a href="{$rm.book_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold">Booking Now</a>
                </div>
            </div>
            {/foreach}
        </div>
    </section>
</div>
