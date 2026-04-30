<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new FlashDealRepository(appDb());
$id = (int)($_GET['id'] ?? 0);
$deal = $id > 0 ? $repo->getById($id) : null;
$isNew = $deal === null;

$products = appDb()->query(
    'SELECT id, title, slug, kind, base_price FROM storefront_products WHERE is_visible = 1 ORDER BY title'
)->fetchAll();

$pageTitle = $isNew ? 'New flash deal' : 'Edit flash deal';
$activeNav = 'flash';
$pageActions = '<a class="btn" href="' . h(adminUrl('flash-deals.php')) . '">← Back</a>';
require __DIR__ . '/_layout_top.php';

$fmt = fn($s) => $s ? date('Y-m-d\TH:i', strtotime((string)$s)) : '';
?>
<form method="post" action="<?= h(adminUrl('actions/flash-deal-save.php')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="card">
        <div class="field">
            <label>Storefront product</label>
            <select name="storefront_product_id" required <?= $isNew ? '' : 'disabled' ?>>
                <option value="">— choose —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= ($deal['storefront_product_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
                        <?= h($p['title']) ?> (<?= h($p['kind']) ?>)<?= $p['base_price'] !== null ? ' · Rs ' . number_format((float)$p['base_price'], 2) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$isNew): ?><input type="hidden" name="storefront_product_id" value="<?= (int)$deal['storefront_product_id'] ?>"><?php endif; ?>
        </div>
        <div class="row three">
            <div class="field">
                <label>Deal price (Rs)</label>
                <input type="number" step="0.01" min="0" name="deal_price" required value="<?= h((string)($deal['deal_price'] ?? '')) ?>">
            </div>
            <div class="field">
                <label>Original price (optional)</label>
                <input type="number" step="0.01" min="0" name="original_price" value="<?= $deal['original_price'] !== null ? h((string)$deal['original_price']) : '' ?>">
            </div>
            <div class="field">
                <label>Label</label>
                <input type="text" name="label" value="<?= h($deal['label'] ?? '') ?>" placeholder="Flash 20% Off">
            </div>
        </div>
        <div class="row">
            <div class="field">
                <label>Starts at (leave blank for "always")</label>
                <input type="datetime-local" name="starts_at" value="<?= h($fmt($deal['starts_at'] ?? null)) ?>">
            </div>
            <div class="field">
                <label>Ends at (leave blank for "no end")</label>
                <input type="datetime-local" name="ends_at" value="<?= h($fmt($deal['ends_at'] ?? null)) ?>">
            </div>
        </div>
        <div class="row">
            <div class="field">
                <label>Sort order</label>
                <input type="number" name="sort_order" value="<?= (int)($deal['sort_order'] ?? 0) ?>">
            </div>
            <div class="field">
                <label class="checkbox-row" style="margin-top:24px"><input type="checkbox" name="is_active" value="1" <?= !$deal || $deal['is_active'] ? 'checked' : '' ?>> Active</label>
            </div>
        </div>
    </div>
    <button class="btn primary" type="submit"><?= $isNew ? 'Create flash deal' : 'Save changes' ?></button>
    <a class="btn" href="<?= h(adminUrl('flash-deals.php')) ?>">Cancel</a>
</form>
<?php require __DIR__ . '/_layout_bottom.php';
