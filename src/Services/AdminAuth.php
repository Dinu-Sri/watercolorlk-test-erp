<?php

declare(strict_types=1);

/**
 * Admin auth helpers. Session-backed; PHP-native sessions.
 * Lockout: 5 failures within 15 minutes per IP+username.
 */
final class AdminAuth
{
    public const SESSION_USER = 'admin_user';
    public const LOCKOUT_THRESHOLD = 5;
    public const LOCKOUT_WINDOW_MIN = 15;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_name('wlk_admin');
        session_start();
    }

    public static function user(): ?array
    {
        self::startSession();
        $u = $_SESSION[self::SESSION_USER] ?? null;
        return is_array($u) ? $u : null;
    }

    public static function userId(): int
    {
        $u = self::user();
        return $u ? (int)($u['id'] ?? 0) : 0;
    }

    public static function require(): array
    {
        self::startSession();
        $u = self::user();
        if (!$u) {
            $back = $_SERVER['REQUEST_URI'] ?? '/admin/';
            header('Location: /admin/login.php?next=' . rawurlencode($back));
            exit;
        }
        return $u;
    }

    public static function attemptLogin(AdminUserRepository $repo, string $username, string $password): array
    {
        self::startSession();
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if ($repo->recentFailureCount($ip, $username, self::LOCKOUT_WINDOW_MIN) >= self::LOCKOUT_THRESHOLD) {
            return ['ok' => false, 'error' => 'Too many failed attempts. Try again in 15 minutes.'];
        }

        $row = $repo->findByUsername($username);
        if (!$row || !password_verify($password, (string)$row['password_hash'])) {
            $repo->logAttempt($ip, $username, false);
            return ['ok' => false, 'error' => 'Invalid username or password.'];
        }

        $repo->logAttempt($ip, $username, true);
        $repo->markLogin((int)$row['id']);

        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER] = [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'display_name' => (string)($row['display_name'] ?? $row['username']),
            'role' => (string)$row['role'],
        ];
        Csrf::rotate();
        return ['ok' => true, 'user' => $_SESSION[self::SESSION_USER]];
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
