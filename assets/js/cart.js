/**
 * WLKCart v2 - localStorage-only guest cart shared across pages.
 *
 * Schema (v2):
 *   localStorage.wlk_cart = JSON [{
 *     line_id,                  // synthetic match key (string)
 *     kind,                     // 'simple' | 'combined' | 'pack'
 *     erp_product_id,           // for kind=simple/combined: the ERP id to fulfil. For pack: 0/null.
 *     storefront_product_id,    // optional - new admin-managed product id
 *     parent_storefront_id,     // for combined: same as storefront_product_id; for pack: same; for simple: null
 *     variant_child_id,         // for combined: storefront_product_children.id of the chosen variant
 *     variant_label,            // for combined: human label (e.g. "Cobalt Blue")
 *     pack_children,            // for pack: [{erp_product_id, quantity, name}, ...]
 *     name, slug, image_url, price, qty, sku
 *   }, ...]
 *
 * Public API on window.WLKCart:
 *   add(item, qty=1)            - merge by line_id (or auto-derive)
 *   remove(line_id)
 *   updateQty(line_id, qty)
 *   clear()
 *   items()                     - array
 *   count()                     - sum of qty
 *   subtotal()                  - sum of price*qty
 *   shipping(subtotal?)         - flat 350 LKR, free over 5000
 *   total()                     - subtotal + shipping
 *   on(event, fn)               - 'change' fires on any mutation
 *   off(event, fn)
 *   formatLKR(n)                - "LKR 1,234.50" helper
 *
 * Persists across tabs via the storage event.
 *
 * BACKWARD COMPATIBILITY: legacy carts (pre-v2) are wiped once on load; the
 * `wlk_cart_v` key tracks the schema version.
 */
(function() {
    if (window.WLKCart) return;

    var KEY = 'wlk_cart';
    var VERSION_KEY = 'wlk_cart_v';
    var CURRENT_VERSION = '2';
    var FREE_SHIP_THRESHOLD = 5000;
    var FLAT_SHIP = 350;
    var listeners = { change: [] };

    /* One-time wipe of v1 carts (which lacked line_id / kind). */
    try {
        if (localStorage.getItem(VERSION_KEY) !== CURRENT_VERSION) {
            localStorage.removeItem(KEY);
            localStorage.setItem(VERSION_KEY, CURRENT_VERSION);
        }
    } catch (_) { /* private mode etc. */ }

    function deriveLineId(item) {
        if (item.line_id) return String(item.line_id);
        var kind = item.kind || 'simple';
        if (kind === 'combined' && item.variant_child_id) {
            return 'cmb:' + (item.parent_storefront_id || item.storefront_product_id || 0) + ':' + item.variant_child_id;
        }
        if (kind === 'pack') {
            return 'pk:' + (item.parent_storefront_id || item.storefront_product_id || 0);
        }
        return 'sp:' + Number(item.erp_product_id || 0);
    }

    function read() {
        try {
            var raw = localStorage.getItem(KEY);
            var arr = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(arr)) return [];
            return arr.filter(function(x) {
                return x && typeof x === 'object' && Number(x.qty) > 0
                    && (Number(x.erp_product_id) > 0 || (x.kind === 'pack' && Array.isArray(x.pack_children) && x.pack_children.length > 0));
            }).map(function(x) {
                if (!x.line_id) x.line_id = deriveLineId(x);
                if (!x.kind) x.kind = 'simple';
                return x;
            });
        } catch (_) { return []; }
    }

    function write(list) {
        try {
            localStorage.setItem(KEY, JSON.stringify(list));
            localStorage.setItem(VERSION_KEY, CURRENT_VERSION);
        } catch (_) { /* quota or disabled */ }
        emit('change', list);
    }

    function emit(name, payload) {
        (listeners[name] || []).forEach(function(fn) {
            try { fn(payload); } catch (_) { /* swallow */ }
        });
    }

    function add(item, qty) {
        if (!item) return;
        var kind = item.kind || 'simple';
        if (kind !== 'pack' && !Number(item.erp_product_id)) return;
        if (kind === 'pack' && !(Array.isArray(item.pack_children) && item.pack_children.length)) return;

        qty = Math.max(1, Number(qty || 1));
        var lineId = deriveLineId(item);
        var list = read();
        var found = false;
        for (var i = 0; i < list.length; i++) {
            if (list[i].line_id === lineId) {
                list[i].qty = Math.min(99, Number(list[i].qty || 0) + qty);
                list[i].name = item.name || list[i].name;
                list[i].slug = item.slug || list[i].slug;
                list[i].image_url = item.image_url || list[i].image_url;
                list[i].price = Number(item.price || list[i].price);
                list[i].sku = item.sku || list[i].sku;
                if (item.variant_label) list[i].variant_label = item.variant_label;
                if (item.pack_children) list[i].pack_children = item.pack_children;
                found = true;
                break;
            }
        }
        if (!found) {
            list.push({
                line_id: lineId,
                kind: kind,
                erp_product_id: Number(item.erp_product_id || 0),
                storefront_product_id: Number(item.storefront_product_id || 0) || null,
                parent_storefront_id: Number(item.parent_storefront_id || 0) || null,
                variant_child_id: Number(item.variant_child_id || 0) || null,
                variant_label: item.variant_label ? String(item.variant_label) : null,
                pack_children: Array.isArray(item.pack_children) ? item.pack_children : null,
                name: String(item.name || ''),
                slug: String(item.slug || ''),
                image_url: String(item.image_url || ''),
                price: Number(item.price || 0),
                qty: Math.min(99, qty),
                sku: String(item.sku || '')
            });
        }
        write(list);
    }

    function remove(lineId) {
        var key = String(lineId);
        var list = read().filter(function(x) {
            return x.line_id !== key && Number(x.erp_product_id) !== Number(lineId);
        });
        write(list);
    }

    function updateQty(lineId, qty) {
        qty = Math.max(0, Math.min(99, Number(qty || 0)));
        if (qty === 0) return remove(lineId);
        var key = String(lineId);
        var list = read();
        for (var i = 0; i < list.length; i++) {
            if (list[i].line_id === key || Number(list[i].erp_product_id) === Number(lineId)) {
                list[i].qty = qty;
                break;
            }
        }
        write(list);
    }

    function clear() { write([]); }
    function items() { return read(); }
    function count() {
        return read().reduce(function(s, x) { return s + Number(x.qty || 0); }, 0);
    }
    function subtotal() {
        return read().reduce(function(s, x) {
            return s + Number(x.price || 0) * Number(x.qty || 0);
        }, 0);
    }
    function shipping(sub) {
        var s = sub == null ? subtotal() : Number(sub);
        if (count() === 0) return 0;
        return s >= FREE_SHIP_THRESHOLD ? 0 : FLAT_SHIP;
    }
    function total() { return subtotal() + shipping(); }

    function on(name, fn) {
        if (!listeners[name]) listeners[name] = [];
        listeners[name].push(fn);
    }
    function off(name, fn) {
        if (!listeners[name]) return;
        listeners[name] = listeners[name].filter(function(x) { return x !== fn; });
    }

    function formatLKR(n) {
        return 'LKR ' + Number(n || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    window.addEventListener('storage', function(e) {
        if (e.key === KEY) emit('change', read());
    });

    window.WLKCart = {
        add: add,
        remove: remove,
        updateQty: updateQty,
        clear: clear,
        items: items,
        count: count,
        subtotal: subtotal,
        shipping: shipping,
        total: total,
        on: on,
        off: off,
        formatLKR: formatLKR,
        deriveLineId: deriveLineId,
        FREE_SHIP_THRESHOLD: FREE_SHIP_THRESHOLD,
        FLAT_SHIP: FLAT_SHIP,
        VERSION: CURRENT_VERSION
    };

    function renderPip() {
        var pip = document.getElementById('cartPip');
        if (!pip) return;
        var n = count();
        if (n > 0) {
            pip.textContent = n > 99 ? '99+' : String(n);
            pip.removeAttribute('hidden');
        } else {
            pip.setAttribute('hidden', '');
        }
    }

    function wire() {
        var btn = document.getElementById('cartButton');
        if (btn) {
            btn.setAttribute('href', 'cart.php');
            btn.addEventListener('click', function(e) {
                if (btn.dataset.wlkBound === '1') return;
            });
            btn.dataset.wlkBound = '1';
        }
        var bottom = document.getElementById('bottomNavCart');
        if (bottom) {
            bottom.setAttribute('href', 'cart.php');
        }
        renderPip();
        on('change', renderPip);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire);
    } else {
        wire();
    }

    window.WLKCart.toast = function(msg, opts) {
        opts = opts || {};
        var t = document.getElementById('wlkToast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'wlkToast';
            t.style.cssText = 'position:fixed;left:50%;top:84px;transform:translateX(-50%) translateY(-14px);z-index:300;background:#10203a;color:#fff;padding:12px 18px;border-radius:14px;font:700 .92rem/1.2 Montserrat,sans-serif;box-shadow:0 12px 30px rgba(16,32,58,.32);display:flex;align-items:center;gap:10px;opacity:0;transition:opacity .2s,transform .25s;pointer-events:none;max-width:90vw;';
            document.body.appendChild(t);
        }
        var actionHTML = opts.action
            ? ' <a href="' + opts.action.href + '" style="color:#ffb877;text-decoration:underline;font-weight:800;pointer-events:auto;">' + opts.action.label + '</a>'
            : '';
        t.innerHTML = '<span style="color:#7be39c;font-size:1.05rem;">\u2713</span><span>' + msg + '</span>' + actionHTML;
        requestAnimationFrame(function() {
            t.style.opacity = '1';
            t.style.transform = 'translateX(-50%) translateY(0)';
        });
        clearTimeout(t._wlkTimer);
        t._wlkTimer = setTimeout(function() {
            t.style.opacity = '0';
            t.style.transform = 'translateX(-50%) translateY(-14px)';
        }, opts.duration || 2600);
    };
})();
