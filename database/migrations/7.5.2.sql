-- Migration for version 7.5.2
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.5.2' WHERE `config` = 'app_version';

-- Track whether a scheme's test is reported qualitatively (Positive/Negative/etc.) or
-- quantitatively (a numeric measurement). Custom tests already capture this per result row
-- in user_test_config as `testType` ('qualitative' | 'quantitative'); this scheme-level
-- column lets every scheme (built-in and user-configured) declare its reporting format in
-- one place. VARCHAR(20) (not ENUM) to stay flexible and consistent with the other varchar
-- flags on this table (e.g. is_user_configured). NULL = not yet classified.
--
-- Idempotent: migrate.php has a dedicated `ALTER TABLE ... ADD [COLUMN]` handler
-- (add_column_if_missing) that skips the statement when the column already exists, so this
-- is safe to re-run.
ALTER TABLE `scheme_list`
  ADD COLUMN `test_format` VARCHAR(20) DEFAULT NULL AFTER `is_user_configured`;

-- Backfill test_format for existing schemes. Every UPDATE is guarded by
-- `test_format IS NULL` so the backfill runs exactly once: migrate.php re-applies the
-- current version on every run, and an unguarded UPDATE would clobber any value an admin
-- later sets by hand. The guards also enforce precedence -- the more specific rules run
-- first and claim the still-NULL rows, the catch-all default runs last.

-- 1. User-configured schemes already declare their format inside user_test_config.testType.
--    Copy that across, but only when it's a recognised value.
UPDATE `scheme_list`
   SET `test_format` = JSON_UNQUOTE(JSON_EXTRACT(`user_test_config`, '$.testType'))
 WHERE `test_format` IS NULL
   AND `is_user_configured` = 'yes'
   AND `user_test_config` IS NOT NULL
   AND JSON_VALID(`user_test_config`)
   AND JSON_UNQUOTE(JSON_EXTRACT(`user_test_config`, '$.testType')) IN ('qualitative', 'quantitative');

-- 2. Viral Load is reported as a numeric measurement -> quantitative.
UPDATE `scheme_list`
   SET `test_format` = 'quantitative'
 WHERE `test_format` IS NULL
   AND `scheme_id` = 'vl';

-- 3. Everything still unclassified defaults to qualitative.
UPDATE `scheme_list`
   SET `test_format` = 'qualitative'
 WHERE `test_format` IS NULL;

-- Per-sample "Lab Comment" dropdown for DTS — required by the Vietnam algorithm.
-- Stored as a stable code string (e.g. 'sent_for_confirmation', 'retest_after_14_days')
-- translated at render time. NULL = not selected. Other variants can populate this
-- column with their own codes later.
ALTER TABLE `response_result_dts`
  ADD COLUMN `lab_comment` VARCHAR(50) DEFAULT NULL AFTER `reported_result`;
