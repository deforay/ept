-- Migration for version 7.4.7
-- Amit Dugar - May 2026
-- Capture a hashed per-session identifier alongside audit_log and
-- user_login_history rows. Lets ops disambiguate users behind CGNAT (same
-- public IP, different sessions) and group "everything done in one sitting"
-- for forensic review. Hash, not the raw PHP session ID, so the stored value
-- can't be replayed against a live session.

UPDATE `system_config` SET `value` = '7.4.7' WHERE `config` = 'app_version';

ALTER TABLE `audit_log`
    ADD COLUMN `ip_address` VARCHAR(64) NULL DEFAULT NULL AFTER `type`;

ALTER TABLE `audit_log`
    ADD COLUMN `user_agent` VARCHAR(512) NULL DEFAULT NULL AFTER `ip_address`;

ALTER TABLE `audit_log`
    ADD COLUMN `session_hash` VARCHAR(16) NULL DEFAULT NULL AFTER `user_agent`;

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_session_hash` (`session_hash`);

ALTER TABLE `user_login_history`
    ADD COLUMN `session_hash` VARCHAR(16) NULL DEFAULT NULL AFTER `operating_system`;

ALTER TABLE `user_login_history`
    ADD INDEX `idx_user_login_history_session_hash` (`session_hash`);
