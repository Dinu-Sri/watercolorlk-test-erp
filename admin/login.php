<?php

declare(strict_types=1);

$GLOBALS['ADMIN_PUBLIC_PAGE'] = true;
require_once __DIR__ . '/_bootstrap.php';

if (AdminAuth::user()) {
    header('Location: ' . adminUrl('index.php'));
    exit;
}

$error = '';
$username = '';
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
if (!preg_match('#^/admin/[A-Za-z0-9._\-/?&=]*$#', $next)) {
    $next = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $error = 'Enter username and password.';
        } else {
            $result = AdminAuth::attemptLogin(new AdminUserRepository(appDb()), $username, $password);
            if ($result['ok']) {
                $target = $next !== '' ? $next : adminUrl('index.php');
                header('Location: ' . $target);
                exit;
            }
            $error = (string)$result['error'];
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin sign in · Watercolor.LK</title>
<style>
body { margin: 0; font: 14px/1.5 "Source Sans 3", "Segoe UI", Tahoma, sans-serif; background: linear-gradient(135deg, #0e1b30 0%, #16253a 100%); color: #16253a; min-height: 100vh; display: grid; place-items: center; padding: 20px; }
.card { background: #fff; padding: 32px 30px; border-radius: 16px; width: 100%; max-width: 380px; box-shadow: 0 18px 60px rgba(0,0,0,.25); }
.card h1 { margin: 0 0 4px; font: 800 1.5rem/1.1 "Playfair Display", "Georgia", serif; color: #16253a; }
.card .sub { color: #6b7388; font-size: .9rem; margin-bottom: 22px; }
.field { margin-bottom: 14px; }
.field label { display: block; font: 700 .74rem/1 "Montserrat", sans-serif; letter-spacing: .04em; text-transform: uppercase; color: #4a5468; margin-bottom: 5px; }
.field input { width: 100%; box-sizing: border-box; padding: 11px 13px; border: 1px solid #e5e8ee; border-radius: 9px; font-size: .95rem; }
.field input:focus { outline: none; border-color: #e8760a; box-shadow: 0 0 0 3px rgba(232,118,10,.15); }
.btn { width: 100%; padding: 12px; background: #e8760a; color: #fff; border: 0; border-radius: 9px; font: 800 .92rem/1 "Montserrat", sans-serif; cursor: pointer; }
.btn:hover { background: #cf6707; }
.err { background: #fdecec; color: #8a1620; border: 1px solid #f5b9bd; padding: 10px 12px; border-radius: 8px; margin-bottom: 14px; font-weight: 600; font-size: .88rem; }
</style>
</head>
<body>
<form class="card" method="post">
    <h1>Watercolor.LK</h1>
    <div class="sub">Admin dashboard sign in</div>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?= Csrf::field() ?>
    <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES) ?>">
    <div class="field">
        <label>Username</label>
        <input type="text" name="username" autocomplete="username" autofocus value="<?= htmlspecialchars($username, ENT_QUOTES) ?>">
    </div>
    <div class="field">
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password">
    </div>
    <button type="submit" class="btn">Sign in</button>
</form>
</body>
</html>
