<?php

declare(strict_types=1);

$GLOBALS['ADMIN_PUBLIC_PAGE'] = true;
require_once __DIR__ . '/_bootstrap.php';

AdminAuth::logout();
header('Location: ' . adminUrl('login.php'));
exit;
