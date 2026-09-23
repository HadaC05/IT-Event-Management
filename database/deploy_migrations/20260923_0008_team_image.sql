-- Optional tribe image shown in adviser management and the student team page.
ALTER TABLE tbl_teams ADD COLUMN image_path VARCHAR(255) NULL AFTER color;
