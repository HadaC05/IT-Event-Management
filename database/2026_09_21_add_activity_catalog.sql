-- Shared activity catalog, grouped by event type.
CREATE TABLE `tbl_activities` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `activities_event_type_label_unique` (`event_type_id`,`label`),
  KEY `activities_event_type_id_foreign` (`event_type_id`),
  CONSTRAINT `activities_event_type_id_foreign`
    FOREIGN KEY (`event_type_id`) REFERENCES `tbl_event_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve existing activity records by turning each distinct legacy activity
-- name within an event type into a catalog activity. Events without an event
-- type must be assigned one before this migration is run.
INSERT INTO `tbl_activities` (`label`, `description`, `event_type_id`, `status`, `created_at`, `updated_at`)
SELECT ea.`name`, MAX(ea.`description`), e.`event_type_id`, 'active', MIN(ea.`created_at`), MAX(ea.`updated_at`)
FROM `tbl_event_activities` ea
INNER JOIN `tbl_events` e ON e.`id` = ea.`event_id`
WHERE e.`event_type_id` IS NOT NULL
GROUP BY ea.`name`, e.`event_type_id`
ON DUPLICATE KEY UPDATE
  `description` = COALESCE(`tbl_activities`.`description`, VALUES(`description`)),
  `updated_at` = GREATEST(`tbl_activities`.`updated_at`, VALUES(`updated_at`));

ALTER TABLE `tbl_event_activities`
  ADD COLUMN `activity_id` bigint(20) UNSIGNED NULL AFTER `event_id`;

UPDATE `tbl_event_activities` ea
INNER JOIN `tbl_events` e ON e.`id` = ea.`event_id`
INNER JOIN `tbl_activities` a
  ON a.`event_type_id` = e.`event_type_id` AND a.`label` = ea.`name`
SET ea.`activity_id` = a.`id`;

-- `activity_id` is required once every legacy row has been mapped. The status
-- conversion intentionally normalizes any unexpected legacy value to inactive.
UPDATE `tbl_event_activities`
SET `status` = 'inactive'
WHERE `status` NOT IN ('active', 'inactive');

ALTER TABLE `tbl_event_activities`
  DROP COLUMN `description`,
  MODIFY COLUMN `activity_id` bigint(20) UNSIGNED NOT NULL,
  MODIFY COLUMN `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  ADD KEY `event_activities_activity_id_foreign` (`activity_id`),
  ADD CONSTRAINT `event_activities_activity_id_foreign`
    FOREIGN KEY (`activity_id`) REFERENCES `tbl_activities` (`id`) ON DELETE RESTRICT;
