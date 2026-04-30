<?php

declare(strict_types=1);

/**
 * Local stock guard + decrement/restore.
 *
 * Stock for storefront items is computed against the underlying `products` table:
 *   - simple   : products.stock_qty
 *   - combined : SUM(child products.stock_qty) -- effective stock = max child qty
 *                (here each variant child is independent, so we check that the
 *                 specific child has enough stock for the line.)
 *   - pack     : MIN(floor(child.stock_qty / child.quantity))
 *
 * NOTE: ERP is the source of truth. We mirror locally so the storefront can
 *       prevent over-selling between sync cycles. The next ERP push corrects
 *       any drift.
 */
final class StockService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Validate that every resolved order item has enough stock.
     *
     * @param array $items Items in the format produced by api/place-order.php
     *                     (kind, product_id, erp_product_id, quantity, sku, display_label, parent_storefront_id, ...)
     * @return array{ok:bool, insufficient: array<int, array{sku:string, label:string, requested:float, available:float}>}
     */
    public function checkAvailability(array $items): array
    {
        $needs = [];
        foreach ($items as $it) {
            $kind = (string)($it['kind'] ?? 'simple');
            if ($kind === 'pack') continue; /* parent display-only row, real demand is in pack_child rows */
            $pid = (int)($it['product_id'] ?? 0);
            if ($pid <= 0) continue;
            $qty = (float)($it['quantity'] ?? 0);
            if ($qty <= 0) continue;
            if (!isset($needs[$pid])) {
                $needs[$pid] = [
                    'qty' => 0.0,
                    'sku' => (string)($it['sku'] ?? ''),
                    'label' => (string)($it['display_label'] ?? ''),
                ];
            }
            $needs[$pid]['qty'] += $qty;
        }

        if (!$needs) return ['ok' => true, 'insufficient' => []];

        $ids = array_keys($needs);
        $place = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id, name, sku, stock_qty FROM products WHERE id IN ($place)");
        $st->execute($ids);
        $stockByPid = [];
        foreach ($st->fetchAll() as $row) {
            $stockByPid[(int)$row['id']] = $row;
        }

        $insufficient = [];
        foreach ($needs as $pid => $info) {
            $row = $stockByPid[$pid] ?? null;
            if (!$row) {
                $insufficient[] = [
                    'sku' => $info['sku'],
                    'label' => $info['label'],
                    'requested' => $info['qty'],
                    'available' => 0.0,
                ];
                continue;
            }
            $available = (float)$row['stock_qty'];
            if ($info['qty'] - $available > 0.0001) {
                $insufficient[] = [
                    'sku' => $info['sku'] !== '' ? $info['sku'] : (string)$row['sku'],
                    'label' => $info['label'] !== '' ? $info['label'] : (string)$row['name'],
                    'requested' => $info['qty'],
                    'available' => $available,
                ];
            }
        }

        return ['ok' => empty($insufficient), 'insufficient' => $insufficient];
    }

    /**
     * Decrement local stock for the given resolved items. Atomic per-row UPDATE
     * with a stock guard to prevent races. Should be called inside an existing
     * transaction or on its own.
     *
     * @return array{ok:bool, decremented: array<int, float>, failed: array}
     */
    public function decrement(array $items): array
    {
        $needs = [];
        foreach ($items as $it) {
            $kind = (string)($it['kind'] ?? 'simple');
            if ($kind === 'pack') continue;
            $pid = (int)($it['product_id'] ?? 0);
            if ($pid <= 0) continue;
            $qty = (float)($it['quantity'] ?? 0);
            if ($qty <= 0) continue;
            $needs[$pid] = ($needs[$pid] ?? 0.0) + $qty;
        }

        $decremented = [];
        $failed = [];
        $st = $this->db->prepare(
            'UPDATE products
                SET stock_qty = stock_qty - :q
              WHERE id = :id AND stock_qty >= :q2'
        );
        foreach ($needs as $pid => $qty) {
            $ok = $st->execute([':q' => $qty, ':id' => $pid, ':q2' => $qty]);
            if ($ok && $st->rowCount() > 0) {
                $decremented[$pid] = $qty;
            } else {
                $failed[$pid] = $qty;
            }
        }

        return ['ok' => empty($failed), 'decremented' => $decremented, 'failed' => $failed];
    }

    /**
     * Restore local stock for an order's items. Used when an order is cancelled.
     *
     * @param array $orderItems Rows from order_items (with product_id, quantity, kind)
     */
    public function restore(array $orderItems): void
    {
        $st = $this->db->prepare(
            'UPDATE products SET stock_qty = stock_qty + :q WHERE id = :id'
        );
        foreach ($orderItems as $it) {
            $kind = (string)($it['kind'] ?? 'simple');
            if ($kind === 'pack') continue;
            $pid = (int)($it['product_id'] ?? 0);
            if ($pid <= 0) continue;
            $qty = (float)($it['quantity'] ?? 0);
            if ($qty <= 0) continue;
            $st->execute([':q' => $qty, ':id' => $pid]);
        }
    }
}
