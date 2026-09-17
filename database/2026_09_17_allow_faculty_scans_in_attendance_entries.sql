-- Faculty use the existing student attendance records and scan entries.
-- Their scans have no SBO assignment or SBO event activity.
ALTER TABLE tbl_attendance_entries MODIFY sbo_event_assignment_id BIGINT UNSIGNED NULL;
ALTER TABLE tbl_attendance_entries MODIFY activity_id BIGINT UNSIGNED NULL;
