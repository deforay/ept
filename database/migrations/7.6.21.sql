-- Migration for version 7.6.21
-- Amit Dugar - Aug 2026
UPDATE `system_config` SET `value` = '7.6.21' WHERE `config` = 'app_version';

-- The name beside "Approved by" on the Zimbabwe reports was the literal string
-- "Lucia Sisya", written into four report layouts. 7.6.20 let the coordinator record an
-- approver per shipment, but the fallback for shipments finalized before that was still
-- the hardcoded name -- and a person's name does not belong in source, least of all in a
-- codebase every country runs its own copy of.
--
-- This adds it as a report setting, editable on /admin/report-config alongside the report
-- header and layout. It serves as the default offered in the finalize form's Approved By
-- field, and as the fallback the layouts print for shipments that predate the per-shipment
-- record.
--
-- Seeded empty, because the name is per-instance. Zimbabwe is then seeded with the name
-- its reports have carried all along, so nothing changes there until someone edits it. The
-- subquery is wrapped in a derived table because MySQL will not read the target table of
-- an UPDATE directly.
--
-- Idempotent: INSERT IGNORE no-ops on the primary key, and the UPDATE only fills a value
-- still left empty, so an edited name survives a replay.
INSERT IGNORE INTO `global_config` (`name`, `value`, `context`)
VALUES ('report-approver-name', '', 'report');

UPDATE `global_config`
   SET `value` = 'Lucia Sisya'
 WHERE `name` = 'report-approver-name'
   AND `context` = 'report'
   AND IFNULL(`value`, '') = ''
   AND (SELECT `instance` FROM (SELECT `value` AS `instance` FROM `global_config` WHERE `name` = 'instance' LIMIT 1) AS `i`) = 'zimbabwe';

-- Late-submission capture (VL to begin with). A PT survey closes at an exact moment
-- (shipment.response_deadline, a DATETIME). A participant can open the response form and
-- click Submit before that moment, then finish the two-step confirmation after it. We now
-- record when the participant first saved the response (started_at, stamped on the
-- pre-confirm Submit and never overwritten). When the start was on/before the deadline but
-- the response lands after it, the response is still saved in full but held as
-- response_status = 'late_submitted' with late_submit_status = 1, until an admin approves
-- it -- approval flips response_status back to 'responded' while keeping late_submit_status
-- = 1 as history.
--
-- migrate.php makes ADD COLUMN idempotent (column_exists), so replaying this is safe.
ALTER TABLE `shipment_participant_map`
  ADD COLUMN `started_at` DATETIME NULL DEFAULT NULL AFTER `created_on_user`;
ALTER TABLE `shipment_participant_map`
  ADD COLUMN `late_submit_status` TINYINT(1) NOT NULL DEFAULT 0 AFTER `started_at`;

-- Grace window (minutes) after a shipment's deadline during which a participant who opened
-- the response form on/before the deadline may still submit. The submission is saved and
-- held as response_status = 'late_submitted' for admin review. 0 disables the grace (the
-- shipment's own response switch then governs entirely). Seeded at 1440 (24h); editable on
-- /admin/global-config. INSERT IGNORE so a replay keeps an edited value.
INSERT IGNORE INTO `global_config` (`name`, `value`) VALUES ('late_submission_grace_minutes', '1440');
