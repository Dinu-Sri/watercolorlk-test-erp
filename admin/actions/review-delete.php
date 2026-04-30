<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('reviews.php'));
    exit;
}

$rid = trim((string)($_POST['review_id'] ?? ''));
if ($rid !== '') {
    (new Repositories\GoogleReviewRepository(appDb()))->deleteOne($rid);
    audit('review_delete', 'google_review', $rid);
    Flash::success('Review deleted.');
}

header('Location: ' . adminUrl('reviews.php'));
exit;
