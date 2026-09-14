-- Run this migration once against an existing event_db database.
-- Existing locations are preserved and can be given coordinates later.
ALTER TABLE `tbl_locations`
  ADD COLUMN `latitude` DECIMAL(10,7) NULL AFTER `type`,
  ADD COLUMN `longitude` DECIMAL(10,7) NULL AFTER `latitude`,
  ADD COLUMN `radius` DECIMAL(10,2) NULL COMMENT 'Square geofence center-to-edge distance in meters' AFTER `longitude`;

