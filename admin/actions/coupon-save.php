<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('coupons.php'));
    exit;
}

$repo = new CouponRepository(appDb());
$id = (int)($_POST['id'] ?? 0);

$code = strtoupper(trim((string)($_POST['code'] ?? '')));
if ($code === '' || !preg_match('/^[A-Z0-9_\-]+$/', $code)) {
    Flash::error('Code must be A-Z, 0-9, dash or underscore.');
    header('Location: ' . adminUrl('coupon-edit.php' . ($id ? '?id=' . $id : '')));
    exit;
}

// Uniqueness check (case-insensitive)
$existing = $repo->getByCode($code);
if ($existing && (int)$existing['id'] !== $id) {
    Flash::error('That code is already in use.');
    header('Location: ' . adminUrl('coupon-edit.php' . ($id ? '?id=' . $id : '')));
    exit;
}

$type = (string)($_POST['type'] ?? 'percent');
if (!in_array($type, ['percent', 'fixed', 'free_ship'], true)) $type = 'percent';

$payload = [
    'code' => $code,
    'description' => trim((string)($_POST['description'] ?? '')) ?: null,
    'type' => $type,
    'value' => (float)($_POST['value'] ?? 0),
    'min_subtotal' => $_POST['min_subtotal'] ?? null,
    'max_discount' => $_POST['max_discount'] ?? null,
    'starts_at' => trim((string)($_POST['starts_at'] ?? '')) ?: null,
    'ends_at' => trim((string)($_POST['ends_at'] ?? '')) ?: null,
    'usage_limit' => $_POST['usage_limit'] ?? null,
    'usage_limit_per_customer' => $_POST['usage_limit_per_customer'] ?? null,
    'applies_to' => in_array(($_POST['applies_to'] ?? 'all'), ['all', 'categories', 'products'], true) ? $_POST['applies_to'] : 'all',
    'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    'created_by' => AdminAuth::userId(),
];

if ($id > 0) {
    $repo->update($id, $payload);
} else {
    $id = $repo->create($payload);
}

$targets = [];
if ($payload['applies_to'] === 'categories') {
    foreach ((array)($_POST['cat_ids'] ?? []) as $cid) {
        $targets[] = ['target_type' => 'category', 'target_id' => (int)$cid];
    }
}
if ($payload['applies_to'] === 'products') {
    foreach ((array)($_POST['prod_ids'] ?? []) as $pid) {
        $targets[] = ['target_type' => 'storefront_product', 'target_id' => (int)$pid];
    }
}
$repo->replaceTargets($id, $targets);

audit('coupon_save', 'coupon', (string)$id, ['code' => $code, 'type' => $type]);
Flash::success('Coupon saved.');

header('Location: ' . adminUrl('coupons.php'));
exit;
