<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('users.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? '');
if ($id <= 0 || !in_array($status, ['active', 'disabled'], true)) {
    Flash::error('Invalid request.');
    header('Location: ' . adminUrl('users.php'));
    exit;
}

try {
    (new UserRepository(appDb()))->setStatus($id, $status);
    audit('user_status', 'user', (string)$id, ['status' => $status]);
    Flash::success('User #' . $id . ' set to ' . $status . '.');
} catch (Throwable $e) {
    Flash::error('Update failed: ' . $e->getMessage());
}

header('Location: ' . adminUrl('users.php'));
exit;
