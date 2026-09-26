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

-- Bulk import makes up a login address (<unique id>@<instance host>) for participants and
-- data managers who have no email. Nothing can be delivered there, so those addresses are
-- stamped login_only and every mail path skips them like a bounced address. Re-running the
-- MODIFY with the same definition is a no-op. Existing rows are stamped by
-- run-once/mark-login-only-emails.php, since the instance host lives in application.ini.
ALTER TABLE `participant`
    MODIFY COLUMN `email_status` ENUM('unknown','valid','invalid_syntax','invalid_domain','hard_bounce','login_only') NOT NULL DEFAULT 'unknown',
    MODIFY COLUMN `additional_email_status` ENUM('unknown','valid','invalid_syntax','invalid_domain','hard_bounce','login_only') NOT NULL DEFAULT 'unknown';
ALTER TABLE `data_manager`
    MODIFY COLUMN `primary_email_status` ENUM('unknown','valid','invalid_syntax','invalid_domain','hard_bounce','login_only') NOT NULL DEFAULT 'unknown',
    MODIFY COLUMN `secondary_email_status` ENUM('unknown','valid','invalid_syntax','invalid_domain','hard_bounce','login_only') NOT NULL DEFAULT 'unknown';
