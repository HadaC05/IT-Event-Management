-- Keep the officer's organizational home tribe separate from attendance scanner authorization.
-- team_id remains the immutable home tribe for this officer assignment.
-- scanner_team_id is populated only when scanner_mode = 'specific'.

ALTER TABLE tbl_sbo_officer_assignments
    ADD COLUMN scanner_team_id BIGINT UNSIGNED NULL AFTER scanner_mode,
    ADD INDEX sbo_officer_assignments_scanner_team_id_foreign (scanner_team_id),
    ADD CONSTRAINT sbo_officer_assignments_scanner_team_id_foreign
        FOREIGN KEY (scanner_team_id) REFERENCES tbl_teams(id) ON DELETE SET NULL;

UPDATE tbl_sbo_officer_assignments
SET scanner_team_id = CASE
    WHEN scanner_mode = 'specific' THEN team_id
    ELSE NULL
END;
