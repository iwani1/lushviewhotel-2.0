{*
* Lush View Hotel - Bookora Pricing webpage content (quote card + live plan
* cards with 6 checkmarks each).
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-pricing-page">
    <section class="bk-page-pricing">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Pricing</p>
                <h2 class="bk2-title">Choose The Perfect Stay Plan For Your comfort and budget.</h2>
            </div>

            <!-- Quote card -->
            <div class="bk-pricing-quote">
                <span class="bk-pricing-quote__mark" aria-hidden="true">“</span>
                <p>Transparent, all-inclusive direct rates — no hidden fees. Every plan includes 24/7 standby power, secure parking, high-speed Wi-Fi, and our warm Lush View hospitality.</p>
            </div>

            <!-- Plan cards -->
            <div class="bk-page-pricing__grid">
                {foreach from=$bk_page.plans item=plan}
                <article class="bk-plan-card">
                    <h3 class="bk-plan-card__title">{$plan.title|escape:'html':'UTF-8'}</h3>
                    <p class="bk-plan-card__sub">{$plan.subtitle|escape:'html':'UTF-8'}</p>
                    <p class="bk-plan-card__price">
                        <span class="bk-plan-card__currency">{$bk.currency_sign|escape:'html':'UTF-8'}</span>{$plan.price_formatted|escape:'html':'UTF-8'}
                        <small>{$plan.period|escape:'html':'UTF-8'}</small>
                    </p>
                    <ul class="bk-plan-card__features">
                        {foreach from=$plan.features item=feat}
                        <li><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M20 6 9 17l-5-5"/></svg>{$feat|escape:'html':'UTF-8'}</li>
                        {/foreach}
                    </ul>
                    <a href="{$plan.book_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold bk-plan-card__cta">Book Now</a>
                </article>
                {/foreach}
            </div>
        </div>
    </section>
</div>
