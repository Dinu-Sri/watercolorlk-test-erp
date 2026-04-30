<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    JsonResponse::send(['ok' => true], 204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::send(['ok' => false, 'error' => 'Method not allowed'], 405);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) {
        JsonResponse::send(['ok' => false, 'error' => 'Invalid JSON payload'], 422);
        exit;
    }

    $code = trim((string)($payload['code'] ?? ''));
    if ($code === '') {
        JsonResponse::send(['ok' => false, 'error' => 'Enter a coupon code.'], 200);
        exit;
    }

    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $customerPhone = trim((string)($payload['customer_phone'] ?? '')) ?: null;

    $db = appDb();

    /* Build the cart shape expected by CouponService::validate(). */
    $lines = [];
    $subtotal = 0.0;
    $catCache = [];

    foreach ($items as $it) {
        $qty = (float)($it['qty'] ?? $it['quantity'] ?? 0);
        $price = (float)($it['price'] ?? $it['unit_price'] ?? 0);
        if ($qty <= 0) continue;
        $amount = $price * $qty;
        $subtotal += $amount;

        $spId = (int)($it['storefront_product_id'] ?? $it['parent_storefront_id'] ?? 0);
        $erpId = (int)($it['erp_product_id'] ?? 0);

        /* Resolve storefront_product_id from erp_product_id if missing (legacy carts). */
        if ($spId <= 0 && $erpId > 0) {
            static $lookupStmt = null;
            if ($lookupStmt === null) {
                $lookupStmt = $db->prepare("SELECT id FROM storefront_products WHERE kind = 'simple' AND erp_product_id = :e LIMIT 1");
            }
            $lookupStmt->execute([':e' => $erpId]);
            $r = $lookupStmt->fetch();
            if ($r) $spId = (int)$r['id'];
        }

        $catIds = [];
        if ($spId > 0) {
            if (!isset($catCache[$spId])) {
                $cs = $db->prepare('SELECT category_id FROM storefront_product_categories WHERE storefront_product_id = :s');
                $cs->execute([':s' => $spId]);
                $catCache[$spId] = array_map(static fn($r): int => (int)$r['category_id'], $cs->fetchAll());
            }
            $catIds = $catCache[$spId];
        }

        $lines[] = [
            'storefront_product_id' => $spId,
            'category_ids' => $catIds,
            'amount' => $amount,
        ];
    }

    $cart = [
        'subtotal' => $subtotal,
        'lines' => $lines,
        'customer_phone' => $customerPhone,
    ];

    $service = new CouponService(new CouponRepository($db));
    $result = $service->validate($code, $cart);

    if (!$result['ok']) {
        JsonResponse::send(['ok' => false, 'error' => $result['error']], 200);
        exit;
    }

    JsonResponse::send([
        'ok' => true,
        'code' => strtoupper($code),
        'coupon_id' => (int)$result['coupon']['id'],
        'discount' => (float)$result['discount'],
        'type' => $result['type'],
        'description' => $result['coupon']['description'] ?? null,
    ], 200);
} catch (Throwable $e) {
    JsonResponse::send(['ok' => false, 'error' => $e->getMessage()], 500);
}
