-- Migration for version 7.6.9
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.9' WHERE `config` = 'app_version';

-- Force-heal participant_feedback_answer_ibfk_3 on instances where the 7.5.0 / 7.6.8
-- repair silently SKIPPED instead of repointing the FK.
--
-- Root cause: on databases upgraded in-place from MySQL 5.x to 8.0, this FK's
-- REFERENCED_TABLE_NAME in information_schema.KEY_COLUMN_USAGE desynced from the actual
-- InnoDB data dictionary. KEY_COLUMN_USAGE reported the correct r_feedback_questions,
-- while SHOW CREATE TABLE / mysqldump still emitted the bad
-- r_participant_feedback_form_question_map. migrate.php's ADD CONSTRAINT idempotence
-- (foreign_key_matches) trusts KEY_COLUMN_USAGE, so it saw "already matches" and skipped
-- the repair -- yet every dump kept carrying the bad parent, failing restore with 6125.
--
-- Fix: drop the FK BY NAME, then re-add it. DROP FOREIGN KEY routes through migrate.php's
-- drop handler, which checks existence via TABLE_CONSTRAINTS (stays accurate through the
-- desync) and removes whatever the dictionary actually holds; the subsequent ADD then
-- binds to the correct parent regardless of what KEY_COLUMN_USAGE claimed. migrate.php
-- no-ops the DROP when the FK is absent, and the ADD runs under FOREIGN_KEY_CHECKS = 0 so
-- orphan rows don't block it. The PK guard from 7.6.8 is repeated so the re-add always has
-- a unique parent column to bind to (idempotent: skipped when the PK already matches).
ALTER TABLE `r_feedback_questions` ADD PRIMARY KEY (`question_id`);
ALTER TABLE `participant_feedback_answer` DROP FOREIGN KEY `participant_feedback_answer_ibfk_3`;
ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `r_feedback_questions` (`question_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
