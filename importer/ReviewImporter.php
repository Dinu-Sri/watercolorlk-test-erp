<?php

declare(strict_types=1);

class ReviewImporter
{
    private $pdo;
    private $reviewRepository;
    private $imageDir;
    private $jsonFile;
    private $importLog = [];

    public function __construct($pdo, $reviewRepository, string $imageDir, string $jsonFile)
    {
        $this->pdo = $pdo;
        $this->reviewRepository = $reviewRepository;
        $this->imageDir = rtrim($imageDir, '/');
        $this->jsonFile = $jsonFile;
    }

    /**
     * Run the full import process.
     * 
     * @param bool $deleteExisting Delete existing reviews before importing
     * @return array Import statistics
     */
    public function import(bool $deleteExisting = false): array
    {
        $this->log('=== Starting Google Reviews Import ===');

        // Validate JSON file exists
        if (!is_file($this->jsonFile)) {
            $this->log("ERROR: JSON file not found: {$this->jsonFile}");
            return $this->getStats();
        }

        // Read and parse JSON
        $jsonContent = file_get_contents($this->jsonFile);
        $reviews = json_decode($jsonContent, true);

        if (!is_array($reviews)) {
            $this->log("ERROR: Invalid JSON format in {$this->jsonFile}");
            return $this->getStats();
        }

        $this->log("Found " . count($reviews) . " reviews in JSON file");

        // Delete existing if requested
        if ($deleteExisting) {
            $deleted = $this->reviewRepository->deleteAll();
            $this->log("Deleted {$deleted} existing reviews");
        }

        // Process each review
        $stats = [
            'total' => count($reviews),
            'imported' => 0,
            'updated' => 0,
            'image_downloaded' => 0,
            'image_failed' => 0,
        ];

        foreach ($reviews as $idx => $review) {
            try {
                // Insert/update review in database
                $result = $this->reviewRepository->upsertReview($review);
                
                if ($result > 0) {
                    $stats['imported']++;
                    $this->log("[{$idx}] Imported review by {$review['author']} (⭐ {$review['rating']})");
                } else {
                    $stats['updated']++;
                    $this->log("[{$idx}] Updated review by {$review['author']}");
                }

                // Download and store profile picture
                if (!empty($review['profile_picture'])) {
                    $imagePath = $this->downloadProfileImage($review);
                    if ($imagePath) {
                        $this->reviewRepository->updateProfilePicturePath(
                            $review['review_id'],
                            $imagePath
                        );
                        $stats['image_downloaded']++;
                        $this->log("[{$idx}] Profile image saved: {$imagePath}");
                    } else {
                        $stats['image_failed']++;
                        $this->log("[{$idx}] Failed to download profile image");
                    }
                }

            } catch (\Exception $e) {
                $this->log("ERROR [{$idx}]: " . $e->getMessage());
            }
        }

        $this->log('=== Import Complete ===');
        $this->log("Imported: {$stats['imported']}, Updated: {$stats['updated']}, Images: {$stats['image_downloaded']}/{$stats['total']}");

        return $stats;
    }

    /**
     * Download and store a profile picture locally.
     * 
     * @param array $review
     * @return string|null Local relative path or null on failure
     */
    private function downloadProfileImage(array $review): ?string
    {
        $imageUrl = $review['profile_picture'] ?? '';
        if (empty($imageUrl)) {
            return null;
        }

        try {
            // Generate filename from review ID
            $reviewId = $review['review_id'] ?? 'unknown';
            $ext = 'jpg'; // Google typically serves JPG
            $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($reviewId, 0, 32)) . ".{$ext}";

            $targetPath = $this->imageDir . '/profiles/' . $filename;
            $absolutePath = dirname(__DIR__) . '/' . ltrim($targetPath, '/');

            // Ensure directory exists
            $dir = dirname($absolutePath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            // Skip if already exists
            if (is_file($absolutePath)) {
                return $targetPath;
            }

            // Download image with timeout
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

            $imageData = @file_get_contents($imageUrl, false, $context);
            if ($imageData === false) {
                $this->log("Could not fetch image from: {$imageUrl}");
                return null;
            }

            // Validate it's actually an image
            if (strpos($imageData, 'GIF89a') !== 0 && strpos($imageData, 'GIF87a') !== 0 &&
                strpos($imageData, "\xFF\xD8\xFF") !== 0 && strpos($imageData, "\x89PNG") !== 0) {
                $this->log("Downloaded file is not a valid image: {$imageUrl}");
                return null;
            }

            // Write to disk
            if (@file_put_contents($absolutePath, $imageData) === false) {
                $this->log("Could not write image to disk: {$absolutePath}");
                return null;
            }

            return $targetPath;

        } catch (\Exception $e) {
            $this->log("Image download error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Log a message to both stdout and internal log.
     * 
     * @param string $message
     */
    private function log(string $message): void
    {
        echo $message . "\n";
        $this->importLog[] = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    }

    /**
     * Get import statistics.
     * 
     * @return array
     */
    private function getStats(): array
    {
        return [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'image_downloaded' => 0,
            'image_failed' => 0,
        ];
    }

    /**
     * Get full log.
     * 
     * @return array
     */
    public function getLog(): array
    {
        return $this->importLog;
    }

    /**
     * Write log to file.
     * 
     * @param string $logFile
     */
    public function saveLog(string $logFile): void
    {
        file_put_contents($logFile, implode("\n", $this->importLog) . "\n");
    }
}
