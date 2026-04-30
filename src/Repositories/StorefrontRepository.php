<?php

declare(strict_types=1);

/**
 * Storefront product repository.
 *
 * The "storefront_products" table is the public-facing catalog row. Every public
 * product (simple, combined, pack) appears here. ERP rows live in `products`
 * and are linked either via `storefront_products.erp_product_id` (kind=simple)
 * or via `storefront_product_children` (kind=combined or kind=pack).
 *
 * Effective price rules:
 *   - simple:    price_override (in storefront_products.base_price) or products.price
 *   - combined:  child.price_override > parent.base_price > underlying products.price
 *   - pack:      parent.base_price (admin sets fixed pack price)
 *
 * Effective stock rules:
 *   - simple:    products.stock_qty
 *   - combined:  the chosen child's products.stock_qty (front-end picks variant)
 *   - pack:      floor(min(child.stock_qty / child.quantity)) across all children
 */
final class StorefrontRepository
{
    public function __construct(private PDO $db) {}

    /* ---------------------------------------------------------------- helpers */

    public function buildSlug(string $candidate, string $fallbackName, ?int $erpId = null): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            $candidate = $fallbackName;
        }
        if ($candidate === '' && $erpId !== null) {
            return 'product-' . $erpId;
        }
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $candidate);
            if (is_string($ascii) && $ascii !== '') {
                $candidate = $ascii;
            }
        }
        $slug = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $candidate));
        $slug = trim($slug, '-');
        if ($slug === '') {
            return $erpId !== null ? ('product-' . $erpId) : 'item';
        }
        return $slug;
    }

    public function ensureUniqueSlug(string $base, ?int $excludeId = null): string
    {
        $candidate = $base;
        $i = 2;
        while ($this->slugExists($candidate, $excludeId)) {
            $candidate = $base . '-' . $i;
            $i++;
            if ($i > 200) break;
        }
        return $candidate;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM storefront_products WHERE slug = :s' . ($excludeId !== null ? ' AND id <> :x' : '') . ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':s', $slug);
        if ($excludeId !== null) $stmt->bindValue(':x', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetch();
    }

    /* ---------------------------------------------------------------- queries */

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM storefront_products WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getBySlug(string $slug, bool $onlyVisible = true): ?array
    {
        $sql = 'SELECT * FROM storefront_products WHERE slug = :s' . ($onlyVisible ? ' AND is_visible = 1' : '') . ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Get a simple-kind storefront row for a given ERP product. */
    public function getSimpleByErpId(int $erpId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM storefront_products WHERE kind = 'simple' AND erp_product_id = :e LIMIT 1"
        );
        $stmt->execute([':e' => $erpId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Admin list: every storefront product with summary fields.
     *
     * @param array{q?:string,kind?:string,visibility?:string,category_id?:int,limit?:int,offset?:int} $filters
     * @return array{rows:array, total:int}
     */
    public function adminList(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(LOWER(sp.title) LIKE LOWER(:q) OR LOWER(sp.slug) LIKE LOWER(:q))';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['kind']) && in_array($filters['kind'], ['simple', 'combined', 'pack'], true)) {
            $where[] = 'sp.kind = :k';
            $params[':k'] = $filters['kind'];
        }
        if (isset($filters['visibility']) && $filters['visibility'] !== '') {
            $where[] = 'sp.is_visible = :v';
            $params[':v'] = (int)((string)$filters['visibility'] === '1');
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM storefront_product_categories spc WHERE spc.storefront_product_id = sp.id AND spc.category_id = :cat)';
            $params[':cat'] = (int)$filters['category_id'];
        }

        $whereSql = implode(' AND ', $where);
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = "
            SELECT sp.id, sp.kind, sp.slug, sp.title, sp.badge, sp.is_visible, sp.erp_product_id,
                   sp.base_price, sp.compare_at_price, sp.hero_image_url, sp.updated_at,
                   p.sku, p.stock_qty AS simple_stock, p.price AS erp_price
            FROM storefront_products sp
            LEFT JOIN products p ON p.erp_product_id = sp.erp_product_id AND sp.kind = 'simple'
            WHERE $whereSql
            ORDER BY sp.is_visible DESC, sp.updated_at DESC, sp.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $countSql = "SELECT COUNT(*) AS c FROM storefront_products sp WHERE $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
        $countStmt->execute();
        $total = (int)($countStmt->fetch()['c'] ?? 0);

        return ['rows' => $rows, 'total' => $total];
    }

    /** Get full record + children + categories. */
    public function getFull(int $id): ?array
    {
        $sp = $this->getById($id);
        if (!$sp) return null;
        $sp['children'] = $this->getChildren($id);
        $sp['category_ids'] = $this->getCategoryIds($id);
        return $sp;
    }

    public function getChildren(int $parentId, ?string $context = null): array
    {
        $sql = "
            SELECT spc.*, p.sku, p.name AS product_name, p.image_url AS product_image,
                   p.price AS product_price, p.stock_qty, p.erp_product_id
            FROM storefront_product_children spc
            INNER JOIN products p ON p.id = spc.child_product_id
            WHERE spc.parent_storefront_id = :id"
            . ($context !== null ? ' AND spc.context = :ctx' : '') .
           " ORDER BY spc.context, spc.sort_order, spc.id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $parentId, PDO::PARAM_INT);
        if ($context !== null) $stmt->bindValue(':ctx', $context);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getCategoryIds(int $storefrontId): array
    {
        $stmt = $this->db->prepare(
            'SELECT category_id FROM storefront_product_categories WHERE storefront_product_id = :id'
        );
        $stmt->execute([':id' => $storefrontId]);
        return array_map(static fn($r) => (int)$r['category_id'], $stmt->fetchAll());
    }

    /* ---------------------------------------------------------------- writes */

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO storefront_products
             (kind, slug, title, subtitle, description, hero_image_url, gallery_json, badge,
              base_price, compare_at_price, erp_product_id, is_visible, sort_order,
              seo_title, seo_description, created_by, created_at, updated_at)
             VALUES
             (:kind, :slug, :title, :subtitle, :description, :hero, :gallery, :badge,
              :base_price, :compare_at, :erp_id, :is_visible, :sort_order,
              :seo_t, :seo_d, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':kind' => $data['kind'],
            ':slug' => $data['slug'],
            ':title' => $data['title'],
            ':subtitle' => $data['subtitle'] ?? null,
            ':description' => $data['description'] ?? null,
            ':hero' => $data['hero_image_url'] ?? null,
            ':gallery' => $data['gallery_json'] ?? null,
            ':badge' => $data['badge'] ?? null,
            ':base_price' => isset($data['base_price']) && $data['base_price'] !== '' ? $data['base_price'] : null,
            ':compare_at' => isset($data['compare_at_price']) && $data['compare_at_price'] !== '' ? $data['compare_at_price'] : null,
            ':erp_id' => $data['erp_product_id'] ?? null,
            ':is_visible' => (int)($data['is_visible'] ?? 0),
            ':sort_order' => (int)($data['sort_order'] ?? 0),
            ':seo_t' => $data['seo_title'] ?? null,
            ':seo_d' => $data['seo_description'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = [
            'slug','title','subtitle','description','hero_image_url','gallery_json','badge',
            'base_price','compare_at_price','is_visible','sort_order','seo_title','seo_description',
        ];
        $sets = [];
        $params = [':id' => $id];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "$f = :$f";
                $val = $data[$f];
                if (in_array($f, ['base_price', 'compare_at_price'], true) && ($val === '' || $val === null)) {
                    $val = null;
                }
                $params[":$f"] = $val;
            }
        }
        if (!$sets) return;
        $sets[] = 'updated_at = NOW()';
        $sql = 'UPDATE storefront_products SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function setVisible(int $id, bool $visible): void
    {
        $stmt = $this->db->prepare('UPDATE storefront_products SET is_visible = :v, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':v' => $visible ? 1 : 0, ':id' => $id]);
    }

    public function bulkSetVisible(array $ids, bool $visible): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return;
        $place = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE storefront_products SET is_visible = ?, updated_at = NOW() WHERE id IN ($place)";
        $params = array_merge([$visible ? 1 : 0], $ids);
        $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM storefront_products WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /* ---------------------------------------------------------------- children */

    public function replaceChildren(int $parentId, string $context, array $children): void
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM storefront_product_children WHERE parent_storefront_id = :id AND context = :ctx');
            $del->execute([':id' => $parentId, ':ctx' => $context]);

            $ins = $this->db->prepare(
                'INSERT INTO storefront_product_children
                 (parent_storefront_id, child_product_id, context, variant_label, variant_swatch_hex,
                  quantity, price_override, sort_order, is_default, created_at)
                 VALUES
                 (:pid, :cid, :ctx, :label, :hex, :qty, :po, :so, :def, NOW())'
            );
            $sortIdx = 0;
            $sawDefault = false;
            foreach ($children as $c) {
                $cid = (int)($c['child_product_id'] ?? 0);
                if ($cid <= 0) continue;
                $isDefault = !empty($c['is_default']) && !$sawDefault ? 1 : 0;
                if ($isDefault) $sawDefault = true;
                $ins->execute([
                    ':pid' => $parentId,
                    ':cid' => $cid,
                    ':ctx' => $context,
                    ':label' => $c['variant_label'] ?? null,
                    ':hex' => $c['variant_swatch_hex'] ?? null,
                    ':qty' => isset($c['quantity']) && $c['quantity'] !== '' ? (float)$c['quantity'] : 1.0,
                    ':po' => isset($c['price_override']) && $c['price_override'] !== '' ? (float)$c['price_override'] : null,
                    ':so' => $sortIdx++,
                    ':def' => $isDefault,
                ]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function replaceCategories(int $storefrontId, array $categoryIds): void
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM storefront_product_categories WHERE storefront_product_id = :id');
            $del->execute([':id' => $storefrontId]);
            if ($categoryIds) {
                $ins = $this->db->prepare(
                    'INSERT IGNORE INTO storefront_product_categories (storefront_product_id, category_id) VALUES (:s, :c)'
                );
                foreach ($categoryIds as $cid) {
                    $cid = (int)$cid;
                    if ($cid <= 0) continue;
                    $ins->execute([':s' => $storefrontId, ':c' => $cid]);
                }
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /* ---------------------------------------------------------------- public read */

    /**
     * Compute effective stock for a storefront product (any kind).
     * For 'combined' returns the sum (so listing shows non-zero if any variant has stock).
     */
    public function effectiveStock(int $storefrontId): float
    {
        $sp = $this->getById($storefrontId);
        if (!$sp) return 0.0;
        if ($sp['kind'] === 'simple') {
            $stmt = $this->db->prepare('SELECT stock_qty FROM products WHERE erp_product_id = :e LIMIT 1');
            $stmt->execute([':e' => (int)$sp['erp_product_id']]);
            return (float)($stmt->fetch()['stock_qty'] ?? 0);
        }
        if ($sp['kind'] === 'combined') {
            $stmt = $this->db->prepare(
                'SELECT COALESCE(SUM(p.stock_qty), 0) AS s
                 FROM storefront_product_children spc
                 INNER JOIN products p ON p.id = spc.child_product_id
                 WHERE spc.parent_storefront_id = :id AND spc.context = "variant"'
            );
            $stmt->execute([':id' => $storefrontId]);
            return (float)($stmt->fetch()['s'] ?? 0);
        }
        // pack
        $stmt = $this->db->prepare(
            'SELECT p.stock_qty AS stock, spc.quantity AS qty
             FROM storefront_product_children spc
             INNER JOIN products p ON p.id = spc.child_product_id
             WHERE spc.parent_storefront_id = :id AND spc.context = "pack_item"'
        );
        $stmt->execute([':id' => $storefrontId]);
        $rows = $stmt->fetchAll();
        if (!$rows) return 0.0;
        $min = INF;
        foreach ($rows as $r) {
            $qty = max(0.001, (float)$r['qty']);
            $available = floor((float)$r['stock'] / $qty);
            if ($available < $min) $min = $available;
        }
        return $min === INF ? 0.0 : (float)$min;
    }
}
