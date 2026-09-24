-- Migration for version 7.6.22
-- Amit Dugar - Sep 2026
UPDATE `system_config` SET `value` = '7.6.22' WHERE `config` = 'app_version';

-- DTS "updated-3-tests" accepted Test 1 and Test 2 reactive as a complete Positive and
-- never looked at Test 3, so a participant who skipped Test 3 still passed the algorithm
-- check (NMRL-PT tracker ePT-01). dtsRequireTest3 makes Test 3 part of the algorithm:
-- reactive for Positive, non-reactive for Indeterminate/Inconclusive, blank is a fail.
-- It is a toggle on the DTS scheme settings page, off unless set.
--
-- Zimbabwe's national algorithm needs all three tests, so it is switched on there. The
-- subquery is wrapped in a derived table because MySQL will not read the target table of
-- an UPDATE directly (same pattern as 7.6.21).
--
-- Idempotent: only fills the key while it is absent, so a value changed on the settings
-- page survives a replay.
UPDATE `scheme_config`
   SET `scheme_config_value` = JSON_SET(`scheme_config_value`, '$.dtsRequireTest3', 'yes')
 WHERE `scheme_config_name` = 'dts'
   AND JSON_EXTRACT(`scheme_config_value`, '$.dtsRequireTest3') IS NULL
   AND (SELECT `instance` FROM (SELECT `value` AS `instance` FROM `global_config` WHERE `name` = 'instance' LIMIT 1) AS `i`) = 'zimbabwe';
