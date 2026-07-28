-- Migration for version 7.6.16
-- Amit Dugar - Jul 2026
UPDATE `system_config` SET `value` = '7.6.16' WHERE `config` = 'app_version';

-- Heal shipments stuck on tb_form_generated='queued'. Historically the flag was
-- set when generation was queued but only ever cleared on a fully successful
-- run — a failed/cancelled/whitelist-rejected job left the shipment page showing
-- a disabled "Generating TB Forms..." button forever. The code now releases the
-- flag on every failure path and on cancel; this backfills the rows stranded
-- before that fix. Guarded so a job genuinely in flight during the upgrade
-- keeps its flag. Idempotent by construction (plain data UPDATE).
UPDATE `shipment` `s`
SET `s`.`tb_form_generated` = 'no'
WHERE `s`.`tb_form_generated` = 'queued'
  AND NOT EXISTS (
    SELECT 1 FROM `scheduled_jobs` `sj`
    WHERE `sj`.`status` IN ('pending', 'processing')
      AND `sj`.`job` = CONCAT('generate-tb-forms.php -s ', `s`.`shipment_id`)
  );

-- Thana 27-Jul-2026: Add table to track report downloads
CREATE TABLE `track_report_downloaded_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `downloaded_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `downloaded_by` varchar(256) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci

