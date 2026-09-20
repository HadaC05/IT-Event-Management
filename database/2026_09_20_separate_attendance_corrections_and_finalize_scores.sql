-- Preserve scanner-produced attendance as evidence and store Adviser corrections separately.
ALTER TABLE `tbl_attendances`
    ADD COLUMN `manual_status` varchar(20) DEFAULT NULL AFTER `status`,
    ADD COLUMN `manual_corrected_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `recorded_by`,
    ADD COLUMN `manual_corrected_at` timestamp NULL DEFAULT NULL AFTER `manual_corrected_by`,
    ADD COLUMN `manual_reason` varchar(255) DEFAULT NULL AFTER `manual_corrected_at`,
    ADD KEY `attendances_manual_corrected_by_foreign` (`manual_corrected_by`),
    ADD KEY `attendances_event_manual_status_index` (`event_id`, `manual_status`),
    ADD CONSTRAINT `attendances_manual_corrected_by_foreign`
        FOREIGN KEY (`manual_corrected_by`) REFERENCES `tbl_users` (`id`) ON DELETE SET NULL;

-- Rows without scan entries were created manually by the old Adviser roster.
UPDATE `tbl_attendances` AS attendance
SET attendance.manual_status = attendance.status,
    attendance.manual_corrected_by = attendance.recorded_by,
    attendance.manual_corrected_at = COALESCE(attendance.updated_at, attendance.created_at, CURRENT_TIMESTAMP)
WHERE NOT EXISTS (
    SELECT 1
    FROM `tbl_attendance_entries` AS entry
    WHERE entry.attendance_id = attendance.id
);

CREATE OR REPLACE VIEW `vw_attendance_effective` AS
SELECT attendance.*,
       COALESCE(attendance.manual_status, attendance.status) AS effective_status
FROM `tbl_attendances` AS attendance;

-- Adviser-managed score sheets do not require an SBO Officer assignment.
ALTER TABLE `tbl_score_sheets`
    MODIFY `sbo_event_assignment_id` bigint(20) UNSIGNED DEFAULT NULL;

-- Only finalized score sheets are official. Draft rows remain available to score editors.
CREATE OR REPLACE VIEW `vw_finalized_scores` AS
SELECT score.*
FROM `tbl_scores` AS score
JOIN `tbl_score_categories` AS category ON category.id = score.score_category_id
JOIN `tbl_score_sheets` AS sheet
  ON sheet.event_id = score.event_id
 AND sheet.activity_id = category.activity_id
 AND sheet.status = 'finalized';
