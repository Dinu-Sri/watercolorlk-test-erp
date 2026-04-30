<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$auth = appUserAuth();
$user = $auth->require('login.php');
$repo = new UserRepository(appDb());

$msg = null; $err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::check($_POST['_csrf'] ?? '')) {
        $err = 'Session expired. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'profile') {
                $repo->update((int)$user['id'], [
                    'full_name' => trim((string)($_POST['full_name'] ?? '')) ?: null,
                    'phone' => trim((string)($_POST['phone'] ?? '')) ?: null,
                ]);
                $msg = 'Profile updated.';
            } elseif ($action === 'password') {
                $current = (string)($_POST['current_password'] ?? '');
                $new1 = (string)($_POST['new_password'] ?? '');
                $new2 = (string)($_POST['new_password2'] ?? '');
                if (strlen($new1) < 8) throw new RuntimeException('New password must be at least 8 characters.');
                if ($new1 !== $new2) throw new RuntimeException('Passwords do not match.');
                if (!empty($user['password_hash']) && !password_verify($current, (string)$user['password_hash'])) {
                    throw new RuntimeException('Current password is incorrect.');
                }
                $repo->update((int)$user['id'], ['password_hash' => password_hash($new1, PASSWORD_BCRYPT)]);
                $msg = 'Password updated.';
            } elseif ($action === 'address_save') {
                $aId = (int)($_POST['address_id'] ?? 0) ?: null;
                $repo->saveAddress((int)$user['id'], [
                    'full_name' => trim((string)($_POST['a_full_name'] ?? '')),
                    'phone' => trim((string)($_POST['a_phone'] ?? '')),
                    'address_line' => trim((string)($_POST['a_address_line'] ?? '')),
                    'city' => trim((string)($_POST['a_city'] ?? '')),
                    'is_default' => !empty($_POST['a_is_default']),
                ], $aId);
                $msg = 'Address saved.';
            } elseif ($action === 'address_delete') {
                $repo->deleteAddress((int)$user['id'], (int)($_POST['address_id'] ?? 0));
                $msg = 'Address removed.';
            }
            $user = $repo->findById((int)$user['id']);
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}

$addresses = $repo->listAddresses((int)$user['id']);

$pageTitle = 'Profile · Watercolor.LK';
require __DIR__ . '/_chrome.php';
?>
<div class="acc-grid">
    <aside class="acc-side">
        <a href="index.php">Overview</a>
        <a href="orders.php">My orders</a>
        <a class="active" href="profile.php">Profile &amp; addresses</a>
        <a href="logout.php" style="color:#b8232f;">Log out</a>
    </aside>
    <div style="display:grid;gap:18px;">
        <?php if ($msg): ?><div class="acc-msg ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="acc-msg err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <div class="acc-card">
            <h1>Profile</h1>
            <form class="acc-form" method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="action" value="profile">
                <div><label for="email">Email</label>
                    <input id="email" type="email" value="<?= htmlspecialchars((string)$user['email']) ?>" disabled>
                </div>
                <div><label for="full_name">Full name</label>
                    <input id="full_name" name="full_name" type="text" value="<?= htmlspecialchars((string)($user['full_name'] ?? '')) ?>">
                </div>
                <div><label for="phone">Phone</label>
                    <input id="phone" name="phone" type="tel" value="<?= htmlspecialchars((string)($user['phone'] ?? '')) ?>">
                </div>
                <button class="btn-primary" type="submit">Save profile</button>
            </form>
        </div>

        <div class="acc-card">
            <h1>Password</h1>
            <?php if (empty($user['password_hash'])): ?>
                <p class="lead">You signed in with Google. Set a password if you'd like to log in with email/password too.</p>
            <?php endif; ?>
            <form class="acc-form" method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="action" value="password">
                <?php if (!empty($user['password_hash'])): ?>
                <div><label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                </div>
                <?php endif; ?>
                <div><label for="new_password">New password</label>
                    <input id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
                </div>
                <div><label for="new_password2">Confirm new password</label>
                    <input id="new_password2" name="new_password2" type="password" minlength="8" autocomplete="new-password" required>
                </div>
                <button class="btn-primary" type="submit">Update password</button>
            </form>
        </div>

        <div class="acc-card">
            <h1>Addresses</h1>
            <?php if (!$addresses): ?>
                <p class="acc-meta">No saved addresses yet.</p>
            <?php else: ?>
                <table class="acc-table">
                    <thead><tr><th>Name</th><th>Phone</th><th>Address</th><th>City</th><th>Default</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($addresses as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$a['full_name']) ?></td>
                            <td><?= htmlspecialchars((string)$a['phone']) ?></td>
                            <td><?= htmlspecialchars((string)$a['address_line']) ?></td>
                            <td><?= htmlspecialchars((string)$a['city']) ?></td>
                            <td><?= $a['is_default'] ? '✓' : '' ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this address?');">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="action" value="address_delete">
                                    <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" style="background:none;border:0;color:#b8232f;cursor:pointer;font-weight:700;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <h2 style="font:800 1rem/1 'Montserrat',sans-serif;color:#0f2440;margin:18px 0 8px;">Add a new address</h2>
            <form class="acc-form" method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="action" value="address_save">
                <div><label>Full name</label><input name="a_full_name" type="text"></div>
                <div><label>Phone</label><input name="a_phone" type="tel"></div>
                <div><label>Address</label><input name="a_address_line" type="text"></div>
                <div><label>City</label><input name="a_city" type="text"></div>
                <label style="display:flex;align-items:center;gap:8px;text-transform:none;font:600 .92rem/1 'Source Sans 3',sans-serif;">
                    <input type="checkbox" name="a_is_default" value="1"> Make this my default address
                </label>
                <button class="btn-primary" type="submit">Save address</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_chrome_end.php'; ?>
