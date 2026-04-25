<?php

declare(strict_types=1);

// Optional server-only config file. Do not commit local.php.
$localConfig = [];
$localConfigPath = __DIR__ . '/local.php';
if (is_file($localConfigPath)) {
	$loaded = require $localConfigPath;
	if (is_array($loaded)) {
		$localConfig = $loaded;
	}
}

$cfg = static function (string $key, mixed $default) use ($localConfig): mixed {
	$env = getenv($key);
	if ($env !== false && $env !== '') {
		return $env;
	}

	if (array_key_exists($key, $localConfig) && $localConfig[$key] !== '') {
		return $localConfig[$key];
	}

	return $default;
};

// Database configuration.
define('DB_HOST', (string)$cfg('DB_HOST', 'localhost'));
define('DB_PORT', (int)$cfg('DB_PORT', 3306));
define('DB_NAME', (string)$cfg('DB_NAME', 'watercolorlk_store'));
define('DB_USER', (string)$cfg('DB_USER', 'root'));
define('DB_PASS', (string)$cfg('DB_PASS', ''));

// ERP OAuth + API configuration.
define('ERP_BASE_URL', (string)$cfg('ERP_BASE_URL', 'https://erppro.lk/public'));
define('ERP_CLIENT_ID', (int)$cfg('ERP_CLIENT_ID', 3));
define('ERP_CLIENT_SECRET', (string)$cfg('ERP_CLIENT_SECRET', ''));
define('ERP_USERNAME', (string)$cfg('ERP_USERNAME', ''));
define('ERP_PASSWORD', (string)$cfg('ERP_PASSWORD', ''));
define('ERP_LOCATION_ID', (int)$cfg('ERP_LOCATION_ID', 5));

// ERP order writeback endpoint. Confirm exact route in your ERP instance.
define('ERP_ORDER_ENDPOINT', (string)$cfg('ERP_ORDER_ENDPOINT', '/connector/api/sell'));

// Simple key to protect manual sync routes.
define('SYNC_WEBHOOK_KEY', (string)$cfg('SYNC_WEBHOOK_KEY', 'change-me'));
