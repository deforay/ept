-- Migration for version 7.6.36
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.36' WHERE `config` = 'app_version';

-- An enrollment list belongs to one scheme, so the key must include the scheme.
-- The old key (list_name, participant_id) allowed a participant on a list for one
-- scheme only: enrolling them in a second scheme on the same list (usually Default)
-- was silently ignored. The new key holds every old key column, so no existing row
-- can collide.
--
-- Idempotent: migrate.php replaces the key only when it differs, adds it when the
-- table has none, and skips when it already matches.
ALTER TABLE `enrollments` DROP PRIMARY KEY, ADD PRIMARY KEY (`list_name`, `scheme_id`, `participant_id`);

-- Archived enrollment lists. A row here hides the list from Ship It's preset picker
-- and moves it to the Archived group on Scheme Enrollments. Its participants stay
-- enrolled. Idempotent: create_table_if_missing skips an existing table.
CREATE TABLE IF NOT EXISTS `enrollment_list_archive` (
  `scheme_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `list_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `archived_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_by` int DEFAULT NULL,
  PRIMARY KEY (`scheme_id`, `list_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
