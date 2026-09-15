-- Run once on event_db. Preserves existing attendance and venue records.
-- Check for pre-existing duplicate (attendance_id,event_schedule_id,session_code)
-- groups before importing; consolidate them manually if any are present.
ALTER TABLE tbl_events
  ADD COLUMN attendance_location_policy ENUM('off','warning','strict') NOT NULL DEFAULT 'off' AFTER location_id;

ALTER TABLE tbl_attendance_entries
  ADD COLUMN scan_latitude DECIMAL(10,7) NULL AFTER scanned_at,
  ADD COLUMN scan_longitude DECIMAL(10,7) NULL AFTER scan_latitude,
  ADD COLUMN location_accuracy_m DECIMAL(10,2) NULL AFTER scan_longitude,
  ADD COLUMN distance_from_venue_m DECIMAL(10,2) NULL AFTER location_accuracy_m,
  ADD COLUMN location_status ENUM('inside','outside','unavailable') NOT NULL DEFAULT 'unavailable' AFTER distance_from_venue_m,
  ADD COLUMN location_captured_at DATETIME NULL AFTER location_status,
  ADD COLUMN location_unavailable_reason VARCHAR(120) NULL AFTER location_captured_at,
  ADD COLUMN venue_name_snapshot VARCHAR(255) NULL AFTER location_unavailable_reason,
  ADD UNIQUE KEY attendance_entries_student_session_unique (attendance_id,event_schedule_id,session_code),
  ADD KEY attendance_entries_recent_event_index (event_schedule_id,session_code,scanned_at),
  ADD KEY attendance_entries_location_status_index (location_status,scanned_at);

CREATE TABLE tbl_sbo_scan_rate_limits (
  officer_user_id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY,
  window_started_at DATETIME NOT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  CONSTRAINT sbo_scan_rate_limits_officer_foreign
    FOREIGN KEY (officer_user_id) REFERENCES tbl_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
