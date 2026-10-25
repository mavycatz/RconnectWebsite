/* ============================================================
   Site-wide transitions - navigation interceptor, login hook,
   component entrance cleanup, KPI number counters, Chart.js
   animation defaults (admin + finance + login)
   ============================================================ */
(function () {
    'use strict';

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var EXIT_MS = reduced ? 0 : 300;
    var exiting = false;

    function isSameSite(href) {
        try {
            return new URL(href, window.location.href).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    /* ---------- exit transition for same-site link clicks ---------- */
    document.addEventListener('click', function (e) {
        if (exiting) { e.preventDefault(); return; }

        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || !a.href) return;
        if (e.defaultPrevented) return;
        if (e.button !== 0) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (a.target && a.target !== '_self') return;
        if (a.hasAttribute('download')) return;
        if (!isSameSite(a.href)) return;

        var u = new URL(a.href, window.location.href);
        if (u.pathname === window.location.pathname && u.search === window.location.search) return;

        e.preventDefault();

        /* Auth pages (no .main-content) get a themed spinner overlay during
           the exit so the page never blanks to black/empty. Admin/finance
           pages keep the existing content fade. Reduced-motion skips both. */
        var main = document.querySelector('.main-content');
        if (main) {
            main.classList.add('sit-page-exit');
        } else if (!reduced) {
            var loader = document.createElement('div');
            loader.className = 'auth-loader';
            loader.setAttribute('aria-hidden', 'true');
            var spinner = document.createElement('span');
            spinner.className = 'auth-spinner';
            loader.appendChild(spinner);
            document.body.appendChild(loader);
        }
        exiting = true;

        setTimeout(function () {
            window.location.href = a.href;
        }, EXIT_MS + 50);
    });

    /* ---------- login: themed submission state ----------
       Login submit loading is handled by authLoading() in auth-alert.js
       (inline spinner + disabled button). The old blank-fade of the card
       is intentionally gone. */

    /* ---------- component entrance cleanup ----------
       Entrance keyframes retain a translateY(0) transform via
       fill-mode. That would trap position:fixed elements (e.g. the
       dashboard filter button) inside the animated section as their
       containing block, so the finished animation is removed. */
    if (!reduced) {
        document.addEventListener('animationend', function (e) {
            var name = e.animationName;
            if (name !== 'sit-page-enter' && name !== 'sit-card-enter') return;
            var t = e.target;
            if (t && t.style) t.style.animation = 'none';
        });
    }

    /* ---------- Chart.js animation defaults ---------- */
    var chartDefaultsApplied = false;

    function applyChartDefaults() {
        if (chartDefaultsApplied) return true;
        if (typeof window.Chart === 'undefined') return false;
        chartDefaultsApplied = true;
        var C = window.Chart;
        try {
            if (reduced) {
                C.defaults.animation = false;
                return true;
            }
            if (C.defaults && typeof C.defaults.animation === 'object') {
                C.defaults.animation.duration = 900;
                C.defaults.animation.easing = 'easeOutQuart';
            }
            if (C.defaults && C.defaults.datasets) {
                if (C.defaults.datasets.line) {
                    C.defaults.datasets.line.animation = {
                        x: { type: 'number', easing: 'linear', duration: 1100, from: NaN,
                             delay: function (ctx) { return (ctx.dataIndex || 0) * 40; } },
                        y: { easing: 'easeOutQuart', duration: 900 }
                    };
                }
                if (C.defaults.datasets.bar) {
                    C.defaults.datasets.bar.animation = {
                        delay: function (ctx) { return (ctx.dataIndex || 0) * 50; }
                    };
                }
            }
        } catch (err) { /* non-fatal: charts still render with their defaults */ }
        return true;
    }
    if (!applyChartDefaults()) {
        var tries = 0;
        var chartWait = setInterval(function () {
            if (applyChartDefaults() || ++tries > 50) clearInterval(chartWait);
        }, 200);
    }

    /* ---------- KPI number counters (parse-and-restore) ---------- */
    var COUNTER_MS = 800;

    function startCounter(el) {
        if (reduced) return;
        if (el.dataset.sitBusy) return;
        var text = el.textContent || '';
        var m = text.match(/(-?\d[\d,]*(?:\.\d+)?)/);
        if (!m) return;
        var target = parseFloat(m[1].replace(/,/g, ''));
        if (!isFinite(target) || target <= 0) return;
        if (el.dataset.sitCounted === text) return;
        var prefix = text.slice(0, m.index);
        var suffix = text.slice(m.index + m[1].length);
        var decimals = (m[1].split('.')[1] || '').length;
        el.dataset.sitBusy = '1';
        var startTs = null;
        function step(ts) {
            if (startTs === null) startTs = ts;
            var p = Math.min((ts - startTs) / COUNTER_MS, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            if (p < 1) {
                el.textContent = prefix +
                    (target * eased).toLocaleString('en-US', {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals
                    }) + suffix;
                requestAnimationFrame(step);
            } else {
                el.textContent = text;
                delete el.dataset.sitBusy;
                el.dataset.sitCounted = text;
            }
        }
        requestAnimationFrame(step);
    }

    function scanCounters(root) {
        if (!root || !root.querySelectorAll) return;
        root.querySelectorAll('.sit-count').forEach(startCounter);
        if (root.matches && root.matches('.sit-count')) startCounter(root);
    }

    scanCounters(document);

    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mu) {
                if (mu.type === 'characterData') {
                    var p = mu.target.parentNode;
                    if (p && p.matches && p.matches('.sit-count')) startCounter(p);
                    return;
                }
                mu.addedNodes.forEach(function (n) {
                    var parent = n.nodeType === 3 ? n.parentNode : null;
                    if (parent && parent.matches && parent.matches('.sit-count')) startCounter(parent);
                    else if (n.nodeType === 1) scanCounters(n);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true, characterData: true });
    }
})();
