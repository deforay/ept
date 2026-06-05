-- Migration for version 7.6.3
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.3' WHERE `config` = 'app_version';

-- Heal installs whose `user_login_history` table predates the `user_id` column.
-- On those servers the table already existed when 7.2.2 ran, so its
-- `CREATE TABLE IF NOT EXISTS` was a no-op and `user_id` was never added —
-- every admin/participant login then fataled on
--   "Unknown column 'user_id' in 'field list'"
-- because LoginController + UserLoginHistory::addLoginHistory always write it.
--
-- Idempotent: migrate.php treats 1060 (Duplicate column name) as success, so
-- this is a no-op on installs that already have the column.
ALTER TABLE `user_login_history`
    ADD COLUMN `user_id` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `login_context`;
