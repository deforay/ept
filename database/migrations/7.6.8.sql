-- Migration for version 7.6.8
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.8' WHERE `config` = 'app_version';

-- Heal the missing PRIMARY KEY on r_feedback_questions(question_id).
-- init.sql declares this PK, but long-lived instances that grew via migrations
-- (not from init.sql) never had it added, so question_id is a plain non-unique
-- column there. That breaks the 7.5.0 participant_feedback_answer_ibfk_3 heal with
-- error 6125 "Missing unique key for constraint" -- the FK's parent column isn't
-- unique -- and the same failure surfaces on any dump-restore of such an instance.
-- migrate.php routes ADD PRIMARY KEY through an idempotent handler: a table missing
-- the PK gets it added, a matching PK is skipped.
ALTER TABLE `r_feedback_questions` ADD PRIMARY KEY (`question_id`);

-- Now that the parent column is guaranteed unique, (re-)assert the FK. On instances
-- where the 7.5.0 heal ran under --auto-continue, the old bad FK was dropped but the
-- re-add failed (6125) and was skipped, leaving NO ibfk_3 at all -- this restores it.
-- migrate.php's idempotent ADD CONSTRAINT handler skips when the FK already matches,
-- and drops + re-adds when it exists but points at a different parent. Runs under
-- FOREIGN_KEY_CHECKS = 0, so pre-existing orphan rows do not block the add.
ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `r_feedback_questions` (`question_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
