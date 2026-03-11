-- AppProfileSafe Manual — MySQL Schema

-- ── Users ──
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `gender` varchar(10) NOT NULL DEFAULT '',
  `username` varchar(20) NOT NULL DEFAULT '',
  `password` tinytext NOT NULL,
  `fullname` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `role` varchar(20) NOT NULL DEFAULT 'editor',
  `change_password` tinyint(1) NOT NULL DEFAULT 0,
  `page_tree` text NOT NULL,
  `notify_mentions` tinyint(1) NOT NULL DEFAULT 1,
  `preferences` text NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'inactive',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Pages (with soft delete) ──
CREATE TABLE IF NOT EXISTS `pages` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT 0,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `modified_by` bigint UNSIGNED NOT NULL DEFAULT 0,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `position` bigint UNSIGNED NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(160) NOT NULL DEFAULT '',
  `content` longtext NOT NULL,
  `views` bigint UNSIGNED NOT NULL DEFAULT 0,
  `status` varchar(10) NOT NULL DEFAULT 'inactive',
  `workflow_status` varchar(20) NOT NULL DEFAULT 'published',
  `publish_at` timestamp NULL DEFAULT NULL,
  `expire_at` timestamp NULL DEFAULT NULL,
  `review_due_at` timestamp NULL DEFAULT NULL,
  `requires_ack` tinyint(1) NOT NULL DEFAULT 0,
  `locale` varchar(10) NOT NULL DEFAULT 'en',
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `position` (`position`),
  KEY `status` (`status`),
  KEY `deleted_at` (`deleted_at`),
  FULLTEXT KEY `ft_search` (`title`, `description`, `content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Revisions ──
CREATE TABLE IF NOT EXISTS `page_revisions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(160) NOT NULL DEFAULT '',
  `content` longtext NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT 0,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revision_note` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Translations ──
CREATE TABLE IF NOT EXISTS `page_translations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `locale` varchar(10) NOT NULL DEFAULT 'en',
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(160) NOT NULL DEFAULT '',
  `content` longtext NOT NULL,
  `modified_by` bigint UNSIGNED NOT NULL DEFAULT 0,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `base_modified` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_locale` (`page_id`, `locale`),
  KEY `locale` (`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Feedback ──
CREATE TABLE IF NOT EXISTS `page_feedback` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `rating` tinyint NOT NULL DEFAULT 0,
  `comment` text NOT NULL,
  `client_ip` varchar(45) NOT NULL DEFAULT '',
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Comments (internal editor/contributor comments) ──
CREATE TABLE IF NOT EXISTS `page_comments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `user_id` (`user_id`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Pagesindex ──
CREATE TABLE IF NOT EXISTS `pagesindex` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `keyword` text NOT NULL,
  `page_id` bigint UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Audit Log ──
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL DEFAULT 0,
  `action` varchar(50) NOT NULL DEFAULT '',
  `entity_type` varchar(50) NOT NULL DEFAULT '',
  `entity_id` bigint UNSIGNED NOT NULL DEFAULT 0,
  `details` text NOT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `entity_type` (`entity_type`, `entity_id`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Tags (categories/tags for cross-referencing) ──
CREATE TABLE IF NOT EXISTS `page_tags` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `tag` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `tag` (`tag`),
  UNIQUE KEY `page_tag_unique` (`page_id`, `tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ── Page Reviews (mehrstufiger Review-Prozess) ──
CREATE TABLE IF NOT EXISTS `page_reviews` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `reviewer_id` bigint UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `comment` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Subscriptions (Abonnements / Benachrichtigungen) ──
CREATE TABLE IF NOT EXISTS `page_subscriptions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_user` (`page_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Acknowledgements (Lesebestätigung) ──
CREATE TABLE IF NOT EXISTS `page_acknowledgements` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `revision_id` bigint UNSIGNED DEFAULT NULL,
  `confirmed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_user_rev` (`page_id`, `user_id`, `revision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Inline Comments (absatzbezogen) ──
CREATE TABLE IF NOT EXISTS `inline_comments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `anchor` varchar(100) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT 0,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `resolved` (`resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Webhooks ──
CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `url` varchar(500) NOT NULL DEFAULT '',
  `events` varchar(255) NOT NULL DEFAULT '',
  `secret` varchar(100) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Media Files (zentrale Medienverwaltung) ──
CREATE TABLE IF NOT EXISTS `media_files` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `original_name` varchar(255) NOT NULL DEFAULT '',
  `mime_type` varchar(100) NOT NULL DEFAULT '',
  `file_size` bigint UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_by` bigint UNSIGNED NOT NULL DEFAULT 0,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


