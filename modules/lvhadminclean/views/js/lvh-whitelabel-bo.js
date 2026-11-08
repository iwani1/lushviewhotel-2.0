/**
 * Lush View Hotel - Back-Office Ad Blocker & Complete White-Label Engine
 *
 * Blocks all QloApps/Webkul ad banners and phone-home requests, removes Addons /
 * Update / Help Center clutter, rebrands Header, Footer & Login screen, and
 * replaces all visible "QloApps" / "Webkul" text with "Lush View Hotel".
 */
(function () {
    'use strict';

    var cfg = window.LVH_WHITELABEL_CFG || {
        brand: 'Lush View Hotel',
        blockAds: true,
        hideHelpAndUpdate: true,
        hideDemoBtn: false,
        replaceText: true,
        customLogoUrl: ''
    };
    var BRAND = cfg.brand || 'Lush View Hotel';

    // 1. Kill recommendation banner loaders immediately
    if (cfg.blockAds) {
        window.loadRecommendation = function () {};
        window.closeRecommendation = function () {};
    }

    function hookJQueryGuards() {
        if (!window.jQuery) {
            setTimeout(hookJQueryGuards, 15);
            return;
        }
        if (cfg.blockAds && window.jQuery.ajaxPrefilter) {
            window.jQuery.ajaxPrefilter(function (options, original, jqXHR) {
                var data = original && original.data;
                var action = '';
                if (data && typeof data === 'object') {
                    action = String(data.action || '');
                } else if (typeof data === 'string') {
                    var m = data.match(/(?:^|&)action=([^&]+)/);
                    action = m ? decodeURIComponent(m[1]) : data;
                }
                if (
                    action === 'getRecommendationContent' ||
                    action === 'recommendationClosed' ||
                    action === 'checkUpgrade'
                ) {
                    jqXHR.abort();
                }
            });
        }
    }
    hookJQueryGuards();

    function scrubString(str) {
        if (!str || typeof str !== 'string') {
            return str;
        }
        if (!/qloapps|webkul|lushviewhotel\.net/i.test(str)) {
            return str;
        }
        var out = str
            // Strip parenthetical "(QloApps™)" first so titles stay clean
            .replace(/\s*\(\s*QloApps\s*(?:\u2122|&trade;|TM)?\s*\)/gi, '')
            .replace(/\bQloApps\s*(?:\u2122|&trade;)?/gi, BRAND)
            .replace(/\bWebkul\s*(?:\u2122|&trade;)?/gi, BRAND)
            .replace(/\blushviewhotel\.net\b/gi, BRAND);

        // Collapse accidental double brand names (e.g. "Lush View Hotel • Lush View Hotel")
        var dupRe = new RegExp(escapeRegExp(BRAND) + '\\s+' + escapeRegExp(BRAND), 'gi');
        out = out.replace(dupRe, BRAND);
        return out;
    }

    function escapeRegExp(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function cleanTitle() {
        if (!cfg.replaceText) {
            return;
        }
        var next = scrubString(document.title || '');
        if (next && next !== document.title) {
            document.title = next;
        }
    }

    var SKIP_TAGS = {
        SCRIPT: 1,
        STYLE: 1,
        NOSCRIPT: 1,
        TEXTAREA: 1,
        INPUT: 1,
        SELECT: 1,
        OPTION: 1,
        CODE: 1,
        PRE: 1
    };

    var ATTRS = ['title', 'alt', 'data-title', 'data-original-title', 'placeholder'];

    function scrubSubtree(root) {
        if (!cfg.replaceText || !root) {
            return;
        }
        if (root.nodeType === 3) {
            var p = root.parentNode;
            if (p && !SKIP_TAGS[p.nodeName]) {
                var replaced = scrubString(root.nodeValue);
                if (replaced !== root.nodeValue) {
                    root.nodeValue = replaced;
                }
            }
            return;
        }
        if (root.nodeType !== 1 || SKIP_TAGS[root.nodeName]) {
            return;
        }

        // Scrub tooltip/alt/placeholder attributes without touching href/src/value
        var els = [root];
        if (root.querySelectorAll) {
            var found = root.querySelectorAll('[title],[alt],[data-title],[data-original-title],[placeholder]');
            for (var k = 0; k < found.length; k++) {
                els.push(found[k]);
            }
        }
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (SKIP_TAGS[el.nodeName]) {
                continue;
            }
            for (var a = 0; a < ATTRS.length; a++) {
                var attr = ATTRS[a];
                if (el.hasAttribute && el.hasAttribute(attr)) {
                    var v = el.getAttribute(attr);
                    var nv = scrubString(v);
                    if (nv !== v) {
                        el.setAttribute(attr, nv);
                    }
                }
            }
        }

        if (document.createTreeWalker) {
            var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
            var node;
            while ((node = walker.nextNode())) {
                var parent = node.parentNode;
                if (parent && !SKIP_TAGS[parent.nodeName]) {
                    var nextVal = scrubString(node.nodeValue);
                    if (nextVal !== node.nodeValue) {
                        node.nodeValue = nextVal;
                    }
                }
            }
        }
    }

    function buildCrestDataUri() {
        var svg =
            '<svg xmlns="http://www.w3.org/2000/svg" width="136" height="136" viewBox="0 0 136 136">' +
            '<circle cx="68" cy="68" r="64" fill="#14532d" stroke="#eab308" stroke-width="4"/>' +
            '<text x="68" y="77" text-anchor="middle" font-family="Arial,sans-serif" font-size="34" font-weight="bold" fill="#fef08a">LVH</text>' +
            '</svg>';
        return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
    }

    function applyStructuralCleanups() {
        cleanTitle();

        // Top-left header brand name
        var shopLink = document.getElementById('header_shopname');
        if (shopLink && cfg.replaceText) {
            shopLink.textContent = BRAND;
        }

        // Remove empty <li> wrappers for "Explore QloApps Addons" & "Recommendations"
        if (cfg.blockAds) {
            var promoLinks = document.querySelectorAll(
                '#header_employee_box a[href*="qloapps.com"], ' +
                '#header_employee_box a[href*="webkul.com"], ' +
                'a[id^="page-header-desc-"][id$="-modules-list"]'
            );
            for (var i = 0; i < promoLinks.length; i++) {
                var li = promoLinks[i].closest ? promoLinks[i].closest('li') : promoLinks[i].parentNode;
                if (li && li.tagName === 'LI') {
                    li.style.display = 'none';
                } else {
                    promoLinks[i].style.display = 'none';
                }
            }

            var recWrap = document.getElementById('recommendation-wrapper');
            if (recWrap) {
                recWrap.innerHTML = '';
                recWrap.style.display = 'none';
            }
        }

        if (cfg.hideDemoBtn) {
            var demoBtn = document.getElementById('page-header-desc-configuration-switch_demo');
            if (demoBtn) {
                var demoLi = demoBtn.closest ? demoBtn.closest('li') : demoBtn.parentNode;
                if (demoLi && demoLi.tagName === 'LI') {
                    demoLi.style.display = 'none';
                } else {
                    demoBtn.style.display = 'none';
                }
            }
        }

        // Dashboard right column: hide QLOAPPS UPDATE + HELP CENTER & widen main stats area
        if (cfg.hideHelpAndUpdate) {
            var zoneThree = document.getElementById('hookDashboardZoneThree');
            if (zoneThree) {
                var panels = zoneThree.querySelectorAll('section.panel, section.widget, div.panel');
                var visibleCount = 0;
                for (var p = 0; p < panels.length; p++) {
                    var pnl = panels[p];
                    var txt = (pnl.textContent || '').toLowerCase();
                    if (
                        pnl.classList.contains('dash_links') ||
                        txt.indexOf('qloapps update') !== -1 ||
                        txt.indexOf('latest version available') !== -1 ||
                        txt.indexOf('help center') !== -1 ||
                        txt.indexOf('google hotel') !== -1
                    ) {
                        var colWrap = pnl.parentNode;
                        if (colWrap && colWrap.className && colWrap.className.indexOf('col-') !== -1) {
                            colWrap.style.display = 'none';
                        } else {
                            pnl.style.display = 'none';
                        }
                    } else if (pnl.offsetParent !== null) {
                        visibleCount++;
                    }
                }
                if (visibleCount === 0) {
                    zoneThree.style.display = 'none';
                    var zoneTwo = document.getElementById('hookDashboardZoneTwo');
                    if (zoneTwo) {
                        zoneTwo.classList.remove('col-lg-7');
                        zoneTwo.classList.add('col-lg-9');
                    }
                }
            }
        }

        // Back-Office Footer rebranding
        var footer = document.getElementById('footer');
        if (footer && cfg.replaceText && !footer.getAttribute('data-lvh-cleaned')) {
            footer.setAttribute('data-lvh-cleaned', '1');
            var firstCol = footer.querySelector('.col-sm-2');
            if (firstCol) {
                var loadSpan = document.getElementById('footer-load-time');
                var loadHtml = loadSpan ? ' &bull; ' + loadSpan.outerHTML : '';
                firstCol.className = 'col-sm-6 hidden-xs';
                firstCol.innerHTML = '<strong>&copy; ' + new Date().getFullYear() + ' ' + BRAND + '</strong>' + loadHtml;
            }
        }

        // Back-Office Login Screen rebranding
        var loginPanel = document.getElementById('login-panel');
        if (loginPanel && cfg.replaceText) {
            var loginHeader = document.getElementById('login-header');
            if (loginHeader && !loginHeader.querySelector('.lvh-login-brand-title')) {
                var h = document.createElement('div');
                h.className = 'lvh-login-brand-title';
                h.textContent = BRAND;
                loginHeader.insertBefore(h, loginHeader.firstChild);
            }
            var loginShopName = document.getElementById('shop_name');
            if (loginShopName) {
                loginShopName.textContent = BRAND + ' — Staff Portal';
            }
            var loginAvatar = document.querySelector('#shop-img img');
            if (loginAvatar && !loginAvatar.getAttribute('data-lvh-logo')) {
                loginAvatar.setAttribute('data-lvh-logo', '1');
                loginAvatar.src = cfg.customLogoUrl ? cfg.customLogoUrl : buildCrestDataUri();
                loginAvatar.alt = BRAND;
                loginAvatar.style.objectFit = 'contain';
                loginAvatar.style.borderRadius = '50%';
                loginAvatar.style.background = '#ffffff';
                loginAvatar.style.padding = '6px';
            }
            var loginFooter = document.getElementById('login-footer');
            if (loginFooter && !loginFooter.getAttribute('data-lvh-cleaned')) {
                loginFooter.setAttribute('data-lvh-cleaned', '1');
                loginFooter.innerHTML =
                    '<p class="text-center text-muted">&copy; ' +
                    new Date().getFullYear() +
                    ' ' +
                    BRAND +
                    '. All rights reserved.</p>';
            }
        }

        scrubSubtree(document.body);
    }

    function init() {
        applyStructuralCleanups();

        if (window.MutationObserver && document.body) {
            var observer = new MutationObserver(function (mutations) {
                cleanTitle();
                for (var i = 0; i < mutations.length; i++) {
                    var added = mutations[i].addedNodes;
                    for (var j = 0; j < added.length; j++) {
                        var nd = added[j];
                        if (nd.nodeType === 1 || nd.nodeType === 3) {
                            scrubSubtree(nd);
                        }
                    }
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    cleanTitle();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
