CREATE TABLE IF NOT EXISTS `tbl_post_images` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_images_order_unique` (`post_id`,`sort_order`),
  KEY `post_images_post_index` (`post_id`),
  CONSTRAINT `post_images_post_foreign` FOREIGN KEY (`post_id`) REFERENCES `tbl_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbl_post_images` (`post_id`,`image_path`,`sort_order`)
SELECT p.`id`,p.`image_path`,0
FROM `tbl_posts` p
WHERE p.`image_path` IS NOT NULL AND p.`image_path`<>''
  AND NOT EXISTS (SELECT 1 FROM `tbl_post_images` pi WHERE pi.`post_id`=p.`id`);
