<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$repo = new ProductRepository(appDb());
$initialQuery = trim((string)($_GET['q'] ?? ''));
$products = $repo->listProducts($initialQuery, 24, 0);
$initialProductsJson = json_encode($products, JSON_UNESCAPED_SLASHES);

$flashDeals = [];
$bestSellers = [];
$onSale = [];
$categoryCounts = ['Brushes' => 0, 'Papers' => 0, 'Paints' => 0, 'Sketchbooks' => 0, 'Accessories' => 0];
$topReviews = [];
$reviewStats = ['count' => 0, 'avg' => 4.9];

try {
    $flashDeals = $repo->listFlashDeals(8);
} catch (Throwable $e) { $flashDeals = []; }

try {
    $bestSellers = $repo->listBestSellers(10);
} catch (Throwable $e) { $bestSellers = []; }

try {
    $allDeals = array_filter($flashDeals, static function (array $row): bool {
        $orig = (float)($row['original_price'] ?? 0);
        $now = (float)($row['price'] ?? 0);
        return $orig > 0 && $now > 0 && $now < $orig;
    });
    $onSale = array_slice(array_values($allDeals), 0, 8);
} catch (Throwable $e) { $onSale = []; }

try {
    $categoryCounts = $repo->listCategoriesWithCounts([
        'Brushes' => 'brush',
        'Papers' => 'paper',
        'Paints' => 'paint',
        'Sketchbooks' => 'sketch',
        'Accessories' => 'access',
    ]);
} catch (Throwable $e) {
    $categoryCounts = ['Brushes' => 0, 'Papers' => 0, 'Paints' => 0, 'Sketchbooks' => 0, 'Accessories' => 0];
}

try {
    $reviewRepo = new Repositories\GoogleReviewRepository(appDb());
    $allTop = $reviewRepo->getByMinRating(5.0, 12);
    $filtered = array_values(array_filter($allTop, static function (array $r): bool {
        return trim((string)($r['review_text'] ?? '')) !== '';
    }));
    $topReviews = array_slice($filtered, 0, 6);
    $reviewStats['count'] = $reviewRepo->getCount();
} catch (Throwable $e) {
    $topReviews = [];
}

$scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
$baseHref = rtrim($scriptDir, '/');
if ($baseHref === '') {
    $baseHref = '/';
} else {
    $baseHref .= '/';
}

function slugifyProductTitle(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    return trim($value, '-');
}

function productUrl(string $slug, string $name, int $erpId): string
{
    $slug = trim($slug);
    if ($slug === '' || preg_match('/^product-\d+$/i', $slug) === 1) {
        $slug = slugifyProductTitle($name);
    }

    if ($slug !== '') {
        return 'product/' . rawurlencode($slug);
    }

    return 'product/' . rawurlencode('product-' . $erpId);
}

function renderDealCard(array $product, ?int $rank = null): string
{
    $url = productUrl((string)($product['slug'] ?? ''), (string)($product['display_name'] ?? $product['name'] ?? ''), (int)$product['erp_product_id']);
    $img = (string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png');
    $name = (string)($product['display_name'] ?? '');
    $price = (float)($product['price'] ?? 0);
    $orig = isset($product['original_price']) ? (float)$product['original_price'] : $price * 1.12;
    $stock = (float)($product['stock_qty'] ?? 0);
    $hasDiscount = $orig > $price && $price > 0;
    $discountPct = $hasDiscount ? (int)round((($orig - $price) / $orig) * 100) : 12;
    $saved = max(0, $orig - $price);
    $sold = 80 + (((int)$product['erp_product_id']) % 220);
    $rating = '4.' . (5 + (((int)$product['erp_product_id']) % 5));
    $scarcityCap = 30;
    $scarcityPct = $stock > 0 ? max(8, min(96, 100 - ($stock / $scarcityCap) * 100)) : 100;

    $ribbon = '';
    if ($rank !== null) {
        $ribbon = '<span class="rank-ribbon">#' . (int)$rank . ' Best Seller</span>';
    }

    $stockNote = '';
    if ($stock <= 0) {
        $stockNote = '<div class="scarcity-note out">Sold out</div>';
    } elseif ($stock <= 7) {
        $stockNote = '<div class="scarcity-note hot">' . htmlspecialchars('Only ' . (int)$stock . ' left') . '</div>';
    } else {
        $stockNote = '<div class="scarcity-note">Selling fast</div>';
    }

    $html  = '<a class="deal-card" href="' . htmlspecialchars($url) . '">';
    $html .= '<div class="deal-media">';
    $html .= '<span class="discount-flag">-' . $discountPct . '%</span>';
    $html .= $ribbon;
    $html .= '<img loading="lazy" src="' . htmlspecialchars($img) . '" alt="' . htmlspecialchars($name) . '">';
    $html .= '</div>';
    $html .= '<div class="deal-body">';
    $html .= '<h3 class="deal-name">' . htmlspecialchars($name) . '</h3>';
    $html .= '<div class="deal-price-row">';
    $html .= '<span class="deal-price">LKR ' . number_format($price, 0) . '</span>';
    if ($hasDiscount) {
        $html .= '<span class="deal-price-old">LKR ' . number_format($orig, 0) . '</span>';
    }
    $html .= '</div>';
    if ($saved > 0) {
        $html .= '<div class="deal-save">You save LKR ' . number_format($saved, 0) . '</div>';
    }
    $html .= '<div class="deal-meta"><span class="stars" aria-label="Rating">★★★★★</span><span class="meta-dot">' . $rating . '</span><span class="meta-dot">' . $sold . '+ sold</span></div>';
    $html .= '<div class="scarcity-bar"><span style="width:' . (int)$scarcityPct . '%"></span></div>';
    $html .= $stockNote;
    $html .= '</div>';
    $html .= '</a>';

    return $html;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="<?= htmlspecialchars($baseHref, ENT_QUOTES) ?>">
    <title>Watercolor.LK | Sri Lanka's #1 Watercolor & Art Supplies Store</title>
    <meta name="description" content="Shop authentic watercolor paints, brushes, papers and sketchbooks. Free island-wide delivery, cash on delivery and trusted by 1,200+ Sri Lankan artists.">
    <meta name="theme-color" content="#1b2d4f">
    <link rel="icon" type="image/webp" href="assets/images/brand/favicon-watercolorlk.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-navy: #1b2d4f;
            --brand-navy-deep: #10203a;
            --paper: #faf8f5;
            --surface: #ffffff;
            --surface-2: #fdf8f1;
            --line: #e7ddd2;
            --text: #1a1a1a;
            --muted: #6b6b6b;
            --amber: #e8760a;
            --amber-deep: #c4600a;
            --rose: #c4705a;
            --danger: #c0392b;
            --success: #2d7a4f;
            --gold: #d8a03d;
            --accent-fire: #e63946;
            --accent-mint: #2d7a4f;
            --ribbon-gold: #f4b740;
            --radius-sm: 10px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --shadow-sm: 0 10px 24px rgba(17, 31, 56, 0.08);
            --shadow-md: 0 16px 40px rgba(17, 31, 56, 0.10);
            --shadow-lg: 0 24px 60px rgba(17, 31, 56, 0.14);
            --grad-fire: linear-gradient(135deg, #ff5b3a 0%, #e63946 60%, #b8232f 100%);
            --grad-navy: linear-gradient(135deg, #1b2d4f 0%, #243a66 100%);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(196, 112, 90, .15), transparent 32%),
                radial-gradient(circle at top right, rgba(232, 118, 10, .08), transparent 28%),
                linear-gradient(180deg, #fffdfa 0%, var(--paper) 42%, #f5eee6 100%);
            font: 400 17px/1.7 'Source Sans 3', 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        .wrap { width: min(calc(100% - 32px), 1240px); margin: 0 auto; }

        /* ===== Promo bar ===== */
        .promo-bar {
            background: var(--grad-fire);
            color: #fff;
            font: 700 .82rem/1.2 'Montserrat', sans-serif;
            letter-spacing: .03em;
            position: relative;
            overflow: hidden;
        }
        .promo-bar.is-hidden { display: none; }
        .promo-track {
            display: flex;
            align-items: center;
            gap: 22px;
            padding: 9px 44px 9px 18px;
            white-space: nowrap;
            animation: promoSlide 28s linear infinite;
        }
        .promo-track span { display: inline-flex; align-items: center; gap: 8px; }
        .promo-track .dot { width: 5px; height: 5px; border-radius: 50%; background: rgba(255,255,255,.65); display: inline-block; }
        .promo-close {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            background: rgba(0,0,0,.18); color: #fff; border: 0; width: 24px; height: 24px;
            border-radius: 50%; cursor: pointer; font-size: 14px; line-height: 1;
        }
        @keyframes promoSlide {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .promo-track { animation: none; }
        }

        /* ===== Header ===== */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 40;
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(16px);
            background: rgba(250, 248, 245, .94);
            box-shadow: 0 8px 24px rgba(17, 31, 56, 0.08);
        }
        .header-inner {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 14px 0;
        }
        .brand {
            min-width: 240px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .logo { height: 42px; width: auto; display: block; }
        .brand-sub {
            color: #43516d;
            font: 700 .79rem/1.2 'Montserrat', sans-serif;
            letter-spacing: .02em;
        }
        .header-search { flex: 1; position: relative; }
        .header-search-input {
            width: 100%;
            border: 1px solid #d7c9b8;
            outline: none;
            border-radius: 999px;
            padding: 17px 18px 17px 48px;
            background: #fff;
            color: #1f2a3d;
            font: 600 1.02rem/1.2 'Source Sans 3', sans-serif;
            transition: border-color .15s, box-shadow .15s;
        }
        .header-search-input::placeholder { color: #7a828f; }
        .header-search-input:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(232,118,10,.14); }
        .header-search > .search-ico { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #6d7383; width: 20px; height: 20px; }
        .header-actions { display: flex; align-items: center; gap: 10px; }
        .icon-btn {
            position: relative;
            width: 46px;
            height: 46px;
            border: 1px solid var(--line);
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--brand-navy);
            background: #fff;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
            transition: transform .15s, box-shadow .15s;
        }
        .icon-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .icon-btn svg { width: 21px; height: 21px; }
        .icon-btn .pip {
            position: absolute; top: -2px; right: -2px;
            background: var(--accent-fire); color: #fff;
            font: 700 10px/1 'Montserrat', sans-serif;
            min-width: 18px; height: 18px; padding: 0 5px;
            border-radius: 999px; display: inline-flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(230,57,70,.4);
        }
        .trust-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 999px;
            background: rgba(232, 118, 10, .1); color: #a44d05;
            font: 700 .72rem/1 'Montserrat', sans-serif; letter-spacing: .04em;
        }
        .trust-pill .stars { color: var(--gold); letter-spacing: .04em; }

        /* ===== Hero ===== */
        .hero {
            margin: 22px 0 26px;
            background:
                radial-gradient(circle at 90% 20%, rgba(232,118,10,.18), transparent 45%),
                radial-gradient(circle at 10% 90%, rgba(196,112,90,.22), transparent 50%),
                linear-gradient(135deg, #fffdfa 0%, #fdf3e6 100%);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 36px 36px;
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 28px;
            align-items: center;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            position: relative;
        }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 14px; border-radius: 999px;
            background: rgba(27,45,79,.08); color: var(--brand-navy);
            font: 700 .76rem/1 'Montserrat', sans-serif; letter-spacing: .08em;
            text-transform: uppercase;
        }
        .hero h1 {
            margin: 14px 0 12px;
            font: 700 clamp(1.9rem, 3.4vw, 2.7rem)/1.12 'Playfair Display', serif;
            color: var(--brand-navy);
            letter-spacing: -.01em;
        }
        .hero h1 em { color: var(--amber-deep); font-style: normal; }
        .hero p.lead {
            margin: 0 0 22px;
            color: #485168;
            font: 500 1.04rem/1.55 'Source Sans 3', sans-serif;
            max-width: 520px;
        }
        .hero-cta-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 22px; }
        .btn-primary, .btn-secondary {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 14px 22px; border-radius: 999px;
            font: 700 .96rem/1 'Montserrat', sans-serif; letter-spacing: .02em;
            text-decoration: none; cursor: pointer; border: 0;
            transition: transform .15s, box-shadow .15s;
        }
        .btn-primary {
            background: var(--grad-fire); color: #fff;
            box-shadow: 0 12px 26px rgba(230,57,70,.32);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(230,57,70,.4); }
        .btn-secondary {
            background: #fff; color: var(--brand-navy);
            border: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
        }
        .btn-secondary:hover { transform: translateY(-2px); border-color: var(--brand-navy); }
        .hero-trust {
            display: flex; flex-wrap: wrap; gap: 18px; align-items: center;
            color: #4e5b73; font: 600 .88rem/1.2 'Source Sans 3', sans-serif;
        }
        .hero-trust .stars { color: var(--gold); letter-spacing: .06em; font-size: 1.05rem; }
        .hero-trust .sep { width: 1px; height: 18px; background: var(--line); }
        .hero-art {
            position: relative;
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .hero-art img {
            width: 100%; aspect-ratio: 1; object-fit: contain;
            background: rgba(255,255,255,.75);
            border-radius: var(--radius-md);
            padding: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(255,255,255,.8);
        }
        .hero-art img:nth-child(1) { transform: translateY(8px); }
        .hero-art img:nth-child(2) { transform: translateY(-12px); }
        .hero-art img:nth-child(3) { transform: translateY(8px); }

        /* ===== USP strip ===== */
        .usp-strip {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 12px; margin-bottom: 28px;
        }
        .usp-tile {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px; border-radius: var(--radius-md);
            background: #fff; border: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
        }
        .usp-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: rgba(232,118,10,.12); color: var(--amber-deep);
            display: inline-flex; align-items: center; justify-content: center;
            flex: 0 0 42px;
        }
        .usp-icon svg { width: 22px; height: 22px; }
        .usp-tile strong { display: block; color: var(--brand-navy); font: 700 .92rem/1.2 'Montserrat', sans-serif; }
        .usp-tile span { color: #6e7689; font: 500 .8rem/1.3 'Source Sans 3', sans-serif; }

        /* ===== Section heads ===== */
        .section { margin: 0 0 36px; }
        .section-head {
            display: flex; align-items: end; justify-content: space-between;
            gap: 14px; margin: 0 0 14px; flex-wrap: wrap;
        }
        .section-title {
            margin: 0;
            font: 700 1.35rem/1.15 'Playfair Display', serif;
            color: var(--brand-navy);
            letter-spacing: -.01em;
        }
        .section-title small {
            display: block; margin-top: 4px;
            color: #6e7689; font: 500 .82rem/1.2 'Source Sans 3', sans-serif;
            letter-spacing: 0;
        }
        .section-link {
            color: var(--amber-deep); text-decoration: none;
            font: 700 .85rem/1 'Montserrat', sans-serif; letter-spacing: .04em;
        }
        .section-link:hover { text-decoration: underline; }

        /* ===== Flash deals ===== */
        .flash-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; padding: 16px 22px; border-radius: var(--radius-md);
            background: var(--grad-fire); color: #fff;
            margin-bottom: 14px;
            box-shadow: 0 12px 30px rgba(230,57,70,.25);
            flex-wrap: wrap;
        }
        .flash-title {
            display: flex; align-items: center; gap: 10px;
            font: 700 1.2rem/1 'Playfair Display', serif;
        }
        .flash-title .bolt {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; background: rgba(255,255,255,.18);
            border-radius: 8px;
        }
        .flash-title small {
            display: block; font: 500 .78rem/1 'Source Sans 3', sans-serif;
            opacity: .85; margin-top: 4px;
        }
        .countdown { display: flex; align-items: center; gap: 6px; }
        .countdown .label { font: 600 .76rem/1 'Montserrat', sans-serif; opacity: .9; margin-right: 6px; }
        .cd-cell {
            min-width: 38px; padding: 6px 8px; border-radius: 8px;
            background: rgba(0,0,0,.28); font: 700 1rem/1 'Montserrat', sans-serif;
            text-align: center;
        }
        .cd-sep { font-weight: 700; opacity: .6; }

        /* ===== Rails ===== */
        .rail {
            display: grid; grid-auto-flow: column;
            grid-auto-columns: minmax(220px, 1fr);
            gap: 14px; padding: 4px 4px 16px;
            overflow-x: auto; scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        .rail::-webkit-scrollbar { height: 8px; }
        .rail::-webkit-scrollbar-thumb { background: rgba(27,45,79,.18); border-radius: 999px; }
        .rail > * { scroll-snap-align: start; }

        /* ===== Deal card ===== */
        .deal-card {
            display: grid; grid-template-rows: auto 1fr;
            background: #fff; border: 1px solid var(--line);
            border-radius: var(--radius-md); overflow: hidden;
            text-decoration: none; color: inherit;
            box-shadow: var(--shadow-sm);
            transition: transform .16s ease, box-shadow .16s ease;
            position: relative;
        }
        .deal-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .deal-media {
            position: relative; aspect-ratio: 1/1; background: #fff;
            display: flex; align-items: center; justify-content: center; padding: 14px;
        }
        .deal-media img { width: 100%; height: 100%; object-fit: contain; border-radius: 12px; }
        .discount-flag {
            position: absolute; top: 10px; left: 10px;
            background: var(--grad-fire); color: #fff;
            font: 700 .82rem/1 'Montserrat', sans-serif;
            padding: 6px 9px; border-radius: 8px;
            box-shadow: 0 6px 14px rgba(230,57,70,.35);
        }
        .rank-ribbon {
            position: absolute; top: 10px; right: 10px;
            background: linear-gradient(135deg, #f4b740, #d8a03d);
            color: #2a1f00;
            font: 700 .7rem/1 'Montserrat', sans-serif;
            padding: 6px 9px; border-radius: 8px; letter-spacing: .04em;
            box-shadow: 0 6px 14px rgba(244,183,64,.35);
        }
        .deal-body { padding: 14px; display: grid; gap: 6px; align-content: start; }
        .deal-name {
            margin: 0; color: #22314d;
            font: 700 .98rem/1.25 'Source Sans 3', sans-serif;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            min-height: 2.5em;
        }
        .deal-price-row { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
        .deal-price { color: var(--accent-fire); font: 800 1.25rem/1 'Source Sans 3', sans-serif; }
        .deal-price-old { color: #9a8e81; text-decoration: line-through; font-size: .9rem; }
        .deal-save { color: var(--accent-mint); font: 700 .8rem/1 'Montserrat', sans-serif; }
        .deal-meta {
            display: flex; align-items: center; gap: 8px;
            color: var(--muted); font: 500 .78rem/1 'Source Sans 3', sans-serif;
        }
        .deal-meta .stars { color: var(--gold); letter-spacing: .06em; font-size: .85rem; }
        .meta-dot { position: relative; padding-left: 10px; }
        .meta-dot::before { content: ""; position: absolute; left: 2px; top: 50%; transform: translateY(-50%); width: 3px; height: 3px; border-radius: 50%; background: #c4bdb1; }
        .scarcity-bar {
            height: 6px; background: rgba(27,45,79,.08); border-radius: 999px; overflow: hidden;
            margin-top: 4px;
        }
        .scarcity-bar > span {
            display: block; height: 100%;
            background: linear-gradient(90deg, #2d7a4f 0%, #e8760a 60%, #e63946 100%);
            border-radius: 999px;
        }
        .scarcity-note {
            font: 700 .76rem/1.2 'Montserrat', sans-serif;
            color: var(--amber-deep);
        }
        .scarcity-note.hot { color: var(--accent-fire); }
        .scarcity-note.out { color: #6b6b6b; }

        /* ===== Categories ===== */
        .category-grid { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: 14px; }
        .category-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 18px 12px 14px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            text-decoration: none;
            transition: transform .16s, box-shadow .16s, border-color .16s;
            position: relative;
        }
        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: rgba(232,118,10,.4);
        }
        .category-icon { width: 78px; height: 78px; object-fit: contain; margin: 0 auto 10px; }
        .category-card strong { display: block; color: var(--brand-navy); font: 700 .92rem/1.1 'Montserrat', sans-serif; }
        .category-card span { display: block; margin-top: 4px; color: #8a8275; font: 500 .76rem/1.2 'Source Sans 3', sans-serif; }

        /* ===== Activity ticker ===== */
        .activity-toast {
            position: fixed; left: 16px; bottom: 96px;
            display: none; align-items: center; gap: 10px;
            padding: 10px 14px; border-radius: 999px;
            background: rgba(27,45,79,.96); color: #fff;
            box-shadow: var(--shadow-lg);
            font: 600 .82rem/1.2 'Source Sans 3', sans-serif;
            z-index: 38; max-width: 320px;
            animation: toastIn .35s ease;
        }
        .activity-toast.is-visible { display: inline-flex; }
        .activity-toast .av {
            width: 26px; height: 26px; border-radius: 50%;
            background: var(--amber); color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font: 700 .78rem/1 'Montserrat', sans-serif;
        }
        .activity-toast small { display: block; opacity: .7; font-weight: 500; margin-top: 2px; }
        @keyframes toastIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* ===== "Just for You" grid ===== */
        .meta-row {
            margin: 0 0 12px;
            color: #4e5b73;
            font: 700 .86rem/1.3 'Montserrat', sans-serif;
            letter-spacing: .02em;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(225px, 1fr));
            gap: 18px;
            padding: 6px 0 18px;
        }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow-sm);
            transition: transform .16s ease, box-shadow .16s ease;
        }
        .card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .media {
            position: relative; background: #fff;
            aspect-ratio: 1/1; display: flex; align-items: center; justify-content: center; padding: 16px;
        }
        .img {
            width: 100%; object-fit: cover;
            background: #f7f7f7; display: block; border-radius: 14px;
        }
        .card-badges { position: absolute; top: 12px; left: 12px; display: flex; gap: 6px; flex-wrap: wrap; }
        .body { padding: 16px; display: grid; gap: 8px; align-content: start; min-height: 205px; }
        .name {
            margin: 0; color: #22314d;
            font: 700 17px/1.24 'Source Sans 3', sans-serif;
            min-height: 48px;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .price-row { display: flex; align-items: end; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .price { color: var(--brand-navy); font: 700 1.55rem/1 'Source Sans 3', sans-serif; }
        .deal-row { display: flex; align-items: baseline; gap: 10px; }
        .price-old { color: #9a8e81; text-decoration: line-through; font-size: 1rem; }
        .price-off { color: var(--danger); font: 700 .88rem/1 'Montserrat', sans-serif; }
        .rating { margin-top: 8px; display: flex; gap: 8px; align-items: center; color: var(--muted); font-size: .82rem; }
        .stars { color: var(--gold); letter-spacing: .08em; }
        .stock { margin-top: 4px; font: 600 13px/1.2 'Montserrat', sans-serif; }
        .stock.ok { color: var(--success); }
        .stock.no { color: var(--danger); }
        .badge {
            display: inline-block;
            font: 700 11px/1 'Montserrat', sans-serif;
            background: rgba(27, 45, 79, .1);
            color: var(--brand-navy);
            border-radius: 999px;
            padding: 6px 9px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .badge.sale { background: rgba(192,57,43,.1); color: var(--danger); }
        .badge.stock { background: rgba(45,122,79,.12); color: var(--success); }
        .badge.low { background: rgba(232,118,10,.12); color: #a44d05; }
        .empty {
            grid-column: 1 / -1;
            background: rgba(255, 255, 255, .86);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 26px;
            text-align: center;
            color: #525e74;
            font: 600 16px/1.4 'Source Sans 3', sans-serif;
        }

        /* ===== Suggestions ===== */
        .suggestions {
            margin-top: 6px; border: 1px solid #d6cab8; border-radius: 14px;
            background: #fff; display: none; overflow: hidden;
            box-shadow: 0 14px 36px rgba(17, 31, 56, .12);
            position: absolute; left: 0; right: 0; z-index: 30;
        }
        .suggestion {
            padding: 11px 13px; border-bottom: 1px solid #f1ecdf;
            cursor: pointer; font: 600 14px/1.3 'Source Sans 3', sans-serif; color: #273144;
        }
        .suggestion:hover { background: #fdf8f1; }
        .suggestion:last-child { border-bottom: 0; }

        /* ===== Reviews strip ===== */
        .reviews-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
        .review-card {
            background: #fff; border: 1px solid var(--line);
            border-radius: var(--radius-md); padding: 18px;
            box-shadow: var(--shadow-sm);
            display: grid; gap: 10px; align-content: start;
        }
        .review-head { display: flex; align-items: center; gap: 10px; }
        .review-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, #243a66, #1b2d4f);
            color: #fff; display: inline-flex; align-items: center; justify-content: center;
            font: 700 .9rem/1 'Montserrat', sans-serif;
            object-fit: cover;
        }
        .review-name { color: var(--brand-navy); font: 700 .94rem/1.1 'Montserrat', sans-serif; }
        .review-name small { display: block; color: #8a8275; font-weight: 500; margin-top: 2px; }
        .review-text { color: #3e485e; font: 500 .94rem/1.55 'Source Sans 3', sans-serif;
            display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden; }
        .review-foot { display: flex; align-items: center; justify-content: space-between; }
        .review-foot .stars { color: var(--gold); letter-spacing: .06em; }
        .google-pill {
            display: inline-flex; align-items: center; gap: 6px;
            color: #6b6b6b; font: 600 .76rem/1 'Montserrat', sans-serif;
        }
        .reviews-cta {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 18px; border-radius: var(--radius-md);
            background: #fff; border: 1px solid var(--line);
            box-shadow: var(--shadow-sm); margin-top: 14px; flex-wrap: wrap;
        }
        .reviews-cta strong { color: var(--brand-navy); font: 700 1rem/1.2 'Montserrat', sans-serif; }
        .reviews-cta .stars { color: var(--gold); font-size: 1.1rem; letter-spacing: .06em; }

        /* ===== Newsletter ===== */
        .newsletter {
            margin: 14px 0 30px;
            padding: 28px;
            border-radius: var(--radius-lg);
            background: var(--grad-navy);
            color: #fff;
            display: grid; grid-template-columns: 1.2fr 1fr;
            gap: 24px; align-items: center;
            box-shadow: var(--shadow-md);
        }
        .newsletter h3 { margin: 0 0 8px; font: 700 1.35rem/1.15 'Playfair Display', serif; }
        .newsletter p { margin: 0; opacity: .82; font-size: .96rem; }
        .newsletter form {
            display: flex; gap: 8px; background: rgba(255,255,255,.1);
            border-radius: 999px; padding: 6px;
            border: 1px solid rgba(255,255,255,.2);
        }
        .newsletter input {
            flex: 1; background: transparent; border: 0; outline: 0;
            padding: 12px 16px; color: #fff;
            font: 500 .96rem/1 'Source Sans 3', sans-serif;
        }
        .newsletter input::placeholder { color: rgba(255,255,255,.6); }
        .newsletter button {
            background: var(--amber); color: #fff; border: 0;
            padding: 12px 20px; border-radius: 999px; cursor: pointer;
            font: 700 .9rem/1 'Montserrat', sans-serif; letter-spacing: .03em;
        }
        .newsletter button:hover { background: var(--amber-deep); }

        /* ===== Footer ===== */
        .site-footer {
            background: var(--brand-navy-deep); color: rgba(255,255,255,.82);
            padding: 44px 0 24px; margin-top: 14px;
        }
        .footer-grid {
            display: grid; grid-template-columns: 1.4fr repeat(3, 1fr); gap: 30px;
            padding-bottom: 26px; border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .footer-brand .logo-foot { height: 38px; margin-bottom: 12px; }
        .footer-brand p { margin: 0 0 14px; font-size: .92rem; line-height: 1.6; opacity: .75; }
        .footer-socials { display: flex; gap: 8px; }
        .footer-socials a {
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(255,255,255,.08); color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; transition: background .15s;
        }
        .footer-socials a:hover { background: var(--amber); }
        .footer-socials svg { width: 16px; height: 16px; }
        .footer-col h4 {
            margin: 0 0 14px; color: #fff;
            font: 700 .82rem/1 'Montserrat', sans-serif;
            letter-spacing: .1em; text-transform: uppercase;
        }
        .footer-col ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
        .footer-col a { color: rgba(255,255,255,.72); text-decoration: none; font-size: .92rem; }
        .footer-col a:hover { color: var(--amber); }
        .footer-bottom {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; padding-top: 22px; flex-wrap: wrap;
            font-size: .82rem; opacity: .7;
        }
        .pay-icons { display: flex; gap: 10px; align-items: center; opacity: .85; }
        .pay-pill {
            background: #fff; color: #1b2d4f;
            padding: 5px 10px; border-radius: 6px;
            font: 700 .72rem/1 'Montserrat', sans-serif; letter-spacing: .05em;
        }

        /* ===== Mobile bottom nav ===== */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: rgba(255,255,255,.97); backdrop-filter: blur(14px);
            border-top: 1px solid var(--line);
            display: none; grid-template-columns: repeat(4, 1fr);
            padding: 8px 0 calc(8px + env(safe-area-inset-bottom));
            z-index: 39;
            box-shadow: 0 -8px 24px rgba(17,31,56,.08);
        }
        .bottom-nav a {
            display: flex; flex-direction: column; align-items: center; gap: 3px;
            color: var(--brand-navy); text-decoration: none;
            font: 700 .68rem/1 'Montserrat', sans-serif;
            padding: 6px 4px;
        }
        .bottom-nav a svg { width: 22px; height: 22px; }

        /* ===== Responsive ===== */
        @media (max-width: 980px) {
            .hero { grid-template-columns: 1fr; padding: 28px 22px; }
            .hero-art { grid-template-columns: repeat(3, 1fr); max-width: 380px; margin: 6px auto 0; }
            .usp-strip { grid-template-columns: repeat(2, 1fr); }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 720px) {
            .wrap { width: min(calc(100% - 24px), 1240px); }
            .header-inner { padding: 12px 0; flex-wrap: wrap; }
            .header-search, .brand { min-width: 100%; }
            .brand { flex-direction: row; align-items: center; gap: 12px; }
            .brand-sub { display: none; }
            .header-actions { margin-left: auto; }
            .hero { padding: 22px 18px; }
            .hero h1 { font-size: 1.7rem; }
            .category-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .category-icon { width: 56px; height: 56px; }
            .flash-head { padding: 14px 16px; }
            .newsletter { grid-template-columns: 1fr; padding: 22px; }
            .footer-grid { grid-template-columns: 1fr; gap: 22px; }
            .bottom-nav { display: grid; }
            body { padding-bottom: 78px; }
            .activity-toast { bottom: 88px; left: 12px; right: 12px; max-width: none; }
        }
        @media (max-width: 460px) {
            .usp-strip { grid-template-columns: 1fr; }
            .category-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<?php
$avgRating = number_format((float)$reviewStats['avg'], 1);
$reviewCount = (int)$reviewStats['count'];
$reviewCountLabel = $reviewCount > 0 ? number_format($reviewCount) . '+ Google reviews' : '1,200+ happy artists';
?>
<div id="promoBar" class="promo-bar">
    <div class="promo-track">
        <span><strong>FREE delivery</strong> on orders over LKR 5,000</span><span class="dot"></span>
        <span><strong>Cash on Delivery</strong> island-wide</span><span class="dot"></span>
        <span>Flash deals end in <strong id="promoCountdown">--:--:--</strong></span><span class="dot"></span>
        <span>100% authentic stock - sourced from official brands</span><span class="dot"></span>
        <span><strong>FREE delivery</strong> on orders over LKR 5,000</span><span class="dot"></span>
        <span><strong>Cash on Delivery</strong> island-wide</span><span class="dot"></span>
        <span>Flash deals end in <strong>tonight</strong></span><span class="dot"></span>
        <span>100% authentic stock - sourced from official brands</span>
    </div>
    <button id="promoClose" class="promo-close" aria-label="Dismiss promo">&times;</button>
</div>

<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="index.php">
            <img class="logo" src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK">
            <span class="brand-sub">පටන් ගන්න! පාට කරන්න! ජිවිතය විදින්න!</span>
        </a>
        <div class="header-search">
            <svg class="search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
            <input id="search" class="header-search-input" placeholder="Search 1,000+ products, brands, categories..." autocomplete="off" value="<?= htmlspecialchars($initialQuery) ?>">
            <div id="suggestions" class="suggestions"></div>
        </div>
        <div class="header-actions">
            <span class="trust-pill" aria-hidden="true"><span class="stars">★</span> <?= $avgRating ?> / 5</span>
            <a class="icon-btn" href="admin/index.php" aria-label="Account">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            <a class="icon-btn" href="#" aria-label="Cart" id="cartButton">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H7"/></svg>
                <span class="pip" id="cartPip" hidden>0</span>
            </a>
        </div>
    </div>
</header>

<main class="wrap home-main">
    <!-- HERO -->
    <section class="hero" aria-label="Welcome">
        <div class="hero-copy">
            <span class="hero-eyebrow">Sri Lanka's #1 Watercolor Studio</span>
            <h1>Premium art supplies, <em>delivered with love</em> across the island.</h1>
            <p class="lead">From beginner kits to pro-grade pigments - shop authentic watercolor paints, brushes, papers and sketchbooks. Trusted by 1,200+ artists in Sri Lanka.</p>
            <div class="hero-cta-row">
                <a class="btn-primary" href="#flash-deals">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>
                    Shop today's deals
                </a>
                <a class="btn-secondary" href="#best-sellers">Best sellers &rarr;</a>
            </div>
            <div class="hero-trust">
                <span><span class="stars">★★★★★</span> <strong style="color:var(--brand-navy)"><?= $avgRating ?></strong>&nbsp;<?= htmlspecialchars($reviewCountLabel) ?></span>
                <span class="sep"></span>
                <span>🇱🇰 Free delivery > LKR 5,000</span>
                <span class="sep"></span>
                <span>💳 COD &amp; bank transfer</span>
            </div>
        </div>
        <div class="hero-art" aria-hidden="true">
            <img src="assets/images/mascots/watercolor-paints.webp" alt="Watercolor paints">
            <img src="assets/images/mascots/watercolor-brushes-1.webp" alt="Brushes">
            <img src="assets/images/mascots/watercolor-papers.webp" alt="Papers">
        </div>
    </section>

    <!-- USP STRIP -->
    <section class="usp-strip" aria-label="Why shop with us">
        <div class="usp-tile">
            <span class="usp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-6"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg></span>
            <div><strong>Free delivery</strong><span>Orders over LKR 5,000</span></div>
        </div>
        <div class="usp-tile">
            <span class="usp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3z"/><path d="M2 11h20"/></svg></span>
            <div><strong>Cash on Delivery</strong><span>Pay when it arrives</span></div>
        </div>
        <div class="usp-tile">
            <span class="usp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 6v6c0 5 3.5 9 8 10 4.5-1 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span>
            <div><strong>100% Authentic</strong><span>Sourced from official brands</span></div>
        </div>
        <div class="usp-tile">
            <span class="usp-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.4 8.4 0 0 1-1 4 8.5 8.5 0 0 1-7.6 4.5 8.4 8.4 0 0 1-4-1L3 21l2-5.4a8.4 8.4 0 0 1-1-4 8.5 8.5 0 0 1 4.5-7.5 8.4 8.4 0 0 1 4-1A8.5 8.5 0 0 1 21 11.5z"/></svg></span>
            <div><strong>WhatsApp support</strong><span>9 AM - 9 PM, all week</span></div>
        </div>
    </section>

    <!-- FLASH DEALS -->
    <?php if (!empty($flashDeals)): ?>
    <section class="section" id="flash-deals" aria-label="Flash deals">
        <div class="flash-head">
            <div class="flash-title">
                <span class="bolt"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg></span>
                <div>
                    Flash Deals
                    <small>Limited stock - prices reset at midnight</small>
                </div>
            </div>
            <div class="countdown" aria-label="Countdown">
                <span class="label">Ends in</span>
                <span class="cd-cell" id="cdH">00</span><span class="cd-sep">:</span>
                <span class="cd-cell" id="cdM">00</span><span class="cd-sep">:</span>
                <span class="cd-cell" id="cdS">00</span>
            </div>
        </div>
        <div class="rail">
            <?php foreach ($flashDeals as $product): ?>
                <?= renderDealCard($product) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- CATEGORIES -->
    <section class="section" id="categories" aria-label="Shop by category">
        <div class="section-head">
            <h2 class="section-title">Shop by category<small>Find your medium - we stock it all</small></h2>
        </div>
        <div class="category-grid">
            <?php
            $cats = [
                ['key' => 'Brushes', 'q' => 'brush', 'img' => 'assets/images/mascots/watercolor-brushes-1.webp'],
                ['key' => 'Papers', 'q' => 'paper', 'img' => 'assets/images/mascots/watercolor-papers.webp'],
                ['key' => 'Paints', 'q' => 'paint', 'img' => 'assets/images/mascots/watercolor-paints.webp'],
                ['key' => 'Sketchbooks', 'q' => 'sketch', 'img' => 'assets/images/mascots/watercolor-sktechbooks.webp'],
                ['key' => 'Accessories', 'q' => 'access', 'img' => 'assets/images/mascots/watercolor-assesries.webp'],
            ];
            foreach ($cats as $c):
                $count = (int)($categoryCounts[$c['key']] ?? 0);
            ?>
                <a class="category-card" href="?q=<?= urlencode($c['q']) ?>#products">
                    <img class="category-icon" src="<?= htmlspecialchars($c['img']) ?>" alt="<?= htmlspecialchars($c['key']) ?>" loading="lazy">
                    <strong><?= htmlspecialchars($c['key']) ?></strong>
                    <?php if ($count > 0): ?><span><?= $count ?> products</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- BEST SELLERS -->
    <?php if (!empty($bestSellers)): ?>
    <section class="section" id="best-sellers" aria-label="Best sellers">
        <div class="section-head">
            <h2 class="section-title">Best Sellers<small>Loved by 1,200+ Sri Lankan artists</small></h2>
            <a class="section-link" href="?q=#products">View all &rarr;</a>
        </div>
        <div class="rail">
            <?php foreach ($bestSellers as $i => $product): ?>
                <?= renderDealCard($product, $i + 1) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ON SALE -->
    <?php if (!empty($onSale)): ?>
    <section class="section" id="on-sale" aria-label="On sale">
        <div class="section-head">
            <h2 class="section-title">On Sale Now<small>Real discounts - while stocks last</small></h2>
            <a class="section-link" href="#flash-deals">All deals &rarr;</a>
        </div>
        <div class="rail">
            <?php foreach ($onSale as $product): ?>
                <?= renderDealCard($product) ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- JUST FOR YOU GRID -->
    <section class="section" id="products">
        <div class="section-head">
            <h2 class="section-title">Just for you<small>Hand-picked from our latest catalogue</small></h2>
        </div>
        <div class="meta-row" id="resultMeta">Showing latest products</div>
        <div id="grid" class="grid">
        <?php foreach ($products as $product): ?>
            <a class="card" href="<?= htmlspecialchars(productUrl((string)($product['slug'] ?? ''), (string)($product['display_name'] ?? $product['name'] ?? ''), (int)$product['erp_product_id'])) ?>">
                <div class="media">
                    <div class="card-badges">
                        <?php if ((float)$product['stock_qty'] <= 0): ?>
                            <span class="badge sale">Out of stock</span>
                        <?php elseif ((float)$product['stock_qty'] <= 7): ?>
                            <span class="badge low">Only <?= (int)$product['stock_qty'] ?> left</span>
                        <?php elseif ($product['badge'] !== ''): ?>
                            <span class="badge"><?= htmlspecialchars((string)$product['badge']) ?></span>
                        <?php else: ?>
                            <span class="badge">Featured</span>
                        <?php endif; ?>
                    </div>
                    <img class="img" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['display_name']) ?>" loading="lazy">
                </div>
                <div class="body">
                    <h3 class="name"><?= htmlspecialchars((string)$product['display_name']) ?></h3>
                    <div class="price-row">
                        <span class="price">LKR <?= number_format((float)$product['price'], 2) ?></span>
                    </div>
                    <div class="deal-row"><span class="price-old">LKR <?= number_format((float)$product['price'] * 1.12, 2) ?></span><span class="price-off">Save 12%</span></div>
                    <div class="rating"><span class="stars">★★★★★</span><span>4.9 rated</span><span>120+ sold</span></div>
                    <div class="stock <?= (float)$product['stock_qty'] > 0 ? 'ok' : 'no' ?>"><?= (float)$product['stock_qty'] > 0 ? ((float)$product['stock_qty'] <= 7 ? 'Only ' . (int)$product['stock_qty'] . ' left in stock' : 'In stock') : 'Out of stock' ?></div>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    </section>

    <!-- REVIEWS STRIP -->
    <?php if (!empty($topReviews)): ?>
    <section class="section" id="reviews" aria-label="Customer reviews">
        <div class="section-head">
            <h2 class="section-title">What artists are saying<small>Verified Google reviews from real customers</small></h2>
            <a class="section-link" href="#" target="_blank" rel="noopener">See all on Google &rarr;</a>
        </div>
        <div class="reviews-strip">
            <?php foreach ($topReviews as $review):
                $author = htmlspecialchars((string)($review['author'] ?? 'Customer'));
                $initial = strtoupper(mb_substr((string)($review['author'] ?? 'A'), 0, 1));
                $text = htmlspecialchars((string)($review['review_text'] ?? ''));
                $rating = (float)($review['rating'] ?? 5);
                $stars = str_repeat('★', (int)round($rating)) . str_repeat('☆', max(0, 5 - (int)round($rating)));
                $date = !empty($review['review_date']) ? date('M Y', strtotime((string)$review['review_date'])) : '';
                $localPath = trim((string)($review['profile_picture_local_path'] ?? ''));
                $remote = trim((string)($review['profile_picture_remote_url'] ?? ''));
                $avatar = $localPath !== '' ? $localPath : ($remote !== '' ? $remote : '');
            ?>
            <article class="review-card">
                <header class="review-head">
                    <?php if ($avatar !== ''): ?>
                        <img class="review-avatar" src="<?= htmlspecialchars($avatar) ?>" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'review-avatar',textContent:'<?= $initial ?>'}))">
                    <?php else: ?>
                        <span class="review-avatar"><?= $initial ?></span>
                    <?php endif; ?>
                    <div class="review-name"><?= $author ?><small><?= htmlspecialchars($date) ?> &middot; via Google</small></div>
                </header>
                <p class="review-text">"<?= $text ?>"</p>
                <footer class="review-foot">
                    <span class="stars" aria-label="<?= (int)$rating ?> of 5 stars"><?= $stars ?></span>
                    <span class="google-pill">
                        <svg width="14" height="14" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.6 2.4 30.2 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.8 6c1.9-5.5 7-9.7 13.6-9.7z"/><path fill="#4285F4" d="M46.5 24.6c0-1.6-.1-3.1-.4-4.6H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4 6.9-10 6.9-17.4z"/><path fill="#FBBC05" d="M10.4 28.7c-.5-1.4-.7-2.9-.7-4.7s.3-3.3.7-4.7l-7.8-6C.9 16.5 0 20.1 0 24s.9 7.5 2.6 10.7z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.8-5.8l-7.5-5.8c-2.1 1.4-4.8 2.3-8.3 2.3-6.5 0-12-4.4-13.9-10.3l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                        Google
                    </span>
                </footer>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="reviews-cta">
            <div>
                <strong><span class="stars">★★★★★</span> <?= $avgRating ?> / 5</strong>
                <div style="color:#6e7689;font:500 .85rem/1.3 'Source Sans 3',sans-serif;margin-top:4px;">Based on <?= htmlspecialchars($reviewCountLabel) ?></div>
            </div>
            <a class="btn-secondary" href="#" target="_blank" rel="noopener">Read all reviews</a>
        </div>
    </section>
    <?php endif; ?>

    <!-- NEWSLETTER -->
    <section class="newsletter" aria-label="Join our newsletter">
        <div>
            <h3>Get LKR 500 off your first order</h3>
            <p>Subscribe for early access to flash sales, new arrivals and artist tutorials.</p>
        </div>
        <form onsubmit="event.preventDefault(); alert('Thanks! We will be in touch soon.');" novalidate>
            <input type="email" required placeholder="your@email.com" aria-label="Email">
            <button type="submit">Get my coupon</button>
        </form>
    </section>
</main>

<!-- ACTIVITY TICKER -->
<div id="activityToast" class="activity-toast" role="status" aria-live="polite">
    <span class="av" id="actAv">A</span>
    <div><span id="actText">Someone just bought a product</span><small>Recent activity (sample)</small></div>
</div>

<!-- FOOTER -->
<footer class="site-footer">
    <div class="wrap">
        <div class="footer-grid">
            <div class="footer-brand">
                <img class="logo-foot" src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK">
                <p>Sri Lanka's trusted online store for premium watercolor and art supplies. පටන් ගන්න! පාට කරන්න! ජිවිතය විදින්න!</p>
                <div class="footer-socials">
                    <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H8v-3h2.4V9.4c0-2.4 1.4-3.7 3.6-3.7 1 0 2.1.2 2.1.2v2.3h-1.2c-1.2 0-1.5.7-1.5 1.5V12h2.6l-.4 3h-2.2v7A10 10 0 0 0 22 12z"/></svg></a>
                    <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg></a>
                    <a href="#" aria-label="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 3.5A10 10 0 0 0 4 16l-1 5 5-1A10 10 0 1 0 20 3.5zm-8 16a8 8 0 0 1-4-1.1l-3 .8.8-3A8 8 0 1 1 12 19.5z"/></svg></a>
                    <a href="#" aria-label="TikTok"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 3v3a4 4 0 0 0 4 4v3a7 7 0 0 1-4-1.3V16a5 5 0 1 1-5-5v3a2 2 0 1 0 2 2V3z"/></svg></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Shop</h4>
                <ul>
                    <li><a href="?q=paint">Paints</a></li>
                    <li><a href="?q=brush">Brushes</a></li>
                    <li><a href="?q=paper">Papers</a></li>
                    <li><a href="?q=sketch">Sketchbooks</a></li>
                    <li><a href="?q=access">Accessories</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Help</h4>
                <ul>
                    <li><a href="#">Track order</a></li>
                    <li><a href="#">Shipping &amp; delivery</a></li>
                    <li><a href="#">Returns &amp; refunds</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Contact us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="#">About Watercolor.LK</a></li>
                    <li><a href="#">Artist tutorials</a></li>
                    <li><a href="#">Wholesale &amp; schools</a></li>
                    <li><a href="#">Terms of service</a></li>
                    <li><a href="#">Privacy policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Watercolor.LK - Made with love in Sri Lanka.</span>
            <div class="pay-icons" aria-label="Payment methods">
                <span class="pay-pill">VISA</span>
                <span class="pay-pill">MASTER</span>
                <span class="pay-pill">COD</span>
                <span class="pay-pill">BANK</span>
            </div>
        </div>
    </div>
</footer>

<!-- MOBILE BOTTOM NAV -->
<nav class="bottom-nav" aria-label="Mobile navigation">
    <a href="index.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 11 9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>Home</a>
    <a href="#categories"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Categories</a>
    <a href="#" id="bottomNavSearch"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>Search</a>
    <a href="#" id="bottomNavCart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H7"/></svg>Cart</a>
</nav>

<script>
const input = document.getElementById('search');
const suggestions = document.getElementById('suggestions');
const grid = document.getElementById('grid');
const resultMeta = document.getElementById('resultMeta');
const cartButton = document.getElementById('cartButton');
const initialProducts = <?= $initialProductsJson ?: '[]' ?>;
let suggestTimer = null;
let searchTimer = null;
let requestId = 0;

cartButton.addEventListener('click', (event) => {
    event.preventDefault();
    alert('Cart module is coming next.');
});

const bottomCart = document.getElementById('bottomNavCart');
if (bottomCart) {
    bottomCart.addEventListener('click', (event) => {
        event.preventDefault();
        alert('Cart module is coming next.');
    });
}

const bottomSearch = document.getElementById('bottomNavSearch');
if (bottomSearch) {
    bottomSearch.addEventListener('click', (event) => {
        event.preventDefault();
        input.focus();
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

/* ===== Promo bar dismiss (resets at midnight) ===== */
(function() {
    const bar = document.getElementById('promoBar');
    const close = document.getElementById('promoClose');
    if (!bar || !close) return;
    const key = 'wlk_promo_dismissed_until';
    const until = parseInt(localStorage.getItem(key) || '0', 10);
    if (until && Date.now() < until) {
        bar.classList.add('is-hidden');
    }
    close.addEventListener('click', () => {
        const midnight = new Date();
        midnight.setHours(24, 0, 0, 0);
        localStorage.setItem(key, String(midnight.getTime()));
        bar.classList.add('is-hidden');
    });
})();

/* ===== Flash deal countdown (rolls over midnight) ===== */
(function() {
    const h = document.getElementById('cdH');
    const m = document.getElementById('cdM');
    const s = document.getElementById('cdS');
    const promo = document.getElementById('promoCountdown');
    if (!h && !promo) return;
    function tick() {
        const now = new Date();
        const end = new Date();
        end.setHours(24, 0, 0, 0);
        let diff = Math.max(0, Math.floor((end - now) / 1000));
        const hh = String(Math.floor(diff / 3600)).padStart(2, '0');
        const mm = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
        const ss = String(diff % 60).padStart(2, '0');
        if (h) h.textContent = hh;
        if (m) m.textContent = mm;
        if (s) s.textContent = ss;
        if (promo) promo.textContent = `${hh}:${mm}:${ss}`;
    }
    tick();
    setInterval(tick, 1000);
})();

/* ===== Activity ticker (sample) ===== */
(function() {
    const toast = document.getElementById('activityToast');
    const av = document.getElementById('actAv');
    const text = document.getElementById('actText');
    if (!toast || !text) return;
    const names = ['Nimal', 'Ayesha', 'Dilani', 'Kasun', 'Ravi', 'Tharushi', 'Sahan', 'Imali', 'Pasindu', 'Hiruni', 'Janani', 'Sanduni'];
    const cities = ['Colombo', 'Kandy', 'Galle', 'Negombo', 'Jaffna', 'Matara', 'Kurunegala', 'Anuradhapura', 'Ratnapura', 'Batticaloa'];
    const verbs = ['just bought', 'just added to cart', 'just viewed', 'just ordered'];
    function pickProductName() {
        const cards = document.querySelectorAll('#grid .name, .deal-name');
        if (!cards.length) return 'a watercolor product';
        const c = cards[Math.floor(Math.random() * cards.length)];
        return c.textContent.trim().slice(0, 60);
    }
    function rand(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
    let rounds = 0;
    function show() {
        const name = rand(names);
        const city = rand(cities);
        const verb = rand(verbs);
        const product = pickProductName();
        const mins = 1 + Math.floor(Math.random() * 12);
        text.innerHTML = `<strong>${name}</strong> from ${city} ${verb} <em style="font-style:normal;color:var(--amber)">${product}</em> &middot; ${mins} min ago`;
        if (av) av.textContent = name.charAt(0);
        toast.classList.add('is-visible');
        setTimeout(() => toast.classList.remove('is-visible'), 5500);
    }
    setTimeout(show, 4500);
    setInterval(() => {
        rounds++;
        if (rounds > 12) return;
        show();
    }, 12000);
})();

updateMeta(initialProducts.length, <?= json_encode($initialQuery !== '' ? ('Results for "' . $initialQuery . '"') : 'Showing latest products') ?>);

input.addEventListener('input', () => {
    const q = input.value.trim();
    clearTimeout(suggestTimer);
    clearTimeout(searchTimer);

    if (q.length < 1) {
        suggestions.style.display = 'none';
        renderProducts(initialProducts);
        updateMeta(initialProducts.length, 'Showing latest products');
        return;
    }

    suggestTimer = setTimeout(async () => {
        const res = await fetch(`api/search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.success) {
            suggestions.style.display = 'none';
            return;
        }
        renderSuggestions(data.suggestions || []);
    }, 140);

    searchTimer = setTimeout(async () => {
        const current = ++requestId;
        const res = await fetch(`api/products.php?q=${encodeURIComponent(q)}&per_page=60`);
        const data = await res.json();
        if (!data.success) {
            if (current !== requestId) {
                return;
            }
            grid.innerHTML = `<div class="empty">Search is temporarily unavailable. Please try again.</div>`;
            return;
        }
        if (current !== requestId) {
            return;
        }
        renderProducts(data.products || []);
        updateMeta((data.products || []).length, `Results for "${q}"`);
    }, 180);
});

input.addEventListener('keydown', async (event) => {
    if (event.key !== 'Enter') return;
    const q = input.value.trim();
    const res = await fetch(`api/products.php?q=${encodeURIComponent(q)}&per_page=48`);
    const data = await res.json();
    if (!data.success) {
        grid.innerHTML = `<div class="empty">Search is temporarily unavailable. Please try again.</div>`;
        return;
    }
    const products = data.products || [];
    renderProducts(products);
    updateMeta(products.length, `Results for "${q}"`);
    suggestions.style.display = 'none';
});

document.addEventListener('click', (event) => {
    if (event.target !== input && !suggestions.contains(event.target)) {
        suggestions.style.display = 'none';
    }
});

function renderSuggestions(items) {
    if (!items.length) {
        suggestions.style.display = 'none';
        return;
    }

    suggestions.innerHTML = items.map(item => `
        <div class="suggestion" onclick="window.location='${buildProductUrl(item)}'">
            ${escapeHtml(item.name)} - LKR ${Number(item.price).toFixed(2)}
        </div>
    `).join('');
    suggestions.style.display = 'block';
}

function renderProducts(items) {
    if (!items.length) {
        grid.innerHTML = `<div class="empty">No products found for this search. Try another keyword.</div>`;
        return;
    }

    grid.innerHTML = items.map(item => `
        <a class="card" href="${buildProductUrl(item)}">
            <div class="media">
                <div class="card-badges">
                    ${renderTopBadge(item)}
                </div>
                <img class="img" src="${item.image_url || 'assets/images/brand/logo-watercolorlk.png'}" alt="${escapeHtml(item.display_name)}">
            </div>
            <div class="body">
                <h3 class="name">${escapeHtml(item.display_name)}</h3>
                <div class="price-row">
                    <span class="price">LKR ${Number(item.price).toFixed(2)}</span>
                </div>
                <div class="deal-row"><span class="price-old">LKR ${(Number(item.price) * 1.12).toFixed(2)}</span><span class="price-off">Save 12%</span></div>
                <div class="rating"><span class="stars">★★★★★</span><span>4.9 rated</span><span>120+ sold</span></div>
                <div class="stock ${Number(item.stock_qty) > 0 ? 'ok' : 'no'}">${renderStockText(item)}</div>
            </div>
        </a>
    `).join('');
}

function buildProductUrl(item) {
    let slug = String(item.slug || '').trim();
    if (!slug || /^product-\d+$/i.test(slug)) {
        const source = String(item.display_name || item.name || '').trim().toLowerCase();
        slug = source.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }
    if (slug !== '') {
        return `product/${encodeURIComponent(slug)}`;
    }
    return `product/${encodeURIComponent(`product-${Number(item.erp_product_id || 0)}`)}`;
}

function renderTopBadge(item) {
    const qty = Number(item.stock_qty || 0);
    if (qty <= 0) {
        return '<span class="badge sale">Out of stock</span>';
    }
    if (qty <= 7) {
        return `<span class="badge low">Only ${Math.floor(qty)} left</span>`;
    }
    if (item.badge) {
        return `<span class="badge">${escapeHtml(item.badge)}</span>`;
    }
    return '<span class="badge">Featured</span>';
}

function renderStockText(item) {
    const qty = Number(item.stock_qty || 0);
    if (qty <= 0) {
        return 'Out of stock';
    }
    if (qty <= 7) {
        return `Only ${Math.floor(qty)} left in stock`;
    }
    return 'In stock';
}

function updateMeta(count, label) {
    resultMeta.textContent = `${label} (${count})`;
}

function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
</script>
</body>
</html>
