-- Migration for version 7.6.13
-- Amit Dugar - Jul 2026
UPDATE `system_config` SET `value` = '7.6.13' WHERE `config` = 'app_version';

-- Report generation runs asynchronously via queue_report_generation. When a background
-- run crashed there was nowhere to record why, so the distribution page could only show
-- the button silently reverting with no explanation. Store the failure reason so the UI
-- can surface it (status = 'failed' + this message).
-- migrate.php makes ADD COLUMN idempotent (column_exists), so re-running is safe.
ALTER TABLE `queue_report_generation` ADD COLUMN `error_message` TEXT NULL DEFAULT NULL AFTER `previous_status`;

-- Durable end-of-run timestamp for the job-tracking page. processing_started_at (the real
-- start) was previously nulled on completion, so "Started At" fell back to the request time
-- and there was no way to compute how long a run took. We now keep processing_started_at
-- populated and record completed_at when a run finishes (success OR failure), so the page can
-- show start, end, and duration.
ALTER TABLE `queue_report_generation` ADD COLUMN `completed_at` DATETIME NULL DEFAULT NULL AFTER `last_heartbeat`;
