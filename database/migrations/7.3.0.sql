-- Migration for version 7.3.0

-- Amit 23-Sep-2025
UPDATE `system_config` SET `value` = '7.3.0' WHERE `system_config`.`config` = 'app_version';

-- Thana 11-Nov-2025
ALTER TABLE `shipment_participant_map`
  DROP `summary_report_downloaded_on`,
  DROP `individual_report_downloaded_on`;
ALTER TABLE `shipment_participant_map` ADD `report_download_metadata` JSON NULL DEFAULT NULL AFTER `response_status`;
-- Thana 13-Nov-2025

CREATE TABLE IF NOT EXISTS `r_participant_feedback_form` (
  `rpff_id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `scheme_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `form_content` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`rpff_id`),
  KEY `shipment_id` (`shipment_id`),
  CONSTRAINT `r_participant_feedback_form_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Lookup table holding the actual feedback questions. The tables below reference it
-- (r_participant_feedback_form_question_map_ibfk_2), so it must exist first. Historically
-- this table came from the init.sql baseline and was never created by a migration, so
-- instances not seeded from such an init.sql lacked it -- breaking this FK and every later
-- ALTER against it. Create it here (IF NOT EXISTS -> no-op where it already exists);
-- mirrors init.sql with PRIMARY KEY + AUTO_INCREMENT inlined.
CREATE TABLE IF NOT EXISTS `r_feedback_questions` (
  `question_id` int NOT NULL AUTO_INCREMENT,
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `question_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `question_type` enum('text','datetime','dropdown','numeric') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `question_show_to` varchar(256) DEFAULT NULL,
  `question_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `response_attributes` json DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `modified_by` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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

CREATE TABLE IF NOT EXISTS `r_participant_feedback_form_files_map` (
  `rpf_id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `scheme_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `feedback_file` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  PRIMARY KEY (`rpf_id`),
  KEY `shipment_id` (`shipment_id`),
  CONSTRAINT `r_participant_feedback_form_files_map_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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


-- END OF VERSION 7.3.0 --

