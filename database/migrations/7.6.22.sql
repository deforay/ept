-- Migration for version 7.6.22
-- Amit Dugar - Sep 2026
UPDATE `system_config` SET `value` = '7.6.22' WHERE `config` = 'app_version';

-- DTS "updated-3-tests" accepted Test 1 and Test 2 reactive as a complete Positive and
-- never looked at Test 3, so a participant who skipped Test 3 still passed the algorithm
-- check (NMRL-PT tracker ePT-01). dtsRequireTest3 makes Test 3 part of the algorithm:
-- reactive for Positive, non-reactive for Indeterminate/Inconclusive, blank is a fail.
-- It is a toggle on the DTS scheme settings page, off unless set.
--
-- Zimbabwe's national algorithm needs all three tests, so it is switched on there, and
-- only while the DTS scheme type is updated-3-tests (the evaluator ignores it otherwise). The
-- subquery is wrapped in a derived table because MySQL will not read the target table of
-- an UPDATE directly (same pattern as 7.6.21).
--
-- Idempotent: only fills the key while it is absent, so a value changed on the settings
-- page survives a replay.
UPDATE `scheme_config`
   SET `scheme_config_value` = JSON_SET(`scheme_config_value`, '$.dtsRequireTest3', 'yes')
 WHERE `scheme_config_name` = 'dts'
   AND JSON_EXTRACT(`scheme_config_value`, '$.dtsRequireTest3') IS NULL
   AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'updated-3-tests'
   AND (SELECT `instance` FROM (SELECT `value` AS `instance` FROM `global_config` WHERE `name` = 'instance' LIMIT 1) AS `i`) = 'zimbabwe';

-- DTS documentation scoring now checks the order of the reported dates: a panel is
-- received, then rehydrated, then tested. A rehydration date before the receipt date loses
-- the rehydration-date point and needs its own corrective action; 12 means the date is
-- missing. 1-21 are taken on every instance, so 22 is used explicitly. INSERT IGNORE keeps
-- a replay (or an edited text) intact.
INSERT IGNORE INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`)
VALUES (22, 'Record the date the panel was actually rehydrated. A panel can only be rehydrated after it has been received.', 'Rehydration date is before the panel receipt date.');
