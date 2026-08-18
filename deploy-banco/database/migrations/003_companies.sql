CREATE TABLE IF NOT EXISTS `cities` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `state` VARCHAR(8) NOT NULL DEFAULT 'MG',
  `lat` DECIMAL(9,6) NULL,
  `lng` DECIMAL(9,6) NULL,
  `hero_title` TEXT NULL,
  `hero_subtitle` TEXT NULL,
  `hero_image_url` TEXT NULL,
  `banner_url` TEXT NULL,
  `logo_url` TEXT NULL,
  `video_url` TEXT NULL,
  `primary_color` VARCHAR(32) NULL,
  `seo_title` TEXT NULL,
  `seo_description` TEXT NULL,
  `og_image_url` TEXT NULL,
  `featured_category_ids` JSON NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cities_slug` (`slug`),
  KEY `idx_cities_is_active` (`is_active`),
  KEY `idx_cities_lat_lng` (`lat`, `lng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `icon` TEXT NULL,
  `description` TEXT NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `companies` (
  `id` CHAR(36) NOT NULL,
  `owner_id` CHAR(36) NULL,
  `slug` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `tagline` TEXT NULL,
  `description` TEXT NULL,
  `phone` VARCHAR(64) NULL,
  `whatsapp` VARCHAR(64) NULL,
  `email` VARCHAR(255) NULL,
  `address` TEXT NULL,
  `zip` VARCHAR(16) NULL,
  `city_id` CHAR(36) NULL,
  `lat` DECIMAL(9,6) NULL,
  `lng` DECIMAL(9,6) NULL,
  `website` TEXT NULL,
  `instagram` TEXT NULL,
  `facebook` TEXT NULL,
  `tiktok` TEXT NULL,
  `youtube` TEXT NULL,
  `hours` JSON NULL,
  `logo_url` TEXT NULL,
  `banner_url` TEXT NULL,
  `video_url` TEXT NULL,
  `tour_360_url` TEXT NULL,
  `catalog_url` TEXT NULL,
  `pricebook_url` TEXT NULL,
  `portfolio_pdf_url` TEXT NULL,
  `plan` VARCHAR(32) NOT NULL DEFAULT 'free',
  `plan_expires_at` DATETIME(3) NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `review_count` INT NOT NULL DEFAULT 0,
  `views_count` INT NOT NULL DEFAULT 0,
  `founded_year` INT NULL,
  `years_experience` INT NULL,
  `response_time_minutes` INT NULL,
  `response_rate` DECIMAL(5,2) NULL,
  `services_completed` INT NULL,
  `clients_served` INT NULL,
  `certifications` JSON NULL,
  `coverage_cities` JSON NULL,
  `quality_scores` JSON NULL,
  `reputation_score` INT NULL,
  `badges` JSON NULL,
  `price_range` SMALLINT NULL,
  `promotions` JSON NULL,
  `financing_info` JSON NULL,
  `differentials` JSON NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_companies_slug` (`slug`),
  KEY `idx_companies_owner_id` (`owner_id`),
  KEY `idx_companies_city_id` (`city_id`),
  KEY `idx_companies_featured` (`featured`),
  KEY `idx_companies_status` (`status`),
  KEY `idx_companies_plan` (`plan`),
  CONSTRAINT `fk_companies_owner`
    FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_companies_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_categories` (
  `company_id` CHAR(36) NOT NULL,
  `category_id` CHAR(36) NOT NULL,
  PRIMARY KEY (`company_id`, `category_id`),
  CONSTRAINT `fk_company_categories_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_company_categories_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
