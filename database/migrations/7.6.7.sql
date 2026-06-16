-- Migration for version 7.6.7
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.7' WHERE `config` = 'app_version';

-- Add the primary key the live schema already carries but init.sql never declared.
-- Bulk enrollment relies on (list_name, participant_id) for its ON DUPLICATE KEY UPDATE
-- de-duplication; without this key a fresh install would silently insert duplicate rows
-- per participant. migrate.php routes ADD PRIMARY KEY through an idempotent handler:
-- a fresh table (no PK) gets it added, an existing matching PK is skipped.
ALTER TABLE `enrollments` ADD PRIMARY KEY (`list_name`, `participant_id`);
