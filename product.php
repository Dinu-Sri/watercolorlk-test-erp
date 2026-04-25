<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$erpId = (int)($_GET['id'] ?? 0);
$repo = new ProductRepository(appDb());
$product = $erpId > 0 ? $repo->getByErpId($erpId) : null;
$stock = $product ? (float)$product['stock_qty'] : 0;
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
            gap: 20px;
            margin-top: 14px;
        }
        .gallery,
        .box {
            background: rgba(255, 255, 255, .9);
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 18px;
            box-shadow: var(--shadow-sm);
        }
        .img {
            width: 100%;
            border-radius: 18px;
            border: 1px solid #e8decd;
            background: #fff;
            aspect-ratio: 1 / 1;
            object-fit: cover;
        }
        .brand { color: #8f4d39; font: 700 12px/1 'Montserrat', sans-serif; letter-spacing: .12em; text-transform: uppercase; }
        h1 {
            margin: 8px 0;
            color: var(--brand-navy-deep);
            font: 700 clamp(1.7rem, 3vw, 2.4rem)/1.12 'Playfair Display', serif;
        }
        .price {
            color: var(--brand-navy);
            font: 700 2rem/1.1 'Source Sans 3', sans-serif;
        }
        .stock {
            margin: 8px 0 12px;
            font: 700 13px/1 'Montserrat', sans-serif;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .stock.ok { color: var(--success); }
        .stock.low { color: var(--danger); }
        .desc {
            margin: 0 0 12px;
            color: #313949;
            font: 500 16px/1.65 'Source Sans 3', sans-serif;
        }
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
        .order-title {
            margin: 14px 0 4px;
            color: var(--brand-navy);
            font: 700 16px/1.2 'Montserrat', sans-serif;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .field {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border-radius: 12px;
            border: 1px solid #d5c8b4;
            margin-top: 10px;
            font: 500 15px/1.3 'Source Sans 3', sans-serif;
            color: #222b3f;
            background: #fff;
        }
        .button {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border-radius: 12px;
            margin-top: 12px;
            border: 0;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(180deg, #e8760a, #c4600a);
            font: 700 16px/1.2 'Source Sans 3', sans-serif;
        }
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
        }
    </style>
</head>
<body>
<div class="wrap">
    <a class="back" href="index.php">&larr; Back to shop</a>

    <?php if (!$product): ?>
        <div class="not-found">Product not found in local catalog. Run sync first.</div>
    <?php else: ?>
        <div class="layout">
            <div class="gallery">
                <img class="img" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['name']) ?>">
            </div>
            <div class="box">
                <?php if (!empty($product['badge'])): ?>
                    <span class="badge"><?= htmlspecialchars((string)$product['badge']) ?></span>
                <?php endif; ?>
                <div class="brand">Watercolor.LK Curated</div>
                <h1><?= htmlspecialchars((string)$product['name']) ?></h1>
                <div class="price">LKR <?= number_format((float)$product['price'], 2) ?></div>
                <div class="stock <?= $stock > 2 ? 'ok' : 'low' ?>"><?= $stock > 2 ? 'In stock' : 'Limited stock' ?>: <?= $stock ?></div>
                <p class="desc"><?= nl2br(htmlspecialchars((string)$product['description'])) ?></p>

                <h3 class="order-title">Quick Order</h3>
                <input id="customer_name" class="field" placeholder="Full name">
                <input id="customer_phone" class="field" placeholder="Phone number">
                <input id="customer_email" class="field" placeholder="Email (optional)">
                <select id="payment_method" class="field">
                    <option value="payhere">PayHere</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="whatsapp">WhatsApp assisted</option>
                </select>
                <textarea id="notes" class="field" placeholder="Notes"></textarea>
                <input id="qty" class="field" type="number" value="1" min="1" step="1">
                <button class="button" onclick="submitOrder()">Place Order</button>
                <div id="orderResult"></div>
            </div>
        </div>

        <script>
            const product = {
                product_id: <?= (int)$product['id'] ?>,
                erp_product_id: <?= (int)$product['erp_product_id'] ?>,
                sku: <?= json_encode((string)$product['sku']) ?>,
                unit_price: <?= json_encode((float)$product['price']) ?>
            };

            async function submitOrder() {
                const payload = {
                    customer_name: document.getElementById('customer_name').value.trim(),
                    customer_phone: document.getElementById('customer_phone').value.trim(),
                    customer_email: document.getElementById('customer_email').value.trim(),
                    payment_method: document.getElementById('payment_method').value,
                    notes: document.getElementById('notes').value.trim(),
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
        </script>
    <?php endif; ?>
</div>
</body>
</html>
