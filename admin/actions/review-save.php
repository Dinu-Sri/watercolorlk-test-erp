<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('reviews.php'));
    exit;
}

$repo = new Repositories\GoogleReviewRepository(appDb());
$rid = trim((string)($_POST['review_id'] ?? ''));

$payload = [
    'author' => trim((string)($_POST['author'] ?? '')),
    'rating' => (float)($_POST['rating'] ?? 5),
    'review_text' => trim((string)($_POST['review_text'] ?? '')),
    'review_date' => trim((string)($_POST['review_date'] ?? '')) ?: null,
    'language' => trim((string)($_POST['language'] ?? 'en')),
    'profile_picture_remote_url' => trim((string)($_POST['profile_picture_remote_url'] ?? '')) ?: null,
    'owner_response' => trim((string)($_POST['owner_response'] ?? '')),
    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
];

if ($payload['author'] === '' || $payload['review_text'] === '') {
    Flash::error('Author and review text are required.');
    header('Location: ' . adminUrl('review-edit.php' . ($rid ? '?id=' . urlencode($rid) : '')));
    exit;
}

if ($rid === '' || !$repo->getOne($rid)) {
    $rid = $repo->insertManual($payload);
    audit('review_create', 'google_review', $rid);
    Flash::success('Review created.');
} else {
    $repo->updateOne($rid, $payload);
    audit('review_update', 'google_review', $rid);
    Flash::success('Review updated.');
}

header('Location: ' . adminUrl('review-edit.php?id=' . urlencode($rid)));
exit;
