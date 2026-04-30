<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new StorefrontRepository(appDb());
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$res = $repo->adminList(['kind' => 'combined', 'limit' => $perPage, 'offset' => ($page - 1) * $perPage, 'q' => trim((string)($_GET['q'] ?? ''))]);
$pagination = new Pagination($page, $perPage, $res['total']);

$pageTitle = 'Combined Products';
$activeNav = 'combined';
$pageActions = '<a class="btn primary" href="' . h(adminUrl('combined-edit.php')) . '">+ New combined product</a>';
require __DIR__ . '/_layout_top.php';
?>
<div class="card">
    <form class="toolbar" method="get">
        <input type="text" name="q" placeholder="Search…" value="<?= h((string)($_GET['q'] ?? '')) ?>">
        <button class="btn dark">Filter</button>
        <span class="muted">Combined products group multiple ERP variants (color/size/etc.) under one storefront page.</span>
    </form>
</div>
<div class="card" style="padding:0">
    <table>
        <thead><tr><th>Title</th><th>Variants</th><th>Price</th><th>Visibility</th><th></th></tr></thead>
        <tbody>
        <?php if (!$res['rows']): ?>
            <tr><td colspan="5" style="padding:30px; text-align:center; color:#6b7388">No combined products yet.</td></tr>
        <?php else: foreach ($res['rows'] as $r): ?>
            <?php
                $varCount = (int)(appDb()->query("SELECT COUNT(*) c FROM storefront_product_children WHERE parent_storefront_id = " . (int)$r['id'] . " AND context = 'variant'")->fetch()['c'] ?? 0);
            ?>
            <tr>
                <td><strong><a href="<?= h(adminUrl('combined-edit.php?id=' . (int)$r['id'])) ?>"><?= h($r['title']) ?></a></strong>
                    <div class="muted" style="font-size:.78rem">/<?= h($r['slug']) ?></div></td>
                <td><?= $varCount ?></td>
                <td><?= $r['base_price'] !== null ? 'Rs ' . number_format((float)$r['base_price'], 2) : '<span class="muted">per variant</span>' ?></td>
                <td><?= $r['is_visible'] ? '<span class="pill green">Visible</span>' : '<span class="pill gray">Hidden</span>' ?></td>
                <td><a class="btn sm" href="<?= h(adminUrl('combined-edit.php?id=' . (int)$r['id'])) ?>">Edit</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php if ($pagination->totalPages > 1): ?>
<div class="pagination"><?php for ($p = 1; $p <= $pagination->totalPages; $p++): ?>
    <?php if ($p === $page): ?><span class="cur"><?= $p ?></span><?php else: ?><a href="<?= h($pagination->url(adminUrl('combined.php'), $_GET, $p)) ?>"><?= $p ?></a><?php endif; ?>
<?php endfor; ?></div>
<?php endif; ?>
<?php require __DIR__ . '/_layout_bottom.php';
