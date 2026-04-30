<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('coupons.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    (new CouponRepository(appDb()))->delete($id);
    audit('coupon_delete', 'coupon', (string)$id);
    Flash::success('Coupon deleted.');
}

header('Location: ' . adminUrl('coupons.php'));
exit;
