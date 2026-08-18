-- Additive content/civic batch. Application code validates status values.
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_tokens_hash` (`token_hash`),
  KEY `idx_password_reset_tokens_user` (`user_id`),
  KEY `idx_password_reset_tokens_expiry` (`expires_at`),
  CONSTRAINT `fk_password_reset_tokens_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_categories` (
  `id` CHAR(36) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `icon` TEXT NULL,
  `color` VARCHAR(32) NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `editorial_posts` (
  `id` CHAR(36) NOT NULL,
  `publish_date` DATE NOT NULL,
  `theme` TEXT NOT NULL,
  `format` VARCHAR(128) NOT NULL DEFAULT 'Reels',
  `caption` TEXT NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'planejado',
  `campaign` TEXT NULL,
  `city` VARCHAR(255) NULL,
  `company_id` CHAR(36) NULL,
  `tags` JSON NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_editorial_posts_publish_date` (`publish_date`),
  KEY `idx_editorial_posts_status` (`status`),
  CONSTRAINT `fk_editorial_posts_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_sync_logs` (
  `id` CHAR(36) NOT NULL,
  `source` VARCHAR(255) NOT NULL,
  `city_id` CHAR(36) NULL,
  `status` VARCHAR(32) NOT NULL,
  `items_found` INT NOT NULL DEFAULT 0,
  `items_new` INT NOT NULL DEFAULT 0,
  `items_updated` INT NOT NULL DEFAULT 0,
  `error` TEXT NULL,
  `duration_ms` INT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_sync_logs_city_created` (`city_id`, `created_at`),
  CONSTRAINT `fk_event_sync_logs_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tourist_attractions` (
  `id` CHAR(36) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NULL,
  `description` TEXT NOT NULL,
  `category` VARCHAR(128) NOT NULL DEFAULT 'geral',
  `city_id` CHAR(36) NULL,
  `image_url` TEXT NULL,
  `link_url` TEXT NULL,
  `meta` TEXT NULL,
  `tag` VARCHAR(128) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tourist_attractions_slug` (`slug`),
  KEY `idx_tourist_attractions_city` (`city_id`),
  KEY `idx_tourist_attractions_active` (`is_active`),
  KEY `idx_tourist_attractions_category` (`category`),
  CONSTRAINT `fk_tourist_attractions_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `procurements` (
  `id` CHAR(36) NOT NULL,
  `city_id` CHAR(36) NOT NULL,
  `source_site` VARCHAR(255) NOT NULL,
  `source_url` TEXT NOT NULL,
  `external_id` VARCHAR(255) NULL,
  `process_number` VARCHAR(255) NULL,
  `modality` VARCHAR(64) NULL,
  `title` VARCHAR(500) NOT NULL,
  `object` TEXT NULL,
  `agency` VARCHAR(255) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'open',
  `publish_date` DATE NULL,
  `opening_date` DATETIME(3) NULL,
  `deadline_date` DATETIME(3) NULL,
  `estimated_value` DECIMAL(14,2) NULL,
  `files` JSON NOT NULL,
  `raw_excerpt` TEXT NULL,
  `content_hash` CHAR(64) NULL,
  `fallback_dedupe_hash` CHAR(64)
    GENERATED ALWAYS AS (
      CASE
        WHEN `external_id` IS NULL
          THEN SHA2(CONCAT_WS('|', `city_id`, `source_url`, LOWER(`title`)), 256)
        ELSE NULL
      END
    ) STORED,
  `scraped_at` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_procurements_city` (`city_id`),
  KEY `idx_procurements_status` (`status`),
  KEY `idx_procurements_modality` (`modality`),
  KEY `idx_procurements_publish_date` (`publish_date`),
  KEY `idx_procurements_opening_date` (`opening_date`),
  UNIQUE KEY `uq_procurements_city_source_external` (`city_id`, `source_site`, `external_id`),
  UNIQUE KEY `uq_procurements_fallback_dedupe` (`fallback_dedupe_hash`),
  CONSTRAINT `fk_procurements_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @agendaqui_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'profiles' AND column_name IN ('city_id', 'state', 'onboarding_completed_at', 'onboarding_version')) = 4
    AND (SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'profiles' AND index_name IN ('idx_profiles_city_id', 'idx_profiles_state')) = 2
    AND EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'profiles' AND constraint_name = 'fk_profiles_city'),
  'SELECT 1',
  'ALTER TABLE `profiles` ADD COLUMN `city_id` CHAR(36) NULL, ADD COLUMN `state` VARCHAR(8) NULL, ADD COLUMN `onboarding_completed_at` DATETIME(3) NULL, ADD COLUMN `onboarding_version` VARCHAR(32) NULL, ADD KEY `idx_profiles_city_id` (`city_id`), ADD KEY `idx_profiles_state` (`state`), ADD CONSTRAINT `fk_profiles_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'companies' AND column_name = 'services_offered'),
  'SELECT 1',
  'ALTER TABLE `companies` ADD COLUMN `services_offered` JSON NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

UPDATE `companies`
SET `services_offered` = JSON_ARRAY()
WHERE `services_offered` IS NULL;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'companies' AND column_name = 'services_offered' AND is_nullable = 'NO'),
  'SELECT 1',
  'ALTER TABLE `companies` MODIFY COLUMN `services_offered` JSON NOT NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'posts' AND column_name = 'category_id')
    AND (SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'posts' AND index_name IN ('idx_posts_category_id', 'idx_posts_type_status_published')) = 2
    AND EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'posts' AND constraint_name = 'fk_posts_blog_category'),
  'SELECT 1',
  'ALTER TABLE `posts` ADD COLUMN `category_id` CHAR(36) NULL, ADD KEY `idx_posts_category_id` (`category_id`), ADD KEY `idx_posts_type_status_published` (`type`, `status`, `published_at`), ADD CONSTRAINT `fk_posts_blog_category` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'events' AND column_name IN ('source', 'source_url', 'external_id', 'dedupe_hash')) = 4
    AND (SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'events' AND index_name IN ('uq_events_dedupe_hash', 'idx_events_source')) = 2,
  'SELECT 1',
  'ALTER TABLE `events` ADD COLUMN `source` VARCHAR(255) NULL, ADD COLUMN `source_url` TEXT NULL, ADD COLUMN `external_id` VARCHAR(255) NULL, ADD COLUMN `dedupe_hash` VARCHAR(191) NULL, ADD UNIQUE KEY `uq_events_dedupe_hash` (`dedupe_hash`), ADD KEY `idx_events_source` (`source`)'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'public_services' AND column_name IN ('verification_status', 'verified_at', 'verified_source', 'verified_by')) = 4
    AND EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'public_services' AND index_name = 'idx_public_services_verification_status')
    AND EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'public_services' AND constraint_name = 'fk_public_services_verified_by'),
  'SELECT 1',
  'ALTER TABLE `public_services` ADD COLUMN `verification_status` VARCHAR(32) NOT NULL DEFAULT ''unverified'', ADD COLUMN `verified_at` DATETIME(3) NULL, ADD COLUMN `verified_source` VARCHAR(255) NULL, ADD COLUMN `verified_by` CHAR(36) NULL, ADD KEY `idx_public_services_verification_status` (`verification_status`), ADD CONSTRAINT `fk_public_services_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'session_version'),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 1'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

UPDATE `profiles`
SET `onboarding_version` = '1.0.0'
WHERE `onboarding_completed_at` IS NOT NULL AND `onboarding_version` IS NULL;

INSERT IGNORE INTO `blog_categories`
  (`id`, `slug`, `name`, `icon`, `color`, `sort`, `created_at`, `updated_at`)
VALUES
  (UUID(), 'noticias', 'Notícias', 'Newspaper', '#EF4444', 1, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'cidade', 'Cidade', 'Building2', '#3B82F6', 2, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'negocios', 'Negócios', 'Briefcase', '#10B981', 3, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'turismo', 'Turismo', 'MapPin', '#F59E0B', 4, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'gastronomia', 'Gastronomia', 'UtensilsCrossed', '#EC4899', 5, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'cultura', 'Cultura', 'Palette', '#8B5CF6', 6, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'servicos', 'Serviços', 'Wrench', '#06B6D4', 7, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'dicas', 'Dicas', 'Lightbulb', '#EAB308', 8, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
