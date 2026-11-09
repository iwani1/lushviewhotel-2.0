{*
* Lush View Hotel - Bookora Blog webpage content (6-article dated grid).
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-blog-page">
    <section class="bk2-blog">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Trending Topics</p>
                <h2 class="bk2-title">Hotel Articles</h2>
            </div>

            <div class="bk2-blog__grid">
                {foreach from=$bk_page.articles item=post}
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
        </div>
    </section>
</div>
