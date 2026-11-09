{*
* Lush View Hotel - Bookora Team webpage content (full team grid).
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-team-page">
    <section class="bk2-team">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Our Expert</p>
                <h2 class="bk2-title">Meet The Faces Behind Your Perfect Stay</h2>
            </div>

            <div class="bk2-team__grid">
                {foreach from=$bk_page.team item=member}
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
        </div>
    </section>
</div>
