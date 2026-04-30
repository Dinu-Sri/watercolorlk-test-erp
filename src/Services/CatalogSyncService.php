<?php

declare(strict_types=1);

final class CatalogSyncService
{
    public function __construct(
        private ErpClient $erpClient,
        private ProductRepository $productRepository,
        private ?PDO $db = null
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
                $this->seedStorefrontIfMissing($product);
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

    /**
     * For each newly-seen ERP product, ensure a hidden simple storefront row exists.
     * Existing rows (visible or hidden) are left untouched — admin curates after first sync.
     */
    private function seedStorefrontIfMissing(array $erpProduct): void
    {
        if ($this->db === null) {
            return;
        }
        $erpId = (int)($erpProduct['id'] ?? 0);
        if ($erpId <= 0) {
            return;
        }
        $check = $this->db->prepare(
            "SELECT id FROM storefront_products WHERE kind='simple' AND erp_product_id = :e LIMIT 1"
        );
        $check->execute([':e' => $erpId]);
        if ($check->fetch()) {
            return;
        }
        $name = (string)($erpProduct['name'] ?? ('product-' . $erpId));
        $slugBase = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slugBase = trim($slugBase, '-');
        if ($slugBase === '') $slugBase = 'product-' . $erpId;
        $slug = $slugBase;
        $i = 2;
        $slugCheck = $this->db->prepare('SELECT id FROM storefront_products WHERE slug = :s LIMIT 1');
        while (true) {
            $slugCheck->execute([':s' => $slug]);
            if (!$slugCheck->fetch()) break;
            $slug = $slugBase . '-' . $i;
            $i++;
            if ($i > 200) { $slug = $slugBase . '-erp' . $erpId; break; }
        }
        $insert = $this->db->prepare(
            "INSERT INTO storefront_products
             (kind, slug, title, description, hero_image_url, base_price, erp_product_id, is_visible, created_at, updated_at)
             VALUES ('simple', :slug, :title, :desc, :hero, :price, :erp, 0, NOW(), NOW())"
        );
        $insert->execute([
            ':slug' => $slug,
            ':title' => $name,
            ':desc' => $erpProduct['description'] ?? null,
            ':hero' => $erpProduct['image_url'] ?? null,
            ':price' => (float)($erpProduct['price'] ?? 0),
            ':erp' => $erpId,
        ]);
    }
}
