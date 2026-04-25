<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    JsonResponse::send(['ok' => true], 204);
    exit;
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    if (strlen($q) < 2) {
        JsonResponse::send(['success' => true, 'suggestions' => []]);
        exit;
    }

    $repo = new ProductRepository(appDb());
    $suggestions = $repo->searchSuggestions($q, 10);

    JsonResponse::send([
        'success' => true,
        'suggestions' => $suggestions,
    ]);
} catch (Throwable $e) {
    JsonResponse::send(['success' => false, 'error' => $e->getMessage()], 500);
}
