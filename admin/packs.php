<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new StorefrontRepository(appDb());
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$res = $repo->adminList(['kind' => 'pack', 'limit' => $perPage, 'offset' => ($page - 1) * $perPage, 'q' => trim((string)($_GET['q'] ?? ''))]);
$pagination = new Pagination($page, $perPage, $res['total']);

$pageTitle = 'Packs';
$activeNav = 'packs';
$pageActions = '<a class="btn primary" href="' . h(adminUrl('pack-edit.php')) . '">+ New pack</a>';
require __DIR__ . '/_layout_top.php';
?>
<div class="card">
    <form class="toolbar" method="get">
        <input type="text" name="q" value="<?= h((string)($_GET['q'] ?? '')) ?>" placeholder="Search…">
        <button class="btn dark">Filter</button>
        <span class="muted">Packs are bundles of multiple ERP products at a single fixed price. Stock = floor(min(child stock / qty)).</span>
    </form>
</div>
<div class="card" style="padding:0">
    <table>
        <thead><tr><th>Title</th><th>Items</th><th>Pack price</th><th>Stock</th><th>Visibility</th><th></th></tr></thead>
        <tbody>
        <?php if (!$res['rows']): ?>
            <tr><td colspan="6" style="padding:30px; text-align:center; color:#6b7388">No packs yet.</td></tr>
        <?php else: foreach ($res['rows'] as $r): ?>
            <?php
                $childCount = (int)(appDb()->query("SELECT COUNT(*) c FROM storefront_product_children WHERE parent_storefront_id = " . (int)$r['id'] . " AND context = 'pack_item'")->fetch()['c'] ?? 0);
                $stock = $repo->effectiveStock((int)$r['id']);
            ?>
            <tr>
                <td><strong><a href="<?= h(adminUrl('pack-edit.php?id=' . (int)$r['id'])) ?>"><?= h($r['title']) ?></a></strong>
                    <div class="muted" style="font-size:.78rem">/<?= h($r['slug']) ?></div></td>
                <td><?= $childCount ?></td>
                <td><?= $r['base_price'] !== null ? 'Rs ' . number_format((float)$r['base_price'], 2) : '<span class="pill amber">price not set</span>' ?></td>
                <td><?= (int)$stock ?></td>
                <td><?= $r['is_visible'] ? '<span class="pill green">Visible</span>' : '<span class="pill gray">Hidden</span>' ?></td>
                <td><a class="btn sm" href="<?= h(adminUrl('pack-edit.php?id=' . (int)$r['id'])) ?>">Edit</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php if ($pagination->totalPages > 1): ?>
<div class="pagination"><?php for ($p = 1; $p <= $pagination->totalPages; $p++): ?>
    <?php if ($p === $page): ?><span class="cur"><?= $p ?></span><?php else: ?><a href="<?= h($pagination->url(adminUrl('packs.php'), $_GET, $p)) ?>"><?= $p ?></a><?php endif; ?>
<?php endfor; ?></div>
<?php endif; ?>
<?php require __DIR__ . '/_layout_bottom.php';
