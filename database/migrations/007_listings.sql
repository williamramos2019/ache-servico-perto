CREATE TABLE IF NOT EXISTS `listing_categories` (
  `id` CHAR(36) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `icon` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_listing_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `listing_categories` (`id`, `slug`, `name`, `icon`, `sort_order`, `is_active`, `created_at`) VALUES
  (UUID(), 'eletronicos', 'Eletrônicos', 'Smartphone', 1, 1, UTC_TIMESTAMP(3)),
  (UUID(), 'moveis', 'Móveis', 'Sofa', 2, 1, UTC_TIMESTAMP(3)),
  (UUID(), 'veiculos', 'Veículos', 'Car', 3, 1, UTC_TIMESTAMP(3)),
  (UUID(), 'imoveis', 'Imóveis', 'Home', 4, 1, UTC_TIMESTAMP(3)),
  (UUID(), 'moda', 'Moda e Beleza', 'Shirt', 5, 1, UTC_TIMESTAMP(3)),
  (UUID(), 'servicos', 'Serviços', 'Wrench', 6, 1, UTC_TIMESTAMP(3)),
  (UUID(), 'bebe-infantil', 'Bebê e Infantil', 'Baby', 7, 1, UTC_TIMESTAMP(3)),
  (UUID(), 'casa-jardim', 'Casa e Jardim', 'Flower', 8, 1, UTC_TIMESTAMP(3)),
  (UUID(), 'esportes', 'Esportes e Lazer', 'Dumbbell', 9, 1, UTC_TIMESTAMP(3)),
  (UUID(), 'outros', 'Outros', 'Package', 99, 1, UTC_TIMESTAMP(3));

CREATE TABLE IF NOT EXISTS `listings` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `category_slug` VARCHAR(255) NOT NULL,
  `city_id` CHAR(36) NULL,
  `neighborhood` VARCHAR(255) NULL,
  `price` DECIMAL(12,2) NULL,
  `condition` VARCHAR(16) NOT NULL DEFAULT 'usado',
  `status` VARCHAR(16) NOT NULL DEFAULT 'ativo',
  `images` JSON NULL,
  `contact_phone` VARCHAR(64) NULL,
  `views_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_listings_slug` (`slug`),
  KEY `idx_listings_city_status_created` (`city_id`, `status`, `created_at`),
  KEY `idx_listings_user_id` (`user_id`),
  KEY `idx_listings_category_status_created` (`category_slug`, `status`, `created_at`),
  CONSTRAINT `fk_listings_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_listings_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_listings_category_slug`
    FOREIGN KEY (`category_slug`) REFERENCES `listing_categories` (`slug`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `listing_messages` (
  `id` CHAR(36) NOT NULL,
  `listing_id` CHAR(36) NOT NULL,
  `buyer_id` CHAR(36) NOT NULL,
  `seller_id` CHAR(36) NOT NULL,
  `sender_id` CHAR(36) NOT NULL,
  `body` TEXT NOT NULL,
  `read_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_listing_messages_listing_id` (`listing_id`),
  CONSTRAINT `fk_listing_messages_listing`
    FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_listing_messages_buyer`
    FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_listing_messages_seller`
    FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_listing_messages_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `listing_reports` (
  `id` CHAR(36) NOT NULL,
  `listing_id` CHAR(36) NOT NULL,
  `reporter_id` CHAR(36) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `notes` TEXT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'aberto',
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_listing_reports_listing_id` (`listing_id`),
  CONSTRAINT `fk_listing_reports_listing`
    FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_listing_reports_reporter`
    FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media` (
  `id` CHAR(36) NOT NULL,
  `url` TEXT NOT NULL,
  `kind` VARCHAR(32) NOT NULL,
  `meta` JSON NULL,
  `owner_id` CHAR(36) NULL,
  `company_id` CHAR(36) NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_media_owner_id` (`owner_id`),
  KEY `idx_media_company_id` (`company_id`),
  CONSTRAINT `fk_media_owner`
    FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_media_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
