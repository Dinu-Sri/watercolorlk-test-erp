<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$repo = new OrderRepository(appDb());
$order = $id > 0 ? $repo->getOrderWithItems($id) : null;

if (!$order) {
    Flash::error('Order not found.');
    header('Location: ' . adminUrl('orders.php'));
    exit;
}

$pageTitle = 'Order #' . $id;
$activeNav = 'orders';
require __DIR__ . '/_layout_top.php';
?>
<style>
.pill { display:inline-block; padding:2px 9px; border-radius:999px; font:700 .72rem/1.6 'Source Sans 3',sans-serif; text-transform:uppercase; letter-spacing:.04em; }
.pill-pending { background:#fff3cd; color:#7a5b00; }
.pill-processing { background:#dbe9ff; color:#1b3d8f; }
.pill-completed { background:#d6f1de; color:#17633e; }
.pill-cancelled { background:#fde2e2; color:#a31621; }
.pill-synced { background:#d6f1de; color:#17633e; }
.pill-failed { background:#fde2e2; color:#a31621; }
.kv { display:grid; grid-template-columns:160px 1fr; gap:6px 14px; font:600 .92rem/1.4 'Source Sans 3',sans-serif; color:#0f2440; }
.kv .lbl { color:#6b7388; font-weight:700; }
</style>

<p style="margin:0 0 14px;"><a class="btn small" href="<?= h(adminUrl('orders.php')) ?>">&larr; All orders</a></p>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin:0;">Order #<?= (int)$order['id'] ?></h2>
            <div class="muted">Placed <?= h(date('M j, Y · H:i', strtotime((string)$order['created_at']))) ?></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <span class="pill pill-<?= h((string)$order['status']) ?>">Status: <?= h((string)$order['status']) ?></span>
            <span class="pill pill-<?= h((string)$order['erp_sync_status']) ?>">ERP: <?= h((string)$order['erp_sync_status']) ?></span>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div class="card">
        <h3 style="margin:0 0 10px;">Customer</h3>
        <div class="kv">
            <span class="lbl">Name</span><span><?= h((string)$order['customer_name']) ?></span>
            <span class="lbl">Phone</span><span><?= h((string)$order['customer_phone']) ?></span>
            <span class="lbl">Email</span><span><?= h((string)($order['customer_email'] ?? '—')) ?></span>
            <?php if (!empty($order['user_id'])): ?>
                <span class="lbl">Account</span><span>User #<?= (int)$order['user_id'] ?></span>
            <?php endif; ?>
            <span class="lbl">Payment</span><span><?= h(strtoupper((string)$order['payment_method'])) ?></span>
            <?php if (!empty($order['notes'])): ?>
                <span class="lbl">Notes</span><span><?= nl2br(h((string)$order['notes'])) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <h3 style="margin:0 0 10px;">Totals</h3>
        <div class="kv">
            <span class="lbl">Subtotal</span><span>LKR <?= number_format((float)$order['subtotal_amount'], 2) ?></span>
            <?php if ((float)$order['discount_amount'] > 0): ?>
                <span class="lbl">Discount<?= !empty($order['coupon_code']) ? ' (' . h((string)$order['coupon_code']) . ')' : '' ?></span>
                <span style="color:#17633e;">− LKR <?= number_format((float)$order['discount_amount'], 2) ?></span>
            <?php endif; ?>
            <span class="lbl">Shipping</span><span><?= (float)$order['shipping_amount'] === 0.0 ? 'FREE' : 'LKR ' . number_format((float)$order['shipping_amount'], 2) ?></span>
            <span class="lbl"><strong>Total</strong></span><span><strong>LKR <?= number_format((float)$order['total_amount'], 2) ?></strong></span>
        </div>
        <?php if (!empty($order['sync_error'])): ?>
            <div style="margin-top:12px;padding:10px 12px;background:#fde2e2;color:#a31621;border-radius:8px;font:600 .85rem/1.4 'Source Sans 3',sans-serif;">
                <strong>ERP error:</strong> <?= h((string)$order['sync_error']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3 style="margin:0 0 10px;">Items</h3>
    <table class="table">
        <thead><tr><th>Kind</th><th>Item</th><th>SKU</th><th>Qty</th><th>Unit</th><th>Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($order['items'] as $it): ?>
            <tr<?= $it['kind'] === 'pack_child' ? ' style="background:#fafafa;color:#666;"' : '' ?>>
                <td><span class="pill"><?= h((string)$it['kind']) ?></span></td>
                <td><?= h((string)$it['display_label']) ?></td>
                <td><?= h((string)($it['sku'] ?: '—')) ?></td>
                <td><?= rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.') ?></td>
                <td>LKR <?= number_format((float)$it['unit_price'], 2) ?></td>
                <td>LKR <?= number_format((float)$it['unit_price'] * (float)$it['quantity'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3 style="margin:0 0 10px;">Actions</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <form method="post" action="<?= h(adminUrl('actions/order-retry.php')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <button class="btn primary" type="submit">Retry ERP sync</button>
        </form>
        <form method="post" action="<?= h(adminUrl('actions/order-status.php')) ?>" style="display:flex;gap:6px;align-items:center;">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <select name="status">
                <?php foreach (['pending','processing','completed','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn dark" type="submit">Update status</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
