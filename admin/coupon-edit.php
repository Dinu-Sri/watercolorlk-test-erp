<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new CouponRepository(appDb());
$catRepo = new CategoryRepository(appDb());
$id = (int)($_GET['id'] ?? 0);
$c = $id > 0 ? $repo->getById($id) : null;
$isNew = $c === null;

$targets = $id > 0 ? $repo->getTargets($id) : [];
$selectedCats = []; $selectedProds = [];
foreach ($targets as $t) {
    if ($t['target_type'] === 'category') $selectedCats[] = (int)$t['target_id'];
    if ($t['target_type'] === 'storefront_product') $selectedProds[] = (int)$t['target_id'];
}

$categories = $catRepo->listAll(false);
$products = appDb()->query('SELECT id, title, kind FROM storefront_products WHERE is_visible = 1 ORDER BY title')->fetchAll();

$pageTitle = $isNew ? 'New coupon' : 'Edit coupon';
$activeNav = 'coupons';
$pageActions = '<a class="btn" href="' . h(adminUrl('coupons.php')) . '">← Back</a>';
require __DIR__ . '/_layout_top.php';

$fmt = fn($s) => $s ? date('Y-m-d\TH:i', strtotime((string)$s)) : '';
?>
<form method="post" action="<?= h(adminUrl('actions/coupon-save.php')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="card">
        <div class="row">
            <div class="field">
                <label>Code</label>
                <input type="text" name="code" required value="<?= h($c['code'] ?? '') ?>" style="font-family:monospace; text-transform:uppercase; letter-spacing:.05em">
            </div>
            <div class="field">
                <label>Description (internal)</label>
                <input type="text" name="description" value="<?= h($c['description'] ?? '') ?>">
            </div>
        </div>

        <div class="row three">
            <div class="field">
                <label>Type</label>
                <select name="type">
                    <option value="percent"   <?= ($c['type'] ?? '') === 'percent'   ? 'selected' : '' ?>>Percent off</option>
                    <option value="fixed"     <?= ($c['type'] ?? '') === 'fixed'     ? 'selected' : '' ?>>Fixed amount off</option>
                    <option value="free_ship" <?= ($c['type'] ?? '') === 'free_ship' ? 'selected' : '' ?>>Free shipping</option>
                </select>
            </div>
            <div class="field">
                <label>Value</label>
                <input type="number" step="0.01" min="0" name="value" value="<?= h((string)($c['value'] ?? 0)) ?>">
                <div class="muted" style="font-size:.78rem; margin-top:4px">% for percent, Rs for fixed, ignored for free shipping.</div>
            </div>
            <div class="field">
                <label>Max discount cap (Rs)</label>
                <input type="number" step="0.01" min="0" name="max_discount" value="<?= $c['max_discount'] !== null ? h((string)$c['max_discount']) : '' ?>" placeholder="optional">
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label>Min subtotal (Rs)</label>
                <input type="number" step="0.01" min="0" name="min_subtotal" value="<?= $c['min_subtotal'] !== null ? h((string)$c['min_subtotal']) : '' ?>">
            </div>
            <div class="field">
                <label>Total usage limit</label>
                <input type="number" min="0" name="usage_limit" value="<?= $c['usage_limit'] !== null ? (int)$c['usage_limit'] : '' ?>" placeholder="unlimited">
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label>Per-customer usage limit</label>
                <input type="number" min="0" name="usage_limit_per_customer" value="<?= $c['usage_limit_per_customer'] !== null ? (int)$c['usage_limit_per_customer'] : '' ?>" placeholder="unlimited">
            </div>
            <div class="field">
                <label class="checkbox-row" style="margin-top:24px"><input type="checkbox" name="is_active" value="1" <?= !$c || $c['is_active'] ? 'checked' : '' ?>> Active</label>
            </div>
        </div>

        <div class="row">
            <div class="field"><label>Starts at</label><input type="datetime-local" name="starts_at" value="<?= h($fmt($c['starts_at'] ?? null)) ?>"></div>
            <div class="field"><label>Ends at</label><input type="datetime-local" name="ends_at" value="<?= h($fmt($c['ends_at'] ?? null)) ?>"></div>
        </div>
    </div>

    <div class="card">
        <h2>Applies to</h2>
        <?php $at = $c['applies_to'] ?? 'all'; ?>
        <div class="field">
            <label class="checkbox-row"><input type="radio" name="applies_to" value="all"        <?= $at === 'all' ? 'checked' : '' ?>> All products</label>
            <label class="checkbox-row"><input type="radio" name="applies_to" value="categories" <?= $at === 'categories' ? 'checked' : '' ?>> Specific categories</label>
            <label class="checkbox-row"><input type="radio" name="applies_to" value="products"   <?= $at === 'products' ? 'checked' : '' ?>> Specific products</label>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px">
            <div>
                <label class="field" style="margin-bottom:6px"><label>Categories</label></label>
                <div style="max-height:220px; overflow:auto; border:1px solid var(--line); border-radius:8px; padding:8px">
                    <?php foreach ($categories as $cat): ?>
                        <label class="checkbox-row"><input type="checkbox" name="cat_ids[]" value="<?= (int)$cat['id'] ?>" <?= in_array((int)$cat['id'], $selectedCats, true) ? 'checked' : '' ?>> <?= h($cat['name']) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="field" style="margin-bottom:6px"><label>Products</label></label>
                <div style="max-height:220px; overflow:auto; border:1px solid var(--line); border-radius:8px; padding:8px">
                    <?php foreach ($products as $p): ?>
                        <label class="checkbox-row"><input type="checkbox" name="prod_ids[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $selectedProds, true) ? 'checked' : '' ?>> <?= h($p['title']) ?> <span class="muted" style="font-size:.78rem">(<?= h($p['kind']) ?>)</span></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn primary"><?= $isNew ? 'Create coupon' : 'Save changes' ?></button>
    <a class="btn" href="<?= h(adminUrl('coupons.php')) ?>">Cancel</a>
</form>
<?php require __DIR__ . '/_layout_bottom.php';
