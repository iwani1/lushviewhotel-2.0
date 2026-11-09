{*
* Lush View Hotel - Bookora Gold Ribbon, 4-Column Luxury Footer & Lightbox
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div id="bk-footer" class="bk-root bk-footer-wrap">

    <!-- Signature Bookora Champagne Gold Feature Ribbon -->
    <div class="bk-gold-ribbon">
        <div class="bk-container bk-gold-ribbon__inner">
            <span>&#10022; Luxury Bedrooms &amp; Suites</span>
            <span>&#10022; 24/7 Front Desk &amp; Security</span>
            <span>&#10022; 60-Seat Conference Hall</span>
            <span>&#10022; Cash, MoMo &amp; Card Accepted</span>
        </div>
    </div>

    <!-- Main Obsidian 4-Column Footer -->
    <footer class="bk-footer">
        <div class="bk-container bk-footer__grid">
            <!-- Inset Contact Card (matches Bookora left dark column) -->
            <div class="bk-footer__contact-card">
                <a href="{$bk.home_url|escape:'html':'UTF-8'}" class="bk-footer__brand">
                    {if $bk.logo_url}
                        <img src="{$bk.logo_url|escape:'html':'UTF-8'}" alt="{$bk.hotel_name|escape:'html':'UTF-8'}">
                    {/if}
                    <span>{$bk.hotel_name|escape:'html':'UTF-8'}</span>
                </a>
                <p class="bk-footer__tagline">
                    Refined comfort, tranquil surroundings, and attentive Ghanaian hospitality for business and leisure guests.
                </p>
                <h4>Contact Info</h4>
                <ul class="bk-footer__contact-list">
                    <li>
                        <a href="tel:{$bk.hotel_phone_href|escape:'html':'UTF-8'}">{$bk.hotel_phone|escape:'html':'UTF-8'}</a>
                    </li>
                    <li>{$bk.hotel_address|escape:'html':'UTF-8'}</li>
                    <li>
                        <a href="mailto:{$bk.hotel_email|escape:'html':'UTF-8'}">{$bk.hotel_email|escape:'html':'UTF-8'}</a>
                    </li>
                </ul>
            </div>

            <!-- Quick Links -->
            <div class="bk-footer__col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="{$bk.home_url|escape:'html':'UTF-8'}">Home</a></li>
                    <li><a href="{$bk.search_url|escape:'html':'UTF-8'}">Rooms &amp; Suites</a></li>
                    <li><a href="{$bk.home_url|escape:'html':'UTF-8'}#bk-offers">Special Offers &amp; Hall</a></li>
                    <li><a href="{$bk.contact_url|escape:'html':'UTF-8'}">Contact Us</a></li>
                    {foreach from=$bk.cms_links item=cm}
                        <li><a href="{$cm.url|escape:'html':'UTF-8'}">{$cm.title|escape:'html':'UTF-8'}</a></li>
                    {/foreach}
                </ul>
            </div>

            <!-- Guest Services -->
            <div class="bk-footer__col">
                <h4>Guest Services</h4>
                <ul>
                    <li><a href="{$bk.search_url|escape:'html':'UTF-8'}">24/7 Front Desk ({$bk.check_in_time|escape:'html':'UTF-8'} Check-In)</a></li>
                    <li><a href="{$bk.home_url|escape:'html':'UTF-8'}#bk-facilities">Gated Parking &amp; 24h Security</a></li>
                    <li><a href="{$bk.home_url|escape:'html':'UTF-8'}#bk-facilities">Restaurant &amp; Room Service</a></li>
                    <li><a href="{$bk.home_url|escape:'html':'UTF-8'}#bk-facilities">Complimentary High-Speed Wi-Fi</a></li>
                    <li><a href="{$bk.contact_url|escape:'html':'UTF-8'}">Conference &amp; Event Hosting</a></li>
                </ul>
            </div>

            <!-- Direct Booking CTA -->
            <div class="bk-footer__col">
                <h4>Direct Reservations</h4>
                <p class="bk-footer__note">
                    Reserve online for instant room confirmation or reach our 24-hour reception desk for group and corporate bookings.
                </p>
                <a href="{$bk.search_url|escape:'html':'UTF-8'}" class="bk-btn bk-btn--gold bk-btn--block">
                    Check Room Availability
                </a>
                <div class="bk-footer__hours">
                    <span>Check-In: <strong>{$bk.check_in_time|escape:'html':'UTF-8'}</strong></span>
                    <span>Check-Out: <strong>{$bk.check_out_time|escape:'html':'UTF-8'}</strong></span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Deep Forest Teal Bottom Bar -->
    <div class="bk-copyright">
        <div class="bk-container bk-copyright__inner">
            <span>&copy; {$bk_year|escape:'html':'UTF-8'} {$bk.hotel_name|escape:'html':'UTF-8'}. All rights reserved.</span>
            <span>Direct Reservations &bull; Best Rate Guaranteed</span>
        </div>
    </div>

    <!-- Full-Screen Photo Lightbox -->
    <div id="bk-lightbox" class="bk-lightbox" aria-hidden="true" role="dialog" aria-label="Photo Preview">
        <div class="bk-lightbox__backdrop" data-bk-lightbox-close></div>
        <div class="bk-lightbox__dialog">
            <button type="button" class="bk-lightbox__close" data-bk-lightbox-close aria-label="Close Preview">&times;</button>
            <img id="bk-lightbox-img" src="" alt="Lush View Hotel">
            <p id="bk-lightbox-caption" class="bk-lightbox__caption"></p>
        </div>
    </div>
</div>
