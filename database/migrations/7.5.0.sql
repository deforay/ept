-- Migration for version 7.5.0
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.5.0' WHERE `config` = 'app_version';

-- Heal the participant_feedback_answer.question_id foreign key on instances where it
-- still points at a non-unique parent column. On MySQL 8.4+ such an FK can no longer be
-- created, so dump/restore (and fresh schema loads) fail with error 6125
-- "Missing unique key for constraint 'participant_feedback_answer_ibfk_3'".
--
-- Across live instances the bad parent is one of two historical variants:
--   * r_participant_feedback_form_question_map(question_id)  -- non-unique KEY
--   * r_participant_feedback_form(question_id)               -- non-unique / since dropped
-- The correct parent is r_feedback_questions(question_id), which is that table's PRIMARY KEY.
--
-- Why this is a plain ADD CONSTRAINT and NOT the PREPARE/EXECUTE form used in 7.3.6:
-- migrate.php parses each migration with the PhpMyAdmin SQL parser, which silently drops
-- PREPARE/EXECUTE/DEALLOCATE statements -- so the 7.3.6 heal never actually ran under
-- migrate.php and the bad FK survived. migrate.php DOES have a dedicated idempotent handler
-- for `ALTER TABLE ... ADD CONSTRAINT ... FOREIGN KEY`: keyed on the FK name, it skips when
-- the FK already matches, and DROPs + re-adds when the FK exists but references a different
-- parent. The FK has always been named participant_feedback_answer_ibfk_3 (explicit in
-- 7.3.0 / 7.3.5 / init.sql), so name-based healing reliably repoints every variant here.
--
-- Idempotent: no-op when the FK is already correct or absent. Runs under
-- FOREIGN_KEY_CHECKS = 0 (set by migrate.php), so any pre-existing orphan rows in
-- participant_feedback_answer do not block the re-add.
ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `r_feedback_questions` (`question_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
