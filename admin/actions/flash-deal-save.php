<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('flash-deals.php'));
    exit;
}

$repo = new FlashDealRepository(appDb());
$id = (int)($_POST['id'] ?? 0);

$payload = [
    'storefront_product_id' => (int)($_POST['storefront_product_id'] ?? 0),
    'deal_price' => $_POST['deal_price'] ?? 0,
    'original_price' => $_POST['original_price'] ?? null,
    'label' => trim((string)($_POST['label'] ?? '')) ?: null,
    'starts_at' => trim((string)($_POST['starts_at'] ?? '')) ?: null,
    'ends_at' => trim((string)($_POST['ends_at'] ?? '')) ?: null,
    'sort_order' => (int)($_POST['sort_order'] ?? 0),
    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    'created_by' => AdminAuth::userId(),
];
if ($payload['storefront_product_id'] <= 0 || (float)$payload['deal_price'] < 0) {
    Flash::error('Choose a product and enter a non-negative deal price.');
    header('Location: ' . adminUrl('flash-deal-edit.php' . ($id ? '?id=' . $id : '')));
    exit;
}

if ($id > 0) {
    $repo->update($id, $payload);
    audit('flash_update', 'flash_deal', (string)$id);
    Flash::success('Flash deal updated.');
} else {
    $id = $repo->create($payload);
    audit('flash_create', 'flash_deal', (string)$id);
    Flash::success('Flash deal created.');
}

header('Location: ' . adminUrl('flash-deals.php'));
exit;
