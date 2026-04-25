<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$erpId = (int)($_GET['id'] ?? 0);
$repo = new ProductRepository(appDb());
$product = $erpId > 0 ? $repo->getByErpId($erpId) : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $product ? htmlspecialchars((string)$product['name']) : 'Product not found' ?> | Watercolor.LK</title>
    <link rel="icon" type="image/webp" href="assets/images/brand/favicon-watercolorlk.webp">
    <style>
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; background: #f6f4ef; color: #10233f; }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .img { width: 100%; border-radius: 14px; border: 1px solid #e6decd; background: #fff; }
        .box { background: #fff; border: 1px solid #e6decd; border-radius: 14px; padding: 16px; }
        .price { color: #7a4a00; font-size: 24px; font-weight: 800; }
        input, textarea, button { width: 100%; box-sizing: border-box; padding: 10px; border-radius: 10px; border: 1px solid #d7c8a5; margin-top: 10px; }
        button { background: #10233f; color: #fff; border: 0; cursor: pointer; font-weight: 700; }
        #orderResult { margin-top: 10px; font-size: 14px; }
        @media (max-width: 820px) {
            .layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <a href="index.php">&larr; Back to shop</a>

    <?php if (!$product): ?>
        <div class="box" style="margin-top:12px">Product not found in local catalog. Run sync first.</div>
    <?php else: ?>
        <div class="layout" style="margin-top:12px">
            <img class="img" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['name']) ?>">
            <div class="box">
                <h1 style="margin:0 0 8px"><?= htmlspecialchars((string)$product['name']) ?></h1>
                <div class="price">LKR <?= number_format((float)$product['price'], 2) ?></div>
                <p><?= nl2br(htmlspecialchars((string)$product['description'])) ?></p>
                <p><strong>Stock:</strong> <?= (float)$product['stock_qty'] ?></p>

                <h3 style="margin-top:20px">Quick Order</h3>
                <input id="customer_name" placeholder="Full name">
                <input id="customer_phone" placeholder="Phone number">
                <input id="customer_email" placeholder="Email (optional)">
                <select id="payment_method" style="width:100%;padding:10px;border-radius:10px;border:1px solid #d7c8a5;margin-top:10px">
                    <option value="payhere">PayHere</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="whatsapp">WhatsApp assisted</option>
                </select>
                <textarea id="notes" placeholder="Notes"></textarea>
                <input id="qty" type="number" value="1" min="1" step="1">
                <button onclick="submitOrder()">Place Order</button>
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
