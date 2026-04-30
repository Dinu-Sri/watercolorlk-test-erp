<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new Repositories\GoogleReviewRepository(appDb());
$rid = (string)($_GET['id'] ?? '');
$r = $rid !== '' ? $repo->getOne($rid) : null;
$isNew = $r === null;

$pageTitle = $isNew ? 'Add review manually' : 'Edit review';
$activeNav = 'reviews';
$pageActions = '<a class="btn" href="' . h(adminUrl('reviews.php')) . '">← Back</a>';
require __DIR__ . '/_layout_top.php';
?>
<form method="post" action="<?= h(adminUrl('actions/review-save.php')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="review_id" value="<?= h((string)($r['review_id'] ?? '')) ?>">
    <div class="card">
        <div class="row">
            <div class="field">
                <label>Author</label>
                <input type="text" name="author" required value="<?= h($r['author'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Rating</label>
                <select name="rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= ((int)round((float)($r['rating'] ?? 5))) === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?>)</option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label>Review text</label>
            <textarea name="review_text" rows="6" required><?= h($r['review_text'] ?? '') ?></textarea>
        </div>
        <div class="row">
            <div class="field">
                <label>Review date</label>
                <input type="date" name="review_date" value="<?= h($r['review_date'] ? date('Y-m-d', strtotime((string)$r['review_date'])) : date('Y-m-d')) ?>">
            </div>
            <div class="field">
                <label>Language</label>
                <input type="text" name="language" value="<?= h($r['language'] ?? 'en') ?>" maxlength="10">
            </div>
        </div>
        <div class="field">
            <label>Profile picture URL (optional)</label>
            <input type="url" name="profile_picture_remote_url" value="<?= h($r['profile_picture_remote_url'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Owner response (optional)</label>
            <textarea name="owner_response" rows="3"><?= h($r['owner_response'] ?? '') ?></textarea>
        </div>
        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= !$r || $r['is_active'] ? 'checked' : '' ?>><strong>Active (visible on storefront)</strong></label>
    </div>
    <button class="btn primary" type="submit"><?= $isNew ? 'Create review' : 'Save changes' ?></button>
    <?php if (!$isNew): ?>
        <form method="post" action="<?= h(adminUrl('actions/review-delete.php')) ?>" style="display:inline-block; margin-left:8px" onsubmit="return confirm('Delete this review permanently?')">
            <?= Csrf::field() ?>
            <input type="hidden" name="review_id" value="<?= h((string)$r['review_id']) ?>">
            <button class="btn danger" type="submit">Delete review</button>
        </form>
    <?php endif; ?>
</form>
<?php require __DIR__ . '/_layout_bottom.php';
