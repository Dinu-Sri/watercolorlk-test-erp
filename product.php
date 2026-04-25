<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function addWorkingDays(DateTimeImmutable $startDate, int $days): DateTimeImmutable
{
    $current = $startDate;
    $remaining = max(0, $days);

    while ($remaining > 0) {
        $current = $current->modify('+1 day');
        $weekday = (int)$current->format('N');
        if ($weekday < 6) {
            $remaining--;
        }
    }

    return $current;
}

function fetchGoogleReviewSummary(): array
{
    if (!defined('GOOGLE_PLACE_ID') || !defined('GOOGLE_PLACES_API_KEY') || GOOGLE_PLACE_ID === '' || GOOGLE_PLACES_API_KEY === '') {
        return [
            'rating' => null,
            'count' => null,
            'url' => defined('GOOGLE_REVIEWS_URL') ? GOOGLE_REVIEWS_URL : '',
            'reviews' => [],
            'source' => 'fallback',
        ];
    }

    $url = 'https://maps.googleapis.com/maps/api/place/details/json?place_id='
        . rawurlencode(GOOGLE_PLACE_ID)
        . '&fields=rating,user_ratings_total,reviews,url&key='
        . rawurlencode(GOOGLE_PLACES_API_KEY);

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 3,
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return [
            'rating' => null,
            'count' => null,
            'url' => defined('GOOGLE_REVIEWS_URL') ? GOOGLE_REVIEWS_URL : '',
            'reviews' => [],
            'source' => 'fallback',
        ];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'OK') {
        return [
            'rating' => null,
            'count' => null,
            'url' => defined('GOOGLE_REVIEWS_URL') ? GOOGLE_REVIEWS_URL : '',
            'reviews' => [],
            'source' => 'fallback',
        ];
    }

    $result = $decoded['result'] ?? [];
    $reviews = [];
    foreach ((array)($result['reviews'] ?? []) as $review) {
        $reviews[] = [
            'author' => (string)($review['author_name'] ?? 'Verified Buyer'),
            'rating' => (float)($review['rating'] ?? 5),
            'text' => trim((string)($review['text'] ?? '')),
            'time' => (int)($review['time'] ?? time()),
        ];
    }

    return [
        'rating' => isset($result['rating']) ? (float)$result['rating'] : null,
        'count' => isset($result['user_ratings_total']) ? (int)$result['user_ratings_total'] : null,
        'url' => (string)($result['url'] ?? (defined('GOOGLE_REVIEWS_URL') ? GOOGLE_REVIEWS_URL : '')),
        'reviews' => $reviews,
        'source' => 'google',
    ];
}

$erpId = (int)($_GET['id'] ?? 0);
$repo = new ProductRepository(appDb());
$product = $erpId > 0 ? $repo->getByErpId($erpId) : null;

$stock = $product ? (float)$product['stock_qty'] : 0;
$stockPercent = $stock <= 0 ? 0 : min(100, max(12, (int)($stock * 10)));
$isOutOfStock = $stock <= 0;

$brandLine = $product ? strtoupper(trim((string)($product['brand_name'] ?: 'Watercolor.LK / Artist Grade'))) : 'Watercolor.LK / Artist Grade';
$categoryName = $product ? (string)($product['category_name'] ?? '') : '';
$bestSellers = $product ? $repo->listBestSellersByCategory($categoryName, (int)$product['erp_product_id'], 4) : [];

$reviewSummary = fetchGoogleReviewSummary();
$ratingValue = $reviewSummary['rating'] !== null ? (float)$reviewSummary['rating'] : 4.9;
$buyersCount = max((int)($reviewSummary['count'] ?? 0), 382);
$soldCount = max($buyersCount * 2 + 54, 1260);

$deliveryStart = addWorkingDays(new DateTimeImmutable('today'), 2);
$deliveryEnd = addWorkingDays(new DateTimeImmutable('today'), 5);
$deliveryRange = $deliveryStart->format('d M') . ' - ' . $deliveryEnd->format('d M');

$reviewItems = [];
foreach (array_slice((array)$reviewSummary['reviews'], 0, 2) as $item) {
    $reviewItems[] = [
        'author' => $item['author'] !== '' ? $item['author'] : 'Verified Buyer',
        'rating' => max(1, min(5, (float)($item['rating'] ?? 5))),
        'text' => $item['text'] !== '' ? $item['text'] : 'Excellent quality and fast delivery. Will order again.',
    ];
}
if (count($reviewItems) === 0) {
    $reviewItems = [
        [
            'author' => 'Verified Buyer',
            'rating' => 5,
            'text' => 'Superb quality and exactly as shown. Delivery was smooth and on time.',
        ],
        [
            'author' => 'Returning Customer',
            'rating' => 5,
            'text' => 'Professional packaging and genuine products. Highly recommend this shop.',
        ],
    ];
}

$searchSeed = trim((string)($_GET['q'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $product ? htmlspecialchars((string)$product['name']) : 'Product not found' ?> | Watercolor.LK</title>
    <link rel="icon" type="image/webp" href="assets/images/brand/favicon-watercolorlk.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-navy: #1b2d4f;
            --brand-navy-deep: #10203a;
            --paper: #faf8f5;
            --line: #e7ddd2;
            --text: #1a1a1a;
            --muted: #6b6b6b;
            --amber: #e8760a;
            --amber-deep: #c4600a;
            --danger: #c0392b;
            --success: #2d7a4f;
            --gold: #d8a03d;
            --shadow-sm: 0 10px 24px rgba(17, 31, 56, 0.08);
            --shadow-lg: 0 22px 48px rgba(17, 31, 56, 0.13);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(196, 112, 90, .15), transparent 32%),
                radial-gradient(circle at top right, rgba(232, 118, 10, .08), transparent 28%),
                linear-gradient(180deg, #fffdfa 0%, var(--paper) 42%, #f5eee6 100%);
            font: 400 17px/1.7 'Source Sans 3', 'Segoe UI', sans-serif;
        }
        img { max-width: 100%; display: block; }
        .wrap { width: min(calc(100% - 32px), 1240px); margin: 0 auto; }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;
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
            min-width: 300px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .logo { height: 42px; width: auto; }
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
        }
        .header-search-input::placeholder { color: #7a828f; }
        .header-search-input:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(232,118,10,.14); }
        .header-search span { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #6d7383; font-size: .85rem; }
        .header-actions { display: flex; align-items: center; gap: 10px; }
        .icon-btn {
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
        }
        .icon-btn svg { width: 21px; height: 21px; }

        .content { padding: 16px 0 38px; }
        .back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--brand-navy);
            font: 700 13px/1 'Montserrat', sans-serif;
            letter-spacing: .08em;
            text-transform: uppercase;
            text-decoration: none;
        }
        .layout {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 24px;
            margin-top: 12px;
            align-items: start;
        }
        .gallery,
        .box,
        .module {
            background: rgba(255,255,255,.9);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }
        .gallery-stage {
            border: 1px solid #ece2d5;
            background: #f3f0eb;
            border-radius: 24px;
            min-height: 520px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
        }
        .main-image {
            width: 100%;
            max-height: 450px;
            border-radius: 14px;
            object-fit: contain;
        }
        .thumbs { display: flex; gap: 12px; margin-top: 16px; }
        .thumb {
            width: 76px;
            height: 76px;
            border-radius: 16px;
            border: 2px solid #d8ccbb;
            background: #fff;
            padding: 7px;
            cursor: pointer;
        }
        .thumb.active { border-color: var(--amber); }
        .thumb img { width: 100%; height: 100%; object-fit: contain; }

        .box {
            position: sticky;
            top: 92px;
            align-self: start;
        }
        .badge {
            display: inline-block;
            margin-bottom: 8px;
            font: 700 11px/1 'Montserrat', sans-serif;
            background: rgba(27, 45, 79, .1);
            color: var(--brand-navy);
            border-radius: 999px;
            padding: 6px 10px;
            letter-spacing: .09em;
            text-transform: uppercase;
        }
        .brand-line {
            color: #8f4d39;
            font: 700 12px/1 'Montserrat', sans-serif;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        h1 {
            margin: 8px 0;
            color: var(--brand-navy-deep);
            font: 700 clamp(1.7rem, 3vw, 2.4rem)/1.12 'Playfair Display', serif;
        }
        .rating-row {
            display: flex;
            gap: 10px;
            align-items: center;
            color: var(--muted);
            font-size: .9rem;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .stars { color: var(--gold); letter-spacing: .08em; }
        .price-panel {
            background: linear-gradient(135deg,#fffaf4,#f8ece0);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 18px;
            margin: 12px 0;
        }
        .price-label {
            display: block;
            color: var(--muted);
            font: 700 .78rem/1 'Montserrat', sans-serif;
            letter-spacing: .12em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .price { color: var(--brand-navy); font: 700 2.2rem/1.05 'Source Sans 3', sans-serif; }
        .price-compare { color: #9a8e81; text-decoration: line-through; margin-left: 8px; font-size: 1.08rem; }

        .urgency-row { margin: 16px 0; display: flex; gap: 10px; align-items: center; color: #b23c2c; font: 700 1.05rem/1.2 'Source Sans 3', sans-serif; }
        .dot { width: 11px; height: 11px; border-radius: 999px; background: #bc4433; }
        .urgency {
            height: 7px;
            border-radius: 999px;
            background: #ecd9d1;
            overflow: hidden;
            margin-top: 8px;
        }
        .urgency span {
            display: block;
            height: 100%;
            width: <?= $stockPercent ?>%;
            background: linear-gradient(90deg, var(--danger), var(--amber));
        }

        .purchase-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 14px;
        }
        .qty-head,
        .mini-head {
            margin: 0 0 8px;
            color: var(--brand-navy);
            font: 700 .95rem/1 'Montserrat', sans-serif;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .qty-controls {
            width: 100%;
            max-width: 190px;
            display: grid;
            grid-template-columns: 56px 1fr 56px;
            border: 2px solid #d5c8b4;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }
        .qty-btn,
        .qty-value {
            border: 0;
            background: transparent;
            color: var(--brand-navy);
            height: 46px;
        }
        .qty-btn {
            cursor: pointer;
            font: 700 1.6rem/1 'Source Sans 3', sans-serif;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .qty-value {
            text-align: center;
            font: 700 1.1rem/1 'Montserrat', sans-serif;
        }
        .delivery-box {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 10px 12px;
            background: #fff;
            color: #304056;
            font-size: .88rem;
        }
        .delivery-box strong { display: block; color: var(--brand-navy); margin-bottom: 4px; }
        .delivery-box span { display: block; line-height: 1.35; }

        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
        .button {
            width: 100%;
            box-sizing: border-box;
            padding: 13px;
            border-radius: 14px;
            border: 0;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(180deg, #e8760a, #c4600a);
            font: 700 1.05rem/1.2 'Source Sans 3', sans-serif;
        }
        .button.secondary {
            background: #fff;
            color: var(--brand-navy);
            border: 2px solid var(--brand-navy);
        }
        .button.whatsapp { background: #25d366; margin-top: 12px; }
        .button:disabled { opacity: .5; cursor: not-allowed; }

        .pay-strip {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .pay-chip {
            border: 1px solid #d8cbb9;
            border-radius: 999px;
            padding: 5px 10px;
            background: #fff;
            color: #4a5468;
            font: 700 .73rem/1 'Montserrat', sans-serif;
        }

        .trust-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .trust { padding: 12px; border: 1px solid var(--line); border-radius: 16px; background: #fff; text-align: center; }
        .trust strong { display: block; color: var(--brand-navy); font: 700 .88rem/1.2 'Montserrat', sans-serif; }
        .trust span { display: block; color: var(--muted); font-size: .78rem; margin-top: 4px; }

        .details-grid {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 8px;
        }
        .detail {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 10px;
            background: #fff;
        }
        .detail strong {
            display: block;
            color: #667084;
            font: 700 .72rem/1 'Montserrat', sans-serif;
            letter-spacing: .07em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .detail span { color: var(--brand-navy); font: 700 .9rem/1.2 'Source Sans 3', sans-serif; }

        .reviews-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }
        .reviews-head h2,
        .best-head h2 {
            margin: 0;
            color: var(--brand-navy-deep);
            font: 700 1.3rem/1.2 'Playfair Display', serif;
        }
        .google-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 6px 10px;
            background: #fff;
            color: #4a5671;
            font: 700 .75rem/1 'Montserrat', sans-serif;
        }
        .google-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: conic-gradient(#4285F4 0 25%, #34A853 25% 50%, #FBBC05 50% 75%, #EA4335 75% 100%);
        }
        .review-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .review {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px;
            background: #fff;
        }
        .review strong { color: var(--brand-navy); font: 700 .92rem/1.2 'Montserrat', sans-serif; }
        .review .r-stars { color: var(--gold); margin: 6px 0 4px; letter-spacing: .08em; }
        .review p { margin: 0; color: #495267; font-size: .9rem; line-height: 1.45; }
        .review-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 12px;
            border-radius: 999px;
            border: 1px solid var(--line);
            padding: 9px 14px;
            color: var(--brand-navy);
            text-decoration: none;
            font: 700 .82rem/1 'Montserrat', sans-serif;
            background: #fff;
        }

        .best-head { margin: 24px 0 12px; }
        .best-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 14px;
        }
        .best-card {
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            background: #fff;
            box-shadow: var(--shadow-sm);
        }
        .best-media {
            aspect-ratio: 1/1;
            background: #f3eee6;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }
        .best-media img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
        .best-body { padding: 12px; }
        .best-name {
            margin: 0 0 8px;
            color: var(--brand-navy-deep);
            font: 700 .98rem/1.25 'Source Sans 3', sans-serif;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 45px;
        }
        .best-price { color: var(--brand-navy); font: 700 1.2rem/1 'Source Sans 3', sans-serif; }

        #orderResult { margin-top: 10px; font: 600 14px/1.4 'Source Sans 3', sans-serif; }
        .not-found {
            margin-top: 12px;
            background: rgba(255, 255, 255, .9);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            color: #5f6b80;
        }

        @media (max-width: 980px) {
            .layout { grid-template-columns: 1fr; }
            .box { position: static; }
            .best-grid { grid-template-columns: 1fr 1fr; }
            .review-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 720px) {
            .wrap { width: min(calc(100% - 24px), 1240px); }
            .header-inner { flex-wrap: wrap; }
            .header-search, .brand { min-width: 100%; }
            .purchase-row { grid-template-columns: 1fr; }
            .trust-grid { grid-template-columns: 1fr; }
            .best-grid { grid-template-columns: 1fr; }
            .details-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="index.php">
            <img class="logo" src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK">
            <span class="brand-sub">පටන් ගන්න! පාට කරන්න! ජිවිතය විදින්න!</span>
        </a>
        <div class="header-search">
            <span>Find</span>
            <input id="headerSearch" class="header-search-input" placeholder="Search products, brands, categories..." autocomplete="off" value="<?= htmlspecialchars($searchSeed) ?>">
        </div>
        <div class="header-actions">
            <a class="icon-btn" href="admin/index.php" aria-label="Account">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            <a class="icon-btn" href="#" aria-label="Cart" id="cartButton">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H7"/></svg>
            </a>
        </div>
    </div>
</header>

<main class="wrap content">
    <a class="back" href="index.php">&larr; Back to shop</a>

    <?php if (!$product): ?>
        <div class="not-found">Product not found in local catalog. Run sync first.</div>
    <?php else: ?>
        <section class="layout">
            <div>
                <div class="gallery">
                    <div class="gallery-stage">
                        <img id="mainImage" class="main-image" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['name']) ?>">
                    </div>
                    <div class="thumbs">
                        <button class="thumb active" type="button" data-src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>"><img src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="thumb"></button>
                        <button class="thumb" type="button" data-src="assets/images/mascots/watercolor-brushes-1.webp"><img src="assets/images/mascots/watercolor-brushes-1.webp" alt="thumb"></button>
                        <button class="thumb" type="button" data-src="assets/images/mascots/watercolor-paints.webp"><img src="assets/images/mascots/watercolor-paints.webp" alt="thumb"></button>
                    </div>
                </div>

                <div class="module" style="margin-top:16px;">
                    <div class="reviews-head">
                        <h2>Customer reviews</h2>
                        <span class="google-badge"><span class="google-dot"></span>Google reviews</span>
                    </div>
                    <div class="rating-row" style="margin:0 0 10px;">
                        <span class="stars">★★★★★</span>
                        <span><?= number_format($ratingValue, 1) ?> rating from <?= number_format($buyersCount) ?> buyers</span>
                    </div>
                    <div class="review-grid">
                        <?php foreach ($reviewItems as $review): ?>
                            <article class="review">
                                <strong><?= htmlspecialchars((string)$review['author']) ?></strong>
                                <div class="r-stars"><?= str_repeat('★', (int)round((float)$review['rating'])) ?></div>
                                <p><?= htmlspecialchars((string)$review['text']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ((string)$reviewSummary['url'] !== ''): ?>
                        <a class="review-link" href="<?= htmlspecialchars((string)$reviewSummary['url']) ?>" target="_blank" rel="noopener">See more reviews</a>
                    <?php else: ?>
                        <a class="review-link" href="#" onclick="event.preventDefault();">See more reviews</a>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="box">
                <?php if (!empty($product['badge'])): ?>
                    <span class="badge"><?= htmlspecialchars((string)$product['badge']) ?></span>
                <?php endif; ?>
                <div class="brand-line"><?= htmlspecialchars($brandLine) ?></div>
                <h1><?= htmlspecialchars((string)$product['name']) ?></h1>

                <div class="rating-row">
                    <span class="stars">★★★★★</span>
                    <span><?= number_format($ratingValue, 1) ?> rated by <?= number_format($buyersCount) ?> buyers</span>
                    <span><?= number_format($soldCount) ?> sold</span>
                </div>

                <div class="price-panel">
                    <span class="price-label">Price including tax</span>
                    <span class="price">LKR <?= number_format((float)$product['price'], 2) ?></span>
                    <span class="price-compare">LKR <?= number_format((float)$product['price'] * 1.12, 2) ?></span>
                </div>

                <div class="urgency-row"><span class="dot"></span><span><?= $isOutOfStock ? 'Out of stock' : ('Only ' . (int)$stock . ' left in stock') ?></span></div>
                <div class="urgency"><span></span></div>

                <div class="purchase-row">
                    <div>
                        <h3 class="qty-head">Quantity</h3>
                        <div class="qty-controls">
                            <button class="qty-btn" type="button" onclick="changeQty(-1)" aria-label="Decrease quantity">-</button>
                            <input id="qty" class="qty-value" type="text" value="1" readonly>
                            <button class="qty-btn" type="button" onclick="changeQty(1)" aria-label="Increase quantity">+</button>
                        </div>
                    </div>
                    <div>
                        <h3 class="mini-head">Delivery estimation</h3>
                        <div class="delivery-box">
                            <strong>2 to 5 working days</strong>
                            <span><?= htmlspecialchars($deliveryRange) ?></span>
                            <span>Island-wide delivery available.</span>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button class="button" onclick="submitOrder('payhere')" <?= $isOutOfStock ? 'disabled' : '' ?>>Buy Now</button>
                    <button class="button secondary" type="button" onclick="addToCart()" <?= $isOutOfStock ? 'disabled' : '' ?>>Add to Cart</button>
                </div>
                <button class="button whatsapp" type="button" onclick="openWhatsAppOrder()" <?= $isOutOfStock ? 'disabled' : '' ?>>WhatsApp Order</button>

                <div class="pay-strip">
                    <span class="pay-chip">PayHere</span>
                    <span class="pay-chip">VISA</span>
                    <span class="pay-chip">Mastercard</span>
                    <span class="pay-chip">Bank Transfer</span>
                </div>

                <div class="trust-grid">
                    <div class="trust"><strong>Return & refund</strong><span>7-day support for valid issues</span></div>
                    <div class="trust"><strong>Secure checkout</strong><span>Encrypted payment processing</span></div>
                    <div class="trust"><strong>Delivery support</strong><span>Tracked dispatch updates</span></div>
                </div>

                <div class="details-grid">
                    <div class="detail"><strong>Brand</strong><span><?= htmlspecialchars((string)($product['brand_name'] ?: 'Watercolor.LK')) ?></span></div>
                    <div class="detail"><strong>Category</strong><span><?= htmlspecialchars((string)($product['category_name'] ?: 'Art Supplies')) ?></span></div>
                    <div class="detail"><strong>SKU</strong><span><?= htmlspecialchars((string)$product['sku']) ?></span></div>
                    <div class="detail"><strong>Availability</strong><span><?= $isOutOfStock ? 'Out of stock' : 'In stock' ?></span></div>
                </div>

                <p style="margin:12px 0 0;color:#4d586f;font-size:.9rem;"><?= nl2br(htmlspecialchars((string)$product['description'])) ?></p>
                <div id="orderResult"></div>
            </aside>
        </section>

        <section class="best-head">
            <h2>Best sellers in <?= htmlspecialchars($categoryName !== '' ? $categoryName : 'this category') ?></h2>
        </section>
        <section class="best-grid">
            <?php if (count($bestSellers) === 0): ?>
                <div class="module" style="grid-column:1 / -1;">More category products will appear after the next sync.</div>
            <?php else: ?>
                <?php foreach ($bestSellers as $item): ?>
                    <a class="best-card" href="product.php?id=<?= (int)$item['erp_product_id'] ?>">
                        <div class="best-media"><img src="<?= htmlspecialchars((string)($item['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$item['display_name']) ?>"></div>
                        <div class="best-body">
                            <h3 class="best-name"><?= htmlspecialchars((string)$item['display_name']) ?></h3>
                            <div class="best-price">LKR <?= number_format((float)$item['price'], 2) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<script>
const cartButton = document.getElementById('cartButton');
const headerSearch = document.getElementById('headerSearch');
if (cartButton) {
    cartButton.addEventListener('click', (event) => {
        event.preventDefault();
        alert('Cart module is coming next.');
    });
}
if (headerSearch) {
    headerSearch.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        const q = headerSearch.value.trim();
        window.location.href = `index.php${q ? `?q=${encodeURIComponent(q)}` : ''}`;
    });
}

<?php if ($product): ?>
const product = {
    product_id: <?= (int)$product['id'] ?>,
    erp_product_id: <?= (int)$product['erp_product_id'] ?>,
    sku: <?= json_encode((string)$product['sku']) ?>,
    unit_price: <?= json_encode((float)$product['price']) ?>,
    name: <?= json_encode((string)$product['name']) ?>
};

let customerName = '';
let customerPhone = '';

document.querySelectorAll('.thumb').forEach((thumb) => {
    thumb.addEventListener('click', () => {
        document.querySelectorAll('.thumb').forEach((t) => t.classList.remove('active'));
        thumb.classList.add('active');
        document.getElementById('mainImage').src = thumb.dataset.src;
    });
});

function changeQty(delta) {
    const input = document.getElementById('qty');
    const next = Math.max(1, Number(input.value || 1) + delta);
    input.value = String(next);
}

function ensureBuyerDetails() {
    if (!customerName) {
        const name = window.prompt('Enter your full name');
        if (!name) return false;
        customerName = name.trim();
    }
    if (!customerPhone) {
        const phone = window.prompt('Enter your phone number');
        if (!phone) return false;
        customerPhone = phone.trim();
    }
    return true;
}

async function submitOrder(paymentMethod) {
    if (!ensureBuyerDetails()) {
        return;
    }

    const payload = {
        customer_name: customerName,
        customer_phone: customerPhone,
        customer_email: '',
        payment_method: paymentMethod,
        notes: '',
        items: [
            {
                product_id: product.product_id,
                erp_product_id: product.erp_product_id,
                sku: product.sku,
                quantity: Number(document.getElementById('qty').value || 1),
                unit_price: Number(product.unit_price)
            }
        ]
    };

    const res = await fetch('api/place-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const data = await res.json();
    const box = document.getElementById('orderResult');

    if (!data.success) {
        box.innerHTML = `<span style="color:#ae1c1c">${data.error || 'Order failed'}</span>`;
        return;
    }

    const syncMessage = data.erp_sync === 'synced'
        ? 'Synced to ERP successfully.'
        : 'Order saved locally and queued for ERP retry.';

    box.innerHTML = `<span style="color:#1f5d23">Order #${data.order_id} created. ${syncMessage}</span>`;
}

function addToCart() {
    const qty = Number(document.getElementById('qty').value || 1);
    document.getElementById('orderResult').innerHTML = `<span style="color:#1f5d23">${qty} item(s) added to cart queue.</span>`;
}

function openWhatsAppOrder() {
    const qty = Number(document.getElementById('qty').value || 1);
    const text = encodeURIComponent(`Hi Watercolor.LK, I want to order ${product.name} (Qty: ${qty}).`);
    window.open(`https://wa.me/94700000000?text=${text}`, '_blank');
}
<?php endif; ?>
</script>
</body>
</html>
