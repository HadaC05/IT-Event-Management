ALTER TABLE `tbl_attendance_entries`
  ADD COLUMN `distance_from_venue_box_m` DECIMAL(10,2) NULL AFTER `distance_from_venue_m`;

UPDATE `tbl_attendance_entries` entry_row
SET entry_row.`distance_from_venue_box_m` = CASE
  WHEN entry_row.`scan_latitude` IS NULL
    OR entry_row.`scan_longitude` IS NULL
    OR entry_row.`venue_latitude_snapshot` IS NULL
    OR entry_row.`venue_longitude_snapshot` IS NULL
    OR entry_row.`venue_radius_snapshot_m` IS NULL THEN NULL
  WHEN entry_row.`location_status` = 'inside' THEN 0
  ELSE ROUND(SQRT(
    POW(GREATEST(ABS(entry_row.`scan_latitude` - entry_row.`venue_latitude_snapshot`) * 111320 - entry_row.`venue_radius_snapshot_m`, 0), 2)
    + POW(GREATEST(ABS(entry_row.`scan_longitude` - entry_row.`venue_longitude_snapshot`) * 111320
      * GREATEST(COS(RADIANS(entry_row.`venue_latitude_snapshot`)), 0.000001) - entry_row.`venue_radius_snapshot_m`, 0), 2)
  ), 2)
END;

ALTER TABLE `tbl_attendance_entries`
  ADD INDEX `attendance_entries_box_distance_index` (`location_status`,`distance_from_venue_box_m`);
