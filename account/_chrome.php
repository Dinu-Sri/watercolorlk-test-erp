<?php

declare(strict_types=1);

/**
 * Lightweight chrome for account/* pages. Reuses the public site header.
 * Inputs (defined before include): $pageTitle (string), optional $cartCount (int).
 *
 *   require __DIR__ . '/_chrome.php'; opens <html> ... <main>
 *   require __DIR__ . '/_chrome_end.php'; closes the page
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle  = $pageTitle  ?? 'My Account · Watercolor.LK';
$cartCount  = $cartCount  ?? 0;

require_once __DIR__ . '/../bootstrap.php';

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" href="../assets/images/brand/favicon.png">
    <?php require __DIR__ . '/../partials/chrome-styles.php'; ?>
    <style>
        body { background:#f4f6f9; }
        .acc-wrap { max-width: 980px; margin: 30px auto; padding: 0 18px; }
        .acc-card { background:#fff; border:1px solid #e6eaf0; border-radius:18px; padding:30px; box-shadow:0 6px 24px rgba(15,36,64,.04); }
        .acc-card h1 { margin:0 0 6px; font:800 1.7rem/1.1 'Montserrat',sans-serif; color:#0f2440; letter-spacing:-.01em; }
        .acc-card .lead { color:#5a6677; margin:0 0 22px; font:400 .98rem/1.5 'Source Sans 3',sans-serif; }
        .acc-form { display:grid; gap:14px; max-width: 420px; }
        .acc-form label { display:block; font:700 .78rem/1 'Montserrat',sans-serif; color:#0f2440; text-transform:uppercase; letter-spacing:.06em; margin-bottom:5px; }
        .acc-form input { width:100%; padding:11px 13px; border:1px solid #d4dae3; border-radius:10px; font:500 .95rem/1.2 'Source Sans 3',sans-serif; background:#fff; }
        .acc-form input:focus { outline:none; border-color:#0f2440; box-shadow:0 0 0 3px rgba(15,36,64,.12); }
        .btn-primary { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:12px 18px; border:0; border-radius:10px; background:#0f2440; color:#fff; font:700 .95rem/1 'Montserrat',sans-serif; cursor:pointer; }
        .btn-primary:hover { background:#1a3863; }
        .btn-google { display:inline-flex; align-items:center; justify-content:center; gap:10px; padding:11px 18px; border:1px solid #d4dae3; border-radius:10px; background:#fff; color:#1a2230; font:700 .95rem/1 'Source Sans 3',sans-serif; cursor:pointer; text-decoration:none; }
        .btn-google:hover { background:#f4f6f9; }
        .acc-divider { display:flex; align-items:center; gap:10px; margin:18px 0; color:#7a8699; font:600 .8rem/1 'Source Sans 3',sans-serif; text-transform:uppercase; letter-spacing:.08em; }
        .acc-divider::before, .acc-divider::after { content:''; flex:1; height:1px; background:#e6eaf0; }
        .acc-msg { padding:11px 14px; border-radius:10px; margin-bottom:16px; font:600 .92rem/1.4 'Source Sans 3',sans-serif; }
        .acc-msg.err { background:#fce9ea; color:#b8232f; border:1px solid #f5c6c9; }
        .acc-msg.ok { background:#e6f4ec; color:#17633e; border:1px solid #b9dec8; }
        .acc-link { color:#0f2440; font-weight:700; text-decoration:none; }
        .acc-link:hover { text-decoration:underline; }
        .acc-meta { color:#7a8699; font:400 .9rem/1.4 'Source Sans 3',sans-serif; margin-top:14px; }
        .acc-grid { display:grid; grid-template-columns: 250px 1fr; gap:24px; align-items:start; }
        @media (max-width:740px) { .acc-grid { grid-template-columns: 1fr; } }
        .acc-side { background:#fff; border:1px solid #e6eaf0; border-radius:14px; padding:18px; }
        .acc-side a { display:block; padding:8px 10px; color:#1a2230; text-decoration:none; border-radius:8px; font:600 .94rem/1.3 'Source Sans 3',sans-serif; }
        .acc-side a:hover, .acc-side a.active { background:#f4f6f9; color:#0f2440; }
        table.acc-table { width:100%; border-collapse:collapse; font:500 .92rem/1.4 'Source Sans 3',sans-serif; }
        table.acc-table th, table.acc-table td { padding:10px 8px; border-bottom:1px solid #e6eaf0; text-align:left; }
        table.acc-table th { font-weight:700; color:#0f2440; font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; }
        .pill { display:inline-block; padding:3px 9px; border-radius:999px; font:700 .72rem/1.4 'Montserrat',sans-serif; text-transform:uppercase; letter-spacing:.05em; }
        .pill-pending { background:#fff4d6; color:#8a6a00; }
        .pill-synced { background:#dff5e7; color:#15633e; }
        .pill-failed { background:#fde2e4; color:#b8232f; }
        .pill-active { background:#e2f4ec; color:#15633e; }
        .pill-disabled { background:#fde2e4; color:#b8232f; }
    </style>
</head>
<body>
<?php
$cartCount = $cartCount ?? 0;
$showPromoBar = false;
$headerSearchValue = '';
/* Site header expects relative paths that work at site root.
   Account pages live at /account/, so we need to fix asset paths after include. */
?>
<div class="account-chrome" data-base="../">
<?php require __DIR__ . '/../partials/site-header.php'; ?>
</div>
<script>
/* Rewrite header asset paths so they resolve from /account/ */
document.querySelectorAll('.account-chrome a[href]').forEach(function(a) {
    var h = a.getAttribute('href');
    if (!h || /^(https?:|#|mailto:|tel:|\/)/.test(h)) return;
    a.setAttribute('href', '../' + h);
});
document.querySelectorAll('.account-chrome img[src]').forEach(function(img) {
    var s = img.getAttribute('src');
    if (!s || /^(https?:|data:|\/)/.test(s)) return;
    img.setAttribute('src', '../' + s);
});
</script>
<main class="acc-wrap">
