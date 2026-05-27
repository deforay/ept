-- Migration for version 7.4.6
-- Amit Dugar - May 2026
-- Vietnam (NIHE) DTS HIV serology algorithm: new result vocabulary.
-- Adds Weak Reactive (WR) and Indeterminate (IND) as per-test results, and
-- Inconclusive (INC) as a final interpretation. Seeded with display_context='none'
-- so they stay out of every other DTS scheme's dropdowns by default; the admin
-- enables them for the Vietnam scheme manually. See docs/vietnam-dts-algorithm.md.
-- NOTE: IND already exists as a DTS_FINAL value; here it is added as a DTS_TEST value.

UPDATE `system_config` SET `value` = '7.4.6' WHERE `config` = 'app_version';

INSERT INTO `r_possibleresult` (`scheme_id`, `scheme_sub_group`, `response`, `result_code`, `display_context`) VALUES
('dts', 'DTS_TEST', 'WEAK REACTIVE', 'WR', 'none'),
('dts', 'DTS_TEST', 'INDETERMINATE', 'IND', 'none'),
('dts', 'DTS_FINAL', 'INCONCLUSIVE', 'INC', 'none')
ON DUPLICATE KEY UPDATE `result_code` = `result_code`;
