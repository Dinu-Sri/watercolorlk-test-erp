<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

try {
    $catalogService = new CatalogSyncService(appErpClient(), new ProductRepository(appDb()), appDb());
    $orderService = new OrderSyncService(appErpClient(), new OrderRepository(appDb()));

    $catalog = $catalogService->syncProducts();
    $orders = $orderService->pushPendingOrders();

    echo json_encode([
        'success' => true,
        'catalog' => $catalog,
        'orders' => $orders,
        'executed_at' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
