<?php

declare(strict_types=1);

final class ProductRepository
{
    public function __construct(private PDO $db)
    {
    }

    private function buildSeoSlug(?string $slug, ?string $name, int $erpId): string
    {
        $candidate = trim((string)$slug);
        if ($candidate === '' || preg_match('/^product-\d+$/i', $candidate) === 1) {
            $candidate = (string)$name;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return 'product-' . $erpId;
        }

        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $candidate);
            if (is_string($ascii) && $ascii !== '') {
                $candidate = $ascii;
            }
        }

        $slugified = strtolower($candidate);
        $slugified = preg_replace('/[^a-z0-9]+/i', '-', $slugified) ?? '';
        $slugified = trim($slugified, '-');

        return $slugified !== '' ? $slugified : ('product-' . $erpId);
    }

    private function hydrateSeoSlug(array $row): array
    {
        $row['slug'] = $this->buildSeoSlug(
            isset($row['slug']) ? (string)$row['slug'] : '',
            isset($row['display_name']) ? (string)$row['display_name'] : ((string)($row['name'] ?? '')),
            (int)($row['erp_product_id'] ?? 0)
        );

        return $row;
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
    COALESCE(po.override_slug, CONCAT('product-', p.erp_product_id)) AS slug,
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
        $rows = $stmt->fetchAll();
        return array_map(fn(array $row): array => $this->hydrateSeoSlug($row), $rows);
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
        $rows = $stmt->fetchAll();
        return array_map(fn(array $row): array => $this->hydrateSeoSlug($row), $rows);
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
        if (!$row) {
            return null;
        }

        return $this->hydrateSeoSlug($row);
    }

    public function getBySlug(string $slug): ?array
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
WHERE p.is_active = 1
  AND LOWER(COALESCE(NULLIF(po.override_slug, ''), '')) = LOWER(:slug)
LIMIT 1
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if ($row) {
            return $this->hydrateSeoSlug($row);
        }

        if (preg_match('/^product-(\d+)$/i', $slug, $legacy) === 1) {
            return $this->getByErpId((int)$legacy[1]);
        }

        $normalizedSlug = $this->buildSeoSlug($slug, '', 0);
        $matchSql = <<<SQL
SELECT p.erp_product_id,
       COALESCE(NULLIF(po.override_slug, ''), '') AS slug,
       COALESCE(NULLIF(po.override_title, ''), p.name) AS display_name
FROM products p
LEFT JOIN product_overrides po ON po.product_id = p.id
WHERE p.is_active = 1
SQL;
        $matchStmt = $this->db->query($matchSql);
        $candidates = $matchStmt ? $matchStmt->fetchAll() : [];

        foreach ($candidates as $candidate) {
            $candidateSlug = $this->buildSeoSlug(
                (string)($candidate['slug'] ?? ''),
                (string)($candidate['display_name'] ?? ''),
                (int)($candidate['erp_product_id'] ?? 0)
            );
            if ($candidateSlug === $normalizedSlug) {
                return $this->getByErpId((int)$candidate['erp_product_id']);
            }
        }

        return null;
    }

    public function listFlashDeals(int $limit = 8): array
    {
        $sql = <<<SQL
SELECT p.id, p.erp_product_id, p.sku, p.name,
       COALESCE(po.override_slug, CONCAT('product-', p.erp_product_id)) AS slug,
       COALESCE(NULLIF(po.override_title, ''), p.name) AS display_name,
       COALESCE(po.override_image_url, p.image_url) AS image_url,
       COALESCE(po.override_price, p.price) AS price,
       p.price AS original_price,
       p.stock_qty,
       p.category_name,
       p.brand_name,
       COALESCE(po.override_badge, '') AS badge,
       CASE
            WHEN po.override_price IS NOT NULL AND p.price > 0 AND po.override_price < p.price
                THEN ((p.price - po.override_price) / p.price) * 100
            ELSE 0
       END AS discount_pct
FROM products p
LEFT JOIN product_overrides po ON po.product_id = p.id
WHERE p.is_active = 1
  AND p.stock_qty > 0
  AND (
        (po.override_price IS NOT NULL AND po.override_price < p.price)
        OR p.stock_qty <= 12
  )
ORDER BY discount_pct DESC, p.stock_qty ASC, p.updated_at DESC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return array_map(fn(array $row): array => $this->hydrateSeoSlug($row), $rows);
    }

    public function listBestSellers(int $limit = 10): array
    {
        $sql = <<<SQL
SELECT p.id, p.erp_product_id, p.sku, p.name,
       COALESCE(po.override_slug, CONCAT('product-', p.erp_product_id)) AS slug,
       COALESCE(NULLIF(po.override_title, ''), p.name) AS display_name,
       COALESCE(po.override_image_url, p.image_url) AS image_url,
       COALESCE(po.override_price, p.price) AS price,
       p.stock_qty,
       p.category_name,
       p.brand_name,
       COALESCE(po.override_badge, '') AS badge
FROM products p
LEFT JOIN product_overrides po ON po.product_id = p.id
WHERE p.is_active = 1 AND p.stock_qty > 0
ORDER BY p.stock_qty DESC, p.updated_at DESC
LIMIT :limit
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return array_map(fn(array $row): array => $this->hydrateSeoSlug($row), $rows);
    }

    public function listCategoriesWithCounts(array $categoryKeywords): array
    {
        $results = [];
        $sql = <<<SQL
SELECT COUNT(*) AS cnt
FROM products
WHERE is_active = 1
  AND (
        LOWER(COALESCE(category_name, '')) LIKE LOWER(:kw)
        OR LOWER(COALESCE(name, '')) LIKE LOWER(:kw)
  )
SQL;
        $stmt = $this->db->prepare($sql);
        foreach ($categoryKeywords as $key => $keyword) {
            $stmt->bindValue(':kw', '%' . $keyword . '%', PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch();
            $results[$key] = (int)($row['cnt'] ?? 0);
        }
        return $results;
    }

    public function listBestSellersByCategory(string $categoryName, int $excludeErpId, int $limit = 4): array
    {
        $sql = <<<SQL
SELECT p.id, p.erp_product_id, p.sku,
    COALESCE(po.override_slug, CONCAT('product-', p.erp_product_id)) AS slug,
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
        $rows = $stmt->fetchAll();
        return array_map(fn(array $row): array => $this->hydrateSeoSlug($row), $rows);
    }

    /**
     * Faceted search for the shop page. Returns ['products' => [...], 'total' => int].
     *
     * Filters supported:
     *   q           — keyword
     *   categories  — array of category_name (LIKE match per item, OR'd)
     *   brands      — array of brand_name (exact match per item, OR'd)
     *   min_price   — numeric, inclusive
     *   max_price   — numeric, inclusive
     *   in_stock    — bool, restrict to stock_qty > 0
     *   sort        — relevance | price_asc | price_desc | newest | name
     *   limit       — page size
     *   offset      — page offset
     */
    public function searchProducts(array $filters): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(
                LOWER(p.name) LIKE LOWER(:q)
                OR LOWER(COALESCE(po.override_title, '')) LIKE LOWER(:q)
                OR LOWER(p.sku) LIKE LOWER(:q)
                OR LOWER(COALESCE(p.category_name, '')) LIKE LOWER(:q)
                OR LOWER(COALESCE(p.brand_name, '')) LIKE LOWER(:q)
            )";
            $params['q'] = '%' . $q . '%';
        }

        $categories = array_filter(array_map('trim', (array)($filters['categories'] ?? [])), fn($v) => $v !== '');
        if (!empty($categories)) {
            $catParts = [];
            foreach (array_values($categories) as $i => $cat) {
                $key = 'cat' . $i;
                $catParts[] = "LOWER(COALESCE(p.category_name, '')) LIKE LOWER(:$key)";
                $params[$key] = '%' . $cat . '%';
            }
            $where[] = '(' . implode(' OR ', $catParts) . ')';
        }

        $brands = array_filter(array_map('trim', (array)($filters['brands'] ?? [])), fn($v) => $v !== '');
        if (!empty($brands)) {
            $brandParts = [];
            foreach (array_values($brands) as $i => $brand) {
                $key = 'brand' . $i;
                $brandParts[] = "LOWER(COALESCE(p.brand_name, '')) = LOWER(:$key)";
                $params[$key] = $brand;
            }
            $where[] = '(' . implode(' OR ', $brandParts) . ')';
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '' && is_numeric($filters['min_price'])) {
            $where[] = 'COALESCE(po.override_price, p.price) >= :min_price';
            $params['min_price'] = (float)$filters['min_price'];
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== '' && is_numeric($filters['max_price'])) {
            $where[] = 'COALESCE(po.override_price, p.price) <= :max_price';
            $params['max_price'] = (float)$filters['max_price'];
        }
        if (!empty($filters['in_stock'])) {
            $where[] = 'p.stock_qty > 0';
        }

        $sort = (string)($filters['sort'] ?? 'relevance');
        switch ($sort) {
            case 'price_asc':  $orderBy = 'COALESCE(po.override_price, p.price) ASC, p.name ASC'; break;
            case 'price_desc': $orderBy = 'COALESCE(po.override_price, p.price) DESC, p.name ASC'; break;
            case 'newest':     $orderBy = 'p.updated_at DESC, p.id DESC'; break;
            case 'name':       $orderBy = 'p.name ASC'; break;
            case 'relevance':
            default:
                if ($q !== '') {
                    /* Relevance: exact-name match → starts-with → contains, then in-stock first */
                    $orderBy = "
                        CASE WHEN LOWER(p.name) = LOWER(:rel_exact) THEN 0
                             WHEN LOWER(p.name) LIKE LOWER(:rel_prefix) THEN 1
                             ELSE 2 END,
                        (p.stock_qty > 0) DESC, p.stock_qty DESC, p.name ASC";
                    $params['rel_exact']  = $q;
                    $params['rel_prefix'] = $q . '%';
                } else {
                    $orderBy = '(p.stock_qty > 0) DESC, p.stock_qty DESC, p.updated_at DESC';
                }
        }

        $whereSql = implode(' AND ', $where);
        $limit  = max(1, min(96, (int)($filters['limit']  ?? 24)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = <<<SQL
SELECT p.id, p.erp_product_id, p.sku, p.name,
       COALESCE(po.override_slug, CONCAT('product-', p.erp_product_id)) AS slug,
       COALESCE(NULLIF(po.override_title, ''), p.name) AS display_name,
       COALESCE(NULLIF(po.override_description, ''), p.description) AS display_description,
       COALESCE(po.override_image_url, p.image_url) AS image_url,
       COALESCE(po.override_price, p.price) AS price,
       p.price AS original_price,
       p.stock_qty,
       p.category_name,
       p.brand_name,
       COALESCE(po.override_badge, '') AS badge
FROM products p
LEFT JOIN product_overrides po ON po.product_id = p.id
WHERE $whereSql
ORDER BY $orderBy
LIMIT :limit OFFSET :offset
SQL;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_float($value) ? PDO::PARAM_STR : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_map(fn(array $r): array => $this->hydrateSeoSlug($r), $stmt->fetchAll());

        $countSql = "SELECT COUNT(*) AS c FROM products p LEFT JOIN product_overrides po ON po.product_id = p.id WHERE $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            if (in_array($key, ['rel_exact', 'rel_prefix'], true)) continue;
            $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)($countStmt->fetch()['c'] ?? 0);

        return ['products' => $rows, 'total' => $total];
    }

    /** Distinct categories with counts of active products. */
    public function listAllCategories(): array
    {
        $sql = <<<SQL
SELECT TRIM(COALESCE(category_name, '')) AS name, COUNT(*) AS cnt
FROM products
WHERE is_active = 1 AND TRIM(COALESCE(category_name, '')) <> ''
GROUP BY TRIM(COALESCE(category_name, ''))
ORDER BY cnt DESC, name ASC
SQL;
        $stmt = $this->db->query($sql);
        return $stmt ? array_map(static fn(array $r): array => ['name' => (string)$r['name'], 'count' => (int)$r['cnt']], $stmt->fetchAll()) : [];
    }

    /** Distinct brands with counts. */
    public function listAllBrands(): array
    {
        $sql = <<<SQL
SELECT TRIM(COALESCE(brand_name, '')) AS name, COUNT(*) AS cnt
FROM products
WHERE is_active = 1 AND TRIM(COALESCE(brand_name, '')) <> ''
GROUP BY TRIM(COALESCE(brand_name, ''))
ORDER BY cnt DESC, name ASC
SQL;
        $stmt = $this->db->query($sql);
        return $stmt ? array_map(static fn(array $r): array => ['name' => (string)$r['name'], 'count' => (int)$r['cnt']], $stmt->fetchAll()) : [];
    }

    /** Min/max effective price across active products. */
    public function getPriceRange(): array
    {
        $sql = <<<SQL
SELECT
    MIN(COALESCE(po.override_price, p.price)) AS min_price,
    MAX(COALESCE(po.override_price, p.price)) AS max_price
FROM products p
LEFT JOIN product_overrides po ON po.product_id = p.id
WHERE p.is_active = 1
SQL;
        $stmt = $this->db->query($sql);
        $row = $stmt ? $stmt->fetch() : null;
        return [
            'min' => $row ? (float)($row['min_price'] ?? 0) : 0.0,
            'max' => $row ? (float)($row['max_price'] ?? 0) : 0.0,
        ];
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
