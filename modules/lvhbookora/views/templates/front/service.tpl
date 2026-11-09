{*
* Lush View Hotel - Bookora Service webpage content (8-card grid).
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-service-page">
    <section class="bk-page-services">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Customer Services</p>
                <h2 class="bk2-title">Tailored Services For Every Guest</h2>
            </div>

            <div class="bk-page-services__grid">
                {foreach from=$bk_page.services item=svc}
                <article class="bk-service-card">
                    <div class="bk-service-card__media" style="background-image: url('{$svc.image|escape:'html':'UTF-8'}');"></div>
                    <div class="bk-service-card__body">
                        <h3>{$svc.title|escape:'html':'UTF-8'}</h3>
                        <p>{$svc.text|escape:'html':'UTF-8'}</p>
                        <a href="{$svc.url|escape:'html':'UTF-8'}" class="bk2-room-card__link">Book Now</a>
                    </div>
                </article>
                {/foreach}
            </div>
        </div>
    </section>
</div>
