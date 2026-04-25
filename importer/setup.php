<?php

declare(strict_types=1);

/**
 * Setup script to create the google_reviews table if it doesn't exist
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line.\n");
}

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║               Google Reviews DB Setup                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = appDb();

    // Check if table exists
    $result = $db->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
         WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
         AND TABLE_NAME = 'google_reviews'"
    );

    if ($result && $result->fetch()) {
        echo "✅ Table 'google_reviews' already exists\n";
    } else {
        echo "📋 Creating table 'google_reviews'...\n";

        $schema = <<<SQL
        CREATE TABLE IF NOT EXISTS google_reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            review_id VARCHAR(255) NOT NULL UNIQUE,
            place_id VARCHAR(255) NOT NULL,
            author VARCHAR(255) NOT NULL,
            rating DECIMAL(3,1) NOT NULL,
            review_text LONGTEXT NULL,
            review_date DATETIME NULL,
            likes INT UNSIGNED DEFAULT 0,
            author_profile_url VARCHAR(500) NULL,
            profile_picture_local_path VARCHAR(500) NULL,
            profile_picture_remote_url VARCHAR(500) NULL,
            owner_response LONGTEXT NULL,
            language VARCHAR(10) DEFAULT 'en',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            imported_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_reviews_place_id (place_id),
            INDEX idx_reviews_rating (rating),
            INDEX idx_reviews_date (review_date),
            INDEX idx_reviews_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        SQL;

        $db->exec($schema);
        echo "✅ Table 'google_reviews' created successfully\n";
    }

    // Count existing reviews
    $count = $db->query("SELECT COUNT(*) as cnt FROM google_reviews")->fetch(PDO::FETCH_ASSOC);
    echo "📊 Reviews in database: " . $count['cnt'] . "\n";

    echo "\n✨ Setup complete! Ready to import reviews.\n";
    exit(0);

} catch (\Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
