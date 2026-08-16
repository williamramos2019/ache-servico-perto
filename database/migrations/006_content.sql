CREATE TABLE IF NOT EXISTS `event_categories` (
  `id` CHAR(36) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `icon` TEXT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `event_categories` (`id`, `slug`, `name`, `icon`, `sort`, `created_at`) VALUES
  (UUID(), 'show', 'Show', 'music', 1, UTC_TIMESTAMP(3)),
  (UUID(), 'festival', 'Festival', 'party-popper', 2, UTC_TIMESTAMP(3)),
  (UUID(), 'teatro', 'Teatro', 'drama', 3, UTC_TIMESTAMP(3)),
  (UUID(), 'esporte', 'Esporte', 'trophy', 4, UTC_TIMESTAMP(3)),
  (UUID(), 'feira', 'Feira', 'store', 5, UTC_TIMESTAMP(3)),
  (UUID(), 'workshop', 'Workshop', 'graduation-cap', 6, UTC_TIMESTAMP(3)),
  (UUID(), 'gastronomia', 'Gastronomia', 'utensils', 7, UTC_TIMESTAMP(3)),
  (UUID(), 'outros', 'Outros', 'calendar', 99, UTC_TIMESTAMP(3));

CREATE TABLE IF NOT EXISTS `events` (
  `id` CHAR(36) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `cover_image` TEXT NULL,
  `start_at` DATETIME(3) NOT NULL,
  `end_at` DATETIME(3) NULL,
  `location` TEXT NULL,
  `company_id` CHAR(36) NULL,
  `city_id` CHAR(36) NULL,
  `category_id` CHAR(36) NULL,
  `event_type` VARCHAR(64) NULL,
  `ticket_url` TEXT NULL,
  `price_min` DECIMAL(12,2) NULL,
  `price_max` DECIMAL(12,2) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'draft',
  `created_by` CHAR(36) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_events_slug` (`slug`),
  KEY `idx_events_city_status` (`city_id`, `status`),
  KEY `idx_events_start_at` (`start_at`),
  CONSTRAINT `fk_events_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_events_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_events_category`
    FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_events_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shows` (
  `id` CHAR(36) NOT NULL,
  `event_id` CHAR(36) NOT NULL,
  `artist_name` VARCHAR(255) NOT NULL,
  `cover_image` TEXT NULL,
  `description` TEXT NULL,
  `start_at` DATETIME(3) NOT NULL,
  `end_at` DATETIME(3) NULL,
  `stage` VARCHAR(255) NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `ticket_price` DECIMAL(12,2) NULL,
  `ticket_url` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shows_event_id` (`event_id`),
  CONSTRAINT `fk_shows_event`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posts` (
  `id` CHAR(36) NOT NULL,
  `type` VARCHAR(16) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'draft',
  `slug` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `excerpt` TEXT NULL,
  `content` MEDIUMTEXT NULL,
  `featured_image` TEXT NULL,
  `gallery` JSON NULL,
  `tags` JSON NULL,
  `meta_title` TEXT NULL,
  `meta_description` TEXT NULL,
  `og_image` TEXT NULL,
  `author_id` CHAR(36) NULL,
  `author_name` VARCHAR(255) NULL,
  `company_id` CHAR(36) NULL,
  `city_id` CHAR(36) NULL,
  `auto_generated` TINYINT(1) NOT NULL DEFAULT 0,
  `views_count` INT NOT NULL DEFAULT 0,
  `published_at` DATETIME(3) NULL,
  `scheduled_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_slug` (`slug`),
  KEY `idx_posts_type_status` (`type`, `status`),
  CONSTRAINT `fk_posts_author`
    FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_posts_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_posts_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_categories` (
  `post_id` CHAR(36) NOT NULL,
  `category_id` CHAR(36) NOT NULL,
  PRIMARY KEY (`post_id`, `category_id`),
  CONSTRAINT `fk_post_categories_post`
    FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_post_categories_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `public_services` (
  `id` CHAR(36) NOT NULL,
  `city_id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(32) NOT NULL,
  `subtype` VARCHAR(64) NULL,
  `description` TEXT NULL,
  `address` TEXT NULL,
  `neighborhood` VARCHAR(255) NULL,
  `phone` VARCHAR(64) NULL,
  `phone_secondary` VARCHAR(64) NULL,
  `whatsapp` VARCHAR(64) NULL,
  `email` VARCHAR(255) NULL,
  `website` TEXT NULL,
  `hours` TEXT NULL,
  `lat` DECIMAL(9,6) NULL,
  `lng` DECIMAL(9,6) NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_24h` TINYINT(1) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_public_services_city_category` (`city_id`, `category`),
  CONSTRAINT `fk_public_services_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `emergency_contacts` (
  `id` CHAR(36) NOT NULL,
  `city_id` CHAR(36) NULL,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(64) NOT NULL,
  `description` TEXT NULL,
  `icon` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_emergency_contacts_city_id` (`city_id`),
  CONSTRAINT `fk_emergency_contacts_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
