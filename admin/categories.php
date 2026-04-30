<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new CategoryRepository(appDb());
$categories = $repo->listAll(false);

$pageTitle = 'Categories';
$activeNav = 'categories';
$pageActions = '<a class="btn primary" href="' . h(adminUrl('category-edit.php')) . '">+ New category</a>';
require __DIR__ . '/_layout_top.php';
?>
<div class="card" style="padding:0">
    <table>
        <thead>
            <tr>
                <th style="width:50px">Sort</th>
                <th>Name</th>
                <th>Slug</th>
                <th style="width:90px">Products</th>
                <th style="width:110px">Visibility</th>
                <th style="width:160px"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$categories): ?>
                <tr><td colspan="6" style="padding:30px; text-align:center; color:#6b7388">No categories yet. <a href="<?= h(adminUrl('category-edit.php')) ?>">Create one</a>.</td></tr>
            <?php else: foreach ($categories as $c): ?>
                <tr>
                    <td><?= (int)$c['sort_order'] ?></td>
                    <td><strong><?= h($c['name']) ?></strong>
                        <?php if ($c['parent_id']): ?><div class="muted" style="font-size:.78rem">parent #<?= (int)$c['parent_id'] ?></div><?php endif; ?>
                    </td>
                    <td><span class="muted">/<?= h($c['slug']) ?></span></td>
                    <td><?= (int)($c['visible_count'] ?? 0) ?></td>
                    <td><?= $c['is_visible'] ? '<span class="pill green">Visible</span>' : '<span class="pill gray">Hidden</span>' ?></td>
                    <td>
                        <a class="btn sm" href="<?= h(adminUrl('category-edit.php?id=' . (int)$c['id'])) ?>">Edit</a>
                        <form method="post" action="<?= h(adminUrl('actions/category-delete.php')) ?>" style="display:inline" onsubmit="return confirm('Delete this category?')">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn sm danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/_layout_bottom.php';
