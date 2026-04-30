-- =============================================================================
-- Watercolor.LK — Admin Dashboard schema migration
-- Date: 2026-05
-- Idempotent: safe to re-run. CREATE TABLE IF NOT EXISTS / INSERT IGNORE used.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- 1. admin_users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(80) NOT NULL,
    `email` VARCHAR(190) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `display_name` VARCHAR(120) DEFAULT NULL,
    `role` ENUM('super','editor') NOT NULL DEFAULT 'editor',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `password_reset_token` VARCHAR(120) DEFAULT NULL,
    `password_reset_expires_at` DATETIME DEFAULT NULL,
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_username` (`username`),
    UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. admin_login_attempts (throttle)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_login_attempts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(64) NOT NULL,
    `username` VARCHAR(80) DEFAULT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ip_time` (`ip`, `attempted_at`),
    KEY `idx_user_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. admin_audit_log
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_audit_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(80) NOT NULL,
    `target_type` VARCHAR(60) DEFAULT NULL,
    `target_id` VARCHAR(80) DEFAULT NULL,
    `payload_json` LONGTEXT DEFAULT NULL,
    `ip` VARCHAR(64) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_user_time` (`admin_user_id`, `created_at`),
    KEY `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. categories
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(120) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `image_url` VARCHAR(500) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `seo_title` VARCHAR(190) DEFAULT NULL,
    `seo_description` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_slug` (`slug`),
    KEY `idx_parent` (`parent_id`),
    KEY `idx_visible_sort` (`is_visible`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. storefront_products  — public-facing catalog row
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `storefront_products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kind` ENUM('simple','combined','pack') NOT NULL DEFAULT 'simple',
    `slug` VARCHAR(190) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `subtitle` VARCHAR(255) DEFAULT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `hero_image_url` VARCHAR(500) DEFAULT NULL,
    `gallery_json` LONGTEXT DEFAULT NULL,
    `badge` VARCHAR(100) DEFAULT NULL,
    `base_price` DECIMAL(12,2) DEFAULT NULL,
    `compare_at_price` DECIMAL(12,2) DEFAULT NULL,
    `erp_product_id` INT UNSIGNED DEFAULT NULL,
    `is_visible` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `seo_title` VARCHAR(190) DEFAULT NULL,
    `seo_description` VARCHAR(500) DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_slug` (`slug`),
    UNIQUE KEY `uniq_erp_simple` (`erp_product_id`, `kind`),
    KEY `idx_kind_visible` (`kind`, `is_visible`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. storefront_product_children — variants & pack items
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `storefront_product_children` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_storefront_id` INT UNSIGNED NOT NULL,
    `child_product_id` INT UNSIGNED NOT NULL,
    `context` ENUM('variant','pack_item') NOT NULL,
    `variant_label` VARCHAR(150) DEFAULT NULL,
    `variant_swatch_hex` VARCHAR(20) DEFAULT NULL,
    `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    `price_override` DECIMAL(12,2) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_parent_child_ctx` (`parent_storefront_id`, `child_product_id`, `context`),
    KEY `idx_parent` (`parent_storefront_id`, `context`, `sort_order`),
    KEY `idx_child` (`child_product_id`),
    CONSTRAINT `fk_spc_parent` FOREIGN KEY (`parent_storefront_id`)
        REFERENCES `storefront_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_spc_child` FOREIGN KEY (`child_product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. storefront_product_categories  (M:N)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `storefront_product_categories` (
    `storefront_product_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`storefront_product_id`, `category_id`),
    KEY `idx_category` (`category_id`),
    CONSTRAINT `fk_spcat_sp` FOREIGN KEY (`storefront_product_id`)
        REFERENCES `storefront_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_spcat_cat` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. coupons
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(60) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `type` ENUM('percent','fixed','free_ship') NOT NULL DEFAULT 'percent',
    `value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `min_subtotal` DECIMAL(12,2) DEFAULT NULL,
    `max_discount` DECIMAL(12,2) DEFAULT NULL,
    `starts_at` DATETIME DEFAULT NULL,
    `ends_at` DATETIME DEFAULT NULL,
    `usage_limit` INT UNSIGNED DEFAULT NULL,
    `usage_limit_per_customer` INT UNSIGNED DEFAULT NULL,
    `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `applies_to` ENUM('all','categories','products') NOT NULL DEFAULT 'all',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_code` (`code`),
    KEY `idx_active_window` (`is_active`, `starts_at`, `ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. coupon_targets
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupon_targets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `coupon_id` INT UNSIGNED NOT NULL,
    `target_type` ENUM('category','storefront_product') NOT NULL,
    `target_id` INT UNSIGNED NOT NULL,
    UNIQUE KEY `uniq_coupon_target` (`coupon_id`, `target_type`, `target_id`),
    CONSTRAINT `fk_ct_coupon` FOREIGN KEY (`coupon_id`)
        REFERENCES `coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. coupon_redemptions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `coupon_id` INT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED DEFAULT NULL,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `customer_phone` VARCHAR(80) DEFAULT NULL,
    `redeemed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_coupon` (`coupon_id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_phone` (`customer_phone`),
    CONSTRAINT `fk_cr_coupon` FOREIGN KEY (`coupon_id`)
        REFERENCES `coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. flash_deals
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `flash_deals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `storefront_product_id` INT UNSIGNED NOT NULL,
    `deal_price` DECIMAL(12,2) NOT NULL,
    `original_price` DECIMAL(12,2) DEFAULT NULL,
    `label` VARCHAR(80) DEFAULT NULL,
    `starts_at` DATETIME DEFAULT NULL,
    `ends_at` DATETIME DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_window` (`is_active`, `starts_at`, `ends_at`),
    KEY `idx_sp` (`storefront_product_id`),
    CONSTRAINT `fk_fd_sp` FOREIGN KEY (`storefront_product_id`)
        REFERENCES `storefront_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. site_settings (key/value)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` LONGTEXT DEFAULT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. search_queries (analytics; ProductRepository::ensureSearchQueryTable creates
--     this lazily — defined here for completeness so it always exists)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `search_queries` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `query` VARCHAR(190) NOT NULL,
    `query_norm` VARCHAR(190) NOT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 1,
    `last_searched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `first_searched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_norm` (`query_norm`),
    KEY `idx_hits` (`hits`),
    KEY `idx_last` (`last_searched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- BACK-FILL: every existing active product becomes a visible 'simple' storefront row.
-- Slug, title, image, price, badge, description prefer existing override values.
-- Idempotent via INSERT IGNORE on uniq_erp_simple (erp_product_id, kind).
-- =============================================================================
INSERT IGNORE INTO `storefront_products` (
    `kind`, `slug`, `title`, `description`, `hero_image_url`, `badge`,
    `base_price`, `erp_product_id`, `is_visible`, `created_at`, `updated_at`
)
SELECT
    'simple' AS kind,
    COALESCE(NULLIF(po.override_slug, ''), CONCAT('product-', p.erp_product_id)) AS slug,
    COALESCE(NULLIF(po.override_title, ''), p.name) AS title,
    COALESCE(NULLIF(po.override_description, ''), p.description) AS description,
    COALESCE(po.override_image_url, p.image_url) AS hero_image_url,
    NULLIF(po.override_badge, '') AS badge,
    COALESCE(po.override_price, p.price) AS base_price,
    p.erp_product_id,
    1 AS is_visible,    -- per user decision: existing catalogue is published by default
    NOW(), NOW()
FROM `products` p
LEFT JOIN `product_overrides` po ON po.product_id = p.id
WHERE p.is_active = 1;

-- =============================================================================
-- Phase 2 site_settings seed (empty defaults; admin Settings page will edit)
-- =============================================================================
INSERT IGNORE INTO `site_settings` (`key`, `value`) VALUES
    ('hero.eyebrow', ''),
    ('hero.headline', ''),
    ('hero.lead', ''),
    ('hero.cta_primary_label', ''),
    ('hero.cta_primary_url', ''),
    ('hero.cta_secondary_label', ''),
    ('hero.cta_secondary_url', ''),
    ('promo.banner_text', ''),
    ('promo.banner_url', ''),
    ('contact.phone', ''),
    ('contact.email', ''),
    ('contact.maps_url', '');
