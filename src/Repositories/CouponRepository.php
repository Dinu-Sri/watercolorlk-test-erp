<?php

declare(strict_types=1);

final class CouponRepository
{
    public function __construct(private PDO $db) {}

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM coupons ORDER BY is_active DESC, created_at DESC');
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE UPPER(code) = UPPER(:c) LIMIT 1');
        $stmt->execute([':c' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getTargets(int $couponId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupon_targets WHERE coupon_id = :id');
        $stmt->execute([':id' => $couponId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO coupons
             (code, description, type, value, min_subtotal, max_discount, starts_at, ends_at,
              usage_limit, usage_limit_per_customer, applies_to, is_active, created_by)
             VALUES
             (:code, :desc, :type, :val, :mins, :maxd, :sa, :ea,
              :ul, :ulpc, :at, :a, :cb)'
        );
        $stmt->execute([
            ':code' => strtoupper(trim((string)$data['code'])),
            ':desc' => $data['description'] ?? null,
            ':type' => $data['type'],
            ':val' => (float)$data['value'],
            ':mins' => isset($data['min_subtotal']) && $data['min_subtotal'] !== '' ? (float)$data['min_subtotal'] : null,
            ':maxd' => isset($data['max_discount']) && $data['max_discount'] !== '' ? (float)$data['max_discount'] : null,
            ':sa' => $data['starts_at'] ?: null,
            ':ea' => $data['ends_at'] ?: null,
            ':ul' => isset($data['usage_limit']) && $data['usage_limit'] !== '' ? (int)$data['usage_limit'] : null,
            ':ulpc' => isset($data['usage_limit_per_customer']) && $data['usage_limit_per_customer'] !== '' ? (int)$data['usage_limit_per_customer'] : null,
            ':at' => $data['applies_to'] ?? 'all',
            ':a' => (int)($data['is_active'] ?? 1),
            ':cb' => $data['created_by'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE coupons SET code=:code, description=:desc, type=:type, value=:val,
             min_subtotal=:mins, max_discount=:maxd, starts_at=:sa, ends_at=:ea,
             usage_limit=:ul, usage_limit_per_customer=:ulpc,
             applies_to=:at, is_active=:a, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([
            ':code' => strtoupper(trim((string)$data['code'])),
            ':desc' => $data['description'] ?? null,
            ':type' => $data['type'],
            ':val' => (float)$data['value'],
            ':mins' => isset($data['min_subtotal']) && $data['min_subtotal'] !== '' ? (float)$data['min_subtotal'] : null,
            ':maxd' => isset($data['max_discount']) && $data['max_discount'] !== '' ? (float)$data['max_discount'] : null,
            ':sa' => $data['starts_at'] ?: null,
            ':ea' => $data['ends_at'] ?: null,
            ':ul' => isset($data['usage_limit']) && $data['usage_limit'] !== '' ? (int)$data['usage_limit'] : null,
            ':ulpc' => isset($data['usage_limit_per_customer']) && $data['usage_limit_per_customer'] !== '' ? (int)$data['usage_limit_per_customer'] : null,
            ':at' => $data['applies_to'] ?? 'all',
            ':a' => (int)($data['is_active'] ?? 1),
            ':id' => $id,
        ]);
    }

    public function replaceTargets(int $couponId, array $targets): void
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM coupon_targets WHERE coupon_id = :id');
            $del->execute([':id' => $couponId]);
            $ins = $this->db->prepare(
                'INSERT IGNORE INTO coupon_targets (coupon_id, target_type, target_id) VALUES (:c, :tt, :ti)'
            );
            foreach ($targets as $t) {
                $type = $t['target_type'] ?? '';
                $tid = (int)($t['target_id'] ?? 0);
                if (!in_array($type, ['category', 'storefront_product'], true) || $tid <= 0) continue;
                $ins->execute([':c' => $couponId, ':tt' => $type, ':ti' => $tid]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM coupons WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function recordRedemption(int $couponId, ?int $orderId, float $discount, ?string $phone): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO coupon_redemptions (coupon_id, order_id, discount_amount, customer_phone, redeemed_at)
                 VALUES (:c, :o, :d, :p, NOW())'
            );
            $stmt->execute([':c' => $couponId, ':o' => $orderId, ':d' => $discount, ':p' => $phone]);

            $bump = $this->db->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id');
            $bump->execute([':id' => $couponId]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function customerUsageCount(int $couponId, ?string $phone): int
    {
        if (!$phone) return 0;
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM coupon_redemptions WHERE coupon_id = :id AND customer_phone = :p'
        );
        $stmt->execute([':id' => $couponId, ':p' => $phone]);
        return (int)($stmt->fetch()['c'] ?? 0);
    }
}
