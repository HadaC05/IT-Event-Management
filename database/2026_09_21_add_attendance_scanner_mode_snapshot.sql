ALTER TABLE `tbl_attendance_entries`
  ADD COLUMN `scanner_mode_snapshot` ENUM('specific','general') NULL AFTER `recorded_by`;

UPDATE `tbl_attendance_entries` entry_row
LEFT JOIN `tbl_sbo_event_assignments` event_assignment
  ON event_assignment.`id` = entry_row.`sbo_event_assignment_id`
SET entry_row.`scanner_mode_snapshot` = CASE
  WHEN event_assignment.`scanner_mode` = 'general' THEN 'general'
  ELSE 'specific'
END;

ALTER TABLE `tbl_attendance_entries`
  MODIFY COLUMN `scanner_mode_snapshot` ENUM('specific','general') NOT NULL DEFAULT 'specific' AFTER `recorded_by`;
