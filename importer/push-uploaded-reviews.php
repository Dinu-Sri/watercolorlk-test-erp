<?php

declare(strict_types=1);

/**
 * Push uploaded Google reviews + profile images into MySQL.
 *
 * This script is intended for cPanel/server usage after you upload:
 * 1) tmp-google-reviews-scraper-pro/google_reviews.json
 * 2) tmp-google-reviews-scraper-pro/review_images/
 *
 * Usage:
 *   php importer/push-uploaded-reviews.php
 *   php importer/push-uploaded-reviews.php --delete-existing
 *   php importer/push-uploaded-reviews.php --json-file /path/google_reviews.json --images-dir /path/review_images
 */

$baseDir = dirname(__DIR__);
$isCli = php_sapi_name() === 'cli';

require_once $baseDir . '/bootstrap.php';

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');

    $providedKey = (string)($_GET['key'] ?? '');
    if ($providedKey === '' || !hash_equals(SYNC_WEBHOOK_KEY, $providedKey)) {
        http_response_code(403);
        echo "Forbidden\n";
        exit(1);
    }
}

$options = [];
if ($isCli) {
    $options = getopt('h', [
        'help',
        'json-file:',
        'images-dir:',
        'delete-existing',
    ]);

    if (isset($options['h']) || isset($options['help'])) {
        echo "Usage: php importer/push-uploaded-reviews.php [options]\n\n";
        echo "Options:\n";
        echo "  --json-file <path>   Path to uploaded JSON (default: ../tmp-google-reviews-scraper-pro/google_reviews.json)\n";
        echo "  --images-dir <path>  Path to uploaded review_images dir (default: ../tmp-google-reviews-scraper-pro/review_images)\n";
        echo "  --delete-existing    Delete existing rows before import\n";
        echo "  --help               Show this help\n";
        exit(0);
    }
} else {
    $options = [
        'json-file' => isset($_GET['json']) ? (string)$_GET['json'] : null,
        'images-dir' => isset($_GET['images']) ? (string)$_GET['images'] : null,
        'delete-existing' => isset($_GET['delete_existing']) && $_GET['delete_existing'] === '1',
    ];
}

$jsonFile = $options['json-file'] ?? $baseDir . '/tmp-google-reviews-scraper-pro/google_reviews.json';
$imagesDir = $options['images-dir'] ?? $baseDir . '/tmp-google-reviews-scraper-pro/review_images';
$deleteExisting = false;
if ($isCli) {
    $deleteExisting = isset($options['delete-existing']);
} else {
    $deleteExisting = !empty($options['delete-existing']);
}
$localProfilesDir = __DIR__ . '/reviews_images/profiles';
$dbProfilePathPrefix = 'importer/reviews_images/profiles';
require_once $baseDir . '/src/Repositories/GoogleReviewRepository.php';

function sanitizePathComponent(string $name): string
{
    $name = trim(str_replace("\0", '', $name));
    if ($name === '') {
        return 'default';
    }

    $name = preg_replace('/[<>:"\\/\\\\|?*]/', '_', $name);
    $name = preg_replace('/\s+/', '_', (string)$name);
    $name = trim((string)$name, '._-');

    return $name !== '' ? $name : 'default';
}

function buildImageIndex(string $rootDir): array
{
    if (!is_dir($rootDir)) {
        return [];
    }

    $index = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $name = $file->getBasename();
        if (!isset($index[$name])) {
            $index[$name] = $file->getPathname();
        }
    }

    return $index;
}

function extractReviewText(array $review): string
{
    $description = $review['description'] ?? '';

    if (is_array($description)) {
        if (isset($description['en']) && is_string($description['en'])) {
            return $description['en'];
        }

        return implode(' ', array_map(static fn($v): string => is_scalar($v) ? (string)$v : '', $description));
    }

    return is_scalar($description) ? (string)$description : '';
}

function extractOwnerResponse(array $review): string
{
    $responses = $review['owner_responses'] ?? [];

    if (is_array($responses)) {
        if (isset($responses['en']['text']) && is_scalar($responses['en']['text'])) {
            return (string)$responses['en']['text'];
        }

        foreach ($responses as $response) {
            if (is_array($response) && isset($response['text']) && is_scalar($response['text'])) {
                return (string)$response['text'];
            }
        }
    }

    return '';
}

function normalizeReviewDate(?string $input): ?string
{
    if (!$input) {
        return null;
    }

    $ts = strtotime($input);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $ts);
}

echo "\n";
echo "================================================================\n";
echo " Push Uploaded Reviews to MySQL\n";
echo "================================================================\n\n";

echo "JSON file: {$jsonFile}\n";
echo "Images dir: {$imagesDir}\n";
echo "Delete existing: " . ($deleteExisting ? 'YES' : 'NO') . "\n\n";

if (!is_file($jsonFile)) {
    echo "ERROR: JSON file not found: {$jsonFile}\n";
    exit(1);
}

if (!is_dir($localProfilesDir) && !mkdir($localProfilesDir, 0755, true) && !is_dir($localProfilesDir)) {
    echo "ERROR: Cannot create target profile images directory: {$localProfilesDir}\n";
    exit(1);
}

$jsonRaw = file_get_contents($jsonFile);
$decoded = json_decode((string)$jsonRaw, true);

if (!is_array($decoded)) {
    echo "ERROR: Invalid JSON format\n";
    exit(1);
}

$reviews = array_values($decoded);
$imageIndex = buildImageIndex($imagesDir);

try {
    $db = appDb();
    $repo = new Repositories\GoogleReviewRepository($db);

    if ($deleteExisting) {
        $deleted = $repo->deleteAll();
        echo "Deleted existing rows: {$deleted}\n";
    }

    $stats = [
        'total' => count($reviews),
        'imported_or_updated' => 0,
        'skipped' => 0,
        'images_linked' => 0,
        'images_missing' => 0,
    ];

    foreach ($reviews as $idx => $review) {
        $reviewId = (string)($review['review_id'] ?? '');
        if ($reviewId === '') {
            $stats['skipped']++;
            echo "[{$idx}] Skipped review without review_id\n";
            continue;
        }

        $normalized = [
            'review_id' => $reviewId,
            'place_id' => (string)($review['place_id'] ?? ''),
            'author' => (string)($review['author'] ?? ''),
            'rating' => (float)($review['rating'] ?? 0),
            'description' => extractReviewText($review),
            'review_date' => normalizeReviewDate((string)($review['review_date'] ?? '')),
            'likes' => (int)($review['likes'] ?? 0),
            'author_profile_url' => (string)($review['author_profile_url'] ?? ''),
            'profile_picture' => (string)($review['profile_picture'] ?? ''),
            'owner_responses' => ['en' => ['text' => extractOwnerResponse($review)]],
            'language' => (string)($review['language'] ?? 'en'),
        ];

        $repo->upsertReview($normalized);
        $stats['imported_or_updated']++;

        $localProfile = (string)($review['local_profile_picture'] ?? '');
        if ($localProfile === '') {
            $stats['images_missing']++;
            echo "[{$idx}] Imported review {$reviewId} (no local profile image in JSON)\n";
            continue;
        }

        $filename = basename($localProfile);
        $placeId = sanitizePathComponent((string)($review['place_id'] ?? ''));

        $candidates = [
            rtrim($imagesDir, '/\\') . DIRECTORY_SEPARATOR . $placeId . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . $filename,
            rtrim($imagesDir, '/\\') . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . $filename,
        ];

        $source = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $source = $candidate;
                break;
            }
        }

        if ($source === null && isset($imageIndex[$filename]) && is_file($imageIndex[$filename])) {
            $source = $imageIndex[$filename];
        }

        if ($source === null) {
            $stats['images_missing']++;
            echo "[{$idx}] Imported review {$reviewId} (image not found for {$filename})\n";
            continue;
        }

        $target = rtrim($localProfilesDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($target)) {
            if (!copy($source, $target)) {
                $stats['images_missing']++;
                echo "[{$idx}] Imported review {$reviewId} (failed copying {$filename})\n";
                continue;
            }
        }

        $dbPath = $dbProfilePathPrefix . '/' . $filename;
        $repo->updateProfilePicturePath($reviewId, $dbPath);
        $stats['images_linked']++;

        echo "[{$idx}] Imported review {$reviewId} with image {$filename}\n";
    }

    echo "\nDone.\n";
    echo "Total: {$stats['total']}\n";
    echo "Imported/Updated: {$stats['imported_or_updated']}\n";
    echo "Skipped: {$stats['skipped']}\n";
    echo "Images linked: {$stats['images_linked']}\n";
    echo "Images missing: {$stats['images_missing']}\n";

    exit(0);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
