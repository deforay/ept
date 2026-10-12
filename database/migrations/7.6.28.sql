-- Migration for version 7.6.28
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.28' WHERE `config` = 'app_version';
