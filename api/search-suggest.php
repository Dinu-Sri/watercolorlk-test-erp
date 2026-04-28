<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    JsonResponse::send(['ok' => true], 204);
    exit;
}

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $repo = new ProductRepository(appDb());

    /* No keystroke: return trending only (Google-style "popular searches"). */
    if ($q === '') {
        JsonResponse::send([
            'success' => true,
            'q' => '',
            'trending' => $repo->getTrendingSearches('', 8),
            'products' => [],
            'brands' => [],
            'categories' => [],
        ]);
        exit;
    }

    /* Keystroke: blend matching trending + product matches + brand matches + canned categories. */
    $trending  = $repo->getTrendingSearches($q, 6);
    $products  = $repo->searchSuggestions($q, 6);

    /* Brand suggestions: match brand_name LIKE q. */
    $brandStmt = appDb()->prepare(
        "SELECT TRIM(brand_name) AS name, COUNT(*) AS cnt
         FROM products
         WHERE is_active = 1 AND TRIM(COALESCE(brand_name, '')) <> ''
           AND LOWER(brand_name) LIKE LOWER(:q)
         GROUP BY TRIM(brand_name)
         ORDER BY cnt DESC, name ASC
         LIMIT 4"
    );
    $brandStmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
    $brandStmt->execute();
    $brands = array_map(static fn(array $r): array => [
        'name' => (string)$r['name'],
        'count' => (int)$r['cnt'],
    ], $brandStmt->fetchAll());

    /* Canned category buckets that match the prefix. */
    $buckets = [
        'Brushes' => 'brush', 'Papers' => 'paper', 'Paints' => 'paint',
        'Sketchbooks' => 'sketch', 'Accessories' => 'access',
    ];
    $needle = mb_strtolower($q);
    $categories = [];
    foreach ($buckets as $label => $kw) {
        if (str_contains(mb_strtolower($label), $needle) || str_contains($needle, $kw)) {
            $categories[] = ['label' => $label, 'keyword' => $kw];
        }
    }

    JsonResponse::send([
        'success' => true,
        'q' => $q,
        'trending' => $trending,
        'products' => $products,
        'brands' => $brands,
        'categories' => $categories,
    ]);
} catch (Throwable $e) {
    JsonResponse::send(['success' => false, 'error' => $e->getMessage()], 500);
}
