<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$repo = new FlashDealRepository(appDb());
$deals = $repo->listAdmin();

$pageTitle = 'Flash Deals';
$activeNav = 'flash';
$pageActions = '<a class="btn primary" href="' . h(adminUrl('flash-deal-edit.php')) . '">+ New flash deal</a>';
require __DIR__ . '/_layout_top.php';
?>
<div class="card" style="padding:0">
    <table>
        <thead><tr><th>Product</th><th>Deal price</th><th>Original</th><th>Window</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$deals): ?>
            <tr><td colspan="6" style="padding:30px; text-align:center; color:#6b7388">No flash deals scheduled yet.</td></tr>
        <?php else: foreach ($deals as $d): 
            $now = time();
            $sa = $d['starts_at'] ? strtotime((string)$d['starts_at']) : null;
            $ea = $d['ends_at'] ? strtotime((string)$d['ends_at']) : null;
            $live = $d['is_active'] && (!$sa || $sa <= $now) && (!$ea || $ea >= $now);
        ?>
            <tr>
                <td><strong><?= h($d['product_title']) ?></strong>
                    <div class="muted" style="font-size:.78rem">/<?= h($d['product_slug']) ?> · <?= h($d['kind']) ?></div></td>
                <td>Rs <?= number_format((float)$d['deal_price'], 2) ?></td>
                <td><?= $d['original_price'] !== null ? 'Rs ' . number_format((float)$d['original_price'], 2) : '<span class="muted">—</span>' ?></td>
                <td style="font-size:.85rem">
                    <?= $d['starts_at'] ? h(date('M j, H:i', (int)$sa)) : '<span class="muted">always</span>' ?>
                    →
                    <?= $d['ends_at'] ? h(date('M j, H:i', (int)$ea)) : '<span class="muted">no end</span>' ?>
                </td>
                <td>
                    <?php if ($live): ?><span class="pill green">Live</span>
                    <?php elseif (!$d['is_active']): ?><span class="pill gray">Off</span>
                    <?php elseif ($sa && $sa > $now): ?><span class="pill blue">Upcoming</span>
                    <?php else: ?><span class="pill gray">Ended</span><?php endif; ?>
                </td>
                <td>
                    <a class="btn sm" href="<?= h(adminUrl('flash-deal-edit.php?id=' . (int)$d['id'])) ?>">Edit</a>
                    <form method="post" action="<?= h(adminUrl('actions/flash-deal-delete.php')) ?>" style="display:inline" onsubmit="return confirm('Delete this flash deal?')">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                        <button class="btn sm danger" type="submit">×</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/_layout_bottom.php';
