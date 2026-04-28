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
<?php include __DIR__ . '/partials/chrome-styles.php'; ?>
<style>
    /* ===== Product page — AliExpress-style overrides ===== */
    .product-hero {
        margin: 14px 0 20px;
        background:
            radial-gradient(circle at 92% 12%, rgba(232,118,10,.16), transparent 42%),
            radial-gradient(circle at 6% 90%, rgba(196,112,90,.18), transparent 48%),
            linear-gradient(135deg, #fffdfa 0%, #fdf3e6 100%);
        border: 1px solid var(--line);
        border-radius: var(--radius-lg);
        padding: 22px;
        box-shadow: var(--shadow-sm);
    }
    .breadcrumbs {
        display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
        margin: 0 0 14px;
        font: 600 .82rem/1 'Montserrat', sans-serif;
        color: #6b7388;
    }
    .breadcrumbs a { color: var(--brand-navy); text-decoration: none; }
    .breadcrumbs a:hover { color: var(--amber-deep); }
    .breadcrumbs .sep { opacity: .5; }
    .breadcrumbs .current { color: var(--brand-navy-deep); }

    /* Gallery overlays */
    .gallery-stage { position: relative; }
    .gallery-badges {
        position: absolute; inset: 12px 12px auto 12px;
        display: flex; align-items: flex-start; justify-content: space-between;
        pointer-events: none; z-index: 2;
    }
    .gallery-badges .auth {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(45,122,79,.95); color: #fff;
        padding: 6px 12px; border-radius: 999px;
        font: 800 .72rem/1 'Montserrat', sans-serif; letter-spacing: .05em;
        box-shadow: 0 4px 12px rgba(45,122,79,.35);
    }
    .gallery-badges .auth img { width: 14px; height: 14px; }
    .gallery-badges .discount-flag {
        background: var(--grad-fire); color: #fff;
        padding: 8px 14px; border-radius: 10px;
        font: 800 .82rem/1 'Montserrat', sans-serif; letter-spacing: .03em;
        box-shadow: 0 6px 16px rgba(230,57,70,.4);
    }
    .viewing-pill {
        position: absolute; left: 14px; bottom: 14px;
        display: inline-flex; align-items: center; gap: 8px;
        padding: 7px 12px; border-radius: 999px;
        background: rgba(27,45,79,.92); color: #fff;
        font: 700 .76rem/1 'Montserrat', sans-serif; letter-spacing: .03em;
        box-shadow: 0 4px 12px rgba(0,0,0,.2);
        z-index: 2;
    }
    .viewing-pill .pulse {
        width: 8px; height: 8px; border-radius: 50%; background: #ff5b3a;
        position: relative;
    }
    .viewing-pill .pulse::before {
        content: ""; position: absolute; inset: -4px; border-radius: 50%;
        border: 2px solid #ff5b3a; opacity: .6;
        animation: pulseRing 1.6s ease-out infinite;
    }

    /* Right column — purchase box overrides */
    .deal-flag {
        display: inline-flex; align-items: center; gap: 6px;
        background: var(--grad-fire); color: #fff;
        padding: 6px 12px; border-radius: 8px;
        font: 800 .76rem/1 'Montserrat', sans-serif; letter-spacing: .04em;
        margin-right: 8px;
    }
    .rating-row .g-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 999px;
        background: #fff; border: 1px solid var(--line);
        font: 700 .76rem/1 'Montserrat', sans-serif; color: #4a5468;
        text-decoration: none;
    }
    .rating-row .g-pill img { height: 13px; width: auto; }

    .social-proof {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 6px; margin: 10px 0 14px;
        padding: 10px; border-radius: 14px;
        background: rgba(232,118,10,.06); border: 1px solid rgba(232,118,10,.16);
    }
    .social-proof .stat {
        display: flex; flex-direction: column; align-items: center; gap: 2px;
        text-align: center;
    }
    .social-proof .stat strong {
        color: var(--brand-navy); font: 800 1.05rem/1 'Source Sans 3', sans-serif;
    }
    .social-proof .stat span {
        color: #6b7388; font: 600 .7rem/1.1 'Montserrat', sans-serif;
        letter-spacing: .03em; text-transform: uppercase;
    }
    .social-proof .stat .pulse-mini {
        display: inline-block; width: 7px; height: 7px; border-radius: 50%;
        background: var(--accent-mint); margin-right: 4px;
        box-shadow: 0 0 0 4px rgba(45,122,79,.18);
        animation: livePulse 1.6s ease-in-out infinite;
    }
    @keyframes livePulse { 0%,100% { opacity: 1; } 50% { opacity: .35; } }

    .price-hero {
        background: linear-gradient(135deg,#fffaf4,#f8ece0);
        border: 1px solid var(--line);
        border-radius: 22px;
        padding: 16px 18px;
        margin: 6px 0 12px;
    }
    .price-hero .price-row { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .price-hero .price {
        color: #b8232f; font: 900 2.4rem/1 'Source Sans 3', sans-serif;
    }
    .price-hero .price-compare {
        color: #9a8e81; text-decoration: line-through; font-size: 1.05rem;
    }
    .price-hero .save-pill {
        display: inline-flex; align-items: center; gap: 6px;
        margin-top: 8px; padding: 5px 11px;
        background: #eaf7ef; color: #17633e; border: 1px solid #b9e0c8;
        border-radius: 999px;
        font: 800 .82rem/1.1 'Montserrat', sans-serif;
    }
    .price-hero .lowest-30 {
        display: block; margin-top: 6px;
        color: #6b7388; font: 600 .76rem/1.2 'Source Sans 3', sans-serif;
    }

    .deal-countdown {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px; margin-bottom: 12px;
        background: linear-gradient(90deg, rgba(230,57,70,.1), rgba(232,118,10,.08));
        border: 1px solid rgba(230,57,70,.2);
        border-radius: 14px;
        font: 700 .9rem/1.2 'Source Sans 3', sans-serif;
        color: #b8232f;
    }
    .deal-countdown .lightning { font-size: 1.2rem; }
    .deal-countdown .timer {
        display: inline-flex; gap: 4px; margin-left: auto;
        font: 800 1rem/1 'Montserrat', sans-serif;
    }
    .deal-countdown .timer span {
        background: #b8232f; color: #fff;
        padding: 5px 7px; border-radius: 6px;
        min-width: 28px; text-align: center;
    }

    /* Scarcity bar — redesigned */
    .scarcity {
        margin: 0 0 14px;
    }
    .scarcity-line {
        display: flex; justify-content: space-between; align-items: center;
        font: 700 .88rem/1.2 'Source Sans 3', sans-serif;
        margin-bottom: 6px;
    }
    .scarcity-line .left { color: #b8232f; display: inline-flex; align-items: center; gap: 6px; }
    .scarcity-line .left .dot {
        width: 8px; height: 8px; border-radius: 50%; background: #b8232f;
        box-shadow: 0 0 0 4px rgba(184,35,47,.15);
    }
    .scarcity-line .right { color: #6b7388; font-weight: 600; font-size: .8rem; }
    .scarcity-track {
        height: 7px; border-radius: 999px;
        background: #ecd9d1; overflow: hidden;
    }
    .scarcity-track > span {
        display: block; height: 100%;
        background: linear-gradient(90deg, var(--danger), var(--amber));
    }

    /* Buy/cart actions row */
    .actions-2 {
        display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 8px;
    }
    .btn-buy {
        padding: 14px; border-radius: 14px; border: 0; cursor: pointer; color: #fff;
        background: linear-gradient(180deg, #ff5b3a, #b8232f);
        font: 800 1.02rem/1.2 'Montserrat', sans-serif; letter-spacing: .03em;
        box-shadow: 0 8px 20px rgba(184,35,47,.3);
    }
    .btn-buy:disabled { opacity: .5; cursor: not-allowed; }
    .btn-cart {
        padding: 14px; border-radius: 14px; cursor: pointer;
        background: #fff; color: var(--brand-navy);
        border: 2px solid var(--brand-navy);
        font: 800 1.02rem/1.2 'Montserrat', sans-serif; letter-spacing: .03em;
    }
    .btn-cart:disabled { opacity: .5; cursor: not-allowed; }
    .btn-wa {
        width: 100%; padding: 12px; border-radius: 14px; border: 0; cursor: pointer; color: #fff;
        background: #25d366; margin-top: 10px;
        font: 700 .98rem/1.2 'Source Sans 3', sans-serif;
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-wa svg { width: 18px; height: 18px; }

    /* Trust tiles row (right column) — compact, clickable */
    .trust-row {
        display: grid; grid-template-columns: repeat(4, minmax(0,1fr));
        gap: 6px; margin: 12px 0 0;
    }
    .trust-row .ttile {
        background: #fff; border: 1px solid var(--line);
        border-radius: 10px; padding: 7px 4px;
        text-align: center; cursor: pointer;
        display: flex; flex-direction: column; align-items: center; gap: 3px;
        transition: border-color .15s, transform .15s, box-shadow .15s;
    }
    .trust-row .ttile:hover {
        border-color: var(--amber);
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(232,118,10,.15);
    }
    .trust-row .ttile svg {
        width: 18px; height: 18px; color: var(--amber-deep);
    }
    .trust-row .ttile strong {
        color: var(--brand-navy); font: 700 .65rem/1.1 'Montserrat', sans-serif;
        letter-spacing: .01em; text-align: center;
    }

    /* Trust info modal */
    .trust-modal-backdrop {
        position: fixed; inset: 0; z-index: 200;
        background: rgba(16,32,58,.6); backdrop-filter: blur(4px);
        display: none; align-items: center; justify-content: center;
        padding: 20px;
    }
    .trust-modal-backdrop.is-open { display: flex; }
    .trust-modal {
        max-width: 460px; width: 100%;
        background: #fff; border-radius: 18px;
        box-shadow: 0 30px 80px rgba(16,32,58,.4);
        overflow: hidden;
        animation: tmIn .25s ease;
    }
    @keyframes tmIn { from { transform: translateY(20px); opacity: 0; } to { transform: none; opacity: 1; } }
    .trust-modal-head {
        padding: 18px 22px;
        background: linear-gradient(135deg, var(--amber), var(--amber-deep));
        color: #fff;
        display: flex; align-items: center; gap: 12px;
    }
    .trust-modal-head svg { width: 28px; height: 28px; }
    .trust-modal-head h3 {
        margin: 0; font: 800 1.15rem/1.2 'Playfair Display', serif;
    }
    .trust-modal-body {
        padding: 18px 22px;
        color: #3a4258; font: 500 .94rem/1.55 'Source Sans 3', sans-serif;
    }
    .trust-modal-body ul { margin: 8px 0 0; padding-left: 20px; }
    .trust-modal-body li { margin: 4px 0; }
    .trust-modal-close {
        position: absolute; top: 12px; right: 12px;
        width: 32px; height: 32px; border-radius: 50%;
        background: rgba(255,255,255,.25); border: 0; color: #fff;
        cursor: pointer; font: 700 1.1rem/1 sans-serif;
    }
    .trust-modal { position: relative; }

    /* Delivery + buyer protection cards */
    .info-card {
        margin-top: 14px; padding: 12px 14px;
        background: #fff; border: 1px solid var(--line);
        border-radius: 14px;
        display: flex; align-items: center; gap: 12px;
    }
    .info-card .ic-icon {
        width: 40px; height: 40px; border-radius: 10px;
        background: linear-gradient(135deg, rgba(232,118,10,.18), rgba(232,118,10,.06));
        color: var(--amber-deep);
        display: inline-flex; align-items: center; justify-content: center; flex: 0 0 40px;
    }
    .info-card .ic-icon svg { width: 22px; height: 22px; }
    .info-card .ic-body { flex: 1; min-width: 0; }
    .info-card .ic-body strong {
        display: block; color: var(--brand-navy);
        font: 800 .92rem/1.2 'Montserrat', sans-serif;
    }
    .info-card .ic-body span {
        color: #6b7388; font: 500 .82rem/1.4 'Source Sans 3', sans-serif;
    }
    .info-card.payhere {
        background: linear-gradient(180deg, #fff 0%, #f5f8ff 100%);
        border-color: #d6dffb;
    }
    .info-card.payhere .payhere-mini {
        height: 22px; width: auto; margin-left: auto;
    }

    /* Product tabs (anchor scroll) */
    .product-tabs {
        position: sticky; top: 76px; z-index: 30;
        display: flex; gap: 4px;
        margin: 22px 0 12px;
        padding: 6px;
        border: 1px solid var(--line); border-radius: 14px;
        background: rgba(255,255,255,.94); backdrop-filter: blur(10px);
        box-shadow: var(--shadow-sm);
        overflow-x: auto;
        transition: background .25s ease, box-shadow .25s ease, border-color .25s ease;
    }
    .product-tabs.is-stuck {
        background: linear-gradient(95deg, #b8232f 0%, #e63946 45%, #ff5b3a 75%, #e8760a 100%);
        border-color: rgba(184,35,47,.4);
        box-shadow: 0 10px 26px rgba(184,35,47,.28);
    }
    .product-tabs a {
        padding: 9px 16px; border-radius: 10px;
        text-decoration: none; color: #4a5468;
        font: 700 .86rem/1 'Montserrat', sans-serif; letter-spacing: .03em;
        white-space: nowrap;
        transition: background .15s, color .15s;
    }
    .product-tabs a:hover { background: rgba(232,118,10,.1); color: var(--amber-deep); }
    .product-tabs.is-stuck a { color: rgba(255,255,255,.92); }
    .product-tabs.is-stuck a:hover { background: rgba(255,255,255,.18); color: #fff; }

    /* Description / specs */
    .desc-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 16px; }
    .feature-bullets {
        list-style: none; padding: 0; margin: 12px 0 0;
        display: grid; gap: 10px;
    }
    .feature-bullets li {
        display: flex; align-items: flex-start; gap: 10px;
        color: #3a4258; font: 600 .94rem/1.5 'Source Sans 3', sans-serif;
    }
    .feature-bullets li::before {
        content: ""; flex: 0 0 18px; height: 18px; margin-top: 4px;
        background: var(--amber); border-radius: 50%;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3'><path d='m5 12 5 5L20 7'/></svg>");
        background-size: 12px; background-repeat: no-repeat; background-position: center;
    }
    .specs-list {
        margin: 0; padding: 0; display: grid; gap: 8px;
        grid-template-columns: max-content 1fr;
        column-gap: 16px;
    }
    .specs-list dt {
        color: #6b7388; font: 700 .8rem/1.4 'Montserrat', sans-serif;
        letter-spacing: .04em; text-transform: uppercase;
    }
    .specs-list dd {
        margin: 0; color: var(--brand-navy);
        font: 600 .92rem/1.4 'Source Sans 3', sans-serif;
    }

    /* Reviews — compact, keep logos+icons */
    .review { min-height: 0 !important; padding: 11px 12px !important; }
    .review-top { margin-bottom: 5px !important; }
    .review-avatar { width: 32px !important; height: 32px !important; flex: 0 0 32px !important; }
    .review-author { font-size: .88rem !important; }
    .review .r-stars { font-size: 1.05rem !important; margin: 1px 0 4px !important; }
    .review p { font-size: .92rem !important; line-height: 1.4 !important; }
    .review-time { font-size: .76rem !important; }
    @media (min-width: 981px) {
        .review-grid { grid-template-columns: repeat(3, minmax(0,1fr)) !important; }
    }
    .reviews-summary-bars {
        display: grid; gap: 4px;
        margin: 8px 0 12px; padding: 10px 14px;
        background: #fff; border: 1px solid var(--line); border-radius: 12px;
    }
    .rbar { display: grid; grid-template-columns: 30px 1fr 36px; align-items: center; gap: 8px; font: 600 .78rem/1 'Montserrat', sans-serif; color: #6b7388; }
    .rbar .track { height: 6px; background: #f0e6d8; border-radius: 999px; overflow: hidden; }
    .rbar .track > span { display: block; height: 100%; background: linear-gradient(90deg, var(--gold), var(--amber)); }

    /* Q&A */
    .qa-list { display: grid; gap: 8px; margin: 10px 0 0; }
    details.qa-item {
        border: 1px solid var(--line); border-radius: 12px;
        background: #fff; padding: 12px 14px;
        font: 500 .93rem/1.5 'Source Sans 3', sans-serif; color: #3a4258;
    }
    details.qa-item summary {
        cursor: pointer; list-style: none;
        font: 700 .94rem/1.3 'Montserrat', sans-serif; color: var(--brand-navy);
        display: flex; align-items: center; gap: 10px;
    }
    details.qa-item summary::before {
        content: "Q"; flex: 0 0 22px; height: 22px;
        background: var(--amber); color: #fff;
        border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font: 800 .76rem/1 'Montserrat', sans-serif;
    }
    details.qa-item[open] summary { color: var(--amber-deep); }
    details.qa-item p { margin: 8px 0 0 32px; }

    /* Best card — deal-card aesthetic */
    .best-card {
        position: relative;
        transition: transform .15s, box-shadow .15s;
    }
    .best-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
    .best-card .best-flag {
        position: absolute; top: 10px; left: 10px; z-index: 2;
        background: var(--grad-fire); color: #fff;
        padding: 4px 10px; border-radius: 6px;
        font: 800 .68rem/1 'Montserrat', sans-serif; letter-spacing: .04em;
        box-shadow: 0 4px 10px rgba(230,57,70,.3);
    }

    /* Sticky mobile add-to-cart */
    .sticky-buy {
        position: fixed; left: 0; right: 0; bottom: 0;
        display: none; align-items: center; gap: 10px;
        padding: 10px 12px calc(10px + env(safe-area-inset-bottom));
        background: rgba(255,255,255,.98); backdrop-filter: blur(14px);
        border-top: 1px solid var(--line);
        z-index: 41;
        box-shadow: 0 -8px 24px rgba(17,31,56,.1);
    }
    .sticky-buy img { width: 42px; height: 42px; border-radius: 8px; object-fit: cover; flex: 0 0 42px; border: 1px solid var(--line); }
    .sticky-buy .sb-info { flex: 1; min-width: 0; }
    .sticky-buy .sb-info .nm { color: var(--brand-navy); font: 700 .82rem/1.2 'Montserrat', sans-serif; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sticky-buy .sb-info .pr { color: #b8232f; font: 800 1rem/1 'Source Sans 3', sans-serif; margin-top: 2px; }
    .sticky-buy button { padding: 10px 14px; border-radius: 999px; border: 0; cursor: pointer; color: #fff; background: linear-gradient(180deg, #ff5b3a, #b8232f); font: 800 .86rem/1 'Montserrat', sans-serif; letter-spacing: .04em; }

    @media (max-width: 720px) {
        .product-hero { padding: 14px; }
        .desc-grid { grid-template-columns: 1fr; }
        .social-proof { grid-template-columns: 1fr 1fr; }
        .trust-row { grid-template-columns: 1fr 1fr; }
        .product-tabs { top: 64px; }
        .sticky-buy { display: flex; }
        body { padding-bottom: 144px !important; }
    }
</style>
</head>
<body>
<?php
$showPromoBar = false;
$headerSearchValue = $searchSeed;
$cartCount = 0;
include __DIR__ . '/partials/site-header.php';
?>

<main class="wrap content">
    <?php if (!$product): ?>
        <div class="not-found">Product not found in local catalog. Run sync first.</div>
    <?php else: ?>
        <?php
        $currentPrice = (float)$product['price'];
        $comparePrice = round($currentPrice * 1.12, 2);
        $savedAmount = max(0.0, $comparePrice - $currentPrice);
        $savedPercent = $comparePrice > 0 ? (int)round(($savedAmount / $comparePrice) * 100) : 0;
        $crumbCategory = $categoryName !== '' ? $categoryName : 'Shop';
        $crumbBrand = (string)($product['brand_name'] ?? '');
        ?>
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="index.php">Home</a>
            <span class="sep">›</span>
            <a href="index.php?q=<?= urlencode($crumbCategory) ?>"><?= htmlspecialchars($crumbCategory) ?></a>
            <?php if ($crumbBrand !== ''): ?>
                <span class="sep">›</span>
                <a href="index.php?q=<?= urlencode($crumbBrand) ?>"><?= htmlspecialchars($crumbBrand) ?></a>
            <?php endif; ?>
            <span class="sep">›</span>
            <span class="current"><?= htmlspecialchars((string)$product['name']) ?></span>
        </nav>

        <section class="product-hero">
        <section class="layout">
            <div>
                <div class="gallery">
                    <div class="gallery-stage">
                        <div class="gallery-badges">
                            <span class="auth">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.5 9 8 10 4.5-1 8-5 8-10V6z"/></svg>
                                100% AUTHENTIC
                            </span>
                            <?php if ($savedPercent > 0): ?>
                                <span class="discount-flag">−<?= $savedPercent ?>% TODAY</span>
                            <?php endif; ?>
                        </div>
                        <img id="mainImage" class="main-image" src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$product['name']) ?>" onerror="this.onerror=null;this.src='assets/images/brand/logo-watercolorlk.png';">
                        <span class="viewing-pill">
                            <span class="pulse" aria-hidden="true"></span>
                            <span id="viewingCount">28</span> people viewing now
                        </span>
                    </div>
                    <div class="thumbs">
                        <button class="thumb active" type="button" data-src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>"><img src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="thumb"></button>
                        <button class="thumb" type="button" data-src="assets/images/mascots/watercolor-brushes-1.webp"><img src="assets/images/mascots/watercolor-brushes-1.webp" alt="thumb"></button>
                        <button class="thumb" type="button" data-src="assets/images/mascots/watercolor-paints.webp"><img src="assets/images/mascots/watercolor-paints.webp" alt="thumb"></button>
                    </div>
                </div>
            </div>

            <aside class="box">
                <?php if (!empty($product['badge'])): ?>
                    <span class="badge"><?= htmlspecialchars((string)$product['badge']) ?></span>
                <?php endif; ?>
                <div class="brand-line"><?= htmlspecialchars($brandLine) ?></div>
                <h1>
                    <?php if ($savedPercent > 0): ?><span class="deal-flag">⚡ FLASH DEAL</span><?php endif; ?>
                    <?= htmlspecialchars((string)$product['name']) ?>
                </h1>

                <div class="rating-row">
                    <span class="stars">★★★★★</span>
                    <span><strong><?= number_format($ratingValue, 1) ?></strong> (<?= number_format($buyersCount) ?> reviews)</span>
                    <a class="g-pill" href="<?= htmlspecialchars($googleProfileUrl) ?>" target="_blank" rel="noopener">
                        <img src="assets/images/google full logo.svg" alt="Google">
                        Verified
                    </a>
                </div>

                <div class="social-proof">
                    <div class="stat"><strong><?= number_format($soldCount) ?></strong><span>Sold</span></div>
                    <div class="stat"><strong><span class="pulse-mini"></span><span id="viewingCount2">28</span></strong><span>Viewing now</span></div>
                    <div class="stat"><strong><?= max(45, (int)round($soldCount * 0.18)) ?></strong><span>Sold this week</span></div>
                </div>

                <div class="price-hero">
                    <div class="price-row">
                        <span class="price">LKR <?= number_format($currentPrice, 2) ?></span>
                        <?php if ($savedAmount > 0): ?>
                            <span class="price-compare">LKR <?= number_format($comparePrice, 2) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($savedAmount > 0): ?>
                        <span class="save-pill">✓ You save LKR <?= number_format($savedAmount, 2) ?> (<?= $savedPercent ?>%)</span>
                        <span class="lowest-30">Lowest price in the last 30 days</span>
                    <?php endif; ?>
                </div>

                <?php if ($savedPercent > 0): ?>
                <div class="deal-countdown" id="dealCountdown">
                    <span class="lightning">⚡</span>
                    <span>Flash deal ends in</span>
                    <span class="timer"><span id="cdH">00</span><span id="cdM">00</span><span id="cdS">00</span></span>
                </div>
                <?php endif; ?>

                <div class="scarcity">
                    <div class="scarcity-line">
                        <span class="left"><span class="dot"></span><?= $isOutOfStock ? 'Out of stock' : ('Only ' . (int)$stock . ' left in stock!') ?></span>
                        <span class="right"><?= max(8, (int)round($soldCount * 0.05)) ?> sold in last 24h</span>
                    </div>
                    <div class="scarcity-track"><span style="width: <?= max(8, min(95, $stockPercent)) ?>%"></span></div>
                </div>

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

                <div class="actions-2">
                    <button class="btn-buy" onclick="submitOrder('payhere')" <?= $isOutOfStock ? 'disabled' : '' ?>>Buy Now — Checkout</button>
                    <button class="btn-cart" type="button" onclick="addToCart()" <?= $isOutOfStock ? 'disabled' : '' ?>>Add to Cart</button>
                </div>
                <button class="btn-wa" type="button" onclick="openWhatsAppOrder()" <?= $isOutOfStock ? 'disabled' : '' ?>>
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 3.5A10 10 0 0 0 4 16l-1 5 5-1A10 10 0 1 0 20 3.5z"/></svg>
                    WhatsApp Order
                </button>

                <div class="trust-row">
                    <button class="ttile" type="button" data-trust="returns">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>
                        <strong>7-day returns</strong>
                    </button>
                    <button class="ttile" type="button" data-trust="tracking">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-6"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                        <strong>Tracked delivery</strong>
                    </button>
                    <button class="ttile" type="button" data-trust="protection">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 6v6c0 5 3.5 9 8 10 4.5-1 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg>
                        <strong>Buyer protection</strong>
                    </button>
                    <button class="ttile" type="button" data-trust="payment">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
                        <strong>Secure payment</strong>
                    </button>
                </div>

                <div class="meta-inline" style="margin-top:14px;">
                    <span class="meta-pill">Brand: <?= htmlspecialchars((string)($product['brand_name'] ?: 'Watercolor.LK')) ?></span>
                    <span class="meta-pill">Category: <?= htmlspecialchars((string)($product['category_name'] ?: 'Art Supplies')) ?></span>
                    <span class="meta-pill">SKU: <?= htmlspecialchars((string)$product['sku']) ?></span>
                </div>
                <div id="orderResult"></div>
            </aside>
        </section>
        </section>

        <nav class="product-tabs" aria-label="Product sections">
            <a href="#description">Description</a>
            <a href="#specs">Specifications</a>
            <a href="#reviews">Reviews (<?= number_format($buyersCount) ?>)</a>
            <a href="#qa">Q &amp; A</a>
        </nav>

        <section id="description" class="module">
            <div class="reviews-head"><h2>Why artists choose this</h2></div>
            <div class="desc-grid">
                <div>
                    <p style="margin:0;color:#4d586f;font-size:.96rem;line-height:1.65;"><?= nl2br(htmlspecialchars((string)$product['description'])) ?></p>
                    <ul class="feature-bullets">
                        <li>100% authentic, sourced directly from <?= htmlspecialchars((string)($product['brand_name'] ?: 'official suppliers')) ?>.</li>
                        <li>Tested and packed by Watercolor.LK artists in Colombo.</li>
                        <li>Backed by 7-day returns and PayHere buyer protection.</li>
                    </ul>
                </div>
                <div id="specs">
                    <h3 style="margin:0 0 8px;color:var(--brand-navy-deep);font:700 1.05rem/1.2 'Playfair Display',serif;">Specifications</h3>
                    <dl class="specs-list">
                        <dt>Brand</dt><dd><?= htmlspecialchars((string)($product['brand_name'] ?: 'Watercolor.LK')) ?></dd>
                        <dt>Category</dt><dd><?= htmlspecialchars((string)($product['category_name'] ?: 'Art Supplies')) ?></dd>
                        <dt>SKU</dt><dd><?= htmlspecialchars((string)$product['sku']) ?></dd>
                        <dt>Stock</dt><dd><?= $isOutOfStock ? 'Out of stock' : ((int)$stock . ' units') ?></dd>
                        <dt>Delivery</dt><dd><?= htmlspecialchars($deliveryLabel) ?></dd>
                    </dl>
                </div>
            </div>
        </section>

        <section id="reviews" class="module" style="margin-top:16px;">
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
            <?php
            $bar5 = 78; $bar4 = 16; $bar3 = 4; $bar2 = 1; $bar1 = 1;
            ?>
            <div class="reviews-summary-bars">
                <div class="rbar"><span>5★</span><span class="track"><span style="width:<?= $bar5 ?>%"></span></span><span><?= $bar5 ?>%</span></div>
                <div class="rbar"><span>4★</span><span class="track"><span style="width:<?= $bar4 ?>%"></span></span><span><?= $bar4 ?>%</span></div>
                <div class="rbar"><span>3★</span><span class="track"><span style="width:<?= $bar3 ?>%"></span></span><span><?= $bar3 ?>%</span></div>
                <div class="rbar"><span>2★</span><span class="track"><span style="width:<?= $bar2 ?>%"></span></span><span><?= $bar2 ?>%</span></div>
                <div class="rbar"><span>1★</span><span class="track"><span style="width:<?= $bar1 ?>%"></span></span><span><?= $bar1 ?>%</span></div>
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
            <a class="review-link" href="<?= htmlspecialchars($googleProfileUrl) ?>" target="_blank" rel="noopener">Write a review on Google</a>
        </section>

        <section id="qa" class="module" style="margin-top:16px;">
            <div class="reviews-head"><h2>Questions &amp; answers</h2></div>
            <div class="qa-list">
                <details class="qa-item">
                    <summary>Is this product 100% authentic?</summary>
                    <p>Yes — every item is sourced directly from the brand or its official Sri Lankan distributor. We never sell counterfeits. You’re protected by PayHere buyer protection and our 7-day returns.</p>
                </details>
                <details class="qa-item">
                    <summary>How long does delivery take across Sri Lanka?</summary>
                    <p>Colombo &amp; suburbs: 1–2 working days. Other districts: 2–3 working days. We dispatch within 24 hours of order confirmation. You’ll receive a tracking link via WhatsApp.</p>
                </details>
                <details class="qa-item">
                    <summary>Can I pay cash on delivery?</summary>
                    <p>Yes. We support PayHere (Visa, Mastercard, eZ Cash, FriMi), bank transfer, and Cash on Delivery island-wide.</p>
                </details>
            </div>
        </section>

        <section class="best-head">
            <h2>Best sellers in <?= htmlspecialchars($categoryName !== '' ? $categoryName : 'this category') ?></h2>
        </section>
        <section class="best-grid">
            <?php if (count($bestSellers) === 0): ?>
                <div class="module" style="grid-column:1 / -1;">More category products will appear after the next sync.</div>
            <?php else: ?>
                <?php foreach ($bestSellers as $idx => $item): ?>
                    <a class="best-card" href="<?= htmlspecialchars(productUrl((string)($item['slug'] ?? ''), (string)($item['display_name'] ?? ''), (int)$item['erp_product_id'])) ?>">
                        <?php if ($idx < 2): ?><span class="best-flag">HOT</span><?php endif; ?>
                        <div class="best-media"><img src="<?= htmlspecialchars((string)($item['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="<?= htmlspecialchars((string)$item['display_name']) ?>"></div>
                        <div class="best-body">
                            <h3 class="best-name"><?= htmlspecialchars((string)$item['display_name']) ?></h3>
                            <div class="best-price">LKR <?= number_format((float)$item['price'], 2) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <div class="sticky-buy" id="stickyBuy">
            <img src="<?= htmlspecialchars((string)($product['image_url'] ?: 'assets/images/brand/logo-watercolorlk.png')) ?>" alt="">
            <div class="sb-info">
                <div class="nm"><?= htmlspecialchars((string)$product['name']) ?></div>
                <div class="pr">LKR <?= number_format($currentPrice, 2) ?></div>
            </div>
            <button onclick="submitOrder('payhere')" <?= $isOutOfStock ? 'disabled' : '' ?>>Buy now</button>
        </div>
    <?php endif; ?>
</main>

<div class="trust-modal-backdrop" id="trustModal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="trust-modal">
        <button class="trust-modal-close" type="button" aria-label="Close" data-trust-close>&times;</button>
        <div class="trust-modal-head">
            <span id="tmIcon"></span>
            <h3 id="tmTitle"></h3>
        </div>
        <div class="trust-modal-body" id="tmBody"></div>
    </div>
</div>

<?php include __DIR__ . '/partials/site-footer.php'; ?>
<?php include __DIR__ . '/partials/site-scripts.php'; ?>

<script>
const headerSearch = document.getElementById('headerSearch') || document.getElementById('search');
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
    name: <?= json_encode((string)$product['name']) ?>,
    slug: <?= json_encode((string)($product['slug'] ?? '')) ?>,
    image_url: <?= json_encode((string)($product['image_url'] ?? '')) ?>
};

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

function getCartItem() {
    return {
        erp_product_id: product.erp_product_id,
        name: product.name,
        slug: product.slug,
        image_url: product.image_url,
        price: product.unit_price,
        sku: product.sku
    };
}

function addToCart() {
    if (!window.WLKCart) return;
    const qty = Number(document.getElementById('qty').value || 1);
    window.WLKCart.add(getCartItem(), qty);
    window.WLKCart.toast(qty + ' added to cart', { action: { href: 'cart.php', label: 'View cart' } });
}

function submitOrder(/* paymentMethod */) {
    if (!window.WLKCart) return;
    const qty = Number(document.getElementById('qty').value || 1);
    // Buy Now: jump straight to checkout with this single item (do not lose existing cart)
    window.WLKCart.add(getCartItem(), qty);
    window.location.href = 'checkout.php?buynow=' + encodeURIComponent(product.erp_product_id);
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
    const getPerPage = () => {
        if (window.matchMedia('(max-width: 720px)').matches) return 1;
        if (window.matchMedia('(max-width: 980px)').matches) return 2;
        return 3;
    };

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

/* ===== Flash deal countdown (resets at midnight) ===== */
(function() {
    const cd = document.getElementById('dealCountdown');
    if (!cd) return;
    const h = document.getElementById('cdH'), m = document.getElementById('cdM'), s = document.getElementById('cdS');
    function pad(n) { return String(n).padStart(2, '0'); }
    function tick() {
        const now = new Date();
        const end = new Date(); end.setHours(24, 0, 0, 0);
        let ms = end - now;
        if (ms < 0) ms = 0;
        const hh = Math.floor(ms / 3.6e6);
        const mm = Math.floor((ms % 3.6e6) / 6e4);
        const ss = Math.floor((ms % 6e4) / 1000);
        h.textContent = pad(hh); m.textContent = pad(mm); s.textContent = pad(ss);
    }
    tick();
    setInterval(tick, 1000);
})();

/* ===== Live viewing count flicker ===== */
(function() {
    const a = document.getElementById('viewingCount');
    const b = document.getElementById('viewingCount2');
    if (!a && !b) return;
    let n = 22 + Math.floor(Math.random() * 18);
    function refresh() {
        n = Math.max(14, Math.min(58, n + (Math.floor(Math.random() * 5) - 2)));
        if (a) a.textContent = String(n);
        if (b) b.textContent = String(n);
    }
    refresh();
    setInterval(refresh, 4500);
})();

/* ===== Smooth tab scroll with sticky header offset ===== */
document.querySelectorAll('.product-tabs a').forEach((a) => {
    a.addEventListener('click', (e) => {
        const id = a.getAttribute('href');
        if (!id || id.charAt(0) !== '#') return;
        const target = document.querySelector(id);
        if (!target) return;
        e.preventDefault();
        const offset = 140;
        const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({ top, behavior: 'smooth' });
    });
});

/* ===== Sticky tabs: switch to attractive gradient when stuck ===== */
(function() {
    const tabs = document.querySelector('.product-tabs');
    if (!tabs) return;
    const sentinel = document.createElement('div');
    sentinel.style.cssText = 'position:absolute;width:1px;height:1px;';
    tabs.parentNode.insertBefore(sentinel, tabs);
    const io = new IntersectionObserver(([entry]) => {
        tabs.classList.toggle('is-stuck', !entry.isIntersecting);
    }, { rootMargin: '-77px 0px 0px 0px', threshold: 0 });
    io.observe(sentinel);
})();

/* ===== Trust-tile info modal ===== */
(function() {
    const modal = document.getElementById('trustModal');
    if (!modal) return;
    const tIcon = document.getElementById('tmIcon');
    const tTitle = document.getElementById('tmTitle');
    const tBody = document.getElementById('tmBody');
    const data = {
        returns: {
            icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>',
            title: '7-day returns',
            body: '<p>Not happy with your order? You have <strong>7 days from delivery</strong> to request a return.</p><ul><li>Item must be unused and in original packaging.</li><li>Refund issued within 3 working days of receiving the return.</li><li>Free pickup for orders over LKR 5,000.</li><li>Damaged or wrong-item orders: full refund or free replacement.</li></ul>'
        },
        tracking: {
            icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-6"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
            title: 'Tracked delivery, island-wide',
            body: '<p>Every parcel ships with a courier tracking number sent via WhatsApp + SMS.</p><ul><li>Colombo &amp; suburbs: 1\u20132 working days.</li><li>Other districts: 2\u20133 working days.</li><li>Free delivery on orders over LKR 5,000.</li><li>Same-day dispatch for orders confirmed before 2 PM.</li></ul>'
        },
        protection: {
            icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 6v6c0 5 3.5 9 8 10 4.5-1 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg>',
            title: 'Watercolor.LK Buyer protection',
            body: '<p>Shop with confidence. Every order is covered by our buyer-protection promise:</p><ul><li><strong>100% authentic products</strong> \u2014 sourced direct from brand or official distributor.</li><li><strong>Money back guarantee</strong> if the item is not as described.</li><li><strong>Secure escrow</strong> via PayHere on card / wallet payments.</li><li>Disputes resolved within 48 hours via WhatsApp support.</li></ul>'
        },
        payment: {
            icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>',
            title: 'Secure payments',
            body: '<p>Choose the payment method you trust:</p><ul><li><strong>PayHere</strong> \u2014 Visa, Mastercard, Amex, eZ Cash, mCash, Genie, FriMi.</li><li><strong>Bank transfer</strong> \u2014 Commercial Bank, BOC, Sampath, HNB.</li><li><strong>Cash on Delivery</strong> \u2014 pay when the courier hands over your parcel.</li></ul><p style="margin-top:10px;color:#6b7388;font-size:.86rem;">All card payments are processed by PayHere on a PCI-DSS compliant gateway. Watercolor.LK never stores card data.</p>'
        }
    };
    function open(key) {
        const d = data[key]; if (!d) return;
        tIcon.innerHTML = d.icon;
        tTitle.textContent = d.title;
        tBody.innerHTML = d.body;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    document.querySelectorAll('.trust-row .ttile[data-trust]').forEach((b) => {
        b.addEventListener('click', () => open(b.dataset.trust));
    });
    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target.closest('[data-trust-close]')) close();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
    });
})();
<?php endif; ?>

<?php if ($product): ?>
/* ===== Persist recently-viewed for homepage rail ===== */
(function() {
    try {
        const item = {
            erp_product_id: <?= (int)$product['erp_product_id'] ?>,
            display_name: <?= json_encode((string)$product['name']) ?>,
            slug: <?= json_encode((string)($product['slug'] ?? '')) ?>,
            image_url: <?= json_encode((string)($product['image_url'] ?? '')) ?>,
            price: <?= json_encode((float)$product['price']) ?>,
            stock_qty: <?= json_encode((float)$product['stock_qty']) ?>,
            ts: Date.now()
        };
        const key = 'wlk_recently_viewed';
        let list = [];
        try { list = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { list = []; }
        list = list.filter(x => x && x.erp_product_id !== item.erp_product_id);
        list.unshift(item);
        list = list.slice(0, 12);
        localStorage.setItem(key, JSON.stringify(list));
    } catch (e) { /* ignore */ }
})();
<?php endif; ?>
</script>
</body>
</html>
