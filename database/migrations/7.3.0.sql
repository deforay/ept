-- Migration for version 7.2.3

-- Amit 23-Sep-2025
UPDATE `system_config` SET `value` = '7.3.0' WHERE `system_config`.`config` = 'app_version';
