<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/*
Usage examples:
1) CLI full setup + sync
   C:\xampp\php\php.exe scripts\run.php all

2) CLI database schema only
   C:\xampp\php\php.exe scripts\run.php db

3) CLI sync only
   C:\xampp\php\php.exe scripts\run.php sync

4) Browser/API trigger (protect with key)
   /scripts/run.php?mode=all&key=YOUR_SYNC_WEBHOOK_KEY
*/

const MODE_DB = 'db';
const MODE_SYNC = 'sync';
const MODE_ALL = 'all';
const MODE_STATUS = 'status';

try {
    $mode = resolveMode();
    enforceWebKeyIfNeeded();

    $result = [
        'success' => true,
        'mode' => $mode,
        'steps' => [],
        'executed_at' => date('c'),
    ];

    if ($mode === MODE_DB || $mode === MODE_ALL) {
        $result['steps']['db'] = runDbSchema();
    }

    if ($mode === MODE_SYNC || $mode === MODE_ALL) {
        $result['steps']['sync'] = runSync();
    }

    if ($mode === MODE_STATUS) {
        $result['steps']['status'] = getStatus();
    }

    output($result, 200);
} catch (Throwable $e) {
    output([
        'success' => false,
        'error' => $e->getMessage(),
        'executed_at' => date('c'),
    ], 500);
}

function resolveMode(): string
{
    $mode = MODE_ALL;

    if (PHP_SAPI === 'cli') {
        global $argv;
        $mode = strtolower((string)($argv[1] ?? MODE_ALL));
    } else {
        $mode = strtolower((string)($_GET['mode'] ?? MODE_ALL));
    }

    $allowed = [MODE_DB, MODE_SYNC, MODE_ALL, MODE_STATUS];
    if (!in_array($mode, $allowed, true)) {
        throw new RuntimeException('Invalid mode. Allowed: db, sync, all, status.');
    }

    return $mode;
}

function enforceWebKeyIfNeeded(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $key = (string)($_GET['key'] ?? '');
    if ($key === '' || $key !== SYNC_WEBHOOK_KEY) {
        output([
            'success' => false,
            'error' => 'Unauthorized',
        ], 401);
        exit;
    }
}

function runDbSchema(): array
{
    $schemaPath = __DIR__ . '/../schema.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException('schema.sql not found.');
    }

    $sql = trim((string)file_get_contents($schemaPath));
    if ($sql === '') {
        throw new RuntimeException('schema.sql is empty.');
    }

    $pdo = appDb();
    $pdo->beginTransaction();

    try {
        $statements = splitSqlStatements($sql);
        foreach ($statements as $statement) {
            if (trim($statement) === '') {
                continue;
            }
            $pdo->exec($statement);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'applied' => true,
        'statement_count' => count($statements),
    ];
}

function runSync(): array
{
    $catalogService = new CatalogSyncService(appErpClient(), new ProductRepository(appDb()));
    $orderService = new OrderSyncService(appErpClient(), new OrderRepository(appDb()));

    $catalog = $catalogService->syncProducts();
    $orders = $orderService->pushPendingOrders();

    return [
        'catalog' => $catalog,
        'orders' => $orders,
    ];
}

function getStatus(): array
{
    $db = appDb();

    $productCount = (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $pendingOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE erp_sync_status = 'pending'")->fetchColumn();
    $failedOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE erp_sync_status = 'failed'")->fetchColumn();

    return [
        'products' => $productCount,
        'pending_order_sync' => $pendingOrders,
        'failed_order_sync' => $failedOrders,
    ];
}

function splitSqlStatements(string $sql): array
{
    $parts = explode(';', $sql);
    $statements = [];

    foreach ($parts as $part) {
        $stmt = trim($part);
        if ($stmt !== '') {
            $statements[] = $stmt . ';';
        }
    }

    return $statements;
}

function output(array $payload, int $status): void
{
    if (PHP_SAPI === 'cli') {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }

    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}
