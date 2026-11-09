/**
 * Lush View Hotel — Bookora Storefront Interactive Controller (QloApps 1.8.0)
 *
 * Semicolon-disciplined ES5 for safe JShrink minification under QloApps CCC.
 */
(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        preserveAjaxCartModal();
        initStickyHeader();
        initMobileDrawer();
        initAvailabilityForm();
        initRoomsSlider();
        initOffersSwitcher();
        initTestimonialsSlider();
        initLightbox();
    });

    /**
     * Move QloApps #layer_cart out of #header into <body> so AJAX "Book Now"
     * popups render cleanly while legacy #header children are hidden, and keep
     * the Bookora cart counter synced with QloApps ajaxCart.
     */
    function preserveAjaxCartModal() {
        var layerCart = document.querySelector('#header #layer_cart');
        var layerOverlay = document.querySelector('#header .layer_cart_overlay');
        if (layerOverlay && layerOverlay.parentNode !== document.body) {
            document.body.appendChild(layerOverlay);
        }
        if (layerCart && layerCart.parentNode !== document.body) {
            document.body.appendChild(layerCart);
        }

        var badge = document.getElementById('bk-cart-count');
        var qloQty = document.querySelector('.shopping_cart .ajax_cart_quantity');
        if (!badge || !qloQty) {
            return;
        }

        var syncQty = function () {
            var raw = parseInt((qloQty.textContent || '0').trim(), 10);
            badge.textContent = isNaN(raw) ? '0' : String(raw);
        };

        if (typeof MutationObserver !== 'undefined') {
            var obs = new MutationObserver(syncQty);
            obs.observe(qloQty, { childList: true, characterData: true, subtree: true });
        }
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(document).ajaxComplete(syncQty);
        }
    }

    /**
     * Sticky Bookora navigation bar past the 42px top utility bar.
     */
    function initStickyHeader() {
        var shell = document.getElementById('bk-header-shell');
        var navbar = document.getElementById('bk-navbar');
        if (!shell || !navbar) {
            return;
        }

        var updateSticky = function () {
            var threshold = 42;
            if (window.pageYOffset > threshold) {
                if (!navbar.classList.contains('is-fixed')) {
                    shell.style.paddingBottom = navbar.offsetHeight + 'px';
                    navbar.classList.add('is-fixed');
                }
            } else if (navbar.classList.contains('is-fixed')) {
                navbar.classList.remove('is-fixed');
                shell.style.paddingBottom = '0px';
            }
        };

        window.addEventListener('scroll', updateSticky, { passive: true });
        updateSticky();
    }

    /**
     * Mobile navigation drawer open/close handlers.
     */
    function initMobileDrawer() {
        var btn = document.getElementById('bk-mobile-toggle');
        var drawer = document.getElementById('bk-mobile-drawer');
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

        var closers = drawer.querySelectorAll('[data-bk-close-drawer]');
        for (var i = 0; i < closers.length; i++) {
            closers[i].addEventListener('click', closeDrawer);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
                closeDrawer();
            }
        });
    }

    /**
     * Availability search pill: enforces check_out > check_in and redirects
     * to QloApps 1.8.0's exact room-search URL with occupancy parameters.
     */
    function initAvailabilityForm() {
        var form = document.getElementById('bk-availability-form');
        var inInput = document.getElementById('bk-checkin');
        var outInput = document.getElementById('bk-checkout');
        var guestsSel = document.getElementById('bk-guests');

        if (!form || !inInput || !outInput) {
            return;
        }

        var addDaysIso = function (isoStr, days) {
            var parts = isoStr.split('-');
            if (parts.length !== 3) {
                return isoStr;
            }
            var dt = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            dt.setDate(dt.getDate() + days);
            var y = dt.getFullYear();
            var m = String(dt.getMonth() + 1);
            if (m.length < 2) {
                m = '0' + m;
            }
            var d = String(dt.getDate());
            if (d.length < 2) {
                d = '0' + d;
            }
            return y + '-' + m + '-' + d;
        };

        inInput.addEventListener('change', function () {
            if (!inInput.value) {
                return;
            }
            var minOut = addDaysIso(inInput.value, 1);
            outInput.min = minOut;
            if (!outInput.value || outInput.value <= inInput.value) {
                outInput.value = minOut;
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var baseUrl = (typeof window.lvhBookoraSearchUrl === 'string' && window.lvhBookoraSearchUrl)
                ? window.lvhBookoraSearchUrl
                : form.getAttribute('action');

            var dateFrom = inInput.value;
            var dateTo = outInput.value;
            if (dateFrom && (!dateTo || dateTo <= dateFrom)) {
                dateTo = addDaysIso(dateFrom, 1);
            }

            var adults = 2;
            var children = 0;
            if (guestsSel && guestsSel.value) {
                var gp = guestsSel.value.split('-');
                adults = parseInt(gp[0], 10) || 2;
                children = parseInt(gp[1], 10) || 0;
            }

            var params = [
                'date_from=' + encodeURIComponent(dateFrom),
                'date_to=' + encodeURIComponent(dateTo)
            ];

            if (typeof window.lvhBookoraUseOccupancy === 'undefined' || window.lvhBookoraUseOccupancy) {
                params.push(encodeURIComponent('occupancy[0][adults]') + '=' + encodeURIComponent(String(adults)));
                params.push(encodeURIComponent('occupancy[0][children]') + '=' + encodeURIComponent(String(children)));
            }

            var sep = baseUrl.indexOf('?') !== -1 ? '&' : '?';
            window.location.href = baseUrl + sep + params.join('&');
        });
    }

    /**
     * Top Rooms & Suites horizontal carousel with counter & progress indicator.
     */
    function initRoomsSlider() {
        var root = document.querySelector('[data-bk-rooms-slider]');
        if (!root) {
            return;
        }
        var track = root.querySelector('[data-bk-rooms-track]');
        var cards = track ? track.querySelectorAll('.bk-room-card') : [];
        var prevBtn = root.querySelector('[data-bk-slide-prev]');
        var nextBtn = root.querySelector('[data-bk-slide-next]');
        var curEl = root.querySelector('[data-bk-slide-current]');
        var progEl = root.querySelector('[data-bk-slide-progress]');

        if (!track || !cards.length) {
            return;
        }

        var total = cards.length;

        var updateIndicator = function () {
            var cardWidth = cards[0].offsetWidth + 24;
            var idx = Math.round(track.scrollLeft / Math.max(1, cardWidth)) + 1;
            if (idx < 1) {
                idx = 1;
            }
            if (idx > total) {
                idx = total;
            }
            if (curEl) {
                curEl.textContent = idx < 10 ? '0' + idx : String(idx);
            }
            if (progEl) {
                var pct = Math.min(100, Math.max(20, Math.round((idx / total) * 100)));
                progEl.style.width = pct + '%';
            }
        };

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                var step = cards[0].offsetWidth + 24;
                track.scrollBy({ left: -step, behavior: 'smooth' });
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                var step = cards[0].offsetWidth + 24;
                track.scrollBy({ left: step, behavior: 'smooth' });
            });
        }

        track.addEventListener('scroll', updateIndicator, { passive: true });
        updateIndicator();
    }

    /**
     * Interactive Special Offers accordion + right-column preview photo swap.
     */
    function initOffersSwitcher() {
        var items = document.querySelectorAll('[data-bk-offer-item]');
        var preview = document.getElementById('bk-offer-preview-img');
        if (!items.length) {
            return;
        }

        var activate = function (target) {
            for (var i = 0; i < items.length; i++) {
                items[i].classList.toggle('is-active', items[i] === target);
            }
            var img = target.getAttribute('data-image');
            if (preview && img && preview.getAttribute('src') !== img) {
                preview.style.opacity = '0.35';
                setTimeout(function () {
                    preview.setAttribute('src', img);
                    preview.style.opacity = '1';
                }, 120);
            }
        };

        for (var i = 0; i < items.length; i++) {
            (function (item) {
                item.addEventListener('click', function () {
                    activate(item);
                });
                item.addEventListener('mouseenter', function () {
                    activate(item);
                });
            })(items[i]);
        }
    }

    /**
     * Guest Testimonials slider with prev/next buttons & gentle auto-advance.
     */
    function initTestimonialsSlider() {
        var box = document.querySelector('[data-bk-testi-slider]');
        if (!box) {
            return;
        }
        var slides = box.querySelectorAll('[data-bk-testi-slide]');
        if (slides.length <= 1) {
            return;
        }

        var prevBtn = box.querySelector('[data-bk-testi-prev]');
        var nextBtn = box.querySelector('[data-bk-testi-next]');
        var index = 0;
        var timer = null;

        var show = function (nextIdx) {
            index = (nextIdx + slides.length) % slides.length;
            for (var i = 0; i < slides.length; i++) {
                slides[i].classList.toggle('is-active', i === index);
            }
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
    }

    /**
     * Full-screen Lightbox for Hero tour button & Interior Gallery tiles.
     */
    function initLightbox() {
        var modal = document.getElementById('bk-lightbox');
        var imgEl = document.getElementById('bk-lightbox-img');
        var capEl = document.getElementById('bk-lightbox-caption');
        if (!modal || !imgEl) {
            return;
        }

        var triggers = document.querySelectorAll('[data-bk-lightbox]');
        var closers = modal.querySelectorAll('[data-bk-lightbox-close]');

        var openModal = function (src, caption) {
            if (!src) {
                return;
            }
            imgEl.setAttribute('src', src);
            if (capEl) {
                capEl.textContent = caption || '';
            }
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        };

        var closeModal = function () {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            imgEl.setAttribute('src', '');
        };

        for (var i = 0; i < triggers.length; i++) {
            (function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    openModal(el.getAttribute('data-bk-lightbox'), el.getAttribute('data-bk-caption'));
                });
            })(triggers[i]);
        }

        for (var j = 0; j < closers.length; j++) {
            closers[j].addEventListener('click', closeModal);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    }
})();
