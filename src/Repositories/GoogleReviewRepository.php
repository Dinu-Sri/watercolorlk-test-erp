<?php

declare(strict_types=1);

namespace Repositories;

use PDO;

class GoogleReviewRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Upsert a review into the database.
     * 
     * @param array $review Review data from scraper
     * @return int Database insert/update count
     */
    public function upsertReview(array $review): int
    {
        $sql = <<<SQL
        INSERT INTO google_reviews (
            review_id,
            place_id,
            author,
            rating,
            review_text,
            review_date,
            likes,
            author_profile_url,
            profile_picture_remote_url,
            owner_response,
            language,
            is_active,
            imported_at,
            updated_at
        ) VALUES (
            :review_id,
            :place_id,
            :author,
            :rating,
            :review_text,
            :review_date,
            :likes,
            :author_profile_url,
            :profile_picture_remote_url,
            :owner_response,
            :language,
            :is_active,
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            author = VALUES(author),
            rating = VALUES(rating),
            review_text = VALUES(review_text),
            review_date = VALUES(review_date),
            likes = VALUES(likes),
            author_profile_url = VALUES(author_profile_url),
            profile_picture_remote_url = VALUES(profile_picture_remote_url),
            owner_response = VALUES(owner_response),
            language = VALUES(language),
            updated_at = NOW()
        SQL;

        $stmt = $this->pdo->prepare($sql);

        // Extract review text from nested structure
        $reviewText = '';
        if (is_array($review['description'] ?? null)) {
            $reviewText = $review['description']['en'] ?? implode(' ', $review['description']);
        } else {
            $reviewText = $review['description'] ?? '';
        }

        // Extract owner response
        $ownerResponse = '';
        if (is_array($review['owner_responses'] ?? null) && isset($review['owner_responses']['en'])) {
            $ownerResponse = $review['owner_responses']['en']['text'] ?? '';
        }

        // Parse review date
        $reviewDate = null;
        if (!empty($review['review_date'])) {
            try {
                $reviewDate = date('Y-m-d H:i:s', strtotime($review['review_date']));
            } catch (\Exception $e) {
                $reviewDate = null;
            }
        }

        $stmt->execute([
            ':review_id' => $review['review_id'] ?? '',
            ':place_id' => $review['place_id'] ?? '',
            ':author' => $review['author'] ?? '',
            ':rating' => (float)($review['rating'] ?? 0),
            ':review_text' => $reviewText,
            ':review_date' => $reviewDate,
            ':likes' => (int)($review['likes'] ?? 0),
            ':author_profile_url' => $review['author_profile_url'] ?? '',
            ':profile_picture_remote_url' => $review['profile_picture'] ?? '',
            ':owner_response' => $ownerResponse,
            ':language' => $review['language'] ?? 'en',
            ':is_active' => 1,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Update local path for a profile picture.
     * 
     * @param string $reviewId
     * @param string $localPath
     * @return int
     */
    public function updateProfilePicturePath(string $reviewId, string $localPath): int
    {
        $sql = <<<SQL
        UPDATE google_reviews
        SET profile_picture_local_path = :local_path,
            updated_at = NOW()
        WHERE review_id = :review_id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':local_path' => $localPath,
            ':review_id' => $reviewId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Get all active reviews, ordered by rating then date.
     * 
     * @param int $limit
     * @return array
     */
    public function getAllActive(int $limit = 50): array
    {
        $sql = <<<SQL
        SELECT *
        FROM google_reviews
        WHERE is_active = 1
        ORDER BY rating DESC, review_date DESC
        LIMIT :limit
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get reviews by rating threshold.
     * 
     * @param float $minRating
     * @param int $limit
     * @return array
     */
    public function getByMinRating(float $minRating = 4.0, int $limit = 50): array
    {
        $sql = <<<SQL
        SELECT *
        FROM google_reviews
        WHERE is_active = 1 AND rating >= :min_rating
        ORDER BY rating DESC, review_date DESC
        LIMIT :limit
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':min_rating', $minRating);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get count of reviews.
     * 
     * @return int
     */
    public function getCount(): int
    {
        $sql = 'SELECT COUNT(*) as cnt FROM google_reviews WHERE is_active = 1';
        $result = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Delete all reviews (for fresh import).
     * 
     * @return int
     */
    public function deleteAll(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM google_reviews');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
