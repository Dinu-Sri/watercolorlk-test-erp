<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('orders.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? '');
if ($id <= 0 || !in_array($status, ['pending','processing','completed','cancelled'], true)) {
    Flash::error('Invalid status update.');
    header('Location: ' . adminUrl('orders.php'));
    exit;
}

try {
    (new OrderRepository(appDb()))->setStatus($id, $status);
    audit('order_status', 'order', (string)$id, ['status' => $status]);
    Flash::success('Order #' . $id . ' updated to ' . $status . '.');
} catch (Throwable $e) {
    Flash::error('Update failed: ' . $e->getMessage());
}

header('Location: ' . adminUrl('order-view.php?id=' . $id));
exit;
