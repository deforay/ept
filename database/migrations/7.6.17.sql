-- Migration for version 7.6.17
-- Amit Dugar - Aug 2026
UPDATE `system_config` SET `value` = '7.6.17' WHERE `config` = 'app_version';

-- user_login_history.login_context was declared as enum('participant','admin','','')
-- with two identical empty members (a typo carried since 7.2.2). MySQL tolerates the
-- duplicate, but any statement that rebuilds the column rejects it -- most visibly
-- `ALTER TABLE ... CONVERT TO CHARACTER SET` from the collation step of `composer migrate`,
-- which aborts with ERROR 1291 "Column 'login_context' has duplicated value '' in ENUM"
-- on stricter servers (MariaDB), leaving the table unconverted.
--
-- Collapse the two empty members into one. Values convert by name, so existing rows
-- stored against the 4th member land on the surviving '' member; 'participant' and
-- 'admin' are untouched. Charset/collation are deliberately omitted so the column
-- inherits the table default and stays in step with the collation tool.
-- Idempotent: re-applying restates the same definition.
ALTER TABLE `user_login_history`
  MODIFY COLUMN `login_context` enum('participant','admin','') DEFAULT 'participant';
