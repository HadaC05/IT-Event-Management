-- Warning and Strict attendance policies now both require a current GPS position.
-- Warning permits scans outside the saved square venue boundary; Strict blocks them.
UPDATE tbl_events
SET attendance_location_policy = 'warning'
WHERE attendance_location_policy = 'off';

ALTER TABLE tbl_events
  MODIFY COLUMN attendance_location_policy ENUM('warning','strict') NOT NULL DEFAULT 'warning';
