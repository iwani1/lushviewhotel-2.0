{*
* Lush View Hotel - Bookora filter bar for All Listings / Search Result
* (rendered via displayTopColumn on the native QloApps category page).
*
* @author    Lush View Hotel
* @copyright 2026 Lush View Hotel
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}
<div class="bk-root bk-h2 bk-filters" data-bk-filters>
    <div class="bk-filters__bar">

        <form class="bk-filters__search" action="{$bk.search_page_url|escape:'html':'UTF-8'}" method="get">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" name="s" value="{$bk_search_keywords|escape:'html':'UTF-8'}" placeholder="Where are you going?">
        </form>

        <div class="bk-filters__price">
            <label>Price</label>
            <input type="number" min="0" step="10" data-bk-price-min placeholder="Min₵">
            <span>—</span>
            <input type="number" min="0" step="10" data-bk-price-max placeholder="Max₵">
        </div>

        <p class="bk-filters__count"><strong data-bk-items-found>0</strong> Items Found</p>

        <div class="bk-filters__views" role="group" aria-label="View switcher">
            <button type="button" class="is-active" data-bk-view="grid" aria-label="Grid view">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Filter: Grid
            </button>
            <button type="button" data-bk-view="list" aria-label="List view">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                List
            </button>
            <button type="button" data-bk-view="map" aria-label="Map view">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Map
            </button>
        </div>

        <div class="bk-filters__sort">
            <label for="bk-sort-select">Sort By</label>
            <select id="bk-sort-select" data-bk-sort>
                <option value="">Sort By</option>
                <option value="price-asc">Price: Low to High</option>
                <option value="price-desc">Price: High to Low</option>
                <option value="name-asc">Name: A to Z</option>
                <option value="name-desc">Name: Z to A</option>
                <option value="date-desc">Newest First</option>
                <option value="date-asc">Oldest First</option>
                <option value="position-asc">Position: First to Last</option>
                <option value="position-desc">Position: Last to First</option>
            </select>
        </div>
    </div>

    <!-- Interactive map panel (Map view) -->
    <div class="bk-filters__map" data-bk-map-panel hidden>
        <iframe src="{$bk.map_embed_url|escape:'html':'UTF-8'}" loading="lazy" title="Hotel Location Map" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
</div>
