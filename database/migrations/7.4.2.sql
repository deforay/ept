-- Migration for version 7.4.2
-- Amit Dugar - May 2026
-- Password reset: opaque random token + 24h expiry replaces base64(email) URL.

UPDATE `system_config` SET `value` = '7.4.2' WHERE `config` = 'app_version';

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'data_manager' AND COLUMN_NAME = 'password_reset_token');
SET @sql := IF(@col = 0,
    "ALTER TABLE `data_manager` ADD COLUMN `password_reset_token` CHAR(64) NULL DEFAULT NULL AFTER `password`",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'data_manager' AND COLUMN_NAME = 'password_reset_expires_at');
SET @sql := IF(@col = 0,
    "ALTER TABLE `data_manager` ADD COLUMN `password_reset_expires_at` DATETIME NULL DEFAULT NULL AFTER `password_reset_token`",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'data_manager' AND INDEX_NAME = 'uniq_data_manager_pw_reset_token');
SET @sql := IF(@idx = 0,
    "ALTER TABLE `data_manager` ADD UNIQUE INDEX `uniq_data_manager_pw_reset_token` (`password_reset_token`)",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
