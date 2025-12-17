-- Migration for version 7.3.1

ALTER TABLE `temp_mail`
    ADD COLUMN `failure_reason` TEXT NULL AFTER `status`;

UPDATE `system_config` SET `value` = '7.3.1' WHERE `config` = 'app_version';

-- Thana 20-Nov-2025
ALTER TABLE `r_participant_feedback_form` ADD `form_show_to` VARCHAR(50) NULL DEFAULT NULL AFTER `form_content`;

-- Thana 24-Nov-2025
ALTER TABLE `r_feedback_questions` ADD `question_show_to` VARCHAR(256) NULL DEFAULT NULL AFTER `question_type`;
ALTER TABLE `r_participant_feedback_form_files_map` ADD `files_show_to` VARCHAR(255) NULL DEFAULT NULL AFTER `file_name`;

-- Thana 01-Dec-2025
ALTER TABLE shipment
ADD COLUMN previous_status VARCHAR(256) NULL,
ADD COLUMN processing_started_at DATETIME NULL,
ADD COLUMN last_heartbeat DATETIME NULL;

ALTER TABLE queue_report_generation
ADD COLUMN previous_status VARCHAR(256) NULL,
ADD COLUMN processing_started_at DATETIME NULL,
ADD COLUMN last_heartbeat DATETIME NULL;

-- Thana 08-Dec-2025
INSERT INTO `global_config` (`name`, `value`) VALUES ('mail_configuration', '');

-- Thana 09-Dec-2025
ALTER TABLE temp_mail
    MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending',
    ADD COLUMN sent_at       DATETIME NULL AFTER updated_at,
    ADD COLUMN failure_type  VARCHAR(64) NULL AFTER failure_reason;

-- Thana 17-Dec-2025
ALTER TABLE `scheduled_jobs` ADD `initated_by` INT NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `queue_report_generation` ADD `initated_by` INT NULL DEFAULT NULL AFTER `last_heartbeat`;
INSERT INTO `global_config` (`name`, `value`) VALUES ('enable_admin_email_notification', 'yes');