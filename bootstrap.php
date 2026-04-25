<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/Support/Database.php';
require_once __DIR__ . '/src/Support/JsonResponse.php';
require_once __DIR__ . '/src/Repositories/ProductRepository.php';
require_once __DIR__ . '/src/Repositories/OrderRepository.php';
require_once __DIR__ . '/src/Repositories/GoogleReviewRepository.php';
require_once __DIR__ . '/src/Services/ErpClient.php';
require_once __DIR__ . '/src/Services/CatalogSyncService.php';
require_once __DIR__ . '/src/Services/OrderSyncService.php';

function appDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = Database::connect(
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_USER,
        DB_PASS
    );

    return $pdo;
}

function appErpClient(): ErpClient
{
    static $client = null;
    if ($client instanceof ErpClient) {
        return $client;
    }

    $client = new ErpClient([
        'base_url' => ERP_BASE_URL,
        'client_id' => ERP_CLIENT_ID,
        'client_secret' => ERP_CLIENT_SECRET,
        'username' => ERP_USERNAME,
        'password' => ERP_PASSWORD,
        'location_id' => ERP_LOCATION_ID,
        'token_cache_file' => __DIR__ . '/.erp_token_cache.json',
    ]);

    return $client;
}
