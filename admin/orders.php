<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new OrderRepository(appDb());

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'status' => (string)($_GET['status'] ?? ''),
    'sync' => (string)($_GET['sync'] ?? ''),
    'page' => $page,
    'per_page' => $perPage,
];

$res = $repo->adminList($filters);
$rows = $res['rows'];
$total = (int)$res['total'];
$pagination = new Pagination($page, $perPage, $total);

$pageTitle = 'Orders';
$activeNav = 'orders';
require __DIR__ . '/_layout_top.php';
?>
<div class="card">
    <form class="toolbar" method="get">
        <input type="text" name="q" value="<?= h($filters['q']) ?>" placeholder="Search id, name, phone, email…" style="min-width:260px">
        <select name="status">
            <option value="">Any status</option>
            <?php foreach (['pending','processing','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sync">
            <option value="">Any sync</option>
            <?php foreach (['pending','synced','failed'] as $s): ?>
                <option value="<?= $s ?>" <?= $filters['sync'] === $s ? 'selected' : '' ?>>ERP: <?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn dark">Filter</button>
        <a class="btn" href="<?= h(adminUrl('orders.php')) ?>">Reset</a>
        <span class="muted" style="margin-left:auto"><?= $total ?> result<?= $total === 1 ? '' : 's' ?></span>
    </form>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="muted" style="padding:18px;">No orders match those filters.</div>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>#</th><th>Date</th><th>Customer</th><th>Phone</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>ERP sync</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong>#<?= (int)$r['id'] ?></strong></td>
                <td><?= h(date('M j, Y · H:i', strtotime((string)$r['created_at']))) ?></td>
                <td><?= h((string)$r['customer_name']) ?><?php if (!empty($r['user_id'])): ?> <span class="badge" style="background:#e8f0fe;color:#1b3d8f;">acct</span><?php endif; ?></td>
                <td><?= h((string)$r['customer_phone']) ?></td>
                <td><?= (int)$r['item_count'] ?></td>
                <td>LKR <?= number_format((float)$r['total_amount'], 2) ?></td>
                <td><?= h(strtoupper((string)$r['payment_method'])) ?></td>
                <td><span class="pill pill-<?= h((string)$r['status']) ?>"><?= h((string)$r['status']) ?></span></td>
                <td><span class="pill pill-<?= h((string)$r['erp_sync_status']) ?>"><?= h((string)$r['erp_sync_status']) ?></span></td>
                <td><a class="btn small" href="<?= h(adminUrl('order-view.php?id=' . (int)$r['id'])) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php if ($pagination->totalPages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $pagination->totalPages; $p++): ?>
            <?php if ($p === $pagination->page): ?><span class="active"><?= $p ?></span>
            <?php else: ?><a href="<?= h($pagination->url(adminUrl('orders.php'), ['q' => $filters['q'], 'status' => $filters['status'], 'sync' => $filters['sync']], $p)) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<style>
.pill { display:inline-block; padding:2px 9px; border-radius:999px; font:700 .72rem/1.6 'Source Sans 3',sans-serif; text-transform:uppercase; letter-spacing:.04em; }
.pill-pending { background:#fff3cd; color:#7a5b00; }
.pill-processing { background:#dbe9ff; color:#1b3d8f; }
.pill-completed { background:#d6f1de; color:#17633e; }
.pill-cancelled { background:#fde2e2; color:#a31621; }
.pill-synced { background:#d6f1de; color:#17633e; }
.pill-failed { background:#fde2e2; color:#a31621; }
</style>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
