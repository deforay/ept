-- Migration for version 7.7.2
-- Thanaseelan - Nov 2026
UPDATE `system_config` SET `value` = '7.7.2' WHERE `config` = 'app_version';

-- Zimbabwe and Malawi behaviour now keys on the instance setting, not on
-- report-layout (Report Configuration page), which only picks the PDF template
-- folder. Servers that never set instance but run the zimbabwe or malawi layout
-- get instance copied from the layout so they keep behaving the same.
-- Application_Service_Common::isInstance() also falls back to report-layout
-- while instance is empty, so this is belt and braces.
--
-- Safe to re-run: both statements only touch servers whose instance is still
-- empty or missing, and the first run fills it in.

-- instance row exists but is empty.
UPDATE `global_config` i
  JOIN `global_config` l ON l.`name` = 'report-layout'
   SET i.`value` = LOWER(TRIM(l.`value`))
 WHERE i.`name` = 'instance'
   AND (i.`value` IS NULL OR TRIM(i.`value`) = '')
   AND LOWER(TRIM(l.`value`)) IN ('zimbabwe', 'malawi');

-- instance row is missing.
INSERT INTO `global_config` (`name`, `value`, `context`)
SELECT 'instance', LOWER(TRIM(l.`value`)), 'global'
  FROM `global_config` l
 WHERE l.`name` = 'report-layout'
   AND LOWER(TRIM(l.`value`)) IN ('zimbabwe', 'malawi')
   AND NOT EXISTS (SELECT 1 FROM (SELECT `name` FROM `global_config` WHERE `name` = 'instance') x);
