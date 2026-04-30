<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new StorefrontRepository(appDb());
$catRepo = new CategoryRepository(appDb());

$id = (int)($_GET['id'] ?? 0);
$sp = $id > 0 ? $repo->getFull($id) : null;
$isNew = $sp === null;
if (!$isNew && $sp['kind'] !== 'pack') {
    Flash::error('That product is not a pack.');
    header('Location: ' . adminUrl('products.php'));
    exit;
}

$erpProducts = appDb()->query('SELECT id, sku, name, price, stock_qty FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();
$categories = $catRepo->listAll(false);
$selectedCats = $sp ? $sp['category_ids'] : [];
$items = $sp ? array_values(array_filter($sp['children'], fn($c) => $c['context'] === 'pack_item')) : [];

$pageTitle = $isNew ? 'New pack' : 'Edit pack: ' . $sp['title'];
$activeNav = 'packs';
$pageActions = '<a class="btn" href="' . h(adminUrl('packs.php')) . '">← Back</a>';
require __DIR__ . '/_layout_top.php';
?>
<form method="post" action="<?= h(adminUrl('actions/pack-save.php')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="card">
        <h2>Basics</h2>
        <div class="row">
            <div class="field"><label>Title</label><input type="text" name="title" required value="<?= h($sp['title'] ?? '') ?>"></div>
            <div class="field"><label>Slug</label><input type="text" name="slug" value="<?= h($sp['slug'] ?? '') ?>" placeholder="auto from title"></div>
        </div>
        <div class="field"><label>Subtitle</label><input type="text" name="subtitle" value="<?= h($sp['subtitle'] ?? '') ?>"></div>
        <div class="field"><label>Description</label><textarea name="description" rows="6"><?= h($sp['description'] ?? '') ?></textarea></div>
        <div class="row three">
            <div class="field">
                <label>Pack price (Rs)</label>
                <input type="number" step="0.01" min="0" name="base_price" required value="<?= $sp['base_price'] !== null ? h((string)$sp['base_price']) : '' ?>">
                <div class="muted" style="font-size:.78rem; margin-top:4px">Single fixed price for the whole pack.</div>
            </div>
            <div class="field">
                <label>Compare-at price (Rs)</label>
                <input type="number" step="0.01" min="0" name="compare_at_price" value="<?= $sp['compare_at_price'] !== null ? h((string)$sp['compare_at_price']) : '' ?>">
            </div>
            <div class="field"><label>Badge</label><input type="text" name="badge" value="<?= h($sp['badge'] ?? '') ?>" placeholder="Bundle"></div>
        </div>
        <div class="field"><label>Hero image URL</label><input type="url" name="hero_image_url" value="<?= h($sp['hero_image_url'] ?? '') ?>"></div>
    </div>

    <div class="card">
        <h2>Pack contents</h2>
        <p class="muted">Each row adds one ERP product to the pack. Quantity = how many of that item the customer receives.</p>
        <table style="width:100%">
            <thead><tr><th>ERP Product</th><th style="width:100px">Quantity</th><th style="width:60px"></th></tr></thead>
            <tbody id="pack-rows">
            <?php
            $rowsToRender = $items ?: [['child_product_id' => null, 'quantity' => 1]];
            foreach ($rowsToRender as $i => $v):
            ?>
                <tr class="prow">
                    <td>
                        <select name="items[<?= $i ?>][child_product_id]" required>
                            <option value="">— choose ERP product —</option>
                            <?php foreach ($erpProducts as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= (int)($v['child_product_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= h(($p['sku'] ? $p['sku'] . ' · ' : '') . $p['name']) ?> (Rs <?= number_format((float)$p['price'], 2) ?>, stock <?= (int)$p['stock_qty'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" step="1" min="1" name="items[<?= $i ?>][quantity]" value="<?= h((string)((int)($v['quantity'] ?? 1))) ?>"></td>
                    <td><button type="button" class="btn sm danger premove">×</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="btn" id="padd" style="margin-top:8px">+ Add item</button>
    </div>

    <div class="card">
        <h2>Categories</h2>
        <?php if (!$categories): ?><p class="muted">No categories yet.</p><?php else: ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:6px">
            <?php foreach ($categories as $c): ?>
                <label class="checkbox-row"><input type="checkbox" name="categories[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $selectedCats, true) ? 'checked' : '' ?>> <?= h($c['name']) ?></label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <label class="checkbox-row"><input type="checkbox" name="is_visible" value="1" <?= !$sp || $sp['is_visible'] ? 'checked' : '' ?>><strong>Visible on storefront</strong></label>
    </div>

    <button type="submit" class="btn primary"><?= $isNew ? 'Create pack' : 'Save changes' ?></button>
    <a class="btn" href="<?= h(adminUrl('packs.php')) ?>">Cancel</a>
</form>
<script>
(function() {
    const tbody = document.getElementById('pack-rows');
    document.getElementById('padd')?.addEventListener('click', function() {
        const i = tbody.querySelectorAll('tr').length;
        const first = tbody.querySelector('tr.prow');
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input, select').forEach(el => {
            if (el.name) el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']');
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else if (el.type === 'number') el.value = '1';
            else el.value = '';
        });
        tbody.appendChild(clone);
    });
    tbody.addEventListener('click', function(e) {
        if (e.target.classList.contains('premove') && tbody.querySelectorAll('tr').length > 1) {
            e.target.closest('tr').remove();
        }
    });
})();
</script>
<?php require __DIR__ . '/_layout_bottom.php';
