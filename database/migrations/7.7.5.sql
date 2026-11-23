-- Migration for version 7.7.5
-- Amit Dugar - Nov 2026
UPDATE `system_config` SET `value` = '7.7.5' WHERE `config` = 'app_version';

-- Drop r_dts_corrective_actions. DTS corrective actions come from the
-- Pt_Evaluation_Dts_CorrectiveAction enum since 7.7.4, and nothing reads the table.
-- dts_shipment_corrective_action_map stores the same IDs, so its rows stay valid.
--
-- sql/init.sql must be regenerated without this table in the same release.
-- bin/heal-schema.php recreates any table that init.sql defines.
--
-- Safe to re-run: IF EXISTS.

DROP TABLE IF EXISTS `r_dts_corrective_actions`;
