-- Migration for version 7.4.1
-- Amit Dugar - May 2026
UPDATE `system_config` SET `value` = '7.4.1' WHERE `config` = 'app_version';

-- Strengthen audit_log for non-repudiation: capture role, IP, user agent.
-- Also index columns the audit-log feed filters on. Idempotent via info_schema
-- so it is safe to re-run on partially-migrated instances.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'created_by_role');
SET @sql := IF(@col = 0,
    "ALTER TABLE `audit_log` ADD COLUMN `created_by_role` VARCHAR(32) NULL DEFAULT NULL AFTER `created_by`",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'ip_address');
SET @sql := IF(@col = 0,
    "ALTER TABLE `audit_log` ADD COLUMN `ip_address` VARCHAR(64) NULL DEFAULT NULL AFTER `type`",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'user_agent');
SET @sql := IF(@col = 0,
    "ALTER TABLE `audit_log` ADD COLUMN `user_agent` VARCHAR(512) NULL DEFAULT NULL AFTER `ip_address`",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND INDEX_NAME = 'idx_audit_log_created_by');
SET @sql := IF(@idx = 0,
    "ALTER TABLE `audit_log` ADD INDEX `idx_audit_log_created_by` (`created_by`)",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND INDEX_NAME = 'idx_audit_log_created_on');
SET @sql := IF(@idx = 0,
    "ALTER TABLE `audit_log` ADD INDEX `idx_audit_log_created_on` (`created_on`)",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND INDEX_NAME = 'idx_audit_log_type');
SET @sql := IF(@idx = 0,
    "ALTER TABLE `audit_log` ADD INDEX `idx_audit_log_type` (`type`)",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
