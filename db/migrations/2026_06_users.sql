-- =============================================================
-- 2026_06_users.sql
-- Customer accounts: signup/login (email + Google), verification,
-- password reset, addresses, link orders to users.
-- Idempotent (safe to re-run on MariaDB 11.4+).
-- =============================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `email_verified_at` DATETIME NULL DEFAULT NULL,
    `password_hash` VARCHAR(255) NULL DEFAULT NULL,
    `full_name` VARCHAR(160) NULL DEFAULT NULL,
    `phone` VARCHAR(64) NULL DEFAULT NULL,
    `google_sub` VARCHAR(64) NULL DEFAULT NULL,
    `avatar_url` VARCHAR(500) NULL DEFAULT NULL,
    `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_email` (`email`),
    UNIQUE KEY `uniq_users_google_sub` (`google_sub`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_tokens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `kind` ENUM('verify_email','reset_password') NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_tokens_hash` (`token_hash`),
    KEY `idx_user_tokens_user_kind` (`user_id`, `kind`),
    CONSTRAINT `fk_user_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_addresses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `full_name` VARCHAR(160) NULL,
    `phone` VARCHAR(64) NULL,
    `address_line` TEXT NULL,
    `city` VARCHAR(120) NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_addresses_user` (`user_id`),
    CONSTRAINT `fk_user_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `orders`
    ADD COLUMN IF NOT EXISTS `user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `customer_phone`,
    ADD KEY IF NOT EXISTS `idx_orders_user` (`user_id`);
