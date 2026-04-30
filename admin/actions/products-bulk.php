<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('products.php'));
    exit;
}

$ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
$bulk = (string)($_POST['bulk'] ?? '');
if (!$ids || !in_array($bulk, ['show', 'hide'], true)) {
    Flash::error('Select at least one product and an action.');
    header('Location: ' . adminUrl('products.php'));
    exit;
}

$repo = new StorefrontRepository(appDb());
$repo->bulkSetVisible($ids, $bulk === 'show');
audit('product_bulk_' . $bulk, 'storefront_product', implode(',', $ids));
Flash::success(($bulk === 'show' ? 'Published' : 'Hidden') . ' ' . count($ids) . ' product' . (count($ids) === 1 ? '' : 's') . '.');

header('Location: ' . adminUrl('products.php'));
exit;
