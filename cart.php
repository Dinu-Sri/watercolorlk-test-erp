<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/* Pull a few best-seller suggestions for the cross-sell rail. */
$crossSell = [];
try {
    $repo = new ProductRepository(appDb());
    $crossSell = $repo->listBestSellers(4);
} catch (Throwable $e) {
    $crossSell = [];
}

function cart_product_url(string $slug, string $name, int $erpId): string
{
    $base = trim($slug);
    if ($base === '' || preg_match('/^product-\d+$/i', $base) === 1) {
        $base = $name !== '' ? $name : 'product';
    }
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
        if (is_string($ascii) && $ascii !== '') {
            $base = $ascii;
        }
    }
    $base = strtolower($base);
    $base = preg_replace('/[^a-z0-9]+/i', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'product';
    }
    return 'product.php?slug=' . rawurlencode($base) . '-' . $erpId;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Your cart - Watercolor.LK</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" type="image/png" href="assets/images/brand/logo-watercolorlk.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800;900&family=Playfair+Display:wght@600;700;800&family=Source+Sans+3:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php include __DIR__ . '/partials/chrome-styles.php'; ?>
<style>
.cart-wrap { padding: 20px 0 60px; }
.cart-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.cart-head h1 { margin: 0; color: var(--brand-navy-deep); font: 800 1.7rem/1.1 'Playfair Display', serif; }
.cart-head .meta { color: #6b7388; font: 600 .92rem/1.2 'Source Sans 3', sans-serif; }
.cart-head a.continue { color: var(--amber-deep); text-decoration: none; font: 700 .9rem/1 'Montserrat', sans-serif; }
.cart-head a.continue:hover { text-decoration: underline; }

.cart-grid { display: grid; grid-template-columns: 1.55fr 1fr; gap: 22px; align-items: start; }
.cart-list { display: grid; gap: 12px; }
.cart-item {
    display: grid;
    grid-template-columns: 86px 1fr auto;
    gap: 14px;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 14px;
    box-shadow: var(--shadow-sm);
}
.cart-item img.thumb {
    width: 86px; height: 86px; border-radius: 12px;
    object-fit: cover; background: #f3eee6; border: 1px solid var(--line);
}
.cart-item .body { min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.cart-item .body a {
    color: var(--brand-navy-deep); text-decoration: none;
    font: 700 1rem/1.3 'Source Sans 3', sans-serif;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.cart-item .body a:hover { color: var(--amber-deep); }
.cart-item .meta { color: #6b7388; font: 600 .78rem/1 'Montserrat', sans-serif; }
.cart-item .row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 4px; }
.cart-item .unit { color: var(--brand-navy); font: 700 .96rem/1 'Source Sans 3', sans-serif; }
.cart-item .stepper {
    display: inline-flex; align-items: center;
    border: 1px solid var(--line); border-radius: 999px; background: #fff; overflow: hidden;
}
.cart-item .stepper button {
    width: 30px; height: 30px; border: 0; cursor: pointer;
    background: transparent; color: var(--brand-navy); font: 800 1.05rem/1 'Montserrat', sans-serif;
}
.cart-item .stepper button:hover { background: rgba(232,118,10,.08); color: var(--amber-deep); }
.cart-item .stepper input {
    width: 36px; height: 30px; border: 0; text-align: center; outline: none;
    font: 800 .92rem/1 'Source Sans 3', sans-serif; color: var(--brand-navy);
    background: transparent;
}
.cart-item .right {
    display: flex; flex-direction: column; align-items: flex-end; gap: 8px;
    justify-content: space-between;
}
.cart-item .line-total { color: var(--brand-navy-deep); font: 800 1.05rem/1 'Source Sans 3', sans-serif; white-space: nowrap; }
.cart-item .remove {
    background: transparent; border: 0; cursor: pointer; color: #b8232f;
    font: 700 .82rem/1 'Montserrat', sans-serif; display: inline-flex; align-items: center; gap: 4px;
}
.cart-item .remove:hover { text-decoration: underline; }

.empty {
    background: #fff; border: 1px dashed #d9cab8; border-radius: 18px;
    padding: 36px 24px; text-align: center;
}
.empty img { width: 130px; opacity: .9; }
.empty h2 { margin: 8px 0 4px; color: var(--brand-navy-deep); font: 700 1.3rem/1.2 'Playfair Display', serif; }
.empty p { color: #6b7388; margin: 0 0 16px; font: 500 .94rem/1.5 'Source Sans 3', sans-serif; }
.empty a {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(180deg, #ff5b3a, #b8232f); color: #fff; text-decoration: none;
    padding: 12px 22px; border-radius: 999px;
    font: 800 .92rem/1 'Montserrat', sans-serif;
    box-shadow: 0 8px 20px rgba(184,35,47,.3);
}

.summary {
    position: sticky; top: 84px;
    background: #fff; border: 1px solid var(--line); border-radius: 18px;
    padding: 18px; box-shadow: var(--shadow-sm);
}
.summary h2 { margin: 0 0 12px; color: var(--brand-navy-deep); font: 800 1.1rem/1.2 'Playfair Display', serif; }
.summary .line { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; color: #4a5468; font: 600 .94rem/1.3 'Source Sans 3', sans-serif; }
.summary .line strong { color: var(--brand-navy); font-weight: 800; }
.summary .ship-free { color: #17633e; font-weight: 800; }
.summary .total {
    border-top: 1px dashed var(--line); margin-top: 6px; padding-top: 12px;
    display: flex; justify-content: space-between; align-items: baseline;
}
.summary .total .label { color: var(--brand-navy-deep); font: 800 1rem/1 'Montserrat', sans-serif; letter-spacing: .04em; text-transform: uppercase; }
.summary .total .amount { color: #b8232f; font: 900 1.5rem/1 'Source Sans 3', sans-serif; }

.summary .promo {
    margin-top: 12px; display: flex; gap: 6px;
}
.summary .promo input {
    flex: 1; min-width: 0; padding: 10px 12px; border: 1px solid var(--line);
    border-radius: 10px; font: 600 .9rem/1 'Source Sans 3', sans-serif; outline: none;
}
.summary .promo input:focus { border-color: var(--amber); }
.summary .promo button {
    padding: 10px 14px; border-radius: 10px; cursor: pointer;
    border: 1px solid var(--brand-navy); background: #fff; color: var(--brand-navy);
    font: 700 .82rem/1 'Montserrat', sans-serif;
}
.summary .promo-msg { font-size: .82rem; margin-top: 6px; min-height: 14px; }

.summary .checkout-btn {
    margin-top: 14px; width: 100%; padding: 14px;
    border: 0; border-radius: 14px; cursor: pointer; color: #fff;
    background: linear-gradient(180deg, #ff5b3a, #b8232f);
    font: 800 1.02rem/1.2 'Montserrat', sans-serif; letter-spacing: .04em;
    box-shadow: 0 10px 22px rgba(184,35,47,.3);
}
.summary .checkout-btn:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }

.summary .trust-mini {
    display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px;
    align-items: center; justify-content: center;
}
.summary .trust-mini img { height: 22px; width: auto; }
.summary .trust-mini .chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 9px; border-radius: 999px; border: 1px solid var(--line);
    font: 700 .72rem/1 'Montserrat', sans-serif; color: #4a5468; background: #fff;
}
.summary .ship-progress {
    margin-top: 12px; padding: 10px 12px; background: rgba(232,118,10,.06);
    border: 1px solid rgba(232,118,10,.18); border-radius: 12px;
    font: 600 .82rem/1.4 'Source Sans 3', sans-serif; color: var(--amber-deep);
}
.summary .ship-progress .bar { height: 6px; border-radius: 999px; background: #ecd9d1; margin-top: 6px; overflow: hidden; }
.summary .ship-progress .bar > span { display: block; height: 100%; background: linear-gradient(90deg, var(--gold), var(--amber)); transition: width .3s; }

/* Cross-sell rail */
.upsell { margin-top: 36px; }
.upsell h2 { margin: 0 0 14px; color: var(--brand-navy-deep); font: 800 1.25rem/1.2 'Playfair Display', serif; }
.upsell-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.upsell-card {
    background: #fff; border: 1px solid var(--line); border-radius: 16px;
    overflow: hidden; text-decoration: none; color: inherit;
    transition: transform .15s, box-shadow .15s;
}
.upsell-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.upsell-card .media { aspect-ratio: 1/1; background: #f3eee6; padding: 10px; display: flex; align-items: center; justify-content: center; }
.upsell-card .media img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.upsell-card .body { padding: 10px 12px 12px; }
.upsell-card .body h3 {
    margin: 0 0 6px; color: var(--brand-navy-deep);
    font: 700 .9rem/1.25 'Source Sans 3', sans-serif;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    min-height: 36px;
}
.upsell-card .body .pr { color: var(--brand-navy); font: 800 1.02rem/1 'Source Sans 3', sans-serif; }

@media (max-width: 980px) {
    .cart-grid { grid-template-columns: 1fr; }
    .summary { position: static; }
    .upsell-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 720px) {
    .cart-item { grid-template-columns: 70px 1fr; gap: 10px; }
    .cart-item .right { grid-column: 1 / -1; flex-direction: row; align-items: center; justify-content: space-between; }
    .cart-item img.thumb { width: 70px; height: 70px; }
    .upsell-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>
<?php
$showPromoBar = false;
$headerSearchValue = '';
$cartCount = 0;
include __DIR__ . '/partials/site-header.php';
?>

<main class="wrap cart-wrap">
    <div class="cart-head">
        <div>
            <h1>Your cart</h1>
            <div class="meta" id="cartMeta">Loading...</div>
        </div>
        <a class="continue" href="index.php">&larr; Continue shopping</a>
    </div>

    <div class="cart-grid">
        <div>
            <div class="cart-list" id="cartList"></div>
            <div class="empty" id="cartEmpty" hidden>
                <img src="assets/images/mascots/watercolor-paints.webp" alt="" onerror="this.onerror=null;this.src='assets/images/brand/logo-watercolorlk.png';">
                <h2>Your cart is empty</h2>
                <p>Browse our paints, brushes, and papers and add a few favourites.</p>
                <a href="index.php">Start shopping</a>
            </div>
        </div>

        <aside class="summary" id="summary">
            <h2>Order summary</h2>
            <div class="line"><span>Subtotal (<span id="sumQty">0</span> items)</span><strong id="sumSub">LKR 0.00</strong></div>
            <div class="line"><span>Shipping</span><strong id="sumShip">LKR 0.00</strong></div>
            <div class="ship-progress" id="shipProgress" hidden>
                <span id="shipProgressText"></span>
                <div class="bar"><span id="shipProgressBar" style="width:0%"></span></div>
            </div>
            <div class="total"><span class="label">Total</span><span class="amount" id="sumTotal">LKR 0.00</span></div>

            <div class="promo">
                <input type="text" id="promoCode" placeholder="Promo code (optional)" autocomplete="off">
                <button type="button" id="promoApply">Apply</button>
            </div>
            <div class="promo-msg" id="promoMsg"></div>

            <button class="checkout-btn" id="checkoutBtn" disabled>Proceed to checkout</button>

            <div class="trust-mini">
                <span class="chip">7-day returns</span>
                <span class="chip">COD island-wide</span>
                <img src="https://www.payhere.lk/downloads/images/payhere_long_banner.png" alt="PayHere" loading="lazy">
            </div>
        </aside>
    </div>

    <?php if (!empty($crossSell)): ?>
    <section class="upsell">
        <h2>You might also like</h2>
        <div class="upsell-grid">
            <?php foreach ($crossSell as $p): ?>
                <a class="upsell-card" href="<?= htmlspecialchars(cart_product_url((string)($p['slug'] ?? ''), (string)($p['display_name'] ?? ''), (int)$p['erp_product_id'])) ?>">
                    <div class="media"><img src="<?= htmlspecialchars((string)($p['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$p['display_name']) ?>" loading="lazy"></div>
                    <div class="body">
                        <h3><?= htmlspecialchars((string)$p['display_name']) ?></h3>
                        <div class="pr">LKR <?= number_format((float)$p['price'], 2) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/site-footer.php'; ?>
<?php include __DIR__ . '/partials/site-scripts.php'; ?>

<script>
(function() {
    function ready(fn) {
        if (window.WLKCart) return fn();
        var t = setInterval(function() {
            if (window.WLKCart) { clearInterval(t); fn(); }
        }, 30);
    }
    ready(function() {
        var listEl = document.getElementById('cartList');
        var emptyEl = document.getElementById('cartEmpty');
        var metaEl = document.getElementById('cartMeta');
        var sumQty = document.getElementById('sumQty');
        var sumSub = document.getElementById('sumSub');
        var sumShip = document.getElementById('sumShip');
        var sumTotal = document.getElementById('sumTotal');
        var btn = document.getElementById('checkoutBtn');
        var shipProgress = document.getElementById('shipProgress');
        var shipProgressBar = document.getElementById('shipProgressBar');
        var shipProgressText = document.getElementById('shipProgressText');

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]);
            });
        }

        function buildSlug(name) {
            return String(name || 'product').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'product';
        }

        function productUrl(it) {
            var base = it.slug && !/^product-\d+$/i.test(it.slug) ? it.slug : buildSlug(it.name);
            return 'product.php?slug=' + encodeURIComponent(base) + '-' + Number(it.erp_product_id);
        }

        function render() {
            var items = WLKCart.items();
            if (items.length === 0) {
                listEl.innerHTML = '';
                emptyEl.removeAttribute('hidden');
                metaEl.textContent = '0 items';
                btn.disabled = true;
                shipProgress.setAttribute('hidden', '');
            } else {
                emptyEl.setAttribute('hidden', '');
                metaEl.textContent = WLKCart.count() + ' items in your cart';
                btn.disabled = false;

                listEl.innerHTML = items.map(function(it) {
                    var img = it.image_url || 'assets/images/brand/logo-watercolorlk.png';
                    var lineTotal = WLKCart.formatLKR(Number(it.price) * Number(it.qty));
                    return '<article class="cart-item" data-id="' + Number(it.erp_product_id) + '">' +
                        '<img class="thumb" src="' + escapeHtml(img) + '" alt="" onerror="this.onerror=null;this.src=\'assets/images/brand/logo-watercolorlk.png\';">' +
                        '<div class="body">' +
                            '<a href="' + escapeHtml(productUrl(it)) + '">' + escapeHtml(it.name) + '</a>' +
                            (it.sku ? '<span class="meta">SKU: ' + escapeHtml(it.sku) + '</span>' : '') +
                            '<div class="row">' +
                                '<span class="unit">' + WLKCart.formatLKR(it.price) + '</span>' +
                                '<span class="stepper">' +
                                    '<button type="button" data-act="dec" aria-label="Decrease">&minus;</button>' +
                                    '<input type="text" inputmode="numeric" value="' + Number(it.qty) + '" data-act="qty">' +
                                    '<button type="button" data-act="inc" aria-label="Increase">+</button>' +
                                '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="right">' +
                            '<span class="line-total">' + lineTotal + '</span>' +
                            '<button class="remove" type="button" data-act="rm">' +
                                '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6 18 20a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>' +
                                'Remove' +
                            '</button>' +
                        '</div>' +
                    '</article>';
                }).join('');
            }

            var sub = WLKCart.subtotal();
            var ship = WLKCart.shipping(sub);
            sumQty.textContent = WLKCart.count();
            sumSub.textContent = WLKCart.formatLKR(sub);
            sumShip.innerHTML = items.length === 0 ? WLKCart.formatLKR(0) :
                (ship === 0 ? '<span class="ship-free">FREE</span>' : WLKCart.formatLKR(ship));
            sumTotal.textContent = WLKCart.formatLKR(sub + ship);

            if (items.length > 0 && sub < WLKCart.FREE_SHIP_THRESHOLD) {
                var remaining = WLKCart.FREE_SHIP_THRESHOLD - sub;
                shipProgress.removeAttribute('hidden');
                shipProgressText.innerHTML = 'Add <strong>' + WLKCart.formatLKR(remaining) + '</strong> more to unlock <strong>FREE delivery</strong>';
                shipProgressBar.style.width = Math.max(4, (sub / WLKCart.FREE_SHIP_THRESHOLD) * 100) + '%';
            } else if (items.length > 0) {
                shipProgress.removeAttribute('hidden');
                shipProgressText.innerHTML = '<strong>FREE delivery</strong> unlocked &mdash; you saved LKR 350.';
                shipProgressBar.style.width = '100%';
            } else {
                shipProgress.setAttribute('hidden', '');
            }
        }

        listEl.addEventListener('click', function(e) {
            var item = e.target.closest('.cart-item');
            if (!item) return;
            var id = Number(item.dataset.id);
            var act = e.target.closest('[data-act]') && e.target.closest('[data-act]').dataset.act;
            if (act === 'inc') WLKCart.updateQty(id, currentQty(item) + 1);
            else if (act === 'dec') WLKCart.updateQty(id, currentQty(item) - 1);
            else if (act === 'rm') WLKCart.remove(id);
        });
        listEl.addEventListener('change', function(e) {
            if (!e.target.matches('[data-act="qty"]')) return;
            var item = e.target.closest('.cart-item');
            if (!item) return;
            var id = Number(item.dataset.id);
            var n = Math.max(0, Math.floor(Number(e.target.value) || 0));
            WLKCart.updateQty(id, n);
        });

        function currentQty(item) {
            var inp = item.querySelector('[data-act="qty"]');
            return Math.max(0, Math.floor(Number(inp && inp.value) || 0));
        }

        btn.addEventListener('click', function() {
            if (WLKCart.count() === 0) return;
            window.location.href = 'checkout.php';
        });

        document.getElementById('promoApply').addEventListener('click', function() {
            var msg = document.getElementById('promoMsg');
            msg.style.color = '#b8232f';
            msg.textContent = 'Promo codes are coming soon \u2014 free delivery already applies on orders over LKR 5,000.';
        });

        WLKCart.on('change', render);
        render();
    });
})();
</script>
</body>
</html>
