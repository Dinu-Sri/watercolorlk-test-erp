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
    $perPage = min(96, max(1, (int)($_GET['per_page'] ?? 24)));
    $offset = ($page - 1) * $perPage;

    /* Multi-value params accept either CSV or repeated keys. */
    $multi = static function ($v): array {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $item) {
                foreach (explode(',', (string)$item) as $piece) {
                    $piece = trim($piece);
                    if ($piece !== '') $out[] = $piece;
                }
            }
            return $out;
        }
        if (is_string($v) && $v !== '') {
            $out = [];
            foreach (explode(',', $v) as $piece) {
                $piece = trim($piece);
                if ($piece !== '') $out[] = $piece;
            }
            return $out;
        }
        return [];
    };

    $filters = [
        'q'          => $q,
        'categories' => $multi($_GET['category'] ?? $_GET['categories'] ?? null),
        'brands'     => $multi($_GET['brand']    ?? $_GET['brands']    ?? null),
        'min_price'  => isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : '',
        'max_price'  => isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : '',
        'in_stock'   => !empty($_GET['in_stock']) && $_GET['in_stock'] !== '0',
        'sort'       => (string)($_GET['sort'] ?? 'relevance'),
        'limit'      => $perPage,
        'offset'     => $offset,
    ];

    $repo = new ProductRepository(appDb());

    $result = $repo->searchProducts($filters);

    /* Merge visible storefront extras (combined + pack) on page 1 only when no
       category/brand filter active. */
    if ($page === 1 && empty($filters['categories']) && empty($filters['brands'])) {
        try {
            $extras = $repo->listVisibleStorefrontExtras($q, $filters['min_price'], $filters['max_price']);
            if ($filters['in_stock']) {
                $extras = array_values(array_filter($extras, static fn(array $r): bool => (float)$r['stock_qty'] > 0));
            }
            if (!empty($extras)) {
                $result['products'] = array_merge($extras, $result['products']);
                $result['total'] = (int)$result['total'] + count($extras);
            }
        } catch (Throwable $e) { /* ignore */ }
    }
    $payload = [
        'success'  => true,
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => $result['total'],
        'products' => $result['products'],
    ];
    if (!empty($_GET['with_facets'])) {
        $payload['facets'] = [
            'categories'  => $repo->listAllCategories(),
            'brands'      => $repo->listAllBrands(),
            'price_range' => $repo->getPriceRange(),
        ];
    }
    JsonResponse::send($payload);
} catch (Throwable $e) {
    JsonResponse::send(['success' => false, 'error' => $e->getMessage()], 500);
}

