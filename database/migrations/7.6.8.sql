-- Migration for version 7.6.8
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.8' WHERE `config` = 'app_version';

-- Heal a missing r_feedback_questions table. 7.3.0 created the rest of the feedback
-- feature (r_participant_feedback_form_question_map, participant_feedback_answer, ...)
-- with FKs pointing at r_feedback_questions, but never created the lookup table itself
-- -- it was assumed to already exist from the init.sql baseline. Instances whose schema
-- was not seeded from such an init.sql never got the table, so every ALTER against it
-- (the PK/FK heal below) dies with 1146 "table doesn't exist". Create it here
-- (IF NOT EXISTS -> no-op where it already exists). Definition mirrors init.sql, with
-- the PRIMARY KEY + AUTO_INCREMENT inlined so the ALTER below is a clean idempotent skip.
CREATE TABLE IF NOT EXISTS `r_feedback_questions` (
  `question_id` int NOT NULL AUTO_INCREMENT,
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `question_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `question_type` enum('text','datetime','dropdown','numeric') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `question_show_to` varchar(256) DEFAULT NULL,
  `question_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `response_attributes` json DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `modified_by` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
