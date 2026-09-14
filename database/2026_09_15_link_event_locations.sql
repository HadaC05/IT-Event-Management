-- Run once after 2026_09_14_add_location_geofence.sql.
-- Preserves existing location text and links matching event records.
ALTER TABLE `tbl_locations`
  ADD COLUMN `parent_location_id` BIGINT(20) UNSIGNED NULL AFTER `type`,
  ADD KEY `locations_parent_location_id_index` (`parent_location_id`),
  ADD CONSTRAINT `locations_parent_location_id_foreign`
    FOREIGN KEY (`parent_location_id`) REFERENCES `tbl_locations` (`id`) ON DELETE RESTRICT;

-- When the database has exactly one general location, attach legacy specific
-- locations to it. Otherwise they remain unassigned until edited by an adviser.
UPDATE `tbl_locations` location_specific
JOIN (
  SELECT MIN(`id`) AS `id`
  FROM `tbl_locations`
  WHERE `type` = 'general'
  HAVING COUNT(*) = 1
) location_general ON 1 = 1
SET location_specific.`parent_location_id` = location_general.`id`
WHERE location_specific.`type` = 'specific' AND location_specific.`parent_location_id` IS NULL;

ALTER TABLE `tbl_events`
  ADD COLUMN `location_id` BIGINT(20) UNSIGNED NULL AFTER `location`,
  ADD KEY `events_location_id_index` (`location_id`),
  ADD CONSTRAINT `events_location_id_foreign`
    FOREIGN KEY (`location_id`) REFERENCES `tbl_locations` (`id`) ON DELETE SET NULL;

UPDATE `tbl_events` event_row
JOIN `tbl_locations` location_row ON LOWER(TRIM(location_row.`name`)) = LOWER(TRIM(event_row.`location`))
SET event_row.`location_id` = location_row.`id`
WHERE event_row.`location_id` IS NULL;
