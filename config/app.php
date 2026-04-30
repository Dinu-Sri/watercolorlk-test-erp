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

// Optional Google Business Profile integration.
define('GOOGLE_PLACE_ID', (string)$cfg('GOOGLE_PLACE_ID', ''));
define('GOOGLE_PLACES_API_KEY', (string)$cfg('GOOGLE_PLACES_API_KEY', ''));
define('GOOGLE_REVIEWS_URL', (string)$cfg('GOOGLE_REVIEWS_URL', ''));

// Public site URL (used for absolute URLs in emails / OAuth redirects).
define('SITE_URL', rtrim((string)$cfg('SITE_URL', ''), '/'));
define('SITE_NAME', (string)$cfg('SITE_NAME', 'Watercolor.LK'));

// Outgoing mail.
define('MAIL_FROM',       (string)$cfg('MAIL_FROM', 'no-reply@watercolor.lk'));
define('MAIL_FROM_NAME',  (string)$cfg('MAIL_FROM_NAME', SITE_NAME));
define('MAIL_REPLY_TO',   (string)$cfg('MAIL_REPLY_TO', ''));
define('MAIL_TRANSPORT',  (string)$cfg('MAIL_TRANSPORT', 'mail'));   // mail | smtp | log
define('SMTP_HOST',       (string)$cfg('SMTP_HOST', ''));
define('SMTP_PORT',       (int)   $cfg('SMTP_PORT', 587));
define('SMTP_USER',       (string)$cfg('SMTP_USER', ''));
define('SMTP_PASS',       (string)$cfg('SMTP_PASS', ''));
define('SMTP_ENCRYPTION', (string)$cfg('SMTP_ENCRYPTION', 'tls'));   // tls | ssl | ''

// Google Sign-In OAuth (Web application credentials).
define('GOOGLE_OAUTH_CLIENT_ID',     (string)$cfg('GOOGLE_OAUTH_CLIENT_ID', ''));
define('GOOGLE_OAUTH_CLIENT_SECRET', (string)$cfg('GOOGLE_OAUTH_CLIENT_SECRET', ''));
define('GOOGLE_OAUTH_REDIRECT',      (string)$cfg('GOOGLE_OAUTH_REDIRECT', ''));
