CREATE TABLE IF NOT EXISTS `company_claims` (
  `id` CHAR(36) NOT NULL,
  `company_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `role_in_company` VARCHAR(255) NULL,
  `phone` VARCHAR(64) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `document` VARCHAR(64) NULL,
  `message` TEXT NULL,
  `proof_url` TEXT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT NULL,
  `reviewed_by` CHAR(36) NULL,
  `reviewed_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_claims_company_user_status` (`company_id`, `user_id`, `status`),
  KEY `idx_company_claims_status_created` (`status`, `created_at`),
  CONSTRAINT `fk_company_claims_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_company_claims_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_company_claims_reviewed_by`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
