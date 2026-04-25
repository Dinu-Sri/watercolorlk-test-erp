<?php

declare(strict_types=1);

/**
 * Google Reviews Importer CLI Script
 * 
 * Usage:
 *   php import.php [options]
 * 
 * Options:
 *   --json-file <path>      Path to JSON file from scraper (default: ../tmp-google-reviews-scraper-pro/google_reviews.json)
 *   --delete-existing       Delete all existing reviews before importing (default: false)
 *   --help                  Show this help message
 * 
 * Example:
 *   php import.php --json-file ../tmp-google-reviews-scraper-pro/google_reviews.json
 *   php import.php --json-file ../tmp-google-reviews-scraper-pro/google_reviews.json --delete-existing
 */

// Exit if not CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

// Parse command-line arguments
$options = getopt('h', [
    'help',
    'json-file:',
    'delete-existing',
]);

if (isset($options['h']) || isset($options['help'])) {
    echo file_get_contents(__FILE__);
    exit(0);
}

// Base paths
$baseDir = dirname(__DIR__);
$jsonFile = $options['json-file'] ?? $baseDir . '/tmp-google-reviews-scraper-pro/google_reviews.json';
$deleteExisting = isset($options['delete-existing']);
$imageDir = __DIR__ . '/reviews_images';

// Bootstrap
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/src/Repositories/GoogleReviewRepository.php';
require_once __DIR__ . '/ReviewImporter.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        Google Reviews Importer for Watercolor.LK              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Validate JSON file
if (!is_file($jsonFile)) {
    echo "❌ ERROR: JSON file not found at: {$jsonFile}\n";
    echo "   Make sure you've run the scraper first:\n";
    echo "   cd tmp-google-reviews-scraper-pro\n";
    echo "   python start.py scrape --url <YOUR_MAPS_URL>\n";
    exit(1);
}

echo "📄 JSON File: {$jsonFile}\n";
echo "🖼️  Image Dir: {$imageDir}\n";
echo "🗑️  Delete Existing: " . ($deleteExisting ? 'YES' : 'NO') . "\n";
echo "\n";

try {
    // Initialize
    $db = appDb();
    $reviewRepo = new Repositories\GoogleReviewRepository($db);
    $importer = new ReviewImporter($db, $reviewRepo, $imageDir, $jsonFile);

    // Run import
    $stats = $importer->import($deleteExisting);

    // Display results
    echo "\n";
    echo "✅ IMPORT COMPLETE\n";
    echo "  Total Processed:    " . $stats['total'] . "\n";
    echo "  Newly Imported:     " . $stats['imported'] . "\n";
    echo "  Updated:            " . $stats['updated'] . "\n";
    echo "  Images Downloaded:  " . $stats['image_downloaded'] . "/" . $stats['total'] . "\n";
    if ($stats['image_failed'] > 0) {
        echo "  ⚠️  Image Failures:  " . $stats['image_failed'] . "\n";
    }

    // Save log
    $logFile = __DIR__ . '/import_' . date('Y-m-d_H-i-s') . '.log';
    $importer->saveLog($logFile);
    echo "\n📋 Log saved to: {$logFile}\n";

    // Database summary
    $totalCount = $reviewRepo->getCount();
    echo "📊 Total Reviews in DB: {$totalCount}\n";

    echo "\n✨ You can now view reviews on your product page!\n";
    exit(0);

} catch (\Exception $e) {
    echo "\n❌ IMPORT FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nStacktrace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
