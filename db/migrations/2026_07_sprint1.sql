-- Watercolor.LK — Phase D Sprint 1 migration
-- Adds: auth_attempts (rate-limiting), orders.payment_status (groundwork for Sprint 2),
-- orders.cancelled_at + stock_restored (stock decrement bookkeeping)
--
-- Idempotent. Safe to re-run.

-- ============================================================================
-- 1) auth_attempts — used by RateLimiter to throttle login/forgot/reset/signup
-- ============================================================================
CREATE TABLE IF NOT EXISTS `auth_attempts` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip`         VARCHAR(45)  NOT NULL,
    `email_hash` CHAR(64)     DEFAULT NULL,           -- sha256 of lowercased email
    `kind`       ENUM('login','forgot','reset','signup','google') NOT NULL,
    `success`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `ix_attempts_ip_kind_time`    (`ip`, `kind`, `created_at`),
    KEY `ix_attempts_email_kind_time` (`email_hash`, `kind`, `created_at`),
    KEY `ix_attempts_created_at`      (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2) orders.cancelled_at + stock_restored
-- (stock_restored prevents double-restore on cancel)
-- ============================================================================
ALTER TABLE `orders`
    ADD COLUMN IF NOT EXISTS `cancelled_at`   DATETIME NULL AFTER `updated_at`,
    ADD COLUMN IF NOT EXISTS `stock_restored` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cancelled_at`;

-- ============================================================================
-- 3) Groundwork columns referenced by Sprint 2 (safe to add now)
-- ============================================================================
ALTER TABLE `orders`
    ADD COLUMN IF NOT EXISTS `payment_status` ENUM('unpaid','pending_verification','paid','failed','refunded')
        NOT NULL DEFAULT 'unpaid' AFTER `status`;

-- Backfill payment_status for historical rows: anything 'completed' = paid.
UPDATE `orders`
   SET `payment_status` = 'paid'
 WHERE `payment_status` = 'unpaid'
   AND `status` IN ('completed','shipped','delivered');
