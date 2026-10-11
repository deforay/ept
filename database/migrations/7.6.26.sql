-- Migration for version 7.6.26
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.26' WHERE `config` = 'app_version';

-- How Email Participants reaches a data manager or PTCC who looks after several labs.
-- 'combined': one email per shipment, with all their labs in it as a table
-- ({{lab_table}}). 'per_lab': one email per lab per shipment, Cc'd on the lab's own
-- email when participants are selected too. Emails saved before this version went out
-- one per manager, which 'combined' keeps.
--
--
-- Which labs an email is about: 'all' in its shipments, or only those still to respond
-- ('pending': no response yet, or a draft not submitted), for reminders. Emails saved
-- before this version went to every lab, which 'all' keeps.
--
-- Idempotent: migrate.php's ADD COLUMN handler no-ops when the column already exists.
ALTER TABLE `email_participants`
  ADD COLUMN `manager_delivery` VARCHAR(16) NOT NULL DEFAULT 'combined' AFTER `receivers`;

ALTER TABLE `email_participants`
  ADD COLUMN `lab_filter` VARCHAR(16) NOT NULL DEFAULT 'all' AFTER `manager_delivery`;
