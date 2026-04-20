-- Migration for version 7.3.6
-- Amit Dugar - Mar 2026
UPDATE `system_config` SET `value` = '7.3.6' WHERE `config` = 'app_version';

-- Thana -30-Mar-2026
ALTER TABLE `reference_result_dts` ADD `is_sample_diluted` VARCHAR(50) NULL DEFAULT NULL AFTER `mandatory`;


-- Jeyabanu 31-Mar-2026
ALTER TABLE `reference_dts_eia` ADD `test_date` DATE NULL DEFAULT NULL AFTER `eia`;
ALTER TABLE `reference_dts_wb` ADD `test_date` DATE NULL DEFAULT NULL AFTER `wb`;
ALTER TABLE `reference_dts_rapid_hiv` ADD `test_date` DATE NULL DEFAULT NULL AFTER `testkit`;
ALTER TABLE `reference_dts_geenius` ADD `test_date` DATE NULL DEFAULT NULL AFTER `sample_id`;

-- Thana 09-Apr-2026
ALTER TABLE `system_admin` ADD `language` VARCHAR(256) NULL DEFAULT 'en_US' AFTER `scheme`;

-- Thana 10-Apr-2026
ALTER TABLE `scheme_testkit_map` ADD `shipment_id` INT NULL DEFAULT NULL AFTER `testkit_3`;

-- Amit 20-Apr-2026
-- Fix bad FK on participant_feedback_answer.question_id in instances where it still
-- references r_participant_feedback_form(question_id) (non-unique column; causes
-- dump import error 6125). The 7.3.5 migration attempted to replace this FK but
-- failed silently on some instances (e.g. Malawi) because it never dropped the
-- old FK first. Correct parent is r_feedback_questions(question_id).
-- Idempotent: no-op on instances where the FK is already correct or absent.

SET @fk_name = (
  SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'participant_feedback_answer'
    AND COLUMN_NAME = 'question_id'
    AND REFERENCED_TABLE_NAME = 'r_participant_feedback_form'
  LIMIT 1);
SET @sql = IF(@fk_name IS NOT NULL,
  CONCAT('ALTER TABLE `participant_feedback_answer` DROP FOREIGN KEY `', @fk_name, '`'),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ok_fk = (
  SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'participant_feedback_answer'
    AND COLUMN_NAME = 'question_id'
    AND REFERENCED_TABLE_NAME = 'r_feedback_questions'
  LIMIT 1);
SET @sql = IF(@ok_fk IS NULL,
  'ALTER TABLE `participant_feedback_answer` ADD CONSTRAINT `participant_feedback_answer_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `r_feedback_questions` (`question_id`) ON DELETE RESTRICT ON UPDATE RESTRICT',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
