/**
 * Lush View Hotel - Front-Office Guest Website White-Label & Copyright Cleaner
 *
 * Removes "| Powered by QloApps" from the guest website footer on both QloApps 1.7
 * and 1.8 and replaces any residual "QloApps" or "Hotel Prime" visible text with
 * "Lush View Hotel".
 */
(function () {
    'use strict';

    var BRAND = window.LVH_FO_BRAND || 'Lush View Hotel';

    function cleanFrontOffice() {
        // 1. Clean footer copyright ("All rights reserved. | Powered by QloApps")
        var copyNodes = document.querySelectorAll('.copyRightWrapper .copyRight, .copyRight');
        for (var i = 0; i < copyNodes.length; i++) {
            var p = copyNodes[i];
            if (/qloapps/i.test(p.innerHTML)) {
                p.innerHTML = '&copy; ' + new Date().getFullYear() + ' <a href="/">' + BRAND + '</a>. All rights reserved.';
            }
        }

        // 2. Remove generator meta tag if present
        var gen = document.querySelector('meta[name="generator"]');
        if (gen && /qloapps/i.test(gen.getAttribute('content') || '')) {
            gen.parentNode.removeChild(gen);
        }

        // 3. Clean document title
        if (document.title && /qloapps|hotel prime|lushviewhotel\.net/i.test(document.title)) {
            document.title = document.title
                .replace(/\bQloApps\s*(?:\u2122)?/gi, BRAND)
                .replace(/\bHotel Prime\b/gi, BRAND)
                .replace(/\blushviewhotel\.net\b/gi, BRAND);
        }

        // 4. Clean visible text nodes containing QloApps or Hotel Prime
        if (document.body && document.createTreeWalker) {
            var skip = { SCRIPT: 1, STYLE: 1, INPUT: 1, TEXTAREA: 1, SELECT: 1, CODE: 1 };
            var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
            var n;
            while ((n = walker.nextNode())) {
                if (n.parentNode && !skip[n.parentNode.nodeName] && /qloapps|hotel prime/i.test(n.nodeValue)) {
                    n.nodeValue = n.nodeValue
                        .replace(/\|\s*Powered by\s*QloApps/gi, '')
                        .replace(/\bQloApps\s*(?:\u2122)?/gi, BRAND)
                        .replace(/\bHotel Prime\b/gi, BRAND);
                }
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', cleanFrontOffice);
    } else {
        cleanFrontOffice();
    }
})();
