-- Migration for version 7.4.5
-- Amit Dugar - May 2026
-- Per-install cutoff timezone for shipment late-checks. Empty/missing value
-- means "use PHP default timezone" (application.ini `timezone`), so existing
-- single-country installs are unaffected. International programs running
-- Anywhere-on-Earth deadlines should set this to 'Etc/GMT+12' (= UTC-12).
-- Consumed by Pt_Commons_DateUtility::shipmentCutoff().

UPDATE `system_config` SET `value` = '7.4.5' WHERE `config` = 'app_version';

INSERT INTO `global_config` (`name`, `value`) VALUES ('cutoff_timezone', '')
ON DUPLICATE KEY UPDATE `value` = `value`;
