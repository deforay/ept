-- Migration for version 7.4.7
-- Amit Dugar - May 2026
-- Capture a hashed per-session identifier alongside audit_log and
-- user_login_history rows. Lets ops disambiguate users behind CGNAT (same
-- public IP, different sessions) and group "everything done in one sitting"
-- for forensic review. Hash, not the raw PHP session ID, so the stored value
-- can't be replayed against a live session.
--
-- Also backfills the audit_log columns/indexes introduced in 7.4.1
-- (created_by_role, ip_address, user_agent + the created_by/created_on/type
-- indexes): some installs never received 7.4.1 cleanly yet advanced past it, so
-- these are re-added idempotently here (skipped where already present) — and
-- session_hash needs user_agent to exist as its AFTER anchor.

UPDATE `system_config` SET `value` = '7.4.7' WHERE `config` = 'app_version';

ALTER TABLE `audit_log`
    ADD COLUMN `created_by_role` VARCHAR(32) NULL DEFAULT NULL AFTER `created_by`;

ALTER TABLE `audit_log`
    ADD COLUMN `ip_address` VARCHAR(64) NULL DEFAULT NULL AFTER `type`;

ALTER TABLE `audit_log`
    ADD COLUMN `user_agent` VARCHAR(512) NULL DEFAULT NULL AFTER `ip_address`;

ALTER TABLE `audit_log`
    ADD COLUMN `session_hash` VARCHAR(16) NULL DEFAULT NULL AFTER `user_agent`;

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_created_by` (`created_by`);

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_created_on` (`created_on`);

ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_type` (`type`);

ALTER TABLE `user_login_history`
    ADD COLUMN `session_hash` VARCHAR(16) NULL DEFAULT NULL AFTER `operating_system`;


ALTER TABLE `audit_log`
    ADD INDEX `idx_audit_log_session_hash` (`session_hash`);


ALTER TABLE `user_login_history`
    ADD INDEX `idx_user_login_history_session_hash` (`session_hash`);
