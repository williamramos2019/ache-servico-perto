-- Preserve upstream route provenance while schedules remain normalized in migration 012.
CREATE TABLE IF NOT EXISTS `bus_sync_logs` (
  `id` CHAR(36) NOT NULL,
  `city_slug` VARCHAR(191) NOT NULL,
  `lines_found` INT NOT NULL DEFAULT 0,
  `lines_updated` INT NOT NULL DEFAULT 0,
  `errors` JSON NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'ok',
  `idempotency_key` CHAR(64) NULL,
  `started_at` DATETIME(3) NOT NULL,
  `finished_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bus_sync_idempotency` (`idempotency_key`),
  KEY `idx_bus_sync_logs_city_started` (`city_slug`, `started_at`),
  KEY `idx_bus_sync_logs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'transport_lines' AND column_name = 'source_url'),
  'SELECT 1',
  'ALTER TABLE `transport_lines` ADD COLUMN `source_url` TEXT NULL, ADD COLUMN `raw_updated_at` VARCHAR(255) NULL, ADD COLUMN `last_scraped_at` DATETIME(3) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bus_sync_logs' AND column_name = 'idempotency_key'),
  'SELECT 1',
  'ALTER TABLE `bus_sync_logs` ADD COLUMN `idempotency_key` CHAR(64) NULL'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;

SET @agendaqui_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'bus_sync_logs' AND index_name = 'uq_bus_sync_idempotency'),
  'SELECT 1',
  'ALTER TABLE `bus_sync_logs` ADD UNIQUE KEY `uq_bus_sync_idempotency` (`idempotency_key`)'
);
PREPARE agendaqui_stmt FROM @agendaqui_sql;
EXECUTE agendaqui_stmt;
DEALLOCATE PREPARE agendaqui_stmt;
