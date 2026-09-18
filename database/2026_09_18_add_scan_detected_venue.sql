-- Run once after 2026_09_18_add_event_locations.sql.
-- Saves the matched (or nearest, for an outside Warning scan) event venue.
ALTER TABLE `tbl_attendance_entries`
  ADD COLUMN `venue_location_id` BIGINT(20) UNSIGNED NULL AFTER `location_unavailable_reason`,
  ADD COLUMN `venue_latitude_snapshot` DECIMAL(10,7) NULL AFTER `venue_name_snapshot`,
  ADD COLUMN `venue_longitude_snapshot` DECIMAL(10,7) NULL AFTER `venue_latitude_snapshot`,
  ADD COLUMN `venue_radius_snapshot_m` DECIMAL(10,2) NULL AFTER `venue_longitude_snapshot`,
  ADD KEY `attendance_entries_venue_location_index` (`venue_location_id`),
  ADD CONSTRAINT `attendance_entries_venue_location_foreign`
    FOREIGN KEY (`venue_location_id`) REFERENCES `tbl_locations` (`id`) ON DELETE SET NULL;

-- Preserve the original primary venue for historical scans made before
-- multiple event locations were supported.
UPDATE `tbl_attendance_entries` entry_row
JOIN `tbl_attendances` attendance_row ON attendance_row.`id` = entry_row.`attendance_id`
JOIN `tbl_events` event_row ON event_row.`id` = attendance_row.`event_id`
LEFT JOIN `tbl_locations` location_row ON location_row.`id` = event_row.`location_id`
SET entry_row.`venue_location_id` = event_row.`location_id`,
    entry_row.`venue_name_snapshot` = COALESCE(entry_row.`venue_name_snapshot`, location_row.`name`),
    entry_row.`venue_latitude_snapshot` = location_row.`latitude`,
    entry_row.`venue_longitude_snapshot` = location_row.`longitude`,
    entry_row.`venue_radius_snapshot_m` = location_row.`radius`
WHERE entry_row.`venue_location_id` IS NULL;
