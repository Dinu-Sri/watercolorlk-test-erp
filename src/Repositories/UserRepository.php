<?php

declare(strict_types=1);

class UserRepository
{
    public function __construct(private PDO $db) {}

    public function findById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $st->execute([':e' => strtolower(trim($email))]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public function findByGoogleSub(string $sub): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE google_sub = :s LIMIT 1');
        $st->execute([':s' => $sub]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO users (email, password_hash, full_name, phone, google_sub, avatar_url, email_verified_at, status)
                VALUES (:email, :pwd, :name, :phone, :gs, :avatar, :verified, :status)';
        $st = $this->db->prepare($sql);
        $st->execute([
            ':email'    => strtolower(trim((string)$data['email'])),
            ':pwd'      => $data['password_hash'] ?? null,
            ':name'     => $data['full_name'] ?? null,
            ':phone'    => $data['phone'] ?? null,
            ':gs'       => $data['google_sub'] ?? null,
            ':avatar'   => $data['avatar_url'] ?? null,
            ':verified' => $data['email_verified_at'] ?? null,
            ':status'   => $data['status'] ?? 'active',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $fields): void
    {
        if (!$fields) return;
        $allowed = ['email','password_hash','full_name','phone','google_sub','avatar_url','email_verified_at','status','last_login_at'];
        $set = []; $params = [':id' => $id];
        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $set[] = "`$k` = :$k";
            $params[":$k"] = $v;
        }
        if (!$set) return;
        $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
                 ->execute([':id' => $id]);
    }

    public function markVerified(int $id): void
    {
        $this->db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :id AND email_verified_at IS NULL')
                 ->execute([':id' => $id]);
    }

    /* ===== Tokens (verify email / reset password) ===== */

    public function issueToken(int $userId, string $kind, int $ttlMinutes = 60): string
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $st = $this->db->prepare(
            'INSERT INTO user_tokens (user_id, kind, token_hash, expires_at)
             VALUES (:u, :k, :h, DATE_ADD(NOW(), INTERVAL :t MINUTE))'
        );
        $st->execute([':u' => $userId, ':k' => $kind, ':h' => $hash, ':t' => $ttlMinutes]);
        return $raw;
    }

    public function consumeToken(string $raw, string $kind): ?array
    {
        $hash = hash('sha256', $raw);
        $st = $this->db->prepare(
            'SELECT * FROM user_tokens
             WHERE token_hash = :h AND kind = :k AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $st->execute([':h' => $hash, ':k' => $kind]);
        $row = $st->fetch();
        if (!$row) return null;
        $this->db->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = :id')
                 ->execute([':id' => $row['id']]);
        return $row;
    }

    public function invalidateTokens(int $userId, string $kind): void
    {
        $this->db->prepare('UPDATE user_tokens SET used_at = NOW() WHERE user_id = :u AND kind = :k AND used_at IS NULL')
                 ->execute([':u' => $userId, ':k' => $kind]);
    }

    /* ===== Admin listing ===== */

    public function adminList(array $filters = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(email LIKE :q OR full_name LIKE :q OR phone LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['active','disabled'], true)) {
            $where[] = 'status = :st';
            $params[':st'] = $filters['status'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSt = $this->db->prepare("SELECT COUNT(*) AS c FROM users $whereSql");
        $countSt->execute($params);
        $total = (int)$countSt->fetch()['c'];

        $sql = "SELECT u.*,
                       (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
                       (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id) AS lifetime_value
                FROM users u
                $whereSql
                ORDER BY u.created_at DESC
                LIMIT $perPage OFFSET $offset";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return [
            'rows' => $st->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active','disabled'], true)) return;
        $this->db->prepare('UPDATE users SET status = :s WHERE id = :id')
                 ->execute([':s' => $status, ':id' => $id]);
    }

    /* ===== Addresses ===== */

    public function listAddresses(int $userId): array
    {
        $st = $this->db->prepare('SELECT * FROM user_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC');
        $st->execute([':u' => $userId]);
        return $st->fetchAll();
    }

    public function defaultAddress(int $userId): ?array
    {
        $st = $this->db->prepare('SELECT * FROM user_addresses WHERE user_id = :u ORDER BY is_default DESC, id DESC LIMIT 1');
        $st->execute([':u' => $userId]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public function saveAddress(int $userId, array $a, ?int $id = null): int
    {
        if ($id) {
            $st = $this->db->prepare(
                'UPDATE user_addresses SET full_name=:fn, phone=:ph, address_line=:al, city=:ct, is_default=:def
                 WHERE id = :id AND user_id = :u'
            );
            $st->execute([
                ':fn' => $a['full_name'] ?? null,
                ':ph' => $a['phone'] ?? null,
                ':al' => $a['address_line'] ?? null,
                ':ct' => $a['city'] ?? null,
                ':def' => !empty($a['is_default']) ? 1 : 0,
                ':id' => $id, ':u' => $userId,
            ]);
            $newId = $id;
        } else {
            $st = $this->db->prepare(
                'INSERT INTO user_addresses (user_id, full_name, phone, address_line, city, is_default)
                 VALUES (:u, :fn, :ph, :al, :ct, :def)'
            );
            $st->execute([
                ':u' => $userId,
                ':fn' => $a['full_name'] ?? null,
                ':ph' => $a['phone'] ?? null,
                ':al' => $a['address_line'] ?? null,
                ':ct' => $a['city'] ?? null,
                ':def' => !empty($a['is_default']) ? 1 : 0,
            ]);
            $newId = (int)$this->db->lastInsertId();
        }
        if (!empty($a['is_default'])) {
            $this->db->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :u AND id <> :id')
                     ->execute([':u' => $userId, ':id' => $newId]);
        }
        return $newId;
    }

    public function deleteAddress(int $userId, int $id): void
    {
        $this->db->prepare('DELETE FROM user_addresses WHERE id = :id AND user_id = :u')
                 ->execute([':id' => $id, ':u' => $userId]);
    }
}
