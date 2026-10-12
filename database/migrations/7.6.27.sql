-- Migration for version 7.6.27
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.27' WHERE `config` = 'app_version';

-- Email Participants now writes one email per audience, so each audience gets its own
-- text. A participants email can Cc each lab's data managers on that lab's own email;
-- this records that choice. Emails saved earlier inferred it from having both
-- participants and data managers as audiences, which the send still honours.
--
-- Idempotent: migrate.php's ADD COLUMN handler no-ops when the column already exists.
ALTER TABLE `email_participants`
  ADD COLUMN `cc_data_managers` TINYINT(1) NOT NULL DEFAULT 0 AFTER `lab_filter`;

-- Mail templates are kept per audience too: one purpose (a reminder, a deadline notice)
-- can have a version for participants, one for data managers and one for PTCC
-- managers. Templates that existed before this version were written for participants,
-- and the emails ePT sends on its own keep using that version.
ALTER TABLE `mail_template`
  ADD COLUMN `audience` VARCHAR(16) NOT NULL DEFAULT 'participant' AFTER `mail_purpose`;
