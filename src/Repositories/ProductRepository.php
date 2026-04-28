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

        /* Slugs generated by hydrateSeoSlug append "-{erpId}" at the end.
           If a candidate-slug match fails below (catalogue race / override edits),
           fall back to the trailing erpId so links never 404. */
        if (preg_match('/-(\d+)$/', $slug, $tail) === 1) {
            $byTail = $this->getByErpId((int)$tail[1]);
            if ($byTail !== null) {
                /* defer return until after the candidate scan so a true override slug wins */
                $tailFallback = $byTail;
            }
        }
        $tailFallback = $tailFallback ?? null;

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

        if ($tailFallback !== null) {
            return $tailFallback;
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
            /* PDO has EMULATE_PREPARES=false, so each placeholder must be unique. */
            $where[] = "(
                LOWER(p.name) LIKE LOWER(:q_name)
                OR LOWER(COALESCE(po.override_title, '')) LIKE LOWER(:q_title)
                OR LOWER(p.sku) LIKE LOWER(:q_sku)
                OR LOWER(COALESCE(p.category_name, '')) LIKE LOWER(:q_cat)
                OR LOWER(COALESCE(p.brand_name, '')) LIKE LOWER(:q_brand)
            )";
            $like = '%' . $q . '%';
            $params['q_name']  = $like;
            $params['q_title'] = $like;
            $params['q_sku']   = $like;
            $params['q_cat']   = $like;
            $params['q_brand'] = $like;
        }

        $categories = array_filter(array_map('trim', (array)($filters['categories'] ?? [])), fn($v) => $v !== '');
        if (!empty($categories)) {
            $catParts = [];
            foreach (array_values($categories) as $i => $cat) {
                $key = 'cat' . $i;
                /* Match across category_name AND product name — catalogue category_name is sparse,
                   so the keyword (e.g. "brush", "paper") is matched against both. */
                $catParts[] = "(
                    LOWER(COALESCE(p.category_name, '')) LIKE LOWER(:$key)
                    OR LOWER(p.name) LIKE LOWER(:$key)
                    OR LOWER(COALESCE(p.sku, '')) LIKE LOWER(:$key)
                )";
                $params[$key] = '%' . $cat . '%';
            }
            $where[] = '(' . implode(' OR ', $catParts) . ')';
        }

        $brands = array_filter(array_map('trim', (array)($filters['brands'] ?? [])), fn($v) => $v !== '');
        if (!empty($brands)) {
            $brandParts = [];
            foreach (array_values($brands) as $i => $brand) {
                $key = 'brand' . $i;
                /* brand_name is sparse in the catalogue; also match brand keyword inside the
                   product name so inferred brands (e.g. "Baohong", "Paul Rubens") still filter. */
                $brandParts[] = "(
                    LOWER(COALESCE(p.brand_name, '')) = LOWER(:$key)
                    OR LOWER(p.name) LIKE LOWER(:{$key}_like)
                )";
                $params[$key] = $brand;
                $params[$key . '_like'] = '%' . $brand . '%';
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
            /* relevance ORDER BY params do not appear in WHERE — skip them. */
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

    /**
     * Fallback brand list when products.brand_name is empty.
     * Counts products whose name contains a known watercolor brand keyword.
     * Returns same shape as listAllBrands(): [{name, count}, ...] sorted by count DESC.
     */
    public function listInferredBrands(): array
    {
        /* Curated list of brand keywords seen in this catalogue. Extend as needed. */
        $brandKeywords = [
            'Baohong', 'Paul Rubens', 'Winsor & Newton', 'W&N', 'Schmincke', 'Sennelier',
            'Daniel Smith', 'Holbein', 'Mungyo', 'Phoenix', 'Hahnemühle', 'Arches',
            'Saunders', 'Maimeri', 'Faber-Castell', 'Caran d\'Ache', 'Royal Talens',
            'Daler-Rowney', 'Yutang', 'Moyuan', 'Mingxiu', 'Jinghong', 'Mingruixiang',
            'Sinours', 'Yingxiong', 'Shede', 'Mali', 'Xiangfei', 'Sketchers',
            'Artsecret', 'Molong', 'Potentate',
        ];

        $sql = <<<SQL
SELECT COUNT(*) AS cnt
FROM products
WHERE is_active = 1 AND LOWER(name) LIKE LOWER(:kw)
SQL;
        $stmt = $this->db->prepare($sql);
        $out = [];
        foreach ($brandKeywords as $kw) {
            $stmt->bindValue(':kw', '%' . $kw . '%', PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch();
            $cnt = (int)($row['cnt'] ?? 0);
            if ($cnt > 0) {
                $out[] = ['name' => $kw, 'count' => $cnt];
            }
        }
        usort($out, static fn(array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp((string)$a['name'], (string)$b['name']));
        return $out;
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

    /** Lazily ensure the search_queries analytics table exists. */
    private function ensureSearchQueryTable(): void
    {
        static $ensured = false;
        if ($ensured) return;
        $this->db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS search_queries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    query VARCHAR(190) NOT NULL,
    query_norm VARCHAR(190) NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 1,
    last_searched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    first_searched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_norm (query_norm),
    KEY idx_hits (hits DESC),
    KEY idx_last (last_searched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $ensured = true;
    }

    /** Record a user search. Normalised to lowercase + collapsed whitespace for de-dup. */
    public function logSearchQuery(string $q): void
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) > 190) return;
        $norm = mb_strtolower(preg_replace('/\s+/u', ' ', $q) ?? $q);
        if ($norm === '') return;
        try {
            $this->ensureSearchQueryTable();
            $stmt = $this->db->prepare(
                'INSERT INTO search_queries (query, query_norm, hits, last_searched_at, first_searched_at)
                 VALUES (:q, :n, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE hits = hits + 1, last_searched_at = NOW()'
            );
            $stmt->execute([':q' => $q, ':n' => $norm]);
        } catch (Throwable $e) {
            /* analytics must never break user flow */
        }
    }

    /**
     * Top trending searches, optionally filtered by prefix.
     * @return array<int, array{query:string, hits:int}>
     */
    public function getTrendingSearches(string $prefix = '', int $limit = 8): array
    {
        try {
            $this->ensureSearchQueryTable();
            if ($prefix !== '') {
                $stmt = $this->db->prepare(
                    "SELECT query, hits FROM search_queries
                     WHERE query_norm LIKE :p
                     ORDER BY hits DESC, last_searched_at DESC
                     LIMIT :lim"
                );
                $stmt->bindValue(':p', mb_strtolower($prefix) . '%', PDO::PARAM_STR);
                $stmt->bindValue(':lim', max(1, min(20, $limit)), PDO::PARAM_INT);
            } else {
                $stmt = $this->db->prepare(
                    'SELECT query, hits FROM search_queries
                     ORDER BY hits DESC, last_searched_at DESC
                     LIMIT :lim'
                );
                $stmt->bindValue(':lim', max(1, min(20, $limit)), PDO::PARAM_INT);
            }
            $stmt->execute();
            return array_map(static fn(array $r): array => [
                'query' => (string)$r['query'],
                'hits' => (int)$r['hits'],
            ], $stmt->fetchAll());
        } catch (Throwable $e) {
            return [];
        }
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
