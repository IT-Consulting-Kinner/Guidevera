-- AppProfileSafe Manual — MySQL Schema

-- ── Users ──
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `gender` varchar(10) NOT NULL DEFAULT '',
  `username` varchar(20) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `fullname` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `role` varchar(20) NOT NULL DEFAULT 'contributor',
  `change_password` tinyint(1) NOT NULL DEFAULT 0,
  `page_tree` text NOT NULL,
  `notify_mentions` tinyint(1) NOT NULL DEFAULT 1,
  `preferences` text NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'inactive',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `status` (`status`),
  KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Pages (with soft delete) ──
CREATE TABLE IF NOT EXISTS `pages` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `created` datetime NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
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
  KEY `created_by` (`created_by`),
  KEY `modified_by` (`modified_by`),
  KEY `workflow_status` (`workflow_status`),
  FULLTEXT KEY `ft_search` (`title`, `description`, `content`),
  CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_id`) REFERENCES `pages`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Revisions ──
CREATE TABLE IF NOT EXISTS `page_revisions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(160) NOT NULL DEFAULT '',
  `content` longtext NOT NULL,
  `created_by` bigint UNSIGNED NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `revision_note` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `created` (`created`),
  CONSTRAINT `fk_revisions_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
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
  `modified` datetime NOT NULL,
  `base_modified` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_locale` (`page_id`, `locale`),
  KEY `locale` (`locale`),
  CONSTRAINT `fk_translations_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Feedback ──
CREATE TABLE IF NOT EXISTS `page_feedback` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `rating` tinyint NOT NULL DEFAULT 0,
  `comment` text NOT NULL,
  `client_ip` varchar(45) NOT NULL DEFAULT '',
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_feedback_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Comments (internal editor/contributor comments) ──
CREATE TABLE IF NOT EXISTS `page_comments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `user_id` (`user_id`),
  KEY `created` (`created`),
  CONSTRAINT `fk_comments_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Pagesindex ──
CREATE TABLE IF NOT EXISTS `pagesindex` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `keyword` text NOT NULL,
  `page_id` bigint UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  CONSTRAINT `fk_pagesindex_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
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
  `created` datetime NOT NULL,
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
  UNIQUE KEY `page_tag_unique` (`page_id`, `tag`),
  CONSTRAINT `fk_tags_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ── Page Reviews (mehrstufiger Review-Prozess) ──
CREATE TABLE IF NOT EXISTS `page_reviews` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `reviewer_id` bigint UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `comment` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_reviews_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Subscriptions (Abonnements / Benachrichtigungen) ──
CREATE TABLE IF NOT EXISTS `page_subscriptions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_user` (`page_id`, `user_id`),
  CONSTRAINT `fk_subscriptions_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Page Acknowledgements (Lesebestätigung) ──
CREATE TABLE IF NOT EXISTS `page_acknowledgements` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `locale` varchar(10) NOT NULL DEFAULT 'en',
  `revision_id` bigint UNSIGNED DEFAULT NULL,
  `confirmed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_user_locale` (`page_id`, `user_id`, `locale`),
  CONSTRAINT `fk_ack_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ack_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Inline Comments (absatzbezogen) ──
CREATE TABLE IF NOT EXISTS `inline_comments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `anchor` varchar(100) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  KEY `resolved` (`resolved`),
  CONSTRAINT `fk_inline_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inline_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Webhooks ──
CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `url` varchar(500) NOT NULL DEFAULT '',
  `events` varchar(255) NOT NULL DEFAULT '',
  `secret` varchar(100) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Media Files (zentrale Medienverwaltung) ──
CREATE TABLE IF NOT EXISTS `media_folders` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `created_by` bigint UNSIGNED NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `fk_folders_parent` FOREIGN KEY (`parent_id`) REFERENCES `media_folders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `media_files` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_id` bigint UNSIGNED DEFAULT NULL,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `original_name` varchar(255) NOT NULL DEFAULT '',
  `mime_type` varchar(100) NOT NULL DEFAULT '',
  `file_size` bigint UNSIGNED NOT NULL DEFAULT 0,
  `display_mode` varchar(10) NOT NULL DEFAULT 'download',
  `visible_guest` tinyint(1) NOT NULL DEFAULT 1,
  `visible_editor` tinyint(1) NOT NULL DEFAULT 1,
  `visible_contributor` tinyint(1) NOT NULL DEFAULT 1,
  `visible_admin` tinyint(1) NOT NULL DEFAULT 1,
  `download_count` int UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_by` bigint UNSIGNED NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `folder_id` (`folder_id`),
  KEY `filename` (`filename`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_files_folder` FOREIGN KEY (`folder_id`) REFERENCES `media_folders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Migration: Add threading to inline_comments
-- ALTER TABLE inline_comments ADD COLUMN parent_id bigint UNSIGNED DEFAULT NULL AFTER user_id;
-- ALTER TABLE inline_comments ADD KEY parent_id (parent_id);
