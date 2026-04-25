<?php

declare(strict_types=1);

// Database configuration.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'watercolorlk_store');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ERP OAuth + API configuration.
define('ERP_BASE_URL', getenv('ERP_BASE_URL') ?: 'https://erppro.lk/public');
define('ERP_CLIENT_ID', (int)(getenv('ERP_CLIENT_ID') ?: 3));
define('ERP_CLIENT_SECRET', getenv('ERP_CLIENT_SECRET') ?: '');
define('ERP_USERNAME', getenv('ERP_USERNAME') ?: '');
define('ERP_PASSWORD', getenv('ERP_PASSWORD') ?: '');
define('ERP_LOCATION_ID', (int)(getenv('ERP_LOCATION_ID') ?: 5));

// ERP order writeback endpoint. Confirm exact route in your ERP instance.
define('ERP_ORDER_ENDPOINT', getenv('ERP_ORDER_ENDPOINT') ?: '/connector/api/sell');

// Simple key to protect manual sync routes.
define('SYNC_WEBHOOK_KEY', getenv('SYNC_WEBHOOK_KEY') ?: 'change-me');
