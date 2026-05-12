-- Migration for version 7.4.4
-- Amit Dugar - May 2026
-- Bounce processor: when an outgoing email comes back as a hard bounce, we
-- stamp `*_status = 'hard_bounce'` and capture the SMTP reason for debugging.
-- bin/process-bounces.php is the worker; it uses two system_config rows for
-- IMAP UID tracking so re-runs only process new messages.

UPDATE `system_config` SET `value` = '7.4.4' WHERE `config` = 'app_version';

ALTER TABLE `participant`
    ADD COLUMN `last_bounce_at` DATETIME NULL DEFAULT NULL AFTER `email_status_checked_at`,
    ADD COLUMN `last_bounce_reason` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL AFTER `last_bounce_at`;

ALTER TABLE `data_manager`
    ADD COLUMN `last_bounce_at` DATETIME NULL DEFAULT NULL AFTER `email_status_checked_at`,
    ADD COLUMN `last_bounce_reason` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL AFTER `last_bounce_at`;

-- IMAP state. Keyed under bounce_* so a future second mailbox can add bounce_<name>_*.
INSERT INTO `system_config` (`config`, `value`, `display_name`) VALUES
    ('bounce_last_uid', '0', 'Highest IMAP UID processed by bin/process-bounces.php'),
    ('bounce_uidvalidity', '0', 'UIDVALIDITY of the bounce mailbox last time it was read; reset triggers a re-scan')
ON DUPLICATE KEY UPDATE `value` = `value`;
