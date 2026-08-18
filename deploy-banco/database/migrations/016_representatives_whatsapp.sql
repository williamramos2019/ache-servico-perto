-- Civic representatives, activity/attendance, sync audit and WhatsApp opt-in.
CREATE TABLE IF NOT EXISTS `representatives` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `role` VARCHAR(32) NOT NULL,
  `city_id` CHAR(36) NOT NULL,
  `party` VARCHAR(64) NULL,
  `photo_url` TEXT NULL,
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(64) NULL,
  `social_links` JSON NOT NULL,
  `mandate_start` DATE NULL,
  `mandate_end` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `bio` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_representatives_slug` (`slug`),
  KEY `idx_representatives_city_role_active` (`city_id`, `role`, `is_active`),
  CONSTRAINT `fk_representatives_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `representative_activities` (
  `id` CHAR(36) NOT NULL,
  `representative_id` CHAR(36) NULL,
  `city_id` CHAR(36) NOT NULL,
  `kind` VARCHAR(64) NOT NULL,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT NULL,
  `status` VARCHAR(32) NULL,
  `source_url` TEXT NULL,
  `source_name` VARCHAR(255) NULL,
  `occurred_at` DATETIME(3) NOT NULL,
  `raw_payload` JSON NULL,
  `dedupe_hash` VARCHAR(191) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_representative_activities_dedupe` (`dedupe_hash`),
  KEY `idx_rep_activities_city_time` (`city_id`, `occurred_at`),
  KEY `idx_rep_activities_rep_time` (`representative_id`, `occurred_at`),
  KEY `idx_rep_activities_kind` (`kind`),
  CONSTRAINT `fk_rep_activities_representative`
    FOREIGN KEY (`representative_id`) REFERENCES `representatives` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_rep_activities_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `representative_attendance` (
  `id` CHAR(36) NOT NULL,
  `representative_id` CHAR(36) NOT NULL,
  `session_date` DATE NOT NULL,
  `session_type` VARCHAR(128) NULL,
  `present` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rep_attendance_session` (`representative_id`, `session_date`, `session_type`),
  KEY `idx_rep_attendance_rep_date` (`representative_id`, `session_date`),
  CONSTRAINT `fk_rep_attendance_representative`
    FOREIGN KEY (`representative_id`) REFERENCES `representatives` (`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `representative_sync_logs` (
  `id` CHAR(36) NOT NULL,
  `source` VARCHAR(255) NOT NULL,
  `city_id` CHAR(36) NULL,
  `status` VARCHAR(32) NOT NULL,
  `items_found` INT NOT NULL DEFAULT 0,
  `items_new` INT NOT NULL DEFAULT 0,
  `items_updated` INT NOT NULL DEFAULT 0,
  `error` TEXT NULL,
  `duration_ms` INT NULL,
  `idempotency_key` CHAR(64) NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_representative_sync_idempotency` (`idempotency_key`),
  KEY `idx_representative_sync_city_created` (`city_id`, `created_at`),
  CONSTRAINT `fk_representative_sync_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_subscribers` (
  `id` CHAR(36) NOT NULL,
  `phone` VARCHAR(32) NOT NULL,
  `name` VARCHAR(255) NULL,
  `city_id` CHAR(36) NULL,
  `user_id` CHAR(36) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `opted_in_at` DATETIME(3) NOT NULL,
  `opted_out_at` DATETIME(3) NULL,
  `opt_out_token_hash` CHAR(64) NULL,
  `last_sent_at` DATETIME(3) NULL,
  `digest_claim_token` CHAR(36) NULL,
  `digest_claimed_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_whatsapp_subscribers_phone` (`phone`),
  UNIQUE KEY `uq_whatsapp_subscribers_optout_token` (`opt_out_token_hash`),
  KEY `idx_whatsapp_subscribers_city_active` (`city_id`, `is_active`),
  KEY `idx_whatsapp_subscribers_digest_due` (`is_active`, `last_sent_at`, `digest_claimed_at`),
  CONSTRAINT `fk_whatsapp_subscribers_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_whatsapp_subscribers_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scheduled_hook_runs` (
  `id` CHAR(36) NOT NULL,
  `hook_name` VARCHAR(64) NOT NULL,
  `run_key` VARCHAR(191) NOT NULL,
  `claim_token` CHAR(36) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'running',
  `claimed` INT NOT NULL DEFAULT 0,
  `sent` INT NOT NULL DEFAULT 0,
  `failed` INT NOT NULL DEFAULT 0,
  `message` TEXT NULL,
  `started_at` DATETIME(3) NOT NULL,
  `finished_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scheduled_hook_run` (`hook_name`, `run_key`),
  KEY `idx_scheduled_hook_status_started` (`status`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'representative_sync_logs' AND column_name = 'idempotency_key'),
  'SELECT 1',
  'ALTER TABLE `representative_sync_logs` ADD COLUMN `idempotency_key` CHAR(64) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'representative_sync_logs' AND index_name = 'uq_representative_sync_idempotency'),
  'SELECT 1',
  'ALTER TABLE `representative_sync_logs` ADD UNIQUE KEY `uq_representative_sync_idempotency` (`idempotency_key`)'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'whatsapp_subscribers' AND column_name = 'user_id'),
  'SELECT 1',
  'ALTER TABLE `whatsapp_subscribers` ADD COLUMN `user_id` CHAR(36) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'whatsapp_subscribers' AND index_name = 'idx_whatsapp_subscribers_user'),
  'SELECT 1',
  'ALTER TABLE `whatsapp_subscribers` ADD KEY `idx_whatsapp_subscribers_user` (`user_id`)'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'whatsapp_subscribers' AND constraint_name = 'fk_whatsapp_subscribers_user'),
  'SELECT 1',
  'ALTER TABLE `whatsapp_subscribers` ADD CONSTRAINT `fk_whatsapp_subscribers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'whatsapp_subscribers' AND column_name = 'opt_out_token_hash'),
  'SELECT 1',
  'ALTER TABLE `whatsapp_subscribers` ADD COLUMN `opt_out_token_hash` CHAR(64) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'whatsapp_subscribers' AND index_name = 'uq_whatsapp_subscribers_optout_token'),
  'SELECT 1',
  'ALTER TABLE `whatsapp_subscribers` ADD UNIQUE KEY `uq_whatsapp_subscribers_optout_token` (`opt_out_token_hash`)'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'whatsapp_subscribers' AND column_name = 'digest_claim_token'),
  'SELECT 1',
  'ALTER TABLE `whatsapp_subscribers` ADD COLUMN `digest_claim_token` CHAR(36) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'whatsapp_subscribers' AND column_name = 'digest_claimed_at'),
  'SELECT 1',
  'ALTER TABLE `whatsapp_subscribers` ADD COLUMN `digest_claimed_at` DATETIME(3) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'whatsapp_subscribers' AND index_name = 'idx_whatsapp_subscribers_digest_due'),
  'SELECT 1',
  'ALTER TABLE `whatsapp_subscribers` ADD KEY `idx_whatsapp_subscribers_digest_due` (`is_active`, `last_sent_at`, `digest_claimed_at`)'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;
