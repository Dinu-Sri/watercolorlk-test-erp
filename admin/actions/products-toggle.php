<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('products.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$repo = new StorefrontRepository(appDb());
$sp = $repo->getById($id);
if (!$sp) {
    Flash::error('Product not found.');
    header('Location: ' . adminUrl('products.php'));
    exit;
}

$now = !$sp['is_visible'];
$repo->setVisible($id, $now);
audit($now ? 'product_show' : 'product_hide', 'storefront_product', (string)$id);
Flash::success($now ? 'Product is now visible on the storefront.' : 'Product is hidden.');

$ref = $_SERVER['HTTP_REFERER'] ?? adminUrl('products.php');
header('Location: ' . $ref);
exit;
