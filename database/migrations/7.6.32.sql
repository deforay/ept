-- Migration for version 7.6.32
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.32' WHERE `config` = 'app_version';

-- dts_shipment_corrective_action_map has no index on most installs, so every
-- lookup of a response's corrective actions scans the whole table (118,000 rows
-- on a large install). Some installs hold duplicate pairs, so this is a plain
-- index, not a primary key. The name and columns match installs that have it.
--
-- Idempotent: on a second run MySQL reports a duplicate key name (1061), which
-- migrate.php treats as already applied.
ALTER TABLE `dts_shipment_corrective_action_map` ADD INDEX `shipment_map_id` (`shipment_map_id`, `corrective_action_id`);
