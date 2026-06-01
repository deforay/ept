-- Migration for version 7.5.1
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.5.1' WHERE `config` = 'app_version';

-- Rename the generic "reason for not testing the PT panel" column. It was historically
-- prefixed `vl_` but is used by every scheme (vl/eid/dts/covid19/recency/tb/custom), so
-- rename it to the scheme-neutral `pt_not_tested_reason`.
--
-- Idempotent: migrate.php has a dedicated handler for `ALTER TABLE ... CHANGE old new ...`
-- (see bin/migrate.php) that skips the statement when the rename already happened
-- (old column gone, new column present) and otherwise runs it. So this is safe to re-run.
ALTER TABLE `shipment_participant_map`
  CHANGE `vl_not_tested_reason` `pt_not_tested_reason` INT DEFAULT NULL;

-- Re-introduce the "Other" reason. Historically "Other" was a hard-coded template option
-- whose value resolved to the sentinel id 9999 (see migration 7.3.5, which healed the old
-- 'other' string -> 0 conversions up to 9999). The dropdowns still post 9999 for "Other",
-- so seed a matching master row at that fixed id. `ntr_test_type` is NULL on purpose: the
-- option is hard-coded in each response.phtml, so it must NOT also surface via
-- getNotTestedReasons() (which filters on ntr_test_type) or it would appear twice. The row
-- exists purely so report joins on ntr_id resolve 9999 -> "Other" instead of "Unknown".
INSERT IGNORE INTO `r_response_not_tested_reasons`
  (`ntr_id`, `ntr_reason`, `ntr_test_type`, `collect_panel_receipt_date`, `reason_code`, `ntr_status`)
  VALUES (9999, 'Other', NULL, 'no', 'other', 'active');
