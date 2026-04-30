<?php

declare(strict_types=1);

/**
 * Legacy unauthenticated override endpoint. Replaced by the authenticated flow
 * under admin/products.php / admin/product-edit.php. This file now redirects.
 */

require_once __DIR__ . '/_bootstrap.php';

$pid = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
if ($pid > 0) {
    $stmt = appDb()->prepare(
        "SELECT sp.id FROM storefront_products sp
         INNER JOIN products p ON p.erp_product_id = sp.erp_product_id AND sp.kind = 'simple'
         WHERE p.id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $pid]);
    $sid = (int)($stmt->fetch()['id'] ?? 0);
    if ($sid > 0) {
        header('Location: ' . adminUrl('product-edit.php?id=' . $sid));
        exit;
    }
}
header('Location: ' . adminUrl('products.php'));
exit;
