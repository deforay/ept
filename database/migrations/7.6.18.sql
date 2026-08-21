-- Migration for version 7.6.18
-- Amit Dugar - Aug 2026
UPDATE `system_config` SET `value` = '7.6.18' WHERE `config` = 'app_version';

-- A lab that comes back to say it could not test -- no kits, nobody trained -- has taken
-- part, but it has not submitted results. Until now the save paths stamped
-- response_status = 'responded' on those submissions and recorded the declaration only in
-- is_pt_test_not_performed, so a declaration and a real set of results were indistinguishable
-- by status alone. The rule existed but sat commented out in five of the eight save paths,
-- which is why the same declaration is stored both ways inside a single scheme (on ePT
-- Zimbabwe: dts held 236 rows as 'nottested' against 1785 as 'responded', vl 18 against 18).
--
-- The save paths now route through Shipments::responseStatusForSubmission(), which stores
-- 'nottested'. This backfills the rows written before that, so history and new responses
-- read the same way.
--
-- Count-neutral by construction: every "came back to us" aggregate was widened in the same
-- change from `response_status LIKE 'responded'` to `IN ('responded', 'nottested')`, so the
-- set it sums over is unchanged by the flip. The two "submitted results" gates
-- (Evaluation::getIndividualReportsDataForPDF and the report generator's chunk bound) stay
-- narrow on purpose -- those labs did not submit results and are already excluded from
-- evaluation, so they were already filtered there by the is_excluded check.
--
-- Skips shipments still in flight (queued / processing). Their evaluation or report run may
-- be mid-way through reading these rows, and re-running this migration once they settle
-- picks them up. Finalized shipments keep their already-generated report files untouched --
-- nothing regenerates them, and the on-screen counts are unchanged per the note above.
--
-- Idempotent: matches only rows still carrying the old value, so a replay is a no-op.
UPDATE `shipment_participant_map` `sp`
  JOIN `shipment` `s` ON `s`.`shipment_id` = `sp`.`shipment_id`
   SET `sp`.`response_status` = 'nottested'
 WHERE `sp`.`is_pt_test_not_performed` = 'yes'
   AND `sp`.`response_status` = 'responded'
   AND `s`.`status` NOT IN ('queued', 'processing');
