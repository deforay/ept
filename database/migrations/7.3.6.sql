-- Migration for version 7.3.6
-- Amit Dugar - Mar 2026
UPDATE `system_config` SET `value` = '7.3.6' WHERE `config` = 'app_version';
