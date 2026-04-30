<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (!GoogleOAuth::isConfigured()) {
    header('Location: login.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_next']  = (string)($_GET['next'] ?? '/account/index.php');

header('Location: ' . GoogleOAuth::authorizeUrl($state));
exit;
