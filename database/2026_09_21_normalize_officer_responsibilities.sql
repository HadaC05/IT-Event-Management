-- Lookup values used to authorize SBO Officer event duties.
CREATE TABLE `tbl_officer_responsibilities` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `label` varchar(60) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `officer_responsibilities_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbl_officer_responsibilities` (`code`, `label`) VALUES
  ('attendance', 'Attendance'),
  ('scoring', 'Scoring'),
  ('media', 'Media')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `updated_at` = CURRENT_TIMESTAMP;

ALTER TABLE `tbl_sbo_event_assignments`
  ADD COLUMN `responsibility_id` bigint(20) UNSIGNED NULL AFTER `team_id`;

UPDATE `tbl_sbo_event_assignments` assignment_row
JOIN `tbl_officer_responsibilities` responsibility
  ON responsibility.`code` = assignment_row.`responsibility`
SET assignment_row.`responsibility_id` = responsibility.`id`;

ALTER TABLE `tbl_sbo_event_assignments`
  MODIFY COLUMN `responsibility_id` bigint(20) UNSIGNED NOT NULL,
  ADD KEY `sbo_event_assignments_responsibility_id_foreign` (`responsibility_id`),
  ADD CONSTRAINT `sbo_event_assignments_responsibility_id_foreign`
    FOREIGN KEY (`responsibility_id`) REFERENCES `tbl_officer_responsibilities` (`id`) ON DELETE RESTRICT,
  DROP COLUMN `responsibility`;
