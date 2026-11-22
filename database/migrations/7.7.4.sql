-- Migration for version 7.7.4
-- Amit Dugar - Nov 2026
UPDATE `system_config` SET `value` = '7.7.4' WHERE `config` = 'app_version';

-- The DBS (Dried Blood Spot - HIV Serology) scheme is removed from the code.
-- No server runs it. These statements remove its scheme row, result codes,
-- evaluation comments, test kit mappings and enrollments.
--
-- Every statement runs only when the server has no DBS shipments, so a server
-- that ever ran DBS keeps its data untouched.
--
-- The DBS tables (response_result_dbs, reference_result_dbs, reference_dbs_eia,
-- reference_dbs_wb) stay. Nothing reads them now. bin/heal-schema.php recreates
-- any table that sql/init.sql defines, so dropping them would not last.
-- r_dbs_eia and r_dbs_wb are still in use: the DTS EIA and Western blot lists
-- read them.
--
-- EID also uses dried blood spots. Nothing here touches EID.
--
-- Safe to re-run: once the rows are gone, the deletes match nothing.

DELETE FROM `scheme_testkit_map`
 WHERE `scheme_type` = 'dbs'
   AND NOT EXISTS (SELECT 1 FROM `shipment` WHERE `scheme_type` = 'dbs');

DELETE FROM `enrollments`
 WHERE `scheme_id` = 'dbs'
   AND NOT EXISTS (SELECT 1 FROM `shipment` WHERE `scheme_type` = 'dbs');

DELETE FROM `r_evaluation_comments`
 WHERE `scheme` = 'dbs'
   AND NOT EXISTS (SELECT 1 FROM `shipment` WHERE `scheme_type` = 'dbs');

DELETE FROM `r_possibleresult`
 WHERE `scheme_id` = 'dbs'
   AND NOT EXISTS (SELECT 1 FROM `shipment` WHERE `scheme_type` = 'dbs');

DELETE FROM `scheme_list`
 WHERE `scheme_id` = 'dbs'
   AND NOT EXISTS (SELECT 1 FROM `shipment` WHERE `scheme_type` = 'dbs');
