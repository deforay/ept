-- Migration for version 7.6.12
-- Amit Dugar - Jul 2026
UPDATE `system_config` SET `value` = '7.6.12' WHERE `config` = 'app_version';

-- Active/Inactive lifecycle for possible results (user-configured / generic tests for now).
-- An 'inactive' (retired) result is hidden from NEW data-entry pickers -- the participant
-- response form and the shipment reference/expected-result picker -- but stays fully intact
-- for historical shipments: its code still resolves to a label on reports and in evaluation.
-- migrate.php makes ADD COLUMN idempotent (column_exists), so re-running is safe.
ALTER TABLE `r_possibleresult` ADD COLUMN `status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `sort_order`;
