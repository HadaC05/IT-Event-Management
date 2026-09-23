-- Competition schedules and lifecycle statuses. Apply once through the deployment migration runner.
ALTER TABLE tbl_event_activities
  ADD COLUMN event_schedule_id BIGINT UNSIGNED NULL AFTER activity_id,
  MODIFY COLUMN status ENUM('active','upcoming','ongoing','completed','inactive') NOT NULL DEFAULT 'active',
  ADD KEY idx_event_activity_schedule (event_schedule_id),
  ADD CONSTRAINT fk_event_activity_schedule
    FOREIGN KEY (event_schedule_id) REFERENCES tbl_event_attendance_schedules(id)
    ON DELETE SET NULL;

-- Existing competitions are scheduled on the first configured day of their event,
-- so they remain usable when the schedule selector becomes required for new entries.
UPDATE tbl_event_activities ea
JOIN (
  SELECT event_id, MIN(id) AS schedule_id
  FROM tbl_event_attendance_schedules
  GROUP BY event_id
) schedule ON schedule.event_id=ea.event_id
SET ea.event_schedule_id=schedule.schedule_id
WHERE ea.event_schedule_id IS NULL;
