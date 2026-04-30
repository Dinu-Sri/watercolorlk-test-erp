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

    private function normalizeText(string $value): string
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
        $reviewText = $this->normalizeText((string)$reviewText);

        // Extract owner response
        $ownerResponse = '';
        if (is_array($review['owner_responses'] ?? null) && isset($review['owner_responses']['en'])) {
            $ownerResponse = $review['owner_responses']['en']['text'] ?? '';
        }
        $ownerResponse = $this->normalizeText((string)$ownerResponse);

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

    /* ---------------------------------------------------------- admin helpers */

    public function getAllForAdmin(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(LOWER(author) LIKE LOWER(:q) OR LOWER(review_text) LIKE LOWER(:q))';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'is_active = :a';
            $params[':a'] = (int)$filters['is_active'];
        }
        if (!empty($filters['min_rating'])) {
            $where[] = 'rating >= :mr';
            $params[':mr'] = (float)$filters['min_rating'];
        }
        $whereSql = implode(' AND ', $where);
        $sql = "SELECT * FROM google_reviews WHERE $whereSql ORDER BY review_date DESC LIMIT :lim OFFSET :off";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':lim', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAdmin(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(LOWER(author) LIKE LOWER(:q) OR LOWER(review_text) LIKE LOWER(:q))';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'is_active = :a';
            $params[':a'] = (int)$filters['is_active'];
        }
        if (!empty($filters['min_rating'])) {
            $where[] = 'rating >= :mr';
            $params[':mr'] = (float)$filters['min_rating'];
        }
        $whereSql = implode(' AND ', $where);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS c FROM google_reviews WHERE $whereSql");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
    }

    public function getOne(string $reviewId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM google_reviews WHERE review_id = :id LIMIT 1');
        $stmt->execute([':id' => $reviewId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertManual(array $data): string
    {
        $reviewId = 'manual-' . bin2hex(random_bytes(8));
        $stmt = $this->pdo->prepare(
            'INSERT INTO google_reviews
             (review_id, place_id, author, rating, review_text, review_date,
              profile_picture_local_path, profile_picture_remote_url, owner_response,
              language, is_active, imported_at, updated_at)
             VALUES (:rid, :pid, :auth, :rate, :text, :date, :ppl, :ppr, :owner, :lang, :a, NOW(), NOW())'
        );
        $stmt->execute([
            ':rid' => $reviewId,
            ':pid' => $data['place_id'] ?? (defined('GOOGLE_PLACE_ID') ? GOOGLE_PLACE_ID : ''),
            ':auth' => trim((string)($data['author'] ?? '')),
            ':rate' => (float)($data['rating'] ?? 5),
            ':text' => $this->normalizeText((string)($data['review_text'] ?? '')),
            ':date' => $data['review_date'] ?: null,
            ':ppl' => $data['profile_picture_local_path'] ?? null,
            ':ppr' => $data['profile_picture_remote_url'] ?? null,
            ':owner' => $this->normalizeText((string)($data['owner_response'] ?? '')),
            ':lang' => $data['language'] ?? 'en',
            ':a' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);
        return $reviewId;
    }

    public function updateOne(string $reviewId, array $data): void
    {
        $allowed = [
            'author', 'rating', 'review_text', 'review_date', 'profile_picture_local_path',
            'profile_picture_remote_url', 'owner_response', 'language', 'is_active',
        ];
        $sets = [];
        $params = [':id' => $reviewId];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "$f = :$f";
                $params[":$f"] = $data[$f];
            }
        }
        if (!$sets) return;
        $sets[] = 'updated_at = NOW()';
        $sql = 'UPDATE google_reviews SET ' . implode(', ', $sets) . ' WHERE review_id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function setActive(string $reviewId, bool $active): void
    {
        $stmt = $this->pdo->prepare('UPDATE google_reviews SET is_active = :a, updated_at = NOW() WHERE review_id = :id');
        $stmt->execute([':a' => $active ? 1 : 0, ':id' => $reviewId]);
    }

    public function deleteOne(string $reviewId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM google_reviews WHERE review_id = :id');
        $stmt->execute([':id' => $reviewId]);
    }
}
