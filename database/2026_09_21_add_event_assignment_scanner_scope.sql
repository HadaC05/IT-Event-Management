-- Attendance scanning is authorized per event responsibility, rather than only
-- by the officer's account-level default scanner access.
ALTER TABLE `tbl_sbo_event_assignments`
  ADD COLUMN `scanner_mode` ENUM('specific','general') NULL AFTER `responsibility_id`,
  ADD COLUMN `scanner_team_id` BIGINT(20) UNSIGNED NULL AFTER `scanner_mode`,
  ADD KEY `sbo_event_assignments_scanner_team_id_foreign` (`scanner_team_id`),
  ADD CONSTRAINT `sbo_event_assignments_scanner_team_id_foreign`
    FOREIGN KEY (`scanner_team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE SET NULL;

-- Preserve existing attendance access when converting historical assignments.
UPDATE `tbl_sbo_event_assignments` assignment_row
JOIN `tbl_officer_responsibilities` responsibility
  ON responsibility.`id` = assignment_row.`responsibility_id`
JOIN `tbl_sbo_officer_assignments` officer
  ON officer.`id` = assignment_row.`officer_assignment_id`
SET assignment_row.`scanner_mode` = CASE
      WHEN responsibility.`code` = 'attendance' THEN COALESCE(officer.`scanner_mode`, 'specific')
      ELSE NULL
    END,
    assignment_row.`scanner_team_id` = CASE
      WHEN responsibility.`code` = 'attendance' AND COALESCE(officer.`scanner_mode`, 'specific') = 'specific'
        THEN COALESCE(officer.`scanner_team_id`, assignment_row.`team_id`)
      ELSE NULL
    END;
