<?php

declare(strict_types=1);

final class CategoryRepository
{
    public function __construct(private PDO $db) {}

    public function listAll(bool $onlyVisible = false): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM storefront_product_categories spc INNER JOIN storefront_products sp ON sp.id = spc.storefront_product_id WHERE spc.category_id = c.id AND sp.is_visible = 1) AS visible_count
                FROM categories c'
            . ($onlyVisible ? ' WHERE c.is_visible = 1' : '') .
              ' ORDER BY c.sort_order ASC, c.name ASC';
        $stmt = $this->db->query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO categories (slug, name, parent_id, image_url, description, sort_order, is_visible, seo_title, seo_description)
             VALUES (:slug, :name, :parent, :img, :desc, :so, :vis, :st, :sd)'
        );
        $stmt->execute([
            ':slug' => $data['slug'],
            ':name' => $data['name'],
            ':parent' => $data['parent_id'] ?? null,
            ':img' => $data['image_url'] ?? null,
            ':desc' => $data['description'] ?? null,
            ':so' => (int)($data['sort_order'] ?? 0),
            ':vis' => (int)($data['is_visible'] ?? 1),
            ':st' => $data['seo_title'] ?? null,
            ':sd' => $data['seo_description'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE categories SET slug=:slug, name=:name, parent_id=:parent, image_url=:img,
             description=:desc, sort_order=:so, is_visible=:vis, seo_title=:st, seo_description=:sd, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([
            ':slug' => $data['slug'],
            ':name' => $data['name'],
            ':parent' => $data['parent_id'] ?? null,
            ':img' => $data['image_url'] ?? null,
            ':desc' => $data['description'] ?? null,
            ':so' => (int)($data['sort_order'] ?? 0),
            ':vis' => (int)($data['is_visible'] ?? 1),
            ':st' => $data['seo_title'] ?? null,
            ':sd' => $data['seo_description'] ?? null,
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** For public home page: visible categories with product counts. */
    public function listVisibleWithCounts(): array
    {
        $stmt = $this->db->query(
            'SELECT c.id, c.slug, c.name, c.image_url, c.sort_order,
                    (SELECT COUNT(*) FROM storefront_product_categories spc
                       INNER JOIN storefront_products sp ON sp.id = spc.storefront_product_id
                       WHERE spc.category_id = c.id AND sp.is_visible = 1) AS cnt
             FROM categories c
             WHERE c.is_visible = 1
             ORDER BY c.sort_order ASC, c.name ASC'
        );
        return $stmt ? $stmt->fetchAll() : [];
    }
}
