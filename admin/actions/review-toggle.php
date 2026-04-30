<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('reviews.php'));
    exit;
}

$rid = trim((string)($_POST['review_id'] ?? ''));
if ($rid === '') {
    header('Location: ' . adminUrl('reviews.php'));
    exit;
}
$repo = new Repositories\GoogleReviewRepository(appDb());
$r = $repo->getOne($rid);
if ($r) {
    $repo->setActive($rid, !$r['is_active']);
    audit($r['is_active'] ? 'review_hide' : 'review_show', 'google_review', $rid);
    Flash::success($r['is_active'] ? 'Review hidden.' : 'Review is now active.');
}

$ref = $_SERVER['HTTP_REFERER'] ?? adminUrl('reviews.php');
header('Location: ' . $ref);
exit;
