<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('categories.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$repo = new CategoryRepository(appDb());
$storefront = new StorefrontRepository(appDb());

$name = trim((string)($_POST['name'] ?? ''));
if ($name === '') {
    Flash::error('Name is required.');
    header('Location: ' . adminUrl('category-edit.php' . ($id ? '?id=' . $id : '')));
    exit;
}

$slugInput = trim((string)($_POST['slug'] ?? ''));
$slug = $storefront->buildSlug($slugInput, $name);

// Ensure unique by checking existing categories (slug is UNIQUE in schema).
$base = $slug; $i = 2;
while (true) {
    $stmt = appDb()->prepare('SELECT id FROM categories WHERE slug = :s AND id <> :id LIMIT 1');
    $stmt->execute([':s' => $slug, ':id' => $id]);
    if (!$stmt->fetch()) break;
    $slug = $base . '-' . $i++;
    if ($i > 200) break;
}

$payload = [
    'slug' => $slug,
    'name' => $name,
    'parent_id' => ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null,
    'image_url' => trim((string)($_POST['image_url'] ?? '')) ?: null,
    'description' => trim((string)($_POST['description'] ?? '')) ?: null,
    'sort_order' => (int)($_POST['sort_order'] ?? 0),
    'is_visible' => !empty($_POST['is_visible']) ? 1 : 0,
    'seo_title' => trim((string)($_POST['seo_title'] ?? '')) ?: null,
    'seo_description' => trim((string)($_POST['seo_description'] ?? '')) ?: null,
];

if ($id > 0) {
    $repo->update($id, $payload);
    audit('category_update', 'category', (string)$id);
    Flash::success('Category updated.');
} else {
    $id = $repo->create($payload);
    audit('category_create', 'category', (string)$id);
    Flash::success('Category created.');
}

header('Location: ' . adminUrl('categories.php'));
exit;
