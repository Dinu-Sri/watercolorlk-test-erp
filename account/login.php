<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$auth = appUserAuth();
$auth->ensureSession();

$next = (string)($_GET['next'] ?? '/account/index.php');
$next = preg_match('#^/[a-z0-9_./?=&%-]*$#i', $next) ? $next : '/account/index.php';

$err = null;
$prefillEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::check($_POST['_csrf'] ?? '')) {
        $err = 'Session expired. Please try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $prefillEmail = $email;
        $res = $auth->attemptPassword($email, $password);
        if ($res['ok']) {
            header('Location: ' . $next);
            exit;
        }
        $err = $res['error'];
    }
}

$pageTitle = 'Log in · Watercolor.LK';
require __DIR__ . '/_chrome.php';
$googleEnabled = GoogleOAuth::isConfigured();
?>
<div class="acc-card" style="max-width:480px;margin:0 auto;">
    <h1>Log in</h1>
    <p class="lead">Welcome back. Track your orders, save addresses, and check out faster.</p>

    <?php if ($err): ?><div class="acc-msg err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if (!empty($_GET['signed_up'])): ?><div class="acc-msg ok">Account created. Please verify your email — we sent you a link.</div><?php endif; ?>
    <?php if (!empty($_GET['password_reset'])): ?><div class="acc-msg ok">Password updated. You can log in now.</div><?php endif; ?>
    <?php if (!empty($_GET['verified'])): ?><div class="acc-msg ok">Email verified. Welcome!</div><?php endif; ?>
    <?php if (!empty($_GET['logged_out'])): ?><div class="acc-msg ok">You have been logged out.</div><?php endif; ?>

    <form class="acc-form" method="post" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="email" value="<?= htmlspecialchars($prefillEmail) ?>">
        </div>
        <div>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn-primary" type="submit">Log in</button>
    </form>

    <?php if ($googleEnabled): ?>
        <div class="acc-divider">or</div>
        <a class="btn-google" href="google-start.php?next=<?= urlencode($next) ?>">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3a12 12 0 0 1-11.3 8 12 12 0 1 1 7.9-21.1l5.7-5.7A20 20 0 1 0 44 24c0-1.2-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8A12 12 0 0 1 24 12c3 0 5.7 1.1 7.9 3l5.7-5.7A20 20 0 0 0 6.3 14.7z"/><path fill="#4CAF50" d="M24 44a20 20 0 0 0 13.5-5.2l-6.2-5.3A12 12 0 0 1 12.7 29l-6.5 5A20 20 0 0 0 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3a12 12 0 0 1-4 5.5l6.2 5.2c-.5.4 6.7-4.9 6.7-14.7 0-1.2-.1-2.4-.4-3.5z"/></svg>
            Continue with Google
        </a>
    <?php endif; ?>

    <p class="acc-meta">
        <a class="acc-link" href="forgot.php">Forgot password?</a>
        &nbsp;·&nbsp;
        New here? <a class="acc-link" href="signup.php?next=<?= urlencode($next) ?>">Create an account</a>
    </p>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
