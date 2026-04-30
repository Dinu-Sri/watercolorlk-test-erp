<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$repo = new UserRepository(appDb());

$token = (string)($_REQUEST['token'] ?? '');
$err = null;
$ok = false;
$consumed = null;

/* Validate token (peek only, do not consume on GET). */
if ($token === '') {
    $err = 'Missing reset token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    if (!Csrf::check($_POST['_csrf'] ?? '')) {
        $err = 'Session expired. Please try again.';
    } else {
        $rl = appRateLimiter();
        $gate = $rl->check('reset', null);
        if (!$gate['ok']) {
            $mins = max(1, (int)ceil($gate['retry_after'] / 60));
            $err = 'Too many attempts. Please try again in ' . $mins . ' minute' . ($mins === 1 ? '' : 's') . '.';
        } else {
            $pwd = (string)($_POST['password'] ?? '');
            $pwd2 = (string)($_POST['password2'] ?? '');
            if (strlen($pwd) < 8) {
                $err = 'Password must be at least 8 characters.';
                $rl->record('reset', null, false);
            } elseif ($pwd !== $pwd2) {
                $err = 'Passwords do not match.';
                $rl->record('reset', null, false);
            } else {
                $consumed = $repo->consumeToken($token, 'reset_password');
                if (!$consumed) {
                    $err = 'This reset link is invalid or has expired.';
                    $rl->record('reset', null, false);
                } else {
                    $repo->update((int)$consumed['user_id'], [
                        'password_hash' => password_hash($pwd, PASSWORD_BCRYPT),
                    ]);
                    /* Invalidate any other outstanding reset tokens for safety. */
                    $repo->invalidateTokens((int)$consumed['user_id'], 'reset_password');
                    $rl->record('reset', null, true);
                    $ok = true;
                    header('Location: login.php?password_reset=1');
                    exit;
                }
            }
        }
    }
}

$pageTitle = 'Reset password · Watercolor.LK';
require __DIR__ . '/_chrome.php';
?>
<div class="acc-card" style="max-width:480px;margin:0 auto;">
    <h1>Reset password</h1>
    <p class="lead">Choose a new password for your account.</p>

    <?php if ($err): ?><div class="acc-msg err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php if ($token !== ''): ?>
    <form class="acc-form" method="post" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div>
            <label for="password">New password</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        </div>
        <div>
            <label for="password2">Confirm new password</label>
            <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
        </div>
        <button class="btn-primary" type="submit">Set new password</button>
    </form>
    <?php else: ?>
        <p><a class="acc-link" href="forgot.php">Request a new reset link</a></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
