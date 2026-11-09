{*
* Lush View Hotel - Bookora Bookmarks webpage (saved rooms drawer/page).
* Saved rooms are stored in browser localStorage and rendered here by
* bookora.js from the live room seed data.
* @author    Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php
*}
<div class="bk-root bk-h2 bk-page bk-bookmarks-page">
    <section class="bk-page-bookmarks">
        <div class="bk-container">
            <div class="bk2-head bk2-head--center">
                <p class="bk2-eyebrow">Bookmarks</p>
                <h2 class="bk2-title">Your Saved Rooms &amp; Suites</h2>
                <p class="bk2-lead">Rooms you bookmark are saved on this device. Tap the bookmark icon on any room card to add it here.</p>
            </div>

            <div class="bk2-rooms__grid" id="bk-bookmarks-grid">
                <!-- Rendered client-side from localStorage + live room seed -->
            </div>
            <p class="bk-bookmarks-empty" id="bk-bookmarks-empty" hidden>
                You have no bookmarked rooms yet — explore
                <a href="{$bk.search_url|escape:'html':'UTF-8'}">All Listings</a> and tap the bookmark icon to save your favourites.
            </p>
        </div>
    </section>
</div>
