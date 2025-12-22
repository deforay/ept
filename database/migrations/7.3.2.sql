-- Migration for version 7.3.2

UPDATE `system_config` SET `value` = '7.3.2' WHERE `config` = 'app_version';


-- Amit 19-Dec-2025
ALTER TABLE `response_result_tb`
  ADD INDEX `idx_response_result_tb_map_sample` (`shipment_map_id`, `sample_id`),
  ADD INDEX `idx_response_result_tb_sample_assay` (`sample_id`, `assay_id`),
  ADD INDEX `idx_response_result_tb_assay` (`assay_id`),
  ADD INDEX `idx_response_result_tb_rif` (`rif_resistance`);

ALTER TABLE `reference_result_tb`
  ADD INDEX `idx_reference_result_tb_ship_sample` (`shipment_id`, `sample_id`);

ALTER TABLE `shipment_participant_map`
  ADD INDEX `idx_spm_ship_resp_excl_map` (`shipment_id`, `response_status`, `is_excluded`, `map_id`);


UPDATE reference_result_tb
SET probe_d = NULL WHERE probe_d = '';
UPDATE reference_result_tb
SET probe_c = NULL WHERE probe_c = '';
UPDATE reference_result_tb
SET probe_e = NULL WHERE probe_e = '';
UPDATE reference_result_tb
SET probe_b = NULL WHERE probe_b = '';
UPDATE reference_result_tb
SET spc_xpert = NULL WHERE spc_xpert = '';
UPDATE reference_result_tb
SET spc_xpert_ultra = NULL WHERE spc_xpert_ultra = '';
UPDATE reference_result_tb
SET probe_a = NULL WHERE probe_a = '';
UPDATE reference_result_tb
SET is1081_is6110 = NULL WHERE is1081_is6110 = '';
UPDATE reference_result_tb
SET rpo_b1 = NULL WHERE rpo_b1 = '';
UPDATE reference_result_tb
SET rpo_b2 = NULL WHERE rpo_b2 = '';
UPDATE reference_result_tb
SET rpo_b3 = NULL WHERE rpo_b3 = '';
UPDATE reference_result_tb
SET rpo_b4 = NULL WHERE rpo_b4 = '';

ALTER TABLE reference_result_tb
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

ALTER TABLE `reference_result_tb` ADD `mtbrif_probe_a_mean_stability_ct` DECIMAL(10,4) NULL DEFAULT NULL AFTER `probe_a`;
ALTER TABLE `reference_result_tb` ADD `mtbultra_lowest_rpo_b_probe_mean_stability_ct` DECIMAL(10,4) NULL DEFAULT NULL AFTER `mtbrif_probe_a_mean_stability_ct`;
-- Thana 22-Dec-2025
ALTER TABLE `r_participant_feedback_form_files_map` ADD `rpff_id` INT NULL DEFAULT NULL AFTER `rpf_id`;
