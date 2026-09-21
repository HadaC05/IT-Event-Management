-- Attendance scanner authorization is now stored on each event responsibility.
-- Officer assignments retain only officer-account and home-tribe information.
ALTER TABLE `tbl_sbo_officer_assignments`
  DROP FOREIGN KEY `sbo_officer_assignments_scanner_team_id_foreign`,
  DROP INDEX `sbo_officer_assignments_scanner_team_id_foreign`,
  DROP COLUMN `scanner_team_id`,
  DROP COLUMN `scanner_mode`;
