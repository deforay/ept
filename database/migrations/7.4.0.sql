-- Migration for version 7.4.0
-- Amit Dugar - May 2026
UPDATE `system_config` SET `value` = '7.4.0' WHERE `config` = 'app_version';
