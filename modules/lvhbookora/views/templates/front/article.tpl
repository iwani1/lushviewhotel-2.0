{*
* Lush View Hotel - Bookora single blog article content: meta, subheadings,
* pull-quote, inline image, Tags, Share Article, author box, Leave a Comment.
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-article-page">
    {if isset($bk_page.article) && $bk_page.article}
    <article class="bk-article">
        <div class="bk-container bk-article__narrow">

            <!-- Meta -->
            <header class="bk-article__head">
                <p class="bk-article__meta">
                    <span>{$bk_page.article.date|escape:'html':'UTF-8'}</span>
                    <span class="bk-article__dot">•</span>
                    <span>{$bk_page.article.category|escape:'html':'UTF-8'}</span>
                    <span class="bk-article__dot">•</span>
                    <span>By {$bk_page.article.author.name|escape:'html':'UTF-8'}</span>
                </p>
            </header>

            <!-- Body -->
            <div class="bk-article__body">
                {foreach from=$bk_page.article.body item=block}
                    {if $block.type == 'h'}
                        <h2>{$block.text|escape:'html':'UTF-8'}</h2>
                    {elseif $block.type == 'quote'}
                        <blockquote class="bk-article__pullquote">{$block.text|escape:'html':'UTF-8'}</blockquote>
                    {else}
                        <p>{$block.text|escape:'html':'UTF-8'}</p>
                    {/if}
                {/foreach}

                <figure class="bk-article__figure">
                    <img src="{$bk_page.article.image|escape:'html':'UTF-8'}" alt="{$bk_page.article.title|escape:'html':'UTF-8'}" loading="lazy">
                </figure>
            </div>

            <!-- Tags -->
            <div class="bk-article__tags">
                <strong>Tags:</strong>
                {foreach from=$bk_page.article.tags item=tag}
                    <span class="bk-tag">{$tag|escape:'html':'UTF-8'}</span>
                {/foreach}
            </div>

            <!-- Share Article -->
            <div class="bk-article__share">
                <strong>Share Article:</strong>
                <a href="https://www.facebook.com/sharer/sharer.php?u=" target="_blank" rel="noopener" data-bk-share="facebook">Facebook</a>
                <a href="https://twitter.com/intent/tweet?url=" target="_blank" rel="noopener" data-bk-share="x">X</a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=" target="_blank" rel="noopener" data-bk-share="linkedin">LinkedIn</a>
                <button type="button" data-bk-share="copy">Copy Link</button>
            </div>

            <!-- Author bio box -->
            <div class="bk-article__author">
                <span class="bk2-testi__initials">LV</span>
                <div>
                    <strong>{$bk_page.article.author.name|escape:'html':'UTF-8'}</strong>
                    <small>{$bk_page.article.author.role|escape:'html':'UTF-8'}</small>
                    <p>{$bk_page.article.author.bio|escape:'html':'UTF-8'}</p>
                </div>
            </div>

            <!-- Leave a Comment -->
            <section class="bk-article__comments">
                <h2 class="bk2-title">Leave a Comment</h2>
                <form class="bk-form-card" action="{$bk_page.article.url|escape:'html':'UTF-8'}" method="post">
                    <input type="hidden" name="submitBkComment" value="1">
                    <input type="hidden" name="slug" value="{$bk_page.article.slug|escape:'html':'UTF-8'}">
                    <div class="bk-form-row">
                        <input type="text" name="bk_comment_name" placeholder="Your name" required>
                        <input type="email" name="bk_comment_email" placeholder="Your email">
                    </div>
                    <textarea name="bk_comment_message" rows="5" placeholder="Write your comment..." required></textarea>
                    <button type="submit" class="bk-btn bk-btn--gold">Post Comment</button>
                </form>
            </section>

            <!-- Related articles -->
            {if $bk_page.related}
            <section class="bk-article__related">
                <h2 class="bk2-title">Continue Reading</h2>
                <div class="bk2-blog__grid">
                    {foreach from=$bk_page.related item=post}
                    <article class="bk2-post-card">
                        <a href="{$post.url|escape:'html':'UTF-8'}" class="bk2-post-card__media" style="background-image: url('{$post.image|escape:'html':'UTF-8'}');">
                            <span class="bk2-post-card__date">{$post.date|escape:'html':'UTF-8'}</span>
                        </a>
                        <div class="bk2-post-card__body">
                            <h3 class="bk2-post-card__title"><a href="{$post.url|escape:'html':'UTF-8'}">{$post.title|escape:'html':'UTF-8'}</a></h3>
                            <a href="{$post.url|escape:'html':'UTF-8'}" class="bk2-room-card__link">Explore More</a>
                        </div>
                    </article>
                    {/foreach}
                </div>
            </section>
            {/if}
        </div>
    </article>
    {/if}
</div>
