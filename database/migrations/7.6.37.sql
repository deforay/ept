-- Migration for version 7.6.37
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.37' WHERE `config` = 'app_version';

-- Repeat the 7.6.9 repair of participant_feedback_answer_ibfk_3, which never ran.
--
-- 7.6.9 drops the FK by name and adds it again, so it binds to r_feedback_questions
-- even where information_schema reports the right parent but the data dictionary
-- holds the old one (MySQL 5.x to 8.0 in-place upgrades). Until 7.6.36, migrate.php
-- read DROP FOREIGN KEY as dropping a column named FOREIGN, found no such column and
-- skipped it. The ADD then saw an FK that looked correct and skipped too, so no
-- install was repaired.
--
-- The DROP now runs when the FK exists, and the ADD recreates it. migrate.php turns
-- off foreign-key checks for the run, so orphan answer rows do not block the ADD.
-- The primary-key guard from 7.6.9 is repeated so the parent column is unique.
-- Re-runs drop and re-add the FK again, which is harmless.
ALTER TABLE `r_feedback_questions` ADD PRIMARY KEY (`question_id`);
ALTER TABLE `participant_feedback_answer` DROP FOREIGN KEY `participant_feedback_answer_ibfk_3`;
ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `r_feedback_questions` (`question_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
