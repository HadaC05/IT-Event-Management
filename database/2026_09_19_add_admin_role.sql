-- Adds the explicit Admin role required by the shared media feed.
-- Existing SBO administrator accounts remain supported as a legacy admin role.
INSERT INTO tbl_roles (name, created_at, updated_at)
SELECT 'Admin', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM tbl_roles WHERE name = 'Admin');
