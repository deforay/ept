-- Migration for version 7.3.2

UPDATE `system_config` SET `value` = '7.3.2' WHERE `config` = 'app_version';

ALTER TABLE `response_result_tb`
  ADD INDEX `idx_response_result_tb_map_sample` (`shipment_map_id`, `sample_id`),
  ADD INDEX `idx_response_result_tb_sample_assay` (`sample_id`, `assay_id`),
  ADD INDEX `idx_response_result_tb_assay` (`assay_id`),
  ADD INDEX `idx_response_result_tb_rif` (`rif_resistance`);

ALTER TABLE `reference_result_tb`
  ADD INDEX `idx_reference_result_tb_ship_sample` (`shipment_id`, `sample_id`);

ALTER TABLE `shipment_participant_map`
  ADD INDEX `idx_spm_ship_resp_excl_map` (`shipment_id`, `response_status`, `is_excluded`, `map_id`);
