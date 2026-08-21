-- Migration for version 7.6.20
-- Amit Dugar - Aug 2026
UPDATE `system_config` SET `value` = '7.6.20' WHERE `config` = 'app_version';

-- Reports print a "Date of Approval", but nothing ever recorded one. The layouts read
-- queue_report_generation.date_finalised, which is stamped NOW() in the same statement as
-- requested_on the moment an admin clicks Generate Reports -- so it is the report-generation
-- timestamp, re-stamped on every regeneration, not a date anyone approved anything on.
--
-- Zimbabwe now approves results at a physical meeting that can sit well after the reports
-- were generated, so the approval date has to be theirs to enter. These two columns hold it:
-- captured at finalize, left to the admin to fill in, and defaulted to the finalizing user's
-- name and the current time when they skip it (which reproduces today's behaviour).
--
-- results_approved_by is the approver's name as typed, not a user id. The person who chairs
-- the approval meeting is not necessarily the person who finalizes the shipment in ePT, and
-- the report signature block prints a name, so a free-text name is what is actually needed.
--
-- NULL on every existing row, so reports fall back to date_finalised exactly as before until
-- a shipment is finalized again.
--
-- Idempotent: migrate.php's ADD COLUMN handler no-ops when the column already exists.
ALTER TABLE `shipment`
  ADD COLUMN `results_approved_on` DATETIME NULL DEFAULT NULL AFTER `finalized_at`,
  ADD COLUMN `results_approved_by` VARCHAR(256) NULL DEFAULT NULL AFTER `results_approved_on`;
