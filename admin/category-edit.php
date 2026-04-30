<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new CategoryRepository(appDb());
$id = (int)($_GET['id'] ?? 0);
$cat = $id > 0 ? $repo->getById($id) : null;
$isNew = $cat === null;
$parents = $repo->listAll(false);

$pageTitle = $isNew ? 'New category' : 'Edit category: ' . $cat['name'];
$activeNav = 'categories';
$pageActions = '<a class="btn" href="' . h(adminUrl('categories.php')) . '">← Back</a>';
require __DIR__ . '/_layout_top.php';
?>
<form method="post" action="<?= h(adminUrl('actions/category-save.php')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="card">
        <div class="row">
            <div class="field">
                <label>Name</label>
                <input type="text" name="name" required value="<?= h($cat['name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Slug</label>
                <input type="text" name="slug" value="<?= h($cat['slug'] ?? '') ?>" placeholder="auto from name">
            </div>
        </div>
        <div class="row">
            <div class="field">
                <label>Parent category</label>
                <select name="parent_id">
                    <option value="">— none —</option>
                    <?php foreach ($parents as $p): if ((int)$p['id'] === $id) continue; ?>
                        <option value="<?= (int)$p['id'] ?>" <?= ($cat['parent_id'] ?? null) == $p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Sort order</label>
                <input type="number" name="sort_order" value="<?= (int)($cat['sort_order'] ?? 0) ?>">
            </div>
        </div>
        <div class="field">
            <label>Image URL</label>
            <input type="url" name="image_url" value="<?= h($cat['image_url'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Description</label>
            <textarea name="description" rows="3"><?= h($cat['description'] ?? '') ?></textarea>
        </div>
        <label class="checkbox-row">
            <input type="checkbox" name="is_visible" value="1" <?= !$cat || $cat['is_visible'] ? 'checked' : '' ?>>
            <strong>Visible on storefront</strong>
        </label>
    </div>
    <div class="card">
        <h2>SEO</h2>
        <div class="field">
            <label>SEO title</label>
            <input type="text" name="seo_title" value="<?= h($cat['seo_title'] ?? '') ?>">
        </div>
        <div class="field">
            <label>SEO description</label>
            <textarea name="seo_description" rows="2"><?= h($cat['seo_description'] ?? '') ?></textarea>
        </div>
    </div>
    <button class="btn primary" type="submit"><?= $isNew ? 'Create category' : 'Save changes' ?></button>
    <a class="btn" href="<?= h(adminUrl('categories.php')) ?>">Cancel</a>
</form>
<?php require __DIR__ . '/_layout_bottom.php';
