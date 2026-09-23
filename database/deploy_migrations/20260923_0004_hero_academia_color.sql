-- Hero Academia is the yellow team in the current school-year roster.
-- This is safe to retry and leaves team memberships and historical snapshots unchanged.
UPDATE tbl_teams
SET color = '#FACC15', updated_at = CURRENT_TIMESTAMP
WHERE name = 'Hero Academia' AND color <> '#FACC15';
