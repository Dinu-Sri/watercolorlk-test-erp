<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new StorefrontRepository(appDb());
$catRepo = new CategoryRepository(appDb());

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$filters = [
    'q'           => trim((string)($_GET['q'] ?? '')),
    'kind'        => (string)($_GET['kind'] ?? ''),
    'visibility'  => (string)($_GET['visibility'] ?? ''),
    'category_id' => (int)($_GET['category_id'] ?? 0),
    'limit'       => $perPage,
    'offset'      => ($page - 1) * $perPage,
];

$res = $repo->adminList($filters);
$rows = $res['rows'];
$total = $res['total'];
$pagination = new Pagination($page, $perPage, $total);
$categories = $catRepo->listAll(false);

$pageTitle = 'Products';
$activeNav = 'products';
$pageActions = '<form method="post" action="' . h(adminUrl('actions/sync.php')) . '" style="display:inline">' . Csrf::field() . '<button class="btn primary" type="submit">Sync from ERP</button></form>';
require __DIR__ . '/_layout_top.php';
?>
<div class="card">
    <form class="toolbar" method="get">
        <input type="text" name="q" value="<?= h($filters['q']) ?>" placeholder="Search title or slug…" style="min-width:220px">
        <select name="kind">
            <option value="">All kinds</option>
            <?php foreach (['simple' => 'Simple', 'combined' => 'Combined', 'pack' => 'Pack'] as $k => $lbl): ?>
                <option value="<?= $k ?>" <?= $filters['kind'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
        <select name="visibility">
            <option value="">Any visibility</option>
            <option value="1" <?= $filters['visibility'] === '1' ? 'selected' : '' ?>>Visible</option>
            <option value="0" <?= $filters['visibility'] === '0' ? 'selected' : '' ?>>Hidden</option>
        </select>
        <select name="category_id">
            <option value="0">Any category</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $filters['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn dark">Filter</button>
        <a class="btn" href="<?= h(adminUrl('products.php')) ?>">Reset</a>
        <span class="muted" style="margin-left:auto"><?= (int)$total ?> result<?= $total === 1 ? '' : 's' ?></span>
    </form>
</div>

<form method="post" action="<?= h(adminUrl('actions/products-bulk.php')) ?>">
    <?= Csrf::field() ?>
    <div class="card" style="padding:0">
        <table>
            <thead>
                <tr>
                    <th style="width:34px"><input type="checkbox" id="all-toggle"></th>
                    <th style="width:46px"></th>
                    <th>Product</th>
                    <th style="width:90px">Kind</th>
                    <th style="width:110px">Price</th>
                    <th style="width:90px">Stock</th>
                    <th style="width:110px">Visibility</th>
                    <th style="width:160px"></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" style="padding:30px; text-align:center; color:#6b7388">No products yet. Run <strong>Sync from ERP</strong> to load the catalog.</td></tr>
            <?php else: foreach ($rows as $r):
                $price = $r['base_price'] !== null ? (float)$r['base_price'] : (float)($r['erp_price'] ?? 0);
                $img = $r['hero_image_url'] ?: '';
            ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
                    <td><?php if ($img): ?><img class="thumb" src="<?= h($img) ?>" alt=""><?php else: ?><span class="thumb"></span><?php endif; ?></td>
                    <td>
                        <div style="font-weight:700"><a href="<?= h(adminUrl('product-edit.php?id=' . (int)$r['id'])) ?>"><?= h($r['title']) ?></a></div>
                        <div class="muted" style="font-size:.8rem">
                            <?php if ($r['sku']): ?>SKU <?= h($r['sku']) ?> · <?php endif; ?>
                            /<?= h($r['slug']) ?>
                            <?php if ($r['badge']): ?> · <span class="pill amber"><?= h($r['badge']) ?></span><?php endif; ?>
                        </div>
                    </td>
                    <td><span class="pill <?= $r['kind'] === 'simple' ? 'gray' : ($r['kind'] === 'combined' ? 'blue' : 'amber') ?>"><?= h((string)$r['kind']) ?></span></td>
                    <td>Rs <?= number_format($price, 2) ?>
                        <?php if ($r['compare_at_price'] !== null): ?>
                            <div class="muted" style="font-size:.78rem; text-decoration:line-through">Rs <?= number_format((float)$r['compare_at_price'], 2) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $r['kind'] === 'simple' ? rtrim(rtrim(number_format((float)($r['simple_stock'] ?? 0), 3, '.', ''), '0'), '.') : '<span class="muted">—</span>' ?></td>
                    <td>
                        <?php if ($r['is_visible']): ?><span class="pill green">Visible</span><?php else: ?><span class="pill gray">Hidden</span><?php endif; ?>
                    </td>
                    <td>
                        <a class="btn sm" href="<?= h(adminUrl('product-edit.php?id=' . (int)$r['id'])) ?>">Edit</a>
                        <form method="post" action="<?= h(adminUrl('actions/products-toggle.php')) ?>" style="display:inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn sm" type="submit"><?= $r['is_visible'] ? 'Hide' : 'Publish' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="toolbar" style="margin-top:14px">
        <span class="muted">Bulk action:</span>
        <button class="btn" type="submit" name="bulk" value="show">Publish selected</button>
        <button class="btn" type="submit" name="bulk" value="hide">Hide selected</button>
    </div>
</form>

<?php if ($pagination->totalPages > 1): ?>
<div class="pagination">
    <?php for ($p = 1; $p <= $pagination->totalPages; $p++): ?>
        <?php if ($p === $page): ?><span class="cur"><?= $p ?></span>
        <?php else: ?><a href="<?= h($pagination->url(adminUrl('products.php'), $_GET, $p)) ?>"><?= $p ?></a><?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('all-toggle')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="ids[]"]').forEach(c => c.checked = this.checked);
});
</script>
<?php require __DIR__ . '/_layout_bottom.php';
