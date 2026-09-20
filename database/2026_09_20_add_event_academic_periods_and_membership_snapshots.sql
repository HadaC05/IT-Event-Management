-- Explicit event periods and immutable event membership snapshots.

CREATE TABLE IF NOT EXISTS `tbl_academic_periods` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_year_id` bigint(20) UNSIGNED NOT NULL,
  `term_code` varchar(30) NOT NULL,
  `term_name` varchar(80) NOT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_period_school_year_term_unique` (`school_year_id`,`term_code`),
  KEY `academic_period_active_index` (`is_active`,`starts_on`,`ends_on`),
  CONSTRAINT `academic_period_school_year_foreign` FOREIGN KEY (`school_year_id`) REFERENCES `tbl_school_years` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbl_academic_periods` (`school_year_id`,`term_code`,`term_name`,`is_active`)
SELECT sy.id,
       CASE WHEN UPPER(sy.label) REGEXP 'SEM(ESTER)?[[:space:]]*(II|2)' THEN 'second_semester'
            WHEN UPPER(sy.label) LIKE '%SUMMER%' THEN 'summer' ELSE 'first_semester' END,
       CASE WHEN UPPER(sy.label) REGEXP 'SEM(ESTER)?[[:space:]]*(II|2)' THEN 'Second Semester'
            WHEN UPPER(sy.label) LIKE '%SUMMER%' THEN 'Summer Term' ELSE 'First Semester' END,
       1
FROM `tbl_school_years` sy
WHERE NOT EXISTS (SELECT 1 FROM `tbl_academic_periods` ap WHERE ap.school_year_id=sy.id);

ALTER TABLE `tbl_events`
  ADD COLUMN `academic_period_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `audience_type`,
  ADD KEY `events_academic_period_index` (`academic_period_id`),
  ADD CONSTRAINT `events_academic_period_foreign` FOREIGN KEY (`academic_period_id`) REFERENCES `tbl_academic_periods` (`id`) ON DELETE RESTRICT;

UPDATE `tbl_events` e
SET e.academic_period_id=(SELECT ap.id FROM `tbl_academic_periods` ap
  ORDER BY CASE WHEN ap.school_year_id=COALESCE(
                  (SELECT t.school_year_id FROM tbl_event_team et JOIN tbl_teams t ON t.id=et.team_id WHERE et.event_id=e.id GROUP BY t.school_year_id ORDER BY COUNT(*) DESC LIMIT 1),
                  (SELECT t.school_year_id FROM tbl_scores score JOIN tbl_teams t ON t.id=score.team_id WHERE score.event_id=e.id GROUP BY t.school_year_id ORDER BY COUNT(*) DESC LIMIT 1),
                  (SELECT t.school_year_id FROM tbl_sbo_event_assignments assignment JOIN tbl_event_attendance_schedules schedule ON schedule.id=assignment.event_schedule_id JOIN tbl_teams t ON t.id=assignment.team_id WHERE schedule.event_id=e.id GROUP BY t.school_year_id ORDER BY COUNT(*) DESC LIMIT 1)
                ) THEN 0 ELSE 1 END,
           CASE WHEN MONTH(e.start_at) BETWEEN 1 AND 5 AND ap.term_code='second_semester' THEN 0
                WHEN MONTH(e.start_at) BETWEEN 6 AND 12 AND ap.term_code='first_semester' THEN 0 ELSE 1 END,
           ap.is_active DESC,ap.school_year_id DESC,ap.id DESC LIMIT 1)
WHERE e.academic_period_id IS NULL;

ALTER TABLE `tbl_events` MODIFY `academic_period_id` bigint(20) UNSIGNED NOT NULL;

CREATE TABLE IF NOT EXISTS `tbl_event_membership_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `academic_period_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED DEFAULT NULL,
  `student_id_number` varchar(50) DEFAULT NULL,
  `student_name` varchar(255) NOT NULL,
  `year_level_id` bigint(20) UNSIGNED DEFAULT NULL,
  `year_level_label` varchar(80) DEFAULT NULL,
  `team_name` varchar(255) DEFAULT NULL,
  `team_color` varchar(20) DEFAULT NULL,
  `captured_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_membership_snapshot_event_user_unique` (`event_id`,`user_id`),
  KEY `event_membership_snapshot_event_team_index` (`event_id`,`team_id`),
  KEY `event_membership_snapshot_period_index` (`academic_period_id`),
  CONSTRAINT `event_membership_snapshot_event_foreign` FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_membership_snapshot_period_foreign` FOREIGN KEY (`academic_period_id`) REFERENCES `tbl_academic_periods` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `event_membership_snapshot_user_foreign` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `event_membership_snapshot_team_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tbl_event_membership_snapshots` (`event_id`,`academic_period_id`,`user_id`,`team_id`,`student_id_number`,`student_name`,`year_level_id`,`year_level_label`,`team_name`,`team_color`,`captured_at`)
SELECT e.id,e.academic_period_id,u.id,MAX(t.id),u.id_number,
       TRIM(CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name)),
       u.year_level,MAX(yl.label),MAX(t.name),MAX(t.color),CURRENT_TIMESTAMP
FROM `tbl_events` e
JOIN `tbl_academic_periods` ap ON ap.id=e.academic_period_id
JOIN `tbl_users` u
JOIN `tbl_roles` r ON r.id=u.role_id AND r.name='Student'
JOIN `tbl_user_statuses` us ON us.id=u.status AND us.label='active'
LEFT JOIN `tbl_year_levels` yl ON yl.id=u.year_level
LEFT JOIN `tbl_team_user` tu ON tu.user_id=u.id
LEFT JOIN `tbl_teams` t ON t.id=tu.team_id AND t.school_year_id=ap.school_year_id
WHERE (e.audience_type='all_students'
   OR (e.audience_type='selected_tribes' AND EXISTS(SELECT 1 FROM tbl_event_team et WHERE et.event_id=e.id AND et.team_id=t.id))
   OR (e.audience_type='selected_year_levels' AND EXISTS(SELECT 1 FROM tbl_event_year_level eyl WHERE eyl.event_id=e.id AND eyl.year_level_id=u.year_level))
   OR (e.audience_type='specific_students' AND EXISTS(SELECT 1 FROM tbl_event_participants ep WHERE ep.event_id=e.id AND ep.user_id=u.id)))
  AND NOT EXISTS(SELECT 1 FROM tbl_event_membership_snapshots existing WHERE existing.event_id=e.id AND existing.user_id=u.id)
GROUP BY e.id,u.id;

ALTER TABLE `tbl_score_sheets`
  DROP INDEX `score_sheets_event_activity_unique`,
  ADD COLUMN `team_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `activity_id`,
  ADD COLUMN `event_schedule_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `team_id`,
  ADD COLUMN `session_code` varchar(12) DEFAULT NULL AFTER `event_schedule_id`,
  ADD UNIQUE KEY `score_sheets_event_activity_team_unique` (`event_id`,`activity_id`,`team_id`),
  ADD KEY `score_sheets_team_foreign` (`team_id`),
  ADD KEY `score_sheets_schedule_foreign` (`event_schedule_id`),
  ADD CONSTRAINT `score_sheets_team_foreign` FOREIGN KEY (`team_id`) REFERENCES `tbl_teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `score_sheets_schedule_foreign` FOREIGN KEY (`event_schedule_id`) REFERENCES `tbl_event_attendance_schedules` (`id`) ON DELETE SET NULL;

UPDATE `tbl_score_sheets` sheet
JOIN `tbl_sbo_event_assignments` assignment ON assignment.id=sheet.sbo_event_assignment_id
SET sheet.team_id=assignment.team_id,sheet.event_schedule_id=assignment.event_schedule_id,sheet.session_code=assignment.session_code
WHERE sheet.team_id IS NULL;

DROP VIEW IF EXISTS `vw_finalized_scores`;
CREATE VIEW `vw_finalized_scores` AS
SELECT score.* FROM `tbl_scores` score
JOIN `tbl_score_categories` category ON category.id=score.score_category_id
JOIN `tbl_score_sheets` sheet ON sheet.event_id=score.event_id
 AND sheet.activity_id=category.activity_id AND sheet.team_id=score.team_id AND sheet.status='finalized';
