-- Migration for version 7.3.5
-- Amit Dugar - Feb 2026
-- Create certificate_batches table for tracking certificate generation batches
-- Add detected_fields columns to certificate_templates table

UPDATE `system_config` SET `value` = '7.3.5' WHERE `config` = 'app_version';

-- Create certificate_batches table
CREATE TABLE IF NOT EXISTS certificate_batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(100) NOT NULL,
    shipment_ids TEXT NOT NULL,
    status ENUM('pending','generating','generated','approved','distributed','failed') DEFAULT 'pending',
    download_url VARCHAR(500) NULL,
    folder_path VARCHAR(500) NULL,
    excellence_count INT DEFAULT 0,
    participation_count INT DEFAULT 0,
    skipped_count INT DEFAULT 0,
    error_message TEXT NULL,
    created_by INT NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_on DATETIME NULL,
    distributed_on DATETIME NULL,
    INDEX idx_status (status)
);

-- Add detected_fields columns to certificate_templates table for storing PDF form field metadata
-- p_detected_fields: JSON array of field names detected in participation certificate PDF
-- e_detected_fields: JSON array of field names detected in excellence certificate PDF
ALTER TABLE `certificate_templates`
    ADD COLUMN IF NOT EXISTS `p_detected_fields` TEXT NULL COMMENT 'JSON array of detected PDF form fields for participation certificate' AFTER `participation_certificate`;
ALTER TABLE `certificate_templates`    
    ADD COLUMN IF NOT EXISTS `e_detected_fields` TEXT NULL COMMENT 'JSON array of detected PDF form fields for excellence certificate' AFTER `excellence_certificate`;


-- Insert home name in globalconfig
INSERT INTO `global_config` (`name`, `value`) VALUES ('home', '');

-- Amit 02-Mar-2026


CREATE TABLE IF NOT EXISTS `participant_feedback_answer` (
  `answer_id` int NOT NULL,
  `shipment_id` int NOT NULL,
  `participant_id` int DEFAULT NULL,
  `question_id` int NOT NULL,
  `map_id` int NOT NULL,
  `answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `updated_datetime` datetime DEFAULT NULL,
  `modified_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `participant_feedback_answer`
  ADD PRIMARY KEY (`answer_id`);

ALTER TABLE `participant_feedback_answer`
  ADD KEY `map_id` (`map_id`);

ALTER TABLE `participant_feedback_answer`
  ADD KEY `shipment_id` (`shipment_id`);

ALTER TABLE `participant_feedback_answer`
  ADD KEY `participant_id` (`participant_id`);

ALTER TABLE `participant_feedback_answer`
  ADD KEY `question_id` (`question_id`);

ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_1` FOREIGN KEY (`map_id`) REFERENCES `shipment_participant_map` (`map_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_2` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `r_participant_feedback_form_question_map` (`question_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_4` FOREIGN KEY (`participant_id`) REFERENCES `participant` (`participant_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_5` FOREIGN KEY (`participant_id`) REFERENCES `participant` (`participant_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Fix participant_testkit_map FK referencing renamed table r_testkitname_dts -> r_testkitnames
-- Constraint names may vary across instances, so drop dynamically
SET @fk_name = (SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_testkit_map' AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1);
SET @sql = IF(@fk_name IS NOT NULL, CONCAT('ALTER TABLE `participant_testkit_map` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_name = (SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_testkit_map' AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1);
SET @sql = IF(@fk_name IS NOT NULL, CONCAT('ALTER TABLE `participant_testkit_map` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_name = (SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_testkit_map' AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1);
SET @sql = IF(@fk_name IS NOT NULL, CONCAT('ALTER TABLE `participant_testkit_map` DROP FOREIGN KEY `', @fk_name, '`'), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Re-add FKs with correct references
ALTER TABLE `participant_testkit_map`
  ADD CONSTRAINT `participant_testkit_map_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participant` (`participant_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `participant_testkit_map`
  ADD CONSTRAINT `participant_testkit_map_ibfk_2` FOREIGN KEY (`testkit_id`) REFERENCES `r_testkitnames` (`TestKitName_ID`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `participant_testkit_map`
  ADD CONSTRAINT `participant_testkit_map_ibfk_3` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;


CREATE TABLE IF NOT EXISTS `r_participant_feedback_form_question_map` (
  `fqm_id` int NOT NULL AUTO_INCREMENT,
  `rpff_id` int NOT NULL,
  `shipment_id` int NOT NULL,
  `scheme_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `question_id` int NOT NULL,
  `is_response_mandatory` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  PRIMARY KEY (`fqm_id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `question_id` (`question_id`),
  KEY `scheme_type` (`scheme_type`),
  KEY `rpff_id` (`rpff_id`),
  CONSTRAINT `r_participant_feedback_form_question_map_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`),
  CONSTRAINT `r_participant_feedback_form_question_map_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `r_feedback_questions` (`question_id`),
  CONSTRAINT `r_participant_feedback_form_question_map_ibfk_3` FOREIGN KEY (`rpff_id`) REFERENCES `r_participant_feedback_form` (`rpff_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE `global_config` CHANGE `value` `value` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
UPDATE `global_config` SET `value` = null WHERE `value` = '' ;

-- Fix: Old "Other" reason for not testing stored as 0 (from 'other' string → INT conversion)
-- Update to 9999 sentinel value for proper form pre-selection
UPDATE `shipment_participant_map`
SET `vl_not_tested_reason` = 9999
WHERE `is_pt_test_not_performed` = 'yes'
  AND `vl_not_tested_reason` = 0;