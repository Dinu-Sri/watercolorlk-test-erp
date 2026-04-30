<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$repo = new UserRepository(appDb());
$auth = appUserAuth();
$auth->ensureSession();

$err = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::check($_POST['_csrf'] ?? '')) {
        $err = 'Session expired. Please try again.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid email address.';
        } else {
            $u = $repo->findByEmail($email);
            if ($u && empty($u['password_hash']) && !empty($u['google_sub'])) {
                $err = 'This account uses Google Sign-In. Click "Continue with Google" on the login page.';
            } elseif ($u) {
                try {
                    $token = $repo->issueToken((int)$u['id'], 'reset_password', 60);
                    $url = SITE_URL . '/account/reset.php?token=' . urlencode($token);
                    $body = '<p>We received a request to reset your Watercolor.LK password.</p>'
                          . '<p>Click the button below to choose a new one. The link expires in 60 minutes.</p>';
                    $html = appMailer()->renderLayout('Reset your password', $body, $url, 'Choose a new password');
                    appMailer()->send($email, 'Reset your Watercolor.LK password', $html);
                } catch (Throwable $e) {
                    /* keep generic UX message */
                }
            }
            /* Always show generic confirmation - prevents email enumeration. */
            $ok = 'If that email exists in our system, a reset link is on its way. Check your inbox.';
        }
    }
}

$pageTitle = 'Forgot password · Watercolor.LK';
require __DIR__ . '/_chrome.php';
?>
<div class="acc-card" style="max-width:480px;margin:0 auto;">
    <h1>Forgot password</h1>
    <p class="lead">Enter your email and we'll send you a link to reset it.</p>

    <?php if ($err): ?><div class="acc-msg err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="acc-msg ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <form class="acc-form" method="post" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="email">
        </div>
        <button class="btn-primary" type="submit">Send reset link</button>
    </form>

    <p class="acc-meta">
        <a class="acc-link" href="login.php">Back to log in</a>
    </p>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
