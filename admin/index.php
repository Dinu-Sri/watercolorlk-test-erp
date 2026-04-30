<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$db = appDb();

$counts = [
    'visible' => 0, 'hidden' => 0, 'simple' => 0, 'combined' => 0, 'pack' => 0,
    'active_coupons' => 0, 'active_flash' => 0, 'pending_orders' => 0, 'reviews_active' => 0,
];

try {
    foreach ($db->query('SELECT is_visible, kind, COUNT(*) AS c FROM storefront_products GROUP BY is_visible, kind') as $r) {
        $counts[$r['is_visible'] ? 'visible' : 'hidden'] += (int)$r['c'];
        $counts[(string)$r['kind']] = ($counts[(string)$r['kind']] ?? 0) + (int)$r['c'];
    }
} catch (Throwable $e) {}

try { $counts['active_coupons'] = (int)($db->query("SELECT COUNT(*) c FROM coupons WHERE is_active = 1")->fetch()['c'] ?? 0); } catch (Throwable $e) {}
try {
    $counts['active_flash'] = (int)($db->query(
        "SELECT COUNT(*) c FROM flash_deals
         WHERE is_active = 1
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (ends_at IS NULL OR ends_at >= NOW())"
    )->fetch()['c'] ?? 0);
} catch (Throwable $e) {}
try { $counts['pending_orders'] = (int)($db->query("SELECT COUNT(*) c FROM orders WHERE erp_sync_status = 'pending'")->fetch()['c'] ?? 0); } catch (Throwable $e) {}
try { $counts['reviews_active'] = (int)($db->query("SELECT COUNT(*) c FROM google_reviews WHERE is_active = 1")->fetch()['c'] ?? 0); } catch (Throwable $e) {}

/* Phase C extras */
$counts['users_total'] = 0;
$counts['orders_today'] = 0;
$counts['revenue_today'] = 0.0;
$lowStock = [];
$recentOrders = [];
try { $counts['users_total'] = (int)($db->query("SELECT COUNT(*) c FROM users")->fetch()['c'] ?? 0); } catch (Throwable $e) {}
try { $counts['orders_today'] = (int)($db->query("SELECT COUNT(*) c FROM orders WHERE DATE(created_at) = CURDATE()")->fetch()['c'] ?? 0); } catch (Throwable $e) {}
try { $counts['revenue_today'] = (float)($db->query("SELECT COALESCE(SUM(total_amount),0) c FROM orders WHERE DATE(created_at) = CURDATE() AND status <> 'cancelled'")->fetch()['c'] ?? 0); } catch (Throwable $e) {}
try {
    $lowStock = $db->query(
        "SELECT id, erp_product_id, name, sku, stock_qty
         FROM products
         WHERE is_active = 1 AND stock_qty <= 5
         ORDER BY stock_qty ASC, name ASC
         LIMIT 10"
    )->fetchAll();
} catch (Throwable $e) { $lowStock = []; }
try {
    $recentOrders = $db->query(
        "SELECT id, customer_name, total_amount, status, erp_sync_status, created_at
         FROM orders
         ORDER BY id DESC
         LIMIT 8"
    )->fetchAll();
} catch (Throwable $e) { $recentOrders = []; }

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/_layout_top.php';
?>
<div class="kpis">
    <div class="kpi"><div class="lbl">Visible products</div><div class="val"><?= (int)$counts['visible'] ?></div></div>
    <div class="kpi"><div class="lbl">Hidden products</div><div class="val"><?= (int)$counts['hidden'] ?></div></div>
    <div class="kpi"><div class="lbl">Simple</div><div class="val"><?= (int)($counts['simple'] ?? 0) ?></div></div>
    <div class="kpi"><div class="lbl">Combined</div><div class="val"><?= (int)($counts['combined'] ?? 0) ?></div></div>
    <div class="kpi"><div class="lbl">Packs</div><div class="val"><?= (int)($counts['pack'] ?? 0) ?></div></div>
    <div class="kpi"><div class="lbl">Active coupons</div><div class="val"><?= (int)$counts['active_coupons'] ?></div></div>
    <div class="kpi"><div class="lbl">Active flash deals</div><div class="val"><?= (int)$counts['active_flash'] ?></div></div>
    <div class="kpi"><div class="lbl">Pending orders</div><div class="val"><?= (int)$counts['pending_orders'] ?></div></div>
    <div class="kpi"><div class="lbl">Active reviews</div><div class="val"><?= (int)$counts['reviews_active'] ?></div></div>
    <div class="kpi"><div class="lbl">Customers</div><div class="val"><?= (int)$counts['users_total'] ?></div></div>
    <div class="kpi"><div class="lbl">Orders today</div><div class="val"><?= (int)$counts['orders_today'] ?></div></div>
    <div class="kpi"><div class="lbl">Revenue today</div><div class="val">LKR <?= number_format($counts['revenue_today'], 0) ?></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;">
    <div class="card">
        <h2 style="margin:0 0 10px;">Low stock</h2>
        <?php if (!$lowStock): ?>
            <div class="muted">All active products have stock above 5.</div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Product</th><th>SKU</th><th>Stock</th></tr></thead>
            <tbody>
                <?php foreach ($lowStock as $p): ?>
                <tr>
                    <td><?= h((string)$p['name']) ?></td>
                    <td><?= h((string)($p['sku'] ?? '—')) ?></td>
                    <td>
                        <?php $s = (float)$p['stock_qty']; ?>
                        <strong style="color:<?= $s <= 0 ? '#a31621' : ($s <= 2 ? '#b8232f' : '#7a5b00') ?>;">
                            <?= rtrim(rtrim(number_format($s, 2), '0'), '.') ?>
                        </strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2 style="margin:0 0 10px;">Recent orders</h2>
        <?php if (!$recentOrders): ?>
            <div class="muted">No orders yet.</div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><a href="<?= h(adminUrl('order-view.php?id=' . (int)$o['id'])) ?>">#<?= (int)$o['id'] ?></a></td>
                    <td><?= h((string)$o['customer_name']) ?></td>
                    <td>LKR <?= number_format((float)$o['total_amount'], 2) ?></td>
                    <td><?= h((string)$o['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin:10px 0 0;"><a class="btn small" href="<?= h(adminUrl('orders.php')) ?>">View all orders &rarr;</a></p>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:20px">
    <h2>Quick start</h2>
    <ul style="margin:0; padding-left:20px; line-height:1.9">
        <li>Curate the catalog → <a href="<?= h(adminUrl('products.php')) ?>">Products</a> (toggle visibility, edit titles/images/prices)</li>
        <li>Build color/size variant pages → <a href="<?= h(adminUrl('combined.php')) ?>">Combined Products</a></li>
        <li>Build bundles → <a href="<?= h(adminUrl('packs.php')) ?>">Packs</a></li>
        <li>Schedule sales → <a href="<?= h(adminUrl('flash-deals.php')) ?>">Flash Deals</a> · <a href="<?= h(adminUrl('coupons.php')) ?>">Coupons</a></li>
        <li>Add a manual customer review → <a href="<?= h(adminUrl('reviews.php')) ?>">Reviews</a></li>
    </ul>
</div>
<?php require __DIR__ . '/_layout_bottom.php';
