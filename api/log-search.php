<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    JsonResponse::send(['ok' => true], 204);
    exit;
}

/* Accept POST or GET (sendBeacon falls back to POST text/plain). */
$body = file_get_contents('php://input') ?: '';
$payload = [];
if ($body !== '') {
    $decoded = json_decode($body, true);
    if (is_array($decoded)) $payload = $decoded;
    else parse_str($body, $payload);
}
$q = trim((string)($payload['q'] ?? $_POST['q'] ?? $_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2 || mb_strlen($q) > 190) {
    JsonResponse::send(['success' => false, 'error' => 'invalid query'], 400);
    exit;
}

try {
    $repo = new ProductRepository(appDb());
    $repo->logSearchQuery($q);
    JsonResponse::send(['success' => true]);
} catch (Throwable $e) {
    /* Never break user experience for analytics. */
    JsonResponse::send(['success' => false, 'error' => 'log failed'], 200);
}
