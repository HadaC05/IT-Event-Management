-- Run once on the existing MySQL/MariaDB database. Existing scans remain Time In.
ALTER TABLE tbl_event_attendance_schedules
  ADD COLUMN whole_day_in_close_time TIME NULL AFTER whole_day_in_time,
  ADD COLUMN whole_day_out_open_time TIME NULL AFTER whole_day_out_time,
  ADD COLUMN morning_in_close_time TIME NULL AFTER morning_in_time,
  ADD COLUMN morning_out_open_time TIME NULL AFTER morning_out_time,
  ADD COLUMN afternoon_in_close_time TIME NULL AFTER afternoon_in_time,
  ADD COLUMN afternoon_out_open_time TIME NULL AFTER afternoon_out_time;

UPDATE tbl_event_attendance_schedules SET
  whole_day_in_close_time = IF(whole_day_out_time IS NULL, NULL, GREATEST(whole_day_in_time, SUBTIME(whole_day_out_time, '00:30:00'))),
  whole_day_out_open_time = IF(whole_day_out_time IS NULL, NULL, GREATEST(whole_day_in_time, SUBTIME(whole_day_out_time, '00:30:00'))),
  morning_in_close_time = IF(morning_out_time IS NULL, NULL, GREATEST(morning_in_time, SUBTIME(morning_out_time, '00:30:00'))),
  morning_out_open_time = IF(morning_out_time IS NULL, NULL, GREATEST(morning_in_time, SUBTIME(morning_out_time, '00:30:00'))),
  afternoon_in_close_time = IF(afternoon_out_time IS NULL, NULL, GREATEST(afternoon_in_time, SUBTIME(afternoon_out_time, '00:30:00'))),
  afternoon_out_open_time = IF(afternoon_out_time IS NULL, NULL, GREATEST(afternoon_in_time, SUBTIME(afternoon_out_time, '00:30:00')));

-- Legacy sessions shorter than 30 minutes split at the midpoint instead of producing a zero-length Time In window.
UPDATE tbl_event_attendance_schedules SET
  whole_day_in_close_time = IF(whole_day_in_close_time <= whole_day_in_time AND whole_day_out_time > whole_day_in_time,
    ADDTIME(whole_day_in_time, SEC_TO_TIME(FLOOR(TIME_TO_SEC(TIMEDIFF(whole_day_out_time, whole_day_in_time))/2))), whole_day_in_close_time),
  whole_day_out_open_time = IF(whole_day_out_open_time <= whole_day_in_time AND whole_day_out_time > whole_day_in_time,
    ADDTIME(whole_day_in_time, SEC_TO_TIME(FLOOR(TIME_TO_SEC(TIMEDIFF(whole_day_out_time, whole_day_in_time))/2))), whole_day_out_open_time),
  morning_in_close_time = IF(morning_in_close_time <= morning_in_time AND morning_out_time > morning_in_time,
    ADDTIME(morning_in_time, SEC_TO_TIME(FLOOR(TIME_TO_SEC(TIMEDIFF(morning_out_time, morning_in_time))/2))), morning_in_close_time),
  morning_out_open_time = IF(morning_out_open_time <= morning_in_time AND morning_out_time > morning_in_time,
    ADDTIME(morning_in_time, SEC_TO_TIME(FLOOR(TIME_TO_SEC(TIMEDIFF(morning_out_time, morning_in_time))/2))), morning_out_open_time),
  afternoon_in_close_time = IF(afternoon_in_close_time <= afternoon_in_time AND afternoon_out_time > afternoon_in_time,
    ADDTIME(afternoon_in_time, SEC_TO_TIME(FLOOR(TIME_TO_SEC(TIMEDIFF(afternoon_out_time, afternoon_in_time))/2))), afternoon_in_close_time),
  afternoon_out_open_time = IF(afternoon_out_open_time <= afternoon_in_time AND afternoon_out_time > afternoon_in_time,
    ADDTIME(afternoon_in_time, SEC_TO_TIME(FLOOR(TIME_TO_SEC(TIMEDIFF(afternoon_out_time, afternoon_in_time))/2))), afternoon_out_open_time);

ALTER TABLE tbl_attendance_qr_tokens
  ADD COLUMN phase VARCHAR(3) NOT NULL DEFAULT 'in' AFTER session,
  ADD COLUMN schedule_date DATE NULL AFTER phase,
  ADD COLUMN issued_at DATETIME NULL AFTER token,
  ADD COLUMN expires_at DATETIME NULL AFTER issued_at,
  ADD COLUMN used_at DATETIME NULL AFTER expires_at,
  DROP INDEX attendance_qr_tokens_event_id_user_id_session_unique,
  ADD UNIQUE KEY attendance_qr_tokens_student_phase_unique (event_id,user_id,session,phase);

-- Historical tokens are made invalid while existing scan and attendance records stay untouched.
UPDATE tbl_attendance_qr_tokens SET schedule_date=DATE(updated_at),expires_at='2000-01-01 00:00:00';

ALTER TABLE tbl_attendance_entries
  ADD COLUMN phase VARCHAR(3) NOT NULL DEFAULT 'in' AFTER session_code,
  DROP INDEX attendance_entries_session_unique,
  DROP INDEX attendance_entries_student_session_unique,
  ADD UNIQUE KEY attendance_entries_session_phase_unique (attendance_id,event_schedule_id,session_code,phase,activity_id,team_id),
  ADD UNIQUE KEY attendance_entries_student_phase_unique (attendance_id,event_schedule_id,session_code,phase);
