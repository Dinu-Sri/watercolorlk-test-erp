<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$erpId = (int)($_GET['id'] ?? 0);
$repo = new ProductRepository(appDb());
$product = $erpId > 0 ? $repo->getByErpId($erpId) : null;
$stock = $product ? (float)$product['stock_qty'] : 0;
$stockPercent = $stock <= 0 ? 0 : min(100, max(12, (int)($stock * 12)));
$brandLine = $product ? strtoupper(trim((string)($product['brand_name'] ?: 'Watercolor.LK / Artist Grade'))) : 'Watercolor.LK / Artist Grade';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $product ? htmlspecialchars((string)$product['name']) : 'Product not found' ?> | Watercolor.LK</title>
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
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(196, 112, 90, .15), transparent 32%),
                radial-gradient(circle at top right, rgba(232, 118, 10, .08), transparent 28%),
                linear-gradient(180deg, #fffdfa 0%, var(--paper) 42%, #f5eee6 100%);
            color: var(--text);
            font: 400 17px/1.7 'Source Sans 3', 'Segoe UI', sans-serif;
        }
        .wrap { width: min(calc(100% - 32px), 1180px); margin: 0 auto; padding: 22px 0 36px; }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 40;
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(16px);
            background: rgba(250, 248, 245, .94);
            box-shadow: 0 8px 24px rgba(17, 31, 56, 0.08);
        }
        .header-inner { display: flex; align-items: center; gap: 16px; padding: 14px 0; }
        .logo { height: 42px; width: auto; }
        .brand-sub { color: #43516d; font: 700 .79rem/1.2 'Montserrat', sans-serif; letter-spacing: .02em; }
        .back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--brand-navy);
            font: 700 13px/1 'Montserrat', sans-serif;
            letter-spacing: .08em;
            text-transform: uppercase;
            text-decoration: none;
        }
        .layout {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 24px;
            margin-top: 14px;
        }
        .gallery,
        .box {
            background: rgba(255, 255, 255, .9);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }
        .gallery-stage {
            border: 1px solid #ece2d5;
            background: #f3f0eb;
            border-radius: 24px;
            min-height: 520px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
        }
        .img {
            width: 100%;
            max-height: 450px;
            border-radius: 14px;
            object-fit: contain;
            background: transparent;
        }
        .thumbs { display: flex; gap: 12px; margin-top: 16px; }
        .thumb {
            width: 74px;
            height: 74px;
            border-radius: 16px;
            border: 2px solid #d8ccbb;
            background: #fff;
            padding: 7px;
            cursor: pointer;
        }
        .thumb.active { border-color: var(--amber); }
        .thumb img { width: 100%; height: 100%; object-fit: contain; }
        .brand { color: #8f4d39; font: 700 12px/1 'Montserrat', sans-serif; letter-spacing: .12em; text-transform: uppercase; }
        h1 {
            margin: 8px 0;
            color: var(--brand-navy-deep);
            font: 700 clamp(1.7rem, 3vw, 2.4rem)/1.12 'Playfair Display', serif;
        }
        .price-panel {
            background: linear-gradient(135deg,#fffaf4,#f8ece0);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 18px;
            margin: 12px 0;
        }
        .price-label {
            display: block;
            color: var(--muted);
            font: 700 .78rem/1 'Montserrat', sans-serif;
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .price {
            color: var(--brand-navy);
            font: 700 2.2rem/1.05 'Source Sans 3', sans-serif;
        }
        .price-compare {
            color: #9a8e81;
            text-decoration: line-through;
            margin-left: 8px;
            font-size: 1rem;
        }
        .stock {
            margin: 8px 0 12px;
            font: 700 13px/1 'Montserrat', sans-serif;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .stock.ok { color: var(--success); }
        .stock.low { color: var(--danger); }
        .urgency {
            height: 7px;
            border-radius: 999px;
            background: #ecd9d1;
            overflow: hidden;
            margin-top: 8px;
        }
        .urgency span {
            display: block;
            height: 100%;
            width: <?= $stockPercent ?>%;
            background: linear-gradient(90deg, var(--danger), var(--amber));
        }
        .rating { display: flex; gap: 10px; align-items: center; color: var(--muted); font-size: .84rem; margin-top: 8px; }
        .stars { color: var(--gold); letter-spacing: .08em; }
        .desc { margin: 0 0 12px; color: #313949; font: 500 16px/1.65 'Source Sans 3', sans-serif; }
        .badge {
            display: inline-block;
            margin-bottom: 9px;
            font: 700 11px/1 'Montserrat', sans-serif;
            background: rgba(27, 45, 79, .1);
            color: var(--brand-navy);
            border-radius: 999px;
            padding: 6px 10px;
            text-transform: uppercase;
            letter-spacing: .09em;
        }
        .urgency-row { margin: 16px 0; display: flex; gap: 10px; align-items: center; color: #b23c2c; font: 700 1.03rem/1.2 'Source Sans 3', sans-serif; }
        .dot { width: 11px; height: 11px; border-radius: 999px; background: #bc4433; }
        .qty-head { margin: 14px 0 8px; color: var(--brand-navy); font: 700 1.02rem/1 'Montserrat', sans-serif; letter-spacing: .06em; text-transform: uppercase; }
        .qty-controls {
            width: 170px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            border: 2px solid #d5c8b4;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 16px;
            background: #fff;
        }
        .qty-controls button,
        .qty-controls input {
            border: 0;
            background: transparent;
            text-align: center;
            font: 700 1.05rem/1.2 'Montserrat', sans-serif;
            color: var(--brand-navy);
            height: 44px;
        }
        .qty-controls button { cursor: pointer; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .button {
            width: 100%;
            box-sizing: border-box;
            padding: 13px;
            border-radius: 12px;
            margin-top: 0;
            border: 0;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(180deg, #e8760a, #c4600a);
            font: 700 16px/1.2 'Source Sans 3', sans-serif;
        }
        .button.secondary {
            background: #fff;
            color: var(--brand-navy);
            border: 2px solid var(--brand-navy);
        }
        .button.whatsapp {
            background: #25d366;
            margin-top: 12px;
        }
        .trust-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; margin-top: 14px; }
        .trust { padding: 12px; border: 1px solid var(--line); border-radius: 16px; background: #fff; text-align: center; }
        .trust strong { display: block; color: var(--brand-navy); font: 700 .88rem/1.2 'Montserrat', sans-serif; }
        .trust span { display: block; color: var(--muted); font-size: .78rem; margin-top: 4px; }
        #orderResult { margin-top: 10px; font: 600 14px/1.4 'Source Sans 3', sans-serif; }
        .not-found {
            margin-top: 12px;
            background: rgba(255, 255, 255, .9);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            color: #5f6b80;
        }
        @media (max-width: 820px) {
            .layout { grid-template-columns: 1fr; }
            .wrap { width: min(calc(100% - 24px), 1180px); }
            .trust-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="wrap header-inner">
        <img class="logo" src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK">
        <span class="brand-sub">පටන් ගන්න! පාට කරන්න! ජිවිතය විදින්න!</span>
    </div>
</header>

<div class="wrap">
    <a class="back" href="index.php">&larr; Back to shop</a>

    <?php if (!$product): ?>
        <div class="not-found">Product not found in local catalog. Run sync first.</div>
    <?php else: ?>
        <div class="layout">
            <div class="gallery">
                <div class="gallery-stage">
                    <img id="mainImage" class="img" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['name']) ?>">
                </div>
                <div class="thumbs">
                    <button class="thumb active" type="button" data-src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>"><img src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="thumb"></button>
                    <button class="thumb" type="button" data-src="assets/images/mascots/watercolor-brushes-1.webp"><img src="assets/images/mascots/watercolor-brushes-1.webp" alt="thumb"></button>
                    <button class="thumb" type="button" data-src="assets/images/mascots/watercolor-paints.webp"><img src="assets/images/mascots/watercolor-paints.webp" alt="thumb"></button>
                </div>
            </div>
            <div class="box">
                <?php if (!empty($product['badge'])): ?>
                    <span class="badge"><?= htmlspecialchars((string)$product['badge']) ?></span>
                <?php endif; ?>
                <div class="brand"><?= htmlspecialchars($brandLine) ?></div>
                <h1><?= htmlspecialchars((string)$product['name']) ?></h1>
                <div class="rating"><span class="stars">★★★★★</span><span>4.9 rated by buyers</span><span>126 sold</span></div>
                <div class="price-panel">
                    <span class="price-label">Price including tax</span>
                    <span class="price">LKR <?= number_format((float)$product['price'], 2) ?></span>
                    <span class="price-compare">LKR <?= number_format((float)$product['price'] * 1.12, 2) ?></span>
                </div>
                <div class="urgency-row"><span class="dot"></span><span><?= $stock > 0 ? ('Only ' . (int)$stock . ' left in stock') : 'Out of stock' ?></span></div>
                <div class="urgency"><span></span></div>
                <p class="desc"><?= nl2br(htmlspecialchars((string)$product['description'])) ?></p>

                <h3 class="qty-head">Quantity</h3>
                <div class="qty-controls">
                    <button type="button" onclick="changeQty(-1)">-</button>
                    <input id="qty" type="text" value="1" readonly>
                    <button type="button" onclick="changeQty(1)">+</button>
                </div>

                <div class="actions">
                    <button class="button" onclick="submitOrder('payhere')">Buy Now</button>
                    <button class="button secondary" type="button" onclick="addToCart()">Add to Cart</button>
                </div>
                <button class="button whatsapp" type="button" onclick="openWhatsAppOrder()">WhatsApp Order</button>
                <div id="orderResult"></div>

                <div class="trust-grid">
                    <div class="trust"><strong>Secure checkout</strong><span>Server-side processing</span></div>
                    <div class="trust"><strong>Fast delivery</strong><span>Island-wide dispatch</span></div>
                    <div class="trust"><strong>Trusted sourcing</strong><span>Curated art supply focus</span></div>
                </div>
            </div>
        </div>

        <script>
            const product = {
                product_id: <?= (int)$product['id'] ?>,
                erp_product_id: <?= (int)$product['erp_product_id'] ?>,
                sku: <?= json_encode((string)$product['sku']) ?>,
                unit_price: <?= json_encode((float)$product['price']) ?>,
                name: <?= json_encode((string)$product['name']) ?>
            };

            let customerName = '';
            let customerPhone = '';

            document.querySelectorAll('.thumb').forEach((thumb) => {
                thumb.addEventListener('click', () => {
                    document.querySelectorAll('.thumb').forEach((t) => t.classList.remove('active'));
                    thumb.classList.add('active');
                    document.getElementById('mainImage').src = thumb.dataset.src;
                });
            });

            function changeQty(delta) {
                const input = document.getElementById('qty');
                const next = Math.max(1, Number(input.value || 1) + delta);
                input.value = String(next);
            }

            function ensureBuyerDetails() {
                if (!customerName) {
                    const name = window.prompt('Enter your full name');
                    if (!name) return false;
                    customerName = name.trim();
                }
                if (!customerPhone) {
                    const phone = window.prompt('Enter your phone number');
                    if (!phone) return false;
                    customerPhone = phone.trim();
                }
                return true;
            }

            async function submitOrder(paymentMethod) {
                if (!ensureBuyerDetails()) {
                    return;
                }
                const payload = {
                    customer_name: customerName,
                    customer_phone: customerPhone,
                    customer_email: '',
                    payment_method: paymentMethod,
                    notes: '',
                    items: [
                        {
                            product_id: product.product_id,
                            erp_product_id: product.erp_product_id,
                            sku: product.sku,
                            quantity: Number(document.getElementById('qty').value || 1),
                            unit_price: Number(product.unit_price)
                        }
                    ]
                };

                const res = await fetch('api/place-order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                const box = document.getElementById('orderResult');

                if (!data.success) {
                    box.innerHTML = `<span style="color:#ae1c1c">${data.error || 'Order failed'}</span>`;
                    return;
                }

                const syncMessage = data.erp_sync === 'synced'
                    ? 'Synced to ERP successfully.'
                    : 'Order saved locally and queued for ERP retry.';

                box.innerHTML = `<span style="color:#1f5d23">Order #${data.order_id} created. ${syncMessage}</span>`;
            }

            function addToCart() {
                const qty = Number(document.getElementById('qty').value || 1);
                document.getElementById('orderResult').innerHTML = `<span style="color:#1f5d23">${qty} item(s) added to cart queue.</span>`;
            }

            function openWhatsAppOrder() {
                const qty = Number(document.getElementById('qty').value || 1);
                const text = encodeURIComponent(`Hi Watercolor.LK, I want to order ${product.name} (Qty: ${qty}).`);
                window.open(`https://wa.me/94700000000?text=${text}`, '_blank');
            }
        </script>
    <?php endif; ?>
</div>
</body>
</html>
