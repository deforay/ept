-- Migration for version 7.6.10
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.10' WHERE `config` = 'app_version';

-- Rename the in-process report-generation queue statuses to clearer names:
--   not-evaluated -> generating, not-finalized -> finalizing.
-- These are transient states in queue_report_generation.status. Repoint any
-- in-flight rows so they keep matching the "in progress" / stale-job filters
-- after the code rename. Idempotent: re-running matches nothing.
UPDATE `queue_report_generation` SET `status` = 'generating' WHERE `status` = 'not-evaluated';
UPDATE `queue_report_generation` SET `status` = 'finalizing' WHERE `status` = 'not-finalized';
