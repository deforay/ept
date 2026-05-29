-- Migration for version 7.4.8
-- Amit Dugar - May 2026
-- Make the shipment response deadline a precise moment in time and give it a
-- clearer name. `lastdate_response` (DATE) becomes `response_deadline` (DATETIME)
-- so an admin can set an exact close time (e.g. 2026-05-29 23:00:00). The
-- wall-clock value is read in the program cutoff timezone
-- (global_config.cutoff_timezone); the auto-close cron flips response_switch off
-- once it passes. Existing date-only rows are migrated to 23:59:59 so the previous
-- "whole due-date day is open" behaviour is preserved exactly.

UPDATE `system_config` SET `value` = '7.4.8' WHERE `config` = 'app_version';

ALTER TABLE `shipment`
    CHANGE COLUMN `lastdate_response` `response_deadline` DATETIME DEFAULT NULL;

UPDATE `shipment`
    SET `response_deadline` = TIMESTAMP(DATE(`response_deadline`), '23:59:59')
    WHERE `response_deadline` IS NOT NULL;
