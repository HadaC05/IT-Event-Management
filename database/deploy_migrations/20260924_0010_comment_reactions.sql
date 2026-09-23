CREATE TABLE IF NOT EXISTS `tbl_comment_reactions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `comment_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'like',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comment_reactions_comment_user_unique` (`comment_id`,`user_id`),
  KEY `comment_reactions_user_index` (`user_id`),
  CONSTRAINT `comment_reactions_comment_foreign` FOREIGN KEY (`comment_id`) REFERENCES `tbl_post_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_reactions_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
