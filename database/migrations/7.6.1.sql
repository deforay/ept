-- Migration for version 7.6.1
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.1' WHERE `config` = 'app_version';

-- Test-kit master (r_testkitnames) field renames to match their new admin UI labels:
--   TestKit_ApprovalAgency (free-text agency) -> moh_approved          ("Is Ministry of Health Approved?", Yes/No)
--   CountryAdapted (0/1)                        -> pt_provider_validated ("Is PT Provider Validated?")
--
-- Idempotent: migrate.php has a dedicated handler for `ALTER TABLE ... CHANGE old new ...`
-- (see bin/migrate.php) that skips each rename once it has happened (old column gone, new
-- column present) and otherwise runs it. The data UPDATE below runs on the renamed column
-- and only touches rows that aren't already Yes/No/blank, so the whole file is safe to re-run.
ALTER TABLE `r_testkitnames`
  CHANGE `TestKit_ApprovalAgency` `moh_approved` VARCHAR(20) DEFAULT NULL;

ALTER TABLE `r_testkitnames`
  CHANGE `CountryAdapted` `pt_provider_validated` INT DEFAULT NULL;

-- The old "Approval Agency" field was free text (e.g. "WHO", "USFDA"); it is now a Yes/No
-- flag. Any non-blank legacy value becomes "Yes"; blanks/NULLs stay empty so they surface as
-- "-- Select --" until re-saved. NOT IN ('Yes','No') makes this safe to re-run.
UPDATE `r_testkitnames`
  SET `moh_approved` = 'Yes'
  WHERE `moh_approved` IS NOT NULL
    AND TRIM(`moh_approved`) <> ''
    AND `moh_approved` NOT IN ('Yes', 'No');
