CREATE TABLE IF NOT EXISTS `system_settings` (
  `key` VARCHAR(191) NOT NULL,
  `value` JSON NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plans_config` (
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `price_cents` INT NOT NULL DEFAULT 0,
  `duration_days` INT NOT NULL DEFAULT 30,
  `max_photos` INT NOT NULL DEFAULT 3,
  `features` JSON NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `system_settings` (`key`, `value`, `is_public`, `updated_at`) VALUES
  ('search_radius_km', CAST('10' AS JSON), 1, UTC_TIMESTAMP(3)),
  ('map_enabled', CAST('true' AS JSON), 1, UTC_TIMESTAMP(3)),
  ('max_upload_mb', CAST('5' AS JSON), 1, UTC_TIMESTAMP(3));

INSERT IGNORE INTO `plans_config` (`slug`, `name`, `price_cents`, `duration_days`, `max_photos`, `features`, `sort`, `updated_at`) VALUES
  ('free', 'Grátis', 0, 0, 3, CAST('["Presença no catálogo","Contato WhatsApp","Mapa básico"]' AS JSON), 0, UTC_TIMESTAMP(3)),
  ('premium', 'Premium', 9900, 30, 999, CAST('["Destaque no topo","Galeria ilimitada","Banner personalizado","Selo Verificado","Botão WhatsApp destacado","CTA fixo mobile"]' AS JSON), 1, UTC_TIMESTAMP(3)),
  ('featured', 'Destaque', 19900, 30, 999, CAST('["Tudo do Premium","Aparece na home","Recomendações automáticas","Vídeo institucional"]' AS JSON), 2, UTC_TIMESTAMP(3));

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `type` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NULL,
  `payload` JSON NULL,
  `read_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_notifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `user_id` CHAR(36) NOT NULL,
  `atualizacoes` TINYINT(1) NOT NULL DEFAULT 1,
  `blog` TINYINT(1) NOT NULL DEFAULT 1,
  `empresas` TINYINT(1) NOT NULL DEFAULT 1,
  `eventos` TINYINT(1) NOT NULL DEFAULT 1,
  `marketplace` TINYINT(1) NOT NULL DEFAULT 1,
  `novidades` TINYINT(1) NOT NULL DEFAULT 1,
  `promocoes` TINYINT(1) NOT NULL DEFAULT 1,
  `som` TINYINT(1) NOT NULL DEFAULT 1,
  `vibracao` TINYINT(1) NOT NULL DEFAULT 1,
  `quiet_hours_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `quiet_start` INT NOT NULL DEFAULT 22,
  `quiet_end` INT NOT NULL DEFAULT 7,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_notification_preferences_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_templates` (
  `id` CHAR(36) NOT NULL,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(64) NOT NULL,
  `title_template` TEXT NOT NULL,
  `body_template` TEXT NOT NULL,
  `default_url` TEXT NULL,
  `icon_url` TEXT NULL,
  `emoji` VARCHAR(16) NULL,
  `color` VARCHAR(32) NULL,
  `sort` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_templates_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `notification_templates` (`id`, `slug`, `name`, `category`, `title_template`, `body_template`, `emoji`, `color`, `sort`, `created_at`) VALUES
  (UUID(), 'promocao', 'Promoção', 'promocao', 'Promoção especial pra você!', 'Confira as melhores ofertas de hoje no AgendaAqui.', '🎉', '#F97316', 1, UTC_TIMESTAMP(3)),
  (UUID(), 'novidade', 'Novidade', 'novidade', 'Novidade no AgendaAqui', 'Acabou de chegar uma novidade que você vai gostar.', '🚀', '#3B82F6', 2, UTC_TIMESTAMP(3)),
  (UUID(), 'destaque', 'Empresa em destaque', 'empresa', 'Empresa em destaque', 'Conheça a empresa que está bombando na sua cidade.', '⭐', '#FACC15', 3, UTC_TIMESTAMP(3)),
  (UUID(), 'comunicado', 'Comunicado', 'sistema', 'Aviso importante', 'Uma novidade oficial do AgendaAqui pra você.', '📢', '#0EA5E9', 4, UTC_TIMESTAMP(3)),
  (UUID(), 'noticia', 'Notícia', 'noticias', 'Notícia quentinha', 'Fique por dentro do que está acontecendo na sua região.', '📰', '#8B5CF6', 5, UTC_TIMESTAMP(3)),
  (UUID(), 'oferta', 'Oferta relâmpago', 'promocao', 'Oferta imperdível', 'Aproveite antes que acabe.', '🎁', '#EC4899', 6, UTC_TIMESTAMP(3)),
  (UUID(), 'evento', 'Evento', 'evento', 'Evento chegando', 'Não perca o próximo evento da sua cidade.', '📅', '#22C55E', 7, UTC_TIMESTAMP(3)),
  (UUID(), 'manutencao', 'Manutenção', 'sistema', 'Manutenção programada', 'O AgendaAqui passará por manutenção rápida.', '⚠️', '#EF4444', 8, UTC_TIMESTAMP(3));

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `endpoint` TEXT NOT NULL,
  `p256dh` TEXT NOT NULL,
  `auth` TEXT NOT NULL,
  `is_pwa` TINYINT(1) NOT NULL DEFAULT 0,
  `platform` VARCHAR(64) NULL,
  `user_agent` TEXT NULL,
  `last_seen_at` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_subscriptions_endpoint` (`endpoint`(191)),
  KEY `idx_push_subscriptions_user_id` (`user_id`),
  CONSTRAINT `fk_push_subscriptions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `push_notifications` (
  `id` CHAR(36) NOT NULL,
  `template_id` CHAR(36) NULL,
  `created_by` CHAR(36) NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `url` TEXT NULL,
  `category` VARCHAR(64) NOT NULL,
  `priority` VARCHAR(32) NOT NULL DEFAULT 'normal',
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `emoji` VARCHAR(16) NULL,
  `color` VARCHAR(32) NULL,
  `icon_url` TEXT NULL,
  `image_url` TEXT NULL,
  `audience` JSON NULL,
  `buttons` JSON NULL,
  `scheduled_at` DATETIME(3) NULL,
  `sent_at` DATETIME(3) NULL,
  `sent_count` INT NOT NULL DEFAULT 0,
  `delivered_count` INT NOT NULL DEFAULT 0,
  `opened_count` INT NOT NULL DEFAULT 0,
  `clicked_count` INT NOT NULL DEFAULT 0,
  `failed_count` INT NOT NULL DEFAULT 0,
  `unsubscribed_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_push_notifications_template`
    FOREIGN KEY (`template_id`) REFERENCES `notification_templates` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_push_notifications_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `push_deliveries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notification_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `subscription_id` CHAR(36) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'queued',
  `error` TEXT NULL,
  `browser` VARCHAR(64) NULL,
  `device` VARCHAR(64) NULL,
  `sent_at` DATETIME(3) NULL,
  `delivered_at` DATETIME(3) NULL,
  `opened_at` DATETIME(3) NULL,
  `clicked_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_deliveries_notification_user_sub` (`notification_id`, `user_id`, `subscription_id`),
  CONSTRAINT `fk_push_deliveries_notification`
    FOREIGN KEY (`notification_id`) REFERENCES `push_notifications` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_push_deliveries_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_push_deliveries_subscription`
    FOREIGN KEY (`subscription_id`) REFERENCES `push_subscriptions` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `push_inbox` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` CHAR(36) NOT NULL,
  `notification_id` CHAR(36) NOT NULL,
  `received_at` DATETIME(3) NOT NULL,
  `read_at` DATETIME(3) NULL,
  `archived_at` DATETIME(3) NULL,
  `favorite_at` DATETIME(3) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_inbox_user_notification` (`user_id`, `notification_id`),
  KEY `idx_push_inbox_user_received` (`user_id`, `received_at`),
  CONSTRAINT `fk_push_inbox_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_push_inbox_notification`
    FOREIGN KEY (`notification_id`) REFERENCES `push_notifications` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qa_tickets` (
  `id` CHAR(36) NOT NULL,
  `ticket_number` VARCHAR(32) NOT NULL,
  `type` VARCHAR(32) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'open',
  `priority` VARCHAR(32) NOT NULL DEFAULT 'normal',
  `description` TEXT NOT NULL,
  `page_url` TEXT NULL,
  `page_title` VARCHAR(255) NULL,
  `screenshot_url` TEXT NULL,
  `video_url` TEXT NULL,
  `fingerprint` VARCHAR(128) NULL,
  `ip` VARCHAR(64) NULL,
  `user_id` CHAR(36) NULL,
  `user_email` VARCHAR(255) NULL,
  `user_name` VARCHAR(255) NULL,
  `assigned_to` CHAR(36) NULL,
  `city_id` CHAR(36) NULL,
  `device` JSON NULL,
  `console_logs` JSON NULL,
  `network_logs` JSON NULL,
  `extra` JSON NULL,
  `resolved_at` DATETIME(3) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qa_tickets_ticket_number` (`ticket_number`),
  KEY `idx_qa_tickets_status` (`status`),
  KEY `idx_qa_tickets_fingerprint` (`fingerprint`),
  CONSTRAINT `fk_qa_tickets_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_qa_tickets_assigned_to`
    FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_qa_tickets_city`
    FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qa_ticket_comments` (
  `id` CHAR(36) NOT NULL,
  `ticket_id` CHAR(36) NOT NULL,
  `author_id` CHAR(36) NULL,
  `body` TEXT NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qa_ticket_comments_ticket_id` (`ticket_id`),
  CONSTRAINT `fk_qa_ticket_comments_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `qa_tickets` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_qa_ticket_comments_author`
    FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qa_ticket_events` (
  `id` CHAR(36) NOT NULL,
  `ticket_id` CHAR(36) NOT NULL,
  `actor_id` CHAR(36) NULL,
  `kind` VARCHAR(64) NOT NULL,
  `payload` JSON NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qa_ticket_events_ticket_id` (`ticket_id`),
  CONSTRAINT `fk_qa_ticket_events_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `qa_tickets` (`id`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT,
  CONSTRAINT `fk_qa_ticket_events_actor`
    FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
