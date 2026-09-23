-- Adviser-controlled release of standings to students. Start hidden.
CREATE TABLE IF NOT EXISTS tbl_leaderboard_publication (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  student_visible TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbl_leaderboard_publication (id, student_visible)
VALUES (1, 0);
