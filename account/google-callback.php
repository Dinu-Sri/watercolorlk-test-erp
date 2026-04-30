<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$err = null;

try {
    if (!GoogleOAuth::isConfigured()) {
        throw new RuntimeException('Google Sign-In is not configured on this site yet.');
    }
    if (!empty($_GET['error'])) {
        throw new RuntimeException('Google sign-in cancelled.');
    }
    $code  = (string)($_GET['code'] ?? '');
    $state = (string)($_GET['state'] ?? '');
    $expected = (string)($_SESSION['google_oauth_state'] ?? '');
    unset($_SESSION['google_oauth_state']);

    if ($code === '' || $state === '' || !hash_equals($expected, $state)) {
        throw new RuntimeException('Invalid OAuth state. Please try again.');
    }

    $info = GoogleOAuth::exchange($code);
    if (empty($info['email_verified'])) {
        throw new RuntimeException('Your Google email is not verified.');
    }

    $repo = new UserRepository(appDb());
    $auth = appUserAuth();

    /* 1) Existing match by google_sub */
    $u = $repo->findByGoogleSub($info['sub']);
    /* 2) Otherwise match by email and link Google to that account */
    if (!$u) {
        $u = $repo->findByEmail($info['email']);
        if ($u) {
            $repo->update((int)$u['id'], [
                'google_sub' => $info['sub'],
                'avatar_url' => $info['picture'] ?? $u['avatar_url'],
                'email_verified_at' => $u['email_verified_at'] ?: date('Y-m-d H:i:s'),
            ]);
            $u = $repo->findById((int)$u['id']);
        }
    }
    /* 3) Otherwise create a new account */
    if (!$u) {
        $newId = $repo->create([
            'email' => $info['email'],
            'full_name' => $info['name'] ?? null,
            'google_sub' => $info['sub'],
            'avatar_url' => $info['picture'] ?? null,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $u = $repo->findById($newId);
    }

    if (!$u || $u['status'] !== 'active') {
        throw new RuntimeException('This account has been disabled.');
    }
    $auth->login((int)$u['id']);

    $next = (string)($_SESSION['google_oauth_next'] ?? '/account/index.php');
    unset($_SESSION['google_oauth_next']);
    if (!preg_match('#^/[a-z0-9_./?=&%-]*$#i', $next)) $next = '/account/index.php';
    header('Location: ' . $next);
    exit;
} catch (Throwable $e) {
    $err = $e->getMessage();
}

$pageTitle = 'Google Sign-In · Watercolor.LK';
require __DIR__ . '/_chrome.php';
?>
<div class="acc-card" style="max-width:480px;margin:0 auto;">
    <h1>Sign-in failed</h1>
    <div class="acc-msg err"><?= htmlspecialchars((string)$err) ?></div>
    <p><a class="acc-link" href="login.php">Back to login</a></p>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
