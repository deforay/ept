-- Migration for version 7.7.1
-- Amit Dugar - Nov 2026
UPDATE `system_config` SET `value` = '7.7.1' WHERE `config` = 'app_version';

-- Release marker only. No schema changes in this version.
--
-- 7.7.1 collects the documentation and help rework and the fixes that came
-- with it: in-app help for every admin page in English and French, a notice
-- when a page has no help topic, the French documentation site, the optional
-- approved-only test kits setting (participant_testkits_approved_only in
-- global_config, created on first save), own-profile saves limited to the
-- signed-in admin or data manager, and a set of admin page fixes.
--
-- migrate.php re-applies the current version on every run. The single UPDATE
-- above is idempotent, so this file is safe to re-run.
