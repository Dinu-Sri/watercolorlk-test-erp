<?php

declare(strict_types=1);

/**
 * Run the admin-dashboard schema migration.
 *
 *   php scripts/run-migration.php          # default file
 *   php scripts/run-migration.php path.sql # specific file
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit(1);
}

require_once __DIR__ . '/../bootstrap.php';

$path = $argv[1] ?? __DIR__ . '/../db/migrations/2026_05_admin.sql';
if (!is_file($path)) {
    fwrite(STDERR, "Migration file not found: $path\n");
    exit(1);
}

$sql = (string)file_get_contents($path);
if ($sql === '') {
    fwrite(STDERR, "Empty migration file.\n");
    exit(1);
}

$db = appDb();

// Split on `;` followed by newline. Naive but workable for our DDL/DML migration.
$statements = [];
$buf = '';
foreach (preg_split('/\R/', $sql) ?: [] as $line) {
    $trim = ltrim($line);
    if (str_starts_with($trim, '--')) continue;
    $buf .= $line . "\n";
    if (preg_match('/;\s*$/', $line)) {
        $stmt = trim($buf);
        if ($stmt !== '' && $stmt !== ';') {
            $statements[] = rtrim($stmt, "; \t\r\n");
        }
        $buf = '';
    }
}
if (trim($buf) !== '') {
    $statements[] = trim($buf);
}

$ok = 0; $skipped = 0; $failed = 0;
foreach ($statements as $i => $s) {
    if ($s === '') continue;
    try {
        $db->exec($s);
        $ok++;
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        // Idempotent re-runs may try to add duplicate FKs etc — log but continue.
        if (preg_match('/already exists|Duplicate key|Duplicate column/i', $msg)) {
            $skipped++;
            echo "[skip] " . substr($msg, 0, 120) . "\n";
            continue;
        }
        $failed++;
        echo "[fail] stmt #$i: " . substr($msg, 0, 200) . "\n";
        echo "  > " . substr($s, 0, 120) . "...\n";
    }
}

echo "\nDone. ok=$ok skipped=$skipped failed=$failed\n";
exit($failed > 0 ? 1 : 0);
