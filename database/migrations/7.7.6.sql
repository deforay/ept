-- Migration for version 7.7.6
-- Amit Dugar - Nov 2026
UPDATE `system_config` SET `value` = '7.7.6' WHERE `config` = 'app_version';

-- Fill response_status on responses saved by older versions.
--
-- Versions before 7.3 recorded a web response only in evaluation_status. Its third
-- character was '1' after a lab submitted results and '9' for no response. They set
-- response_status only for mobile-app responses, so web responses kept NULL. Current
-- code reads response_status alone. On an install upgraded from those versions,
-- every old round looked as if no lab had responded: report generation skipped all
-- participant reports and participation counts read zero.
--
-- A submitted response becomes 'responded', or 'nottested' when the lab marked the
-- test as not performed. Everything else becomes 'noresponse'. Lateness (the fourth
-- character) is not carried over: those rounds were evaluated with late responses
-- accepted, and 'late' would exclude them if a round is evaluated again.
--
-- Safe to re-run: only rows that still have no response_status change.

UPDATE `shipment_participant_map`
SET `response_status` = CASE
        WHEN SUBSTRING(`evaluation_status`, 3, 1) = '1'
            AND IFNULL(`is_pt_test_not_performed`, 'no') = 'yes' THEN 'nottested'
        WHEN SUBSTRING(`evaluation_status`, 3, 1) = '1' THEN 'responded'
        ELSE 'noresponse'
    END
WHERE `response_status` IS NULL
    AND `evaluation_status` IS NOT NULL
    AND `evaluation_status` <> '';
