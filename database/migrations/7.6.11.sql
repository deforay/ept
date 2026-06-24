-- Migration for version 7.6.11
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.11' WHERE `config` = 'app_version';

-- Shipment cancellation / expiry. A cancelled shipment is permanently locked:
-- no admin actions and no participant responses (treated like a finalized
-- shipment, but it can never be undone). Separate ALTERs so each column is
-- independently idempotent — migrate.php treats 1060 (Duplicate column name)
-- as success, so re-running is a no-op.
ALTER TABLE `shipment` ADD COLUMN `cancelled_at` DATETIME NULL DEFAULT NULL AFTER `finalized_at`;
ALTER TABLE `shipment` ADD COLUMN `cancelled_by` VARCHAR(255) NULL DEFAULT NULL AFTER `cancelled_at`;
ALTER TABLE `shipment` ADD COLUMN `cancellation_reason` TEXT NULL DEFAULT NULL AFTER `cancelled_by`;
