<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new StorefrontRepository(appDb());
$catRepo = new CategoryRepository(appDb());

$id = (int)($_GET['id'] ?? 0);
$sp = $repo->getFull($id);
if (!$sp) {
    Flash::error('Product not found.');
    header('Location: ' . adminUrl('products.php'));
    exit;
}

// Combined / pack products edit on dedicated screens.
if ($sp['kind'] === 'combined') { header('Location: ' . adminUrl('combined-edit.php?id=' . $id)); exit; }
if ($sp['kind'] === 'pack')     { header('Location: ' . adminUrl('pack-edit.php?id=' . $id)); exit; }

// Pull underlying ERP product for reference fields.
$erpStmt = appDb()->prepare('SELECT id, sku, name, price, stock_qty, image_url FROM products WHERE erp_product_id = :e LIMIT 1');
$erpStmt->execute([':e' => (int)$sp['erp_product_id']]);
$erp = $erpStmt->fetch() ?: null;

$categories = $catRepo->listAll(false);
$selectedCats = $sp['category_ids'];

// Decode gallery
$gallery = [];
if (!empty($sp['gallery_json'])) {
    $g = json_decode((string)$sp['gallery_json'], true);
    if (is_array($g)) $gallery = $g;
}

$pageTitle = 'Edit: ' . $sp['title'];
$activeNav = 'products';
$pageActions = '<a class="btn" href="' . h(adminUrl('products.php')) . '">← Back</a>';
require __DIR__ . '/_layout_top.php';
?>
<form method="post" action="<?= h(adminUrl('actions/product-save.php')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$sp['id'] ?>">

    <div class="card">
        <h2>Basics</h2>
        <div class="row">
            <div class="field">
                <label>Title</label>
                <input type="text" name="title" required value="<?= h($sp['title']) ?>">
                <?php if ($erp): ?><div class="muted" style="font-size:.78rem; margin-top:4px">ERP name: <?= h($erp['name']) ?></div><?php endif; ?>
            </div>
            <div class="field">
                <label>Slug</label>
                <input type="text" name="slug" required value="<?= h($sp['slug']) ?>">
                <div class="muted" style="font-size:.78rem; margin-top:4px">/product/<?= h($sp['slug']) ?></div>
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
    </div>

    <div class="card">
        <h2>Pricing</h2>
        <div class="row three">
            <div class="field">
                <label>Selling price (Rs)</label>
                <input type="number" step="0.01" min="0" name="base_price" value="<?= $sp['base_price'] !== null ? h((string)$sp['base_price']) : '' ?>">
                <?php if ($erp): ?><div class="muted" style="font-size:.78rem; margin-top:4px">ERP price: Rs <?= number_format((float)$erp['price'], 2) ?></div><?php endif; ?>
            </div>
            <div class="field">
                <label>Compare-at price (Rs)</label>
                <input type="number" step="0.01" min="0" name="compare_at_price" value="<?= $sp['compare_at_price'] !== null ? h((string)$sp['compare_at_price']) : '' ?>">
                <div class="muted" style="font-size:.78rem; margin-top:4px">Shown struck-through. Leave blank for none.</div>
            </div>
            <div class="field">
                <label>Badge</label>
                <input type="text" name="badge" value="<?= h($sp['badge'] ?? '') ?>" placeholder="New, Hot, Limited…">
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Imagery</h2>
        <div class="field">
            <label>Hero image URL</label>
            <input type="url" name="hero_image_url" value="<?= h($sp['hero_image_url'] ?? '') ?>">
            <?php if ($erp && $erp['image_url']): ?><div class="muted" style="font-size:.78rem; margin-top:4px">ERP image: <?= h($erp['image_url']) ?></div><?php endif; ?>
        </div>
        <div class="field">
            <label>Gallery image URLs (one per line)</label>
            <textarea name="gallery" rows="4" placeholder="https://…"><?= h(implode("\n", array_map('strval', $gallery))) ?></textarea>
        </div>
    </div>

    <div class="card">
        <h2>Categories</h2>
        <?php if (!$categories): ?>
            <p class="muted">No categories yet. <a href="<?= h(adminUrl('categories.php')) ?>">Create one</a>.</p>
        <?php else: ?>
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
        <h2>SEO</h2>
        <div class="field">
            <label>SEO Title</label>
            <input type="text" name="seo_title" value="<?= h($sp['seo_title'] ?? '') ?>">
        </div>
        <div class="field">
            <label>SEO Description</label>
            <textarea name="seo_description" rows="3"><?= h($sp['seo_description'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="card">
        <h2>Visibility</h2>
        <label class="checkbox-row">
            <input type="checkbox" name="is_visible" value="1" <?= $sp['is_visible'] ? 'checked' : '' ?>>
            <strong>Visible on storefront</strong>
        </label>
        <div class="field" style="max-width:200px; margin-top:14px">
            <label>Sort order</label>
            <input type="number" name="sort_order" value="<?= (int)$sp['sort_order'] ?>">
        </div>
    </div>

    <div style="display:flex; gap:8px">
        <button type="submit" class="btn primary">Save changes</button>
        <a class="btn" href="<?= h(adminUrl('products.php')) ?>">Cancel</a>
        <a class="btn" target="_blank" href="/product.php?slug=<?= h($sp['slug']) ?>">View on storefront ↗</a>
    </div>
</form>
<?php require __DIR__ . '/_layout_bottom.php';
