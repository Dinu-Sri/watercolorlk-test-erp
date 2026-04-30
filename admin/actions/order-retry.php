<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('orders.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    Flash::error('Order id missing.');
    header('Location: ' . adminUrl('orders.php'));
    exit;
}

try {
    $repo = new OrderRepository(appDb());
    $svc = new OrderSyncService(appErpClient(), $repo);
    $svc->pushOrderById($id);
    audit('order_sync_retry', 'order', (string)$id);
    Flash::success('Order #' . $id . ' synced to ERP.');
} catch (Throwable $e) {
    Flash::error('Retry failed: ' . $e->getMessage());
}

header('Location: ' . adminUrl('order-view.php?id=' . $id));
exit;
