{*
* Lush View Hotel - Bookora contact cards + embedded hotel map for the native
* Contact page (rendered via displayAfterHotelBranchInformation).
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div class="bk-root bk-h2 bk-contact-extra">
    <div class="bk2-head bk2-head--center">
        <p class="bk2-eyebrow">Contact Us</p>
        <h2 class="bk2-title">We Would Love To Hear From You</h2>
    </div>

    <div class="bk-contact-extra__cards">
        <div class="bk-contact-card">
            <span class="bk-contact-card__icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            <h3>Reservation</h3>
            <a href="tel:{$bk.hotel_phone_href|escape:'html':'UTF-8'}">{$bk.hotel_phone|escape:'html':'UTF-8'}</a>
        </div>
        <div class="bk-contact-card">
            <span class="bk-contact-card__icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            </span>
            <h3>Booking</h3>
            <a href="mailto:reservations@lushviewhotel.net">reservations@lushviewhotel.net</a>
        </div>
        <div class="bk-contact-card">
            <span class="bk-contact-card__icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </span>
            <h3>Address</h3>
            <p>{$bk.hotel_address|escape:'html':'UTF-8'}</p>
        </div>
        <div class="bk-contact-card">
            <span class="bk-contact-card__icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </span>
            <h3>Check-In / Out</h3>
            <p>{$bk.check_in_time|escape:'html':'UTF-8'} / {$bk.check_out_time|escape:'html':'UTF-8'}</p>
        </div>
    </div>

    <div class="bk-contact-extra__map">
        <iframe src="{$bk.map_embed_url|escape:'html':'UTF-8'}" loading="lazy" title="Lush View Hotel Location" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <a class="bk2-footer__map-link" href="{$bk.map_directions_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">Open in Maps</a>
    </div>
</div>
