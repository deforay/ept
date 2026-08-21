-- Migration for version 7.6.19
-- Amit Dugar - Aug 2026
UPDATE `system_config` SET `value` = '7.6.19' WHERE `config` = 'app_version';

-- Completes the 7.6.18 backfill of "could not test" declarations to response_status
-- 'nottested'.
--
-- 7.6.18 skipped shipments whose status was 'queued' or 'processing', on the assumption that
-- a run might be mid-way through reading those rows. That is too cautious: 'queued' only
-- means the shipment is waiting for the report-generation cron to pick it up, and nothing
-- reads the rows until it does. Migrations also run as part of an upgrade, with the queue
-- workers down. The guard's only effect was to leave a live round half-converted -- on ePT
-- Zimbabwe, HIVDTS-2025-B kept 432 declarations stored the old way while its sibling HBV and
-- syphilis shipments in the same round were converted.
--
-- The join to shipment is dropped as well, so rows whose shipment record no longer exists
-- (11 of them on ePT Zimbabwe) are converted rather than left behind as the one remaining
-- source of the old value.
--
-- Count-neutral on the same basis as 7.6.18: every "came back to us" aggregate reads
-- IN ('responded', 'nottested'), so the flip does not move it. The individual-report gate
-- does narrow -- on HIVDTS-2025-B from 4067 to 3703 -- because it deliberately means "has
-- submitted results", and 3703 is exactly that shipment's submitted-results count. The 364
-- labs it drops declared they could not test and have no results to report on; they are
-- excluded from evaluation anyway once it completes.
--
-- Idempotent: matches only rows still carrying the old value, so a replay is a no-op.
UPDATE `shipment_participant_map`
   SET `response_status` = 'nottested'
 WHERE `is_pt_test_not_performed` = 'yes'
   AND `response_status` = 'responded';
