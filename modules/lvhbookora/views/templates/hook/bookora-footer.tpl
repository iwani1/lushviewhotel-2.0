{*
* Lush View Hotel - Bookora Footer 2: 5-logo partner strip, embedded hotel map,
* Contact Us / Drop A Line columns, Quick Links, Guest Services, working
* Newsletter form, copyright bar, floating [TOP] button, and shared modals.
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div id="bk-footer" class="bk-root bk-h2">

    <!-- ============ Partner / Sponsor Logo Strip ============ -->
    <div class="bk2-partners">
        <div class="bk-container bk2-partners__row">
            {foreach from=$bk_partners item=pt}
            <div class="bk2-partner">
                <span class="bk2-partner__name">{$pt.name|escape:'html':'UTF-8'}</span>
                <span class="bk2-partner__tag">{$pt.tag|escape:'html':'UTF-8'}</span>
            </div>
            {/foreach}
        </div>
    </div>

    <!-- ============ Footer 2 ============ -->
    <footer class="bk2-footer">
        <div class="bk-container bk2-footer__grid">

            <!-- Map + address -->
            <div class="bk2-footer__col bk2-footer__col--map">
                <div class="bk2-footer__map">
                    <iframe src="{$bk.map_embed_url|escape:'html':'UTF-8'}" loading="lazy" title="Lush View Hotel Location" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    <a class="bk2-footer__map-link" href="{$bk.map_directions_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Open in Maps
                    </a>
                </div>
                <p class="bk2-footer__addr">{$bk.hotel_address|escape:'html':'UTF-8'}</p>
            </div>

            <!-- Contact Us + Drop A Line -->
            <div class="bk2-footer__col">
                <h3 class="bk2-footer__title">Contact Us</h3>
                <p class="bk2-footer__label">Reservation:</p>
                <a class="bk2-footer__link" href="tel:{$bk.hotel_phone_href|escape:'html':'UTF-8'}">{$bk.hotel_phone|escape:'html':'UTF-8'}</a>
                <p class="bk2-footer__label">Booking:</p>
                <a class="bk2-footer__link" href="mailto:reservations@lushviewhotel.net">reservations@lushviewhotel.net</a>

                <h3 class="bk2-footer__title bk2-footer__title--spaced">Drop A Line</h3>
                <p class="bk2-footer__label">Information:</p>
                <a class="bk2-footer__link" href="mailto:{$bk.hotel_email|escape:'html':'UTF-8'}">{$bk.hotel_email|escape:'html':'UTF-8'}</a>
                <p class="bk2-footer__label">Reception:</p>
                <a class="bk2-footer__link" href="mailto:frontdesk@lushviewhotel.net">frontdesk@lushviewhotel.net</a>
            </div>

            <!-- Quick Links -->
            <div class="bk2-footer__col">
                <h3 class="bk2-footer__title">Quick Links</h3>
                <a class="bk2-footer__link" href="{$bk.home_url|escape:'html':'UTF-8'}">Home 2</a>
                <a class="bk2-footer__link" href="{$bk.home1_url|escape:'html':'UTF-8'}">Home 1</a>
                <a class="bk2-footer__link" href="{$bk.home3_url|escape:'html':'UTF-8'}">Home 3</a>
                <a class="bk2-footer__link" href="{$bk.about_url|escape:'html':'UTF-8'}">About Us</a>
                <a class="bk2-footer__link" href="{$bk.service_url|escape:'html':'UTF-8'}">Our Services</a>
                <a class="bk2-footer__link" href="{$bk.project_url|escape:'html':'UTF-8'}">Our Projects</a>
                <a class="bk2-footer__link" href="{$bk.team_url|escape:'html':'UTF-8'}">Our Team</a>
            </div>

            <!-- Guest Services + Newsletter -->
            <div class="bk2-footer__col">
                <h3 class="bk2-footer__title">Guest Services</h3>
                <a class="bk2-footer__link" href="{$bk.search_url|escape:'html':'UTF-8'}">All Listings</a>
                <a class="bk2-footer__link" href="{$bk.pricing_url|escape:'html':'UTF-8'}">Pricing</a>
                <a class="bk2-footer__link" href="{$bk.blog_url|escape:'html':'UTF-8'}">Blog</a>
                <a class="bk2-footer__link" href="{$bk.testimonial_url|escape:'html':'UTF-8'}">Testimonials</a>
                <a class="bk2-footer__link" href="{$bk.contact_url|escape:'html':'UTF-8'}">Contact</a>

                <h3 class="bk2-footer__title bk2-footer__title--spaced">Newsletter</h3>
                <p class="bk2-footer__news-copy">Subscribe Our Newsletter</p>
                <form class="bk2-newsletter" action="{$bk.subscribe_url|escape:'html':'UTF-8'}" method="post">
                    <input type="hidden" name="submitBkNewsletter" value="1">
                    <input type="hidden" name="bk_return" class="bk-newsletter-return" value="{$bk.home_url|escape:'html':'UTF-8'}">
                    <input type="email" name="bk_newsletter_email" placeholder="Your email address" required>
                    <button type="submit" class="bk-btn bk-btn--gold">Subscribe Now</button>
                </form>
            </div>
        </div>

        <!-- Copyright bar -->
        <div class="bk2-footer__bottom">
            <div class="bk-container bk2-footer__bottom-inner">
                <p>Copyright &copy; {$bk_year|intval} {$bk.hotel_name|escape:'html':'UTF-8'}. All Rights Reserved.</p>
                <div class="bk2-footer__legal">
                    {foreach from=$bk.cms_links item=cms}
                        <a href="{$cms.url|escape:'html':'UTF-8'}">{$cms.title|escape:'html':'UTF-8'}</a>
                    {/foreach}
                </div>
            </div>
        </div>
    </footer>

    <!-- ============ Floating [TOP] scroll button ============ -->
    <button type="button" id="bk-top-btn" class="bk-top-btn" aria-label="Back to top">[TOP]</button>

    <!-- ============ Shared Report / Inquiry Modal ============ -->
    <div id="bk-report-modal" class="bk-modal" aria-hidden="true">
        <div class="bk-modal__backdrop" data-bk-close-modal></div>
        <div class="bk-modal__panel" role="dialog" aria-modal="true" aria-label="Report or Inquiry">
            <button type="button" class="bk-modal__close" data-bk-close-modal aria-label="Close">&times;</button>
            <h3 class="bk-modal__title">Report / Inquiry</h3>
            <p class="bk-modal__sub">Tell us how we can help — our team responds within one business day.</p>
            <form action="{$bk.contact_url|escape:'html':'UTF-8'}" method="get">
                <input type="text" name="bk_report_name" placeholder="Your name" required>
                <input type="email" name="bk_report_email" placeholder="Your email" required>
                <textarea name="bk_report_message" rows="4" placeholder="Your message" required></textarea>
                <button type="submit" class="bk-btn bk-btn--gold">Send Message</button>
            </form>
        </div>
    </div>

    <!-- ============ Share toast ============ -->
    <div id="bk-share-toast" class="bk-share-toast" aria-live="polite">Link copied to clipboard</div>

    <!-- ============ Shared Gallery Lightbox ============ -->
    <div id="bk-lightbox" class="bk-lightbox" aria-hidden="true">
        <div class="bk-lightbox__backdrop" data-bk-close-lightbox></div>
        <figure class="bk-lightbox__figure">
            <img src="" alt="Gallery image">
            <figcaption></figcaption>
        </figure>
        <button type="button" class="bk-lightbox__close" data-bk-close-lightbox aria-label="Close">&times;</button>
        <button type="button" class="bk-lightbox__prev" data-bk-lightbox-prev aria-label="Previous">‹</button>
        <button type="button" class="bk-lightbox__next" data-bk-lightbox-next aria-label="Next">›</button>
    </div>
</div>
