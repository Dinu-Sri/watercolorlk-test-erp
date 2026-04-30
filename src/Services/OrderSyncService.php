<?php

declare(strict_types=1);

final class OrderSyncService
{
    public function __construct(
        private ErpClient $erpClient,
        private OrderRepository $orderRepository
    ) {
    }

    public function pushPendingOrders(int $batchSize = 25): array
    {
        $pending = $this->orderRepository->getPendingOrders($batchSize);
        $synced = 0;
        $failed = 0;

        foreach ($pending as $order) {
            try {
                $this->pushOrderById((int)$order['id']);
                $synced++;
            } catch (Throwable $e) {
                $this->orderRepository->markSyncFailed((int)$order['id'], $e->getMessage());
                $failed++;
            }
        }

        return [
            'success' => true,
            'pending' => count($pending),
            'synced' => $synced,
            'failed' => $failed,
        ];
    }

    public function pushOrderById(int $orderId): void
    {
        $order = $this->orderRepository->getOrderWithItems($orderId);
        if ($order === null) {
            throw new RuntimeException('Order not found: ' . $orderId);
        }

        $payload = [
            'location_id' => ERP_LOCATION_ID,
            'contact_name' => $order['customer_name'],
            'contact_phone' => $order['customer_phone'],
            'contact_email' => $order['customer_email'],
            'payment_method' => $order['payment_method'],
            'additional_notes' => $order['notes'],
            'source' => 'website',
            'items' => array_values(array_map(
                static fn(array $item): array => [
                    'product_id' => (int)$item['erp_product_id'],
                    'quantity' => (float)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
                ],
                array_filter(
                    $order['items'],
                    /* Skip pack-parent rows: they are display-only with no real ERP SKU.
                       The pack contents are persisted as separate kind='pack_child' lines. */
                    static fn(array $item): bool => (string)($item['kind'] ?? 'simple') !== 'pack'
                        && !empty($item['erp_product_id'])
                )
            )),
        ];

        $erpResponse = $this->erpClient->createOrder($payload);
        $erpSellId = isset($erpResponse['id']) ? (string)$erpResponse['id'] : null;

        $this->orderRepository->markSynced($orderId, $erpSellId);
    }
}
