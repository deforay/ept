-- Migration for version 7.7.0
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.7.0' WHERE `config` = 'app_version';

-- Release marker only. No schema changes in this version.
--
-- 7.7.0 marks the admin rework that 7.6.25 to 7.6.37 delivered:
-- per-scheme enrollment lists with clone, archive, merge and unique names;
-- bulk imports with a review step; the rebuilt Scheme Enrollments, Map
-- Participants, Data Managers and PTCC pages; uniform config pages; and the
-- migrate.php fix that makes DROP PRIMARY KEY, INDEX and FOREIGN KEY run.
--
-- migrate.php re-applies the current version on every run. The single UPDATE
-- above is idempotent, so this file is safe to re-run.
