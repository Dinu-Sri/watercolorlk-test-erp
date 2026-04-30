<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new UserRepository(appDb());

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'status' => (string)($_GET['status'] ?? ''),
    'page' => $page,
    'per_page' => $perPage,
];

$migrationMissing = false;
$rows = [];
$total = 0;
try {
    $res = $repo->adminList($filters);
    $rows = $res['rows'];
    $total = (int)$res['total'];
} catch (Throwable $e) {
    if (stripos($e->getMessage(), "doesn't exist") !== false || stripos($e->getMessage(), 'no such table') !== false) {
        $migrationMissing = true;
    } else {
        throw $e;
    }
}
$pagination = new Pagination($page, $perPage, $total);

$pageTitle = 'Users';
$activeNav = 'users';
require __DIR__ . '/_layout_top.php';
?>
<?php if ($migrationMissing): ?>
<div class="card" style="background:#fff4d6;border:1px solid #f0d684;">
    <strong style="color:#8a6a00;">Customer accounts migration not yet applied.</strong>
    <p style="margin:6px 0 0;">Run <code>db/migrations/2026_06_users.sql</code> on the database (phpMyAdmin → SQL tab) to enable the customer accounts module.</p>
</div>
<?php endif; ?>
<style>
.pill { display:inline-block; padding:2px 9px; border-radius:999px; font:700 .72rem/1.6 'Source Sans 3',sans-serif; text-transform:uppercase; letter-spacing:.04em; }
.pill-active { background:#d6f1de; color:#17633e; }
.pill-disabled { background:#fde2e2; color:#a31621; }
</style>
<div class="card">
    <form class="toolbar" method="get">
        <input type="text" name="q" value="<?= h($filters['q']) ?>" placeholder="Search email, name or phone…" style="min-width:280px">
        <select name="status">
            <option value="">Any status</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="disabled" <?= $filters['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <button type="submit" class="btn dark">Filter</button>
        <a class="btn" href="<?= h(adminUrl('users.php')) ?>">Reset</a>
        <span class="muted" style="margin-left:auto"><?= $total ?> result<?= $total === 1 ? '' : 's' ?></span>
    </form>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="muted" style="padding:18px;">No users yet.</div>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>#</th><th>Email</th><th>Name</th><th>Phone</th><th>Verified</th><th>Auth</th><th>Orders</th><th>Lifetime</th><th>Last login</th><th>Status</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $u):
            $hasGoogle = !empty($u['google_sub']);
            $hasPwd = !empty($u['password_hash']);
            $authBits = [];
            if ($hasPwd) $authBits[] = 'Email';
            if ($hasGoogle) $authBits[] = 'Google';
        ?>
            <tr>
                <td>#<?= (int)$u['id'] ?></td>
                <td><?= h((string)$u['email']) ?></td>
                <td><?= h((string)($u['full_name'] ?? '')) ?></td>
                <td><?= h((string)($u['phone'] ?? '—')) ?></td>
                <td><?= !empty($u['email_verified_at']) ? '<span style="color:#17633e;">✓</span>' : '<span style="color:#b8232f;">—</span>' ?></td>
                <td><?= h(implode(' + ', $authBits) ?: '—') ?></td>
                <td><a href="<?= h(adminUrl('orders.php?q=' . (string)$u['email'])) ?>"><?= (int)$u['order_count'] ?></a></td>
                <td>LKR <?= number_format((float)$u['lifetime_value'], 2) ?></td>
                <td><?= !empty($u['last_login_at']) ? h(date('M j, Y', strtotime((string)$u['last_login_at']))) : '—' ?></td>
                <td><span class="pill pill-<?= h((string)$u['status']) ?>"><?= h((string)$u['status']) ?></span></td>
                <td>
                    <form method="post" action="<?= h(adminUrl('actions/user-toggle.php')) ?>" style="display:inline;">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'disabled' : 'active' ?>">
                        <button class="btn small <?= $u['status'] === 'active' ? '' : 'primary' ?>" type="submit"
                                onclick="return confirm('<?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?> this account?');">
                            <?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($pagination->totalPages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $pagination->totalPages; $p++): ?>
            <?php if ($p === $pagination->page): ?><span class="active"><?= $p ?></span>
            <?php else: ?><a href="<?= h($pagination->url(adminUrl('users.php'), ['q' => $filters['q'], 'status' => $filters['status']], $p)) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_layout_bottom.php'; ?>
