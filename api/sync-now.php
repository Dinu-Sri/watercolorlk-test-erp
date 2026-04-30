<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$key = (string)($_GET['key'] ?? '');
if ($key !== SYNC_WEBHOOK_KEY) {
    JsonResponse::send(['success' => false, 'error' => 'Unauthorized'], 401);
    exit;
}

try {
    $catalog = new CatalogSyncService(appErpClient(), new ProductRepository(appDb()), appDb());
    $orderSync = new OrderSyncService(appErpClient(), new OrderRepository(appDb()));

    $catalogResult = $catalog->syncProducts();
    $orderResult = $orderSync->pushPendingOrders();

    JsonResponse::send([
        'success' => true,
        'catalog' => $catalogResult,
        'orders' => $orderResult,
    ]);
} catch (Throwable $e) {
    JsonResponse::send(['success' => false, 'error' => $e->getMessage()], 500);
}
