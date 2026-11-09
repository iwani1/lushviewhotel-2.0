/**
 * Lush View Hotel - Bookora v2 storefront interactions.
 *
 * Header 2 sticky glassmorphism, dropdown/mobile drawer, floating [TOP],
 * Home 2 service-panel crossfade, Featured Suites slider, testimonial slider,
 * availability booking form builder (live QloApps search), localStorage
 * bookmarks (+ /bookmarks page), share links + copy toast, Report modal,
 * project gallery filter + lightbox, listing filter bar (grid/list/map,
 * price filter, sort), count-up counters, and QloApps AJAX cart protection.
 *
 * @author    Lush View Hotel
 * @copyright 2026 Lush View Hotel
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */
(function () {
    'use strict';

    var BOOKMARKS_KEY = 'lvhbk_bookmarks_v1';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    /* ------------------------------------------------------------------ */
    /* QloApps AJAX cart preservation (layer_cart must live on body)       */
    /* ------------------------------------------------------------------ */
    function preserveAjaxCartModal() {
        var layerCart = qs('#header #layer_cart');
        var layerOverlay = qs('#header .layer_cart_overlay');
        if (layerCart && layerCart.parentNode !== document.body) {
            document.body.appendChild(layerCart);
        }
        if (layerOverlay && layerOverlay.parentNode !== document.body) {
            document.body.appendChild(layerOverlay);
        }

        var qloQty = qs('.shopping_cart .ajax_cart_quantity');
        var badge = qs('#bk-cart-count');
        if (qloQty && badge) {
            var syncQty = function () {
                badge.textContent = (qloQty.textContent || '0').replace(/\D/g, '') || '0';
            };
            syncQty();
            if (window.MutationObserver) {
                new MutationObserver(syncQty).observe(qloQty, { childList: true, characterData: true, subtree: true });
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Header 2: sticky glass nav + mobile drawer                          */
    /* ------------------------------------------------------------------ */
    function initStickyHeader() {
        var nav = qs('#bk-navbar');
        if (!nav) {
            return;
        }
        var updateSticky = function () {
            nav.classList.toggle('is-fixed', (window.pageYOffset || document.documentElement.scrollTop) > 90);
        };
        updateSticky();
        window.addEventListener('scroll', updateSticky, { passive: true });
    }

    function initMobileDrawer() {
        var btn = qs('#bk-mobile-toggle');
        var drawer = qs('#bk-mobile-drawer');
        if (!btn || !drawer) {
            return;
        }
        var openDrawer = function () {
            drawer.classList.add('is-open');
            drawer.setAttribute('aria-hidden', 'false');
            btn.setAttribute('aria-expanded', 'true');
        };
        var closeDrawer = function () {
            drawer.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            btn.setAttribute('aria-expanded', 'false');
        };
        btn.addEventListener('click', openDrawer);
        qsa('[data-bk-close-drawer]', drawer).forEach(function (el) {
            el.addEventListener('click', closeDrawer);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDrawer();
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Floating [TOP] button                                              */
    /* ------------------------------------------------------------------ */
    function initTopButton() {
        var btn = qs('#bk-top-btn');
        if (!btn) {
            return;
        }
        var update = function () {
            btn.classList.toggle('is-visible', (window.pageYOffset || document.documentElement.scrollTop) > 420);
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Availability booking form -> live QloApps search URL                */
    /* ------------------------------------------------------------------ */
    function initAvailabilityForm() {
        var form = qs('#bk-availability-form');
        if (!form) {
            return;
        }
        var inInput = qs('#bk-checkin', form);
        var outInput = qs('#bk-checkout', form);

        var addDaysIso = function (isoStr, days) {
            var d = new Date(isoStr + 'T00:00:00');
            if (isNaN(d.getTime())) {
                d = new Date();
            }
            d.setDate(d.getDate() + days);
            return d.toISOString().slice(0, 10);
        };

        if (inInput && outInput) {
            inInput.addEventListener('change', function () {
                var min = addDaysIso(inInput.value, 1);
                outInput.setAttribute('min', min);
                if (!outInput.value || outInput.value <= inInput.value) {
                    outInput.value = min;
                }
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var searchUrl = (typeof window.lvhBookoraSearchUrl === 'string' && window.lvhBookoraSearchUrl)
                ? window.lvhBookoraSearchUrl
                : form.getAttribute('action');

            var params = [];
            var dFrom = inInput ? inInput.value : '';
            var dTo = outInput ? outInput.value : '';
            if (dFrom) {
                params.push('date_from=' + encodeURIComponent(dFrom));
            }
            if (dTo) {
                params.push('date_to=' + encodeURIComponent(dTo));
            }

            var guestsSel = qs('#bk-guests', form);
            var roomsSel = qs('#bk-rooms-count', form);
            var guests = guestsSel ? parseInt(guestsSel.value, 10) || 2 : 2;
            var rooms = roomsSel ? parseInt(roomsSel.value, 10) || 1 : 1;

            if (window.lvhBookoraUseOccupancy) {
                for (var i = 0; i < rooms; i++) {
                    params.push('occupancy[' + i + '][adults]=' + guests);
                    params.push('occupancy[' + i + '][children]=0');
                }
            } else {
                params.push('date_from=' + encodeURIComponent(dFrom));
            }

            var sep = (searchUrl.indexOf('?') !== -1) ? '&' : '?';
            window.location.href = searchUrl + sep + params.join('&');
        });
    }

    /* ------------------------------------------------------------------ */
    /* Home 2: Customer Services interactive crossfade panels             */
    /* ------------------------------------------------------------------ */
    function initServicePanels() {
        var root = qs('[data-bk-services]');
        if (!root) {
            return;
        }
        var items = qsa('[data-bk-service-item]', root);
        var bgs = qsa('[data-bk-service-bg]', root);
        if (!items.length || !bgs.length) {
            return;
        }
        var activate = function (idx) {
            items.forEach(function (it, i) {
                it.classList.toggle('is-active', i === idx);
            });
            bgs.forEach(function (bg, i) {
                bg.classList.toggle('is-active', i === idx);
            });
        };
        items.forEach(function (item, idx) {
            item.addEventListener('mouseenter', function () {
                activate(idx);
            });
            item.addEventListener('click', function (e) {
                if (e.target.closest('a')) {
                    return;
                }
                activate(idx);
            });
            item.addEventListener('focus', function () {
                activate(idx);
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Featured Suites slider (Home 2 / About / Home 3)                    */
    /* ------------------------------------------------------------------ */
    function initFeaturedSlider() {
        qsa('[data-bk-featured-slider]').forEach(function (root) {
            var slides = qsa('[data-bk-featured-slide]', root);
            if (slides.length <= 1) {
                return;
            }
            var prevBtn = qs('[data-bk-featured-prev]', root);
            var nextBtn = qs('[data-bk-featured-next]', root);
            var curEl = qs('[data-bk-featured-current]', root);
            var index = 0;
            var timer = null;

            var show = function (nextIdx) {
                index = (nextIdx + slides.length) % slides.length;
                slides.forEach(function (s, i) {
                    s.classList.toggle('is-active', i === index);
                });
                if (curEl) {
                    curEl.textContent = (index + 1 < 10 ? '0' : '') + (index + 1);
                }
            };
            var startAuto = function () {
                stopAuto();
                timer = setInterval(function () {
                    show(index + 1);
                }, 7500);
            };
            var stopAuto = function () {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            };

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    show(index - 1);
                    startAuto();
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    show(index + 1);
                    startAuto();
                });
            }
            root.addEventListener('mouseenter', stopAuto);
            root.addEventListener('mouseleave', startAuto);
            startAuto();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Testimonial slider                                                  */
    /* ------------------------------------------------------------------ */
    function initTestimonialsSlider() {
        qsa('[data-bk-testi-slider]').forEach(function (box) {
            var slides = qsa('[data-bk-testi-slide]', box);
            if (slides.length <= 1) {
                return;
            }
            var prevBtn = qs('[data-bk-testi-prev]', box);
            var nextBtn = qs('[data-bk-testi-next]', box);
            var index = 0;
            var timer = null;

            var show = function (nextIdx) {
                index = (nextIdx + slides.length) % slides.length;
                slides.forEach(function (s, i) {
                    s.classList.toggle('is-active', i === index);
                });
            };
            var startAuto = function () {
                stopAuto();
                timer = setInterval(function () {
                    show(index + 1);
                }, 7000);
            };
            var stopAuto = function () {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            };

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    show(index - 1);
                    startAuto();
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    show(index + 1);
                    startAuto();
                });
            }
            box.addEventListener('mouseenter', stopAuto);
            box.addEventListener('mouseleave', startAuto);
            startAuto();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Bookmarks (localStorage) + header badge + /bookmarks page          */
    /* ------------------------------------------------------------------ */
    function readBookmarks() {
        try {
            var raw = window.localStorage.getItem(BOOKMARKS_KEY);
            var arr = raw ? JSON.parse(raw) : [];
            return Array.isArray(arr) ? arr : [];
        } catch (e) {
            return [];
        }
    }

    function writeBookmarks(list) {
        try {
            window.localStorage.setItem(BOOKMARKS_KEY, JSON.stringify(list));
        } catch (e) {
            /* storage unavailable — silently ignore */
        }
    }

    function updateBookmarkUI() {
        var list = readBookmarks();
        var badge = qs('#bk-bookmark-count');
        if (badge) {
            badge.textContent = String(list.length);
        }
        qsa('[data-bk-bookmark]').forEach(function (btn) {
            var id = String(btn.getAttribute('data-id') || '');
            var saved = list.some(function (it) {
                return String(it.id) === id && id !== '';
            });
            btn.classList.toggle('is-saved', saved);
        });
    }

    function initBookmarks() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-bk-bookmark]') : null;
            if (!btn) {
                return;
            }
            e.preventDefault();
            var id = String(btn.getAttribute('data-id') || '');
            if (!id) {
                return;
            }
            var list = readBookmarks();
            var exists = list.some(function (it) {
                return String(it.id) === id;
            });
            if (exists) {
                list = list.filter(function (it) {
                    return String(it.id) !== id;
                });
            } else {
                list.push({
                    id: id,
                    name: btn.getAttribute('data-name') || 'Room',
                    url: btn.getAttribute('data-url') || '#',
                    price: btn.getAttribute('data-price') || '',
                    currency: btn.getAttribute('data-currency') || '',
                    image: btn.getAttribute('data-image') || ''
                });
            }
            writeBookmarks(list);
            updateBookmarkUI();
            renderBookmarksPage();
        });

        updateBookmarkUI();
        renderBookmarksPage();
    }

    function renderBookmarksPage() {
        var grid = qs('#bk-bookmarks-grid');
        if (!grid) {
            return;
        }
        var list = readBookmarks();
        var seed = (typeof window.lvhBookoraBookmarksSeed === 'object' && window.lvhBookoraBookmarksSeed)
            ? window.lvhBookoraBookmarksSeed
            : [];
        var seedById = {};
        seed.forEach(function (s) {
            seedById[String(s.id)] = s;
        });

        grid.innerHTML = '';
        list.forEach(function (item) {
            var s = seedById[String(item.id)] || {};
            var name = item.name || s.name || 'Room';
            var url = item.url || s.url || '#';
            var price = item.price || s.price || '';
            var currency = item.currency || s.currency || '';
            var image = item.image || s.image || '';

            var card = document.createElement('article');
            card.className = 'bk2-room-card';
            card.innerHTML =
                '<div class="bk2-room-card__media" style="background-image: url(\'' + String(image).replace(/'/g, '%27') + '\');">' +
                '  <span class="bk2-room-card__price">' + currency + price + ' <small>/Night</small></span>' +
                '  <button type="button" class="bk2-room-card__bookmark is-saved" data-bk-bookmark' +
                '    data-id="' + String(item.id).replace(/"/g, '&quot;') + '"' +
                '    data-name="' + String(name).replace(/"/g, '&quot;') + '"' +
                '    data-url="' + String(url).replace(/"/g, '&quot;') + '"' +
                '    data-price="' + String(price).replace(/"/g, '&quot;') + '"' +
                '    data-currency="' + String(currency).replace(/"/g, '&quot;') + '"' +
                '    data-image="' + String(image).replace(/"/g, '&quot;') + '" aria-label="Remove bookmark">' +
                '    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>' +
                '  </button>' +
                '</div>' +
                '<div class="bk2-room-card__body">' +
                '  <h3 class="bk2-room-card__title">' + name + '</h3>' +
                '  <a href="' + String(url).replace(/"/g, '&quot;') + '" class="bk2-room-card__link">Discover More</a>' +
                '</div>';
            grid.appendChild(card);
        });

        var empty = qs('#bk-bookmarks-empty');
        if (empty) {
            empty.hidden = list.length > 0;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Share links + copy toast                                            */
    /* ------------------------------------------------------------------ */
    function initShare() {
        var toast = qs('#bk-share-toast');
        var showToast = function (msg) {
            if (!toast) {
                return;
            }
            if (msg) {
                toast.textContent = msg;
            }
            toast.classList.add('is-visible');
            setTimeout(function () {
                toast.classList.remove('is-visible');
            }, 2200);
        };

        var pageUrl = encodeURIComponent(window.location.href);
        qsa('[data-bk-share]').forEach(function (el) {
            var kind = el.getAttribute('data-bk-share');
            if (kind === 'copy') {
                el.addEventListener('click', function () {
                    var url = window.location.href;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(function () {
                            showToast('Link copied to clipboard');
                        }, function () {
                            showToast(url);
                        });
                    } else {
                        window.prompt('Copy this link:', url);
                    }
                });
            } else if (el.tagName === 'A') {
                var href = el.getAttribute('href') || '';
                el.setAttribute('href', href + pageUrl);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Report / Inquiry modal                                              */
    /* ------------------------------------------------------------------ */
    function initReportModal() {
        var modal = qs('#bk-report-modal');
        if (!modal) {
            return;
        }
        var open = function () {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        };
        var close = function () {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        };
        qsa('[data-bk-open-report]').forEach(function (btn) {
            btn.addEventListener('click', open);
        });
        qsa('[data-bk-close-modal]', modal).forEach(function (btn) {
            btn.addEventListener('click', close);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                close();
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Project gallery: category filter + lightbox                         */
    /* ------------------------------------------------------------------ */
    function initGallery() {
        var filters = qsa('[data-bk-gallery-filter]');
        var items = qsa('.bk-gallery-item');
        if (filters.length && items.length) {
            filters.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var val = btn.getAttribute('data-bk-gallery-filter');
                    filters.forEach(function (b) {
                        b.classList.toggle('is-active', b === btn);
                    });
                    items.forEach(function (item) {
                        var cat = item.getAttribute('data-category') || '';
                        item.classList.toggle('is-hidden', !(val === '*' || cat === val));
                    });
                });
            });
        }

        var lb = qs('#bk-lightbox');
        if (!lb || !items.length) {
            return;
        }
        var imgEl = qs('img', lb);
        var capEl = qs('figcaption', lb);
        var current = 0;

        var visibleItems = function () {
            return items.filter(function (it) {
                return !it.classList.contains('is-hidden');
            });
        };
        var openAt = function (item) {
            var img = qs('img', item);
            var cap = qs('figcaption strong', item);
            if (!imgEl || !img) {
                return;
            }
            imgEl.src = img.getAttribute('src') || '';
            imgEl.alt = img.getAttribute('alt') || '';
            if (capEl) {
                capEl.textContent = cap ? cap.textContent : '';
            }
            lb.classList.add('is-open');
            lb.setAttribute('aria-hidden', 'false');
            var vis = visibleItems();
            current = Math.max(0, vis.indexOf(item));
        };
        var close = function () {
            lb.classList.remove('is-open');
            lb.setAttribute('aria-hidden', 'true');
        };
        var step = function (dir) {
            var vis = visibleItems();
            if (!vis.length) {
                return;
            }
            current = (current + dir + vis.length) % vis.length;
            openAt(vis[current]);
        };

        items.forEach(function (item) {
            item.addEventListener('click', function () {
                openAt(item);
            });
        });
        qsa('[data-bk-close-lightbox]', lb).forEach(function (btn) {
            btn.addEventListener('click', close);
        });
        var prev = qs('[data-bk-lightbox-prev]', lb);
        var next = qs('[data-bk-lightbox-next]', lb);
        if (prev) {
            prev.addEventListener('click', function () {
                step(-1);
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                step(1);
            });
        }
        document.addEventListener('keydown', function (e) {
            if (!lb.classList.contains('is-open')) {
                return;
            }
            if (e.key === 'Escape') {
                close();
            } else if (e.key === 'ArrowLeft') {
                step(-1);
            } else if (e.key === 'ArrowRight') {
                step(1);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Listings filter bar (category page): price filter, count, views,   */
    /* sort                                                               */
    /* ------------------------------------------------------------------ */
    function initListingFilters() {
        var root = qs('[data-bk-filters]');
        if (!root) {
            return;
        }

        // Locate the live QloApps room list
        var list = qs('#center_column .product_list') || qs('.product_list') || qs('#center_column');
        var cards = list ? qsa('li.product-miniature, .product-miniature, .ajax_block_product', list) : [];

        var countEl = qs('[data-bk-items-found]', root);
        var minInput = qs('[data-bk-price-min]', root);
        var maxInput = qs('[data-bk-price-max]', root);

        var cardPrice = function (card) {
            if (card.hasAttribute('data-bk-price')) {
                return parseFloat(card.getAttribute('data-bk-price')) || 0;
            }
            var txt = card.textContent || '';
            var m = txt.replace(/\s/g, '').match(/([0-9]+(?:[.,][0-9]{1,2})?)/g);
            if (m && m.length) {
                return parseFloat(m[m.length - 1].replace(',', '')) || 0;
            }
            return 0;
        };

        var applyPriceFilter = function () {
            if (!cards.length) {
                return;
            }
            var min = minInput && minInput.value !== '' ? parseFloat(minInput.value) : null;
            var max = maxInput && maxInput.value !== '' ? parseFloat(maxInput.value) : null;
            var shown = 0;
            cards.forEach(function (card) {
                var p = cardPrice(card);
                var ok = (min === null || p >= min) && (max === null || p <= max);
                card.style.display = ok ? '' : 'none';
                if (ok) {
                    shown++;
                }
            });
            if (countEl) {
                countEl.textContent = String(shown);
            }
        };

        if (minInput) {
            minInput.addEventListener('input', applyPriceFilter);
        }
        if (maxInput) {
            maxInput.addEventListener('input', applyPriceFilter);
        }
        if (countEl) {
            countEl.textContent = String(cards.length);
        }

        // Grid / List / Map view switcher
        var viewBtns = qsa('[data-bk-view]', root);
        var mapPanel = qs('[data-bk-map-panel]', root);
        viewBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var view = btn.getAttribute('data-bk-view');
                viewBtns.forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                if (mapPanel) {
                    mapPanel.hidden = (view !== 'map');
                }
                document.body.classList.toggle('bk-listview', view === 'list');
                if (list) {
                    list.classList.toggle('bk-listview', view === 'list');
                }
            });
        });

        // Sort By -> QloApps category controller params
        var sortSel = qs('[data-bk-sort]', root);
        if (sortSel) {
            sortSel.addEventListener('change', function () {
                var val = sortSel.value;
                if (!val) {
                    return;
                }
                var parts = val.split('-');
                var orderby = parts[0];
                var orderway = parts[1] || 'asc';
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set('orderby', orderby);
                    url.searchParams.set('orderway', orderway);
                    window.location.href = url.toString();
                } catch (e) {
                    window.location.search = '&orderby=' + orderby + '&orderway=' + orderway;
                }
            });
        }
    }

    /* ------------------------------------------------------------------ */
    /* Count-up stat counters                                              */
    /* ------------------------------------------------------------------ */
    function initCounters() {
        var els = qsa('[data-bk-countup]');
        if (!els.length || !window.IntersectionObserver) {
            return;
        }
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                var el = entry.target;
                obs.unobserve(el);
                var target = parseInt(el.getAttribute('data-bk-countup'), 10);
                if (isNaN(target) || target <= 0) {
                    return;
                }
                var suffix = (el.textContent || '').replace(/[0-9]/g, '');
                var start = null;
                var dur = 1400;
                var tick = function (ts) {
                    if (!start) {
                        start = ts;
                    }
                    var p = Math.min(1, (ts - start) / dur);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(target * eased) + suffix;
                    if (p < 1) {
                        window.requestAnimationFrame(tick);
                    }
                };
                window.requestAnimationFrame(tick);
            });
        }, { threshold: 0.4 });
        els.forEach(function (el) {
            obs.observe(el);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Newsletter: keep the return URL current so redirects land nicely    */
    /* ------------------------------------------------------------------ */
    function initNewsletterReturn() {
        qsa('.bk-newsletter-return').forEach(function (input) {
            try {
                input.value = window.location.href;
            } catch (e) {
                /* keep fallback */
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Boot                                                                */
    /* ------------------------------------------------------------------ */
    onReady(function () {
        preserveAjaxCartModal();
        initStickyHeader();
        initMobileDrawer();
        initTopButton();
        initAvailabilityForm();
        initServicePanels();
        initFeaturedSlider();
        initTestimonialsSlider();
        initBookmarks();
        initShare();
        initReportModal();
        initGallery();
        initListingFilters();
        initCounters();
        initNewsletterReturn();
    });
})();
