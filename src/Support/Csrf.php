<?php

declare(strict_types=1);

/**
 * Tiny CSRF token helper. Stores a single token in the admin session.
 * Token is rotated on login and verified on every state-changing POST.
 */
final class Csrf
{
    private const SESSION_KEY = 'admin_csrf_token';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION[self::SESSION_KEY];
    }

    public static function rotate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    public static function verify(?string $candidate): bool
    {
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $stored = (string)($_SESSION[self::SESSION_KEY] ?? '');
        return $stored !== '' && hash_equals($stored, $candidate);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function requireValidPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        if (!self::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'CSRF token invalid. <a href="javascript:history.back()">Back</a>';
            exit;
        }
    }
}
