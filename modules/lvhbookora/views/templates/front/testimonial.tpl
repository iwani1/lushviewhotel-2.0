{*
* Lush View Hotel - Bookora Testimonial webpage content (6-card review grid +
* working guest review form).
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-testimonial-page">
    <section class="bk-page-testimonials">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Testimonial</p>
                <h2 class="bk2-title">What Our Guests Say About Their Stay</h2>
            </div>

            <div class="bk-page-testimonials__grid">
                {foreach from=$bk_page.testimonials item=ts}
                <article class="bk-testi-card">
                    <div class="bk2-testi__quote-mark" aria-hidden="true">“</div>
                    <p class="bk2-testi__quote">{$ts.quote|escape:'html':'UTF-8'}</p>
                    <div class="bk2-testi__who">
                        <span class="bk2-testi__initials">{$ts.initials|escape:'html':'UTF-8'}</span>
                        <span>
                            <strong>{$ts.name|escape:'html':'UTF-8'}</strong>
                            <small>{$ts.role|escape:'html':'UTF-8'}</small>
                        </span>
                        <span class="bk2-stars">★★★★★</span>
                    </div>
                </article>
                {/foreach}
            </div>
        </div>
    </section>

    <!-- Working guest review form -->
    <section id="bk-review-form" class="bk-page-review-form">
        <div class="bk-container bk-page-review-form__inner">
            <div>
                <p class="bk2-eyebrow">Share Your Experience</p>
                <h2 class="bk2-title">Leave A Review</h2>
                <p class="bk2-lead">Stayed with us recently? Tell future guests about your experience at Lush View Hotel.</p>
            </div>

            <form class="bk-form-card" action="{$bk.testimonial_url|escape:'html':'UTF-8'}" method="post">
                <input type="hidden" name="submitBkReview" value="1">
                <div class="bk-form-row">
                    <input type="text" name="bk_review_name" placeholder="Your name" required>
                    <input type="email" name="bk_review_email" placeholder="Your email">
                </div>
                <div class="bk-form-row">
                    <select name="bk_review_rating">
                        <option value="5">★★★★★ — Excellent</option>
                        <option value="4">★★★★ — Very Good</option>
                        <option value="3">★★★ — Good</option>
                        <option value="2">★★ — Fair</option>
                        <option value="1">★ — Poor</option>
                    </select>
                </div>
                <textarea name="bk_review_message" rows="5" placeholder="Your review message" required></textarea>
                <button type="submit" class="bk-btn bk-btn--gold">Submit Review</button>
            </form>
        </div>
    </section>
</div>
