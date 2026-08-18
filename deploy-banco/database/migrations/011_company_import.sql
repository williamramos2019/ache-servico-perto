-- Incremental importer metadata. Does not DROP or rewrite existing company rows.
-- MySQL/MariaDB: UNIQUE on companies.cnpj allows multiple NULLs (cadastros manuais sem CNPJ).
-- origin DEFAULT 'manual' aplica-se às ~209 empresas já existentes; importação nova usa 'imported' no PHP.
-- last_external_id / last_batch permitem retomar sem depender só de offset.
-- CHAR(36) UUID, InnoDB, utf8mb4 — igual às migrations 001–010.

ALTER TABLE `companies`
  ADD COLUMN `cnpj` CHAR(14) NULL AFTER `slug`,
  ADD COLUMN `legal_name` VARCHAR(255) NULL AFTER `name`,
  ADD COLUMN `cnae_primary` VARCHAR(16) NULL AFTER `legal_name`,
  ADD COLUMN `neighborhood` VARCHAR(255) NULL AFTER `address`,
  ADD COLUMN `origin` VARCHAR(32) NOT NULL DEFAULT 'manual' AFTER `status`;

ALTER TABLE `companies`
  ADD UNIQUE KEY `uq_companies_cnpj` (`cnpj`),
  ADD KEY `idx_companies_origin` (`origin`),
  ADD KEY `idx_companies_cnae_primary` (`cnae_primary`);

CREATE TABLE IF NOT EXISTS `company_import_runs` (
  `id` CHAR(36) NOT NULL,
  `city_slug` VARCHAR(64) NOT NULL,
  `source` VARCHAR(64) NOT NULL,
  `dry_run` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'running',
  `started_at` DATETIME(3) NOT NULL,
  `finished_at` DATETIME(3) NULL,
  `total_collected` INT NOT NULL DEFAULT 0,
  `total_valid` INT NOT NULL DEFAULT 0,
  `total_inserted` INT NOT NULL DEFAULT 0,
  `total_updated` INT NOT NULL DEFAULT 0,
  `total_duplicates` INT NOT NULL DEFAULT 0,
  `total_rejected` INT NOT NULL DEFAULT 0,
  `total_skipped` INT NOT NULL DEFAULT 0,
  `last_external_id` VARCHAR(128) NULL,
  `last_batch` INT NOT NULL DEFAULT 0,
  `error_message` TEXT NULL,
  `importer_version` VARCHAR(32) NOT NULL DEFAULT '1',
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_import_runs_city_started` (`city_slug`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_sources` (
  `id` CHAR(36) NOT NULL,
  `company_id` CHAR(36) NOT NULL,
  `run_id` CHAR(36) NULL,
  `source_name` VARCHAR(255) NOT NULL,
  `source_url` TEXT NULL,
  `source_type` VARCHAR(64) NOT NULL,
  `external_id` VARCHAR(128) NULL,
  `source_hash` CHAR(64) NULL,
  `collected_at` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_sources_source_external` (`source_type`, `external_id`),
  KEY `idx_company_sources_company_id` (`company_id`),
  KEY `idx_company_sources_run_id` (`run_id`),
  CONSTRAINT `fk_company_sources_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_company_sources_run`
    FOREIGN KEY (`run_id`) REFERENCES `company_import_runs` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_import_errors` (
  `id` CHAR(36) NOT NULL,
  `run_id` CHAR(36) NOT NULL,
  `external_id` VARCHAR(128) NULL,
  `company_name` VARCHAR(255) NULL,
  `error_type` VARCHAR(64) NOT NULL,
  `error_message` TEXT NOT NULL,
  `raw_data` JSON NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_import_errors_run_id` (`run_id`),
  CONSTRAINT `fk_company_import_errors_run`
    FOREIGN KEY (`run_id`) REFERENCES `company_import_runs` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
