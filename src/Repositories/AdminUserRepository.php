<?php

declare(strict_types=1);

final class AdminUserRepository
{
    public function __construct(private PDO $db) {}

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM admin_users WHERE username = :u AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $username, string $password, ?string $email = null, string $role = 'super', ?string $displayName = null): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare(
            'INSERT INTO admin_users (username, email, password_hash, display_name, role, is_active, created_at, updated_at)
             VALUES (:u, :e, :h, :d, :r, 1, NOW(), NOW())'
        );
        $stmt->execute([
            ':u' => $username,
            ':e' => $email,
            ':h' => $hash,
            ':d' => $displayName ?? $username,
            ':r' => $role,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function setPassword(int $userId, string $password): void
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare(
            'UPDATE admin_users SET password_hash = :h, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':h' => $hash, ':id' => $userId]);
    }

    public function markLogin(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $userId]);
    }

    public function logAttempt(string $ip, ?string $username, bool $success): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO admin_login_attempts (ip, username, success, attempted_at) VALUES (:ip, :u, :s, NOW())'
        );
        $stmt->execute([':ip' => $ip, ':u' => $username, ':s' => $success ? 1 : 0]);
    }

    public function recentFailureCount(string $ip, ?string $username, int $minutes = 15): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS c FROM admin_login_attempts
             WHERE success = 0
               AND attempted_at > (NOW() - INTERVAL :mins MINUTE)
               AND (ip = :ip OR (username IS NOT NULL AND username = :u))"
        );
        $stmt->bindValue(':mins', $minutes, PDO::PARAM_INT);
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':u', $username);
        $stmt->execute();
        return (int)($stmt->fetch()['c'] ?? 0);
    }

    public function audit(int $adminId, string $action, ?string $targetType = null, ?string $targetId = null, mixed $payload = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO admin_audit_log (admin_user_id, action, target_type, target_id, payload_json, ip, created_at)
             VALUES (:uid, :a, :tt, :ti, :p, :ip, NOW())'
        );
        $stmt->execute([
            ':uid' => $adminId,
            ':a' => $action,
            ':tt' => $targetType,
            ':ti' => $targetId,
            ':p' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT id, username, email, display_name, role, is_active, last_login_at, created_at FROM admin_users ORDER BY id ASC');
        return $stmt ? $stmt->fetchAll() : [];
    }
}
