-- Thana 16-May-2024
ALTER TABLE `r_possibleresult` ADD `sub_scheme` VARCHAR(256) NULL DEFAULT NULL AFTER `scheme_sub_group`;
-- Thana 17-May-2024
ALTER TABLE `reference_result_generic_test` CHANGE `sample_score` `sample_score` DECIMAL NOT NULL DEFAULT '1';
UPDATE reference_result_generic_test AS rrg SET reference_result = (SELECT result_code FROM r_possibleresult AS rp WHERE rp.id=rrg.reference_result);
UPDATE response_result_generic_test AS rrg SET result = (SELECT result_code FROM r_possibleresult AS rp WHERE rp.id=rrg.result), reported_result = (SELECT result_code FROM r_possibleresult AS rp WHERE rp.id=rrg.reported_result);
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

-- June 12-Jul-2024
ALTER TABLE `dts_shipment_corrective_action_map` 
ADD `action_token` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `corrective_action_id`, 
ADD `action_date` DATE CHARACTER SET utf8mb4 NULL DEFAULT NULL AFTER `action_token`;
-- June 15-Jul-2024
ALTER TABLE `dts_shipment_corrective_action_map` CHANGE `action_token` `action_taken` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;