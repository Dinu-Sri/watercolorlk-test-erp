<?php

declare(strict_types=1);

final class FlashDealRepository
{
    public function __construct(private PDO $db) {}

    public function listAdmin(): array
    {
        $sql = 'SELECT fd.*, sp.title AS product_title, sp.slug AS product_slug, sp.kind, sp.is_visible
                FROM flash_deals fd
                INNER JOIN storefront_products sp ON sp.id = fd.storefront_product_id
                ORDER BY fd.is_active DESC, COALESCE(fd.starts_at, fd.created_at) DESC';
        $stmt = $this->db->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM flash_deals WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO flash_deals
             (storefront_product_id, deal_price, original_price, label, starts_at, ends_at, sort_order, is_active, created_by)
             VALUES (:sp, :dp, :op, :lbl, :sa, :ea, :so, :a, :cb)'
        );
        $stmt->execute([
            ':sp' => (int)$data['storefront_product_id'],
            ':dp' => (float)$data['deal_price'],
            ':op' => isset($data['original_price']) && $data['original_price'] !== '' ? (float)$data['original_price'] : null,
            ':lbl' => $data['label'] ?? null,
            ':sa' => $data['starts_at'] ?: null,
            ':ea' => $data['ends_at'] ?: null,
            ':so' => (int)($data['sort_order'] ?? 0),
            ':a' => (int)($data['is_active'] ?? 1),
            ':cb' => $data['created_by'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE flash_deals SET deal_price=:dp, original_price=:op, label=:lbl,
             starts_at=:sa, ends_at=:ea, sort_order=:so, is_active=:a, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([
            ':dp' => (float)$data['deal_price'],
            ':op' => isset($data['original_price']) && $data['original_price'] !== '' ? (float)$data['original_price'] : null,
            ':lbl' => $data['label'] ?? null,
            ':sa' => $data['starts_at'] ?: null,
            ':ea' => $data['ends_at'] ?: null,
            ':so' => (int)($data['sort_order'] ?? 0),
            ':a' => (int)($data['is_active'] ?? 1),
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM flash_deals WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Public — currently-active flash deals joined to storefront product. */
    public function listActive(int $limit = 8): array
    {
        $sql = "
            SELECT fd.id, fd.deal_price, fd.original_price, fd.label, fd.starts_at, fd.ends_at,
                   sp.id AS storefront_product_id, sp.kind, sp.slug, sp.title, sp.hero_image_url,
                   sp.base_price, sp.badge, sp.erp_product_id,
                   p.stock_qty, p.price AS erp_price
            FROM flash_deals fd
            INNER JOIN storefront_products sp ON sp.id = fd.storefront_product_id
            LEFT JOIN products p ON p.erp_product_id = sp.erp_product_id AND sp.kind = 'simple'
            WHERE fd.is_active = 1 AND sp.is_visible = 1
              AND (fd.starts_at IS NULL OR fd.starts_at <= NOW())
              AND (fd.ends_at IS NULL OR fd.ends_at >= NOW())
            ORDER BY fd.sort_order ASC, fd.id DESC
            LIMIT :lim
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Map of storefront_product_id => deal_price (currently active) for quick price overrides. */
    public function activePriceMap(): array
    {
        $stmt = $this->db->query(
            "SELECT storefront_product_id AS id, deal_price
             FROM flash_deals
             WHERE is_active = 1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())"
        );
        $map = [];
        if ($stmt) {
            foreach ($stmt->fetchAll() as $r) {
                $map[(int)$r['id']] = (float)$r['deal_price'];
            }
        }
        return $map;
    }
}
