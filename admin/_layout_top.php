<?php
/**
 * Shared admin layout. Usage:
 *   $pageTitle = 'Products';
 *   $activeNav = 'products';
 *   require __DIR__ . '/_layout_top.php';
 *   ... page body ...
 *   require __DIR__ . '/_layout_bottom.php';
 */
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';
$flash = Flash::pull();
$adminUser = $adminUser ?? AdminAuth::user();
$nav = [
    'dashboard' => ['Dashboard', 'index.php'],
    'products' => ['Products', 'products.php'],
    'combined' => ['Combined Products', 'combined.php'],
    'packs' => ['Packs', 'packs.php'],
    'categories' => ['Categories', 'categories.php'],
    'flash' => ['Flash Deals', 'flash-deals.php'],
    'coupons' => ['Coupons', 'coupons.php'],
    'orders' => ['Orders', 'orders.php'],
    'users' => ['Users', 'users.php'],
    'reviews' => ['Google Reviews', 'reviews.php'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> · Watercolor.LK Admin</title>
<link rel="icon" type="image/png" href="/assets/images/brand/logo-watercolorlk.png">
<style>
:root { --navy:#16253a; --navy-deep:#0e1b30; --amber:#e8760a; --line:#e5e8ee; --bg:#f5f5f7; --ink:#1a2336; --muted:#6b7388; --ok:#22a06b; --err:#b8232f; }
* { box-sizing: border-box; }
body { margin: 0; font: 14px/1.45 "Source Sans 3", "Segoe UI", Tahoma, sans-serif; background: var(--bg); color: var(--ink); }
a { color: var(--amber); text-decoration: none; }
a:hover { text-decoration: underline; }
.layout { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
.side { background: var(--navy-deep); color: #fff; padding: 18px 0; position: sticky; top: 0; align-self: start; height: 100vh; overflow-y: auto; }
.side .brand { padding: 0 18px 14px; border-bottom: 1px solid rgba(255,255,255,.08); }
.side .brand b { font: 800 1.05rem/1 "Montserrat", sans-serif; letter-spacing: .02em; }
.side .brand small { color: #a8b1c2; font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; }
.side nav { padding: 10px 0; }
.side nav a { display: block; color: #cfd6e4; padding: 10px 18px; font: 600 .9rem/1 "Source Sans 3", sans-serif; border-left: 3px solid transparent; }
.side nav a:hover { background: rgba(255,255,255,.04); color: #fff; text-decoration: none; }
.side nav a.is-active { background: rgba(232,118,10,.12); color: #fff; border-left-color: var(--amber); }
.side .me { padding: 14px 18px; border-top: 1px solid rgba(255,255,255,.08); margin-top: 10px; font-size: .82rem; color: #a8b1c2; }
.side .me .nm { color: #fff; font-weight: 700; }
.side .me a { color: var(--amber); font-weight: 700; }
.main { padding: 22px 28px 60px; }
.topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.topbar h1 { margin: 0; font: 800 1.45rem/1.1 "Playfair Display", "Georgia", serif; color: var(--navy-deep); }
.topbar .actions { display: flex; gap: 8px; }
.flash { padding: 11px 14px; border-radius: 10px; margin-bottom: 14px; font-weight: 600; }
.flash.success { background: #e8f7ee; color: #1b6b46; border: 1px solid #b8e4c9; }
.flash.error { background: #fdecec; color: #8a1620; border: 1px solid #f5b9bd; }
.flash.info { background: #ecf3fd; color: #1a4480; border: 1px solid #bbd2f5; }
.card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 0 rgba(16,32,57,.04); margin-bottom: 16px; }
.card h2 { margin: 0 0 12px; font: 800 1.05rem/1.1 "Montserrat", sans-serif; color: var(--navy-deep); }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 9px 10px; border-bottom: 1px solid var(--line); text-align: left; font-size: .9rem; vertical-align: middle; }
th { background: #fafbfc; color: #4a5468; font: 700 .76rem/1 "Montserrat", sans-serif; letter-spacing: .04em; text-transform: uppercase; }
tr:hover td { background: #fafbfc; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border: 1px solid var(--line); background: #fff; color: var(--ink); border-radius: 8px; font: 700 .82rem/1 "Montserrat", sans-serif; cursor: pointer; text-decoration: none; }
.btn:hover { border-color: #c8cdd6; text-decoration: none; }
.btn.primary { background: var(--amber); color: #fff; border-color: var(--amber); }
.btn.primary:hover { background: #cf6707; }
.btn.dark { background: var(--navy); color: #fff; border-color: var(--navy); }
.btn.danger { color: var(--err); }
.btn.sm { padding: 5px 10px; font-size: .76rem; }
.field { display: block; margin-bottom: 12px; }
.field label { display: block; margin-bottom: 4px; font: 700 .76rem/1 "Montserrat", sans-serif; color: #4a5468; letter-spacing: .04em; text-transform: uppercase; }
.field input, .field select, .field textarea { width: 100%; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; font: 600 .92rem/1.3 "Source Sans 3", sans-serif; color: var(--ink); background: #fff; }
.field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--amber); box-shadow: 0 0 0 3px rgba(232,118,10,.15); }
.field textarea { min-height: 80px; resize: vertical; }
.row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.row.three { grid-template-columns: repeat(3, 1fr); }
.row.four { grid-template-columns: repeat(4, 1fr); }
.pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font: 700 .68rem/1.6 "Montserrat", sans-serif; letter-spacing: .04em; text-transform: uppercase; }
.pill.green { background: #e8f7ee; color: #1b6b46; }
.pill.gray { background: #eef0f4; color: #6b7388; }
.pill.amber { background: #fff3e0; color: #b85708; }
.pill.blue { background: #e6efff; color: #1a4480; }
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
.kpi { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; }
.kpi .lbl { color: var(--muted); font: 700 .72rem/1 "Montserrat", sans-serif; letter-spacing: .06em; text-transform: uppercase; }
.kpi .val { font: 800 1.6rem/1.1 "Playfair Display", serif; color: var(--navy-deep); margin-top: 6px; }
.toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
.toolbar form { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.toolbar input, .toolbar select { padding: 7px 10px; border: 1px solid var(--line); border-radius: 8px; font: 600 .85rem/1.2 "Source Sans 3", sans-serif; }
.pagination { display: flex; gap: 4px; margin-top: 12px; }
.pagination a, .pagination span { padding: 6px 10px; border: 1px solid var(--line); border-radius: 6px; font: 700 .78rem/1 "Montserrat", sans-serif; color: var(--ink); }
.pagination a:hover { border-color: var(--amber); text-decoration: none; }
.pagination .cur { background: var(--navy); color: #fff; border-color: var(--navy); }
img.thumb { width: 38px; height: 38px; object-fit: cover; border-radius: 6px; background: #f0f1f4; }
.checkbox-row { display: flex; align-items: center; gap: 8px; }
.muted { color: var(--muted); }
.swatch { display: inline-block; width: 18px; height: 18px; border-radius: 50%; border: 1px solid rgba(0,0,0,.1); vertical-align: middle; }
@media (max-width: 900px) { .layout { grid-template-columns: 1fr; } .side { position: static; height: auto; } }
</style>
</head>
<body>
<div class="layout">
    <aside class="side">
        <div class="brand">
            <small>Watercolor.LK</small><br>
            <b>Admin</b>
        </div>
        <nav>
            <?php foreach ($nav as $key => [$label, $href]): ?>
                <a href="<?= h(adminUrl($href)) ?>" class="<?= $activeNav === $key ? 'is-active' : '' ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($adminUser): ?>
        <div class="me">
            Signed in as <span class="nm"><?= h($adminUser['display_name'] ?? $adminUser['username']) ?></span><br>
            <a href="<?= h(adminUrl('logout.php')) ?>">Sign out</a>
        </div>
        <?php endif; ?>
    </aside>
    <main class="main">
        <div class="topbar">
            <h1><?= h($pageTitle) ?></h1>
            <div class="actions"><?php if (!empty($pageActions)) echo $pageActions; ?></div>
        </div>
        <?php if ($flash): ?>
            <div class="flash <?= h($flash['type'] ?? 'info') ?>"><?= h($flash['message'] ?? '') ?></div>
        <?php endif; ?>
