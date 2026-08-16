CREATE TABLE IF NOT EXISTS `company_media` (
  `id` CHAR(36) NOT NULL,
  `company_id` CHAR(36) NOT NULL,
  `url` TEXT NOT NULL,
  `type` VARCHAR(32) NOT NULL,
  `caption` TEXT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_media_company_id` (`company_id`),
  CONSTRAINT `fk_company_media_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_views` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` CHAR(36) NOT NULL,
  `ip_hash` VARCHAR(128) NULL,
  `viewed_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_views_company_id` (`company_id`),
  KEY `idx_company_views_viewed_at` (`viewed_at`),
  CONSTRAINT `fk_company_views_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` CHAR(36) NOT NULL,
  `company_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NULL,
  `rating` INT NOT NULL,
  `comment` TEXT NULL,
  `author_name` VARCHAR(255) NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'app',
  `review_date` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_company_id` (`company_id`),
  KEY `idx_reviews_user_id` (`user_id`),
  CONSTRAINT `fk_reviews_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_reviews_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leads` (
  `id` CHAR(36) NOT NULL,
  `company_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NULL,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(64) NOT NULL,
  `email` VARCHAR(255) NULL,
  `message` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_leads_company_id` (`company_id`),
  KEY `idx_leads_user_id` (`user_id`),
  CONSTRAINT `fk_leads_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_leads_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leads_planos` (
  `id` CHAR(36) NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `contact_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(64) NULL,
  `city` VARCHAR(255) NULL,
  `plan` VARCHAR(64) NOT NULL,
  `message` TEXT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'new',
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_leads_planos_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `favorites` (
  `user_id` CHAR(36) NOT NULL,
  `company_id` CHAR(36) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`user_id`, `company_id`),
  KEY `idx_favorites_company_id` (`company_id`),
  CONSTRAINT `fk_favorites_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_favorites_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` CHAR(36) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NULL,
  `city_slug` VARCHAR(255) NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletter_subscribers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
