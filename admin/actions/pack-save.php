<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('packs.php'));
    exit;
}

$repo = new StorefrontRepository(appDb());
$id = (int)($_POST['id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
if ($title === '') {
    Flash::error('Title is required.');
    header('Location: ' . adminUrl('pack-edit.php' . ($id ? '?id=' . $id : '')));
    exit;
}
$slug = $repo->buildSlug((string)($_POST['slug'] ?? ''), $title);
$slug = $repo->ensureUniqueSlug($slug, $id ?: null);

$payload = [
    'kind' => 'pack',
    'slug' => $slug,
    'title' => $title,
    'subtitle' => trim((string)($_POST['subtitle'] ?? '')) ?: null,
    'description' => trim((string)($_POST['description'] ?? '')) ?: null,
    'hero_image_url' => trim((string)($_POST['hero_image_url'] ?? '')) ?: null,
    'badge' => trim((string)($_POST['badge'] ?? '')) ?: null,
    'base_price' => $_POST['base_price'] ?? null,
    'compare_at_price' => $_POST['compare_at_price'] ?? null,
    'is_visible' => !empty($_POST['is_visible']) ? 1 : 0,
    'created_by' => AdminAuth::userId(),
];

if ($id > 0) {
    $repo->update($id, $payload);
} else {
    $id = $repo->create($payload);
}

$rows = (array)($_POST['items'] ?? []);
$children = [];
foreach ($rows as $v) {
    $cid = (int)($v['child_product_id'] ?? 0);
    $qty = (float)($v['quantity'] ?? 1);
    if ($cid <= 0 || $qty <= 0) continue;
    $children[] = [
        'child_product_id' => $cid,
        'quantity' => $qty,
        'is_default' => 0,
    ];
}
if (!$children) {
    Flash::error('Add at least one item to the pack.');
    header('Location: ' . adminUrl('pack-edit.php?id=' . $id));
    exit;
}
$repo->replaceChildren($id, 'pack_item', $children);

$catIds = array_values(array_filter(array_map('intval', (array)($_POST['categories'] ?? []))));
$repo->replaceCategories($id, $catIds);

audit('pack_save', 'storefront_product', (string)$id, ['items' => count($children)]);
Flash::success('Pack saved.');

header('Location: ' . adminUrl('pack-edit.php?id=' . $id));
exit;
