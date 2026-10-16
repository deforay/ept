-- Migration for version 7.6.30
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.30' WHERE `config` = 'app_version';

-- A data manager with no view-only setting is treated as "no" everywhere except the
-- three response-page checks in Shipments, which test for 'no' and hide actions for
-- NULL. Store the missing values as 'no', and make 'no' the default so rows inserted
-- without the field do not go back to NULL.
--
-- Idempotent: the UPDATEs match nothing on a second run, and MODIFY sets the same
-- definition again. MODIFY rather than ALTER COLUMN ... SET DEFAULT, because the
-- migration parser cuts that statement short after SET.
UPDATE `data_manager` SET `view_only_access` = 'no'
  WHERE `view_only_access` IS NULL OR TRIM(`view_only_access`) = '';

-- Older versions of the PTCC bulk import stored column I as typed, so "Yes", "No", "Oui" or
-- "Non" can be stored. The readers compare in PHP, which is case-sensitive, so those
-- values count as neither yes nor no. The IN lists are the yes and no words of every
-- installed language. The column collation ignores case and accents.
UPDATE `data_manager` SET `view_only_access` = 'yes'
  WHERE TRIM(`view_only_access`) IN ('yes', 'oui', 'có') AND BINARY `view_only_access` <> 'yes';

UPDATE `data_manager` SET `view_only_access` = 'no'
  WHERE TRIM(`view_only_access`) IN ('no', 'non', 'không') AND BINARY `view_only_access` <> 'no';

ALTER TABLE `data_manager` MODIFY COLUMN `view_only_access` VARCHAR(255) NULL DEFAULT 'no';
