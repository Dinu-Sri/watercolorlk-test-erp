<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

try {
    $repo = new ProductRepository(appDb());
    $repo->saveOverride((int)($_POST['product_id'] ?? 0), [
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'title' => trim((string)($_POST['title'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'image_url' => trim((string)($_POST['image_url'] ?? '')),
        'price' => trim((string)($_POST['price'] ?? '')),
        'badge' => trim((string)($_POST['badge'] ?? '')),
    ]);

    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Failed to save override: ' . htmlspecialchars($e->getMessage());
}
