-- Migration for version 7.6.14
-- Amit Dugar - Jul 2026
UPDATE `system_config` SET `value` = '7.6.14' WHERE `config` = 'app_version';

-- Merge report_config into global_config.
--
-- The two tables were structurally identical (name/value, PRIMARY KEY (name)) and
-- differed only in which admin page edited them, so they cost two tables and two
-- accessors for one job. `context` records the owning page instead: 'global' for
-- /admin/global-config, 'report' for /admin/report-config. Names never collided
-- across the two tables (report_config used hyphens, global_config underscores),
-- so PRIMARY KEY (name) still holds and a lookup by name alone stays unambiguous —
-- context is a filter for the admin pages, not part of the identity.
--
-- migrate.php makes each step idempotent: ADD COLUMN via column_exists, the
-- INSERT..SELECT backfill and DROP TABLE via benign-1146 on replay once
-- report_config is gone, and the seed INSERT via benign-1062.
ALTER TABLE `global_config` ADD COLUMN `context` VARCHAR(50) NOT NULL DEFAULT 'global' AFTER `value`;

INSERT INTO `global_config` (`name`, `value`, `context`) SELECT `name`, `value`, 'report' FROM `report_config`;

-- generate_reports_for_excluded governs report generation, so it moves to the
-- report-config page along with the rest. Keeps its underscore name: renaming it
-- would buy nothing and would risk dropping the value on any instance that has
-- already switched it on.
UPDATE `global_config` SET `context` = 'report' WHERE `name` = 'generate_reports_for_excluded';

-- Seed it for instances that never toggled it. The UPDATE above preserves an
-- existing value; this only lands when the row is absent. Default OFF.
-- report-config saves via UPDATE ... WHERE name=, so the row must pre-exist.
INSERT INTO `global_config` (`name`, `value`, `context`) VALUES ('generate_reports_for_excluded', 'no', 'report');

DROP TABLE `report_config`;
