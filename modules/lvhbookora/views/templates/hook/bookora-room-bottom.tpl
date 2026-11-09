{*
* Lush View Hotel - Bookora room detail bottom block: Beds / Bath / Guest spec
* cards, interactive map + Get Directions, and the guest Review section.
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div class="bk-root bk-h2 bk-room-detail">

    <!-- Beds / Bath / Guest spec cards -->
    <div class="bk-room-detail__specs">
        <div class="bk-spec-card">
            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8M2 15h20M6 10V7a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3M13 10V7a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3"/></svg>
            <strong>{$bk_room.beds_label|escape:'html':'UTF-8'}</strong>
            <span>Beds</span>
        </div>
        <div class="bk-spec-card">
            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 12h16a1 1 0 0 1 1 1v2a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-2a1 1 0 0 1 1-1zM6 12V6a2 2 0 0 1 2-2h1"/><path d="M7 19v2M17 19v2"/></svg>
            <strong>{$bk_room.bath_label|escape:'html':'UTF-8'}</strong>
            <span>Bath</span>
        </div>
        <div class="bk-spec-card">
            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <strong>{$bk_room.persons_label|escape:'html':'UTF-8'}</strong>
            <span>Guest</span>
        </div>
    </div>

    <!-- Interactive map + Get Directions -->
    <div class="bk-room-detail__map">
        <div class="bk-room-detail__map-head">
            <h3>Hotel Location</h3>
            <a href="{$bk.map_directions_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener" class="bk-btn bk-btn--dark">
                Get Directions
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
        <div class="bk-room-detail__map-frame">
            <iframe src="{$bk.map_embed_url|escape:'html':'UTF-8'}" loading="lazy" title="Lush View Hotel Map" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>

    <!-- Review section -->
    <div class="bk-room-detail__reviews">
        <div class="bk-room-detail__map-head">
            <h3>Review</h3>
            <a href="{$bk.testimonial_url|escape:'html':'UTF-8'}#bk-review-form" class="bk-btn bk-btn--gold">Write a Review</a>
        </div>
        <div class="bk-room-detail__review-note">
            <span class="bk2-stars">★★★★★</span>
            <p>Rated <strong>4.9 / 5</strong> by verified guests — read more guest experiences on our
                <a href="{$bk.testimonial_url|escape:'html':'UTF-8'}">Testimonials</a> page.</p>
        </div>
    </div>
</div>
