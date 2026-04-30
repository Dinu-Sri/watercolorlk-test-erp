<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('products.php'));
    exit;
}

try {
    $svc = new CatalogSyncService(appErpClient(), new ProductRepository(appDb()), appDb());
    $stats = $svc->sync();
    $msg = sprintf(
        'ERP sync complete: %d added, %d updated, %d unchanged.',
        (int)($stats['inserted'] ?? 0),
        (int)($stats['updated'] ?? 0),
        (int)($stats['unchanged'] ?? 0)
    );
    audit('catalog_sync', null, null, $stats);
    Flash::success($msg);
} catch (Throwable $e) {
    Flash::error('Sync failed: ' . $e->getMessage());
}

header('Location: ' . adminUrl('products.php'));
exit;
