-- Additive indexes for paged team-management reads.
-- Each information_schema check makes retries safe after a partial failure.

SET @team_status_index_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='tbl_teams' AND index_name='teams_status_name_index'),
  'SELECT 1',
  'ALTER TABLE tbl_teams ADD INDEX teams_status_name_index (is_active, name)'
);
PREPARE team_status_index_statement FROM @team_status_index_sql;
EXECUTE team_status_index_statement;
DEALLOCATE PREPARE team_status_index_statement;

SET @team_year_status_index_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='tbl_teams' AND index_name='teams_school_year_status_name_index'),
  'SELECT 1',
  'ALTER TABLE tbl_teams ADD INDEX teams_school_year_status_name_index (school_year_id, is_active, name)'
);
PREPARE team_year_status_index_statement FROM @team_year_status_index_sql;
EXECUTE team_year_status_index_statement;
DEALLOCATE PREPARE team_year_status_index_statement;

SET @user_role_status_name_index_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='tbl_users' AND index_name='users_role_status_name_index'),
  'SELECT 1',
  'ALTER TABLE tbl_users ADD INDEX users_role_status_name_index (role_id, status, last_name, first_name, id)'
);
PREPARE user_role_status_name_index_statement FROM @user_role_status_name_index_sql;
EXECUTE user_role_status_name_index_statement;
DEALLOCATE PREPARE user_role_status_name_index_statement;
