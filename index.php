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
            border-bottom: 1px solid rgba(255,255,255,.18);
            backdrop-filter: blur(16px);
            background: rgba(16,32,58,.86);
            box-shadow: 0 10px 30px rgba(0,0,0,.12);
        }
        .header-inner {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 14px 0;
        }
        .brand { display: flex; align-items: center; gap: 14px; min-width: 240px; text-decoration: none; }
        .logo { height: 38px; width: auto; display: block; }
        .brand-copy strong { display: block; color: #fff; font: 700 1.05rem/1 'Montserrat', sans-serif; }
        .brand-copy span { display: block; color: rgba(255,255,255,.68); font: 500 .76rem/1.3 'Montserrat', sans-serif; letter-spacing: .04em; }
        .header-search { flex: 1; position: relative; }
        .header-search input {
            width: 100%;
            border: none;
            outline: none;
            border-radius: 999px;
            padding: 14px 18px 14px 48px;
            background: rgba(255,255,255,.12);
            color: #fff;
            font: 500 .96rem/1.2 'Source Sans 3', sans-serif;
        }
        .header-search input::placeholder { color: rgba(255,255,255,.64); }
        .header-search span { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,.74); }
        .header-links { display: flex; align-items: center; gap: 14px; color: rgba(255,255,255,.8); font: 600 .86rem/1 'Montserrat', sans-serif; }
        .header-links .pill { padding: 10px 15px; border-radius: 999px; background: rgba(255,255,255,.12); color: #fff; text-decoration: none; }

        .hero { padding: 40px 0 24px; }
        .hero-grid {
            display: grid; grid-template-columns: 1.18fr .82fr; gap: 24px; align-items: stretch;
        }
        .hero-card,
        .panel {
            background: rgba(255,255,255,.84);
            border: 1px solid rgba(255,255,255,.72);
            box-shadow: var(--shadow-sm);
        }
        .hero-card {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 34px;
        }
        .hero-card::after {
            content: '';
            position: absolute;
            inset: auto -8% -25% auto;
            width: 340px;
            height: 340px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(232,118,10,.18), rgba(196,112,90,.08), transparent 68%);
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(27,45,79,.08);
            color: var(--brand-navy);
            font: 700 12px/1 'Montserrat', sans-serif;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .eyebrow::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--amber); }
        .title {
            margin: 16px 0 10px;
            color: var(--brand-navy-deep);
            font: 700 clamp(2.3rem, 5vw, 4rem)/1.06 'Playfair Display', serif;
            max-width: 13ch;
        }
        .lead { color: #3d475c; margin: 0; max-width: 58ch; }
        .hero-side { display: grid; gap: 16px; }
        .panel { border-radius: 24px; padding: 24px; }
        .panel h3 { margin: 0 0 10px; color: var(--brand-navy); font: 700 1.2rem/1.2 'Montserrat', sans-serif; }
        .bullet-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; color: #38445a; }
        .bullet-list li { display: flex; gap: 9px; align-items: flex-start; }
        .bullet-list li::before { content: '+'; color: var(--amber); font-weight: 700; line-height: 1; transform: translateY(3px); }
        .search-wrap { position: relative; margin-top: 18px; z-index: 2; }
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
        .search:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(232,118,10,.14); }
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
        .metrics { margin-top: 18px; display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; }
        .metric { padding: 14px; border-radius: 16px; border: 1px solid var(--line); background: #fff; }
        .metric strong { display: block; color: var(--brand-navy); font: 700 1.2rem/1 'Montserrat', sans-serif; }
        .metric span { display: block; margin-top: 4px; color: var(--muted); font-size: .8rem; }

        .section { padding: 4px 0 20px; }
        .section-head { display: flex; justify-content: space-between; align-items: end; gap: 16px; margin-bottom: 16px; }
        .section-head h2 { margin: 8px 0 0; color: var(--brand-navy-deep); font: 700 clamp(1.7rem, 2.8vw, 2.4rem)/1.1 'Playfair Display', serif; }
        .section-head p { margin: 0; max-width: 60ch; color: #495164; }

        .category-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 14px; }
        .category-card {
            background: rgba(255,255,255,.9);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 18px;
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        .category-icon { width: 82px; height: 82px; object-fit: contain; margin: 0 auto 12px; }
        .category-card strong { display: block; color: var(--brand-navy); font: 700 1rem/1.1 'Montserrat', sans-serif; }
        .category-card span { display: block; margin-top: 6px; color: var(--muted); font-size: .86rem; }

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
        .name {
            margin: 0 0 7px;
            color: #22314d;
            font: 700 17px/1.24 'Source Sans 3', sans-serif;
            min-height: 42px;
        }
        .sku { color: var(--muted); font-size: .84rem; }
        .price-row { display: flex; align-items: end; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .price { color: var(--brand-navy); font: 700 1.55rem/1 'Source Sans 3', sans-serif; }
        .price-old { color: #9a8e81; text-decoration: line-through; font-size: .95rem; }
        .price-off { color: var(--danger); font: 700 .8rem/1 'Montserrat', sans-serif; }
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
            .header-inner { flex-wrap: wrap; }
            .header-search, .brand { min-width: 100%; }
            .category-grid { grid-template-columns: 1fr 1fr; }
            .section-head { flex-direction: column; align-items: start; }
            .metrics { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="index.php">
            <img class="logo" src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK">
            <span class="brand-copy">
                <strong>Watercolor.LK</strong>
                <span>Premium art supplies for fast conversion</span>
            </span>
        </a>
        <div class="header-search">
            <span>Search</span>
            <input id="search" class="search" placeholder="Search brushes, sketchbooks, papers, SKU..." autocomplete="off">
            <div id="suggestions" class="suggestions"></div>
        </div>
        <nav class="header-links">
            <a href="#cards">Cards</a>
            <a class="pill" href="design-system.html">Design System</a>
        </nav>
    </div>
</header>

<main class="wrap">
    <section class="hero">
        <div class="hero-grid">
            <div class="hero-card">
                <span class="eyebrow">Live Catalog</span>
                <h1 class="title">Premium, conversion-first watercolor shopping.</h1>
                <p class="lead">Fast search, stock-aware merchandising, and trust-led presentation built for real buying behavior.</p>
                <div class="metrics">
                    <div class="metric"><strong>17px</strong><span>Readable product-first body text</span></div>
                    <div class="metric"><strong>2-3 clicks</strong><span>Target depth to reach checkout intent</span></div>
                    <div class="metric"><strong>Local sync</strong><span>Stable catalog even when ERP is slow</span></div>
                </div>
            </div>
            <div class="hero-side">
                <div class="panel">
                    <h3>What this page does</h3>
                    <ul class="bullet-list">
                        <li>Instant search while typing</li>
                        <li>Trust-first card hierarchy for conversion</li>
                        <li>Stock urgency without visual noise</li>
                        <li>Quick jump into product-level ordering</li>
                    </ul>
                </div>
                <div class="panel">
                    <h3 class="meta-box" id="resultMeta">Showing latest products</h3>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div>
                <span class="eyebrow">Categories</span>
                <h2>Mascot-led category discovery</h2>
            </div>
            <p>Familiar mascot visuals support memory recall and faster browsing for repeat shoppers.</p>
        </div>
        <div class="category-grid">
            <article class="category-card"><img class="category-icon" src="assets/images/mascots/watercolor-brushes-1.webp" alt="Brushes"><strong>Brushes</strong><span>Travel, wash, mop, and detail</span></article>
            <article class="category-card"><img class="category-icon" src="assets/images/mascots/watercolor-papers.webp" alt="Papers"><strong>Papers</strong><span>Blocks, sheets, cotton surfaces</span></article>
            <article class="category-card"><img class="category-icon" src="assets/images/mascots/watercolor-paints.webp" alt="Paints"><strong>Paints</strong><span>Artist-grade sets and tubes</span></article>
            <article class="category-card"><img class="category-icon" src="assets/images/mascots/watercolor-sktechbooks.webp" alt="Sketchbooks"><strong>Sketchbooks</strong><span>Premium books for practice</span></article>
        </div>
    </section>

    <section class="section" id="cards">
        <div class="section-head">
            <div>
                <span class="eyebrow">Cards</span>
                <h2>High-clarity conversion cards</h2>
            </div>
            <p>Each card keeps the order of influence: badge, image, title, price contrast, proof, then urgency.</p>
        </div>
        <div id="grid" class="grid">
        <?php foreach ($products as $product): ?>
            <a class="card" href="product.php?id=<?= (int)$product['erp_product_id'] ?>">
                <div class="media">
                    <div class="card-badges">
                        <?php if ($product['badge'] !== ''): ?>
                            <span class="badge"><?= htmlspecialchars((string)$product['badge']) ?></span>
                        <?php endif; ?>
                        <?php if ((float)$product['stock_qty'] > 0): ?>
                            <span class="badge stock">In Stock</span>
                        <?php else: ?>
                            <span class="badge sale">Out</span>
                        <?php endif; ?>
                    </div>
                    <img class="img" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['display_name']) ?>">
                </div>
                <div class="body">
                    <h3 class="name"><?= htmlspecialchars((string)$product['display_name']) ?></h3>
                    <div class="sku">SKU: <?= htmlspecialchars((string)$product['sku']) ?></div>
                    <div class="price-row">
                        <span class="price">LKR <?= number_format((float)$product['price'], 2) ?></span>
                        <span class="price-old">LKR <?= number_format((float)$product['price'] * 1.12, 2) ?></span>
                        <span class="price-off">Save 12%</span>
                    </div>
                    <div class="rating"><span class="stars">★★★★★</span><span>4.9 rated</span><span>120+ sold</span></div>
                    <div class="stock <?= (float)$product['stock_qty'] > 0 ? 'ok' : 'no' ?>"><?= (float)$product['stock_qty'] > 0 ? 'In stock' : 'Out of stock' ?></div>
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
            <div class="media">
                <div class="card-badges">
                    ${item.badge ? `<span class="badge">${escapeHtml(item.badge)}</span>` : ''}
                    ${Number(item.stock_qty) > 0 ? `<span class="badge stock">In Stock</span>` : `<span class="badge sale">Out</span>`}
                </div>
                <img class="img" src="${item.image_url || 'assets/images/brand/logo-watercolorlk.png'}" alt="${escapeHtml(item.display_name)}">
            </div>
            <div class="body">
                <h3 class="name">${escapeHtml(item.display_name)}</h3>
                <div class="sku">SKU: ${escapeHtml(item.sku || '')}</div>
                <div class="price-row">
                    <span class="price">LKR ${Number(item.price).toFixed(2)}</span>
                    <span class="price-old">LKR ${(Number(item.price) * 1.12).toFixed(2)}</span>
                    <span class="price-off">Save 12%</span>
                </div>
                <div class="rating"><span class="stars">★★★★★</span><span>4.9 rated</span><span>120+ sold</span></div>
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
