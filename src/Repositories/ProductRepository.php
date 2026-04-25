<?php

declare(strict_types=1);

final class ProductRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function upsertFromErp(array $product): void
    {
        $sql = <<<SQL
INSERT INTO products (
    erp_product_id, sku, name, description, category_name, brand_name,
    unit_short_name, image_url, price, stock_qty, is_active, updated_at
) VALUES (
    :erp_product_id, :sku, :name, :description, :category_name, :brand_name,
    :unit_short_name, :image_url, :price, :stock_qty, :is_active, NOW()
)
ON DUPLICATE KEY UPDATE
    sku = VALUES(sku),
    name = VALUES(name),
    description = VALUES(description),
    category_name = VALUES(category_name),
    brand_name = VALUES(brand_name),
    unit_short_name = VALUES(unit_short_name),
    image_url = VALUES(image_url),
    price = VALUES(price),
    stock_qty = VALUES(stock_qty),
    is_active = VALUES(is_active),
    updated_at = NOW()
SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'erp_product_id' => (int)$product['id'],
            'sku' => (string)($product['sku'] ?? ''),
            'name' => (string)($product['name'] ?? ''),
            'description' => (string)($product['description'] ?? ''),
            'category_name' => (string)($product['category'] ?? ''),
            'brand_name' => (string)($product['brand'] ?? ''),
            'unit_short_name' => (string)($product['unit'] ?? 'Pc'),
            'image_url' => $product['image_url'] ?: null,
            'price' => (float)($product['price'] ?? 0),
            'stock_qty' => (float)($product['stock'] ?? 0),
            'is_active' => (int)($product['is_active'] ?? 1),
        ]);
    }

    public function searchSuggestions(string $query, int $limit = 10): array
    {
        $sql = <<<SQL
SELECT p.id, p.erp_product_id, p.sku, p.name,
       COALESCE(po.override_image_url, p.image_url) AS image_url,
       COALESCE(po.override_price, p.price) AS price,
       COALESCE(po.override_badge, '') AS badge
FROM products p
LEFT JOIN product_overrides po ON po.product_id = p.id
WHERE p.is_active = 1
    AND (
            LOWER(p.name) LIKE LOWER(:q)
            OR LOWER(COALESCE(po.override_title, '')) LIKE LOWER(:q)
            OR LOWER(p.sku) LIKE LOWER(:q)
            OR LOWER(p.category_name) LIKE LOWER(:q)
            OR LOWER(COALESCE(p.brand_name, '')) LIKE LOWER(:q)
    )
ORDER BY p.stock_qty > 0 DESC, p.name ASC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listProducts(string $query = '', int $limit = 24, int $offset = 0): array
    {
        $where = 'p.is_active = 1';
        $params = [];

        if ($query !== '') {
            $where .= " AND (
                LOWER(p.name) LIKE LOWER(:q)
                OR LOWER(COALESCE(po.override_title, '')) LIKE LOWER(:q)
                OR LOWER(p.sku) LIKE LOWER(:q)
                OR LOWER(p.category_name) LIKE LOWER(:q)
                OR LOWER(COALESCE(p.brand_name, '')) LIKE LOWER(:q)
            )";
            $params['q'] = '%' . $query . '%';
        }

        $sql = <<<SQL
SELECT p.id, p.erp_product_id, p.sku, p.name,
       COALESCE(po.override_slug, CONCAT('product-', p.erp_product_id)) AS slug,
       COALESCE(NULLIF(po.override_title, ''), p.name) AS display_name,
       COALESCE(NULLIF(po.override_description, ''), p.description) AS display_description,
       COALESCE(po.override_image_url, p.image_url) AS image_url,
       COALESCE(po.override_price, p.price) AS price,
       p.stock_qty,
       p.category_name,
       p.brand_name,
       COALESCE(po.override_badge, '') AS badge
FROM products p
LEFT JOIN product_overrides po ON po.product_id = p.id
WHERE $where
ORDER BY p.stock_qty > 0 DESC, p.name ASC
LIMIT :limit OFFSET :offset
SQL;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getByErpId(int $erpProductId): ?array
    {
        $sql = <<<SQL
SELECT p.id, p.erp_product_id, p.sku,
       COALESCE(NULLIF(po.override_title, ''), p.name) AS name,
       COALESCE(NULLIF(po.override_description, ''), p.description) AS description,
       COALESCE(po.override_image_url, p.image_url) AS image_url,
       COALESCE(po.override_price, p.price) AS price,
       p.stock_qty,
       p.category_name,
       p.brand_name,
       COALESCE(po.override_badge, '') AS badge,
       COALESCE(po.override_slug, CONCAT('product-', p.erp_product_id)) AS slug
FROM products p
LEFT JOIN product_overrides po ON po.product_id = p.id
WHERE p.erp_product_id = :erp_product_id
LIMIT 1
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['erp_product_id' => $erpProductId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listBestSellersByCategory(string $categoryName, int $excludeErpId, int $limit = 4): array
    {
        $sql = <<<SQL
SELECT p.id, p.erp_product_id, p.sku,
       COALESCE(NULLIF(po.override_title, ''), p.name) AS display_name,
       COALESCE(po.override_image_url, p.image_url) AS image_url,
       COALESCE(po.override_price, p.price) AS price,
       p.stock_qty,
       p.category_name,
       COALESCE(po.override_badge, '') AS badge
FROM products p
LEFT JOIN product_overrides po ON po.product_id = p.id
WHERE p.is_active = 1
  AND p.erp_product_id <> :exclude_erp_id
  AND LOWER(COALESCE(p.category_name, '')) = LOWER(:category_name)
ORDER BY p.stock_qty > 0 DESC, p.stock_qty DESC, p.updated_at DESC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':exclude_erp_id', $excludeErpId, PDO::PARAM_INT);
        $stmt->bindValue(':category_name', $categoryName, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function saveOverride(int $productId, array $override): void
    {
        $sql = <<<SQL
INSERT INTO product_overrides (
    product_id, override_slug, override_title, override_description,
    override_image_url, override_price, override_badge, updated_at
) VALUES (
    :product_id, :override_slug, :override_title, :override_description,
    :override_image_url, :override_price, :override_badge, NOW()
)
ON DUPLICATE KEY UPDATE
    override_slug = VALUES(override_slug),
    override_title = VALUES(override_title),
    override_description = VALUES(override_description),
    override_image_url = VALUES(override_image_url),
    override_price = VALUES(override_price),
    override_badge = VALUES(override_badge),
    updated_at = NOW()
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'product_id' => $productId,
            'override_slug' => $override['slug'] ?? null,
            'override_title' => $override['title'] ?? null,
            'override_description' => $override['description'] ?? null,
            'override_image_url' => $override['image_url'] ?? null,
            'override_price' => $override['price'] !== '' ? $override['price'] : null,
            'override_badge' => $override['badge'] ?? null,
        ]);
    }
}
