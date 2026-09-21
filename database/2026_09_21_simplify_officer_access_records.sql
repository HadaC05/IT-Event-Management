-- Officer assignments now represent only the creation and lifecycle of an
-- SBO Officer login. Event duties, teams, and attendance access are stored in
-- tbl_sbo_event_assignments.
ALTER TABLE `tbl_sbo_officer_assignments`
  DROP FOREIGN KEY `sbo_officer_assignments_team_id_foreign`,
  DROP INDEX `sbo_officer_assignments_team_id_foreign`,
  DROP INDEX `officer_student_term_status_index`,
  DROP COLUMN `team_id`,
  DROP COLUMN `position`,
  DROP COLUMN `term`,
  ADD KEY `officer_student_status_index` (`student_id`,`status`);

UPDATE `tbl_users` user_row
JOIN `tbl_roles` role ON role.id=user_row.role_id AND role.name='SBO Officer'
SET user_row.`officer_team_id`=NULL;
