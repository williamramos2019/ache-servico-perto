CREATE TABLE IF NOT EXISTS `transport_sources` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `url` TEXT NULL,
  `type` VARCHAR(32) NOT NULL DEFAULT 'other',
  `collected_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transport_lines` (
  `id` CHAR(36) NOT NULL,
  `city_id` CHAR(36) NULL,
  `source_id` CHAR(36) NULL,
  `code` VARCHAR(32) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(32) NOT NULL DEFAULT 'municipal',
  `status` VARCHAR(32) NOT NULL DEFAULT 'unknown',
  `fare` VARCHAR(64) NULL,
  `operator_name` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `external_id` VARCHAR(128) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transport_lines_slug` (`slug`),
  UNIQUE KEY `uq_transport_lines_code_city_type` (`code`, `city_id`, `type`),
  KEY `idx_transport_lines_city_id` (`city_id`),
  KEY `idx_transport_lines_status` (`status`),
  KEY `idx_transport_lines_type` (`type`),
  KEY `idx_transport_lines_name` (`name`(64)),
  CONSTRAINT `fk_transport_lines_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_transport_lines_source`
    FOREIGN KEY (`source_id`) REFERENCES `transport_sources` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transport_schedules` (
  `id` CHAR(36) NOT NULL,
  `line_id` CHAR(36) NOT NULL,
  `direction` VARCHAR(32) NOT NULL DEFAULT 'ida',
  `day_type` VARCHAR(32) NOT NULL,
  `departure_time` CHAR(5) NOT NULL,
  `control_point` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transport_schedules_line_dir_day` (`line_id`, `direction`, `day_type`),
  CONSTRAINT `fk_transport_schedules_line`
    FOREIGN KEY (`line_id`) REFERENCES `transport_lines` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transport_stops` (
  `id` CHAR(36) NOT NULL,
  `line_id` CHAR(36) NOT NULL,
  `sequence` INT NOT NULL DEFAULT 0,
  `name` VARCHAR(255) NOT NULL,
  `address` TEXT NULL,
  `lat` DECIMAL(9,6) NULL,
  `lng` DECIMAL(9,6) NULL,
  `direction` VARCHAR(32) NOT NULL DEFAULT 'ida',
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transport_stops_line_dir_seq` (`line_id`, `direction`, `sequence`),
  CONSTRAINT `fk_transport_stops_line`
    FOREIGN KEY (`line_id`) REFERENCES `transport_lines` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
