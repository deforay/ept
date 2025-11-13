-- Migration for version 7.3.0

-- Amit 23-Sep-2025
UPDATE `system_config` SET `value` = '7.3.0' WHERE `system_config`.`config` = 'app_version';

-- Thana 11-Nov-2025
ALTER TABLE `shipment_participant_map`
  DROP `summary_report_downloaded_on`,
  DROP `individual_report_downloaded_on`;
ALTER TABLE `shipment_participant_map` ADD `report_download_metadata` JSON NULL DEFAULT NULL AFTER `response_status`;

-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --
-- END OF VERSION 7.3.0 --

