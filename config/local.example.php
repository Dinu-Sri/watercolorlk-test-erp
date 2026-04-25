<?php

declare(strict_types=1);

// Copy this file to config/local.php on the server and fill actual values.
// Do not commit config/local.php.
return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => 3306,
    'DB_NAME' => 'your_db_name',
    'DB_USER' => 'your_db_user',
    'DB_PASS' => 'your_db_password',

    'ERP_BASE_URL' => 'https://erppro.lk/public',
    'ERP_CLIENT_ID' => 3,
    'ERP_CLIENT_SECRET' => 'your_client_secret',
    'ERP_USERNAME' => 'your_erp_username',
    'ERP_PASSWORD' => 'your_erp_password',
    'ERP_LOCATION_ID' => 5,
    'ERP_ORDER_ENDPOINT' => '/connector/api/sell',

    'SYNC_WEBHOOK_KEY' => 'change-me-strong-key',
];
