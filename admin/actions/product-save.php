<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('products.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$repo = new StorefrontRepository(appDb());
$sp = $repo->getById($id);
if (!$sp) {
    Flash::error('Product not found.');
    header('Location: ' . adminUrl('products.php'));
    exit;
}

// Slug
$slugInput = trim((string)($_POST['slug'] ?? ''));
$slug = $repo->buildSlug($slugInput, (string)($_POST['title'] ?? $sp['title']), (int)$sp['erp_product_id']);
$slug = $repo->ensureUniqueSlug($slug, $id);

// Gallery: split lines into JSON array
$galleryRaw = (string)($_POST['gallery'] ?? '');
$gallery = array_values(array_filter(array_map('trim', preg_split('/\R/', $galleryRaw) ?: [])));
$galleryJson = $gallery ? json_encode($gallery, JSON_UNESCAPED_SLASHES) : null;

$repo->update($id, [
    'slug'             => $slug,
    'title'            => trim((string)($_POST['title'] ?? '')),
    'subtitle'         => trim((string)($_POST['subtitle'] ?? '')) ?: null,
    'description'      => trim((string)($_POST['description'] ?? '')) ?: null,
    'hero_image_url'   => trim((string)($_POST['hero_image_url'] ?? '')) ?: null,
    'gallery_json'     => $galleryJson,
    'badge'            => trim((string)($_POST['badge'] ?? '')) ?: null,
    'base_price'       => $_POST['base_price'] ?? null,
    'compare_at_price' => $_POST['compare_at_price'] ?? null,
    'is_visible'       => !empty($_POST['is_visible']) ? 1 : 0,
    'sort_order'       => (int)($_POST['sort_order'] ?? 0),
    'seo_title'        => trim((string)($_POST['seo_title'] ?? '')) ?: null,
    'seo_description'  => trim((string)($_POST['seo_description'] ?? '')) ?: null,
]);

$catIds = array_values(array_filter(array_map('intval', (array)($_POST['categories'] ?? []))));
$repo->replaceCategories($id, $catIds);

audit('product_save', 'storefront_product', (string)$id, ['slug' => $slug]);
Flash::success('Product saved.');

header('Location: ' . adminUrl('product-edit.php?id=' . $id));
exit;
