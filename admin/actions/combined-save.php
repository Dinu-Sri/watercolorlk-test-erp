<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('combined.php'));
    exit;
}

$repo = new StorefrontRepository(appDb());
$id = (int)($_POST['id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
if ($title === '') {
    Flash::error('Title is required.');
    header('Location: ' . adminUrl('combined-edit.php' . ($id ? '?id=' . $id : '')));
    exit;
}
$slug = $repo->buildSlug((string)($_POST['slug'] ?? ''), $title);
$slug = $repo->ensureUniqueSlug($slug, $id ?: null);

$payload = [
    'kind' => 'combined',
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

// Variants
$rows = (array)($_POST['variants'] ?? []);
$defaultIndex = (string)($_POST['default_variant'] ?? '');
$children = [];
foreach ($rows as $i => $v) {
    $cid = (int)($v['child_product_id'] ?? 0);
    if ($cid <= 0) continue;
    $children[] = [
        'child_product_id' => $cid,
        'variant_label' => trim((string)($v['variant_label'] ?? '')) ?: null,
        'variant_swatch_hex' => trim((string)($v['variant_swatch_hex'] ?? '')) ?: null,
        'price_override' => isset($v['price_override']) && $v['price_override'] !== '' ? (float)$v['price_override'] : null,
        'is_default' => ((string)$i === $defaultIndex) ? 1 : 0,
    ];
}
if (!$children) {
    Flash::error('Add at least one variant.');
    header('Location: ' . adminUrl('combined-edit.php?id=' . $id));
    exit;
}
$repo->replaceChildren($id, 'variant', $children);

$catIds = array_values(array_filter(array_map('intval', (array)($_POST['categories'] ?? []))));
$repo->replaceCategories($id, $catIds);

audit('combined_save', 'storefront_product', (string)$id, ['variants' => count($children)]);
Flash::success('Combined product saved.');

header('Location: ' . adminUrl('combined-edit.php?id=' . $id));
exit;
