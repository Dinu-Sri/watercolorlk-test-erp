<?php

declare(strict_types=1);

/**
 * Admin bootstrap. Every admin page must `require_once` this first.
 * - Loads main app bootstrap.
 * - Starts the admin session.
 * - Enforces login (unless $ADMIN_PUBLIC_PAGE = true is set before include).
 * - Verifies CSRF on POST.
 */

require_once __DIR__ . '/../bootstrap.php';

AdminAuth::startSession();

if (empty($GLOBALS['ADMIN_PUBLIC_PAGE'])) {
    $adminUser = AdminAuth::require();
    Csrf::requireValidPost();
} else {
    $adminUser = AdminAuth::user();
}

/** Compute the base URL for admin links. */
function adminUrl(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'))), '/');
    if ($base === '' || !str_ends_with($base, '/admin')) {
        $base = '/admin';
    }
    return $base . '/' . ltrim($path, '/');
}

function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function audit(string $action, ?string $type = null, ?string $id = null, mixed $payload = null): void
{
    try {
        $repo = new AdminUserRepository(appDb());
        $repo->audit(AdminAuth::userId(), $action, $type, $id, $payload);
    } catch (Throwable $e) {
        // never break the action because of an audit failure
    }
}
