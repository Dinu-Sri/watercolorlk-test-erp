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
    $allTop = $reviewRepo->getByMinRating(4.0, 30);
    $filtered = array_values(array_filter($allTop, static function (array $r): bool {
        return trim((string)($r['review_text'] ?? '')) !== '';
    }));
    $topReviews = array_slice($filtered, 0, 6);
    $reviewStats['count'] = $reviewRepo->getCount();
    if ($reviewStats['count'] > 0) {
        $sumAll = $reviewRepo->getAllActive(500);
        $sum = 0.0; $n = 0;
        foreach ($sumAll as $r) { $sum += (float)($r['rating'] ?? 0); $n++; }
        if ($n > 0) { $reviewStats['avg'] = round($sum / $n, 1); }
    }
} catch (Throwable $e) {
    $topReviews = [];
}

$googleReviewsUrl = defined('GOOGLE_REVIEWS_URL') && GOOGLE_REVIEWS_URL !== ''
    ? GOOGLE_REVIEWS_URL
    : 'https://share.google/ScPmVS3Pgq1UFu5M9';

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

function textLengthSafe(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return strlen($value);
}

function normalizeReviewText(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_convert_encoding')) {
        $looksMojibake = preg_match('/(?:Ãƒ.|Ã‚.|Ã¢.|Ã .){2,}/u', $value) === 1;
        if ($looksMojibake) {
            $fixed = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            if (is_string($fixed) && $fixed !== '' && preg_match('//u', $fixed) === 1) {
                $value = $fixed;
            }
        }
    }
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
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
    $html .= '<div class="deal-meta"><span class="stars" aria-label="Rating">&#9733;&#9733;&#9733;&#9733;&#9733;</span><span class="meta-dot">' . $rating . '</span><span class="meta-dot">' . $sold . '+ sold</span></div>';
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
            flex: 0 0 auto;
            min-width: 0;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
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
        .header-actions { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
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

        /* ===== Deal grid (Just for You uses same card style) ===== */
        .deal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
            padding: 6px 0 18px;
        }

        /* ===== Activity ticker (modernised, real) ===== */
        .activity-toast {
            position: fixed; left: 18px; bottom: 22px;
            display: none; align-items: center; gap: 12px;
            padding: 12px 18px 12px 14px; border-radius: 14px;
            background: #fff; color: #1b2d4f;
            border: 1px solid var(--line);
            box-shadow: 0 18px 44px rgba(17,31,56,.18);
            font: 600 .86rem/1.3 'Source Sans 3', sans-serif;
            z-index: 38; max-width: 360px;
            animation: toastIn .35s ease;
        }
        .activity-toast.is-visible { display: inline-flex; }
        .activity-toast .pulse {
            width: 10px; height: 10px; border-radius: 50%;
            background: var(--accent-mint); flex: 0 0 10px;
            position: relative;
        }
        .activity-toast .pulse::before {
            content: ""; position: absolute; inset: -6px;
            border-radius: 50%; border: 2px solid var(--accent-mint);
            opacity: .5; animation: pulseRing 1.6s ease-out infinite;
        }
        @keyframes pulseRing {
            0% { transform: scale(.6); opacity: .8; }
            100% { transform: scale(1.4); opacity: 0; }
        }
        .activity-toast small {
            display: block; color: #8a8275; font-weight: 500;
            margin-top: 3px; font-size: .76rem;
        }

        /* ===== Reviews (rich, like product page) ===== */
        .reviews-summary {
            display: flex; align-items: center; gap: 18px;
            padding: 18px 22px; border-radius: var(--radius-md);
            background: #fff; border: 1px solid var(--line);
            box-shadow: var(--shadow-sm); margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .reviews-summary .gscore {
            display: flex; align-items: center; gap: 12px;
        }
        .reviews-summary .gscore img { height: 28px; width: auto; }
        .reviews-summary .num {
            font: 800 1.6rem/1 'Playfair Display', serif;
            color: var(--brand-navy);
        }
        .reviews-summary .stars {
            color: var(--gold); letter-spacing: .08em; font-size: 1.1rem;
        }
        .reviews-summary .based {
            color: #6e7689; font: 500 .88rem/1.3 'Source Sans 3', sans-serif;
        }
        .reviews-summary .verified-pill {
            display: inline-flex; align-items: center; gap: 6px;
            margin-left: auto; padding: 8px 14px;
            background: rgba(45,122,79,.1); color: var(--accent-mint);
            border-radius: 999px;
            font: 700 .78rem/1 'Montserrat', sans-serif; letter-spacing: .04em;
        }
        .reviews-summary .verified-pill img { height: 16px; width: 16px; }
        .review-card {
            background: #fff; border: 1px solid var(--line);
            border-radius: var(--radius-md); padding: 18px;
            box-shadow: var(--shadow-sm);
            display: grid; gap: 10px; align-content: start;
            transition: transform .15s, box-shadow .15s;
        }
        .review-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .review-head { display: flex; align-items: center; gap: 12px; }
        .review-avatar-img {
            width: 44px; height: 44px; border-radius: 50%;
            object-fit: cover; flex: 0 0 44px;
            background: #f5eee6;
            border: 2px solid #fff;
            box-shadow: 0 2px 6px rgba(17,31,56,.1);
        }
        .review-meta { display: grid; gap: 2px; flex: 1; min-width: 0; }
        .review-author-row {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--brand-navy); font: 700 .94rem/1.1 'Montserrat', sans-serif;
            text-decoration: none;
        }
        .review-author-row:hover { color: var(--amber-deep); }
        .review-author-row .g-icon { width: 14px; height: 14px; }
        .review-time { color: #8a8275; font: 500 .78rem/1 'Source Sans 3', sans-serif; }
        .review-stars-row {
            display: flex; align-items: center; gap: 8px;
        }
        .review-stars-row .stars { color: var(--gold); letter-spacing: .04em; font-size: 1rem; }
        .review-stars-row .verified-icon { width: 16px; height: 16px; opacity: .85; }
        .review-text {
            color: #3e485e; font: 500 .94rem/1.55 'Source Sans 3', sans-serif;
            display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden;
            margin: 0;
        }
        .review-text.expanded { -webkit-line-clamp: unset; }
        .review-readmore {
            color: var(--amber-deep); font: 700 .8rem/1 'Montserrat', sans-serif;
            text-decoration: none; cursor: pointer;
        }
        .reviews-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 14px; }

        /* ===== Footer (paper bg, rich, trust) ===== */
        .site-footer {
            background:
                linear-gradient(180deg, #1b2d4f 0%, #10203a 100%);
            color: rgba(255,255,255,.82);
            padding: 0 0 24px; margin-top: 28px;
            position: relative;
        }
        .footer-trust-band {
            background: linear-gradient(180deg, #fdf3e6 0%, #faf8f5 100%);
            border-top: 1px solid var(--line);
            padding: 28px 0;
        }
        .footer-trust {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }
        .trust-tile {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 18px; border-radius: var(--radius-md);
            background: #fff; border: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
        }
        .trust-tile .icon {
            width: 46px; height: 46px; border-radius: 12px;
            background: linear-gradient(135deg, rgba(232,118,10,.18), rgba(232,118,10,.08));
            color: var(--amber-deep); display: inline-flex; align-items: center; justify-content: center;
            flex: 0 0 46px;
        }
        .trust-tile .icon svg { width: 24px; height: 24px; }
        .trust-tile strong { display: block; color: var(--brand-navy); font: 800 1rem/1.1 'Montserrat', sans-serif; }
        .trust-tile span { color: #6e7689; font: 500 .82rem/1.3 'Source Sans 3', sans-serif; }

        .footer-main { padding-top: 44px; }
        .footer-grid {
            display: grid; grid-template-columns: 1.4fr repeat(3, 1fr); gap: 30px;
            padding-bottom: 26px; border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .footer-brand .logo-card {
            display: inline-flex; align-items: center; justify-content: center;
            background: #fff; border-radius: 12px;
            padding: 8px 14px; margin-bottom: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,.2);
        }
        .footer-brand .logo-card img { height: 36px; width: auto; display: block; }
        .footer-brand p { margin: 0 0 14px; font-size: .92rem; line-height: 1.6; opacity: .82; max-width: 380px; }
        .footer-contact {
            display: grid; gap: 8px; margin: 12px 0 14px;
            font-size: .88rem;
        }
        .footer-contact a, .footer-contact span { color: rgba(255,255,255,.78); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .footer-contact a:hover { color: var(--amber); }
        .footer-contact svg { width: 16px; height: 16px; flex: 0 0 16px; opacity: .8; }
        .footer-socials { display: flex; gap: 8px; }
        .footer-socials a {
            width: 38px; height: 38px; border-radius: 50%;
            background: rgba(255,255,255,.08); color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; transition: background .15s, transform .15s;
        }
        .footer-socials a:hover { background: var(--amber); transform: translateY(-2px); }
        .footer-socials svg { width: 17px; height: 17px; }

        .footer-payments {
            padding: 22px 0 18px; border-bottom: 1px solid rgba(255,255,255,.1);
            display: grid; grid-template-columns: auto 1fr; gap: 18px; align-items: center;
        }
        .footer-payments h5 {
            margin: 0; color: #fff; font: 700 .82rem/1 'Montserrat', sans-serif;
            letter-spacing: .1em; text-transform: uppercase;
        }
        .pay-row {
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
        }
        .payhere-banner {
            background: #fff; padding: 8px 12px; border-radius: 10px;
            display: inline-flex; align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        .payhere-banner img { height: 30px; width: auto; display: block; }
        .pay-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 14px; border-radius: 10px;
            background: rgba(255,255,255,.1); color: #fff;
            font: 700 .82rem/1 'Montserrat', sans-serif; letter-spacing: .04em;
            border: 1px solid rgba(255,255,255,.15);
        }
        .pay-chip svg { width: 18px; height: 18px; }
        .pay-chip.cod { background: rgba(45,122,79,.25); border-color: rgba(45,122,79,.5); }
        .pay-chip.bank { background: rgba(232,118,10,.18); border-color: rgba(232,118,10,.4); }

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
<?php
$showPromoBar = true;
$headerSearchValue = $initialQuery;
$cartCount = 0;
include __DIR__ . '/partials/site-header.php';
?>

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
                <span><span class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span> <strong style="color:var(--brand-navy)"><?= $avgRating ?></strong>&nbsp;<?= htmlspecialchars($reviewCountLabel) ?></span>
                <span class="sep"></span>
                <span>ðŸ‡±ðŸ‡° Free delivery > LKR 5,000</span>
                <span class="sep"></span>
                <span>ðŸ’³ COD &amp; bank transfer</span>
            </div>
        </div>
        <div class="hero-art" aria-hidden="true">
            <img src="assets/images/mascots/watercolor-paints.webp" alt="Watercolor paints" onerror="this.onerror=null;this.src='assets/images/mascots/watercolor-brushes-1.webp';">
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
                <a class="category-card" href="shop.php?category=<?= urlencode($c['q']) ?>">
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
            <a class="section-link" href="shop.php">View all &rarr;</a>
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
        <div id="grid" class="deal-grid">
        <?php foreach ($products as $product): ?>
            <?= renderDealCard($product) ?>
        <?php endforeach; ?>
        </div>
    </section>

    <!-- REVIEWS STRIP -->
    <?php if (!empty($topReviews)): ?>
    <section class="section" id="reviews" aria-label="Customer reviews">
        <div class="section-head">
            <h2 class="section-title">What artists are saying<small>Verified Google reviews from real customers</small></h2>
            <a class="section-link" href="<?= htmlspecialchars($googleReviewsUrl) ?>" target="_blank" rel="noopener">See all on Google &rarr;</a>
        </div>
        <div class="reviews-summary">
            <div class="gscore">
                <img src="assets/images/google full logo.svg" alt="Google" onerror="this.style.display='none'">
                <span class="num"><?= $avgRating ?></span>
                <div>
                    <div class="stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                    <div class="based">Based on <?= htmlspecialchars($reviewCountLabel) ?></div>
                </div>
            </div>
            <span class="verified-pill">
                <img src="assets/images/brand/verified-check.svg" alt="" aria-hidden="true" onerror="this.style.display='none'">
                100% verified buyers
            </span>
        </div>
        <div class="reviews-grid">
            <?php foreach ($topReviews as $review):
                $author = htmlspecialchars((string)($review['author'] ?? 'Verified Buyer'));
                $initial = strtoupper(mb_substr((string)($review['author'] ?? 'A'), 0, 1));
                $text = htmlspecialchars(normalizeReviewText((string)($review['review_text'] ?? '')));
                $rating = (float)($review['rating'] ?? 5);
                $stars = str_repeat('&#9733;', (int)round($rating)) . str_repeat('&#9734;', max(0, 5 - (int)round($rating)));
                $displayDate = 'Recently';
                if (!empty($review['review_date'])) {
                    $ts = strtotime((string)$review['review_date']);
                    if ($ts !== false) { $displayDate = date('M Y', $ts); }
                }
                $localPath = trim((string)($review['profile_picture_local_path'] ?? ''));
                $remote = trim((string)($review['profile_picture_remote_url'] ?? ''));
                $avatar = $localPath !== '' ? $localPath : ($remote !== '' ? $remote : 'assets/images/brand/favicon-watercolorlk.webp');
                $fallback = $remote !== '' ? $remote : 'assets/images/brand/favicon-watercolorlk.webp';
                $isLong = textLengthSafe($text) > 220;
            ?>
            <article class="review-card">
                <header class="review-head">
                    <img class="review-avatar-img" src="<?= htmlspecialchars($avatar) ?>" alt="<?= $author ?>" loading="lazy" onerror="this.onerror=null;this.src='<?= htmlspecialchars($fallback) ?>';">
                    <div class="review-meta">
                        <a class="review-author-row" href="<?= htmlspecialchars($googleReviewsUrl) ?>" target="_blank" rel="noopener">
                            <span><?= $author ?></span>
                            <img class="g-icon" src="assets/images/google icon for go near user name.svg" alt="Google" onerror="this.style.display='none'">
                        </a>
                        <span class="review-time"><?= htmlspecialchars($displayDate) ?> &middot; via Google</span>
                    </div>
                </header>
                <div class="review-stars-row">
                    <span class="stars" aria-label="<?= (int)$rating ?> of 5 stars"><?= $stars ?></span>
                    <img class="verified-icon" src="assets/images/verification icon.png" alt="Verified" onerror="this.onerror=null;this.src='assets/images/brand/verified-check.svg';">
                </div>
                <p class="review-text<?= $isLong ? '' : ' expanded' ?>">"<?= $text ?>"</p>
                <?php if ($isLong): ?>
                    <a class="review-readmore" href="#" onclick="event.preventDefault(); this.previousElementSibling.classList.toggle('expanded'); this.textContent = this.previousElementSibling.classList.contains('expanded') ? 'Show less' : 'Read more';">Read more</a>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
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

<?php include __DIR__ . '/partials/site-footer.php'; ?>
<?php include __DIR__ . '/partials/site-scripts.php'; ?>

<script>
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
</script>
</body>
</html>
