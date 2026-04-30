<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$auth = appUserAuth();
$auth->logout();
header('Location: login.php?logged_out=1');
exit;
