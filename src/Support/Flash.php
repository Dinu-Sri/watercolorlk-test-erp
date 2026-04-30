<?php

declare(strict_types=1);

/** One-shot flash message store for admin redirect-after-post UX. */
final class Flash
{
    private const KEY = 'admin_flash';

    public static function set(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION[self::KEY] = ['type' => $type, 'message' => $message];
    }

    public static function pull(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION[self::KEY])) {
            return null;
        }
        $msg = $_SESSION[self::KEY];
        unset($_SESSION[self::KEY]);
        return is_array($msg) ? $msg : null;
    }

    public static function success(string $message): void { self::set('success', $message); }
    public static function error(string $message): void { self::set('error', $message); }
    public static function info(string $message): void { self::set('info', $message); }
}
