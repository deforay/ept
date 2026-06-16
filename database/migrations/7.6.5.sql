-- Migration for version 7.6.5
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.5' WHERE `config` = 'app_version';

-- Tag each bulk-participant-import run so the failure rows it writes to
-- participants_not_uploaded can be grouped/filtered per run. The importer stamps a
-- ULID here; the re-importable error export is named participant-import-errors-<id>.xlsx.
--
-- Idempotent: migrate.php has a dedicated `ALTER TABLE ... ADD [COLUMN]` handler
-- (add_column_if_missing) that skips the statement when the column already exists.
-- VARCHAR(48): generateULID() returns a 43-char id (ULID + suffix); 40 would truncate it.
ALTER TABLE `participants_not_uploaded`
  ADD COLUMN `import_run_id` VARCHAR(48) DEFAULT NULL AFTER `filename`;
