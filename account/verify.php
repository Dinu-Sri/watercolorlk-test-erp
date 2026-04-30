<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$repo = new UserRepository(appDb());
$auth = appUserAuth();
$auth->ensureSession();

$token = (string)($_GET['token'] ?? '');
$err = null;
$ok = false;

if ($token === '') {
    $err = 'Missing verification token.';
} else {
    $row = $repo->consumeToken($token, 'verify_email');
    if (!$row) {
        $err = 'This verification link is invalid or has expired.';
    } else {
        $repo->markVerified((int)$row['user_id']);
        $ok = true;
        /* Auto-login on click-through. */
        $auth->login((int)$row['user_id']);
        header('Location: index.php?verified=1');
        exit;
    }
}

$pageTitle = 'Verify email · Watercolor.LK';
require __DIR__ . '/_chrome.php';
?>
<div class="acc-card" style="max-width:480px;margin:0 auto;">
    <h1>Verify email</h1>
    <?php if ($err): ?>
        <div class="acc-msg err"><?= htmlspecialchars($err) ?></div>
        <p><a class="acc-link" href="index.php">Go to your account</a> to request a new verification email.</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
