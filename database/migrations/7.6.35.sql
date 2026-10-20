-- Migration for version 7.6.35
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.35' WHERE `config` = 'app_version';

-- Login email change: the confirmation link carries a random one-time token.
-- The link used to carry the new address in base64, so anyone who knew the
-- address could confirm it without access to that mailbox. The column holds
-- the SHA-256 hash of the token, so the database alone cannot build a link.
--
-- Idempotent: on a second run MySQL reports a duplicate column (1060) or key
-- (1061), which migrate.php treats as already applied.
ALTER TABLE `data_manager`
    ADD COLUMN `email_change_token_hash` CHAR(64) NULL DEFAULT NULL AFTER `new_email`;
ALTER TABLE `data_manager`
    ADD COLUMN `email_change_expires_at` DATETIME NULL DEFAULT NULL AFTER `email_change_token_hash`;
ALTER TABLE `data_manager`
    ADD UNIQUE KEY `uniq_data_manager_email_change_token` (`email_change_token_hash`);
