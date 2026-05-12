-- Migration for version 7.4.3
-- Amit Dugar - May 2026
-- Adds email-validity tracking to participant and data_manager so bulk-mail
-- flows can skip dead addresses without per-send DNS work. Stamps are filled
-- by bin/check-participant-emails.php on a recurring schedule.

UPDATE `system_config` SET `value` = '7.4.3' WHERE `config` = 'app_version';

ALTER TABLE `participant`
    ADD COLUMN `email_status` ENUM('unknown','valid','invalid_syntax','invalid_domain','hard_bounce') NOT NULL DEFAULT 'unknown' AFTER `email`,
    ADD COLUMN `additional_email_status` ENUM('unknown','valid','invalid_syntax','invalid_domain','hard_bounce') NOT NULL DEFAULT 'unknown' AFTER `additional_email`,
    ADD COLUMN `email_status_checked_at` DATETIME NULL DEFAULT NULL AFTER `additional_email_status`,
    ADD INDEX `idx_participant_email_checked_at` (`email_status_checked_at`);

ALTER TABLE `data_manager`
    ADD COLUMN `primary_email_status` ENUM('unknown','valid','invalid_syntax','invalid_domain','hard_bounce') NOT NULL DEFAULT 'unknown' AFTER `primary_email`,
    ADD COLUMN `secondary_email_status` ENUM('unknown','valid','invalid_syntax','invalid_domain','hard_bounce') NOT NULL DEFAULT 'unknown' AFTER `secondary_email`,
    ADD COLUMN `email_status_checked_at` DATETIME NULL DEFAULT NULL AFTER `secondary_email_status`,
    ADD INDEX `idx_dm_email_checked_at` (`email_status_checked_at`);
