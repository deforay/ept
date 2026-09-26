-- Migration for version 7.6.23
-- Amit Dugar - Sep 2026
UPDATE `system_config` SET `value` = '7.6.23' WHERE `config` = 'app_version';

-- The profile-review flow copied an unchanged primary email into new_email, so the
-- account looked like it had a change awaiting verification and logins kept sending
-- "change your login email from X to X" mails. new_email now only holds a real pending
-- change; clear the rows left in the old state. Idempotent: a replay matches nothing.
UPDATE `data_manager`
   SET `new_email` = NULL
 WHERE `new_email` IS NOT NULL
   AND LOWER(TRIM(`new_email`)) = LOWER(TRIM(`primary_email`));
