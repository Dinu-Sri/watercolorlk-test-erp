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
