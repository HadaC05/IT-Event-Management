-- Run once after 2026_09_15_link_event_locations.sql.
-- tbl_events.location_id remains the primary venue used by attendance GPS.
CREATE TABLE `tbl_event_locations` (
  `event_id` BIGINT(20) UNSIGNED NOT NULL,
  `location_id` BIGINT(20) UNSIGNED NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`,`location_id`),
  KEY `event_locations_location_id_index` (`location_id`),
  KEY `event_locations_primary_index` (`event_id`,`is_primary`),
  CONSTRAINT `event_locations_event_id_foreign`
    FOREIGN KEY (`event_id`) REFERENCES `tbl_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_locations_location_id_foreign`
    FOREIGN KEY (`location_id`) REFERENCES `tbl_locations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve every existing event venue as that event's primary venue.
INSERT INTO `tbl_event_locations` (`event_id`,`location_id`,`is_primary`)
SELECT `id`,`location_id`,1
FROM `tbl_events`
WHERE `location_id` IS NOT NULL;
