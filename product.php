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

function textLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }

    return strlen($value);
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

function normalizeReviewText(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $looksMojibake = preg_match('/(?:Ã.|Â.|â.|à.){2,}/u', $value) === 1;
    if ($looksMojibake && function_exists('mb_convert_encoding')) {
        $fixed = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        if (is_string($fixed) && $fixed !== '' && preg_match('//u', $fixed) === 1) {
            $value = $fixed;
        }
    }

    if (preg_match('//u', $value) !== 1 && function_exists('mb_convert_encoding')) {
        $fallback = @mb_convert_encoding($value, 'UTF-8', 'UTF-8,Windows-1252,ISO-8859-1');
        if (is_string($fallback) && $fallback !== '') {
            $value = $fallback;
        }
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function fetchGoogleReviewSummary(): array
{
    if (!defined('GOOGLE_PLACE_ID') || !defined('GOOGLE_PLACES_API_KEY') || GOOGLE_PLACE_ID === '' || GOOGLE_PLACES_API_KEY === '') {
        return [
            'rating' => null,
            'count' => null,
            'url' => defined('GOOGLE_REVIEWS_URL') ? GOOGLE_REVIEWS_URL : '',
            'reviews' => [],
            'source' => 'missing-config',
            'status' => 'MISSING_CONFIG',
            'error_message' => '',
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
            'status' => 'FETCH_FAILED',
            'error_message' => 'Failed to fetch Place Details response',
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
            'status' => (string)($decoded['status'] ?? 'INVALID_RESPONSE'),
            'error_message' => (string)($decoded['error_message'] ?? 'Unknown Place Details error'),
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
            'author_url' => (string)($review['author_url'] ?? ''),
            'relative_time' => (string)($review['relative_time_description'] ?? ''),
            'profile_photo_url' => (string)($review['profile_photo_url'] ?? ''),
        ];
    }

    return [
        'rating' => isset($result['rating']) ? (float)$result['rating'] : null,
        'count' => isset($result['user_ratings_total']) ? (int)$result['user_ratings_total'] : null,
        'url' => (string)($result['url'] ?? (defined('GOOGLE_REVIEWS_URL') ? GOOGLE_REVIEWS_URL : '')),
        'reviews' => $reviews,
        'source' => 'google',
        'status' => 'OK',
        'error_message' => '',
    ];
}

$erpId = (int)($_GET['id'] ?? 0);
$slug = trim((string)($_GET['slug'] ?? ''));

if ($slug === '') {
    $requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    if (preg_match('#/product/([^/?#]+)$#i', $requestPath, $matches) === 1) {
        $slug = rawurldecode((string)$matches[1]);
    }
}

$repo = new ProductRepository(appDb());
$product = null;
if ($slug !== '') {
    $product = $repo->getBySlug($slug);
}
if (!$product && $erpId > 0) {
    $product = $repo->getByErpId($erpId);
}

if ($product && $slug === '' && $erpId > 0 && !headers_sent()) {
    header('Location: ' . productUrl((string)($product['slug'] ?? ''), (string)($product['name'] ?? ''), (int)$product['erp_product_id']), true, 301);
    exit;
}

$stock = $product ? (float)$product['stock_qty'] : 0;
$stockPercent = $stock <= 0 ? 0 : min(100, max(12, (int)($stock * 10)));
$isOutOfStock = $stock <= 0;

$today = new DateTimeImmutable('today');
$deliveryStart = addWorkingDays($today, 2);
$deliveryEnd = addWorkingDays($today, 5);
$deliveryLabel = $deliveryStart->format('M j') . '* - ' . $deliveryEnd->format('M j') . '*';

$brandLine = $product ? strtoupper(trim((string)($product['brand_name'] ?: 'Watercolor.LK / Artist Grade'))) : 'Watercolor.LK / Artist Grade';
$categoryName = $product ? (string)($product['category_name'] ?? '') : '';
$bestSellers = $product ? $repo->listBestSellersByCategory($categoryName, (int)$product['erp_product_id'], 4) : [];

// Fetch reviews from database
$dbReviews = [];
try {
    $reviewRepository = new Repositories\GoogleReviewRepository(appDb());
    $dbReviews = $reviewRepository->getByMinRating(4.0, 20); // 4+ stars, max 20
} catch (Exception $e) {
    // Reviews table may not exist yet or database error - silently ignore
    $dbReviews = [];
}

$reviewSummary = fetchGoogleReviewSummary();
$googleProfileUrl = trim((string)($reviewSummary['url'] ?? ''));
if ($googleProfileUrl === '') {
    $googleProfileUrl = 'https://share.google/ScPmVS3Pgq1UFu5M9';
}
$ratingValue = $reviewSummary['rating'] !== null ? (float)$reviewSummary['rating'] : 4.9;
$buyersCount = max((int)($reviewSummary['count'] ?? 0), 382);
$soldCount = max($buyersCount * 2 + 54, 1260);

$reviewItems = [];
$isDbReviewFeed = count($dbReviews) > 0;

if ($isDbReviewFeed) {
    $ratingTotal = 0.0;
    foreach ($dbReviews as $dbReview) {
        $ratingTotal += (float)($dbReview['rating'] ?? 0);
    }

    $buyersCount = count($dbReviews);
    $ratingValue = round($ratingTotal / max(1, $buyersCount), 1);
    $soldCount = max($buyersCount * 2 + 54, 1260);

    foreach ($dbReviews as $item) {
        $localPhoto = trim((string)($item['profile_picture_local_path'] ?? ''));
        $remotePhoto = trim((string)($item['profile_picture_remote_url'] ?? ''));
        $displayDate = 'Recently';
        if (!empty($item['review_date'])) {
            $ts = strtotime((string)$item['review_date']);
            if ($ts !== false) {
                $displayDate = date('M d, Y', $ts);
            }
        }

        $reviewItems[] = [
            'author' => trim((string)($item['author'] ?? '')) !== '' ? (string)$item['author'] : 'Verified Buyer',
            'rating' => max(1, min(5, (float)($item['rating'] ?? 5))),
            'text' => trim((string)($item['review_text'] ?? '')) !== '' ? normalizeReviewText((string)$item['review_text']) : 'Excellent quality and fast delivery. Will order again.',
            'author_url' => $googleProfileUrl,
            'relative_time' => $displayDate,
            'profile_photo_url' => $localPhoto !== '' ? $localPhoto : ($remotePhoto !== '' ? $remotePhoto : 'assets/images/brand/favicon-watercolorlk.webp'),
            'fallback_photo_url' => $remotePhoto !== '' ? $remotePhoto : 'assets/images/brand/favicon-watercolorlk.webp',
        ];
    }
} else {
    foreach ((array)$reviewSummary['reviews'] as $item) {
        $photo = (string)($item['profile_photo_url'] ?? '');
        $reviewItems[] = [
            'author' => $item['author'] !== '' ? $item['author'] : 'Verified Buyer',
            'rating' => max(1, min(5, (float)($item['rating'] ?? 5))),
            'text' => $item['text'] !== '' ? normalizeReviewText((string)$item['text']) : 'Excellent quality and fast delivery. Will order again.',
            'author_url' => (string)($item['author_url'] ?? ''),
            'relative_time' => (string)($item['relative_time'] ?? '1 month ago'),
            'profile_photo_url' => $photo !== '' ? $photo : 'assets/images/brand/favicon-watercolorlk.webp',
            'fallback_photo_url' => 'assets/images/brand/favicon-watercolorlk.webp',
        ];
    }

    if (count($reviewItems) === 0) {
        $reviewItems = [
            [
                'author' => 'Verified Buyer',
                'rating' => 5,
                'text' => 'Superb quality and exactly as shown. Delivery was smooth and on time.',
                'author_url' => '',
                'relative_time' => '1 month ago',
                'profile_photo_url' => 'assets/images/brand/favicon-watercolorlk.webp',
                'fallback_photo_url' => 'assets/images/brand/favicon-watercolorlk.webp',
            ],
            [
                'author' => 'Returning Customer',
                'rating' => 5,
                'text' => 'Professional packaging and genuine products. Highly recommend this shop.',
                'author_url' => '',
                'relative_time' => '1 month ago',
                'profile_photo_url' => 'assets/images/brand/favicon-watercolorlk.webp',
                'fallback_photo_url' => 'assets/images/brand/favicon-watercolorlk.webp',
            ],
        ];
    }
}

$searchSeed = trim((string)($_GET['q'] ?? ''));
$scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
$baseHref = rtrim($scriptDir, '/');
if ($baseHref === '') {
    $baseHref = '/';
} else {
    $baseHref .= '/';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <base href="<?= htmlspecialchars($baseHref, ENT_QUOTES) ?>">
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
        .price { color: var(--brand-navy); font: 800 2.2rem/1.05 'Source Sans 3', sans-serif; }
        .price-compare { color: #9a8e81; text-decoration: line-through; margin-left: 8px; font-size: 1.08rem; }
        .save-strip {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eaf7ef;
            color: #1f6b45;
            border: 1px solid #b9e0c8;
            border-radius: 999px;
            padding: 5px 10px;
            font: 700 .84rem/1.2 'Montserrat', sans-serif;
            letter-spacing: .01em;
        }
        .save-strip strong {
            color: #17633e;
            font-weight: 800;
        }

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
            align-items: start;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font: 700 1.25rem/1 'Source Sans 3', sans-serif;
        }
        .qty-btn span {
            display: inline-block;
            line-height: 1;
            transform: translateY(-1px);
        }
        .qty-value {
            width: 100%;
            min-width: 0;
            text-align: center;
            outline: none;
            box-shadow: none;
            font: 700 1.1rem/1 'Montserrat', sans-serif;
        }
        .delivery-inline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #304056;
            font: 700 .98rem/1.2 'Source Sans 3', sans-serif;
            padding-top: 6px;
        }
        .delivery-icon {
            width: 18px;
            height: 18px;
            color: #4f5d79;
            flex: 0 0 18px;
        }

        .meta-inline {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 5px 9px;
            background: #fff;
            color: #3f4e69;
            font: 700 .75rem/1 'Montserrat', sans-serif;
        }

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
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .trust { padding: 12px; border: 1px solid var(--line); border-radius: 16px; background: #fff; text-align: center; }
        .trust strong { display: block; color: var(--brand-navy); font: 700 .88rem/1.2 'Montserrat', sans-serif; }
        .trust span { display: block; color: var(--muted); font-size: .78rem; margin-top: 4px; }

        .reviews-head {
            display: flex;
            align-items: flex-start;
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
        .reviews-intro { 
            display: flex;
            align-items: flex-start;
            gap: 16px;
            flex: 1;
        }
        .reviews-intro-left {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .reviews-intro-right {
            display: flex;
            flex-direction: column;
            gap: 2px;
            text-align: right;
            align-items: flex-end;
        }
        .profile-icon-shell {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 1px solid #d9cab8;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex: 0 0 48px;
        }
        .profile-icon-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
        }
        .reviews-intro h2 {
            margin: 0;
            color: var(--brand-navy-deep);
            font: 700 1.5rem/1.1 'Playfair Display', serif;
        }
        .summary-text strong,
        .based-text {
            display: block;
            color: #4f5d79;
            font: 600 .88rem/1.15 'Source Sans 3', sans-serif;
        }
        .summary-stars {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #4f5e79;
            font-size: 1.44rem;
            margin: 0;
            justify-content: flex-end;
        }
        .summary-stars .gold { color: #f5b400; letter-spacing: .08em; font-size: 1.35rem; }
        .google-logo {
            height: 25px;
            width: auto;
            display: block;
        }
        .review-slider-shell {
            position: relative;
            padding: 0 30px;
        }
        .review-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            padding-bottom: 4px;
            overflow: hidden;
        }
        .review-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid #d9cab8;
            background: #fff;
            color: var(--brand-navy);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font: 700 1.25rem/1 'Source Sans 3', sans-serif;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            z-index: 2;
        }
        .review-nav[data-review-nav="prev"] { left: -2px; }
        .review-nav[data-review-nav="next"] { right: -2px; }
        .review-nav:disabled {
            opacity: .35;
            cursor: default;
        }
        .review {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px 12px 10px;
            background: #fff;
            min-height: 235px;
        }
        .review.is-hidden { display: none; }
        .review-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 7px;
        }
        .review-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #d8cbb9;
            background: #f2eee8;
            flex: 0 0 36px;
        }
        .review-meta { min-width: 0; }
        .review-author {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--brand-navy);
            font: 700 .95rem/1.2 'Montserrat', sans-serif;
            text-decoration: none;
            max-width: 100%;
        }
        .review-author .name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 165px;
        }
        .g-icon {
            width: 16px;
            height: 16px;
            object-fit: contain;
            flex: 0 0 16px;
        }
        .review-time {
            color: #7a869f;
            font: 600 .82rem/1.2 'Source Sans 3', sans-serif;
            margin-top: 2px;
        }
        .review .r-stars {
            color: #f5b400;
            margin: 3px 0 6px;
            letter-spacing: .08em;
            font-size: 1.22rem;
            line-height: 1;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .verified {
            width: 15px;
            height: 15px;
            object-fit: contain;
        }
        .review p {
            margin: 0;
            color: #111827;
            font-size: 1.01rem;
            line-height: 1.45;
        }
        .review p.truncate {
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .read-more {
            display: inline-block;
            margin-top: 8px;
            color: #7b8598;
            text-decoration: none;
            font: 600 .95rem/1 'Source Sans 3', sans-serif;
        }
        .review-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            border-radius: 999px;
            border: 1px solid var(--line);
            padding: 9px 14px;
            color: var(--brand-navy);
            text-decoration: none;
            font: 700 .82rem/1 'Montserrat', sans-serif;
            background: #fff;
        }

        .trust-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }
        .trust {
            padding: 16px 12px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 74px;
        }
        .trust strong {
            display: block;
            color: var(--brand-navy);
            font: 700 .95rem/1.2 'Montserrat', sans-serif;
        }

        .best-head { margin: 24px 0 12px; }
        .best-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
            align-items: start;
        }
        .best-card {
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            background: #fff;
            box-shadow: var(--shadow-sm);
            min-width: 0;
        }
        .best-media {
            height: 220px;
            background: #f3eee6;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
        }
        @supports (aspect-ratio: 1 / 1) {
            .best-media {
                height: auto;
                aspect-ratio: 1 / 1;
            }
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
            .trust-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
        }
        @media (max-width: 720px) {
            .wrap { width: min(calc(100% - 24px), 1240px); }
            .header-inner { flex-wrap: wrap; }
            .header-search, .brand { min-width: 100%; }
            .purchase-row { grid-template-columns: 1fr; }
            .trust-grid { grid-template-columns: 1fr; }
            .best-grid { grid-template-columns: 1fr; }
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
                        <span class="profile-icon-shell" aria-hidden="true"><img class="profile-icon-logo" src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK profile"></span>
                        <div class="reviews-intro">
                            <div class="reviews-intro-left">
                                <img class="google-logo" src="assets/images/google full logo.svg" alt="Google">
                                <h2>Customer reviews</h2>
                            </div>
                            <div class="reviews-intro-right">
                                <div class="summary-stars"><span class="gold">★★★★★</span><span><?= number_format($ratingValue, 1) ?></span></div>
                                <div class="based-text">Based on <?= number_format($buyersCount) ?> reviews</div>
                            </div>
                        </div>
                    </div>
                    <?php if (!$isDbReviewFeed && ($reviewSummary['source'] ?? 'fallback') !== 'google'): ?>
                        <div style="margin:2px 0 10px;color:#a44d05;font-size:.86rem;">Live Google reviews are not loading yet (<?= htmlspecialchars((string)($reviewSummary['status'] ?? 'UNKNOWN')) ?>).</div>
                    <?php endif; ?>
                    <div class="review-slider-shell">
                        <button class="review-nav" data-review-nav="prev" type="button" aria-label="Previous reviews">‹</button>
                        <div class="review-grid" id="reviewSlider">
                        <?php foreach ($reviewItems as $review): ?>
                            <article class="review">
                                <div class="review-top">
                                    <img class="review-avatar" src="<?= htmlspecialchars((string)$review['profile_photo_url']) ?>" alt="reviewer" onerror="this.onerror=null;this.src='<?= htmlspecialchars((string)$review['fallback_photo_url']) ?>';">
                                    <div class="review-meta">
                                        <a class="review-author" href="<?= htmlspecialchars((string)($review['author_url'] ?: $googleProfileUrl)) ?>" target="_blank" rel="noopener">
                                            <span class="name"><?= htmlspecialchars((string)$review['author']) ?></span>
                                            <img class="g-icon" src="assets/images/google icon for go near user name.svg" alt="Google">
                                        </a>
                                        <div class="review-time"><?= htmlspecialchars((string)($review['relative_time'] ?: '1 month ago')) ?></div>
                                    </div>
                                </div>
                                <div class="r-stars"><span><?= str_repeat('★', (int)round((float)$review['rating'])) ?></span><img class="verified" src="assets/images/verification icon.png" alt="Verified" onerror="this.onerror=null;this.src='assets/images/brand/verified-check.svg';"></div>
                                <?php $text = (string)$review['text']; $isLong = textLength($text) > 140; ?>
                                <p class="<?= $isLong ? 'truncate' : '' ?>" data-long="<?= $isLong ? '1' : '0' ?>"><?= htmlspecialchars($text) ?></p>
                                <?php if ($isLong): ?>
                                    <a class="read-more" href="#" onclick="toggleReviewText(this); return false;">Read more</a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        </div>
                        <button class="review-nav" data-review-nav="next" type="button" aria-label="Next reviews">›</button>
                    </div>
                    <a class="review-link" href="<?= htmlspecialchars($googleProfileUrl) ?>" target="_blank" rel="noopener">See more reviews</a>
                </div>

                <div class="module" style="margin-top:16px;">
                    <div class="reviews-head"><h2>Product details</h2></div>
                    <p style="margin:0;color:#4d586f;font-size:.94rem;line-height:1.65;"><?= nl2br(htmlspecialchars((string)$product['description'])) ?></p>
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
                    <?php
                    $currentPrice = (float)$product['price'];
                    $comparePrice = round($currentPrice * 1.12, 2);
                    $savedAmount = max(0.0, $comparePrice - $currentPrice);
                    $savedPercent = $comparePrice > 0 ? (int)round(($savedAmount / $comparePrice) * 100) : 0;
                    ?>
                    <span class="price">LKR <?= number_format($currentPrice, 2) ?></span>
                    <span class="price-compare">LKR <?= number_format($comparePrice, 2) ?></span>
                    <?php if ($savedAmount > 0): ?>
                        <div class="save-strip">You save <strong>LKR <?= number_format($savedAmount, 2) ?></strong> (<?= $savedPercent ?>%) today</div>
                    <?php endif; ?>
                </div>

                <div class="urgency-row"><span class="dot"></span><span><?= $isOutOfStock ? 'Out of stock' : ('Only ' . (int)$stock . ' left in stock') ?></span></div>
                <div class="urgency"><span></span></div>

                <div class="purchase-row">
                    <div>
                        <h3 class="qty-head">Quantity</h3>
                        <div class="qty-controls">
                            <button class="qty-btn" type="button" onclick="changeQty(-1)" aria-label="Decrease quantity"><span>&minus;</span></button>
                            <input id="qty" class="qty-value" type="text" value="1" readonly>
                            <button class="qty-btn" type="button" onclick="changeQty(1)" aria-label="Increase quantity"><span>&plus;</span></button>
                        </div>
                    </div>
                    <div>
                        <h3 class="mini-head">Delivery estimation</h3>
                        <div class="delivery-inline">
                            <svg class="delivery-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7h13v10H3z"></path><path d="M16 10h3l2 2v5h-5z"></path><circle cx="8" cy="18" r="1.8"></circle><circle cx="18" cy="18" r="1.8"></circle></svg>
                            <span><?= htmlspecialchars($deliveryLabel) ?></span>
                        </div>
                    </div>
                </div>

                <div class="meta-inline">
                    <span class="meta-pill">Brand: <?= htmlspecialchars((string)($product['brand_name'] ?: 'Watercolor.LK')) ?></span>
                    <span class="meta-pill">Category: <?= htmlspecialchars((string)($product['category_name'] ?: 'Art Supplies')) ?></span>
                    <span class="meta-pill">SKU: <?= htmlspecialchars((string)$product['sku']) ?></span>
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
                    <span class="pay-chip">Cash on Delivery</span>
                </div>

                <div class="trust-grid">
                    <div class="trust"><strong>Return & refund</strong></div>
                    <div class="trust"><strong>Delivery Tracking</strong></div>
                    <div class="trust"><strong>Island-wide Delivery</strong></div>
                    <div class="trust"><strong>Safe payments</strong></div>
                </div>
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
                    <a class="best-card" href="<?= htmlspecialchars(productUrl((string)($item['slug'] ?? ''), (string)($item['display_name'] ?? ''), (int)$item['erp_product_id'])) ?>">
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

function toggleReviewText(link) {
    const card = link.closest('.review');
    if (!card) return;

    const p = card.querySelector('p');
    if (!p) return;

    const isExpanded = !p.classList.contains('truncate');
    if (isExpanded) {
        p.classList.add('truncate');
        link.textContent = 'Read more';
    } else {
        p.classList.remove('truncate');
        link.textContent = 'Read less';
    }
}

function initReviewSlider() {
    const slider = document.getElementById('reviewSlider');
    const prevBtn = document.querySelector('[data-review-nav="prev"]');
    const nextBtn = document.querySelector('[data-review-nav="next"]');

    if (!slider || !prevBtn || !nextBtn) return;

    const cards = Array.from(slider.querySelectorAll('.review'));
    if (cards.length === 0) {
        prevBtn.disabled = true;
        nextBtn.disabled = true;
        return;
    }

    let startIndex = 0;
    let totalSteps = 1;
    let autoTimer = null;
    const getPerPage = () => (window.matchMedia('(max-width: 980px)').matches ? 1 : 2);

    const renderWindow = () => {
        const perPage = getPerPage();
        totalSteps = cards.length;

        if (totalSteps === 0) return;

        startIndex = ((startIndex % totalSteps) + totalSteps) % totalSteps;
        const visible = new Set();
        for (let i = 0; i < Math.min(perPage, totalSteps); i++) {
            visible.add((startIndex + i) % totalSteps);
        }

        cards.forEach((card, idx) => {
            const shouldShow = visible.has(idx);
            card.classList.toggle('is-hidden', !shouldShow);
        });

        const hideArrows = totalSteps <= perPage;
        prevBtn.style.display = hideArrows ? 'none' : 'inline-flex';
        nextBtn.style.display = hideArrows ? 'none' : 'inline-flex';
        prevBtn.disabled = hideArrows;
        nextBtn.disabled = hideArrows;
    };

    const stopAuto = () => {
        if (autoTimer !== null) {
            window.clearInterval(autoTimer);
            autoTimer = null;
        }
    };

    const startAuto = () => {
        stopAuto();
        autoTimer = window.setInterval(() => {
            const perPage = getPerPage();
            if (totalSteps <= perPage) return;
            startIndex += 1;
            renderWindow();
        }, 5000);
    };

    prevBtn.addEventListener('click', () => {
        const perPage = getPerPage();
        if (totalSteps <= perPage) return;
        startIndex -= 1;
        renderWindow();
        startAuto();
    });

    nextBtn.addEventListener('click', () => {
        const perPage = getPerPage();
        if (totalSteps <= perPage) return;
        startIndex += 1;
        renderWindow();
        startAuto();
    });

    slider.addEventListener('mouseenter', stopAuto);
    slider.addEventListener('mouseleave', startAuto);
    prevBtn.addEventListener('mouseenter', stopAuto);
    prevBtn.addEventListener('mouseleave', startAuto);
    nextBtn.addEventListener('mouseenter', stopAuto);
    nextBtn.addEventListener('mouseleave', startAuto);

    window.addEventListener('resize', renderWindow);
    renderWindow();
    startAuto();
}

initReviewSlider();
<?php endif; ?>
</script>
</body>
</html>
