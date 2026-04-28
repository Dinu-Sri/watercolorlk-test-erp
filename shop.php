<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$repo = new ProductRepository(appDb());

/* Read URL state for SSR-friendly first paint. JS will hydrate filters from URL too. */
$q          = trim((string)($_GET['q'] ?? ''));
$page       = max(1, (int)($_GET['page'] ?? 1));
$rawPerPage = (int)($_GET['per_page'] ?? 24);
$perPage    = in_array($rawPerPage, [24, 48, 96], true) ? $rawPerPage : 24;
$sort       = (string)($_GET['sort'] ?? 'relevance');
$minPrice   = isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : '';
$maxPrice   = isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : '';
$inStock    = !empty($_GET['in_stock']);

$splitCsv = static function ($v): array {
    if (is_array($v)) $v = implode(',', $v);
    $v = (string)$v;
    if ($v === '') return [];
    $out = [];
    foreach (explode(',', $v) as $p) {
        $p = trim($p);
        if ($p !== '') $out[] = $p;
    }
    return $out;
};
$selectedCategories = $splitCsv($_GET['category'] ?? null);
$selectedBrands     = $splitCsv($_GET['brand'] ?? null);

/* Initial load: fetch products + facets in one go so first paint is correct. */
$bucketMap = [
    'Brushes' => 'brush', 'Papers' => 'paper', 'Paints' => 'paint',
    'Sketchbooks' => 'sketch', 'Accessories' => 'access',
];

/* Resilient facets: each subquery is wrapped so one failure doesn't blank the rail. */
$bucketCounts = [];
try { $bucketCounts = $repo->listCategoriesWithCounts($bucketMap); } catch (Throwable $e) { $bucketCounts = []; }
$bucketFacets = [];
foreach ($bucketMap as $label => $kw) {
    $bucketFacets[] = [
        'label'   => $label,
        'keyword' => $kw,
        'count'   => (int)($bucketCounts[$label] ?? 0),
    ];
}

$brandsList = [];
try { $brandsList = $repo->listAllBrands(); } catch (Throwable $e) { $brandsList = []; }
if (empty($brandsList)) {
    try { $brandsList = $repo->listInferredBrands(); } catch (Throwable $e) { $brandsList = []; }
}

$priceRange = ['min' => 0, 'max' => 10000];
try { $priceRange = $repo->getPriceRange(); } catch (Throwable $e) { /* keep default */ }

$facets = [
    'product_types' => $bucketFacets,
    'brands'        => $brandsList,
    'price_range'   => $priceRange,
];

/* Log search query for analytics (fire-and-forget; failures swallowed in repo). */
if ($q !== '' && mb_strlen($q) >= 2) {
    try { $repo->logSearchQuery($q); } catch (Throwable $e) { /* ignore */ }
}

try {
    $result = $repo->searchProducts([
        'q'          => $q,
        'categories' => $selectedCategories,
        'brands'     => $selectedBrands,
        'min_price'  => $minPrice,
        'max_price'  => $maxPrice,
        'in_stock'   => $inStock,
        'sort'       => $sort,
        'limit'      => $perPage,
        'offset'     => ($page - 1) * $perPage,
    ]);
} catch (Throwable $e) {
    $result = ['products' => [], 'total' => 0];
}

$initialProductsJson = json_encode($result['products'], JSON_UNESCAPED_SLASHES);
$initialTotal        = (int)$result['total'];
$facetsJson          = json_encode($facets, JSON_UNESCAPED_SLASHES);

/* Translate keyword bucket values back to friendly labels for SSR strings. */
$bucketLabelMap = ['brush' => 'Brushes', 'paper' => 'Papers', 'paint' => 'Paints', 'sketch' => 'Sketchbooks', 'access' => 'Accessories'];
$selectedCategoryLabels = array_map(static fn(string $c): string => $bucketLabelMap[mb_strtolower($c)] ?? $c, $selectedCategories);

$pageTitle = $q !== ''
    ? ('Search: ' . $q . ' - Watercolor.LK')
    : (!empty($selectedCategoryLabels) ? (implode(' & ', $selectedCategoryLabels) . ' - Watercolor.LK') : 'Shop all products - Watercolor.LK');

function shopProductUrl(string $slug, string $name, int $erpId): string
{
    $base = trim($slug);
    if ($base === '' || preg_match('/^product-\d+$/i', $base) === 1) {
        $base = $name !== '' ? $name : 'product';
    }
    if (function_exists('iconv')) {
        $a = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
        if (is_string($a) && $a !== '') $base = $a;
    }
    $base = strtolower($base);
    $base = preg_replace('/[^a-z0-9]+/i', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') $base = 'product';
    return 'product/' . rawurlencode($base) . '-' . $erpId;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Browse watercolor paints, brushes, papers and sketchbooks. Filter by brand, price and availability. Free island-wide delivery over LKR 5,000.">
<link rel="icon" type="image/png" href="assets/images/brand/logo-watercolorlk.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800;900&family=Playfair+Display:wght@600;700;800&family=Source+Sans+3:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php include __DIR__ . '/partials/chrome-styles.php'; ?>
<style>
.shop-wrap { padding: 18px 0 60px; }

/* Visually-hidden helper for SEO/a11y headings */
.sr-only { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

/* Breadcrumb + heading */
.shop-crumb { color: #6b7388; font: 600 .82rem/1 'Source Sans 3', sans-serif; margin-bottom: 6px; }
.shop-crumb a { color: #6b7388; text-decoration: none; }
.shop-crumb a:hover { color: var(--amber-deep); }
.shop-crumb .sep { margin: 0 8px; opacity: .6; }
.shop-head { display: flex; align-items: end; justify-content: space-between; gap: 14px; margin-bottom: 14px; flex-wrap: wrap; }
.shop-head h1 { margin: 0; color: var(--brand-navy-deep); font: 800 1.55rem/1.1 'Playfair Display', serif; }
.shop-head h1 em { color: var(--amber-deep); font-style: normal; }
.shop-head .meta { color: #6b7388; font: 600 .92rem/1.2 'Source Sans 3', sans-serif; }

/* Trust strip */
.shop-trust {
    display: flex; gap: 8px; flex-wrap: wrap;
    padding: 8px 12px; margin-bottom: 14px;
    background: #fff; border: 1px solid var(--line); border-radius: 12px;
    font: 700 .76rem/1 'Montserrat', sans-serif;
}
.shop-trust .chip {
    display: inline-flex; align-items: center; gap: 6px; color: #4a5468;
    padding: 4px 10px; border-radius: 999px; background: rgba(232,118,10,.06);
}
.shop-trust .chip svg { width: 14px; height: 14px; color: var(--amber-deep); }

/* Layout */
.shop-grid { display: grid; grid-template-columns: 260px 1fr; gap: 22px; align-items: start; }

/* Left filter rail */
.shop-filters {
    position: sticky; top: 84px;
    background: #fff; border: 1px solid var(--line); border-radius: 14px;
    padding: 14px 16px; box-shadow: var(--shadow-sm);
    max-height: calc(100vh - 100px); overflow-y: auto;
}
.shop-filters h3 {
    margin: 0; font: 800 .82rem/1 'Montserrat', sans-serif;
    color: var(--brand-navy-deep); letter-spacing: .04em; text-transform: uppercase;
}
.shop-filters .filt-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.shop-filters .clear-all { font: 700 .76rem/1 'Montserrat', sans-serif; color: var(--amber-deep); background: 0; border: 0; cursor: pointer; padding: 0; }
.shop-filters .clear-all:hover { text-decoration: underline; }
.shop-filters .clear-all[hidden] { display: none; }

.filt-block { padding: 12px 0; border-top: 1px dashed var(--line); }
.filt-block:first-of-type { border-top: 0; padding-top: 0; }
.filt-block summary {
    cursor: pointer; list-style: none; display: flex; justify-content: space-between; align-items: center;
    color: var(--brand-navy); font: 800 .8rem/1 'Montserrat', sans-serif; letter-spacing: .04em; text-transform: uppercase;
}
.filt-block summary::-webkit-details-marker { display: none; }
.filt-block summary::after { content: "+"; color: var(--amber-deep); font-weight: 800; transition: transform .15s; }
.filt-block[open] summary::after { content: "-"; }
.filt-block .body { padding-top: 10px; }

.filt-list { display: grid; gap: 6px; max-height: 220px; overflow-y: auto; padding-right: 4px; }
.filt-list label {
    display: flex; align-items: center; gap: 8px; padding: 4px 0; cursor: pointer;
    font: 600 .88rem/1.2 'Source Sans 3', sans-serif; color: #2a3448;
}
.filt-list label:hover { color: var(--amber-deep); }
.filt-list label .cnt { margin-left: auto; color: #98a1b3; font-size: .78rem; font-weight: 600; }
.filt-list input[type=checkbox] { accent-color: var(--amber); width: 16px; height: 16px; }
.filt-show-more { background: 0; border: 0; padding: 6px 0 0; color: var(--amber-deep); font: 700 .76rem/1 'Montserrat', sans-serif; cursor: pointer; }
.filt-show-more:hover { text-decoration: underline; }

/* Price */
.price-inputs { display: grid; grid-template-columns: 1fr auto 1fr; gap: 6px; align-items: center; margin-top: 6px; }
.price-inputs input {
    width: 100%; box-sizing: border-box; padding: 7px 9px; border: 1px solid var(--line);
    border-radius: 8px; font: 600 .82rem/1 'Source Sans 3', sans-serif; color: var(--brand-navy-deep);
    outline: none;
}
.price-inputs input:focus { border-color: var(--amber); }
.price-inputs span { color: #98a1b3; font-weight: 700; }
.price-help { color: #6b7388; font: 500 .74rem/1.4 'Source Sans 3', sans-serif; margin-top: 6px; }

/* In stock toggle */
.toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 4px 0; }
.toggle-row label { color: var(--brand-navy); font: 700 .9rem/1 'Source Sans 3', sans-serif; cursor: pointer; }
.switch { position: relative; width: 38px; height: 22px; flex: 0 0 38px; }
.switch input { opacity: 0; width: 0; height: 0; }
.switch .slider {
    position: absolute; inset: 0; background: #d6dae4; border-radius: 999px; cursor: pointer; transition: background .15s;
}
.switch .slider::before {
    content: ""; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px;
    background: #fff; border-radius: 50%; transition: transform .15s;
}
.switch input:checked + .slider { background: var(--amber); }
.switch input:checked + .slider::before { transform: translateX(16px); }

/* Toolbar */
.shop-toolbar {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    background: #fff; border: 1px solid var(--line); border-radius: 12px;
    padding: 10px 12px; margin-bottom: 12px; flex-wrap: wrap;
}
.shop-toolbar .count { color: var(--brand-navy); font: 700 .92rem/1 'Source Sans 3', sans-serif; }
.shop-toolbar .right { display: flex; align-items: center; gap: 10px; }
.shop-toolbar select {
    padding: 8px 28px 8px 10px; border: 1px solid var(--line); border-radius: 8px;
    background: #fff url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'><path fill='%231b2d4f' d='M0 0l5 6 5-6z'/></svg>") right 10px center / 10px no-repeat;
    appearance: none; -webkit-appearance: none; -moz-appearance: none;
    color: var(--brand-navy); font: 700 .82rem/1 'Montserrat', sans-serif; cursor: pointer;
}
.mobile-filter-btn {
    display: none;
    padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; background: #fff;
    color: var(--brand-navy); font: 700 .82rem/1 'Montserrat', sans-serif; cursor: pointer;
    align-items: center; gap: 6px;
}
.mobile-filter-btn .pip {
    background: var(--amber); color: #fff; border-radius: 999px; min-width: 18px; height: 18px;
    padding: 0 5px; font: 800 .68rem/18px 'Montserrat', sans-serif; text-align: center;
}

/* Active chips */
.active-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.active-chips .chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 10px 5px 12px; border-radius: 999px;
    background: rgba(232,118,10,.1); color: var(--amber-deep);
    font: 700 .78rem/1 'Montserrat', sans-serif; border: 1px solid rgba(232,118,10,.2);
}
.active-chips .chip button {
    background: 0; border: 0; cursor: pointer; color: inherit;
    width: 16px; height: 16px; border-radius: 50%; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.1rem; line-height: 1;
}
.active-chips .chip button:hover { background: rgba(184,35,47,.15); color: #b8232f; }

/* Product grid */
.shop-products {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;
}
.sp-card {
    background: #fff; border: 1px solid var(--line); border-radius: 14px;
    overflow: hidden; text-decoration: none; color: inherit;
    display: flex; flex-direction: column;
    transition: transform .15s, box-shadow .15s;
    position: relative;
}
.sp-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.sp-card.is-out { opacity: .55; }
.sp-card .media { aspect-ratio: 1/1; background: #f3eee6; padding: 10px; display: flex; align-items: center; justify-content: center; }
.sp-card .media img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
.sp-card .badge {
    position: absolute; top: 8px; left: 8px;
    padding: 3px 8px; border-radius: 999px;
    font: 800 .68rem/1 'Montserrat', sans-serif; letter-spacing: .04em;
    background: var(--brand-navy); color: #fff;
}
.sp-card .badge.sale { background: var(--accent-fire); }
.sp-card .badge.low  { background: var(--amber); }
.sp-card .badge.out  { background: #6b7388; }
.sp-card .body { padding: 10px 12px 12px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
.sp-card .brand { color: #98a1b3; font: 700 .68rem/1 'Montserrat', sans-serif; letter-spacing: .04em; text-transform: uppercase; }
.sp-card .nm {
    color: var(--brand-navy-deep); font: 700 .9rem/1.25 'Source Sans 3', sans-serif;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    min-height: 2.5em;
}
.sp-card .pr { color: var(--brand-navy); font: 800 1.05rem/1 'Source Sans 3', sans-serif; margin-top: 4px; }
.sp-card .pr .old { color: #98a1b3; font-size: .78rem; font-weight: 600; text-decoration: line-through; margin-left: 6px; }
.sp-card .add {
    margin-top: 8px; padding: 8px 10px; border: 0; border-radius: 8px;
    background: linear-gradient(180deg, #ff5b3a, #b8232f); color: #fff;
    font: 800 .76rem/1 'Montserrat', sans-serif; letter-spacing: .04em; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.sp-card .add:disabled { background: #c8cdd8; cursor: not-allowed; }
.sp-card .add svg { width: 14px; height: 14px; }
.sp-card .add:hover:not(:disabled) { box-shadow: 0 6px 14px rgba(184,35,47,.3); }

/* Skeleton */
.sk { background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
.sk .ph { background: linear-gradient(90deg, #f3eee6 0%, #faf6f0 50%, #f3eee6 100%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
.sk .ph.media { aspect-ratio: 1/1; }
.sk .ph.line { height: 12px; margin: 8px 12px; border-radius: 4px; }
.sk .ph.line.short { width: 40%; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* Empty state */
.empty {
    grid-column: 1 / -1;
    background: #fff; border: 1px dashed #d9cab8; border-radius: 14px;
    padding: 36px 24px; text-align: center;
}
.empty img { width: 110px; opacity: .9; }
.empty h2 { margin: 8px 0 4px; color: var(--brand-navy-deep); font: 800 1.2rem/1.2 'Playfair Display', serif; }
.empty p { color: #6b7388; margin: 0 0 16px; font: 500 .94rem/1.5 'Source Sans 3', sans-serif; }
.empty .actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
.empty .actions a, .empty .actions button {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; border-radius: 999px; text-decoration: none; cursor: pointer;
    font: 800 .84rem/1 'Montserrat', sans-serif; border: 0;
}
.empty .actions .primary { background: linear-gradient(180deg, #ff5b3a, #b8232f); color: #fff; }
.empty .actions .ghost { background: #fff; color: var(--brand-navy); border: 1px solid var(--line); }
.empty .actions .wa { background: #25d366; color: #fff; }

/* Pagination */
.shop-pagination {
    display: flex; gap: 4px; justify-content: center; align-items: center;
    margin-top: 22px; flex-wrap: wrap;
}
.shop-pagination button, .shop-pagination span {
    min-width: 36px; height: 36px; padding: 0 10px;
    border: 1px solid var(--line); border-radius: 8px; background: #fff; color: var(--brand-navy);
    font: 700 .86rem/1 'Montserrat', sans-serif; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
}
.shop-pagination button[aria-current="true"] {
    background: var(--brand-navy); color: #fff; border-color: var(--brand-navy);
}
.shop-pagination button:hover:not([disabled]):not([aria-current="true"]) { background: rgba(232,118,10,.08); border-color: var(--amber); color: var(--amber-deep); }
.shop-pagination button[disabled] { opacity: .4; cursor: not-allowed; }
.shop-pagination .ellipsis { background: 0; border: 0; cursor: default; color: #98a1b3; }

/* Recently viewed rail */
.rv-section { margin-top: 32px; }
.rv-section h2 { margin: 0 0 12px; color: var(--brand-navy-deep); font: 800 1.15rem/1.2 'Playfair Display', serif; }
.rv-rail { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(160px, 1fr); gap: 12px; overflow-x: auto; padding-bottom: 8px; }
.rv-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; text-decoration: none; color: inherit; }
.rv-card:hover { box-shadow: var(--shadow-sm); }
.rv-card .media { aspect-ratio: 1/1; padding: 6px; background: #f3eee6; }
.rv-card .media img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.rv-card .body { padding: 8px 10px 10px; }
.rv-card .nm { color: var(--brand-navy-deep); font: 700 .82rem/1.2 'Source Sans 3', sans-serif; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.rv-card .pr { color: var(--brand-navy); font: 800 .9rem/1 'Source Sans 3', sans-serif; margin-top: 4px; }

/* Mobile drawer */
.filter-drawer-overlay {
    position: fixed; inset: 0; background: rgba(16,32,58,.5); z-index: 49;
    display: none; opacity: 0; transition: opacity .2s;
}
.filter-drawer-overlay.is-open { display: block; opacity: 1; }
.filter-drawer {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 50;
    background: #fff; border-radius: 18px 18px 0 0;
    max-height: 86vh; display: none; flex-direction: column;
    transform: translateY(100%); transition: transform .25s;
}
.filter-drawer.is-open { display: flex; transform: translateY(0); }
.filter-drawer .dh { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--line); }
.filter-drawer .dh h3 { margin: 0; color: var(--brand-navy-deep); font: 800 1.05rem/1.1 'Playfair Display', serif; }
.filter-drawer .dh button { background: 0; border: 0; cursor: pointer; font-size: 1.4rem; color: var(--brand-navy); }
.filter-drawer .body { flex: 1; overflow-y: auto; padding: 14px 16px; }
.filter-drawer .footer {
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); border-top: 1px solid var(--line);
    display: flex; gap: 10px;
}
.filter-drawer .footer button {
    flex: 1; padding: 13px; border-radius: 10px; cursor: pointer; border: 0;
    font: 800 .92rem/1 'Montserrat', sans-serif; letter-spacing: .04em;
}
.filter-drawer .footer .reset { background: #fff; color: var(--brand-navy); border: 1px solid var(--line); }
.filter-drawer .footer .apply { background: linear-gradient(180deg, #ff5b3a, #b8232f); color: #fff; flex: 1.5; box-shadow: 0 8px 16px rgba(184,35,47,.3); }

@media (max-width: 980px) {
    .shop-grid { grid-template-columns: 1fr; }
    .shop-filters { display: none; }
    .mobile-filter-btn { display: inline-flex; }
    .shop-products { grid-template-columns: repeat(2, 1fr); gap: 10px; }
}
@media (max-width: 460px) {
    .shop-products { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .sp-card .nm { font-size: .82rem; }
    .sp-card .pr { font-size: .94rem; }
}
</style>
</head>
<body>
<?php
$showPromoBar = true;
$headerSearchValue = $q;
$cartCount = 0;
include __DIR__ . '/partials/site-header.php';
?>

<main class="wrap shop-wrap">
    <h1 id="shopHeading" class="sr-only">
        <?php if ($q !== ''): ?>Results for "<?= htmlspecialchars($q) ?>"
        <?php elseif (!empty($selectedCategoryLabels)): ?><?= htmlspecialchars(implode(' & ', $selectedCategoryLabels)) ?>
        <?php else: ?>All products<?php endif; ?>
    </h1>
    <div class="sr-only" id="shopCount"><?= number_format($initialTotal) ?> products</div>

    <div class="shop-grid">
        <!-- LEFT FILTER RAIL -->
        <aside class="shop-filters" id="shopFilters" aria-label="Filters"></aside>

        <!-- RIGHT: toolbar + grid -->
        <section>
            <div class="shop-toolbar">
                <button class="mobile-filter-btn" type="button" id="openFilters">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
                    Filters <span class="pip" id="filterPip" hidden>0</span>
                </button>
                <span class="count" id="shopCountInline"><?= number_format($initialTotal) ?> products</span>
                <div class="right">
                    <select id="sortSelect" aria-label="Sort by">
                        <option value="relevance">Relevance</option>
                        <option value="price_asc">Price: Low to high</option>
                        <option value="price_desc">Price: High to low</option>
                        <option value="newest">Newest</option>
                        <option value="name">Name A-Z</option>
                    </select>
                    <select id="perPageSelect" aria-label="Per page">
                        <option value="24">24 / page</option>
                        <option value="48">48 / page</option>
                        <option value="96">96 / page</option>
                    </select>
                </div>
            </div>

            <div class="active-chips" id="activeChips" hidden></div>

            <div class="shop-products" id="shopGrid"></div>

            <nav class="shop-pagination" id="shopPagination" aria-label="Pagination"></nav>
        </section>
    </div>

    <section class="rv-section" id="rvSection" hidden>
        <h2>Recently viewed</h2>
        <div class="rv-rail" id="rvRail"></div>
    </section>
</main>

<!-- MOBILE FILTER DRAWER -->
<div class="filter-drawer-overlay" id="filterOverlay"></div>
<aside class="filter-drawer" id="filterDrawer" aria-label="Filters">
    <header class="dh">
        <h3>Filters</h3>
        <button type="button" id="closeFilters" aria-label="Close">&times;</button>
    </header>
    <div class="body" id="drawerBody"></div>
    <footer class="footer">
        <button type="button" class="reset" id="drawerReset">Clear all</button>
        <button type="button" class="apply" id="drawerApply">Apply (<span id="drawerCount">0</span>)</button>
    </footer>
</aside>

<?php include __DIR__ . '/partials/site-footer.php'; ?>
<?php include __DIR__ . '/partials/site-scripts.php'; ?>

<script>
(function() {
    var INITIAL_PRODUCTS = <?= $initialProductsJson ?: '[]' ?>;
    var INITIAL_TOTAL    = <?= (int)$initialTotal ?>;
    var FACETS           = <?= $facetsJson ?: '{"product_types":[],"brands":[],"price_range":{"min":0,"max":10000}}' ?>;
    var PER_PAGE_DEFAULT = <?= (int)$perPage ?>;

    /* ---------- State, sourced from URL ---------- */
    function readState() {
        var url = new URL(window.location.href);
        var p = url.searchParams;
        var multi = function(name) {
            var v = p.get(name) || '';
            return v ? v.split(',').map(function(s){return s.trim();}).filter(Boolean) : [];
        };
        return {
            q: p.get('q') || '',
            categories: multi('category'),
            brands: multi('brand'),
            min: p.get('min') || '',
            max: p.get('max') || '',
            in_stock: p.get('in_stock') === '1',
            sort: p.get('sort') || 'relevance',
            page: Math.max(1, parseInt(p.get('page') || '1', 10) || 1),
            per_page: (function(){ var v = parseInt(p.get('per_page') || '', 10); return [24,48,96].indexOf(v) !== -1 ? v : 24; })()
        };
    }
    function writeState(s, replace) {
        var p = new URLSearchParams();
        if (s.q) p.set('q', s.q);
        if (s.categories.length) p.set('category', s.categories.join(','));
        if (s.brands.length) p.set('brand', s.brands.join(','));
        if (s.min) p.set('min', s.min);
        if (s.max) p.set('max', s.max);
        if (s.in_stock) p.set('in_stock', '1');
        if (s.sort && s.sort !== 'relevance') p.set('sort', s.sort);
        if (s.page > 1) p.set('page', String(s.page));
        if (s.per_page && s.per_page !== 24) p.set('per_page', String(s.per_page));
        var qs = p.toString();
        var newUrl = (window.WLK_BASE || '') + 'shop.php' + (qs ? '?' + qs : '');
        if (replace) history.replaceState(s, '', newUrl);
        else history.pushState(s, '', newUrl);
    }

    var state = readState();

    /* ---------- DOM ---------- */
    var $grid = document.getElementById('shopGrid');
    var $count = document.getElementById('shopCount');
    var $countInline = document.getElementById('shopCountInline');
    var $heading = document.getElementById('shopHeading');
    var $pagination = document.getElementById('shopPagination');
    var $chips = document.getElementById('activeChips');
    var $sort = document.getElementById('sortSelect');
    var $perPage = document.getElementById('perPageSelect');
    var $filters = document.getElementById('shopFilters');
    var $drawerBody = document.getElementById('drawerBody');
    var $drawerCount = document.getElementById('drawerCount');
    var $filterPip = document.getElementById('filterPip');
    var $headerSearch = document.getElementById('search');

    var fetchSeq = 0;
    var fetchTimer = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);});
    }
    function formatLKR(n) {
        return 'LKR ' + Number(n||0).toLocaleString('en-LK', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }
    function buildSlug(name) {
        return String(name||'product').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'') || 'product';
    }
    function productHref(p) {
        var slug = (p.slug && !/^product-\d+$/i.test(p.slug)) ? p.slug : buildSlug(p.display_name || p.name);
        return (window.WLK_BASE || '') + 'product/' + encodeURIComponent(slug) + '-' + Number(p.erp_product_id);
    }

    /* ---------- Render filter rail (used in both desktop and drawer) ---------- */
    function renderFiltersInto(host, prefix) {
        var html = '';
        html += '<div class="filt-head"><h3>Filters</h3><button class="clear-all" type="button" id="' + prefix + 'ClearAll" hidden>Clear all</button></div>';

        /* Product Type (keyword buckets — always rendered so the section is never missing) */
        html += '<details class="filt-block" open><summary>Product Type</summary><div class="body"><div class="filt-list" data-list="cat">';
        var pt = (FACETS.product_types && FACETS.product_types.length) ? FACETS.product_types : [
            {label:'Brushes',keyword:'brush',count:0},{label:'Papers',keyword:'paper',count:0},
            {label:'Paints',keyword:'paint',count:0},{label:'Sketchbooks',keyword:'sketch',count:0},
            {label:'Accessories',keyword:'access',count:0}
        ];
        pt.forEach(function(c) {
            var checked = state.categories.indexOf(c.keyword) !== -1 ? 'checked' : '';
            var dim = (c.count <= 0) ? ' style="opacity:.5"' : '';
            html += '<label' + dim + '><input type="checkbox" data-filter="category" value="' + escapeHtml(c.keyword) + '" ' + checked + '><span>' + escapeHtml(c.label) + '</span><span class="cnt">' + (c.count||0) + '</span></label>';
        });
        html += '</div></div></details>';

        /* Brands — always rendered; falls back to a short hint if the list is empty */
        html += '<details class="filt-block"><summary>Brand</summary><div class="body"><div class="filt-list" data-list="brand">';
        if (FACETS.brands && FACETS.brands.length) {
            FACETS.brands.forEach(function(b) {
                var checked = state.brands.indexOf(b.name) !== -1 ? 'checked' : '';
                html += '<label><input type="checkbox" data-filter="brand" value="' + escapeHtml(b.name) + '" ' + checked + '><span>' + escapeHtml(b.name) + '</span><span class="cnt">' + b.count + '</span></label>';
            });
        } else {
            html += '<div style="color:#98a1b3;font:600 .82rem/1.3 \'Source Sans 3\',sans-serif;padding:6px 0">No brand data yet.</div>';
        }
        html += '</div></div></details>';

        /* Price */
        var pr = FACETS.price_range || {min:0, max:10000};
        html += '<details class="filt-block" open><summary>Price</summary><div class="body">';
        html += '<div class="price-inputs">';
        html += '<input type="number" inputmode="numeric" min="0" placeholder="' + Math.floor(pr.min || 0) + '" data-filter="min" value="' + escapeHtml(state.min) + '">';
        html += '<span>-</span>';
        html += '<input type="number" inputmode="numeric" min="0" placeholder="' + Math.ceil(pr.max || 10000) + '" data-filter="max" value="' + escapeHtml(state.max) + '">';
        html += '</div>';
        html += '<div class="price-help">Range: ' + formatLKR(pr.min) + ' - ' + formatLKR(pr.max) + '</div>';
        html += '</div></details>';

        /* Availability */
        html += '<details class="filt-block" open><summary>Availability</summary><div class="body">';
        html += '<div class="toggle-row"><label for="' + prefix + 'InStock">In stock only</label>';
        html += '<span class="switch"><input type="checkbox" id="' + prefix + 'InStock" data-filter="in_stock" ' + (state.in_stock ? 'checked' : '') + '><span class="slider"></span></span>';
        html += '</div></div></details>';

        host.innerHTML = html;
        bindFilterEvents(host);
        updateClearButton();
    }

    function bindFilterEvents(host) {
        host.addEventListener('change', function(e) {
            var t = e.target;
            if (!t || !t.dataset || !t.dataset.filter) return;
            var f = t.dataset.filter;
            if (f === 'category' || f === 'brand') {
                var key = f === 'category' ? 'categories' : 'brands';
                var v = t.value;
                if (t.checked) {
                    if (state[key].indexOf(v) === -1) state[key].push(v);
                } else {
                    state[key] = state[key].filter(function(x){return x !== v;});
                }
                state.page = 1;
                applyChange();
            } else if (f === 'in_stock') {
                state.in_stock = t.checked;
                state.page = 1;
                applyChange();
            } else if (f === 'min' || f === 'max') {
                /* debounced via input event below */
            }
        });
        host.addEventListener('input', function(e) {
            var t = e.target;
            if (!t || !t.dataset || !t.dataset.filter) return;
            if (t.dataset.filter === 'min' || t.dataset.filter === 'max') {
                state[t.dataset.filter] = t.value.trim();
                state.page = 1;
                debouncedApply();
            }
        });
    }
    function updateClearButton() {
        var has = state.categories.length || state.brands.length || state.min || state.max || state.in_stock || state.q;
        ['shopFiltersClearAll', 'drawerClearAll'].forEach(function(id) {
            var btn = document.getElementById(id);
            if (btn) {
                if (has) btn.removeAttribute('hidden');
                else btn.setAttribute('hidden', '');
            }
        });
        if ($filterPip) {
            var n = state.categories.length + state.brands.length + (state.min ? 1 : 0) + (state.max ? 1 : 0) + (state.in_stock ? 1 : 0);
            if (n > 0) { $filterPip.textContent = String(n); $filterPip.removeAttribute('hidden'); }
            else $filterPip.setAttribute('hidden', '');
        }
    }

    document.addEventListener('click', function(e) {
        if (e.target && (e.target.id === 'shopFiltersClearAll' || e.target.id === 'drawerClearAll')) {
            state.categories = []; state.brands = []; state.min = ''; state.max = ''; state.in_stock = false; state.q = '';
            if ($headerSearch) $headerSearch.value = '';
            state.page = 1;
            applyChange();
            renderFiltersInto($filters, 'shopFilters');
            renderFiltersInto($drawerBody, 'drawer');
        }
    });

    /* ---------- Active filter chips ---------- */
    function renderChips() {
        var chips = [];
        var bucketLabels = {};
        (FACETS.product_types || []).forEach(function(b) { bucketLabels[b.keyword] = b.label; });
        if (state.q) chips.push({label: 'Search: "' + state.q + '"', remove: function(){
            state.q = '';
            if ($headerSearch) { $headerSearch.value = ''; }
            /* Also remove ?q= from the URL right away so reload preserves cleared state */
            try {
                var u = new URL(window.location.href);
                u.searchParams.delete('q');
                window.history.replaceState(null, '', u.toString());
            } catch (_) {}
        }});
        state.categories.forEach(function(c) {
            var nice = bucketLabels[c] || c;
            chips.push({label: nice, remove: function(){ state.categories = state.categories.filter(function(x){return x !== c;}); }});
        });
        state.brands.forEach(function(b) { chips.push({label: 'Brand: ' + b, remove: function(){ state.brands = state.brands.filter(function(x){return x !== b;}); }}); });
        if (state.min || state.max) chips.push({label: 'Price: ' + (state.min || '0') + ' - ' + (state.max || '∞'), remove: function(){ state.min = ''; state.max = ''; }});
        if (state.in_stock) chips.push({label: 'In stock only', remove: function(){ state.in_stock = false; }});

        if (!chips.length) { $chips.setAttribute('hidden', ''); return; }
        $chips.innerHTML = chips.map(function(c, i) {
            return '<span class="chip">' + escapeHtml(c.label) + '<button type="button" data-i="' + i + '" aria-label="Remove">&times;</button></span>';
        }).join('');
        $chips.removeAttribute('hidden');
        $chips.querySelectorAll('button').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var i = Number(btn.dataset.i);
                if (chips[i]) {
                    chips[i].remove();
                    state.page = 1;
                    renderChips();        /* update chips instantly — don't wait for fetch */
                    updateClearButton();
                    applyChange();
                    renderFiltersInto($filters, 'shopFilters');
                    renderFiltersInto($drawerBody, 'drawer');
                }
            });
        });
    }

    /* ---------- Render product cards ---------- */
    function cardHtml(p) {
        var img = p.image_url || 'assets/images/brand/logo-watercolorlk.png';
        var stock = Number(p.stock_qty || 0);
        var price = Number(p.price || 0);
        var orig = Number(p.original_price || 0);
        var hasDiscount = orig > price && price > 0;
        var badge = '';
        if (stock <= 0) badge = '<span class="badge out">Sold out</span>';
        else if (hasDiscount) badge = '<span class="badge sale">-' + Math.round(((orig - price) / orig) * 100) + '%</span>';
        else if (stock <= 7) badge = '<span class="badge low">Only ' + stock + ' left</span>';
        else if (p.badge) badge = '<span class="badge">' + escapeHtml(p.badge) + '</span>';

        var oldPrice = hasDiscount ? '<span class="old">' + formatLKR(orig) + '</span>' : '';
        var brand = p.brand_name ? '<span class="brand">' + escapeHtml(p.brand_name) + '</span>' : '';
        var addBtn = stock <= 0
            ? '<button class="add" disabled type="button">Sold out</button>'
            : '<button class="add" type="button" data-add="1" data-id="' + Number(p.erp_product_id) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H7"/></svg>Add to cart</button>';

        var data = encodeURIComponent(JSON.stringify({
            erp_product_id: Number(p.erp_product_id),
            name: p.display_name || p.name || '',
            slug: p.slug || '',
            image_url: p.image_url || '',
            price: price,
            sku: p.sku || ''
        }));

        return '<article class="sp-card' + (stock <= 0 ? ' is-out' : '') + '" data-product="' + data + '">' +
            badge +
            '<a class="media" href="' + escapeHtml(productHref(p)) + '"><img loading="lazy" src="' + escapeHtml(img) + '" alt="" onerror="this.onerror=null;this.src=\'assets/images/brand/logo-watercolorlk.png\';"></a>' +
            '<div class="body">' +
                brand +
                '<a class="nm" href="' + escapeHtml(productHref(p)) + '">' + escapeHtml(p.display_name || p.name || '') + '</a>' +
                '<div class="pr">' + formatLKR(price) + oldPrice + '</div>' +
                addBtn +
            '</div>' +
        '</article>';
    }

    function renderProducts(products, total) {
        if (!products || !products.length) {
            $grid.innerHTML = '<div class="empty">' +
                '<img src="assets/images/mascots/watercolor-paints.webp" alt="" onerror="this.onerror=null;this.src=\'assets/images/brand/logo-watercolorlk.png\';">' +
                '<h2>No products found</h2>' +
                '<p>Try removing some filters or browse all products.</p>' +
                '<div class="actions">' +
                    '<button class="primary" type="button" id="emptyClearAll">Clear all filters</button>' +
                    '<a class="ghost" href="index.php">Back to home</a>' +
                    '<a class="wa" href="https://wa.me/94700000000?text=' + encodeURIComponent('Hi Watercolor.LK, I am looking for: ' + (state.q || (state.categories.join(', ') || 'a product'))) + '" target="_blank" rel="noopener">Ask on WhatsApp</a>' +
                '</div>' +
            '</div>';
            var btn = document.getElementById('emptyClearAll');
            if (btn) btn.addEventListener('click', function() {
                state.categories = []; state.brands = []; state.min = ''; state.max = ''; state.in_stock = false; state.q = '';
                if ($headerSearch) $headerSearch.value = '';
                state.page = 1; applyChange();
                renderFiltersInto($filters, 'shopFilters'); renderFiltersInto($drawerBody, 'drawer');
            });
            $pagination.innerHTML = '';
            return;
        }
        $grid.innerHTML = products.map(cardHtml).join('');
    }
    function renderSkeletons() {
        var n = Math.min(8, state.per_page);
        var html = '';
        for (var i = 0; i < n; i++) {
            html += '<div class="sk"><div class="ph media"></div><div class="ph line" style="width:80%"></div><div class="ph line short"></div><div class="ph line" style="width:50%"></div></div>';
        }
        $grid.innerHTML = html;
    }

    /* ---------- Pagination ---------- */
    function renderPagination(total) {
        var per = state.per_page > 0 ? state.per_page : <?= (int)$perPage ?>;
        var totalPages = Math.max(1, Math.ceil((total || 0) / per));
        if (!isFinite(totalPages) || totalPages > 999) totalPages = 1;
        if (totalPages <= 1) { $pagination.innerHTML = ''; return; }
        var cur = state.page;
        var pages = [];
        var seen = {};
        var add = function(p) { if (!seen[p]) { seen[p] = true; pages.push(p); } };
        add(1);
        var winStart = Math.max(2, cur - 2);
        var winEnd   = Math.min(totalPages - 1, cur + 2);
        if (winStart > 2) pages.push('...');
        for (var p = winStart; p <= winEnd; p++) add(p);
        if (winEnd < totalPages - 1) pages.push('...');
        if (totalPages > 1) add(totalPages);

        var html = '';
        html += '<button type="button" data-page="' + (cur - 1) + '"' + (cur <= 1 ? ' disabled' : '') + '>&larr;</button>';
        pages.forEach(function(p) {
            if (p === '...') html += '<span class="ellipsis">...</span>';
            else html += '<button type="button" data-page="' + p + '"' + (p === cur ? ' aria-current="true"' : '') + '>' + p + '</button>';
        });
        html += '<button type="button" data-page="' + (cur + 1) + '"' + (cur >= totalPages ? ' disabled' : '') + '>&rarr;</button>';
        $pagination.innerHTML = html;
    }
    $pagination.addEventListener('click', function(e) {
        var btn = e.target.closest('button[data-page]');
        if (!btn) return;
        var p = Number(btn.dataset.page);
        if (!p || p < 1) return;
        state.page = p;
        applyChange();
        window.scrollTo({ top: document.querySelector('.shop-toolbar').offsetTop - 80, behavior: 'smooth' });
    });

    /* ---------- Update heading + count ---------- */
    function updateHeading(total) {
        var label = '';
        var bucketLabels = {};
        (FACETS.product_types || []).forEach(function(b) { bucketLabels[b.keyword] = b.label; });
        if (state.q) label = 'Results for <em>"' + escapeHtml(state.q) + '"</em>';
        else if (state.categories.length) {
            label = escapeHtml(state.categories.map(function(c){ return bucketLabels[c] || c; }).join(' & '));
        }
        else label = 'All products';
        $heading.innerHTML = label;
        var fmt = Number(total).toLocaleString('en-LK');
        $count.textContent = fmt + ' product' + (total === 1 ? '' : 's');
        $countInline.textContent = fmt + ' product' + (total === 1 ? '' : 's');
        $drawerCount.textContent = fmt;
    }

    /* ---------- Add to cart from card ---------- */
    $grid.addEventListener('click', function(e) {
        var btn = e.target.closest('button[data-add]');
        if (!btn) return;
        var card = btn.closest('.sp-card');
        if (!card) return;
        var data;
        try { data = JSON.parse(decodeURIComponent(card.dataset.product || '')); } catch (_) { return; }
        if (!window.WLKCart || !data) return;
        window.WLKCart.add(data, 1);
        window.WLKCart.toast('Added to cart', { action: { href: 'cart.php', label: 'View cart' } });
    });

    /* ---------- Fetch ---------- */
    function buildQuery() {
        var p = new URLSearchParams();
        if (state.q) p.set('q', state.q);
        if (state.categories.length) p.set('category', state.categories.join(','));
        if (state.brands.length) p.set('brand', state.brands.join(','));
        if (state.min) p.set('min', state.min);
        if (state.max) p.set('max', state.max);
        if (state.in_stock) p.set('in_stock', '1');
        if (state.sort && state.sort !== 'relevance') p.set('sort', state.sort);
        p.set('page', String(state.page));
        p.set('per_page', String(state.per_page));
        return p.toString();
    }
    function fetchProducts() {
        var seq = ++fetchSeq;
        renderSkeletons();
        fetch((window.WLK_BASE || '') + 'api/products.php?' + buildQuery() + '&_=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (seq !== fetchSeq) return;
                if (!data.success) {
                    $grid.innerHTML = '<div class="empty"><h2>Search is unavailable</h2><p>Please try again in a moment.</p></div>';
                    return;
                }
                var total = (typeof data.total === 'number') ? data.total : (data.products || []).length;
                renderProducts(data.products || [], total);
                renderPagination(total);
                updateHeading(total);
                renderChips();
                updateClearButton();
            })
            .catch(function() {
                if (seq !== fetchSeq) return;
                $grid.innerHTML = '<div class="empty"><h2>Network error</h2><p>Check your connection and try again.</p></div>';
                renderChips();    /* ensure chips always reflect current state even on failure */
                updateClearButton();
            });
    }
    function debouncedApply() {
        clearTimeout(fetchTimer);
        fetchTimer = setTimeout(function() {
            writeState(state, false);
            fetchProducts();
        }, 450);
    }
    function applyChange() {
        clearTimeout(fetchTimer);
        writeState(state, false);
        fetchProducts();
    }

    /* ---------- Top controls ---------- */
    $sort.value = state.sort;
    $perPage.value = String(state.per_page);
    $sort.addEventListener('change', function() { state.sort = $sort.value; state.page = 1; applyChange(); });
    $perPage.addEventListener('change', function() { state.per_page = Number($perPage.value) || 24; state.page = 1; applyChange(); });

    /* ---------- Header search → live shop search (overrides index.php's redirect on this page) ---------- */
    if ($headerSearch) {
        $headerSearch.value = state.q;
        var searchTimer = null;
        $headerSearch.addEventListener('input', function() {
            clearTimeout(searchTimer);
            var v = $headerSearch.value.trim();
            searchTimer = setTimeout(function() {
                state.q = v; state.page = 1;
                applyChange();
            }, 350);
        });
        $headerSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimer);
                state.q = $headerSearch.value.trim(); state.page = 1;
                applyChange();
            }
        });
        /* signal to global handler that we manage the input here */
        $headerSearch.dataset.shopBound = '1';
        /* autocomplete may emit a custom submit event when user picks "Search for X" */
        $headerSearch.addEventListener('wlk:search-submit', function(e) {
            clearTimeout(searchTimer);
            state.q = (e.detail && e.detail.q) || '';
            state.page = 1;
            applyChange();
        });
    }

    /* ---------- Mobile drawer ---------- */
    var $overlay = document.getElementById('filterOverlay');
    var $drawer = document.getElementById('filterDrawer');
    document.getElementById('openFilters').addEventListener('click', function() {
        renderFiltersInto($drawerBody, 'drawer');
        $overlay.classList.add('is-open'); $drawer.classList.add('is-open'); document.body.style.overflow = 'hidden';
    });
    function closeDrawer() {
        $overlay.classList.remove('is-open'); $drawer.classList.remove('is-open'); document.body.style.overflow = '';
    }
    document.getElementById('closeFilters').addEventListener('click', closeDrawer);
    $overlay.addEventListener('click', closeDrawer);
    document.getElementById('drawerApply').addEventListener('click', closeDrawer);
    document.getElementById('drawerReset').addEventListener('click', function() {
        state.categories = []; state.brands = []; state.min = ''; state.max = ''; state.in_stock = false;
        state.page = 1; applyChange();
        renderFiltersInto($filters, 'shopFilters'); renderFiltersInto($drawerBody, 'drawer');
    });

    /* ---------- Browser back/forward ---------- */
    window.addEventListener('popstate', function() {
        state = readState();
        $sort.value = state.sort;
        $perPage.value = String(state.per_page);
        if ($headerSearch) $headerSearch.value = state.q;
        renderFiltersInto($filters, 'shopFilters');
        fetchProducts();
    });

    /* ---------- Recently viewed ---------- */
    function renderRecentlyViewed() {
        var raw = '';
        try { raw = localStorage.getItem('wlk_recent') || '[]'; } catch (_) {}
        var list = [];
        try { list = JSON.parse(raw); } catch (_) {}
        if (!Array.isArray(list) || list.length === 0) return;
        var section = document.getElementById('rvSection');
        var rail = document.getElementById('rvRail');
        rail.innerHTML = list.slice(0, 8).map(function(p) {
            var img = p.image_url || 'assets/images/brand/logo-watercolorlk.png';
            return '<a class="rv-card" href="' + escapeHtml(productHref(p)) + '">' +
                '<div class="media"><img loading="lazy" src="' + escapeHtml(img) + '" alt="" onerror="this.onerror=null;this.src=\'assets/images/brand/logo-watercolorlk.png\';"></div>' +
                '<div class="body"><div class="nm">' + escapeHtml(p.name || '') + '</div><div class="pr">' + formatLKR(p.price) + '</div></div>' +
            '</a>';
        }).join('');
        section.removeAttribute('hidden');
    }

    /* ---------- First paint ---------- */
    renderFiltersInto($filters, 'shopFilters');
    renderProducts(INITIAL_PRODUCTS, INITIAL_TOTAL);
    renderPagination(INITIAL_TOTAL);
    updateHeading(INITIAL_TOTAL);
    renderChips();
    updateClearButton();
    renderRecentlyViewed();
    writeState(state, true); /* normalize URL */
})();
</script>
</body>
</html>
