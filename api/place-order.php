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
    $productRepo = new ProductRepository($db);
    $storefrontRepo = new StorefrontRepository($db);

    /**
     * Explode each cart line into one or more order_items rows:
     *   simple   -> 1 row, kind='simple'
     *   combined -> 1 row, kind='variant', erp_product_id of the chosen variant
     *   pack     -> 1 display-only parent row (kind='pack', erp_product_id=0; OrderSyncService skips these)
     *               + N child rows (kind='pack_child') with proportional unit_price allocation.
     */
    $resolvedItems = [];
    $couponLines = [];
    $subtotalCalc = 0.0;

    $getStorefront = static function(int $id) use ($db): ?array {
        if ($id <= 0) return null;
        $st = $db->prepare('SELECT * FROM storefront_products WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    };
    $getStorefrontCats = static function(int $id) use ($db): array {
        if ($id <= 0) return [];
        $st = $db->prepare('SELECT category_id FROM storefront_product_categories WHERE storefront_product_id = :id');
        $st->execute([':id' => $id]);
        return array_map(static fn($r): int => (int)$r['category_id'], $st->fetchAll());
    };
    $getChild = static function(int $childId) use ($db): ?array {
        if ($childId <= 0) return null;
        $st = $db->prepare(
            'SELECT spc.*, p.sku, p.name AS product_name, p.erp_product_id, p.price AS product_price
             FROM storefront_product_children spc
             INNER JOIN products p ON p.id = spc.child_product_id
             WHERE spc.id = :id LIMIT 1'
        );
        $st->execute([':id' => $childId]);
        $r = $st->fetch();
        return $r ?: null;
    };
    $getPackChildren = static function(int $parentId) use ($db): array {
        $st = $db->prepare(
            'SELECT spc.*, p.sku, p.name AS product_name, p.erp_product_id, p.price AS product_price
             FROM storefront_product_children spc
             INNER JOIN products p ON p.id = spc.child_product_id
             WHERE spc.parent_storefront_id = :p AND spc.context = "pack_item"
             ORDER BY spc.sort_order, spc.id'
        );
        $st->execute([':p' => $parentId]);
        return $st->fetchAll();
    };

    foreach ($payload['items'] as $rawItem) {
        $kind = (string)($rawItem['kind'] ?? 'simple');
        $cartQty = max(1, (int)($rawItem['quantity'] ?? 1));
        $unitPrice = (float)($rawItem['unit_price'] ?? 0);
        $lineAmount = $unitPrice * $cartQty;
        $subtotalCalc += $lineAmount;

        if ($kind === 'combined') {
            $parentSpId = (int)($rawItem['parent_storefront_id'] ?? $rawItem['storefront_product_id'] ?? 0);
            $variantChildId = (int)($rawItem['variant_child_id'] ?? 0);
            $child = $getChild($variantChildId);
            if (!$child) {
                JsonResponse::send(['success' => false, 'error' => 'Variant not found for combined product line.'], 422);
                exit;
            }
            $erpId = (int)$child['erp_product_id'];
            $row = $productRepo->getByErpId($erpId);
            if (!$row) {
                JsonResponse::send(['success' => false, 'error' => 'Unknown product (variant): ' . $erpId], 422);
                exit;
            }
            $sp = $getStorefront($parentSpId);
            $resolvedItems[] = [
                'kind' => 'variant',
                'product_id' => (int)$row['id'],
                'erp_product_id' => $erpId,
                'sku' => (string)($child['sku'] ?? $row['sku'] ?? ''),
                'quantity' => $cartQty,
                'unit_price' => $unitPrice,
                'storefront_product_id' => $parentSpId ?: null,
                'parent_storefront_id' => $parentSpId ?: null,
                'display_label' => trim((string)($sp['title'] ?? $row['name']) . ' — ' . (string)($child['variant_label'] ?? '')),
            ];
            $couponLines[] = [
                'storefront_product_id' => $parentSpId,
                'category_ids' => $getStorefrontCats($parentSpId),
                'amount' => $lineAmount,
            ];
            continue;
        }

        if ($kind === 'pack') {
            $parentSpId = (int)($rawItem['parent_storefront_id'] ?? $rawItem['storefront_product_id'] ?? 0);
            $sp = $getStorefront($parentSpId);
            if (!$sp || $sp['kind'] !== 'pack') {
                JsonResponse::send(['success' => false, 'error' => 'Pack product not found.'], 422);
                exit;
            }
            $packChildren = $getPackChildren($parentSpId);
            if (!$packChildren) {
                JsonResponse::send(['success' => false, 'error' => 'Pack has no contents configured.'], 422);
                exit;
            }
            /* Display-only parent row. erp_product_id=0 + product_id=0 -> OrderSyncService skips it. */
            $resolvedItems[] = [
                'kind' => 'pack',
                'product_id' => 0,
                'erp_product_id' => 0,
                'sku' => '',
                'quantity' => $cartQty,
                'unit_price' => $unitPrice,
                'storefront_product_id' => $parentSpId,
                'parent_storefront_id' => null,
                'display_label' => (string)($sp['title'] ?? 'Pack'),
            ];

            $weights = [];
            $totalWeight = 0.0;
            foreach ($packChildren as $c) {
                $w = max(0.01, (float)$c['quantity'] * (float)$c['product_price']);
                $weights[] = $w;
                $totalWeight += $w;
            }
            foreach ($packChildren as $i => $c) {
                $childErp = (int)$c['erp_product_id'];
                $row = $productRepo->getByErpId($childErp);
                if (!$row) {
                    JsonResponse::send(['success' => false, 'error' => 'Unknown product in pack: ' . $childErp], 422);
                    exit;
                }
                $childCartQty = (float)$c['quantity'] * $cartQty;
                $allocated = $totalWeight > 0 ? ($unitPrice * $cartQty) * ($weights[$i] / $totalWeight) : 0;
                $childUnitPrice = $childCartQty > 0 ? round($allocated / $childCartQty, 2) : 0;
                $resolvedItems[] = [
                    'kind' => 'pack_child',
                    'product_id' => (int)$row['id'],
                    'erp_product_id' => $childErp,
                    'sku' => (string)($c['sku'] ?? ''),
                    'quantity' => $childCartQty,
                    'unit_price' => $childUnitPrice,
                    'storefront_product_id' => null,
                    'parent_storefront_id' => $parentSpId,
                    'display_label' => (string)($c['product_name'] ?? ''),
                ];
            }
            $couponLines[] = [
                'storefront_product_id' => $parentSpId,
                'category_ids' => $getStorefrontCats($parentSpId),
                'amount' => $lineAmount,
            ];
            continue;
        }

        /* simple */
        $erpId = (int)($rawItem['erp_product_id'] ?? 0);
        if ($erpId <= 0) {
            JsonResponse::send(['success' => false, 'error' => 'Each item requires erp_product_id'], 422);
            exit;
        }
        $row = $productRepo->getByErpId($erpId);
        if (!$row) {
            JsonResponse::send(['success' => false, 'error' => 'Unknown product: ' . $erpId], 422);
            exit;
        }
        $spId = (int)($rawItem['storefront_product_id'] ?? 0);
        if ($spId <= 0) {
            $simpleSp = $storefrontRepo->getSimpleByErpId($erpId);
            if ($simpleSp) $spId = (int)$simpleSp['id'];
        }
        $resolvedItems[] = [
            'kind' => 'simple',
            'product_id' => (int)$row['id'],
            'erp_product_id' => $erpId,
            'sku' => (string)($rawItem['sku'] ?? $row['sku'] ?? ''),
            'quantity' => $cartQty,
            'unit_price' => $unitPrice !== 0.0 ? $unitPrice : (float)$row['price'],
            'storefront_product_id' => $spId ?: null,
            'parent_storefront_id' => null,
            'display_label' => (string)($rawItem['name'] ?? $row['name'] ?? ''),
        ];
        $couponLines[] = [
            'storefront_product_id' => $spId,
            'category_ids' => $getStorefrontCats($spId),
            'amount' => $lineAmount,
        ];
    }

    /* ===== Coupon validation ===== */
    $couponId = null;
    $couponCode = null;
    $couponDiscount = 0.0;
    $couponType = null;
    $couponInput = trim((string)($payload['coupon_code'] ?? ''));
    if ($couponInput !== '') {
        $couponService = new CouponService(new CouponRepository($db));
        $cv = $couponService->validate($couponInput, [
            'subtotal' => $subtotalCalc,
            'lines' => $couponLines,
            'customer_phone' => (string)$payload['customer_phone'],
        ]);
        if (!$cv['ok']) {
            JsonResponse::send(['success' => false, 'error' => 'Coupon: ' . $cv['error']], 422);
            exit;
        }
        $couponId = (int)$cv['coupon']['id'];
        $couponCode = strtoupper($couponInput);
        $couponDiscount = (float)$cv['discount'];
        $couponType = (string)$cv['type'];
    }

    /* Authoritative totals (recomputed server-side; client values ignored). */
    $subtotal = round($subtotalCalc, 2);
    $shipping = $subtotal >= 5000 ? 0.0 : ($subtotal > 0 ? 350.0 : 0.0);
    if ($couponType === 'free_ship') {
        $shipping = 0.0;
    }
    $total = round(max(0.0, $subtotal - $couponDiscount + $shipping), 2);

    $payload['items'] = $resolvedItems;
    $payload['subtotal_amount'] = $subtotal;
    $payload['shipping_amount'] = $shipping;
    $payload['discount_amount'] = round($couponDiscount, 2);
    $payload['total_amount']    = $total;
    $payload['coupon_id']       = $couponId;
    $payload['coupon_code']     = $couponCode;

    /* Attach logged-in user (if any) so order shows up in their account. */
    $payload['user_id'] = appUserAuth()->currentUserId();

    /* ===== Server-side stock guard (recheck against products.stock_qty) ===== */
    $stockSvc = new StockService($db);
    $stockCheck = $stockSvc->checkAvailability($resolvedItems);
    if (!$stockCheck['ok']) {
        JsonResponse::send([
            'success' => false,
            'error' => 'Some items are out of stock or have less stock than requested. Please update your cart.',
            'insufficient' => $stockCheck['insufficient'],
        ], 409);
        exit;
    }

    $orderRepo = new OrderRepository($db);
    $orderId = $orderRepo->createOrder($payload);

    /* Decrement local stock immediately. ERP push is the authoritative truth and
       will correct any drift on the next sync cycle. */
    try {
        $stockSvc->decrement($resolvedItems);
    } catch (Throwable $stockErr) {
        error_log('stock-decrement-failed for order #' . $orderId . ': ' . $stockErr->getMessage());
    }

    if ($couponId !== null) {
        try {
            (new CouponRepository($db))->recordRedemption(
                $couponId,
                $orderId,
                $couponDiscount,
                (string)$payload['customer_phone']
            );
        } catch (Throwable $rerr) {
            error_log('coupon-redemption-failed: ' . $rerr->getMessage());
        }
    }

    $syncService = new OrderSyncService(appErpClient(), $orderRepo);
    $syncStatus = 'queued';

    try {
        $syncService->pushOrderById($orderId);
        $syncStatus = 'synced';
    } catch (Throwable $syncError) {
        $orderRepo->markSyncFailed($orderId, $syncError->getMessage());
        $syncStatus = 'failed';
    }

    /* Order confirmation email (best-effort, non-fatal). */
    if (!empty($payload['customer_email'])) {
        try {
            $rows = '';
            foreach ($resolvedItems as $it) {
                if ($it['kind'] === 'pack_child') continue; /* hide as part of parent */
                $qty = rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.');
                $line = (float)$it['unit_price'] * (float)$it['quantity'];
                $rows .= '<tr>'
                       . '<td style="padding:6px 8px;border-bottom:1px solid #eef0f4;">' . htmlspecialchars((string)$it['display_label']) . '</td>'
                       . '<td style="padding:6px 8px;border-bottom:1px solid #eef0f4;text-align:center;">' . htmlspecialchars($qty) . '</td>'
                       . '<td style="padding:6px 8px;border-bottom:1px solid #eef0f4;text-align:right;">LKR ' . number_format($line, 2) . '</td>'
                       . '</tr>';
            }
            $totalsHtml = '<table style="width:100%;border-collapse:collapse;margin-top:10px;font:600 .92rem/1.4 system-ui,sans-serif;color:#0f2440;">'
                       . '<tr><td>Subtotal</td><td style="text-align:right;">LKR ' . number_format($subtotal, 2) . '</td></tr>';
            if ($couponDiscount > 0) {
                $totalsHtml .= '<tr><td>Discount' . ($couponCode ? ' (' . htmlspecialchars($couponCode) . ')' : '') . '</td>'
                            . '<td style="text-align:right;color:#17633e;">− LKR ' . number_format($couponDiscount, 2) . '</td></tr>';
            }
            $totalsHtml .= '<tr><td>Shipping</td><td style="text-align:right;">' . ($shipping == 0.0 ? 'FREE' : 'LKR ' . number_format($shipping, 2)) . '</td></tr>'
                        . '<tr><td style="font-weight:800;border-top:1px solid #0f2440;padding-top:6px;">Total</td>'
                        . '<td style="text-align:right;font-weight:800;border-top:1px solid #0f2440;padding-top:6px;">LKR ' . number_format($total, 2) . '</td></tr>'
                        . '</table>';
            $body = '<p>Hi ' . htmlspecialchars((string)$payload['customer_name']) . ',</p>'
                  . '<p>Thanks for your order — we\'ve received it and will be in touch on WhatsApp shortly.</p>'
                  . '<p style="font:700 .95rem/1 \'Montserrat\',sans-serif;color:#0f2440;margin:18px 0 6px;">Order #' . (int)$orderId . '</p>'
                  . '<table style="width:100%;border-collapse:collapse;font:400 .92rem/1.4 system-ui,sans-serif;color:#0f2440;">'
                  . '<thead><tr style="text-align:left;background:#fff7e8;">'
                  . '<th style="padding:6px 8px;">Item</th><th style="padding:6px 8px;text-align:center;">Qty</th><th style="padding:6px 8px;text-align:right;">Total</th>'
                  . '</tr></thead><tbody>' . $rows . '</tbody></table>'
                  . $totalsHtml;
            $cta = appUserAuth()->currentUserId() ? (SITE_URL . '/account/order.php?id=' . (int)$orderId) : '';
            $html = appMailer()->renderLayout(
                'Order received · Watercolor.LK',
                $body,
                $cta,
                $cta ? 'View order in my account' : ''
            );
            appMailer()->send((string)$payload['customer_email'], 'Order #' . (int)$orderId . ' · Watercolor.LK', $html);
        } catch (Throwable $emailErr) {
            error_log('order-email-failed: ' . $emailErr->getMessage());
        }
    }

    JsonResponse::send([
        'success' => true,
        'order_id' => $orderId,
        'erp_sync' => $syncStatus,
        'totals' => [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => round($couponDiscount, 2),
            'total' => $total,
        ],
    ], 201);
} catch (Throwable $e) {
    JsonResponse::send(['success' => false, 'error' => $e->getMessage()], 500);
}
