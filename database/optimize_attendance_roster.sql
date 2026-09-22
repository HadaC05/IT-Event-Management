-- Supporting index for date-scoped attendance roster reads.
-- The information_schema check makes this safe to run again.

SET @attendance_event_date_user_index_sql = IF(
  EXISTS(
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='tbl_attendances'
      AND index_name='attendance_event_date_user_index'
  ),
  'SELECT 1',
  'ALTER TABLE tbl_attendances ADD INDEX attendance_event_date_user_index (event_id, attendance_date, user_id)'
);
PREPARE attendance_event_date_user_index_statement FROM @attendance_event_date_user_index_sql;
EXECUTE attendance_event_date_user_index_statement;
DEALLOCATE PREPARE attendance_event_date_user_index_statement;
