<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$repo = new ProductRepository(appDb());
$products = $repo->listProducts('', 100, 0);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Product Overrides</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; background: #f7f7f7; color: #16253a; }
        .wrap { max-width: 1050px; margin: 0 auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; }
        th, td { padding: 10px; border-bottom: 1px solid #ececec; text-align: left; font-size: 14px; }
        form { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 16px; background: #fff; border-radius: 12px; padding: 12px; }
        input, textarea, button { width: 100%; box-sizing: border-box; padding: 9px; border: 1px solid #d2d2d2; border-radius: 8px; }
        button { background: #16253a; color: #fff; font-weight: 700; border: 0; cursor: pointer; }
        .full { grid-column: 1 / -1; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Product Overrides</h1>
    <p>Use this to override ERP image/content when ERP quality is low.</p>

    <table>
        <thead>
        <tr>
            <th>ERP ID</th>
            <th>Name</th>
            <th>SKU</th>
            <th>Price</th>
            <th>Stock</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?= (int)$p['erp_product_id'] ?></td>
                <td><?= htmlspecialchars((string)$p['display_name']) ?></td>
                <td><?= htmlspecialchars((string)$p['sku']) ?></td>
                <td>LKR <?= number_format((float)$p['price'], 2) ?></td>
                <td><?= (float)$p['stock_qty'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form action="save-override.php" method="post">
        <input name="product_id" type="number" placeholder="Local product_id (not ERP ID)" required>
        <input name="slug" placeholder="SEO slug">
        <input name="title" placeholder="Override title">
        <input name="image_url" placeholder="Override image URL">
        <input name="price" type="number" step="0.01" placeholder="Override price">
        <input name="badge" placeholder="Badge text">
        <textarea class="full" name="description" rows="5" placeholder="Override description"></textarea>
        <button class="full" type="submit">Save Override</button>
    </form>
</div>
</body>
</html>
