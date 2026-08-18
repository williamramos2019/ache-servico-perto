-- Final upstream-compatible jobs schema, including premium/application fields.
CREATE TABLE IF NOT EXISTS `job_sources` (
  `id` CHAR(36) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `kind` VARCHAR(32) NOT NULL,
  `endpoint_url` TEXT NULL,
  `config` JSON NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sync_frequency_minutes` INT NOT NULL DEFAULT 60,
  `last_sync_at` DATETIME(3) NULL,
  `last_sync_status` VARCHAR(32) NULL,
  `last_sync_message` TEXT NULL,
  `next_sync_at` DATETIME(3) NULL,
  `failure_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `sync_lock_token` CHAR(36) NULL,
  `sync_locked_until` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_sources_slug` (`slug`),
  KEY `idx_job_sources_active` (`is_active`),
  KEY `idx_job_sources_due` (`is_active`, `next_sync_at`),
  KEY `idx_job_sources_lock` (`sync_locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` CHAR(36) NOT NULL,
  `source_id` CHAR(36) NULL,
  `external_id` VARCHAR(255) NULL,
  `title` VARCHAR(500) NOT NULL,
  `company_name` VARCHAR(255) NULL,
  `description` MEDIUMTEXT NULL,
  `location_city` VARCHAR(255) NULL,
  `location_state` VARCHAR(8) NULL,
  `is_remote` TINYINT(1) NOT NULL DEFAULT 0,
  `employment_type` VARCHAR(64) NULL,
  `experience_level` VARCHAR(64) NULL,
  `salary_min` DECIMAL(14,2) NULL,
  `salary_max` DECIMAL(14,2) NULL,
  `salary_currency` VARCHAR(8) NULL DEFAULT 'BRL',
  `apply_url` TEXT NULL,
  `category` VARCHAR(128) NULL,
  `tags` JSON NOT NULL,
  `posted_at` DATETIME(3) NULL,
  `expires_at` DATETIME(3) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `raw` JSON NULL,
  `is_premium` TINYINT(1) NOT NULL DEFAULT 0,
  `company_id` CHAR(36) NULL,
  `company_logo_url` TEXT NULL,
  `company_size` VARCHAR(128) NULL,
  `company_culture` TEXT NULL,
  `requirements` JSON NOT NULL,
  `nice_to_have` JSON NOT NULL,
  `benefits` JSON NOT NULL,
  `responsibilities` JSON NOT NULL,
  `workload` VARCHAR(128) NULL,
  `apply_email` VARCHAR(255) NULL,
  `apply_whatsapp` VARCHAR(64) NULL,
  `application_deadline` DATE NULL,
  `featured_until` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jobs_source_external` (`source_id`, `external_id`),
  KEY `idx_jobs_active_posted` (`is_active`, `posted_at`),
  KEY `idx_jobs_city` (`location_city`),
  KEY `idx_jobs_state` (`location_state`),
  KEY `idx_jobs_remote` (`is_remote`),
  KEY `idx_jobs_premium_featured` (`is_premium`, `featured_until`, `posted_at`),
  KEY `idx_jobs_company_id` (`company_id`),
  CONSTRAINT `fk_jobs_source`
    FOREIGN KEY (`source_id`) REFERENCES `job_sources` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_jobs_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_sync_logs` (
  `id` CHAR(36) NOT NULL,
  `source_id` CHAR(36) NULL,
  `started_at` DATETIME(3) NOT NULL,
  `finished_at` DATETIME(3) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'running',
  `fetched` INT NOT NULL DEFAULT 0,
  `inserted` INT NOT NULL DEFAULT 0,
  `updated` INT NOT NULL DEFAULT 0,
  `errors` INT NOT NULL DEFAULT 0,
  `message` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job_sync_logs_source_started` (`source_id`, `started_at`),
  CONSTRAINT `fk_job_sync_logs_source`
    FOREIGN KEY (`source_id`) REFERENCES `job_sources` (`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'job_sources' AND column_name = 'next_sync_at'),
  'SELECT 1',
  'ALTER TABLE `job_sources` ADD COLUMN `next_sync_at` DATETIME(3) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'job_sources' AND column_name = 'failure_count'),
  'SELECT 1',
  'ALTER TABLE `job_sources` ADD COLUMN `failure_count` INT UNSIGNED NOT NULL DEFAULT 0'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'job_sources' AND column_name = 'sync_lock_token'),
  'SELECT 1',
  'ALTER TABLE `job_sources` ADD COLUMN `sync_lock_token` CHAR(36) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'job_sources' AND column_name = 'sync_locked_until'),
  'SELECT 1',
  'ALTER TABLE `job_sources` ADD COLUMN `sync_locked_until` DATETIME(3) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'job_sources' AND index_name = 'idx_job_sources_due'),
  'SELECT 1',
  'ALTER TABLE `job_sources` ADD KEY `idx_job_sources_due` (`is_active`, `next_sync_at`)'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'job_sources' AND index_name = 'idx_job_sources_lock'),
  'SELECT 1',
  'ALTER TABLE `job_sources` ADD KEY `idx_job_sources_lock` (`sync_locked_until`)'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

INSERT IGNORE INTO `job_sources`
  (`id`, `slug`, `name`, `kind`, `endpoint_url`, `config`, `sync_frequency_minutes`, `created_at`, `updated_at`)
VALUES
  (UUID(), 'remoteok', 'RemoteOK (remoto)', 'api', 'https://remoteok.com/api', '{"filter_categories":["dev","design","marketing","product"]}', 60, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'trampos-co', 'Trampos.co (tech BR)', 'api', 'https://trampos.co/oportunidades.json', '{"filter_states":["MG"],"include_remote":true}', 60, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'sine-vespasiano', 'SINE — Vespasiano (manual)', 'manual', NULL, '{"note":"Cadastre vagas do SINE local manualmente."}', 1440, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3)),
  (UUID(), 'sine-sao-jose-lapa', 'SINE — São José da Lapa (manual)', 'manual', NULL, '{"note":"Cadastre vagas do SINE local manualmente."}', 1440, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3));
