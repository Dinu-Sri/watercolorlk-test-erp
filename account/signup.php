<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$auth = appUserAuth();
$auth->ensureSession();

$next = (string)($_GET['next'] ?? '/account/index.php');
$next = preg_match('#^/[a-z0-9_./?=&%-]*$#i', $next) ? $next : '/account/index.php';

$err = null;
$prefill = ['email' => '', 'full_name' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::check($_POST['_csrf'] ?? '')) {
        $err = 'Session expired. Please try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $fullName = trim((string)($_POST['full_name'] ?? '')) ?: null;
        $phone = trim((string)($_POST['phone'] ?? '')) ?: null;
        $prefill = ['email' => $email, 'full_name' => (string)$fullName, 'phone' => (string)$phone];

        $res = $auth->signup($email, $password, $fullName, $phone);
        if ($res['ok']) {
            $userId = (int)$res['user_id'];
            /* Send verification email (best-effort) */
            try {
                $repo = new UserRepository(appDb());
                $token = $repo->issueToken($userId, 'verify_email', 60 * 24);
                $url = SITE_URL . '/account/verify.php?token=' . urlencode($token);
                $body = '<p>Welcome to Watercolor.LK!</p>'
                      . '<p>Please confirm your email address so we can keep your orders and account secure.</p>';
                $html = appMailer()->renderLayout('Verify your email', $body, $url, 'Verify email');
                appMailer()->send($email, 'Verify your Watercolor.LK email', $html);
            } catch (Throwable $e) {
                /* swallow - user can re-trigger from account page */
            }
            $auth->login($userId);
            header('Location: index.php?signed_up=1');
            exit;
        }
        $err = $res['error'];
    }
}

$pageTitle = 'Create account · Watercolor.LK';
require __DIR__ . '/_chrome.php';
$googleEnabled = GoogleOAuth::isConfigured();
?>
<div class="acc-card" style="max-width:480px;margin:0 auto;">
    <h1>Create account</h1>
    <p class="lead">Join Watercolor.LK to track orders, save addresses, and check out faster.</p>

    <?php if ($err): ?><div class="acc-msg err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form class="acc-form" method="post" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
        <div>
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" type="text" autocomplete="name" value="<?= htmlspecialchars($prefill['full_name']) ?>">
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="email" value="<?= htmlspecialchars($prefill['email']) ?>">
        </div>
        <div>
            <label for="phone">Phone (optional)</label>
            <input id="phone" name="phone" type="tel" autocomplete="tel" value="<?= htmlspecialchars($prefill['phone']) ?>">
        </div>
        <div>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
            <small style="color:#7a8699;">Minimum 8 characters.</small>
        </div>
        <button class="btn-primary" type="submit">Create account</button>
    </form>

    <?php if ($googleEnabled): ?>
        <div class="acc-divider">or</div>
        <a class="btn-google" href="google-start.php?next=<?= urlencode($next) ?>">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3a12 12 0 0 1-11.3 8 12 12 0 1 1 7.9-21.1l5.7-5.7A20 20 0 1 0 44 24c0-1.2-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8A12 12 0 0 1 24 12c3 0 5.7 1.1 7.9 3l5.7-5.7A20 20 0 0 0 6.3 14.7z"/><path fill="#4CAF50" d="M24 44a20 20 0 0 0 13.5-5.2l-6.2-5.3A12 12 0 0 1 12.7 29l-6.5 5A20 20 0 0 0 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3a12 12 0 0 1-4 5.5l6.2 5.2c-.5.4 6.7-4.9 6.7-14.7 0-1.2-.1-2.4-.4-3.5z"/></svg>
            Continue with Google
        </a>
    <?php endif; ?>

    <p class="acc-meta">
        Already have an account? <a class="acc-link" href="login.php">Log in</a>
    </p>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
