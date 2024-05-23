-- Thana 16-May-2024
ALTER TABLE `r_possibleresult` ADD `sub_scheme` VARCHAR(256) NULL DEFAULT NULL AFTER `scheme_sub_group`;
-- Thana 17-May-2024
ALTER TABLE `reference_result_generic_test` CHANGE `sample_score` `sample_score` DECIMAL NOT NULL DEFAULT '1';
UPDATE reference_result_generic_test AS rrg SET reference_result = (SELECT result_code FROM r_possibleresult AS rp WHERE rp.id=rrg.reference_result);
UPDATE response_result_generic_test AS rrg SET result = (SELECT result_code FROM r_possibleresult AS rp WHERE rp.id=rrg.result), reported_result = (SELECT result_code FROM r_possibleresult AS rp WHERE rp.id=rrg.reported_result);
-- Thana 23-May-2024
UPDATE data_manager SET ptcc = 'no' WHERE ptcc like '' OR ptcc like null;
ALTER TABLE `data_manager` CHANGE `ptcc` `ptcc` ENUM('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'no';
