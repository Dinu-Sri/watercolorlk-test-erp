<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$auth = appUserAuth();
$user = $auth->require('login.php');
$orders = (new OrderRepository(appDb()))->listForUser((int)$user['id'], 100);

$pageTitle = 'My orders · Watercolor.LK';
require __DIR__ . '/_chrome.php';
?>
<div class="acc-grid">
    <aside class="acc-side">
        <a href="index.php">Overview</a>
        <a class="active" href="orders.php">My orders</a>
        <a href="profile.php">Profile &amp; addresses</a>
        <a href="logout.php" style="color:#b8232f;">Log out</a>
    </aside>
    <div class="acc-card">
        <h1>My orders</h1>
        <?php if (!$orders): ?>
            <p class="acc-meta">No orders yet. <a class="acc-link" href="../shop.php">Browse the shop &rarr;</a></p>
        <?php else: ?>
            <table class="acc-table">
                <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a class="acc-link" href="order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime((string)$o['created_at']))) ?></td>
                        <td><?= (int)$o['item_count'] ?></td>
                        <td>LKR <?= number_format((float)$o['total_amount'], 2) ?></td>
                        <td><?= htmlspecialchars(strtoupper((string)$o['payment_method'])) ?></td>
                        <td><span class="pill pill-<?= htmlspecialchars((string)$o['status']) ?>"><?= htmlspecialchars((string)$o['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
