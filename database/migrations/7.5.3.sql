-- Migration for version 7.5.3
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.5.3' WHERE `config` = 'app_version';

-- Vietnam (NIHE) — seed r_network_tiers with the NIHE Summary Report §2.1
-- "function" categories (National hospital / Provincial general hospital / CDC /
-- Military hospital / Research Institute / etc.). The Vietnam summary layout
-- groups participants by participant.network_tier → r_network_tiers.network_name
-- for the "Proportion of Participants by Function" pie chart.
--
-- ePT is distributed (every client install runs every migration), so the seeds
-- are gated by EXISTS(scheme_config WHERE dtsSchemeType='vietnam'). On non-Vietnam
-- installs (e.g. Zimbabwe, Malawi) the EXISTS predicate is false, every row's
-- SELECT yields zero rows, and r_network_tiers stays untouched.
--
-- Each INSERT is also guarded by NOT EXISTS(matching row) so re-running this
-- migration (migrate.php re-applies the current version on every run) leaves the
-- previously seeded rows in place rather than duplicating them.

INSERT INTO `r_network_tiers` (`network_name`)
SELECT 'National hospital' FROM DUAL
WHERE EXISTS (SELECT 1 FROM `scheme_config` WHERE `scheme_config_name` = 'dts' AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'vietnam')
  AND NOT EXISTS (SELECT 1 FROM `r_network_tiers` WHERE `network_name` = 'National hospital');

INSERT INTO `r_network_tiers` (`network_name`)
SELECT 'Provincial general hospital' FROM DUAL
WHERE EXISTS (SELECT 1 FROM `scheme_config` WHERE `scheme_config_name` = 'dts' AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'vietnam')
  AND NOT EXISTS (SELECT 1 FROM `r_network_tiers` WHERE `network_name` = 'Provincial general hospital');

INSERT INTO `r_network_tiers` (`network_name`)
SELECT 'Cottage hospital' FROM DUAL
WHERE EXISTS (SELECT 1 FROM `scheme_config` WHERE `scheme_config_name` = 'dts' AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'vietnam')
  AND NOT EXISTS (SELECT 1 FROM `r_network_tiers` WHERE `network_name` = 'Cottage hospital');

INSERT INTO `r_network_tiers` (`network_name`)
SELECT 'Private hospital' FROM DUAL
WHERE EXISTS (SELECT 1 FROM `scheme_config` WHERE `scheme_config_name` = 'dts' AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'vietnam')
  AND NOT EXISTS (SELECT 1 FROM `r_network_tiers` WHERE `network_name` = 'Private hospital');

INSERT INTO `r_network_tiers` (`network_name`)
SELECT 'Military hospital' FROM DUAL
WHERE EXISTS (SELECT 1 FROM `scheme_config` WHERE `scheme_config_name` = 'dts' AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'vietnam')
  AND NOT EXISTS (SELECT 1 FROM `r_network_tiers` WHERE `network_name` = 'Military hospital');

INSERT INTO `r_network_tiers` (`network_name`)
SELECT 'Centers for Disease Control and Prevention' FROM DUAL
WHERE EXISTS (SELECT 1 FROM `scheme_config` WHERE `scheme_config_name` = 'dts' AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'vietnam')
  AND NOT EXISTS (SELECT 1 FROM `r_network_tiers` WHERE `network_name` = 'Centers for Disease Control and Prevention');

INSERT INTO `r_network_tiers` (`network_name`)
SELECT 'Local Health center' FROM DUAL
WHERE EXISTS (SELECT 1 FROM `scheme_config` WHERE `scheme_config_name` = 'dts' AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'vietnam')
  AND NOT EXISTS (SELECT 1 FROM `r_network_tiers` WHERE `network_name` = 'Local Health center');

INSERT INTO `r_network_tiers` (`network_name`)
SELECT 'Research Institute' FROM DUAL
WHERE EXISTS (SELECT 1 FROM `scheme_config` WHERE `scheme_config_name` = 'dts' AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'vietnam')
  AND NOT EXISTS (SELECT 1 FROM `r_network_tiers` WHERE `network_name` = 'Research Institute');

INSERT INTO `r_network_tiers` (`network_name`)
SELECT 'Other' FROM DUAL
WHERE EXISTS (SELECT 1 FROM `scheme_config` WHERE `scheme_config_name` = 'dts' AND JSON_UNQUOTE(JSON_EXTRACT(`scheme_config_value`, '$.dtsSchemeType')) = 'vietnam')
  AND NOT EXISTS (SELECT 1 FROM `r_network_tiers` WHERE `network_name` = 'Other');
