<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('flash-deals.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    (new FlashDealRepository(appDb()))->delete($id);
    audit('flash_delete', 'flash_deal', (string)$id);
    Flash::success('Flash deal deleted.');
}

header('Location: ' . adminUrl('flash-deals.php'));
exit;
