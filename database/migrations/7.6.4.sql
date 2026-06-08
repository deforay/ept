-- Migration for version 7.6.4
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.4' WHERE `config` = 'app_version';

-- Correct custom-test (generic) result codes saved in the wrong TEST/FINAL namespace.
--
-- Some schemes define two parallel option families in r_possibleresult, distinguished by
-- scheme_sub_group = 'TEST' vs 'FINAL', that share the same human label (e.g. mRDT has
-- MAL-T-* test codes and MAL-F-* final codes that both read "Positive"). A duplicate-dropdown
-- bug let the reference key, the reported (final) result, and the per-test result columns be
-- stored under the wrong family. Because evaluation compares reference_result == reported_result
-- by code, conceptually-correct answers were scored as failures, and after the dropdowns were
-- split the Test Result column rendered blank.
--
-- Canonical state:
--   * reference_result + reported_result -> FINAL namespace (these are what evaluation compares)
--   * result_1 / result_2 / result_3     -> TEST  namespace
--
-- SAFETY: each statement moves a value ONLY when it is literally sitting in the wrong
-- scheme_sub_group AND a same-label counterpart exists in the correct sub_group (matched on the
-- shared `response` label within the same scheme). It therefore:
--   * never assumes test == final, so responses whose test reading legitimately differs from the
--     final result are left untouched (their codes are already in the correct namespace),
--   * is a no-op for schemes with only one namespace or no TEST/FINAL split,
--   * is idempotent — a re-run (migrate.php re-applies the current version) finds nothing to move.
--
-- Finalized shipments are skipped: their results are locked, already scored and reported, so any
-- correction there must go through a proper re-evaluation rather than a blanket data update.
--
-- NOTE: this corrects the raw data only. Stored shipment_score / failure_reason were computed
-- against the old codes and are NOT recomputed here; affected (non-finalized) shipments must be
-- re-evaluated in the admin Evaluate screen for the corrected scores to take effect.

-- reference key -> FINAL namespace
UPDATE reference_result_generic_test ref
JOIN shipment s         ON s.shipment_id = ref.shipment_id AND COALESCE(s.status, '') <> 'finalized'
JOIN r_possibleresult t ON t.scheme_id = s.scheme_type AND t.result_code = ref.reference_result AND UPPER(TRIM(t.scheme_sub_group)) = 'TEST'
JOIN r_possibleresult f ON f.scheme_id = s.scheme_type AND f.response = t.response AND UPPER(TRIM(f.scheme_sub_group)) = 'FINAL'
SET ref.reference_result = f.result_code;

-- reported (final) result -> FINAL namespace
UPDATE response_result_generic_test res
JOIN shipment_participant_map sp ON sp.map_id = res.shipment_map_id
JOIN shipment s         ON s.shipment_id = sp.shipment_id AND COALESCE(s.status, '') <> 'finalized'
JOIN r_possibleresult t ON t.scheme_id = s.scheme_type AND t.result_code = res.reported_result AND UPPER(TRIM(t.scheme_sub_group)) = 'TEST'
JOIN r_possibleresult f ON f.scheme_id = s.scheme_type AND f.response = t.response AND UPPER(TRIM(f.scheme_sub_group)) = 'FINAL'
SET res.reported_result = f.result_code;

-- per-test result columns -> TEST namespace
UPDATE response_result_generic_test res
JOIN shipment_participant_map sp ON sp.map_id = res.shipment_map_id
JOIN shipment s         ON s.shipment_id = sp.shipment_id AND COALESCE(s.status, '') <> 'finalized'
JOIN r_possibleresult f ON f.scheme_id = s.scheme_type AND f.result_code = res.result_1 AND UPPER(TRIM(f.scheme_sub_group)) = 'FINAL'
JOIN r_possibleresult t ON t.scheme_id = s.scheme_type AND t.response = f.response AND UPPER(TRIM(t.scheme_sub_group)) = 'TEST'
SET res.result_1 = t.result_code;

UPDATE response_result_generic_test res
JOIN shipment_participant_map sp ON sp.map_id = res.shipment_map_id
JOIN shipment s         ON s.shipment_id = sp.shipment_id AND COALESCE(s.status, '') <> 'finalized'
JOIN r_possibleresult f ON f.scheme_id = s.scheme_type AND f.result_code = res.result_2 AND UPPER(TRIM(f.scheme_sub_group)) = 'FINAL'
JOIN r_possibleresult t ON t.scheme_id = s.scheme_type AND t.response = f.response AND UPPER(TRIM(t.scheme_sub_group)) = 'TEST'
SET res.result_2 = t.result_code;

UPDATE response_result_generic_test res
JOIN shipment_participant_map sp ON sp.map_id = res.shipment_map_id
JOIN shipment s         ON s.shipment_id = sp.shipment_id AND COALESCE(s.status, '') <> 'finalized'
JOIN r_possibleresult f ON f.scheme_id = s.scheme_type AND f.result_code = res.result_3 AND UPPER(TRIM(f.scheme_sub_group)) = 'FINAL'
JOIN r_possibleresult t ON t.scheme_id = s.scheme_type AND t.response = f.response AND UPPER(TRIM(t.scheme_sub_group)) = 'TEST'
SET res.result_3 = t.result_code;
