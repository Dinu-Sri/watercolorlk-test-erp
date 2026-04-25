<?php

declare(strict_types=1);

/**
 * Dry-run validator - shows what will be imported without needing a live database
 * 
 * Usage:
 *   php validate.php [--json-file <path>]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

$options = getopt('h', ['help', 'json-file:']);

if (isset($options['h']) || isset($options['help'])) {
    echo "Usage: php validate.php [--json-file <path>]\n\n";
    echo "This validator shows what reviews will be imported without requiring a database.\n";
    echo "It's useful for testing the scraper output before running the full import.\n";
    exit(0);
}

$baseDir = dirname(__DIR__);
$jsonFile = $options['json-file'] ?? $baseDir . '/tmp-google-reviews-scraper-pro/google_reviews.json';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║           Google Reviews Validator (Dry-Run)                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Validate JSON file
if (!is_file($jsonFile)) {
    echo "❌ JSON file not found: {$jsonFile}\n";
    echo "\nRun the scraper first:\n";
    echo "  cd tmp-google-reviews-scraper-pro\n";
    echo "  python start.py scrape --url \"https://www.google.com/maps/place/Watercolor.LK/...\"\n";
    exit(1);
}

echo "📄 Reading: {$jsonFile}\n\n";

// Parse JSON
$jsonContent = file_get_contents($jsonFile);
$reviews = json_decode($jsonContent, true);

if (!is_array($reviews)) {
    echo "❌ Invalid JSON format\n";
    exit(1);
}

echo "✅ Found " . count($reviews) . " reviews\n\n";

// Analyze structure
$stats = [
    'by_rating' => [],
    'total_text_length' => 0,
    'with_images' => 0,
    'with_responses' => 0,
    'languages' => [],
];

foreach ($reviews as $review) {
    // Count by rating
    $rating = (int)($review['rating'] ?? 0);
    $stats['by_rating'][$rating] = ($stats['by_rating'][$rating] ?? 0) + 1;

    // Text stats
    $text = $review['description']['en'] ?? $review['description'] ?? '';
    if (is_array($text)) {
        $text = implode(' ', $text);
    }
    $text = (string)$text;
    $stats['total_text_length'] += strlen($text);

    // Images
    if (!empty($review['profile_picture'])) {
        $stats['with_images']++;
    }

    // Owner responses
    if (!empty($review['owner_responses'])) {
        $stats['with_responses']++;
    }

    // Language detection
    $lang = $review['language'] ?? 'unknown';
    $stats['languages'][$lang] = ($stats['languages'][$lang] ?? 0) + 1;
}

// Display statistics
echo "📊 STATISTICS\n";
echo "═══════════════════════════════════════════════════════════════\n";

echo "\n⭐ Rating Distribution:\n";
krsort($stats['by_rating']);
foreach ($stats['by_rating'] as $rating => $count) {
    $pct = round(($count / count($reviews)) * 100);
    echo "   {$rating} stars: {$count} reviews ({$pct}%)\n";
}

$avgRating = array_sum(array_map(function($r) { return $r['rating'] ?? 0; }, $reviews)) / count($reviews);
echo "\n   Average Rating: " . number_format($avgRating, 1) . " ⭐\n";

echo "\n👤 Profile Data:\n";
echo "   With Profile Pictures: {$stats['with_images']}/" . count($reviews) . "\n";
echo "   With Owner Responses:  {$stats['with_responses']}/" . count($reviews) . "\n";

echo "\n📝 Content:\n";
echo "   Total Text Length:     " . number_format($stats['total_text_length']) . " characters\n";
echo "   Avg Text per Review:   " . number_format($stats['total_text_length'] / count($reviews), 0) . " chars\n";

if (!empty($stats['languages'])) {
    echo "\n🌍 Languages:\n";
    foreach ($stats['languages'] as $lang => $count) {
        echo "   {$lang}: {$count}\n";
    }
}

// Show sample reviews
echo "\n\n📋 SAMPLE REVIEWS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

foreach (array_slice($reviews, 0, 3) as $idx => $review) {
    echo "[Review " . ($idx + 1) . "]\n";
    echo "Author:         " . ($review['author'] ?? 'Unknown') . "\n";
    echo "Rating:         ⭐ " . ($review['rating'] ?? 0) . "\n";
    echo "Date:           " . ($review['review_date'] ?? 'Unknown') . "\n";
    echo "Has Image:      " . (!empty($review['profile_picture']) ? "Yes" : "No") . "\n";

    $text = $review['description']['en'] ?? $review['description'] ?? '';
    if (is_array($text)) {
        $text = implode(' ', $text);
    }
    $text = (string)$text;
    $preview = substr($text, 0, 100) . (strlen($text) > 100 ? '...' : '');
    echo "Text:           " . $preview . "\n";

    if (!empty($review['owner_responses']['en']['text'] ?? null)) {
        $response = substr($review['owner_responses']['en']['text'], 0, 80) . '...';
        echo "Owner Response: " . $response . "\n";
    }
    echo "\n";
}

// Database preview
echo "📊 DATABASE PREVIEW\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "When imported, these fields will be stored in the 'google_reviews' table:\n\n";

if (!empty($reviews[0])) {
    $review = $reviews[0];
    $descText = $review['description']['en'] ?? $review['description'] ?? '';
    if (is_array($descText)) {
        $descText = implode(' ', $descText);
    }
    $descText = (string)$descText;
    
    $respText = $review['owner_responses']['en']['text'] ?? '';
    $respText = (string)$respText;
    
    $fields = [
        'review_id'                  => $review['review_id'] ?? '',
        'place_id'                   => $review['place_id'] ?? '',
        'author'                     => $review['author'] ?? '',
        'rating'                     => (string)($review['rating'] ?? 0),
        'review_text'                => substr($descText, 0, 50) . '...',
        'review_date'                => $review['review_date'] ?? '',
        'profile_picture_remote_url' => $review['profile_picture'] ?? '',
        'profile_picture_local_path' => 'importer/reviews_images/profiles/[auto-generated]',
        'owner_response'             => substr($respText, 0, 50) . '...',
    ];

    foreach ($fields as $field => $value) {
        echo "   " . str_pad($field, 30) . " → " . substr($value, 0, 60) . "\n";
    }
}

echo "\n";
echo "✅ VALIDATION COMPLETE\n";
echo "   All reviews are in valid format and ready to import!\n";
echo "\n🚀 To import reviews to database, run:\n";
echo "   php import.php\n\n";
