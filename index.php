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
            --line: #e7ddd2;
            --text: #1a1a1a;
            --muted: #6b6b6b;
            --amber: #e8760a;
            --danger: #c0392b;
            --success: #2d7a4f;
            --shadow-sm: 0 10px 24px rgba(17, 31, 56, 0.08);
            --shadow-lg: 0 24px 60px rgba(17, 31, 56, 0.14);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(196, 112, 90, .15), transparent 32%),
                radial-gradient(circle at top right, rgba(232, 118, 10, .08), transparent 28%),
                linear-gradient(180deg, #fffdfa 0%, var(--paper) 42%, #f5eee6 100%);
            font: 400 17px/1.7 'Source Sans 3', 'Segoe UI', sans-serif;
        }
        .wrap { width: min(calc(100% - 32px), 1240px); margin: 0 auto; }
        .header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(16, 32, 58, .88);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, .15);
        }
        .header-inner {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 0;
        }
        .logo { height: 38px; width: auto; display: block; }
        .hero { padding: 34px 0 12px; }
        .hero-grid {
            display: grid;
            grid-template-columns: 1.25fr .75fr;
            gap: 18px;
            align-items: stretch;
        }
        .hero-card,
        .hero-side {
            background: rgba(255, 255, 255, .88);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            padding: 22px;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 13px;
            border-radius: 999px;
            background: rgba(27, 45, 79, .08);
            color: var(--brand-navy);
            font: 700 12px/1 'Montserrat', sans-serif;
            letter-spacing: .11em;
            text-transform: uppercase;
        }
        .eyebrow::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--amber); }
        .title {
            margin: 12px 0 8px;
            color: var(--brand-navy-deep);
            font: 700 clamp(2rem, 4vw, 3.1rem)/1.05 'Playfair Display', serif;
        }
        .lead { color: #3d475c; margin: 0; }
        .search-wrap { position: relative; margin-top: 16px; }
        .search {
            width: 100%;
            border: 2px solid #d9ccbd;
            border-radius: 14px;
            padding: 14px;
            font: 600 16px/1.2 'Source Sans 3', sans-serif;
            color: #1d2330;
            outline: none;
            background: #fff;
        }
        .search:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(232, 118, 10, .14); }
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
        .meta-box { font: 600 14px/1.4 'Montserrat', sans-serif; color: #425170; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(225px, 1fr));
            gap: 18px;
            padding: 18px 0 38px;
        }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow-sm);
            transition: transform .16s ease, box-shadow .16s ease;
        }
        .card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .img {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            background: #f7f7f7;
            display: block;
        }
        .body { padding: 14px; }
        .name {
            margin: 0 0 7px;
            color: #22314d;
            font: 700 17px/1.24 'Source Sans 3', sans-serif;
            min-height: 42px;
        }
        .price { color: var(--brand-navy); font: 700 22px/1.05 'Source Sans 3', sans-serif; }
        .stock { margin-top: 4px; font: 600 13px/1.2 'Montserrat', sans-serif; }
        .stock.ok { color: var(--success); }
        .stock.no { color: var(--danger); }
        .badge {
            display: inline-block;
            margin-bottom: 8px;
            font: 700 11px/1 'Montserrat', sans-serif;
            background: rgba(27, 45, 79, .1);
            color: var(--brand-navy);
            border-radius: 999px;
            padding: 6px 9px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
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
            .hero { padding-top: 20px; }
            .hero-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="header">
    <div class="wrap header-inner">
        <img class="logo" src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK">
    </div>
</header>

<main class="wrap">
    <section class="hero">
        <div class="hero-grid">
            <div class="hero-card">
                <span class="eyebrow">Live Catalog</span>
                <h1 class="title">Find the right art material instantly.</h1>
                <p class="lead">Fast product search, synced stock, and curated quality for watercolor artists in Sri Lanka.</p>
                <div class="search-wrap">
                    <input id="search" class="search" placeholder="Type to search products, SKU, categories..." autocomplete="off">
                    <div id="suggestions" class="suggestions"></div>
                </div>
            </div>
            <aside class="hero-side">
                <div class="meta-box" id="resultMeta">Showing latest products</div>
            </aside>
        </div>
    </section>

    <div id="grid" class="grid">
        <?php foreach ($products as $product): ?>
            <a class="card" href="product.php?id=<?= (int)$product['erp_product_id'] ?>">
                <img class="img" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['display_name']) ?>">
                <div class="body">
                    <?php if ($product['badge'] !== ''): ?>
                        <span class="badge"><?= htmlspecialchars((string)$product['badge']) ?></span>
                    <?php endif; ?>
                    <h3 class="name"><?= htmlspecialchars((string)$product['display_name']) ?></h3>
                    <div class="price">LKR <?= number_format((float)$product['price'], 2) ?></div>
                    <div class="stock <?= (float)$product['stock_qty'] > 0 ? 'ok' : 'no' ?>"><?= (float)$product['stock_qty'] > 0 ? 'In stock' : 'Out of stock' ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</main>

<script>
const input = document.getElementById('search');
const suggestions = document.getElementById('suggestions');
const grid = document.getElementById('grid');
const resultMeta = document.getElementById('resultMeta');
const initialProducts = <?= $initialProductsJson ?: '[]' ?>;
let suggestTimer = null;
let searchTimer = null;
let requestId = 0;

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
        renderSuggestions(data.suggestions || []);
    }, 140);

    searchTimer = setTimeout(async () => {
        const current = ++requestId;
        const res = await fetch(`api/products.php?q=${encodeURIComponent(q)}&per_page=60`);
        const data = await res.json();
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
            <img class="img" src="${item.image_url || 'assets/images/brand/logo-watercolorlk.png'}" alt="${escapeHtml(item.display_name)}">
            <div class="body">
                ${item.badge ? `<span class="badge">${escapeHtml(item.badge)}</span>` : ''}
                <h3 class="name">${escapeHtml(item.display_name)}</h3>
                <div class="price">LKR ${Number(item.price).toFixed(2)}</div>
                <div class="stock ${Number(item.stock_qty) > 0 ? 'ok' : 'no'}">${Number(item.stock_qty) > 0 ? 'In stock' : 'Out of stock'}</div>
            </div>
        </a>
    `).join('');
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
