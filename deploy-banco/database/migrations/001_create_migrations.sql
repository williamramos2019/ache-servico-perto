CREATE TABLE IF NOT EXISTS `migrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(32) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `checksum` CHAR(64) NOT NULL,
  `executed_at` DATETIME(3) NOT NULL,
  `execution_time_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `success` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migrations_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
