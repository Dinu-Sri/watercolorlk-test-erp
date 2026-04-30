<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$method = isset($_GET['method']) ? (string)$_GET['method'] : 'payhere';
$methodLabel = [
    'payhere' => 'PayHere (card / wallet)',
    'cod' => 'Cash on Delivery',
    'bank' => 'Bank Transfer',
][$method] ?? 'PayHere';

$order = null;
if ($orderId > 0) {
    try {
        $order = (new OrderRepository(appDb()))->getOrderWithItems($orderId);
    } catch (Throwable $e) {
        $order = null;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Order confirmed - Watercolor.LK</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" type="image/png" href="assets/images/brand/logo-watercolorlk.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800;900&family=Playfair+Display:wght@600;700;800&family=Source+Sans+3:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php include __DIR__ . '/partials/chrome-styles.php'; ?>
<style>
.ok-wrap { padding: 32px 0 60px; }
.ok-card {
    background: #fff; border: 1px solid var(--line); border-radius: 20px;
    box-shadow: var(--shadow-sm); padding: 32px 28px; max-width: 720px; margin: 0 auto;
    text-align: center;
}
.ok-check {
    width: 72px; height: 72px; border-radius: 50%; margin: 0 auto 14px;
    background: linear-gradient(180deg, #2ed877, #17633e);
    display: flex; align-items: center; justify-content: center; color: #fff;
    box-shadow: 0 14px 28px rgba(46,156,93,.3);
}
.ok-card h1 { margin: 0 0 6px; color: var(--brand-navy-deep); font: 800 1.7rem/1.2 'Playfair Display', serif; }
.ok-card .num { color: var(--amber-deep); font: 800 1.05rem/1.2 'Source Sans 3', sans-serif; }
.ok-card p.lead { color: #4a5468; font: 600 .96rem/1.5 'Source Sans 3', sans-serif; max-width: 520px; margin: 6px auto 18px; }

.ok-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 14px auto; max-width: 480px; text-align: left; }
.ok-meta .row { background: #faf6f0; border: 1px solid var(--line); border-radius: 10px; padding: 8px 12px; }
.ok-meta .lbl { color: #6b7388; font: 700 .7rem/1 'Montserrat', sans-serif; letter-spacing: .04em; text-transform: uppercase; }
.ok-meta .val { color: var(--brand-navy-deep); font: 700 .94rem/1.3 'Source Sans 3', sans-serif; margin-top: 2px; }

.steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 22px 0; text-align: left; }
.steps .step {
    background: #faf6f0; border: 1px solid var(--line); border-radius: 14px; padding: 14px;
}
.steps .step .n {
    width: 24px; height: 24px; border-radius: 50%; background: var(--amber); color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font: 800 .76rem/1 'Montserrat', sans-serif; margin-bottom: 6px;
}
.steps .step h3 { margin: 0 0 4px; color: var(--brand-navy-deep); font: 800 .92rem/1.2 'Montserrat', sans-serif; }
.steps .step p { margin: 0; color: #4a5468; font: 500 .82rem/1.4 'Source Sans 3', sans-serif; }

.ok-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 16px; }
.ok-actions a {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 20px; border-radius: 999px; text-decoration: none;
    font: 800 .88rem/1 'Montserrat', sans-serif; letter-spacing: .03em;
}
.ok-actions a.primary { background: linear-gradient(180deg, #ff5b3a, #b8232f); color: #fff; box-shadow: 0 8px 20px rgba(184,35,47,.3); }
.ok-actions a.wa { background: #25d366; color: #fff; box-shadow: 0 8px 20px rgba(37,211,102,.28); }
.ok-actions a.ghost { background: #fff; color: var(--brand-navy); border: 1px solid var(--line); }

@media (max-width: 720px) {
    .steps { grid-template-columns: 1fr; }
    .ok-meta { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php
$showPromoBar = false;
$headerSearchValue = '';
$cartCount = 0;
include __DIR__ . '/partials/site-header.php';
?>

<main class="wrap ok-wrap">
    <div class="ok-card">
        <div class="ok-check">
            <svg viewBox="0 0 24 24" width="38" height="38" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 12 10 18 20 6"/></svg>
        </div>
        <h1>Thank you<?= $order ? ', ' . htmlspecialchars((string)$order['customer_name']) : '' ?>!</h1>
        <?php if ($orderId > 0): ?>
            <p class="lead">Your order <span class="num">#<?= $orderId ?></span> has been received. We'll confirm dispatch on WhatsApp shortly.</p>
        <?php else: ?>
            <p class="lead">Your order has been received. We'll confirm dispatch on WhatsApp shortly.</p>
        <?php endif; ?>

        <?php if ($order): ?>
            <div class="ok-meta">
                <div class="row"><div class="lbl">Order</div><div class="val">#<?= $orderId ?></div></div>
                <div class="row"><div class="lbl">Payment</div><div class="val"><?= htmlspecialchars($methodLabel) ?></div></div>
                <div class="row"><div class="lbl">Phone</div><div class="val"><?= htmlspecialchars((string)$order['customer_phone']) ?></div></div>
                <div class="row"><div class="lbl">Status</div><div class="val">Pending confirmation</div></div>
            </div>

            <div class="ok-totals" style="max-width:480px;margin:14px auto 0;background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 18px;text-align:left;">
                <table style="width:100%;border-collapse:collapse;font:600 .92rem/1.6 'Source Sans 3',sans-serif;color:#0f2440;">
                    <tr><td>Subtotal</td><td style="text-align:right;">LKR <?= number_format((float)$order['subtotal_amount'], 2) ?></td></tr>
                    <?php if ((float)$order['discount_amount'] > 0): ?>
                        <tr><td>Discount<?= !empty($order['coupon_code']) ? ' (' . htmlspecialchars((string)$order['coupon_code']) . ')' : '' ?></td>
                            <td style="text-align:right;color:#17633e;">− LKR <?= number_format((float)$order['discount_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Shipping</td><td style="text-align:right;"><?= (float)$order['shipping_amount'] === 0.0 ? 'FREE' : 'LKR ' . number_format((float)$order['shipping_amount'], 2) ?></td></tr>
                    <tr><td style="border-top:1px solid var(--line);padding-top:8px;font-weight:800;">Total</td>
                        <td style="border-top:1px solid var(--line);padding-top:8px;text-align:right;font-weight:800;">LKR <?= number_format((float)$order['total_amount'], 2) ?></td></tr>
                </table>
            </div>
        <?php endif; ?>

        <div class="steps">
            <div class="step">
                <div class="n">1</div>
                <h3>We confirm on WhatsApp</h3>
                <p>Our team will message you within an hour to confirm address and total.</p>
            </div>
            <div class="step">
                <div class="n">2</div>
                <h3>Dispatch within 24h</h3>
                <p>Carefully packed and handed to the courier the next working day.</p>
            </div>
            <div class="step">
                <div class="n">3</div>
                <h3>Tracked delivery 1-3 days</h3>
                <p>Island-wide via tracked courier. We'll share the tracking link.</p>
            </div>
        </div>

        <div class="ok-actions">
            <a class="wa" href="https://wa.me/94700000000?text=<?= rawurlencode('Hi Watercolor.LK, I just placed order #' . ($orderId ?: '')) ?>" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 3.5A10 10 0 0 0 4 16l-1 5 5-1A10 10 0 1 0 20 3.5z"/></svg>
                Confirm on WhatsApp
            </a>
            <a class="primary" href="index.php">Continue shopping</a>
            <?php if (appUserAuth()->currentUserId() && $orderId > 0): ?>
                <a class="ghost" href="account/order.php?id=<?= $orderId ?>">View in my account</a>
            <?php else: ?>
                <a class="ghost" href="cart.php">View cart</a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/partials/site-footer.php'; ?>
<?php include __DIR__ . '/partials/site-scripts.php'; ?>
<script>
// Defensive cart clear in case user lands here directly with order_id from a refresh
try { if (window.WLKCart) { window.WLKCart.clear(); } } catch (_) {}
</script>
</body>
</html>
