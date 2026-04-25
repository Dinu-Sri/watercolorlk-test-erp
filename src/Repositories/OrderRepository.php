<?php

declare(strict_types=1);

final class OrderRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function createOrder(array $payload): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO orders (customer_name, customer_phone, customer_email, payment_method, notes, status, erp_sync_status, created_at, updated_at)
                 VALUES (:name, :phone, :email, :payment, :notes, :status, :erp_sync_status, NOW(), NOW())'
            );
            $stmt->execute([
                'name' => $payload['customer_name'],
                'phone' => $payload['customer_phone'],
                'email' => $payload['customer_email'] ?? null,
                'payment' => $payload['payment_method'],
                'notes' => $payload['notes'] ?? null,
                'status' => 'pending',
                'erp_sync_status' => 'pending',
            ]);

            $orderId = (int)$this->db->lastInsertId();

            $itemStmt = $this->db->prepare(
                'INSERT INTO order_items (order_id, product_id, erp_product_id, sku, quantity, unit_price, created_at)
                 VALUES (:order_id, :product_id, :erp_product_id, :sku, :quantity, :unit_price, NOW())'
            );

            foreach ($payload['items'] as $item) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'erp_product_id' => $item['erp_product_id'],
                    'sku' => $item['sku'] ?? '',
                    'quantity' => (float)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
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
}
