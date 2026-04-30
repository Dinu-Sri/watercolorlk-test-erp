<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new StorefrontRepository(appDb());
$catRepo = new CategoryRepository(appDb());

$id = (int)($_GET['id'] ?? 0);
$sp = $id > 0 ? $repo->getFull($id) : null;
$isNew = $sp === null;
if (!$isNew && $sp['kind'] !== 'combined') {
    Flash::error('That product is not a combined product.');
    header('Location: ' . adminUrl('products.php'));
    exit;
}

// Pull all ERP products for the variant picker.
$erpProducts = appDb()->query(
    'SELECT id, sku, name, price, stock_qty FROM products WHERE is_active = 1 ORDER BY name'
)->fetchAll();

$categories = $catRepo->listAll(false);
$selectedCats = $sp ? $sp['category_ids'] : [];
$variants = $sp ? array_values(array_filter($sp['children'], fn($c) => $c['context'] === 'variant')) : [];

$pageTitle = $isNew ? 'New combined product' : 'Edit: ' . $sp['title'];
$activeNav = 'combined';
$pageActions = '<a class="btn" href="' . h(adminUrl('combined.php')) . '">← Back</a>';
require __DIR__ . '/_layout_top.php';
?>
<form method="post" action="<?= h(adminUrl('actions/combined-save.php')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="card">
        <h2>Basics</h2>
        <div class="row">
            <div class="field">
                <label>Title</label>
                <input type="text" name="title" required value="<?= h($sp['title'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Slug</label>
                <input type="text" name="slug" value="<?= h($sp['slug'] ?? '') ?>" placeholder="auto from title">
            </div>
        </div>
        <div class="field">
            <label>Subtitle</label>
            <input type="text" name="subtitle" value="<?= h($sp['subtitle'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="6"><?= h($sp['description'] ?? '') ?></textarea>
        </div>
        <div class="row three">
            <div class="field">
                <label>Default selling price (Rs) — optional</label>
                <input type="number" step="0.01" min="0" name="base_price" value="<?= $sp['base_price'] !== null ? h((string)$sp['base_price']) : '' ?>">
                <div class="muted" style="font-size:.78rem; margin-top:4px">Used when a variant doesn't override.</div>
            </div>
            <div class="field">
                <label>Compare-at price (Rs)</label>
                <input type="number" step="0.01" min="0" name="compare_at_price" value="<?= $sp['compare_at_price'] !== null ? h((string)$sp['compare_at_price']) : '' ?>">
            </div>
            <div class="field">
                <label>Badge</label>
                <input type="text" name="badge" value="<?= h($sp['badge'] ?? '') ?>">
            </div>
        </div>
        <div class="field">
            <label>Hero image URL</label>
            <input type="url" name="hero_image_url" value="<?= h($sp['hero_image_url'] ?? '') ?>">
        </div>
    </div>

    <div class="card">
        <h2>Variants</h2>
        <p class="muted">Each row maps a UI variant to one ERP product. Mark one as <strong>Default</strong> for initial selection.</p>
        <div id="variants" data-erp='<?= h(json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'price' => (float)$p['price'], 'stock' => (float)$p['stock_qty']], $erpProducts), JSON_UNESCAPED_SLASHES)) ?>'>
            <table style="width:100%">
                <thead>
                    <tr>
                        <th>ERP Product</th>
                        <th style="width:140px">Label</th>
                        <th style="width:80px">Swatch</th>
                        <th style="width:120px">Price override (Rs)</th>
                        <th style="width:70px">Default</th>
                        <th style="width:60px"></th>
                    </tr>
                </thead>
                <tbody id="variant-rows">
                <?php
                $rowsToRender = $variants ?: [['child_product_id' => null, 'variant_label' => '', 'variant_swatch_hex' => '', 'price_override' => null, 'is_default' => 1]];
                foreach ($rowsToRender as $i => $v):
                ?>
                    <tr class="vrow">
                        <td>
                            <select name="variants[<?= $i ?>][child_product_id]" required>
                                <option value="">— choose ERP product —</option>
                                <?php foreach ($erpProducts as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" data-price="<?= h((string)$p['price']) ?>" <?= (int)($v['child_product_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= h(($p['sku'] ? $p['sku'] . ' · ' : '') . $p['name']) ?> (Rs <?= number_format((float)$p['price'], 2) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="variants[<?= $i ?>][variant_label]" value="<?= h($v['variant_label'] ?? '') ?>" placeholder="e.g. Red"></td>
                        <td><input type="color" name="variants[<?= $i ?>][variant_swatch_hex]" value="<?= h($v['variant_swatch_hex'] ?? '#cccccc') ?>" style="height:36px; padding:2px"></td>
                        <td><input type="number" step="0.01" min="0" name="variants[<?= $i ?>][price_override]" value="<?= $v['price_override'] !== null ? h((string)$v['price_override']) : '' ?>" placeholder="auto"></td>
                        <td style="text-align:center"><input type="radio" name="default_variant" value="<?= $i ?>" <?= !empty($v['is_default']) ? 'checked' : '' ?>></td>
                        <td><button type="button" class="btn sm danger vremove">×</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn" id="vadd" style="margin-top:8px">+ Add variant</button>
        </div>
    </div>

    <div class="card">
        <h2>Categories</h2>
        <?php if (!$categories): ?><p class="muted">No categories yet.</p><?php else: ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:6px">
            <?php foreach ($categories as $c): ?>
                <label class="checkbox-row">
                    <input type="checkbox" name="categories[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $selectedCats, true) ? 'checked' : '' ?>>
                    <?= h($c['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <label class="checkbox-row">
            <input type="checkbox" name="is_visible" value="1" <?= !$sp || $sp['is_visible'] ? 'checked' : '' ?>>
            <strong>Visible on storefront</strong>
        </label>
    </div>

    <button type="submit" class="btn primary"><?= $isNew ? 'Create combined product' : 'Save changes' ?></button>
    <a class="btn" href="<?= h(adminUrl('combined.php')) ?>">Cancel</a>
</form>

<script>
(function() {
    const tbody = document.getElementById('variant-rows');
    const addBtn = document.getElementById('vadd');
    function nextIndex() { return tbody.querySelectorAll('tr').length; }
    addBtn?.addEventListener('click', function() {
        const i = nextIndex();
        const first = tbody.querySelector('tr.vrow');
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input, select').forEach(el => {
            if (el.name) el.name = el.name.replace(/variants\[\d+\]/, 'variants[' + i + ']');
            if (el.type === 'radio') el.value = i;
            if (el.type !== 'color') el.value = '';
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
        });
        tbody.appendChild(clone);
    });
    tbody.addEventListener('click', function(e) {
        if (e.target.classList.contains('vremove')) {
            if (tbody.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
        }
    });
})();
</script>
<?php require __DIR__ . '/_layout_bottom.php';
