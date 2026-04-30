<?php

declare(strict_types=1);

final class OrderRepository
{
    private ?bool $hasUserIdColumn = null;

    public function __construct(private PDO $db)
    {
    }

    private function ordersHasUserIdColumn(): bool
    {
        if ($this->hasUserIdColumn !== null) return $this->hasUserIdColumn;
        try {
            $st = $this->db->query("SHOW COLUMNS FROM orders LIKE 'user_id'");
            $this->hasUserIdColumn = $st && $st->fetch() !== false;
        } catch (Throwable $e) {
            $this->hasUserIdColumn = false;
        }
        return $this->hasUserIdColumn;
    }

    public function createOrder(array $payload): int
    {
        $this->db->beginTransaction();
        try {
            $hasUserId = $this->ordersHasUserIdColumn();
            $userIdCol = $hasUserId ? 'user_id, ' : '';
            $userIdVal = $hasUserId ? ':user_id, ' : '';
            $stmt = $this->db->prepare(
                "INSERT INTO orders (customer_name, customer_phone, customer_email, {$userIdCol}payment_method, notes,
                                     status, erp_sync_status,
                                     subtotal_amount, shipping_amount, discount_amount, total_amount,
                                     coupon_id, coupon_code,
                                     created_at, updated_at)
                 VALUES (:name, :phone, :email, {$userIdVal}:payment, :notes,
                         :status, :erp_sync_status,
                         :subtotal, :shipping, :discount, :total,
                         :coupon_id, :coupon_code,
                         NOW(), NOW())"
            );
            $params = [
                'name' => $payload['customer_name'],
                'phone' => $payload['customer_phone'],
                'email' => $payload['customer_email'] ?? null,
                'payment' => $payload['payment_method'],
                'notes' => $payload['notes'] ?? null,
                'status' => 'pending',
                'erp_sync_status' => 'pending',
                'subtotal' => (float)($payload['subtotal_amount'] ?? 0),
                'shipping' => (float)($payload['shipping_amount'] ?? 0),
                'discount' => (float)($payload['discount_amount'] ?? 0),
                'total'    => (float)($payload['total_amount'] ?? 0),
                'coupon_id'   => isset($payload['coupon_id']) && $payload['coupon_id'] !== '' ? (int)$payload['coupon_id'] : null,
                'coupon_code' => $payload['coupon_code'] ?? null,
            ];
            if ($hasUserId) {
                $params['user_id'] = isset($payload['user_id']) && $payload['user_id'] ? (int)$payload['user_id'] : null;
            }
            $stmt->execute($params);

            $orderId = (int)$this->db->lastInsertId();

            $itemStmt = $this->db->prepare(
                'INSERT INTO order_items (order_id, product_id, erp_product_id, sku, quantity, unit_price,
                                          kind, storefront_product_id, parent_storefront_id, display_label,
                                          created_at)
                 VALUES (:order_id, :product_id, :erp_product_id, :sku, :quantity, :unit_price,
                         :kind, :spid, :parent_spid, :label,
                         NOW())'
            );

            foreach ($payload['items'] as $item) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'erp_product_id' => $item['erp_product_id'],
                    'sku' => $item['sku'] ?? '',
                    'quantity' => (float)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
                    'kind' => $item['kind'] ?? 'simple',
                    'spid' => isset($item['storefront_product_id']) && $item['storefront_product_id'] ? (int)$item['storefront_product_id'] : null,
                    'parent_spid' => isset($item['parent_storefront_id']) && $item['parent_storefront_id'] ? (int)$item['parent_storefront_id'] : null,
                    'label' => $item['display_label'] ?? null,
                ]);
            }

            $this->db->commit();
            return $orderId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getPendingOrders(int $limit = 25): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM orders WHERE erp_sync_status = :status ORDER BY id ASC LIMIT :limit'
        );
        $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getOrderWithItems(int $orderId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        $itemStmt = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
        $itemStmt->execute(['order_id' => $orderId]);
        $order['items'] = $itemStmt->fetchAll();
        return $order;
    }

    public function markSynced(int $orderId, ?string $erpSellId = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE orders SET erp_sync_status = :status, erp_sell_id = :erp_sell_id, synced_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'synced',
            'erp_sell_id' => $erpSellId,
            'id' => $orderId,
        ]);
    }

    public function markSyncFailed(int $orderId, string $error): void
    {
        $stmt = $this->db->prepare(
            'UPDATE orders SET erp_sync_status = :status, sync_error = :sync_error, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'failed',
            'sync_error' => substr($error, 0, 1000),
            'id' => $orderId,
        ]);
    }

    /* ===== Customer (account) helpers ===== */

    public function listForUser(int $userId, int $limit = 50): array
    {
        $st = $this->db->prepare(
            'SELECT id, status, erp_sync_status, total_amount, payment_method, created_at,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
             FROM orders o
             WHERE user_id = :u
             ORDER BY id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute([':u' => $userId]);
        return $st->fetchAll();
    }

    public function getUserOrder(int $userId, int $orderId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :u LIMIT 1');
        $st->execute([':id' => $orderId, ':u' => $userId]);
        $order = $st->fetch();
        if (!$order) return null;
        $is = $this->db->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC');
        $is->execute([':id' => $orderId]);
        $order['items'] = $is->fetchAll();
        return $order;
    }

    /* ===== Admin listing ===== */

    public function adminList(array $filters = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $where = []; $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(o.id = :qid OR o.customer_name LIKE :q OR o.customer_phone LIKE :q OR o.customer_email LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
            $params[':qid'] = ctype_digit((string)$filters['q']) ? (int)$filters['q'] : 0;
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['pending','processing','completed','cancelled'], true)) {
            $where[] = 'o.status = :st';
            $params[':st'] = $filters['status'];
        }
        if (!empty($filters['sync']) && in_array($filters['sync'], ['pending','synced','failed'], true)) {
            $where[] = 'o.erp_sync_status = :ss';
            $params[':ss'] = $filters['sync'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSt = $this->db->prepare("SELECT COUNT(*) AS c FROM orders o $whereSql");
        $countSt->execute($params);
        $total = (int)$countSt->fetch()['c'];

        $sql = "SELECT o.*,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
                FROM orders o
                $whereSql
                ORDER BY o.id DESC
                LIMIT $perPage OFFSET $offset";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return [
            'rows' => $st->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function setStatus(int $orderId, string $status): void
    {
        if (!in_array($status, ['pending','processing','completed','cancelled'], true)) return;
        $this->db->prepare('UPDATE orders SET status = :s, updated_at = NOW() WHERE id = :id')
                 ->execute([':s' => $status, ':id' => $orderId]);
    }
}
