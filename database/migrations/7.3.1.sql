-- Migration for version 7.3.1

ALTER TABLE `temp_mail`
    ADD COLUMN `failure_reason` TEXT NULL AFTER `status`;

UPDATE `system_config` SET `value` = '7.3.1' WHERE `config` = 'app_version';

-- Thana 20-Nov-2025
ALTER TABLE `r_participant_feedback_form` ADD `form_show_to` VARCHAR(50) NULL DEFAULT NULL AFTER `form_content`;

-- Thana 24-Nov-2025
ALTER TABLE `r_feedback_questions` ADD `question_show_to` VARCHAR(256) NULL DEFAULT NULL AFTER `question_type`;
ALTER TABLE `r_participant_feedback_form_files_map` ADD `files_show_to` VARCHAR(255) NULL DEFAULT NULL AFTER `file_name`;

