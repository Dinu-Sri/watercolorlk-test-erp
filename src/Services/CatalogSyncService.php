<?php

declare(strict_types=1);

final class CatalogSyncService
{
    public function __construct(
        private ErpClient $erpClient,
        private ProductRepository $productRepository
    ) {
    }

    public function syncProducts(int $maxPages = 10, int $perPage = 200): array
    {
        $synced = 0;
        $page = 1;

        while ($page <= $maxPages) {
            $chunk = $this->erpClient->getProducts($perPage, $page);
            $products = $chunk['products'] ?? [];

            if ($products === []) {
                break;
            }

            foreach ($products as $product) {
                $this->productRepository->upsertFromErp($product);
                $synced++;
            }

            if (count($products) < $perPage) {
                break;
            }
            $page++;
        }

        return [
            'success' => true,
            'synced_products' => $synced,
            'pages_processed' => $page,
        ];
    }
}
