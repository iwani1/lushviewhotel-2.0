{*
* Lush View Hotel - Bookora room detail top block: Go Back, Bookmark, Share
* (Facebook / X / LinkedIn / Copy), Report / Inquiry trigger.
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div class="bk-root bk-h2 bk-room-actions">
    <button type="button" class="bk-room-actions__btn" data-bk-go-back>
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        Go Back
    </button>

    <button type="button" class="bk-room-actions__btn" data-bk-bookmark
            data-id="{$bk_room.id_product|intval}"
            data-name="{$bk_room.name|escape:'html':'UTF-8'}"
            data-url="{$bk.search_url|escape:'html':'UTF-8'}"
            data-price="{$bk_room.price|escape:'html':'UTF-8'}"
            data-currency="{$bk.currency_sign|escape:'html':'UTF-8'}"
            data-image="">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        Bookmark
    </button>

    <div class="bk-room-actions__share">
        <span class="bk-room-actions__share-label">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
            Share
        </span>
        <a href="https://www.facebook.com/sharer/sharer.php?u=" target="_blank" rel="noopener" data-bk-share="facebook">Facebook</a>
        <a href="https://twitter.com/intent/tweet?url=" target="_blank" rel="noopener" data-bk-share="x">X</a>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=" target="_blank" rel="noopener" data-bk-share="linkedin">LinkedIn</a>
        <button type="button" data-bk-share="copy">Copy</button>
    </div>

    <button type="button" class="bk-room-actions__btn bk-room-actions__btn--report" data-bk-open-report>
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
        Report / Inquiry
    </button>
</div>
