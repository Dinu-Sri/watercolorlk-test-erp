<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$repo = new ProductRepository(appDb());
$products = $repo->listProducts('', 24, 0);
$initialProductsJson = json_encode($products, JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Watercolor.LK | Premium Art Supplies</title>
    <link rel="icon" type="image/webp" href="assets/images/brand/favicon-watercolorlk.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-navy: #1b2d4f;
            --brand-navy-deep: #10203a;
            --paper: #faf8f5;
            --surface: #ffffff;
            --line: #e7ddd2;
            --text: #1a1a1a;
            --muted: #6b6b6b;
            --amber: #e8760a;
            --amber-deep: #c4600a;
            --rose: #c4705a;
            --danger: #c0392b;
            --success: #2d7a4f;
            --gold: #d8a03d;
            --shadow-sm: 0 10px 24px rgba(17, 31, 56, 0.08);
            --shadow-lg: 0 24px 60px rgba(17, 31, 56, 0.14);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(196, 112, 90, .15), transparent 32%),
                radial-gradient(circle at top right, rgba(232, 118, 10, .08), transparent 28%),
                linear-gradient(180deg, #fffdfa 0%, var(--paper) 42%, #f5eee6 100%);
            font: 400 17px/1.7 'Source Sans 3', 'Segoe UI', sans-serif;
        }
        img { max-width: 100%; display: block; }
        .wrap { width: min(calc(100% - 32px), 1240px); margin: 0 auto; }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 40;
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(16px);
            background: rgba(250, 248, 245, .94);
            box-shadow: 0 8px 24px rgba(17, 31, 56, 0.08);
        }
        .header-inner {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 14px 0;
        }
        .brand {
            min-width: 300px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .logo { height: 42px; width: auto; display: block; }
        .brand-sub {
            color: #43516d;
            font: 700 .79rem/1.2 'Montserrat', sans-serif;
            letter-spacing: .02em;
        }
        .header-search { flex: 1; position: relative; }
        .header-search-input {
            width: 100%;
            border: 1px solid #d7c9b8;
            outline: none;
            border-radius: 999px;
            padding: 17px 18px 17px 48px;
            background: #fff;
            color: #1f2a3d;
            font: 600 1.02rem/1.2 'Source Sans 3', sans-serif;
        }
        .header-search-input::placeholder { color: #7a828f; }
        .header-search-input:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(232,118,10,.14); }
        .header-search span { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #6d7383; font-size: .85rem; }
        .header-actions { display: flex; align-items: center; gap: 10px; }
        .icon-btn {
            width: 46px;
            height: 46px;
            border: 1px solid var(--line);
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--brand-navy);
            background: #fff;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }
        .icon-btn svg { width: 21px; height: 21px; }
        .home-main { padding: 20px 0 30px; }
        .suggestions {
            margin-top: 6px;
            border: 1px solid #d6cab8;
            border-radius: 14px;
            background: #fff;
            display: none;
            overflow: hidden;
            box-shadow: 0 14px 36px rgba(17, 31, 56, .12);
        }
        .suggestion {
            padding: 11px 13px;
            border-bottom: 1px solid #f1ecdf;
            cursor: pointer;
            font: 600 14px/1.3 'Source Sans 3', sans-serif;
            color: #273144;
        }
        .suggestion:hover { background: #fdf8f1; }
        .suggestion:last-child { border-bottom: 0; }
        .meta-row {
            margin: 0 0 12px;
            color: #4e5b73;
            font: 700 .86rem/1.3 'Montserrat', sans-serif;
            letter-spacing: .02em;
        }
        .category-grid { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: 12px; margin-bottom: 18px; }
        .category-card {
            background: rgba(255,255,255,.9);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 12px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            text-decoration: none;
        }
        .category-icon { width: 74px; height: 74px; object-fit: contain; margin: 0 auto 8px; }
        .category-card strong { display: block; color: var(--brand-navy); font: 700 .83rem/1.1 'Montserrat', sans-serif; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(225px, 1fr));
            gap: 18px;
            padding: 18px 0 38px;
        }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 24px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow-sm);
            transition: transform .16s ease, box-shadow .16s ease;
        }
        .card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .media {
            position: relative;
            background: #fff;
            aspect-ratio: 1/1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .img {
            width: 100%;
            object-fit: cover;
            background: #f7f7f7;
            display: block;
            border-radius: 14px;
        }
        .card-badges { position: absolute; top: 12px; left: 12px; display: flex; gap: 6px; flex-wrap: wrap; }
        .body { padding: 16px; }
        .body { display: grid; gap: 8px; align-content: start; min-height: 205px; }
        .name {
            margin: 0;
            color: #22314d;
            font: 700 17px/1.24 'Source Sans 3', sans-serif;
            min-height: 48px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .price-row { display: flex; align-items: end; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .price { color: var(--brand-navy); font: 700 1.55rem/1 'Source Sans 3', sans-serif; }
        .deal-row { display: flex; align-items: baseline; gap: 10px; }
        .price-old { color: #9a8e81; text-decoration: line-through; font-size: 1rem; }
        .price-off { color: var(--danger); font: 700 .88rem/1 'Montserrat', sans-serif; }
        .rating { margin-top: 8px; display: flex; gap: 8px; align-items: center; color: var(--muted); font-size: .82rem; }
        .stars { color: var(--gold); letter-spacing: .08em; }
        .stock { margin-top: 4px; font: 600 13px/1.2 'Montserrat', sans-serif; }
        .stock.ok { color: var(--success); }
        .stock.no { color: var(--danger); }
        .badge {
            display: inline-block;
            font: 700 11px/1 'Montserrat', sans-serif;
            background: rgba(27, 45, 79, .1);
            color: var(--brand-navy);
            border-radius: 999px;
            padding: 6px 9px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .badge.sale { background: rgba(192,57,43,.1); color: var(--danger); }
        .badge.stock { background: rgba(45,122,79,.12); color: var(--success); }
        .badge.low { background: rgba(232,118,10,.12); color: #a44d05; }
        .empty {
            grid-column: 1 / -1;
            background: rgba(255, 255, 255, .86);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 26px;
            text-align: center;
            color: #525e74;
            font: 600 16px/1.4 'Source Sans 3', sans-serif;
        }
        @media (max-width: 720px) {
            .wrap { width: min(calc(100% - 24px), 1240px); }
            .header-inner { padding: 12px 0; }
            .header-inner { flex-wrap: wrap; }
            .header-search, .brand { min-width: 100%; }
            .category-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="index.php">
            <img class="logo" src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK">
            <span class="brand-sub">පටන් ගන්න! පාට කරන්න! ජිවිතය විදින්න!</span>
        </a>
        <div class="header-search">
            <span>Find</span>
            <input id="search" class="header-search-input" placeholder="Search products, brands, categories..." autocomplete="off">
            <div id="suggestions" class="suggestions"></div>
        </div>
        <div class="header-actions">
            <a class="icon-btn" href="admin/index.php" aria-label="Account">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            <a class="icon-btn" href="#" aria-label="Cart" id="cartButton">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H7"/></svg>
            </a>
        </div>
    </div>
</header>

<main class="wrap home-main">
    <section id="categories">
        <div class="category-grid">
            <a class="category-card" href="#"><img class="category-icon" src="assets/images/mascots/watercolor-brushes-1.webp" alt="Brushes"><strong>Brushes</strong></a>
            <a class="category-card" href="#"><img class="category-icon" src="assets/images/mascots/watercolor-papers.webp" alt="Papers"><strong>Papers</strong></a>
            <a class="category-card" href="#"><img class="category-icon" src="assets/images/mascots/watercolor-paints.webp" alt="Paints"><strong>Paints</strong></a>
            <a class="category-card" href="#"><img class="category-icon" src="assets/images/mascots/watercolor-sktechbooks.webp" alt="Sketchbooks"><strong>Sketchbooks</strong></a>
            <a class="category-card" href="#"><img class="category-icon" src="assets/images/mascots/watercolor-assesries.webp" alt="Accessories"><strong>Accessories</strong></a>
        </div>
        <div class="meta-row" id="resultMeta">Showing latest products</div>
    </section>

    <section id="products">
        <div id="grid" class="grid">
        <?php foreach ($products as $product): ?>
            <a class="card" href="product.php?id=<?= (int)$product['erp_product_id'] ?>">
                <div class="media">
                    <div class="card-badges">
                        <?php if ((float)$product['stock_qty'] <= 0): ?>
                            <span class="badge sale">Out of stock</span>
                        <?php elseif ((float)$product['stock_qty'] <= 7): ?>
                            <span class="badge low">Only <?= (int)$product['stock_qty'] ?> left</span>
                        <?php elseif ($product['badge'] !== ''): ?>
                            <span class="badge"><?= htmlspecialchars((string)$product['badge']) ?></span>
                        <?php else: ?>
                            <span class="badge">Featured</span>
                        <?php endif; ?>
                    </div>
                    <img class="img" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['display_name']) ?>">
                </div>
                <div class="body">
                    <h3 class="name"><?= htmlspecialchars((string)$product['display_name']) ?></h3>
                    <div class="price-row">
                        <span class="price">LKR <?= number_format((float)$product['price'], 2) ?></span>
                    </div>
                    <div class="deal-row"><span class="price-old">LKR <?= number_format((float)$product['price'] * 1.12, 2) ?></span><span class="price-off">Save 12%</span></div>
                    <div class="rating"><span class="stars">★★★★★</span><span>4.9 rated</span><span>120+ sold</span></div>
                    <div class="stock <?= (float)$product['stock_qty'] > 0 ? 'ok' : 'no' ?>"><?= (float)$product['stock_qty'] > 0 ? ((float)$product['stock_qty'] <= 7 ? 'Only ' . (int)$product['stock_qty'] . ' left in stock' : 'In stock') : 'Out of stock' ?></div>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    </section>
</main>

<script>
const input = document.getElementById('search');
const suggestions = document.getElementById('suggestions');
const grid = document.getElementById('grid');
const resultMeta = document.getElementById('resultMeta');
const cartButton = document.getElementById('cartButton');
const initialProducts = <?= $initialProductsJson ?: '[]' ?>;
let suggestTimer = null;
let searchTimer = null;
let requestId = 0;

cartButton.addEventListener('click', (event) => {
    event.preventDefault();
    alert('Cart module is coming next.');
});

updateMeta(initialProducts.length, 'Showing latest products');

input.addEventListener('input', () => {
    const q = input.value.trim();
    clearTimeout(suggestTimer);
    clearTimeout(searchTimer);

    if (q.length < 1) {
        suggestions.style.display = 'none';
        renderProducts(initialProducts);
        updateMeta(initialProducts.length, 'Showing latest products');
        return;
    }

    suggestTimer = setTimeout(async () => {
        const res = await fetch(`api/search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.success) {
            suggestions.style.display = 'none';
            return;
        }
        renderSuggestions(data.suggestions || []);
    }, 140);

    searchTimer = setTimeout(async () => {
        const current = ++requestId;
        const res = await fetch(`api/products.php?q=${encodeURIComponent(q)}&per_page=60`);
        const data = await res.json();
        if (!data.success) {
            if (current !== requestId) {
                return;
            }
            grid.innerHTML = `<div class="empty">Search is temporarily unavailable. Please try again.</div>`;
            return;
        }
        if (current !== requestId) {
            return;
        }
        renderProducts(data.products || []);
        updateMeta((data.products || []).length, `Results for "${q}"`);
    }, 180);
});

input.addEventListener('keydown', async (event) => {
    if (event.key !== 'Enter') return;
    const q = input.value.trim();
    const res = await fetch(`api/products.php?q=${encodeURIComponent(q)}&per_page=48`);
    const data = await res.json();
    if (!data.success) {
        grid.innerHTML = `<div class="empty">Search is temporarily unavailable. Please try again.</div>`;
        return;
    }
    const products = data.products || [];
    renderProducts(products);
    updateMeta(products.length, `Results for "${q}"`);
    suggestions.style.display = 'none';
});

document.addEventListener('click', (event) => {
    if (event.target !== input && !suggestions.contains(event.target)) {
        suggestions.style.display = 'none';
    }
});

function renderSuggestions(items) {
    if (!items.length) {
        suggestions.style.display = 'none';
        return;
    }

    suggestions.innerHTML = items.map(item => `
        <div class="suggestion" onclick="window.location='product.php?id=${item.erp_product_id}'">
            ${escapeHtml(item.name)} - LKR ${Number(item.price).toFixed(2)}
        </div>
    `).join('');
    suggestions.style.display = 'block';
}

function renderProducts(items) {
    if (!items.length) {
        grid.innerHTML = `<div class="empty">No products found for this search. Try another keyword.</div>`;
        return;
    }

    grid.innerHTML = items.map(item => `
        <a class="card" href="product.php?id=${item.erp_product_id}">
            <div class="media">
                <div class="card-badges">
                    ${renderTopBadge(item)}
                </div>
                <img class="img" src="${item.image_url || 'assets/images/brand/logo-watercolorlk.png'}" alt="${escapeHtml(item.display_name)}">
            </div>
            <div class="body">
                <h3 class="name">${escapeHtml(item.display_name)}</h3>
                <div class="price-row">
                    <span class="price">LKR ${Number(item.price).toFixed(2)}</span>
                </div>
                <div class="deal-row"><span class="price-old">LKR ${(Number(item.price) * 1.12).toFixed(2)}</span><span class="price-off">Save 12%</span></div>
                <div class="rating"><span class="stars">★★★★★</span><span>4.9 rated</span><span>120+ sold</span></div>
                <div class="stock ${Number(item.stock_qty) > 0 ? 'ok' : 'no'}">${renderStockText(item)}</div>
            </div>
        </a>
    `).join('');
}

function renderTopBadge(item) {
    const qty = Number(item.stock_qty || 0);
    if (qty <= 0) {
        return '<span class="badge sale">Out of stock</span>';
    }
    if (qty <= 7) {
        return `<span class="badge low">Only ${Math.floor(qty)} left</span>`;
    }
    if (item.badge) {
        return `<span class="badge">${escapeHtml(item.badge)}</span>`;
    }
    return '<span class="badge">Featured</span>';
}

function renderStockText(item) {
    const qty = Number(item.stock_qty || 0);
    if (qty <= 0) {
        return 'Out of stock';
    }
    if (qty <= 7) {
        return `Only ${Math.floor(qty)} left in stock`;
    }
    return 'In stock';
}

function updateMeta(count, label) {
    resultMeta.textContent = `${label} (${count})`;
}

function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
</script>
</body>
</html>
