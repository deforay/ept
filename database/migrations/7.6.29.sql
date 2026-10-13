-- Migration for version 7.6.29
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.29' WHERE `config` = 'app_version';

-- How long a shipment's feedback form stays open after the shipment is finalized.
-- Finalizing used to fix it at 56 days; each form now carries its own number of days,
-- NULL meaning that same 56-day default, and copying a form copies it too.
--
-- Idempotent: migrate.php's ADD COLUMN handler no-ops when the column already exists.
ALTER TABLE `r_participant_feedback_form`
  ADD COLUMN `open_for_days` INT NULL DEFAULT NULL AFTER `form_show_to`;
