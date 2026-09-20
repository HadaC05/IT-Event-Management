-- Repair account identity collisions and normalize the current academic period.
-- Safe to run once on the existing event_db database.

START TRANSACTION;

-- A roster import must never silently turn a student into an Officer. Restore
-- imported users that have the Officer role but have never had an Officer
-- assignment. Their student profile and tribe membership remain intact.
UPDATE tbl_users u
JOIN tbl_roles officer_role ON officer_role.id=u.role_id AND officer_role.name='SBO Officer'
JOIN tbl_roles student_role ON student_role.name='Student'
JOIN tbl_student_profiles profile ON profile.user_id=u.id
LEFT JOIN tbl_sbo_officer_assignments assignment ON assignment.officer_user_id=u.id
SET u.role_id=student_role.id,
    u.officer_team_id=NULL,
    u.updated_at=CURRENT_TIMESTAMP
WHERE assignment.id IS NULL;

-- Officer access is a separate login identity. Give every actual Officer
-- account a unique non-delivery alias rather than copying the student's email.
UPDATE tbl_users u
JOIN tbl_roles role ON role.id=u.role_id AND role.name='SBO Officer'
JOIN tbl_sbo_officer_assignments assignment ON assignment.officer_user_id=u.id
SET u.email=CONCAT(LOWER(u.username),'@officer.itevents.local'),
    u.updated_at=CURRENT_TIMESTAMP;

-- The imported source represented both year and term in one label. Store the
-- school year once and keep the semester in tbl_academic_periods.
UPDATE tbl_school_years
SET label='2026-2027',updated_at=CURRENT_TIMESTAMP
WHERE label IN ('SY 26-27 SEM I','SY 2026-2027 SEM I','2026-2027 SEM I');

UPDATE tbl_academic_periods
SET term_code='first_semester',term_name='First Semester',updated_at=CURRENT_TIMESTAMP
WHERE term_code IN ('first_semester','semester_i','sem_i')
   OR term_name IN ('First Semester','Semester I','Sem I');

UPDATE tbl_student_profiles
SET school_year_label='SY 2026-2027 · First Semester',updated_at=CURRENT_TIMESTAMP
WHERE school_year_label IN ('SY 26-27 SEM I','SY 2026-2027 SEM I','2026-2027 SEM I');

UPDATE tbl_sbo_officer_assignments assignment
JOIN tbl_teams team ON team.id=assignment.team_id
JOIN tbl_school_years school_year ON school_year.id=team.school_year_id
SET assignment.term=school_year.label,assignment.updated_at=CURRENT_TIMESTAMP;

COMMIT;

-- Application checks provide friendly errors; this constraint closes the
-- simultaneous-request race and makes email login deterministic.
SET @email_index_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema=DATABASE() AND table_name='tbl_users' AND index_name='users_email_unique'
);
SET @email_index_sql := IF(
    @email_index_exists=0,
    'ALTER TABLE tbl_users ADD UNIQUE KEY users_email_unique (email)',
    'SELECT 1'
);
PREPARE email_index_statement FROM @email_index_sql;
EXECUTE email_index_statement;
DEALLOCATE PREPARE email_index_statement;
