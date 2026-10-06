-- Migration for version 7.6.25
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.25' WHERE `config` = 'app_version';

-- Scheduled emails on Email Participants. Each row of email_participants is now one
-- email as the admin wrote it: its subject, message, shipments (shipment_code holds
-- shipment ids) and audiences (receivers). Sending it means working out the recipients
-- and queuing one temp_mail row per recipient. "Send now" does that at once. A scheduled
-- email waits as status 'scheduled' until scheduled_at, then the
-- dispatch-scheduled-emails job queues it. Recipients are worked out at that moment, so
-- labs enrolled after the email was scheduled are included.
--
-- Statuses: scheduled, paused, cancelled, dispatching (being queued), queued (in
-- temp_mail), failed. Rows that existed before this version were all queued as soon as
-- they were written, so the 'queued' default describes them correctly.
--
-- Idempotent: migrate.php's ADD COLUMN and ADD INDEX handlers no-op when the column or
-- index already exists.
ALTER TABLE `email_participants`
  ADD COLUMN `status` VARCHAR(16) NOT NULL DEFAULT 'queued' AFTER `shipment_code`,
  ADD COLUMN `scheduled_at` DATETIME NULL DEFAULT NULL AFTER `status`,
  ADD COLUMN `dispatched_at` DATETIME NULL DEFAULT NULL AFTER `scheduled_at`,
  ADD COLUMN `queued_count` INT NULL DEFAULT NULL AFTER `dispatched_at`,
  ADD COLUMN `failure_reason` TEXT NULL DEFAULT NULL AFTER `queued_count`,
  ADD COLUMN `updated_by` VARCHAR(256) NULL DEFAULT NULL AFTER `initiated_by`,
  ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL AFTER `updated_by`;

ALTER TABLE `email_participants`
  ADD INDEX `idx_email_participants_status_scheduled` (`status`, `scheduled_at`);

-- Links each queued message back to the email it came from. This lets the Scheduled
-- list show how far a large send has got, and lets an admin hold ('held') or cancel
-- ('cancelled') the messages of a send still in progress. send-emails.php only picks up
-- 'pending' rows, so held and cancelled rows are never sent. NULL for mail queued from
-- anywhere else.
ALTER TABLE `temp_mail`
  ADD COLUMN `email_participant_id` INT NULL DEFAULT NULL AFTER `temp_id`;

ALTER TABLE `temp_mail`
  ADD INDEX `idx_temp_mail_email_participant` (`email_participant_id`, `status`);
