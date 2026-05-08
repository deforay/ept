-- Migration for version 7.4.2
-- Amit Dugar - May 2026
-- Password reset: opaque random token + 24h expiry replaces base64(email) URL.

UPDATE `system_config` SET `value` = '7.4.2' WHERE `config` = 'app_version';

ALTER TABLE `data_manager`
    ADD COLUMN `password_reset_token` CHAR(64) NULL DEFAULT NULL AFTER `password`;

ALTER TABLE `data_manager`
    ADD COLUMN `password_reset_expires_at` DATETIME NULL DEFAULT NULL AFTER `password_reset_token`;

ALTER TABLE `data_manager`
    ADD UNIQUE KEY `uniq_data_manager_pw_reset_token` (`password_reset_token`);
