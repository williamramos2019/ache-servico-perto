-- Additive engagement, moderation and advertising batch.
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` CHAR(36) NOT NULL,
  `company_id` CHAR(36) NULL,
  `city_id` CHAR(36) NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `code` VARCHAR(191) NOT NULL,
  `discount_percent` INT NULL,
  `discount_label` VARCHAR(255) NULL,
  `category` VARCHAR(128) NULL,
  `image_url` TEXT NULL,
  `link_url` TEXT NULL,
  `terms` TEXT NULL,
  `valid_from` DATETIME(3) NULL,
  `valid_to` DATETIME(3) NULL,
  `is_sponsored` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'published',
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_coupons_city` (`city_id`),
  KEY `idx_coupons_company` (`company_id`),
  KEY `idx_coupons_status_valid` (`status`, `valid_to`),
  KEY `idx_coupons_sponsored` (`is_sponsored`),
  CONSTRAINT `chk_coupons_discount_percent`
    CHECK (`discount_percent` IS NULL OR `discount_percent` BETWEEN 0 AND 100),
  CONSTRAINT `fk_coupons_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_coupons_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_campaigns` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `image_url` TEXT NOT NULL,
  `link_url` TEXT NOT NULL,
  `city_slug` VARCHAR(191) NULL,
  `placement` VARCHAR(32) NOT NULL DEFAULT 'bottom-right',
  `delay_seconds` INT NOT NULL DEFAULT 5,
  `scroll_trigger_percent` INT NOT NULL DEFAULT 0,
  `display_seconds` INT NOT NULL DEFAULT 7,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `starts_at` DATETIME(3) NULL,
  `ends_at` DATETIME(3) NULL,
  `impressions` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `clicks` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `weight` INT NOT NULL DEFAULT 1,
  `route_patterns` JSON NOT NULL,
  `company_id` CHAR(36) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ad_campaigns_active_city` (`active`, `city_slug`),
  KEY `idx_ad_campaigns_company_id` (`company_id`),
  KEY `idx_ad_campaigns_active_dates` (`active`, `starts_at`, `ends_at`),
  CONSTRAINT `fk_ad_campaigns_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_requests` (
  `id` CHAR(36) NOT NULL,
  `request_number` VARCHAR(32) NOT NULL,
  `category` VARCHAR(32) NOT NULL DEFAULT 'outro',
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `page_url` TEXT NULL,
  `attachment_url` TEXT NULL,
  `user_id` CHAR(36) NULL,
  `user_name` VARCHAR(255) NULL,
  `user_email` VARCHAR(255) NULL,
  `user_phone` VARCHAR(64) NULL,
  `city_id` CHAR(36) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'novo',
  `priority` VARCHAR(32) NOT NULL DEFAULT 'media',
  `admin_response` TEXT NULL,
  `assigned_to` CHAR(36) NULL,
  `ip` VARCHAR(64) NULL,
  `extra` JSON NOT NULL,
  `resolved_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_requests_number` (`request_number`),
  KEY `idx_user_requests_status` (`status`),
  KEY `idx_user_requests_created_at` (`created_at`),
  KEY `idx_user_requests_user_id` (`user_id`),
  KEY `idx_user_requests_city_id` (`city_id`),
  CONSTRAINT `fk_user_requests_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_requests_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_user_requests_assigned_to`
    FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `live_feed_hidden` (
  `id` CHAR(36) NOT NULL,
  `source` VARCHAR(128) NOT NULL,
  `source_id` CHAR(36) NOT NULL,
  `reason` TEXT NULL,
  `hidden_by` CHAR(36) NULL,
  `hidden_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_live_feed_hidden_source_id` (`source`, `source_id`),
  CONSTRAINT `fk_live_feed_hidden_user`
    FOREIGN KEY (`hidden_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @agendaqui_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'promotions' AND column_name IN ('city_id', 'category', 'discount_percent', 'image_url', 'link_url')) = 5
    AND (SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'promotions' AND index_name IN ('idx_promotions_city', 'idx_promotions_status_valid')) = 2
    AND EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'promotions' AND constraint_name = 'fk_promotions_city')
    AND EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'promotions' AND constraint_name = 'chk_promotions_discount_percent'),
  'SELECT 1',
  'ALTER TABLE `promotions` ADD COLUMN `city_id` CHAR(36) NULL, ADD COLUMN `category` VARCHAR(128) NULL, ADD COLUMN `discount_percent` INT NULL, ADD COLUMN `image_url` TEXT NULL, ADD COLUMN `link_url` TEXT NULL, ADD KEY `idx_promotions_city` (`city_id`), ADD KEY `idx_promotions_status_valid` (`status`, `valid_to`), ADD CONSTRAINT `chk_promotions_discount_percent` CHECK (`discount_percent` IS NULL OR `discount_percent` BETWEEN 0 AND 100), ADD CONSTRAINT `fk_promotions_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'push_deliveries' AND column_name IN ('retry_count', 'next_retry_at')) = 2
    AND EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'push_deliveries' AND index_name = 'idx_push_deliveries_status_retry'),
  'SELECT 1',
  'ALTER TABLE `push_deliveries` ADD COLUMN `retry_count` INT NOT NULL DEFAULT 0, ADD COLUMN `next_retry_at` DATETIME(3) NULL, ADD KEY `idx_push_deliveries_status_retry` (`status`, `next_retry_at`)'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

INSERT IGNORE INTO `system_settings` (`key`, `value`, `is_public`, `updated_at`)
VALUES ('live_feed_blacklist', '["spam","teste","test123","xxx"]', 0, UTC_TIMESTAMP(3));
