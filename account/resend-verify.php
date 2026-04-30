<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$auth = appUserAuth();
$user = $auth->require('login.php');

if (!empty($user['email_verified_at'])) {
    header('Location: index.php');
    exit;
}

try {
    $repo = new UserRepository(appDb());
    $repo->invalidateTokens((int)$user['id'], 'verify_email');
    $token = $repo->issueToken((int)$user['id'], 'verify_email', 60 * 24);
    $url = SITE_URL . '/account/verify.php?token=' . urlencode($token);
    $body = '<p>Hi ' . htmlspecialchars((string)($user['full_name'] ?? '')) . ',</p>'
          . '<p>Tap the button below to verify your email address.</p>';
    $html = appMailer()->renderLayout('Verify your email', $body, $url, 'Verify email');
    appMailer()->send((string)$user['email'], 'Verify your Watercolor.LK email', $html);
} catch (Throwable $e) {
    /* shown generically */
}

$pageTitle = 'Verification email sent · Watercolor.LK';
require __DIR__ . '/_chrome.php';
?>
<div class="acc-card" style="max-width:520px;margin:0 auto;">
    <h1>Email sent</h1>
    <p class="lead">We've sent a fresh verification link to <strong><?= htmlspecialchars((string)$user['email']) ?></strong>. Please check your inbox.</p>
    <p class="acc-meta"><a class="acc-link" href="index.php">&larr; Back to my account</a></p>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
