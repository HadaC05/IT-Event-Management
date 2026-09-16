-- Run once on the existing event_db database. Existing officers stay team-restricted.
ALTER TABLE tbl_sbo_officer_assignments
    ADD COLUMN scanner_mode VARCHAR(8) NOT NULL DEFAULT 'specific' AFTER team_id;
