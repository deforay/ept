-- Migration for version 7.3.4
-- Add milestone timestamp columns to track shipment processing independently of status

UPDATE `system_config` SET `value` = '7.3.4' WHERE `config` = 'app_version';

-- Add new milestone timestamp columns
ALTER TABLE `shipment`
  ADD COLUMN `evaluated_at` DATETIME NULL DEFAULT NULL AFTER `status`,
  ADD COLUMN `reports_generated_at` DATETIME NULL DEFAULT NULL AFTER `evaluated_at`,
  ADD COLUMN `finalized_at` DATETIME NULL DEFAULT NULL AFTER `reports_generated_at`;

-- Backfill existing data based on current status
-- Shipments with status 'evaluated' or 'finalized' have been evaluated
UPDATE `shipment` SET `evaluated_at` = `updated_on_admin`
WHERE `status` IN ('evaluated', 'finalized') AND `evaluated_at` IS NULL;

-- Shipments with status 'finalized' have had reports generated and been finalized
UPDATE `shipment` SET `reports_generated_at` = `updated_on_admin`
WHERE `status` = 'finalized' AND `reports_generated_at` IS NULL;

UPDATE `shipment` SET `finalized_at` = `updated_on_admin`
WHERE `status` = 'finalized' AND `finalized_at` IS NULL;
