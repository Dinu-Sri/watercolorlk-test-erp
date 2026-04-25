<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    JsonResponse::send(['ok' => true], 204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::send(['success' => false, 'error' => 'Method not allowed'], 405);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) {
        JsonResponse::send(['success' => false, 'error' => 'Invalid JSON payload'], 422);
        exit;
    }

    if (empty($payload['customer_name']) || empty($payload['customer_phone']) || empty($payload['payment_method'])) {
        JsonResponse::send(['success' => false, 'error' => 'Missing required customer fields'], 422);
        exit;
    }

    if (!isset($payload['items']) || !is_array($payload['items']) || count($payload['items']) === 0) {
        JsonResponse::send(['success' => false, 'error' => 'Order must include at least one item'], 422);
        exit;
    }

    $db = appDb();
    $orderRepo = new OrderRepository($db);
    $orderId = $orderRepo->createOrder($payload);

    $syncService = new OrderSyncService(appErpClient(), $orderRepo);
    $syncStatus = 'queued';

    try {
        $syncService->pushOrderById($orderId);
        $syncStatus = 'synced';
    } catch (Throwable $syncError) {
        $orderRepo->markSyncFailed($orderId, $syncError->getMessage());
        $syncStatus = 'failed';
    }

    JsonResponse::send([
        'success' => true,
        'order_id' => $orderId,
        'erp_sync' => $syncStatus,
    ], 201);
} catch (Throwable $e) {
    JsonResponse::send(['success' => false, 'error' => $e->getMessage()], 500);
}
