<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new Repositories\GoogleReviewRepository(appDb());

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'is_active' => (string)($_GET['is_active'] ?? ''),
    'min_rating' => (string)($_GET['min_rating'] ?? ''),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$total = $repo->countAdmin($filters);
$rows = $repo->getAllForAdmin($perPage, ($page - 1) * $perPage, $filters);
$pagination = new Pagination($page, $perPage, $total);

$pageTitle = 'Google Reviews';
$activeNav = 'reviews';
$pageActions = '<a class="btn primary" href="' . h(adminUrl('review-edit.php')) . '">+ Add review manually</a>';
require __DIR__ . '/_layout_top.php';
?>
<div class="card">
    <form class="toolbar" method="get">
        <input type="text" name="q" value="<?= h($filters['q']) ?>" placeholder="Search author or text…" style="min-width:240px">
        <select name="is_active">
            <option value="">Any status</option>
            <option value="1" <?= $filters['is_active'] === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= $filters['is_active'] === '0' ? 'selected' : '' ?>>Hidden</option>
        </select>
        <select name="min_rating">
            <option value="">Any rating</option>
            <?php for ($r = 5; $r >= 1; $r--): ?>
                <option value="<?= $r ?>" <?= $filters['min_rating'] === (string)$r ? 'selected' : '' ?>>≥ <?= $r ?> stars</option>
            <?php endfor; ?>
        </select>
        <button class="btn dark">Filter</button>
        <a class="btn" href="<?= h(adminUrl('reviews.php')) ?>">Reset</a>
        <span class="muted" style="margin-left:auto"><?= (int)$total ?> result<?= $total === 1 ? '' : 's' ?></span>
    </form>
</div>
<div class="card" style="padding:0">
    <table>
        <thead><tr><th>Author</th><th style="width:90px">Rating</th><th>Excerpt</th><th style="width:120px">Date</th><th style="width:100px">Status</th><th style="width:130px"></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" style="padding:30px; text-align:center; color:#6b7388">No reviews match.</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= h($r['author']) ?></strong>
                    <?php if (str_starts_with((string)$r['review_id'], 'manual-')): ?><div><span class="pill amber">manual</span></div><?php endif; ?>
                </td>
                <td><?= str_repeat('★', (int)round((float)$r['rating'])) ?></td>
                <td style="font-size:.86rem; max-width:520px"><?= h(mb_strimwidth((string)$r['review_text'], 0, 180, '…')) ?></td>
                <td style="font-size:.85rem"><?= $r['review_date'] ? h(date('M j, Y', strtotime((string)$r['review_date']))) : '<span class="muted">—</span>' ?></td>
                <td><?= $r['is_active'] ? '<span class="pill green">Active</span>' : '<span class="pill gray">Hidden</span>' ?></td>
                <td>
                    <a class="btn sm" href="<?= h(adminUrl('review-edit.php?id=' . urlencode((string)$r['review_id']))) ?>">Edit</a>
                    <form method="post" action="<?= h(adminUrl('actions/review-toggle.php')) ?>" style="display:inline">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="review_id" value="<?= h((string)$r['review_id']) ?>">
                        <button class="btn sm" type="submit"><?= $r['is_active'] ? 'Hide' : 'Show' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php if ($pagination->totalPages > 1): ?>
<div class="pagination"><?php for ($p = 1; $p <= $pagination->totalPages; $p++): ?>
    <?php if ($p === $page): ?><span class="cur"><?= $p ?></span><?php else: ?><a href="<?= h($pagination->url(adminUrl('reviews.php'), $_GET, $p)) ?>"><?= $p ?></a><?php endif; ?>
<?php endfor; ?></div>
<?php endif; ?>
<?php require __DIR__ . '/_layout_bottom.php';
