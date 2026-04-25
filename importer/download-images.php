<?php

declare(strict_types=1);

/**
 * Image Downloader - Downloads profile images from JSON file
 * Useful for testing image download functionality without importing to database
 * 
 * Usage:
 *   php download-images.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

$baseDir = dirname(__DIR__);
$jsonFile = $baseDir . '/tmp-google-reviews-scraper-pro/google_reviews.json';
$imageDir = __DIR__ . '/reviews_images/profiles';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║            Profile Image Downloader                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Validate JSON
if (!is_file($jsonFile)) {
    echo "❌ JSON file not found: {$jsonFile}\n";
    exit(1);
}

// Ensure directory exists
if (!is_dir($imageDir)) {
    mkdir($imageDir, 0755, true);
    echo "📁 Created directory: {$imageDir}\n";
}

// Read reviews
$jsonContent = file_get_contents($jsonFile);
$reviews = json_decode($jsonContent, true);

if (!is_array($reviews)) {
    echo "❌ Invalid JSON\n";
    exit(1);
}

echo "📄 Processing " . count($reviews) . " reviews...\n\n";

$downloaded = 0;
$failed = 0;
$skipped = 0;

foreach ($reviews as $idx => $review) {
    $imageUrl = $review['profile_picture'] ?? '';
    if (empty($imageUrl)) {
        echo "[" . ($idx + 1) . "] ⏭️  No image URL (skipped)\n";
        $skipped++;
        continue;
    }

    // Generate filename
    $reviewId = $review['review_id'] ?? 'unknown';
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($reviewId, 0, 32)) . '.jpg';
    $filePath = $imageDir . '/' . $filename;

    // Skip if already exists
    if (is_file($filePath)) {
        echo "[" . ($idx + 1) . "] ✅ Already exists: {$filename} (" . round(filesize($filePath) / 1024, 1) . "KB)\n";
        $skipped++;
        continue;
    }

    // Download
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
            'https' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);

        echo "[" . ($idx + 1) . "] 📥 Downloading: {$review['author']}...";

        $imageData = @file_get_contents($imageUrl, false, $context);
        if ($imageData === false) {
            echo " ❌ Failed to fetch\n";
            $failed++;
            continue;
        }

        // Validate image
        $isValid = (
            strpos($imageData, 'GIF89a') === 0 || 
            strpos($imageData, 'GIF87a') === 0 ||
            strpos($imageData, "\xFF\xD8\xFF") === 0 || 
            strpos($imageData, "\x89PNG") === 0
        );

        if (!$isValid) {
            echo " ❌ Not a valid image\n";
            $failed++;
            continue;
        }

        // Save
        if (@file_put_contents($filePath, $imageData) === false) {
            echo " ❌ Failed to write to disk\n";
            $failed++;
            continue;
        }

        $sizeKb = round(strlen($imageData) / 1024, 1);
        echo " ✅ {$filename} ({$sizeKb}KB)\n";
        $downloaded++;

    } catch (\Exception $e) {
        echo " ❌ Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "✅ Downloaded: {$downloaded}\n";
echo "❌ Failed:     {$failed}\n";
echo "⏭️  Skipped:    {$skipped}\n";
echo "\n🖼️  Images stored in: {$imageDir}/\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// List downloaded files
$files = glob($imageDir . '/*.jpg');
if (!empty($files)) {
    echo "📂 Downloaded Files:\n";
    foreach ($files as $file) {
        $size = round(filesize($file) / 1024, 1);
        echo "   ✓ " . basename($file) . " ({$size}KB)\n";
    }
}

echo "\n";
