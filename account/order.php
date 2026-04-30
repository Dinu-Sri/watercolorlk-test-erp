<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$auth = appUserAuth();
$user = $auth->require('login.php');

$id = (int)($_GET['id'] ?? 0);
$order = (new OrderRepository(appDb()))->getUserOrder((int)$user['id'], $id);
if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found · Watercolor.LK';
    require __DIR__ . '/_chrome.php';
    echo '<div class="acc-card"><h1>Order not found</h1><p>This order does not exist or you do not have access.</p><p><a class="acc-link" href="orders.php">Back to my orders</a></p></div>';
    require __DIR__ . '/_chrome_end.php';
    exit;
}

$pageTitle = 'Order #' . (int)$order['id'] . ' · Watercolor.LK';
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
        <p class="acc-meta"><a class="acc-link" href="orders.php">&larr; All orders</a></p>
        <h1>Order #<?= (int)$order['id'] ?></h1>
        <p class="lead">
            Placed <?= htmlspecialchars(date('M j, Y · g:i a', strtotime((string)$order['created_at']))) ?>
            &nbsp;·&nbsp; <span class="pill pill-<?= htmlspecialchars((string)$order['status']) ?>"><?= htmlspecialchars((string)$order['status']) ?></span>
            &nbsp;·&nbsp; ERP <span class="pill pill-<?= htmlspecialchars((string)$order['erp_sync_status']) ?>"><?= htmlspecialchars((string)$order['erp_sync_status']) ?></span>
        </p>

        <table class="acc-table">
            <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Unit</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($order['items'] as $it):
                if ($it['kind'] === 'pack_child') continue; /* hide as part of parent listing on customer view */
                $label = (string)($it['display_label'] ?: 'Item');
                $isPack = $it['kind'] === 'pack';
            ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($label) ?></strong>
                        <?php if ($isPack): ?><div style="color:#7a8699;font-size:.82rem;">Pack of items</div><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string)($it['sku'] ?: '—')) ?></td>
                    <td><?= rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.') ?></td>
                    <td>LKR <?= number_format((float)$it['unit_price'], 2) ?></td>
                    <td>LKR <?= number_format((float)$it['unit_price'] * (float)$it['quantity'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <table class="acc-table" style="margin-top:14px;max-width:380px;margin-left:auto;">
            <tr><th>Subtotal</th><td>LKR <?= number_format((float)$order['subtotal_amount'], 2) ?></td></tr>
            <?php if ((float)$order['discount_amount'] > 0): ?>
                <tr><th>Discount<?= !empty($order['coupon_code']) ? ' (' . htmlspecialchars((string)$order['coupon_code']) . ')' : '' ?></th>
                    <td style="color:#17633e;">− LKR <?= number_format((float)$order['discount_amount'], 2) ?></td></tr>
            <?php endif; ?>
            <tr><th>Shipping</th><td><?= (float)$order['shipping_amount'] === 0.0 ? 'FREE' : 'LKR ' . number_format((float)$order['shipping_amount'], 2) ?></td></tr>
            <tr><th>Total</th><td><strong>LKR <?= number_format((float)$order['total_amount'], 2) ?></strong></td></tr>
            <tr><th>Payment</th><td><?= htmlspecialchars(strtoupper((string)$order['payment_method'])) ?></td></tr>
        </table>
    </div>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
