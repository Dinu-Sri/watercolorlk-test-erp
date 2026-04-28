<?php
/**
 * Shared chrome JS — runs on every page.
 * Wires up: header search autocomplete, bottom-nav, promo bar dismiss, activity ticker.
 */
?>
<script src="assets/js/cart.js?v=1" defer></script>
<style>
/* ===== Search autocomplete dropdown ===== */
.suggestions { padding: 6px 0; max-height: 70vh; overflow-y: auto; }
.suggestions .sug-section {
    padding: 8px 14px 4px; color: #6b7388;
    font: 800 .68rem/1 'Montserrat', sans-serif; letter-spacing: .08em; text-transform: uppercase;
}
.suggestions .sug-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; cursor: pointer; text-decoration: none; color: inherit;
    transition: background .12s; border-bottom: 0;
}
.suggestions .sug-item:hover, .suggestions .sug-item.is-active { background: #fdf3e6; }
.suggestions .sug-item .ico { width: 16px; height: 16px; flex: 0 0 16px; color: #98a1b3; }
.suggestions .sug-item .lbl { flex: 1; min-width: 0; color: #273144; font: 600 14px/1.3 'Source Sans 3', sans-serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.suggestions .sug-item .lbl b { color: var(--brand-navy); font-weight: 800; }
.suggestions .sug-item .meta { color: #98a1b3; font: 600 .76rem/1 'Source Sans 3', sans-serif; flex: 0 0 auto; }
.suggestions .sug-item.product .img { width: 32px; height: 32px; border-radius: 6px; background: #f3eee6; flex: 0 0 32px; object-fit: cover; }
.suggestions .sug-item.product .price { color: var(--brand-navy); font: 800 .82rem/1 'Source Sans 3', sans-serif; flex: 0 0 auto; }
.suggestions hr { border: 0; border-top: 1px solid #f1ecdf; margin: 4px 0; }
.suggestions .sug-empty { padding: 14px; color: #98a1b3; font: 500 .88rem/1.3 'Source Sans 3', sans-serif; text-align: center; }
</style>
<script>
(function() {
    /* ============================================================
       Universal header search with Google/AliExpress-style autocomplete.
    ============================================================ */
    var input = document.getElementById('search');
    var box   = document.getElementById('suggestions');

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);});
    }
    function highlight(text, q) {
        if (!q) return escapeHtml(text);
        var safe = escapeHtml(text);
        var rx;
        try { rx = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'); }
        catch (_) { return safe; }
        return safe.replace(rx, '<b>$1</b>');
    }
    function shopUrl(q, extra) {
        var p = new URLSearchParams();
        if (q) p.set('q', q);
        if (extra && extra.category) p.set('category', extra.category);
        if (extra && extra.brand) p.set('brand', extra.brand);
        return (window.WLK_BASE || '') + 'shop.php' + (p.toString() ? '?' + p.toString() : '');
    }
    function logQuery(q) {
        if (!q || q.length < 2) return;
        try {
            var blob = new Blob([JSON.stringify({q: q})], {type: 'application/json'});
            var url = (window.WLK_BASE || '') + 'api/log-search.php';
            if (navigator.sendBeacon) navigator.sendBeacon(url, blob);
            else fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({q: q}), keepalive: true});
        } catch (_) {}
    }

    if (input && box) {
        var debounceTimer = null;
        var lastQ = null;
        var activeIdx = -1;
        var items = [];

        function close() { box.style.display = 'none'; activeIdx = -1; }
        function open()  { box.style.display = 'block'; }

        function render(data) {
            var q = (input.value || '').trim();
            var html = '';
            items = [];

            var trending = (data && data.trending) || [];
            var products = (data && data.products) || [];
            var brands   = (data && data.brands)   || [];
            var cats     = (data && data.categories) || [];

            if (!q) {
                if (trending.length) {
                    html += '<div class="sug-section">Trending searches</div>';
                    trending.forEach(function(t) {
                        items.push({ type: 'query', q: t.query, url: shopUrl(t.query) });
                        html += '<a class="sug-item" data-i="' + (items.length - 1) + '" href="' + shopUrl(t.query) + '">' +
                            '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/></svg>' +
                            '<span class="lbl">' + escapeHtml(t.query) + '</span>' +
                            '<span class="meta">' + t.hits + ' searches</span>' +
                        '</a>';
                    });
                } else {
                    html += '<div class="sug-empty">Start typing to search products, brands &amp; categories</div>';
                }
            } else {
                var didAny = false;
                if (trending.length) {
                    didAny = true;
                    html += '<div class="sug-section">Popular searches</div>';
                    trending.forEach(function(t) {
                        items.push({ type: 'query', q: t.query, url: shopUrl(t.query) });
                        html += '<a class="sug-item" data-i="' + (items.length - 1) + '" href="' + shopUrl(t.query) + '">' +
                            '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>' +
                            '<span class="lbl">' + highlight(t.query, q) + '</span>' +
                        '</a>';
                    });
                }
                if (cats.length) {
                    didAny = true;
                    html += '<hr><div class="sug-section">Categories</div>';
                    cats.forEach(function(c) {
                        var u = shopUrl('', { category: c.keyword });
                        items.push({ type: 'category', q: c.label, url: u });
                        html += '<a class="sug-item" data-i="' + (items.length - 1) + '" href="' + u + '">' +
                            '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>' +
                            '<span class="lbl">' + highlight(c.label, q) + '</span>' +
                            '<span class="meta">in shop</span>' +
                        '</a>';
                    });
                }
                if (brands.length) {
                    didAny = true;
                    html += '<hr><div class="sug-section">Brands</div>';
                    brands.forEach(function(b) {
                        var u = shopUrl('', { brand: b.name });
                        items.push({ type: 'brand', q: b.name, url: u });
                        html += '<a class="sug-item" data-i="' + (items.length - 1) + '" href="' + u + '">' +
                            '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4"/></svg>' +
                            '<span class="lbl">' + highlight(b.name, q) + '</span>' +
                            '<span class="meta">' + b.count + ' product' + (b.count === 1 ? '' : 's') + '</span>' +
                        '</a>';
                    });
                }
                if (products.length) {
                    didAny = true;
                    html += '<hr><div class="sug-section">Products</div>';
                    products.forEach(function(p) {
                        var name = p.display_name || p.name || '';
                        var slug = (p.slug && !/^product-\d+$/i.test(p.slug)) ? p.slug : (name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'product');
                        var url = (window.WLK_BASE || '') + 'product/' + encodeURIComponent(slug + '-' + Number(p.erp_product_id));
                        items.push({ type: 'product', q: name, url: url });
                        var img = p.image_url || 'assets/images/brand/logo-watercolorlk.png';
                        html += '<a class="sug-item product" data-i="' + (items.length - 1) + '" href="' + url + '">' +
                            '<img class="img" src="' + escapeHtml(img) + '" alt="" onerror="this.onerror=null;this.src=\'assets/images/brand/logo-watercolorlk.png\';">' +
                            '<span class="lbl">' + highlight(name, q) + '</span>' +
                            '<span class="price">LKR ' + Number(p.price || 0).toLocaleString('en-LK') + '</span>' +
                        '</a>';
                    });
                }
                if (!didAny) {
                    items.push({ type: 'query', q: q, url: shopUrl(q) });
                    html += '<a class="sug-item" data-i="0" href="' + shopUrl(q) + '">' +
                        '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>' +
                        '<span class="lbl">Search for "<b>' + escapeHtml(q) + '</b>"</span>' +
                    '</a>';
                }
            }

            box.innerHTML = html;
            open();
        }

        function fetchSuggestions(q) {
            if (q === lastQ) return;
            lastQ = q;
            fetch((window.WLK_BASE || '') + 'api/search-suggest.php?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) render(data);
                    else close();
                })
                .catch(function() {});
        }

        function setActive(i) {
            var nodes = box.querySelectorAll('.sug-item');
            nodes.forEach(function(n) { n.classList.remove('is-active'); });
            if (i >= 0 && i < nodes.length) {
                nodes[i].classList.add('is-active');
                nodes[i].scrollIntoView({ block: 'nearest' });
            }
            activeIdx = i;
        }

        input.addEventListener('focus', function() {
            clearTimeout(debounceTimer);
            var v = input.value.trim();
            debounceTimer = setTimeout(function() { fetchSuggestions(v); }, 80);
        });
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var v = input.value.trim();
            debounceTimer = setTimeout(function() { fetchSuggestions(v); }, 180);
        });
        input.addEventListener('keydown', function(e) {
            if (box.style.display !== 'none') {
                if (e.key === 'ArrowDown') { e.preventDefault(); setActive(Math.min(items.length - 1, activeIdx + 1)); return; }
                if (e.key === 'ArrowUp')   { e.preventDefault(); setActive(Math.max(-1, activeIdx - 1)); return; }
                if (e.key === 'Escape')    { close(); return; }
            }
            if (e.key === 'Enter') {
                if (activeIdx >= 0 && items[activeIdx]) {
                    e.preventDefault();
                    var it = items[activeIdx];
                    if (it.type === 'query') logQuery(it.q);
                    window.location.href = it.url;
                    return;
                }
                /* shop.php manages its own Enter for live filtering */
                if (input.dataset.shopBound === '1') {
                    var vv = input.value.trim();
                    if (vv) logQuery(vv);
                    close();
                    return; /* do not preventDefault — shop.js owns Enter */
                }
                e.preventDefault();
                var v = input.value.trim();
                if (v) logQuery(v);
                window.location.href = (window.WLK_BASE || '') + 'shop.php' + (v ? '?q=' + encodeURIComponent(v) : '');
            }
        });
        box.addEventListener('mousedown', function(e) {
            var a = e.target.closest('.sug-item');
            if (!a) return;
            var idx = Number(a.dataset.i);
            var it = items[idx];
            if (it && it.type === 'query') logQuery(it.q);
            /* native navigation */
        });
        document.addEventListener('click', function(e) {
            if (e.target !== input && !box.contains(e.target)) close();
        });
    }

    /* Bottom nav: focus header search */
    var bs = document.getElementById('bottomNavSearch');
    if (bs) {
        bs.addEventListener('click', function(e) {
            e.preventDefault();
            var s = document.getElementById('search');
            if (s) { s.focus(); s.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        });
    }

    /* Promo bar dismiss */
    var pb = document.getElementById('promoBar');
    var pc = document.getElementById('promoClose');
    if (pb && pc) {
        if (sessionStorage.getItem('wlk_promo_hidden') === '1') pb.classList.add('is-hidden');
        pc.addEventListener('click', function() {
            pb.classList.add('is-hidden');
            sessionStorage.setItem('wlk_promo_hidden', '1');
        });
    }

    /* Activity ticker */
    var toast = document.getElementById('activityToast');
    var text = document.getElementById('actText');
    var sub = document.getElementById('actSub');
    if (toast && text) {
        var names = ['Nimal', 'Ayesha', 'Dilani', 'Kasun', 'Ravi', 'Tharushi', 'Sahan', 'Imali', 'Pasindu', 'Hiruni', 'Janani', 'Sanduni'];
        var cities = ['Colombo', 'Kandy', 'Galle', 'Negombo', 'Jaffna', 'Matara', 'Kurunegala', 'Anuradhapura', 'Ratnapura', 'Batticaloa'];
        var verbs = ['just ordered', 'just added to cart', 'is viewing', 'just bought'];
        var subs = ['Verified order', 'Live on site', 'Just now', 'Confirmed checkout'];
        var rand = function(arr) { return arr[Math.floor(Math.random() * arr.length)]; };
        var pickProductName = function() {
            var cards = document.querySelectorAll('.deal-name, .name, h1, .best-name, .sp-card .nm');
            if (!cards.length) return 'a watercolor product';
            var c = cards[Math.floor(Math.random() * cards.length)];
            return (c.textContent || '').trim().slice(0, 60) || 'a watercolor product';
        };
        var rounds = 0;
        var show = function() {
            var name = rand(names);
            var city = rand(cities);
            var verb = rand(verbs);
            var product = pickProductName();
            var mins = 1 + Math.floor(Math.random() * 12);
            text.innerHTML = '<strong>' + name + '</strong> from ' + city + ' ' + verb + ' <em style="font-style:normal;color:var(--amber)">' + product + '</em> &middot; ' + mins + ' min ago';
            if (sub) sub.textContent = rand(subs);
            toast.classList.add('is-visible');
            setTimeout(function() { toast.classList.remove('is-visible'); }, 5500);
        };
        setTimeout(show, 4500);
        setInterval(function() { rounds++; if (rounds > 12) return; show(); }, 12000);
    }
})();
</script>
