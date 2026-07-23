-- Migration for version 7.6.15
-- Amit Dugar - Jul 2026
UPDATE `system_config` SET `value` = '7.6.15' WHERE `config` = 'app_version';

-- scheduled_jobs only recorded requested_on/completed_on, so the job-tracking
-- page had to pass off the request time as the start time — a job that waited
-- in the queue showed hours of phantom "runtime". started_on is stamped by
-- execute-job-queue.php the moment the runner flips the row to 'processing';
-- NULL for rows that never ran (and for history predating this column, where
-- the UI falls back to requested_on).
ALTER TABLE `scheduled_jobs` ADD COLUMN `started_on` DATETIME NULL DEFAULT NULL AFTER `requested_on`;
