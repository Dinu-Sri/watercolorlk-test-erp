-- Emoji/Sinhala safe fix for google_reviews text storage
-- Run this inside the target database in phpMyAdmin (left sidebar selected DB).

SET NAMES utf8mb4;

-- 1) Optional admin-only step (usually blocked in shared hosting).
--    Keep commented unless you have ALTER DATABASE privilege.
-- ALTER DATABASE `your_database_name`
--   CHARACTER SET = utf8mb4
--   COLLATE = utf8mb4_unicode_ci;

-- 2) Ensure table + existing data are converted to utf8mb4
ALTER TABLE google_reviews
  CONVERT TO CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- 3) Compatibility step: if old schema has `author_url`, rename it to `author_profile_url`.
SET @db_name = DATABASE();
SELECT COUNT(*) INTO @has_author_profile_url
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'google_reviews'
  AND COLUMN_NAME = 'author_profile_url';

SELECT COUNT(*) INTO @has_author_url
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'google_reviews'
  AND COLUMN_NAME = 'author_url';

SET @sql = IF(
  @has_author_profile_url = 0 AND @has_author_url = 1,
  'ALTER TABLE google_reviews CHANGE author_url author_profile_url VARCHAR(500) NULL',
  'SELECT "skip rename author_url"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4) Force text columns to utf8mb4 explicitly (only when a column exists).
SELECT COUNT(*) INTO @has_author
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'google_reviews' AND COLUMN_NAME = 'author';
SET @sql = IF(
  @has_author = 1,
  'ALTER TABLE google_reviews MODIFY author VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL',
  'SELECT "skip author"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_review_text
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'google_reviews' AND COLUMN_NAME = 'review_text';
SET @sql = IF(
  @has_review_text = 1,
  'ALTER TABLE google_reviews MODIFY review_text LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
  'SELECT "skip review_text"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_owner_response
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'google_reviews' AND COLUMN_NAME = 'owner_response';
SET @sql = IF(
  @has_owner_response = 1,
  'ALTER TABLE google_reviews MODIFY owner_response LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
  'SELECT "skip owner_response"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_author_profile_url_after
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'google_reviews' AND COLUMN_NAME = 'author_profile_url';
SET @sql = IF(
  @has_author_profile_url_after = 1,
  'ALTER TABLE google_reviews MODIFY author_profile_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
  'SELECT "skip author_profile_url"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_profile_picture_local_path
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'google_reviews' AND COLUMN_NAME = 'profile_picture_local_path';
SET @sql = IF(
  @has_profile_picture_local_path = 1,
  'ALTER TABLE google_reviews MODIFY profile_picture_local_path VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
  'SELECT "skip profile_picture_local_path"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_profile_picture_remote_url
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'google_reviews' AND COLUMN_NAME = 'profile_picture_remote_url';
SET @sql = IF(
  @has_profile_picture_remote_url = 1,
  'ALTER TABLE google_reviews MODIFY profile_picture_remote_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
  'SELECT "skip profile_picture_remote_url"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_language
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'google_reviews' AND COLUMN_NAME = 'language';
SET @sql = IF(
  @has_language = 1,
  'ALTER TABLE google_reviews MODIFY language VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT "en"',
  'SELECT "skip language"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5) Verify table/column charset-collation
SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_COLLATION
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'google_reviews';

SELECT COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'google_reviews'
  AND DATA_TYPE IN ('varchar', 'text', 'mediumtext', 'longtext');

-- 6) Optional smoke test with emoji + Sinhala
-- (remove after testing)
-- UPDATE google_reviews
-- SET review_text = 'Emoji test ❤️⭐ සහ සිංහල text test'
-- WHERE review_id = 'PUT_A_REAL_REVIEW_ID_HERE';
