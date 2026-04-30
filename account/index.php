<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$auth = appUserAuth();
$user = $auth->require('login.php');

$repo = new UserRepository(appDb());
$orders = (new OrderRepository(appDb()))->listForUser((int)$user['id'], 5);

$pageTitle = 'My account · Watercolor.LK';
require __DIR__ . '/_chrome.php';
?>
<div class="acc-grid">
    <aside class="acc-side">
        <a class="active" href="index.php">Overview</a>
        <a href="orders.php">My orders</a>
        <a href="profile.php">Profile &amp; addresses</a>
        <a href="logout.php" style="color:#b8232f;">Log out</a>
    </aside>
    <div class="acc-card">
        <h1>Welcome<?= $user['full_name'] ? ', ' . htmlspecialchars(explode(' ', (string)$user['full_name'])[0]) : '' ?></h1>
        <p class="lead"><?= htmlspecialchars((string)$user['email']) ?>
            <?php if (empty($user['email_verified_at'])): ?>
                &nbsp;·&nbsp;<a class="acc-link" href="resend-verify.php" style="color:#b8232f;">Verify email</a>
            <?php else: ?>
                &nbsp;·&nbsp;<span style="color:#17633e;">✓ Verified</span>
            <?php endif; ?>
        </p>

        <h2 style="font:800 1.05rem/1 'Montserrat',sans-serif;color:#0f2440;margin:20px 0 10px;">Recent orders</h2>
        <?php if (!$orders): ?>
            <p class="acc-meta">You haven't placed any orders yet. <a class="acc-link" href="../shop.php">Browse the shop &rarr;</a></p>
        <?php else: ?>
            <table class="acc-table">
                <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a class="acc-link" href="order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime((string)$o['created_at']))) ?></td>
                        <td><?= (int)$o['item_count'] ?></td>
                        <td>LKR <?= number_format((float)$o['total_amount'], 2) ?></td>
                        <td><span class="pill pill-<?= htmlspecialchars((string)$o['status']) ?>"><?= htmlspecialchars((string)$o['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="acc-meta"><a class="acc-link" href="orders.php">View all orders &rarr;</a></p>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
