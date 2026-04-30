<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new CouponRepository(appDb());
$coupons = $repo->listAll();

$pageTitle = 'Coupons';
$activeNav = 'coupons';
$pageActions = '<a class="btn primary" href="' . h(adminUrl('coupon-edit.php')) . '">+ New coupon</a>';
require __DIR__ . '/_layout_top.php';
?>
<div class="card" style="padding:0">
    <table>
        <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Used</th><th>Redeemed</th><th>Discount given</th><th>Window</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$coupons): ?>
            <tr><td colspan="9" style="padding:30px; text-align:center; color:#6b7388">No coupons yet.</td></tr>
        <?php else: foreach ($coupons as $c): ?>
            <tr>
                <td><strong style="font-family:monospace; letter-spacing:.05em"><?= h($c['code']) ?></strong>
                    <?php if ($c['description']): ?><div class="muted" style="font-size:.78rem"><?= h($c['description']) ?></div><?php endif; ?></td>
                <td><?= h($c['type']) ?></td>
                <td>
                    <?php if ($c['type'] === 'percent'): ?><?= rtrim(rtrim(number_format((float)$c['value'], 2, '.', ''), '0'), '.') ?>%
                    <?php elseif ($c['type'] === 'fixed'): ?>Rs <?= number_format((float)$c['value'], 2) ?>
                    <?php else: ?>Free shipping<?php endif; ?>
                </td>
                <td><?= (int)$c['used_count'] ?><?= $c['usage_limit'] !== null ? ' / ' . (int)$c['usage_limit'] : '' ?></td>
                <td><strong><?= (int)($c['redemption_count'] ?? 0) ?></strong></td>
                <td>LKR <?= number_format((float)($c['redemption_total'] ?? 0), 2) ?></td>
                <td style="font-size:.85rem">
                    <?= $c['starts_at'] ? h(date('M j', strtotime((string)$c['starts_at']))) : '<span class="muted">always</span>' ?> →
                    <?= $c['ends_at'] ? h(date('M j', strtotime((string)$c['ends_at']))) : '<span class="muted">no end</span>' ?>
                </td>
                <td><?= $c['is_active'] ? '<span class="pill green">Active</span>' : '<span class="pill gray">Off</span>' ?></td>
                <td>
                    <a class="btn sm" href="<?= h(adminUrl('coupon-edit.php?id=' . (int)$c['id'])) ?>">Edit</a>
                    <form method="post" action="<?= h(adminUrl('actions/coupon-delete.php')) ?>" style="display:inline" onsubmit="return confirm('Delete this coupon?')">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <button class="btn sm danger" type="submit">×</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/_layout_bottom.php';
