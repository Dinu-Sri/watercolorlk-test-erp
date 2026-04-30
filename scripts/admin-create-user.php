<?php

declare(strict_types=1);

/**
 * One-shot CLI script to create or reset an admin user.
 *
 *   php scripts/admin-create-user.php
 *
 * Prompts for username, password (twice) and role.
 * Refuses to run via web (must be invoked from CLI).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the CLI.";
    exit(1);
}

require_once __DIR__ . '/../bootstrap.php';

function prompt(string $label, bool $silent = false): string
{
    fwrite(STDOUT, $label);
    if ($silent && DIRECTORY_SEPARATOR !== '\\') {
        // *nix: turn off echo
        system('stty -echo');
        $line = trim((string)fgets(STDIN));
        system('stty echo');
        fwrite(STDOUT, "\n");
        return $line;
    }
    return trim((string)fgets(STDIN));
}

try {
    $repo = new AdminUserRepository(appDb());

    $username = '';
    while ($username === '') {
        $username = prompt('Username (min 3 chars): ');
        if (strlen($username) < 3) { $username = ''; }
    }

    $existing = $repo->findByUsername($username);
    if ($existing) {
        echo "User '$username' already exists. Resetting password.\n";
    }

    $pw1 = '';
    while (strlen($pw1) < 8) {
        $pw1 = prompt('Password (min 8 chars): ', true);
    }
    $pw2 = prompt('Confirm password: ', true);
    if ($pw1 !== $pw2) {
        fwrite(STDERR, "Passwords do not match.\n");
        exit(2);
    }

    $email = prompt('Email (optional): ');
    $role = strtolower(prompt('Role [super/editor] (default: super): '));
    if (!in_array($role, ['super', 'editor'], true)) $role = 'super';

    if ($existing) {
        $repo->setPassword((int)$existing['id'], $pw1);
        echo "Password updated for user #{$existing['id']} ($username).\n";
    } else {
        $id = $repo->create($username, $pw1, $email !== '' ? $email : null, $role, $username);
        echo "Created admin user #$id ($username, role=$role).\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
