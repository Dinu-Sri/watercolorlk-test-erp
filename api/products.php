<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    JsonResponse::send(['ok' => true], 204);
    exit;
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(60, max(1, (int)($_GET['per_page'] ?? 24)));
    $offset = ($page - 1) * $perPage;

    $repo = new ProductRepository(appDb());
    $products = $repo->listProducts($q, $perPage, $offset);

    JsonResponse::send([
        'success' => true,
        'page' => $page,
        'per_page' => $perPage,
        'products' => $products,
    ]);
} catch (Throwable $e) {
    JsonResponse::send(['success' => false, 'error' => $e->getMessage()], 500);
}
