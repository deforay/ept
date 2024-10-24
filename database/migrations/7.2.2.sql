-- Thana 16-May-2024
ALTER TABLE `r_possibleresult` ADD `sub_scheme` VARCHAR(256) NULL DEFAULT NULL AFTER `scheme_sub_group`;
-- Thana 17-May-2024
ALTER TABLE `reference_result_generic_test` CHANGE `sample_score` `sample_score` DECIMAL NOT NULL DEFAULT '1';
-- UPDATE reference_result_generic_test AS rrg SET reference_result = (SELECT result_code FROM r_possibleresult AS rp WHERE rp.id=rrg.reference_result);
-- UPDATE response_result_generic_test AS rrg SET result = (SELECT result_code FROM r_possibleresult AS rp WHERE rp.id=rrg.result), reported_result = (SELECT result_code FROM r_possibleresult AS rp WHERE rp.id=rrg.reported_result);
-- Thana 23-May-2024
UPDATE data_manager SET ptcc = 'no' WHERE ptcc like '' OR ptcc like null;
ALTER TABLE `data_manager` CHANGE `ptcc` `ptcc` ENUM('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'no';
-- Thana 27-May-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('evaluate_before_generating_reports', 'yes');
-- Thana 05-Jun-2024
ALTER TABLE `data_manager` ADD `last_date_for_email_reset` DATE NULL DEFAULT NULL AFTER `new_email`;

-- Amit 11-Jun-2024
UPDATE data_manager set ptcc = 'yes' where dm_id in (select ptcc_countries_map.ptcc_id from ptcc_countries_map);

-- June 21-Jun-2024
CREATE TABLE IF NOT EXISTS `email_participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject` varchar(256) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci,
  `receivers` text COLLATE utf8mb4_general_ci,
  `shipment_code` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_initiated` datetime DEFAULT CURRENT_TIMESTAMP,
  `initiated_by` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Thana 12-Jul-2024
ALTER TABLE `dts_shipment_corrective_action_map`
ADD `action_taken` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `corrective_action_id`,
ADD `action_date` DATE NULL DEFAULT NULL AFTER `action_taken`;

-- Thana 16-Jul-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('enable_capa', 'no');

-- Thana 26-Jul-2024
ALTER TABLE `shipment` ADD `allow_editing_response` ENUM('yes','no') NOT NULL DEFAULT 'yes' AFTER `response_switch`;

-- Thana 05-Aug-2024
-- ALTER TABLE `home_sections` ADD `type` VARCHAR(25) NULL DEFAULT NULL AFTER `section`;

-- Thana 09-Aug-2024
-- ALTER TABLE `home_sections` DROP `type`;
CREATE TABLE IF NOT EXISTS `custom_page_content` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(256) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci,
  `modified_by` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `modified_date_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Thana 12-Aug-2024
INSERT INTO `report_config` (`name`, `value`) VALUES ('template-top-margin', '55');
-- Thana 14-Aug-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('direct_participant_login', 'no');

-- Thana 16-Aug-2024
ALTER TABLE `data_manager` ADD `data_manager_type` VARCHAR(50) NOT NULL DEFAULT 'manager' AFTER `institute`;
-- Amit 16-Aug-2024
UPDATE data_manager SET data_manager_type = 'ptcc' WHERE IFNULL(ptcc, 'no') like 'yes';
-- Thana 19-Aug-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('participant_login_prefix', 'PTID');
ALTER TABLE `participant` ADD `ulid` TEXT NULL DEFAULT NULL AFTER `participant_id`;
ALTER TABLE `data_manager` ADD `participant_ulid` TEXT NULL DEFAULT NULL AFTER `dm_id`;


-- Amit 02-Sep-2024
ALTER TABLE `enrollments` DROP `enrollment_ended_on`;
ALTER TABLE `enrollments` CHANGE `enrolled_on` `enrolled_on` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `enrollments` ADD `enrollment_id` VARCHAR(64) NOT NULL FIRST;
ALTER TABLE `enrollments` ADD `list_name` VARCHAR(128) NOT NULL DEFAULT 'default' AFTER `enrollment_id`;
ALTER TABLE `enrollments` CHANGE `scheme_id` `scheme_id` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE `enrollments` DROP PRIMARY KEY;
ALTER TABLE `enrollments` ADD PRIMARY KEY(`list_name`, `participant_id`);

-- Amit 03-Sep-2024
INSERT INTO enrollments (enrollment_id, list_name, participant_id, enrolled_on, status)
    SELECT eln_unique_id AS enrollment_id,
          eln_name AS list_name,
          participant_id,
          added_on AS enrolled_on,
          'enrolled' AS status
    FROM enrollment_lists_names;

DROP TABLE enrollment_lists_names;

ALTER TABLE `temp_mail` ADD `queued_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `from_full_name`;

-- Thana 04-Sep-2024
UPDATE shipment_participant_map
SET is_excluded = NULL
WHERE is_excluded like '';

ALTER TABLE `shipment_participant_map`
CHANGE `is_excluded` `is_excluded` ENUM('yes', 'no')
CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
NULL DEFAULT NULL;

-- Thana 09-Sep-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('participant_login_password_length', '8');
INSERT INTO `global_config` (`name`, `value`) VALUES ('enable_login_attempt_ban', 'no');
ALTER TABLE `data_manager` ADD `login_ban` VARCHAR(50) NOT NULL DEFAULT 'no' AFTER `last_login`;
-- Thana 12-Sep-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('temporary_login_ban_time', '30:00');

-- Thana 13-Sep-2024
ALTER TABLE `system_admin` CHANGE `password` `password` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `data_manager` CHANGE `password` `password` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
INSERT INTO `global_config`
(`name`, `value`)
VALUES
('max_attempts_for_temp_ban', '3'),
('max_attempts_for_perm_ban', '5');

-- Thana 23-Sep-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('participants_can_edit_name', 'yes');

-- Thana 24-Sep-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('feed_back_option', 'no');
ALTER TABLE `participant_feedback_answer` CHANGE `answer` `answer` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

-- Thana 01-Oct-2024
ALTER TABLE `data_manager`
  DROP `push_status`,
  DROP `marked_push_notify`,
  DROP `push_notify_token`;

-- Thana 03-Oct-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('footer_text', '');

-- Thana 18-Oct-2024
ALTER TABLE `shipment` ADD `pt_co_ordinator_email` VARCHAR(256) NULL DEFAULT NULL AFTER `pt_co_ordinator_name`, ADD `pt_co_ordinator_phone` VARCHAR(256) NULL DEFAULT NULL AFTER `pt_co_ordinator_email`;
