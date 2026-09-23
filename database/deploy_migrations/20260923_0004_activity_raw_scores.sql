-- Activity-level raw scoring and finalized placement points. Safe to retry.
CREATE TABLE IF NOT EXISTS tbl_activity_raw_scores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  raw_score DECIMAL(12,2) NOT NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_activity_raw_score (event_id, activity_id, team_id),
  KEY idx_activity_raw_score_activity (event_id, activity_id),
  KEY idx_activity_raw_score_team (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_activity_score_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id BIGINT UNSIGNED NOT NULL,
  activity_id BIGINT UNSIGNED NOT NULL,
  team_id BIGINT UNSIGNED NOT NULL,
  raw_score DECIMAL(12,2) NOT NULL,
  placement TINYINT UNSIGNED NOT NULL,
  overall_points TINYINT UNSIGNED NOT NULL,
  finalized_by BIGINT UNSIGNED NULL,
  finalized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_activity_score_result (event_id, activity_id, team_id),
  KEY idx_activity_score_result_activity (event_id, activity_id),
  KEY idx_activity_score_result_team (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
