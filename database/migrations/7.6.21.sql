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
