<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$repo = new ProductRepository(appDb());
$products = $repo->listProducts('', 24, 0);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Watercolor.LK Shop</title>
    <link rel="icon" type="image/webp" href="assets/images/brand/favicon-watercolorlk.webp">
    <style>
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; background: #f6f4ef; color: #10233f; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 20px; }
        .bar { position: sticky; top: 0; background: #f6f4ef; padding: 14px 0; z-index: 9; }
        .search { width: 100%; box-sizing: border-box; border: 1px solid #d4c7aa; border-radius: 12px; padding: 12px 14px; font-size: 16px; }
        .suggestions { margin-top: 6px; border: 1px solid #d4c7aa; border-radius: 12px; background: #fff; display: none; }
        .suggestion { padding: 10px 12px; border-bottom: 1px solid #f1ecdf; cursor: pointer; }
        .suggestion:last-child { border-bottom: 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-top: 16px; }
        .card { background: #fff; border-radius: 14px; overflow: hidden; border: 1px solid #ece6d8; }
        .img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; background: #f8f8f8; }
        .body { padding: 12px; }
        .name { margin: 0 0 6px; font-size: 15px; line-height: 1.3; }
        .price { font-weight: 700; color: #7a4a00; }
        .stock { font-size: 12px; color: #365e35; }
        .badge { display: inline-block; font-size: 11px; background: #10233f; color: #fff; padding: 3px 8px; border-radius: 999px; margin-bottom: 6px; }
        @media (max-width: 720px) {
            .wrap { padding: 14px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="bar">
        <img src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK" style="height:42px">
        <input id="search" class="search" placeholder="Search products instantly..." autocomplete="off">
        <div id="suggestions" class="suggestions"></div>
    </div>

    <div id="grid" class="grid">
        <?php foreach ($products as $product): ?>
            <a class="card" href="product.php?id=<?= (int)$product['erp_product_id'] ?>" style="text-decoration:none;color:inherit">
                <img class="img" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['display_name']) ?>">
                <div class="body">
                    <?php if ($product['badge'] !== ''): ?>
                        <span class="badge"><?= htmlspecialchars((string)$product['badge']) ?></span>
                    <?php endif; ?>
                    <h3 class="name"><?= htmlspecialchars((string)$product['display_name']) ?></h3>
                    <div class="price">LKR <?= number_format((float)$product['price'], 2) ?></div>
                    <div class="stock"><?= (float)$product['stock_qty'] > 0 ? 'In stock' : 'Out of stock' ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<script>
const input = document.getElementById('search');
const suggestions = document.getElementById('suggestions');
const grid = document.getElementById('grid');
let timer = null;

input.addEventListener('input', () => {
    const q = input.value.trim();
    clearTimeout(timer);

    if (q.length < 2) {
        suggestions.style.display = 'none';
        return;
    }

    timer = setTimeout(async () => {
        const res = await fetch(`api/search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        renderSuggestions(data.suggestions || []);
    }, 120);
});

input.addEventListener('keydown', async (event) => {
    if (event.key !== 'Enter') return;
    const q = input.value.trim();
    const res = await fetch(`api/products.php?q=${encodeURIComponent(q)}&per_page=48`);
    const data = await res.json();
    renderProducts(data.products || []);
    suggestions.style.display = 'none';
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
    grid.innerHTML = items.map(item => `
        <a class="card" href="product.php?id=${item.erp_product_id}" style="text-decoration:none;color:inherit">
            <img class="img" src="${item.image_url || 'assets/images/brand/logo-watercolorlk.png'}" alt="${escapeHtml(item.display_name)}">
            <div class="body">
                ${item.badge ? `<span class="badge">${escapeHtml(item.badge)}</span>` : ''}
                <h3 class="name">${escapeHtml(item.display_name)}</h3>
                <div class="price">LKR ${Number(item.price).toFixed(2)}</div>
                <div class="stock">${Number(item.stock_qty) > 0 ? 'In stock' : 'Out of stock'}</div>
            </div>
        </a>
    `).join('');
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
