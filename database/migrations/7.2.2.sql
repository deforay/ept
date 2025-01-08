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
UPDATE data_manager SET data_manager_type = 'ptcc' WHERE IFNULL(ptcc, 'no') = 'yes';
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

-- Thana 24-Oct-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('aggregate_insights_url', '');
INSERT INTO `system_config` (`config`, `value`, `display_name`) VALUES ('api_version', '2.0', 'API Version');
-- Thana 24-Oct-2024
CREATE TABLE IF NOT EXISTS `system_metadata` (
  `metadata_id` varchar(128) COLLATE utf8mb4_general_ci NOT NULL,
  `metadata_value` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`metadata_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_metadata` (`metadata_id`, `metadata_value`) VALUES ('instance-id', null);
-- Thana 11-Nov-2024
CREATE TABLE IF NOT EXISTS `track_api_requests` (
  `api_track_id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `requested_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `requested_on` datetime DEFAULT NULL,
  `number_of_records` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `request_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `test_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `api_url` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `api_params` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `request_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `response_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `data_format` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`api_track_id`),
  KEY `requested_on` (`requested_on`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Amit 21-Nov-2024
ALTER TABLE `r_possibleresult` CHANGE `display_context` `display_context` ENUM('participant','admin','all', 'none') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'all';
INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `sub_scheme`, `result_type`, `response`, `result_code`, `display_context`, `high_range`, `threshold_range`, `low_range`, `sort_order`) VALUES (NULL, 'dts', 'DTS_FINAL', NULL, NULL, 'NONREACTIVE', 'NR', 'all', NULL, NULL, NULL, NULL);

-- Thana 19-Dec-2024
INSERT INTO `global_config` (`name`, `value`) VALUES ('instance', null);

-- sakthi 24-Dec-2024


-- Sakti 27-Dec-2024
CREATE TABLE IF NOT EXISTS participant_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    attached_file VARCHAR(255) NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL
);

-- Sakti 30-Dec-2024
ALTER TABLE `temp_mail` ADD `attachment` VARCHAR(255) NULL AFTER `from_full_name`;

-- Thana 30-Dec-2024
CREATE TABLE IF NOT EXISTS `generic_recommended_test_types` (
  `scheme_id` varchar(256) COLLATE utf8mb4_general_ci NOT NULL,
  `testkit` varchar(256) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

RENAME TABLE `r_testkitname_dts` TO `r_testkitnames`;

-- Amit 31-Dec-2024
UPDATE response_result_tb
SET probe_d = NULL WHERE probe_d = '';
UPDATE response_result_tb
SET probe_c = NULL WHERE probe_c = '';
UPDATE response_result_tb
SET probe_e = NULL WHERE probe_e = '';
UPDATE response_result_tb
SET probe_b = NULL WHERE probe_b = '';
UPDATE response_result_tb
SET spc_xpert = NULL WHERE spc_xpert = '';
UPDATE response_result_tb
SET spc_xpert_ultra = NULL WHERE spc_xpert_ultra = '';
UPDATE response_result_tb
SET probe_a = NULL WHERE probe_a = '';
UPDATE response_result_tb
SET is1081_is6110 = NULL WHERE is1081_is6110 = '';
UPDATE response_result_tb
SET rpo_b1 = NULL WHERE rpo_b1 = '';
UPDATE response_result_tb
SET rpo_b2 = NULL WHERE rpo_b2 = '';
UPDATE response_result_tb
SET rpo_b3 = NULL WHERE rpo_b3 = '';
UPDATE response_result_tb
SET rpo_b4 = NULL WHERE rpo_b4 = '';

ALTER TABLE response_result_tb
MODIFY COLUMN probe_d DECIMAL(10,4),
MODIFY COLUMN probe_c DECIMAL(10,4),
MODIFY COLUMN probe_e DECIMAL(10,4),
MODIFY COLUMN probe_b DECIMAL(10,4),
MODIFY COLUMN spc_xpert DECIMAL(10,4),
MODIFY COLUMN spc_xpert_ultra DECIMAL(10,4),
MODIFY COLUMN probe_a DECIMAL(10,4),
MODIFY COLUMN is1081_is6110 DECIMAL(10,4),
MODIFY COLUMN rpo_b1 DECIMAL(10,4),
MODIFY COLUMN rpo_b2 DECIMAL(10,4),
MODIFY COLUMN rpo_b3 DECIMAL(10,4),
MODIFY COLUMN rpo_b4 DECIMAL(10,4);

-- Thana 02-Jan-2025
ALTER TABLE `reference_result_tb` ADD `mtb_detected_ultra` VARCHAR(256) NULL DEFAULT NULL AFTER `mtb_detected`;
ALTER TABLE `reference_result_tb` ADD `rif_resistance_ultra` VARCHAR(256) NULL DEFAULT NULL AFTER `rif_resistance`;

-- Thana 03-Jan-2024
CREATE TABLE `scheme_testkit_map` (
  `scheme_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `testkit_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `testkit_1` int NOT NULL DEFAULT '0',
  `testkit_2` int NOT NULL DEFAULT '0',
  `testkit_3` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Thana 03-Jan-2024
ALTER TABLE `r_possibleresult` ADD `sd_scaling_factor` VARCHAR(256) NULL DEFAULT NULL AFTER `low_range`, ADD `uncertainy_scaling_factor` VARCHAR(256) NULL DEFAULT NULL AFTER `sd_scaling_factor`, ADD `uncertainy_threshold` VARCHAR(256) NULL DEFAULT NULL AFTER `uncertainy_scaling_factor`;

-- Amit 07-Jan-2025
INSERT INTO `scheme_testkit_map`(`scheme_type`, `testkit_id`, `testkit_1`, `testkit_2`, `testkit_3`)
    SELECT r_testkitnames.scheme_type, r_testkitnames.TestKitName_ID, r_testkitnames.testkit_1, r_testkitnames.testkit_2, r_testkitnames.testkit_3 FROM r_testkitnames;

ALTER TABLE `r_testkitnames`
  DROP `scheme_type`,
  DROP `testkit_1`,
  DROP `testkit_2`,
  DROP `testkit_3`;

-- Thana 07-Jan-2025
CREATE TABLE `reference_generic_test_calculations` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `no_of_responses` int DEFAULT NULL,
  `q1` double(20,10) DEFAULT NULL,
  `q3` double(20,10) DEFAULT NULL,
  `iqr` double(20,10) DEFAULT NULL,
  `quartile_low` double(20,10) DEFAULT NULL,
  `quartile_high` double(20,10) DEFAULT NULL,
  `mean` double(20,10) DEFAULT NULL,
  `median` double(20,10) DEFAULT NULL,
  `sd` double(20,10) DEFAULT NULL,
  `standard_uncertainty` double(20,10) DEFAULT NULL,
  `is_uncertainty_acceptable` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cv` double(20,10) DEFAULT NULL,
  `low_limit` double(20,10) DEFAULT NULL,
  `high_limit` double(20,10) DEFAULT NULL,
  `calculated_on` datetime DEFAULT NULL,
  `manual_mean` double(20,10) DEFAULT NULL,
  `manual_median` double(20,10) DEFAULT NULL,
  `manual_sd` double(20,10) DEFAULT NULL,
  `manual_standard_uncertainty` double(20,10) DEFAULT NULL,
  `manual_is_uncertainty_acceptable` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `manual_cv` double(20,10) DEFAULT NULL,
  `manual_q1` double(20,10) DEFAULT NULL,
  `manual_q3` double(20,10) DEFAULT NULL,
  `manual_iqr` double(20,10) DEFAULT NULL,
  `manual_quartile_low` double(20,10) DEFAULT NULL,
  `manual_quartile_high` double(20,10) DEFAULT NULL,
  `manual_low_limit` double(20,10) DEFAULT NULL,
  `manual_high_limit` double(20,10) DEFAULT NULL,
  `z_score` double(20,10) NOT NULL,
  `is_result_invalid` varchar(256) COLLATE utf8mb4_general_ci NOT NULL,
  `error_code` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `comment` varchar(256) COLLATE utf8mb4_general_ci NOT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `use_range` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'calculated',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Thana 08-Jan-2025
ALTER TABLE `response_result_generic_test` ADD `z_score` DOUBLE(20,10) NULL DEFAULT NULL AFTER `additional_detail`, ADD `is_result_invalid` VARCHAR(256) NULL DEFAULT NULL AFTER `z_score`, ADD `error_code` VARCHAR(256) NULL DEFAULT NULL AFTER `is_result_invalid`;
