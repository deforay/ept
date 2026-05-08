-- Migration for version 7.4.1
-- Amit Dugar - May 2026
-- Strengthen audit_log for non-repudiation: capture role, IP, user agent.
-- Also index columns the audit-log feed filters on.

UPDATE `system_config` SET `value` = '7.4.1' WHERE `config` = 'app_version';

ALTER TABLE `audit_log`
    ADD COLUMN `created_by_role` VARCHAR(32) NULL DEFAULT NULL AFTER `created_by`;

ALTER TABLE `audit_log`
    ADD COLUMN `ip_address` VARCHAR(64) NULL DEFAULT NULL AFTER `type`;

ALTER TABLE `audit_log`
    ADD COLUMN `user_agent` VARCHAR(512) NULL DEFAULT NULL AFTER `ip_address`;

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_created_by` (`created_by`);

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_created_on` (`created_on`);

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_type` (`type`);
