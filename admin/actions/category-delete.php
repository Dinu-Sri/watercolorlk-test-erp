<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('categories.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    (new CategoryRepository(appDb()))->delete($id);
    audit('category_delete', 'category', (string)$id);
    Flash::success('Category deleted.');
}

header('Location: ' . adminUrl('categories.php'));
exit;
