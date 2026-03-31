-- Migration for version 7.3.6
-- Amit Dugar - Mar 2026
UPDATE `system_config` SET `value` = '7.3.6' WHERE `config` = 'app_version';

-- Thana -30-Mar-2026
ALTER TABLE `reference_result_dts` ADD `is_sample_diluted` VARCHAR(50) NULL DEFAULT NULL AFTER `mandatory`;
